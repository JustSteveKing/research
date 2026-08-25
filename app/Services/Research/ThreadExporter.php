<?php

namespace App\Services\Research;

use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Source;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Renders a thread as something that can leave the app: a readable report, a
 * machine-readable dump, or a bibliography for a reference manager.
 *
 * Dismissed facts are left out of every format — the reader already said they
 * were wrong — but disputed ones are kept and marked, because hiding a
 * disagreement from the export would misrepresent the research.
 */
class ThreadExporter
{
    public const FORMATS = ['markdown', 'json', 'bibtex'];

    /**
     * @return array{filename: string, mime: string, body: string}
     */
    public function export(Thread $thread, string $format): array
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException("Unsupported export format [{$format}].");
        }

        $slug = str($thread->title)->slug()->limit(60, '')->toString() ?: "thread-{$thread->getKey()}";

        return match ($format) {
            'markdown' => [
                'filename' => "{$slug}.md",
                'mime' => 'text/markdown',
                'body' => $this->markdown($thread),
            ],
            'json' => [
                'filename' => "{$slug}.json",
                'mime' => 'application/json',
                'body' => $this->json($thread),
            ],
            'bibtex' => [
                'filename' => "{$slug}.bib",
                'mime' => 'application/x-bibtex',
                'body' => $this->bibtex($thread),
            ],
        };
    }

    protected function markdown(Thread $thread): string
    {
        $facts = $this->facts($thread);
        $sources = $this->sources($thread);
        $disputes = $this->disputes($facts);

        $lines = [
            "# {$thread->title}",
            '',
            "> {$thread->seed_question}",
            '',
            sprintf(
                '%d runs · %d sources · %d facts · %d of %d credits spent · exported %s',
                $thread->runs()->count(),
                $sources->count(),
                $facts->count(),
                $thread->credits_spent,
                $thread->credit_budget,
                now()->toDayDateTimeString(),
            ),
        ];

        if (filled($thread->brief)) {
            $lines[] = '';
            $lines[] = '## Brief';
            $lines[] = '';
            $lines[] = trim($thread->brief);
        }

        $lines[] = '';
        $lines[] = '## Facts';
        $lines[] = '';

        if ($facts->isEmpty()) {
            $lines[] = '_Nothing distilled yet._';
        }

        foreach ($facts as $index => $fact) {
            $number = $index + 1;
            $reference = $fact->source_id === null ? '' : ' ['.$this->citationKey($fact->source).']';

            $lines[] = "{$number}. {$fact->statement}{$reference}{$this->factMarkers($fact)}";
        }

        if ($disputes->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Disputed';
            $lines[] = '';

            foreach ($disputes as $clash) {
                $lines[] = "- **A.** {$clash->fact->statement}";
                $lines[] = "  **B.** {$clash->otherFact->statement}";

                if ($clash->note !== null) {
                    $lines[] = "  _{$clash->note}_";
                }
            }
        }

        $lines[] = '';
        $lines[] = '## Sources';
        $lines[] = '';

        foreach ($sources as $source) {
            $published = $source->published_at?->toFormattedDateString();

            $lines[] = sprintf(
                '- [%s](%s)%s — cited %d×%s',
                $source->title ?? $source->url,
                $source->url,
                $published === null ? '' : " ({$published})",
                $source->cited_count,
                $source->reliability === null ? '' : ", reliability {$source->reliability}",
            );
        }

        $lines[] = '';
        $lines[] = '## Runs';
        $lines[] = '';

        foreach ($thread->runs()->orderBy('id')->get() as $run) {
            $lines[] = sprintf(
                '- [%s] %s — %s, depth %d, %d credits',
                $run->status->value,
                $run->query,
                $run->mode->value,
                $run->depth,
                $run->credits_cost,
            );
        }

        return implode("\n", $lines)."\n";
    }

    protected function json(Thread $thread): string
    {
        $facts = $this->facts($thread);

        return (string) json_encode([
            'thread' => [
                'id' => $thread->getKey(),
                'title' => $thread->title,
                'seed_question' => $thread->seed_question,
                'status' => $thread->status->value,
                'mode' => $thread->mode->value,
                'credits_spent' => $thread->credits_spent,
                'credit_budget' => $thread->credit_budget,
                'brief' => $thread->brief,
                'brief_generated_at' => $thread->brief_generated_at?->toIso8601String(),
            ],
            'exported_at' => now()->toIso8601String(),
            'facts' => $facts->map(fn (Fact $fact): array => [
                'id' => $fact->getKey(),
                'statement' => $fact->statement,
                'tags' => $fact->tags ?? [],
                'confidence' => $fact->confidence,
                'pinned' => $fact->isPinned(),
                'edited' => $fact->edited_at !== null,
                'stale' => $fact->isStale(),
                'asserted_at' => $fact->assertedAt()->toIso8601String(),
                'source_url' => $fact->source?->url,
            ])->all(),
            'disputed' => $this->disputes($facts)->map(fn (Contradiction $clash): array => [
                'note' => $clash->note,
                'similarity' => $clash->similarity,
                'a' => $clash->fact->statement,
                'b' => $clash->otherFact->statement,
            ])->all(),
            'sources' => $this->sources($thread)->map(fn (Source $source): array => [
                'url' => $source->url,
                'title' => $source->title,
                'domain' => $source->domain,
                'published_at' => $source->published_at?->toDateString(),
                'cited_count' => $source->cited_count,
                'relevance' => $source->relevance,
                'reliability' => $source->reliability,
            ])->all(),
            'runs' => $thread->runs()->orderBy('id')->get()->map(fn (ResearchRun $run): array => [
                'query' => $run->query,
                'mode' => $run->mode->value,
                'depth' => $run->depth,
                'status' => $run->status->value,
                'credits_cost' => $run->credits_cost,
                'completed_at' => $run->completed_at?->toIso8601String(),
                'report' => $run->report,
            ])->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function bibtex(Thread $thread): string
    {
        $entries = $this->sources($thread)->map(function (Source $source): string {
            $fields = array_filter([
                'title' => $source->title ?? $source->url,
                'howpublished' => "\\url{{$source->url}}",
                'url' => $source->url,
                'year' => $source->published_at?->format('Y'),
                'month' => $source->published_at?->format('M'),
                'urldate' => now()->toDateString(),
                'note' => $source->domain,
            ], fn (?string $value): bool => $value !== null && $value !== '');

            $body = collect($fields)
                ->map(fn (string $value, string $key): string => sprintf(
                    '  %s = {%s},',
                    $key,
                    str_replace(['{', '}'], ['\\{', '\\}'], $value),
                ))
                ->implode("\n");

            return "@misc{{$this->citationKey($source)},\n{$body}\n}";
        });

        return "% {$thread->title}\n% Exported ".now()->toDateString()."\n\n".$entries->implode("\n\n")."\n";
    }

    /**
     * @return Collection<int, Fact>
     */
    protected function facts(Thread $thread): Collection
    {
        return $thread->facts()
            ->active()
            ->with('source:id,url,title,domain')
            ->orderByDesc('pinned_at')
            ->orderByDesc('confidence')
            ->get();
    }

    /**
     * @return Collection<int, Source>
     */
    protected function sources(Thread $thread): Collection
    {
        return $thread->sources()->orderByDesc('cited_count')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @return Collection<int, Contradiction>
     */
    protected function disputes(Collection $facts): Collection
    {
        if ($facts->isEmpty()) {
            return new Collection;
        }

        return Contradiction::forFacts($facts)
            ->with(['fact:id,statement', 'otherFact:id,statement'])
            ->get();
    }

    protected function factMarkers(Fact $fact): string
    {
        $markers = array_filter([
            $fact->isPinned() ? 'pinned' : null,
            $fact->edited_at !== null ? 'edited' : null,
            $fact->isStale() ? 'stale, '.$fact->ageInDays().'d' : null,
        ]);

        return $markers === [] ? '' : ' _('.implode(', ', $markers).')_';
    }

    /**
     * A stable, cite-able key: domain plus source id, so re-exporting the same
     * thread does not renumber every reference.
     */
    protected function citationKey(Source $source): string
    {
        $domain = str($source->domain ?? 'source')->replace('.', '')->slug()->toString();

        return "{$domain}{$source->getKey()}";
    }
}
