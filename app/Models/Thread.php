<?php

namespace App\Models;

use App\Enums\KnowledgeState;
use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Enums\WatchCadence;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $seed_question
 * @property ThreadStatus $status
 * @property ResearchMode $mode
 * @property int $credit_budget
 * @property int $credits_reserved
 * @property int $credits_spent
 * @property bool $auto_follow_up
 * @property WatchCadence|null $watch_cadence
 * @property Carbon|null $watched_at
 * @property array<string, mixed>|null $last_watch_report
 * @property int $max_depth
 * @property string|null $brief
 * @property Carbon|null $brief_generated_at
 * @property array<int, float>|null $embedding
 * @property array<int, array<string, mixed>>|null $coverage
 * @property Carbon|null $coverage_mapped_at
 * @property string|null $knowledge_error
 * @property KnowledgeState $knowledge_state
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $pinned_at
 */
#[Fillable([
    'title',
    'seed_question',
    'status',
    'mode',
    'credit_budget',
    'auto_follow_up',
    'max_depth',
    'watch_cadence',
])]
class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory;

    /**
     * Rail order: pinned first, then whatever moved most recently.
     *
     * @param  Builder<Thread>  $query
     * @return Builder<Thread>
     */
    #[Scope]
    protected function forRail(Builder $query): Builder
    {
        return $query->orderByRaw('pinned_at is null')
            ->orderByDesc('pinned_at')
            ->orderByDesc('updated_at');
    }

    /**
     * @return HasMany<ResearchRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ResearchRun::class);
    }

    /**
     * @return HasMany<Source, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /**
     * @return HasMany<Fact, $this>
     */
    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class);
    }

    /**
     * @return HasMany<FollowUpQuestion, $this>
     */
    public function followUpQuestions(): HasMany
    {
        return $this->hasMany(FollowUpQuestion::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Credits that are neither spent nor already committed to a queued run.
     */
    public function availableCredits(): int
    {
        return max(0, $this->credit_budget - $this->credits_spent - $this->credits_reserved);
    }

    public function canAfford(ResearchMode $mode): bool
    {
        return $this->availableCredits() >= $mode->creditCost();
    }

    /**
     * Record why the local knowledge pipeline stopped, so the UI can offer a
     * rebuild instead of silently showing an empty brief.
     */
    public function recordKnowledgeError(?string $message): void
    {
        $this->forceFill([
            'knowledge_error' => $message === null ? null : str($message)->limit(300)->toString(),
            'knowledge_state' => $message === null ? $this->knowledge_state : KnowledgeState::Failed,
        ])->save();
    }

    /**
     * Track what the local model is doing so the shell can say so.
     */
    public function markKnowledgeState(KnowledgeState $state): void
    {
        $this->forceFill([
            'knowledge_state' => $state,
            'knowledge_error' => $state === KnowledgeState::Failed ? $this->knowledge_error : null,
        ])->save();
    }

    /**
     * A watch is due when its cadence has elapsed since the last one, and
     * immediately the first time it is switched on.
     */
    public function watchIsDue(): bool
    {
        if ($this->watch_cadence === null || $this->status === ThreadStatus::Paused) {
            return false;
        }

        return $this->watched_at === null
            || $this->watched_at->addDays($this->watch_cadence->days())->isPast();
    }

    public function isBusy(): bool
    {
        return $this->hasActiveRun() || $this->knowledge_state->isWorking();
    }

    public function hasActiveRun(): bool
    {
        return $this->runs()
            ->whereIn('status', [RunStatus::Queued, RunStatus::Streaming])
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ThreadStatus::class,
            'mode' => ResearchMode::class,
            'knowledge_state' => KnowledgeState::class,
            'last_opened_at' => 'datetime',
            'pinned_at' => 'datetime',
            'auto_follow_up' => 'boolean',
            'watch_cadence' => WatchCadence::class,
            'watched_at' => 'datetime',
            'last_watch_report' => 'array',
            'brief_generated_at' => 'datetime',
            'embedding' => 'array',
            'coverage' => 'array',
            'coverage_mapped_at' => 'datetime',
        ];
    }
}
