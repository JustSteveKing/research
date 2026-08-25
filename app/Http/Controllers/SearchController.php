<?php

namespace App\Http\Controllers;

use App\Models\Fact;
use App\Models\Source;
use App\Models\Thread;
use App\Services\Ollama\OllamaClient;
use App\Support\FullTextQuery;
use App\Support\Vector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Feeds the command palette. Returns JSON rather than an Inertia page so the
 * palette can search without navigating.
 *
 * Keyword matching runs first through the FTS5 indexes — it is exact and
 * costs nothing. When that comes back thin and the reader has typed enough to
 * express an idea, the facts are searched again by meaning, so "how much do
 * they cost" finds a fact that never says "cost".
 */
class SearchController extends Controller
{
    protected const FACT_LIMIT = 8;

    /** Below this, a keyword search has not really answered the question. */
    protected const SEMANTIC_TRIGGER = 3;

    public function __invoke(Request $request, OllamaClient $ollama): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json(['threads' => [], 'facts' => [], 'sources' => [], 'semantic' => false]);
        }

        $facts = $this->facts($term);
        $semantic = false;

        if ($facts->count() < self::SEMANTIC_TRIGGER && str_word_count($term) >= 3) {
            $facts = $this->semanticFacts($term, $facts, $ollama);
            $semantic = $facts->isNotEmpty();
        }

        return response()->json([
            'threads' => $this->threads($term),
            'facts' => $facts->map(fn (Fact $fact): array => [
                'id' => $fact->id,
                'thread_id' => $fact->thread_id,
                'thread_title' => $fact->thread->title,
                'statement' => $fact->statement,
            ])->values()->all(),
            'sources' => $this->sources($term),
            // The palette says so, because a fuzzy match needs different trust
            // from an exact one.
            'semantic' => $semantic,
        ]);
    }

    /**
     * @return list<Thread>
     */
    protected function threads(string $term): array
    {
        $ids = FullTextQuery::ids('threads', $term, 6);

        $query = Thread::query()->when(
            $ids !== [] || FullTextQuery::available(),
            fn (Builder $query) => $query->whereIn('id', $ids),
            fn (Builder $query) => $this->like($query, $term, ['title', 'seed_question']),
        );

        return array_values($query->forRail()->limit(6)->get(['id', 'title', 'status'])->all());
    }

    /**
     * @return Collection<int, Fact>
     */
    protected function facts(string $term): Collection
    {
        $ids = FullTextQuery::ids('facts', $term, self::FACT_LIMIT);

        return Fact::query()
            ->active()
            ->with('thread:id,title')
            ->when(
                $ids !== [] || FullTextQuery::available(),
                fn (Builder $query) => $query->whereIn('id', $ids),
                fn (Builder $query) => $this->like($query, $term, ['statement']),
            )
            ->orderByDesc('confidence')
            ->limit(self::FACT_LIMIT)
            ->get(['id', 'thread_id', 'statement', 'confidence']);
    }

    /**
     * Rank every fact by meaning instead of wording. Only reached when the
     * keyword pass came back nearly empty, so the embedding call is rare.
     *
     * @param  Collection<int, Fact>  $keyword
     * @return Collection<int, Fact>
     */
    protected function semanticFacts(string $term, Collection $keyword, OllamaClient $ollama): Collection
    {
        $embedding = $ollama->embedOne($term);

        if ($embedding === null) {
            return $keyword;
        }

        /** @var Collection<int, Fact> $candidates */
        $candidates = Fact::query()
            ->active()
            ->whereNotNull('embedding')
            ->with('thread:id,title')
            ->get(['id', 'thread_id', 'statement', 'embedding']);

        $ranked = array_filter(
            Vector::topK($embedding, $candidates->pluck('embedding', 'id')->all(), self::FACT_LIMIT),
            fn (float $score): bool => $score >= (float) config('research.cross_thread_min_similarity'),
        );

        if ($ranked === []) {
            return $keyword;
        }

        return $candidates
            ->whereIn('id', array_keys($ranked))
            ->sortByDesc(fn (Fact $fact): float => $ranked[$fact->id] ?? 0.0)
            ->values();
    }

    /**
     * @return list<Source>
     */
    protected function sources(string $term): array
    {
        $ids = FullTextQuery::ids('sources', $term, 6);

        return array_values(Source::query()
            ->when(
                $ids !== [] || FullTextQuery::available(),
                fn (Builder $query) => $query->whereIn('id', $ids),
                fn (Builder $query) => $this->like($query, $term, ['title', 'url']),
            )
            ->orderByDesc('cited_count')
            ->limit(6)
            ->get(['id', 'thread_id', 'title', 'url', 'domain'])
            ->all());
    }

    /**
     * Fallback for databases without an FTS index.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $columns
     */
    protected function like(Builder $query, string $term, array $columns): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($columns, $like): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', $like);
            }
        });
    }
}
