import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ThreadChatController from '@/actions/App/Http/Controllers/ThreadChatController';
import Markdown from '@/components/markdown';
import Button from '@/components/ui/button';
import Icon from '@/components/ui/icon';
import Keycap from '@/components/ui/keycap';
import type { ChatMessage } from '@/types/research';

/**
 * Chat runs against the local model over the thread's stored knowledge, so it
 * answers instantly and spends no research credits.
 */
export default function ChatPanel({
    threadId,
    messages,
    expanded,
    onToggle,
}: {
    threadId: number;
    messages: ChatMessage[];
    expanded: boolean;
    onToggle: (expanded: boolean) => void;
}) {
    const draftKey = `chat-draft:${threadId}`;
    const [draft, setDraft] = useState(
        () => window.sessionStorage.getItem(draftKey) ?? '',
    );

    const updateDraft = (value: string) => {
        setDraft(value);
        window.sessionStorage.setItem(draftKey, value);
    };
    const [streaming, setStreaming] = useState(false);
    const [answer, setAnswer] = useState('');
    const [error, setError] = useState<string | null>(null);
    const sourceRef = useRef<EventSource | null>(null);

    const ask = () => {
        const question = draft.trim();

        if (question === '' || streaming) {
            return;
        }

        updateDraft('');
        setError(null);
        setAnswer('');

        router.post(
            ThreadChatController.store(threadId).url,
            { content: question },
            {
                preserveScroll: true,
                only: ['chat'],
                // The stream is told which message to answer. Ask twice in
                // quick succession and each answer still lands under its own
                // question instead of both racing for the latest one.
                onSuccess: (page) => listen(lastQuestionId(page.props.chat)),
            },
        );
    };

    const listen = (messageId: number | null) => {
        if (messageId === null) {
            return;
        }

        setStreaming(true);

        const stream = new EventSource(
            ThreadChatController.stream(threadId, {
                query: { message: messageId },
            }).url,
        );
        sourceRef.current = stream;

        stream.addEventListener('token', (event) => {
            const { token } = JSON.parse(
                (event as MessageEvent<string>).data,
            ) as {
                token: string;
            };

            setAnswer((current) => current + token);
        });

        stream.addEventListener('failed', () => {
            setError(
                'The local model could not be reached. Is Ollama running?',
            );
            stop(stream);
        });

        stream.addEventListener('citations', () => finish(stream));

        // A dropped connection still leaves whatever was written on the server.
        stream.onerror = () => finish(stream);
    };

    const finish = (stream: EventSource) => {
        stop(stream);
        router.reload({ only: ['chat'], onFinish: () => setAnswer('') });
    };

    const stop = (stream: EventSource) => {
        stream.close();
        sourceRef.current = null;
        setStreaming(false);
    };

    if (!expanded) {
        return (
            <button
                type="button"
                onClick={() => onToggle(true)}
                className="focus-ring group flex w-full items-center gap-2 border-t border-rule px-4 py-2 text-left transition-colors hover:bg-panel"
            >
                <Icon
                    name="send"
                    className="text-muted transition-colors group-hover:text-ink"
                />
                <span className="label transition-colors group-hover:text-ink">
                    Ask this thread
                </span>
                <span className="ml-auto flex items-center gap-2">
                    <span className="label">no credits</span>
                    <Keycap keys="⌘J" />
                </span>
            </button>
        );
    }

    return (
        <div className="flex h-full flex-col border-t border-rule bg-panel/40">
            <div className="flex h-8 items-center gap-2 border-b border-rule px-4">
                <span className="label">Chat · grounded in stored facts</span>
                <button
                    type="button"
                    onClick={() => onToggle(false)}
                    aria-label="Collapse chat"
                    className="focus-ring ml-auto flex items-center gap-1.5 rounded p-1 text-muted transition-colors hover:text-ink"
                >
                    <Keycap keys="⌘J" />
                    <Icon name="close" className="h-3 w-3" />
                </button>
            </div>

            <div className="pane min-h-0 flex-1 space-y-4 px-4 py-3">
                {messages.length === 0 && answer === '' ? (
                    <p className="text-sm text-muted">
                        Ask anything the thread has already researched. Answers
                        come from the stored facts, not a new web search.
                    </p>
                ) : null}

                {messages.map((message) =>
                    message.role === 'user' ? (
                        <p
                            key={message.id}
                            className="ml-auto max-w-[75%] rounded-md rounded-br-sm border border-rule bg-ground px-3 py-1.5 text-[13px] leading-5"
                        >
                            {message.content}
                        </p>
                    ) : (
                        <div key={message.id} className="max-w-[92%]">
                            <Markdown>{message.content}</Markdown>
                            {message.citations &&
                            message.citations.length > 0 ? (
                                <ul className="mt-2 flex flex-wrap gap-1.5">
                                    {message.citations.map((citation) => (
                                        <li key={citation.url}>
                                            <a
                                                href={citation.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="focus-ring inline-flex items-center gap-1 rounded-full border border-rule px-2 py-px font-mono text-[10px] text-muted transition-colors hover:border-knowledge/50 hover:text-knowledge"
                                            >
                                                {new URL(citation.url).hostname}
                                                {citation.thread ? (
                                                    <span className="text-knowledge">
                                                        · {citation.thread}
                                                    </span>
                                                ) : null}
                                                <Icon
                                                    name="external"
                                                    className="h-2.5 w-2.5"
                                                />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                        </div>
                    ),
                )}

                {answer !== '' ? (
                    <div className="max-w-[90%]">
                        <Markdown>{answer}</Markdown>
                    </div>
                ) : null}

                {streaming && answer === '' ? (
                    <p className="label flex items-center gap-1.5">
                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-knowledge" />
                        Thinking locally…
                    </p>
                ) : null}

                {error ? (
                    <p className="flex items-center gap-1.5 text-xs text-red-700 dark:text-red-400">
                        <Icon name="warning" className="h-3 w-3" />
                        {error}
                    </p>
                ) : null}
            </div>

            <div className="flex items-center gap-2 border-t border-rule px-4 py-2.5">
                <input
                    value={draft}
                    onChange={(event) => updateDraft(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            ask();
                        }
                    }}
                    placeholder="Ask about anything this thread has learned"
                    autoFocus
                    className="focus-ring h-8 flex-1 rounded border border-rule bg-ground px-3 text-[13px] outline-none"
                />
                <Button
                    type="button"
                    onClick={ask}
                    disabled={streaming || draft.trim() === ''}
                    variant="solid"
                    icon="send"
                >
                    Ask
                </Button>
            </div>
        </div>
    );
}

/**
 * The id of the question just stored, read back off the refreshed chat prop.
 */
function lastQuestionId(chat: unknown): number | null {
    if (!Array.isArray(chat)) {
        return null;
    }

    const questions = (chat as ChatMessage[]).filter(
        (message) => message.role === 'user',
    );

    return questions.at(-1)?.id ?? null;
}
