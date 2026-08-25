<?php

namespace App\Services\Research;

use App\Models\ResearchRun;
use App\Models\Source;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Turns the citedPages metadata of a completed run into thread-level sources,
 * merging repeats across runs instead of duplicating them.
 */
class SourceRecorder
{
    public function record(ResearchRun $run): void
    {
        foreach ($run->citedPages() as $page) {
            $url = (string) ($page['url'] ?? '');

            if ($url === '') {
                continue;
            }

            $existing = Source::query()
                ->where('thread_id', $run->thread_id)
                ->where('url', $url)
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'cited_count' => $existing->cited_count + 1,
                    'claims' => array_values(array_unique([...($existing->claims ?? []), ...($page['claims'] ?? [])])),
                    'summary' => $existing->summary ?: ($page['summary'] ?? null),
                    'title' => $existing->title ?: ($page['title'] ?? null),
                    'published_at' => $existing->published_at ?? $this->publishedAt($page),
                ])->save();

                continue;
            }

            Source::query()->create([
                'thread_id' => $run->thread_id,
                'first_run_id' => $run->getKey(),
                'url' => $url,
                'domain' => parse_url($url, PHP_URL_HOST) ?: null,
                'title' => $page['title'] ?? null,
                'summary' => $page['summary'] ?? null,
                'relevance' => $page['relevance'] ?? null,
                'reliability' => $page['reliability'] ?? null,
                'published_at' => $this->publishedAt($page),
                'claims' => array_values($page['claims'] ?? []),
                'cited_count' => 1,
            ]);
        }
    }

    /**
     * Tabstack names the publication date differently depending on where it
     * scraped it from, and frequently has none at all.
     *
     * @param  array<string, mixed>  $page
     */
    protected function publishedAt(array $page): ?CarbonInterface
    {
        foreach (['publishedAt', 'publishedDate', 'datePublished', 'published'] as $key) {
            $value = $page[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
