<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ContradictionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pair of facts the local model judged to disagree. Stored once per pair,
 * lowest fact id first, so the same clash is never flagged twice.
 *
 * @property int $id
 * @property int $fact_id
 * @property int $other_fact_id
 * @property int|null $detected_run_id
 * @property float $similarity
 * @property string|null $note
 * @property CarbonInterface|null $resolved_at
 * @property-read Fact $fact
 * @property-read Fact $otherFact
 */
#[Fillable([
    'fact_id',
    'other_fact_id',
    'detected_run_id',
    'similarity',
    'note',
    'resolved_at',
])]
class Contradiction extends Model
{
    /** @use HasFactory<ContradictionFactory> */
    use HasFactory;

    /**
     * Store the pair in a stable order so (a, b) and (b, a) collide on the
     * unique index instead of both being recorded.
     *
     * @return array{int, int}
     */
    public static function orderPair(int $factId, int $otherFactId): array
    {
        return $factId <= $otherFactId ? [$factId, $otherFactId] : [$otherFactId, $factId];
    }

    /**
     * Every unresolved pair touching one of the given facts, from either side.
     *
     * @param  Collection<int, Fact>|list<int>  $facts
     * @return Builder<Contradiction>
     */
    public static function forFacts(Collection|array $facts): Builder
    {
        $factIds = $facts instanceof Collection
            ? array_values(array_map(intval(...), $facts->modelKeys()))
            : $facts;

        return self::query()
            ->whereNull('resolved_at')
            ->where(fn (Builder $query) => $query
                ->whereIn('fact_id', $factIds)
                ->orWhereIn('other_fact_id', $factIds));
    }

    /**
     * @return BelongsTo<Fact, $this>
     */
    public function fact(): BelongsTo
    {
        return $this->belongsTo(Fact::class);
    }

    /**
     * @return BelongsTo<Fact, $this>
     */
    public function otherFact(): BelongsTo
    {
        return $this->belongsTo(Fact::class, 'other_fact_id');
    }

    /**
     * The side of the pair that is not the given fact.
     */
    public function counterpartTo(Fact $fact): Fact
    {
        return $this->fact_id === $fact->getKey() ? $this->otherFact : $this->fact;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'similarity' => 'float',
            'resolved_at' => 'datetime',
        ];
    }
}
