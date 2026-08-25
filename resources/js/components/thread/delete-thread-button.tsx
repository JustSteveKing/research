import { Form } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import Button from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * Two-step delete: the first click arms it, the second commits. Arming resets
 * itself after a few seconds so a stray click cannot linger.
 */
export default function DeleteThreadButton({
    threadId,
    summary,
    className,
}: {
    threadId: number;
    summary?: string;
    className?: string;
}) {
    const [confirming, setConfirming] = useState(false);

    useEffect(() => {
        if (!confirming) {
            return;
        }

        const timer = setTimeout(() => setConfirming(false), 5000);

        return () => clearTimeout(timer);
    }, [confirming]);

    if (!confirming) {
        return (
            <Button
                type="button"
                onClick={() => setConfirming(true)}
                variant="ghost"
                size="xs"
                icon="trash"
                mono
                className={className}
            >
                Delete
            </Button>
        );
    }

    return (
        <div className={cn('flex items-center gap-2', className)}>
            {summary ? (
                <span className="label text-muted">{summary}</span>
            ) : null}

            <Form action={ThreadController.destroy(threadId)}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        disabled={processing}
                        autoFocus
                        variant="danger"
                        size="xs"
                        icon="trash"
                        mono
                    >
                        {processing ? 'Deleting…' : 'Delete for good'}
                    </Button>
                )}
            </Form>

            <Button
                type="button"
                onClick={() => setConfirming(false)}
                variant="ghost"
                size="xs"
                mono
            >
                Keep
            </Button>
        </div>
    );
}
