import { Form } from '@inertiajs/react';
import FollowUpQuestionController from '@/actions/App/Http/Controllers/FollowUpQuestionController';
import Button from '@/components/ui/button';
import EmptyState from '@/components/ui/empty-state';
import Pill from '@/components/ui/pill';
import type { FollowUp } from '@/types/research';

export default function FollowUpQueue({
    threadId,
    followUps,
}: {
    threadId: number;
    followUps: FollowUp[];
}) {
    if (followUps.length === 0) {
        return (
            <EmptyState
                title="Nothing queued"
                body="After each run the local model proposes what to ask next."
            />
        );
    }

    return (
        <ul className="space-y-3">
            {followUps.map((question) => (
                <li
                    key={question.id}
                    className="group border-b border-rule pb-3 last:border-0"
                >
                    <div className="flex items-start justify-between gap-2">
                        <p className="text-[13px] leading-5 text-pretty">
                            {question.question}
                        </p>
                        <Pill
                            tone={
                                question.score >= 0.8 ? 'knowledge' : 'neutral'
                            }
                            className="mt-0.5 shrink-0"
                        >
                            {question.score.toFixed(2)}
                        </Pill>
                    </div>

                    {question.rationale ? (
                        <p className="mt-1 text-xs leading-relaxed text-muted">
                            {question.rationale}
                        </p>
                    ) : null}

                    <div className="mt-2 flex items-center gap-1.5">
                        {question.status === 'queued' ? (
                            <span className="label flex items-center gap-1.5 text-signal">
                                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-signal" />
                                Running
                            </span>
                        ) : (
                            <>
                                <Form
                                    action={FollowUpQuestionController.update([
                                        threadId,
                                        question.id,
                                    ])}
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="run"
                                    />
                                    <Button type="submit" size="xs" mono>
                                        Run now
                                    </Button>
                                </Form>
                                <Form
                                    action={FollowUpQuestionController.update([
                                        threadId,
                                        question.id,
                                    ])}
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject"
                                    />
                                    <Button
                                        type="submit"
                                        size="xs"
                                        variant="ghost"
                                        mono
                                    >
                                        Dismiss
                                    </Button>
                                </Form>
                            </>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}
