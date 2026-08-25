import { Form } from '@inertiajs/react';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';

/**
 * Re-runs distillation and the brief over reports already stored, without
 * spending research credits.
 */
export default function RebuildButton({ threadId }: { threadId: number }) {
    return (
        <Form
            action={ThreadController.rebuild(threadId)}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <button
                    type="submit"
                    disabled={processing}
                    className="label rounded border border-rule px-2 py-1 transition hover:border-ink hover:text-ink disabled:opacity-50"
                >
                    {processing ? 'Rebuilding…' : 'Rebuild'}
                </button>
            )}
        </Form>
    );
}
