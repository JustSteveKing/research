<?php

namespace App\Services\Tabstack;

use App\Enums\ResearchMode;
use Closure;

interface TabstackClient
{
    /**
     * Stream a research run, invoking the callback for every SSE event.
     *
     * @param  Closure(string, array<string, mixed>): void  $onEvent
     *
     * @throws TabstackException
     */
    public function research(string $query, ResearchMode $mode, Closure $onEvent, bool $noCache = false): void;
}
