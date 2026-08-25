<?php

namespace App\Models;

use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $thread_id
 * @property int|null $first_run_id
 * @property string $url
 * @property string|null $domain
 * @property string|null $title
 * @property string|null $summary
 * @property string|null $relevance
 * @property string|null $reliability
 * @property Carbon|null $published_at
 * @property array<int, string>|null $claims
 * @property int $cited_count
 * @property bool $distilled
 */
#[Fillable([
    'thread_id',
    'first_run_id',
    'url',
    'domain',
    'title',
    'summary',
    'relevance',
    'reliability',
    'published_at',
    'claims',
    'cited_count',
    'distilled',
])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return HasMany<Fact, $this>
     */
    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claims' => 'array',
            'published_at' => 'datetime',
            'distilled' => 'boolean',
        ];
    }
}
