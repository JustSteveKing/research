import { Link } from '@inertiajs/react';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import EmptyState from '@/components/ui/empty-state';
import Icon from '@/components/ui/icon';
import Pill from '@/components/ui/pill';
import type { BorrowedFact, RelatedThread } from '@/types/research';

/**
 * Knowledge does not stop at the thread boundary. Threads are matched on the
 * topic vector written with each brief, and the facts below are the ones this
 * thread never researched but could have used.
 */
export default function RelatedThreads({
    related,
    borrowed,
    hasBrief,
}: {
    related: RelatedThread[];
    borrowed: BorrowedFact[];
    hasBrief: boolean;
}) {
    if (related.length === 0 && borrowed.length === 0) {
        return (
            <EmptyState
                title={hasBrief ? 'Nothing related yet' : 'Waiting on a brief'}
                body={
                    hasBrief
                        ? 'Other threads on the same ground will appear here as they build up knowledge.'
                        : 'A thread becomes matchable once the local model has written its first brief.'
                }
            />
        );
    }

    return (
        <div className="space-y-4">
            {related.length > 0 ? (
                <ul className="space-y-2">
                    {related.map((thread) => (
                        <li key={thread.id}>
                            <Link
                                href={ThreadController.show(thread.id).url}
                                className="focus-ring group flex items-baseline gap-2 rounded text-[13px] leading-5 transition-colors hover:text-knowledge"
                            >
                                <Icon
                                    name="link"
                                    className="mt-0.5 text-muted transition-colors group-hover:text-knowledge"
                                />
                                <span className="min-w-0 flex-1 text-pretty">
                                    {thread.title}
                                </span>
                                <Pill
                                    title={`${Math.round(thread.similarity * 100)}% topic overlap`}
                                >
                                    {Math.round(thread.similarity * 100)}%
                                </Pill>
                            </Link>

                            <p className="label mt-0.5 pl-5">
                                {thread.facts_count} facts
                                {thread.shared_domains.length > 0
                                    ? ` · shares ${thread.shared_domains.join(', ')}`
                                    : null}
                            </p>
                        </li>
                    ))}
                </ul>
            ) : null}

            {borrowed.length > 0 ? (
                <div className="border-t border-rule pt-3">
                    <p className="label mb-2">
                        Facts from other threads that fit this question
                    </p>

                    <ul className="space-y-2.5">
                        {borrowed.map((fact) => (
                            <li key={fact.id} className="flex gap-2.5">
                                <span
                                    aria-hidden
                                    className="mt-1.5 h-3 w-0.5 shrink-0 rounded-full bg-knowledge/50"
                                />
                                <div className="min-w-0">
                                    <p className="text-[13px] leading-5 text-pretty">
                                        {fact.statement}
                                    </p>
                                    <p className="label mt-0.5">
                                        <Link
                                            href={
                                                ThreadController.show(
                                                    fact.thread.id,
                                                ).url
                                            }
                                            className="hover:text-knowledge"
                                        >
                                            {fact.thread.title}
                                        </Link>
                                        {fact.source ? (
                                            <>
                                                {' · '}
                                                <a
                                                    href={fact.source.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="hover:text-knowledge"
                                                >
                                                    {fact.source.domain}
                                                </a>
                                            </>
                                        ) : null}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}
