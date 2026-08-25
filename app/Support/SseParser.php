<?php

namespace App\Support;

use Closure;

/**
 * Incremental parser for `text/event-stream` bodies.
 *
 * Chunks arrive from the HTTP client at arbitrary boundaries, so the parser
 * buffers partial lines and only emits once a blank line terminates an event.
 */
class SseParser
{
    protected string $buffer = '';

    /** @var list<string> */
    protected array $dataLines = [];

    protected ?string $event = null;

    /**
     * Feed a raw chunk. The callback receives the event name and decoded data.
     *
     * @param  Closure(string, array<string, mixed>): void  $onEvent
     */
    public function push(string $chunk, Closure $onEvent): void
    {
        $this->buffer .= $chunk;

        while (($position = strpos($this->buffer, "\n")) !== false) {
            $line = rtrim(substr($this->buffer, 0, $position), "\r");
            $this->buffer = substr($this->buffer, $position + 1);

            $this->consumeLine($line, $onEvent);
        }
    }

    /**
     * Flush a trailing event that was not terminated by a blank line.
     *
     * @param  Closure(string, array<string, mixed>): void  $onEvent
     */
    public function finish(Closure $onEvent): void
    {
        if ($this->buffer !== '') {
            $this->consumeLine(rtrim($this->buffer, "\r"), $onEvent);
            $this->buffer = '';
        }

        $this->dispatch($onEvent);
    }

    /**
     * @param  Closure(string, array<string, mixed>): void  $onEvent
     */
    protected function consumeLine(string $line, Closure $onEvent): void
    {
        if ($line === '') {
            $this->dispatch($onEvent);

            return;
        }

        if (str_starts_with($line, ':')) {
            return;
        }

        [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
        $value = ltrim($value, ' ');

        match ($field) {
            'event' => $this->event = $value,
            'data' => $this->dataLines[] = $value,
            default => null,
        };
    }

    /**
     * @param  Closure(string, array<string, mixed>): void  $onEvent
     */
    protected function dispatch(Closure $onEvent): void
    {
        if ($this->dataLines === [] && $this->event === null) {
            return;
        }

        $payload = implode("\n", $this->dataLines);
        $event = $this->event;

        $this->dataLines = [];
        $this->event = null;

        if ($payload === '[DONE]') {
            return;
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            $decoded = $payload === '' ? [] : ['raw' => $payload];
        }

        $onEvent($event ?? ($decoded['event'] ?? 'message'), $decoded);
    }
}
