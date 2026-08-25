<?php

namespace App\Services\Tabstack;

use App\Enums\ResearchMode;
use App\Support\SseParser;
use Closure;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpTabstackClient implements TabstackClient
{
    public function __construct(
        protected ?string $apiKey,
        protected string $baseUrl,
        protected int $timeout,
        protected ?int $fetchTimeout = null,
    ) {}

    public function research(string $query, ResearchMode $mode, Closure $onEvent, bool $noCache = false): void
    {
        if (blank($this->apiKey)) {
            throw new TabstackException('No Tabstack API key configured. Add one under Settings.');
        }

        $payload = array_filter([
            'query' => $query,
            'mode' => $mode->value,
            'nocache' => $noCache ?: null,
            'fetch_timeout' => $this->fetchTimeout,
        ], fn (mixed $value): bool => $value !== null);

        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->timeout($this->timeout)
                ->connectTimeout(30)
                ->withOptions(['stream' => true])
                ->post(rtrim($this->baseUrl, '/').'/research', $payload);
        } catch (Throwable $e) {
            throw TabstackException::transport($e);
        }

        if ($response->failed()) {
            throw TabstackException::response($response->status(), $response->body());
        }

        $parser = new SseParser;
        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $chunk = $body->read(8192);

            if ($chunk === '') {
                continue;
            }

            $parser->push($chunk, $onEvent);
        }

        $parser->finish($onEvent);
    }
}
