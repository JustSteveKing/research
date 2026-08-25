<?php

namespace App\Jobs;

use App\Enums\KnowledgeState;
use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Services\Ollama\OllamaClient;
use App\Support\Vector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Decides what a run's new facts actually are, relative to everything already
 * known: a repeat, a conflict, or something new.
 *
 * This is the only place that judgement can be made. Cosine similarity gets
 * the pairs worth looking at but cannot tell them apart — measured against
 * nomic-embed-text, "Toyota targets 2027" and "Toyota targets 2030" score
 * 0.98, slightly *higher* than an honest reword of the same claim. So
 * distillation stores near-duplicates and this job resolves them: duplicates
 * are deleted, conflicts are recorded, and anything genuinely distinct stays.
 *
 * Candidates come from every thread, because a clash with another topic's
 * research counts just as much as one at home.
 */
class DetectContradictionsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    /** Thread deleted mid-flight: drop the job rather than failing it. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public ResearchRun $run)
    {
        $this->onQueue(config('research.knowledge_queue'));
    }

    public function handle(OllamaClient $ollama): void
    {
        $run = $this->run->fresh();

        if ($run === null) {
            return;
        }

        $fresh = Fact::query()
            ->active()
            ->where('research_run_id', $run->getKey())
            ->whereNotNull('embedding')
            ->get();

        if ($fresh->isEmpty()) {
            return;
        }

        $run->thread->markKnowledgeState(KnowledgeState::Checking);

        $pairs = $this->candidatePairs($fresh);

        if ($pairs === []) {
            return;
        }

        try {
            $verdicts = $ollama->structured([
                ['role' => 'system', 'content' => $this->instructions()],
                ['role' => 'user', 'content' => "Pairs to judge:\n".$this->describePairs($pairs)],
            ], $this->schema());
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $this->apply($run, $pairs, $verdicts['verdicts'] ?? []);
    }

    protected function instructions(): string
    {
        return <<<'TXT'
        You compare pairs of factual statements and decide how they relate. Answer for every pair you are given, keyed by its index, and return JSON only.

        relation = "duplicate" when both statements make the same claim and one adds nothing the other lacks. Rewording, reordering and small changes in phrasing are still duplicates.

        relation = "contradiction" when the two cannot both be true of the same thing at the same time: different values for one measurement, different dates for one event, one asserting what the other denies, opposite directions of change.

        relation = "distinct" for everything else — one adds detail the other lacks, they cover different subjects or time periods, or one is a forecast and the other a present observation.

        When a pair contradicts, say in one short sentence what the disagreement is.
        TXT;
    }

    /**
     * Fact pairs close enough to be about the same claim. There is no upper
     * bound: the most similar pairs are exactly where duplicates and flat
     * contradictions are indistinguishable, which is why they are judged.
     *
     * @param  Collection<int, Fact>  $fresh
     * @return list<array{fact: Fact, other: Fact, similarity: float}>
     */
    protected function candidatePairs(Collection $fresh): array
    {
        $floor = (float) config('research.contradiction_min_similarity');

        $known = Fact::query()
            ->active()
            ->whereNotNull('embedding')
            ->whereNotIn('id', $fresh->modelKeys())
            ->with('thread:id,title')
            ->get();

        if ($known->isEmpty()) {
            return [];
        }

        $seen = $this->existingPairs($fresh);
        $pairs = [];

        foreach ($fresh as $fact) {
            foreach ($known as $other) {
                $similarity = Vector::cosine($fact->embedding ?? [], $other->embedding ?? []);

                if ($similarity < $floor) {
                    continue;
                }

                [$low, $high] = Contradiction::orderPair($fact->getKey(), $other->getKey());

                if (isset($seen["{$low}:{$high}"])) {
                    continue;
                }

                $seen["{$low}:{$high}"] = true;
                $pairs[] = ['fact' => $fact, 'other' => $other, 'similarity' => $similarity];
            }
        }

        usort($pairs, fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return array_slice($pairs, 0, (int) config('research.contradiction_pairs_per_run'));
    }

    /**
     * Pairs already judged, so a rebuild does not ask the model twice.
     * Resolved pairs count as judged: the reader settled them.
     *
     * @param  Collection<int, Fact>  $facts
     * @return array<string, true>
     */
    protected function existingPairs(Collection $facts): array
    {
        $factIds = $facts->modelKeys();

        return Contradiction::query()
            ->where(fn ($query) => $query->whereIn('fact_id', $factIds)->orWhereIn('other_fact_id', $factIds))
            ->get(['fact_id', 'other_fact_id'])
            ->mapWithKeys(fn (Contradiction $row): array => ["{$row->fact_id}:{$row->other_fact_id}" => true])
            ->all();
    }

    /**
     * @param  list<array{fact: Fact, other: Fact, similarity: float}>  $pairs
     */
    protected function describePairs(array $pairs): string
    {
        $lines = [];

        foreach ($pairs as $index => $pair) {
            $lines[] = sprintf(
                "%d.\n  A: %s\n  B: %s",
                $index,
                $pair['fact']->statement,
                $pair['other']->statement,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{fact: Fact, other: Fact, similarity: float}>  $pairs
     * @param  array<int, mixed>  $verdicts
     */
    protected function apply(ResearchRun $run, array $pairs, array $verdicts): void
    {
        $dropped = [];

        foreach ($verdicts as $verdict) {
            if (! is_array($verdict)) {
                continue;
            }

            $pair = $pairs[(int) ($verdict['index'] ?? -1)] ?? null;

            if ($pair === null || in_array($pair['fact']->getKey(), $dropped, true)) {
                continue;
            }

            match ($verdict['relation'] ?? 'distinct') {
                'duplicate' => $dropped[] = $this->drop($pair['fact']),
                'contradiction' => $this->record($run, $pair, $verdict['note'] ?? null),
                default => null,
            };
        }
    }

    /**
     * The newly distilled side of a duplicate pair is the one that goes: the
     * older fact may already be cited, pinned or part of a flagged conflict.
     * Returns the fact's id so the caller can stop judging pairs against a
     * row that no longer exists.
     */
    protected function drop(Fact $fact): int
    {
        $fact->delete();

        return (int) $fact->getKey();
    }

    /**
     * @param  array{fact: Fact, other: Fact, similarity: float}  $pair
     */
    protected function record(ResearchRun $run, array $pair, mixed $note): void
    {
        [$low, $high] = Contradiction::orderPair($pair['fact']->getKey(), $pair['other']->getKey());

        Contradiction::query()->updateOrCreate(
            ['fact_id' => $low, 'other_fact_id' => $high],
            [
                'detected_run_id' => $run->getKey(),
                'similarity' => $pair['similarity'],
                'note' => is_string($note) ? str($note)->limit(300)->toString() : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'verdicts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'index' => ['type' => 'integer'],
                            'relation' => ['type' => 'string', 'enum' => ['duplicate', 'contradiction', 'distinct']],
                            'note' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['index', 'relation'],
                    ],
                ],
            ],
            'required' => ['verdicts'],
        ];
    }
}
