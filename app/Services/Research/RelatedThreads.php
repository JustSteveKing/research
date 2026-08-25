<?php

namespace App\Services\Research;

use App\Models\Fact;
use App\Models\Thread;
use App\Support\Vector;
use Illuminate\Support\Collection;

/**
 * Finds the other threads working on the same ground.
 *
 * Matching is on the thread topic vector written by SynthesizeThreadBriefJob,
 * so a thread only becomes discoverable once it has a brief. The shared fact
 * count is counted separately: two threads can be about the same subject
 * without either having cited the other's sources yet.
 */
class RelatedThreads
{
    /**
     * @return list<array{id: int, title: string, similarity: float, facts_count: int, shared_domains: list<string>}>
     */
    public function for(Thread $thread): array
    {
        $embedding = $thread->embedding;

        if (! is_array($embedding) || $embedding === []) {
            return [];
        }

        /** @var Collection<int, Thread> $others */
        $others = Thread::query()
            ->whereKeyNot($thread->getKey())
            ->whereNotNull('embedding')
            ->withCount(['facts' => fn ($query) => $query->active()])
            ->get();

        if ($others->isEmpty()) {
            return [];
        }

        $ranked = Vector::topK(
            $embedding,
            $others->pluck('embedding', 'id')->all(),
            (int) config('research.related_threads'),
        );

        $floor = (float) config('research.related_thread_min_similarity');
        $domains = $this->domainsFor($thread);

        return array_values($others
            ->filter(fn (Thread $other): bool => ($ranked[$other->getKey()] ?? 0.0) >= $floor)
            ->sortByDesc(fn (Thread $other): float => $ranked[$other->getKey()] ?? 0.0)
            ->map(fn (Thread $other): array => [
                'id' => (int) $other->getKey(),
                'title' => $other->title,
                'similarity' => round($ranked[$other->getKey()], 3),
                'facts_count' => (int) $other->facts_count,
                'shared_domains' => $this->sharedDomains($domains, $other),
            ])
            ->all());
    }

    /**
     * Threads worth searching for a given query or topic vector.
     *
     * Comparing a few dozen thread vectors is cheap, and it keeps any fact
     * scan proportional to the threads that could plausibly answer rather
     * than to everything ever researched. A thread with no brief has no
     * vector yet, so it is searched rather than silently excluded — the
     * alternative is that a thread's first findings are invisible everywhere
     * else until the brief lands.
     *
     * @param  array<int, float>  $embedding
     * @return list<int>
     */
    public function candidateThreadIds(Thread $thread, array $embedding): array
    {
        $floor = (float) config('research.related_thread_min_similarity');

        return array_values(Thread::query()
            ->whereKeyNot($thread->getKey())
            ->get(['id', 'embedding'])
            ->filter(function (Thread $other) use ($embedding, $floor): bool {
                $vector = $other->embedding;

                if (! is_array($vector) || $vector === []) {
                    return true;
                }

                return Vector::cosine($embedding, $vector) >= $floor;
            })
            ->map(fn (Thread $other): int => (int) $other->getKey())
            ->all());
    }

    /**
     * Facts from other threads that a rebuild would not find, ranked against
     * this thread's own topic. Used to seed the reading pane's borrowed panel.
     *
     * @return Collection<int, Fact>
     */
    public function borrowedFacts(Thread $thread, int $limit = 6): Collection
    {
        $embedding = $thread->embedding;

        if (! is_array($embedding) || $embedding === []) {
            return new Collection;
        }

        $threadIds = $this->candidateThreadIds($thread, $embedding);

        if ($threadIds === []) {
            return new Collection;
        }

        $candidates = Fact::query()
            ->active()
            ->whereIn('thread_id', $threadIds)
            ->whereNotNull('embedding')
            ->with(['thread:id,title', 'source:id,url,title,domain'])
            ->get();

        $ranked = array_filter(
            Vector::topK($embedding, $candidates->pluck('embedding', 'id')->all(), $limit),
            fn (float $score): bool => $score >= (float) config('research.cross_thread_min_similarity'),
        );

        return $candidates
            ->whereIn('id', array_keys($ranked))
            ->sortByDesc(fn (Fact $fact): float => $ranked[$fact->getKey()] ?? 0.0)
            ->values();
    }

    /**
     * @return list<string>
     */
    protected function domainsFor(Thread $thread): array
    {
        return array_values(array_unique(array_map(
            strval(...),
            $thread->sources()->whereNotNull('domain')->pluck('domain')->all(),
        )));
    }

    /**
     * @param  list<string>  $domains
     * @return list<string>
     */
    protected function sharedDomains(array $domains, Thread $other): array
    {
        if ($domains === []) {
            return [];
        }

        return array_values(array_map(strval(...), $other->sources()
            ->whereIn('domain', $domains)
            ->distinct()
            ->limit(4)
            ->pluck('domain')
            ->all()));
    }
}
