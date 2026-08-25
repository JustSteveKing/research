<?php

namespace App\Services\Research;

use App\Models\Fact;
use App\Models\Thread;
use App\Services\Ollama\OllamaClient;
use App\Support\Vector;
use Illuminate\Database\Eloquent\Collection;

/**
 * Picks the facts most relevant to a question so chat answers stay grounded in
 * what has already been researched.
 *
 * Threads are not sealed. After the thread's own facts are ranked, the same
 * question is put to every other thread's knowledge, and anything that clears
 * the similarity floor is carried across. Foreign facts keep their thread
 * loaded so callers can label where an answer came from.
 */
class KnowledgeRetriever
{
    public function __construct(
        protected OllamaClient $ollama,
        protected RelatedThreads $related,
    ) {}

    /**
     * @return Collection<int, Fact>
     */
    public function retrieve(Thread $thread, string $question, ?int $limit = null): Collection
    {
        $limit ??= (int) config('research.chat_context_facts');

        /** @var Collection<int, Fact> $facts */
        $facts = $thread->facts()->active()->with('source:id,url,title')->get();

        $embedding = $this->ollama->embedOne($question);

        if ($embedding === null) {
            // No embeddings means no cross-thread search either: word overlap
            // across unrelated topics matches far too eagerly to be useful.
            return $facts->isEmpty() ? $facts : $this->keywordMatch($facts, $question, $limit);
        }

        $local = $this->rank($facts, $embedding, $limit, 0.0);
        $foreign = $this->crossThread($thread, $embedding);

        if ($local->isEmpty() && $foreign->isEmpty()) {
            return $facts->isEmpty() ? $facts : $this->keywordMatch($facts, $question, $limit);
        }

        return $local->concat($foreign);
    }

    /**
     * Facts other threads already hold that answer this question.
     *
     * Threads are filtered first, on their own topic vector: comparing a few
     * dozen thread vectors is cheap, and it keeps the fact scan proportional
     * to the threads that could plausibly answer rather than to everything
     * ever researched. A thread with no brief yet has no vector, so it is
     * searched anyway rather than being silently excluded.
     *
     * @param  array<int, float>  $embedding
     * @return Collection<int, Fact>
     */
    protected function crossThread(Thread $thread, array $embedding): Collection
    {
        $limit = (int) config('research.cross_thread_facts');

        if ($limit < 1) {
            /** @var Collection<int, Fact> $none */
            $none = new Collection;

            return $none;
        }

        $threadIds = $this->related->candidateThreadIds($thread, $embedding);

        if ($threadIds === []) {
            /** @var Collection<int, Fact> $none */
            $none = new Collection;

            return $none;
        }

        /** @var Collection<int, Fact> $candidates */
        $candidates = Fact::query()
            ->active()
            ->whereIn('thread_id', $threadIds)
            ->whereNotNull('embedding')
            ->with(['source:id,url,title', 'thread:id,title'])
            ->get();

        return $this->rank(
            $candidates,
            $embedding,
            $limit,
            (float) config('research.cross_thread_min_similarity'),
        );
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @param  array<int, float>  $embedding
     * @return Collection<int, Fact>
     */
    protected function rank(Collection $facts, array $embedding, int $limit, float $floor): Collection
    {
        if ($facts->isEmpty() || $limit < 1) {
            /** @var Collection<int, Fact> $none */
            $none = new Collection;

            return $none;
        }

        $ranked = array_filter(
            Vector::topK($embedding, $facts->pluck('embedding', 'id')->all(), $limit),
            fn (float $score): bool => $score >= $floor,
        );

        return $facts
            ->whereIn('id', array_keys($ranked))
            ->sortByDesc(fn (Fact $fact): float => $ranked[$fact->id] ?? 0.0)
            ->values();
    }

    /**
     * Fallback used when embeddings are unavailable.
     *
     * @param  Collection<int, Fact>  $facts
     * @return Collection<int, Fact>
     */
    protected function keywordMatch(Collection $facts, string $question, int $limit): Collection
    {
        $words = collect(preg_split('/\W+/', mb_strtolower($question), flags: PREG_SPLIT_NO_EMPTY) ?: [])
            ->filter(fn (string $word): bool => mb_strlen($word) > 3);

        return $facts
            ->sortByDesc(fn (Fact $fact): int => $words->filter(
                fn (string $word): bool => str_contains(mb_strtolower($fact->statement), $word)
            )->count())
            ->take($limit)
            ->values();
    }
}
