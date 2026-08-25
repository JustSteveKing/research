<?php

namespace App\Http\Controllers;

use App\Enums\ChatRole;
use App\Models\ChatMessage;
use App\Models\Fact;
use App\Models\Thread;
use App\Services\Ollama\OllamaClient;
use App\Services\Research\KnowledgeRetriever;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Chat grounded in what has already been researched. Runs entirely against
 * the local model, so it never spends Tabstack credits.
 */
class ThreadChatController extends Controller
{
    public function store(Request $request, Thread $thread): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
        ]);

        $thread->chatMessages()->create([
            'role' => ChatRole::User,
            'content' => $validated['content'],
        ]);

        return back();
    }

    public function stream(Request $request, Thread $thread, OllamaClient $ollama, KnowledgeRetriever $retriever): StreamedResponse
    {
        $question = $this->questionToAnswer($request, $thread);

        abort_if($question === null, 404);

        $facts = $retriever->retrieve($thread, $question->content);
        $citations = $this->citations($thread, $facts);
        $messages = $this->buildMessages($thread, $facts);

        return response()->eventStream(function () use ($thread, $question, $ollama, $messages, $citations) {
            $answer = '';

            try {
                foreach ($ollama->chatTokens($messages) as $token) {
                    $answer .= $token;

                    // Tokens contain newlines, which would break SSE framing if sent raw.
                    yield new StreamedEvent(event: 'token', data: json_encode(['token' => $token]));
                }

                yield new StreamedEvent(event: 'citations', data: json_encode($citations));
            } catch (Throwable $e) {
                report($e);

                yield new StreamedEvent(event: 'failed', data: 'The local model could not be reached.');
            } finally {
                // Also runs when the window closes mid-answer, so partial replies survive.
                if (trim($answer) !== '') {
                    ChatMessage::query()->create([
                        'thread_id' => $thread->id,
                        'role' => ChatRole::Assistant,
                        'content' => $answer,
                        'citations' => $citations,
                        'replies_to_id' => $question->getKey(),
                    ]);
                }
            }
        });
    }

    /**
     * The question this stream is for.
     *
     * The client names the message it wants answered, because two questions
     * sent in quick succession would otherwise both stream against whichever
     * one landed last, and the first answer would be filed under the wrong
     * question. Falls back to the latest unanswered question when no id is
     * given. Either way an already-answered question is refused, so a
     * reconnecting EventSource cannot produce a duplicate reply.
     */
    protected function questionToAnswer(Request $request, Thread $thread): ?ChatMessage
    {
        $requested = $request->integer('message');

        $question = $requested > 0
            ? $thread->chatMessages()->where('role', ChatRole::User)->find($requested)
            : $thread->chatMessages()->where('role', ChatRole::User)->latest('id')->first();

        if ($question === null || blank($question->content)) {
            return null;
        }

        $answered = $thread->chatMessages()
            ->where('role', ChatRole::Assistant)
            ->where(fn ($query) => $query
                ->where('replies_to_id', $question->getKey())
                ->orWhere(fn ($query) => $query->whereNull('replies_to_id')->where('id', '>', $question->getKey())))
            ->exists();

        return $answered ? null : $question;
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @return list<array{url: string, title: string|null, thread: string|null}>
     */
    protected function citations(Thread $thread, Collection $facts): array
    {
        return array_values($facts
            ->map(fn (Fact $fact): ?array => $fact->source === null ? null : [
                'url' => $fact->source->url,
                'title' => $fact->source->title,
                // Named only when it was borrowed, so the reader can see the
                // answer leaned on another thread's research.
                'thread' => $fact->thread_id === $thread->getKey() ? null : $fact->thread->title,
            ])
            ->filter()
            ->unique('url')
            ->all());
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @return list<array{role: string, content: string}>
     */
    protected function buildMessages(Thread $thread, Collection $facts): array
    {
        $knowledge = $facts
            ->map(fn (Fact $fact): string => sprintf(
                '- %s [%s]%s%s',
                $fact->statement,
                $fact->source_id === null ? 'no source' : $fact->source->url,
                $fact->thread_id === $thread->getKey() ? '' : ' (from the thread "'.$fact->thread->title.'")',
                $fact->isStale() ? ' (stale: '.$fact->ageInDays().' days old)' : '',
            ))
            ->implode("\n");

        $history = $thread->chatMessages()
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): array => [
                'role' => $message->role->value,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        return [
            [
                'role' => 'system',
                'content' => <<<PROMPT
                You answer questions about an ongoing research thread using only the knowledge provided below. If the knowledge does not cover the question, say so and suggest a research query that would close the gap. Cite source URLs inline as markdown links. Some facts were researched for another thread and are marked with the thread they came from — you may use them, but say where they came from. Facts marked stale may be out of date; flag that rather than stating them plainly.

                Research topic: {$thread->title}
                Original question: {$thread->seed_question}

                Thread brief:
                {$thread->brief}

                Known facts:
                {$knowledge}
                PROMPT,
            ],
            ...$history,
        ];
    }
}
