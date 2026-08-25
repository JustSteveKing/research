<?php

namespace App\Services\Tabstack;

use App\Enums\ResearchMode;
use App\Support\SseParser;
use Closure;

/**
 * Replays a recorded SSE stream so the app (and its tests) can run without
 * spending credits.
 */
class FakeTabstackClient implements TabstackClient
{
    /** @var list<array{query: string, mode: ResearchMode, noCache: bool}> */
    public array $calls = [];

    public function __construct(
        protected string $fixturePath,
        protected int $delayMicroseconds = 0,
    ) {}

    public function research(string $query, ResearchMode $mode, Closure $onEvent, bool $noCache = false): void
    {
        $this->calls[] = ['query' => $query, 'mode' => $mode, 'noCache' => $noCache];

        if (! is_file($this->fixturePath)) {
            throw new TabstackException("Missing Tabstack fixture at {$this->fixturePath}.");
        }

        $stream = (string) file_get_contents($this->fixturePath);
        $stream = str_replace('{{QUERY}}', addslashes($query), $stream);

        $parser = new SseParser;

        foreach (str_split($stream, 512) as $chunk) {
            $parser->push($chunk, $onEvent);

            if ($this->delayMicroseconds > 0) {
                usleep($this->delayMicroseconds);
            }
        }

        $parser->finish($onEvent);
    }
}
