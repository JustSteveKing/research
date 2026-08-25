<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\FactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $thread_id
 * @property int|null $source_id
 * @property int|null $research_run_id
 * @property string $statement
 * @property array<int, string>|null $tags
 * @property float $confidence
 * @property array<int, float>|null $embedding
 * @property CarbonInterface|null $pinned_at
 * @property CarbonInterface|null $edited_at
 * @property CarbonInterface|null $dismissed_at
 * @property CarbonInterface|null $asserted_at
 * @property-read Source|null $source
 * @property-read Thread $thread
 */
#[Fillable([
    'thread_id',
    'source_id',
    'research_run_id',
    'statement',
    'tags',
    'confidence',
    'embedding',
    'pinned_at',
    'edited_at',
    'dismissed_at',
    'asserted_at',
])]
class Fact extends Model
{
    /** @use HasFactory<FactFactory> */
    use HasFactory;

    /**
     * Facts the reader has not thrown away. Dismissed rows stay in the table
     * so distillation keeps treating them as already seen.
     *
     * @param  Builder<Fact>  $query
     * @return Builder<Fact>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }

    /**
     * Facts a rebuild must not throw away, because a person made them.
     *
     * @param  Builder<Fact>  $query
     * @return Builder<Fact>
     */
    #[Scope]
    protected function curated(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNotNull('pinned_at')
            ->orWhereNotNull('edited_at')
            ->orWhereNotNull('dismissed_at'));
    }

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Pairs where this fact is the lower id. Read both sides through
     * Contradiction::forFacts() rather than these relations.
     *
     * @return HasMany<Contradiction, $this>
     */
    public function contradictions(): HasMany
    {
        return $this->hasMany(Contradiction::class);
    }

    /**
     * @return HasMany<Contradiction, $this>
     */
    public function contradictedBy(): HasMany
    {
        return $this->hasMany(Contradiction::class, 'other_fact_id');
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    /**
     * When the claim was true: the source's publication date where we have
     * one, otherwise when the fact was distilled.
     */
    public function assertedAt(): CarbonInterface
    {
        return $this->asserted_at ?? $this->created_at ?? now();
    }

    /**
     * Claims age out. A pinned fact is the reader's own, so it never goes
     * stale on its own.
     */
    public function isStale(): bool
    {
        if ($this->isPinned()) {
            return false;
        }

        return $this->assertedAt()->diffInDays(now()) >= (int) config('research.stale_after_days');
    }

    public function ageInDays(): int
    {
        return (int) $this->assertedAt()->diffInDays(now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'embedding' => 'array',
            'confidence' => 'float',
            'pinned_at' => 'datetime',
            'edited_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'asserted_at' => 'datetime',
        ];
    }
}
