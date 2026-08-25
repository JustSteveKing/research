<?php

namespace App\Models;

use Database\Factories\RunEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $research_run_id
 * @property int $sequence
 * @property string $event
 * @property string|null $message
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 */
#[Fillable(['research_run_id', 'sequence', 'event', 'message', 'data'])]
class RunEvent extends Model
{
    /** @use HasFactory<RunEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ResearchRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class, 'research_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
