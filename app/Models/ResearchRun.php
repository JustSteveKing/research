<?php

namespace App\Models;

use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use Database\Factories\ResearchRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $thread_id
 * @property int|null $parent_run_id
 * @property string $query
 * @property ResearchMode $mode
 * @property int $depth
 * @property bool $is_watch
 * @property RunStatus $status
 * @property string|null $phase
 * @property string|null $report
 * @property array<string, mixed>|null $metadata
 * @property int $credits_cost
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'thread_id',
    'parent_run_id',
    'query',
    'mode',
    'depth',
    'is_watch',
    'status',
    'credits_cost',
])]
class ResearchRun extends Model
{
    /** @use HasFactory<ResearchRunFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return BelongsTo<ResearchRun, $this>
     */
    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }

    /**
     * @return HasMany<ResearchRun, $this>
     */
    public function childRuns(): HasMany
    {
        return $this->hasMany(self::class, 'parent_run_id');
    }

    /**
     * @return HasMany<RunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(RunEvent::class);
    }

    /**
     * Cited pages returned on the "complete" event.
     *
     * @return array<int, array<string, mixed>>
     */
    public function citedPages(): array
    {
        return data_get($this->metadata, 'citedPages', []);
    }

    /**
     * Gap evaluations returned on the "complete" event (absent in fast mode).
     *
     * @return array<int, array<string, mixed>>
     */
    public function gapEvaluations(): array
    {
        return data_get($this->metadata, 'gapEvaluations', []);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => ResearchMode::class,
            'status' => RunStatus::class,
            'metadata' => 'array',
            'is_watch' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
