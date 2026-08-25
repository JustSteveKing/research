<?php

namespace App\Services\Ollama;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper over the local Ollama HTTP API. Everything that does not need
 * the live web runs through here so it costs no Tabstack credits.
 */
class OllamaClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $chatModel,
        protected string $embedModel,
        protected int $timeout,
    ) {}

    public function chatModel(): string
    {
        return $this->chatModel;
    }

    public function embedModel(): string
    {
        return $this->embedModel;
    }

    /**
     * Send a chat completion and return the assistant message content.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $jsonSchema  when given, Ollama is forced to emit matching JSON
     */
    public function chat(array $messages, ?array $jsonSchema = null, ?string $model = null): string
    {
        $payload = array_filter([
            'model' => $model ?? $this->chatModel,
            'messages' => $messages,
            'stream' => false,
            'format' => $jsonSchema,
            'think' => false,
            'options' => ['temperature' => 0.2],
        ], fn (mixed $value): bool => $value !== null);

        $response = $this->request()->post('/api/chat', $payload);

        if ($response->failed()) {
            throw new OllamaException("Ollama chat failed with HTTP {$response->status()}: ".str($response->body())->limit(300));
        }

        return (string) $response->json('message.content', '');
    }

    /**
     * Chat and decode a JSON object response.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $jsonSchema
     * @return array<string, mixed>
     */
    public function structured(array $messages, array $jsonSchema, ?string $model = null): array
    {
        $content = $this->chat($messages, $jsonSchema, $model);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new OllamaException('Ollama returned a non-JSON response: '.str($content)->limit(300));
        }

        return $decoded;
    }

    /**
     * Stream a chat completion, yielding each token as it arrives.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return Generator<int, string>
     */
    public function chatTokens(array $messages, ?string $model = null): Generator
    {
        $response = $this->request()
            ->withOptions(['stream' => true])
            ->post('/api/chat', [
                'model' => $model ?? $this->chatModel,
                'messages' => $messages,
                'stream' => true,
                'think' => false,
                'options' => ['temperature' => 0.3],
            ]);

        if ($response->failed()) {
            throw new OllamaException("Ollama chat failed with HTTP {$response->status()}.");
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(2048);

            while (($position = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + 1);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $token = (string) data_get($decoded, 'message.content', '');

                if ($token !== '') {
                    yield $token;
                }
            }
        }
    }

    /**
     * Embed one or more strings.
     *
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = $this->request()->post('/api/embed', [
            'model' => $this->embedModel,
            'input' => $texts,
        ]);

        if ($response->failed()) {
            throw new OllamaException("Ollama embedding failed with HTTP {$response->status()}: ".str($response->body())->limit(300));
        }

        /** @var list<array<int, float>> $embeddings */
        $embeddings = $response->json('embeddings', []);

        return $embeddings;
    }

    /**
     * Embed a single string, returning null when the local model is unavailable.
     *
     * @return array<int, float>|null
     */
    public function embedOne(string $text): ?array
    {
        try {
            return $this->embed([$text])[0] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Installed models, for the settings screen.
     *
     * @return list<array<string, mixed>>
     */
    public function models(): array
    {
        $response = $this->request()->timeout(5)->get('/api/tags');

        if ($response->failed()) {
            throw new OllamaException("Ollama is not reachable at {$this->baseUrl}.");
        }

        return $response->json('models', []);
    }

    public function isReachable(): bool
    {
        try {
            $this->models();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->connectTimeout(15)
            // Ollama refuses connections briefly while it swaps a model into
            // memory, so retry the handshake but never a slow generation.
            // throw: false so a failed response comes back to the callers,
            // which raise OllamaException with a message worth showing.
            ->retry(3, 2000, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false)
            ->acceptJson();
    }
}
