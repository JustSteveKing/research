<?php

namespace App\Models;

use App\Enums\QuestionOrigin;
use App\Enums\QuestionStatus;
use Database\Factories\FollowUpQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $thread_id
 * @property int|null $research_run_id
 * @property int|null $spawned_run_id
 * @property string $question
 * @property QuestionOrigin $origin
 * @property QuestionStatus $status
 * @property float $score
 * @property string|null $rationale
 * @property array<int, float>|null $embedding
 */
#[Fillable([
    'thread_id',
    'research_run_id',
    'spawned_run_id',
    'question',
    'origin',
    'status',
    'score',
    'rationale',
    'embedding',
])]
class FollowUpQuestion extends Model
{
    /** @use HasFactory<FollowUpQuestionFactory> */
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
    public function originRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class, 'research_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => QuestionOrigin::class,
            'status' => QuestionStatus::class,
            'embedding' => 'array',
            'score' => 'float',
        ];
    }
}
