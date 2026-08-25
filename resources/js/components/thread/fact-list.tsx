import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import FactController from '@/actions/App/Http/Controllers/FactController';
import EmptyState from '@/components/ui/empty-state';
import Icon from '@/components/ui/icon';
import Pill from '@/components/ui/pill';
import { cn } from '@/lib/utils';
import type { Contradiction, Fact } from '@/types/research';

const VISIBLE_LIMIT = 40;

/**
 * The distilled knowledge, and the only place it can be corrected. A fact the
 * reader pins, rewrites or dismisses stops being the model's: rebuilds leave
 * it alone, and a dismissed one can never be re-distilled.
 */
export default function FactList({
    threadId,
    facts,
    contradictions = [],
}: {
    threadId: number;
    facts: Fact[];
    contradictions?: Contradiction[];
}) {
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState<number | null>(null);
    const [draft, setDraft] = useState('');
    const [showDismissed, setShowDismissed] = useState(false);
    const [showAll, setShowAll] = useState(false);

    const disputesByFact = useMemo(() => {
        const map = new Map<number, Contradiction[]>();

        for (const clash of contradictions) {
            for (const factId of clash.fact_ids) {
                map.set(factId, [...(map.get(factId) ?? []), clash]);
            }
        }

        return map;
    }, [contradictions]);

    const matches = (fact: Fact) => {
        const term = query.trim().toLowerCase();

        return (
            term === '' ||
            fact.statement.toLowerCase().includes(term) ||
            (fact.tags ?? []).some((tag) => tag.includes(term))
        );
    };

    const matching = facts.filter((fact) => !fact.dismissed && matches(fact));
    const dismissed = facts.filter((fact) => fact.dismissed);

    // A thread with hundreds of facts is a wall of text; show a readable slice
    // and let the reader ask for the rest.
    const capped = showAll ? matching : matching.slice(0, VISIBLE_LIMIT);
    const groups = groupByTag(capped);

    const patch = (
        fact: Fact,
        payload: { statement?: string; pinned?: boolean; dismissed?: boolean },
    ) =>
        router.patch(FactController.update([threadId, fact.id]).url, payload, {
            preserveScroll: true,
            only: ['facts', 'contradictions', 'thread', 'rail', 'activity'],
        });

    const startEditing = (fact: Fact) => {
        setEditing(fact.id);
        setDraft(fact.statement);
    };

    const saveEdit = (fact: Fact) => {
        const statement = draft.trim();

        setEditing(null);

        if (statement.length >= 8 && statement !== fact.statement) {
            patch(fact, { statement });
        }
    };

    if (facts.length === 0) {
        return (
            <EmptyState
                title="Nothing distilled yet"
                body="Each finished report is broken into standalone facts by the local model."
            />
        );
    }

    return (
        <div>
            <div className="relative mb-3">
                <span className="pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-muted">
                    <Icon name="search" />
                </span>
                <input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Filter facts"
                    className="focus-ring h-7 w-full rounded border border-rule bg-ground pr-2 pl-7 text-xs outline-none"
                />
            </div>

            {matching.length === 0 ? (
                <p className="label py-2">No facts match “{query}”</p>
            ) : null}

            {groups.map(([tag, group]) => (
                <div key={tag} className="mb-4 last:mb-0">
                    {groups.length > 1 ? (
                        <p className="label mb-1.5 flex items-baseline gap-1.5 border-b border-rule pb-1">
                            {tag}
                            <Pill className="ml-auto">{group.length}</Pill>
                        </p>
                    ) : null}

                    <ul className="space-y-2.5">
                        {group.map((fact) => {
                            const disputes = disputesByFact.get(fact.id) ?? [];

                            return (
                                <li
                                    key={fact.id}
                                    className="group/fact flex gap-2.5"
                                >
                                    <span
                                        aria-hidden
                                        title={`Confidence ${Math.round(fact.confidence * 100)}%`}
                                        className={cn(
                                            'mt-1.5 h-3 w-0.5 shrink-0 rounded-full',
                                            disputes.length > 0
                                                ? 'bg-signal'
                                                : 'bg-knowledge',
                                        )}
                                        style={{
                                            opacity: Math.max(
                                                0.25,
                                                fact.confidence,
                                            ),
                                        }}
                                    />

                                    <div className="min-w-0 flex-1">
                                        {editing === fact.id ? (
                                            <div className="space-y-1.5">
                                                <textarea
                                                    value={draft}
                                                    autoFocus
                                                    rows={3}
                                                    onChange={(event) =>
                                                        setDraft(
                                                            event.target.value,
                                                        )
                                                    }
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key ===
                                                            'Escape'
                                                        ) {
                                                            setEditing(null);
                                                        }

                                                        if (
                                                            event.key ===
                                                                'Enter' &&
                                                            (event.metaKey ||
                                                                event.ctrlKey)
                                                        ) {
                                                            saveEdit(fact);
                                                        }
                                                    }}
                                                    className="focus-ring w-full resize-none rounded border border-rule bg-ground p-2 text-[13px] leading-5 outline-none"
                                                />
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            saveEdit(fact)
                                                        }
                                                        className="focus-ring label rounded border border-rule px-1.5 py-0.5 hover:border-ink hover:text-ink"
                                                    >
                                                        Save ⌘↵
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setEditing(null)
                                                        }
                                                        className="focus-ring label rounded px-1 py-0.5 hover:text-ink"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="text-[13px] leading-5 text-pretty">
                                                {fact.statement}
                                            </p>
                                        )}

                                        <p className="label mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                            {(fact.tags ?? []).length > 0 ? (
                                                <span>
                                                    {(fact.tags ?? []).join(
                                                        ' · ',
                                                    )}
                                                </span>
                                            ) : null}

                                            {fact.source ? (
                                                <a
                                                    href={fact.source.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="hover:text-knowledge"
                                                >
                                                    {fact.source.domain}
                                                </a>
                                            ) : null}

                                            {fact.pinned ? (
                                                <Pill tone="knowledge">
                                                    pinned
                                                </Pill>
                                            ) : null}

                                            {fact.edited ? (
                                                <Pill>edited</Pill>
                                            ) : null}

                                            {fact.stale ? (
                                                <Pill
                                                    tone="signal"
                                                    className="gap-1"
                                                    title={`Asserted ${fact.age_days} days ago`}
                                                >
                                                    <Icon
                                                        name="clock"
                                                        className="h-2.5 w-2.5"
                                                    />
                                                    {fact.age_days}d
                                                </Pill>
                                            ) : null}

                                            {disputes.length > 0 ? (
                                                <Pill
                                                    tone="danger"
                                                    className="gap-1"
                                                >
                                                    <Icon
                                                        name="split"
                                                        className="h-2.5 w-2.5"
                                                    />
                                                    disputed
                                                </Pill>
                                            ) : null}

                                            <span className="ml-auto flex items-center gap-0.5 opacity-0 transition-opacity group-hover/fact:opacity-100 focus-within:opacity-100">
                                                <FactAction
                                                    icon="pin"
                                                    label={
                                                        fact.pinned
                                                            ? 'Unpin this fact'
                                                            : 'Pin — kept through rebuilds'
                                                    }
                                                    active={fact.pinned}
                                                    onClick={() =>
                                                        patch(fact, {
                                                            pinned: !fact.pinned,
                                                        })
                                                    }
                                                />
                                                <FactAction
                                                    icon="pencil"
                                                    label="Rewrite this fact"
                                                    onClick={() =>
                                                        startEditing(fact)
                                                    }
                                                />
                                                {fact.stale ? (
                                                    <FactAction
                                                        icon="refresh"
                                                        label="Research whether this still holds"
                                                        onClick={() =>
                                                            router.post(
                                                                FactController.recheck(
                                                                    [
                                                                        threadId,
                                                                        fact.id,
                                                                    ],
                                                                ).url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    />
                                                ) : null}
                                                <FactAction
                                                    icon="close"
                                                    label="Dismiss — never distilled again"
                                                    onClick={() =>
                                                        patch(fact, {
                                                            dismissed: true,
                                                        })
                                                    }
                                                />
                                            </span>
                                        </p>

                                        {disputes.map((clash) => (
                                            <Dispute
                                                key={clash.id}
                                                clash={clash}
                                                factId={fact.id}
                                            />
                                        ))}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ))}

            {matching.length > capped.length ? (
                <button
                    type="button"
                    onClick={() => setShowAll(true)}
                    className="focus-ring label mt-1 w-full rounded border border-rule py-1 transition-colors hover:border-ink hover:text-ink"
                >
                    Show all {matching.length}
                </button>
            ) : null}

            {dismissed.length > 0 ? (
                <div className="mt-4 border-t border-rule pt-2">
                    <button
                        type="button"
                        onClick={() => setShowDismissed((open) => !open)}
                        aria-expanded={showDismissed}
                        className="focus-ring label flex w-full items-center gap-1.5 rounded py-1 hover:text-ink"
                    >
                        <Icon
                            name="chevron"
                            className={cn(
                                'transition-transform duration-[--duration-instant]',
                                showDismissed && 'rotate-90',
                            )}
                        />
                        Dismissed
                        <Pill className="ml-1">{dismissed.length}</Pill>
                    </button>

                    {showDismissed ? (
                        <ul className="rise-in mt-1 space-y-1.5">
                            {dismissed.map((fact) => (
                                <li
                                    key={fact.id}
                                    className="flex items-start gap-2"
                                >
                                    <p className="min-w-0 flex-1 text-xs leading-5 text-muted line-through">
                                        {fact.statement}
                                    </p>
                                    <FactAction
                                        icon="refresh"
                                        label="Restore this fact"
                                        onClick={() =>
                                            patch(fact, { dismissed: false })
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

/**
 * The other side of a disagreement, shown under the fact it clashes with so
 * the conflict is impossible to read past.
 */
function Dispute({ clash, factId }: { clash: Contradiction; factId: number }) {
    const other = clash.sides.find((side) => side.id !== factId);

    if (!other) {
        return null;
    }

    return (
        <div className="mt-1.5 border-l-2 border-signal/50 pl-2">
            <p className="text-xs leading-5 text-muted">
                <span className="text-signal">Conflicts with:</span>{' '}
                {other.statement}
            </p>
            {other.thread ? (
                <p className="label mt-0.5">in “{other.thread.title}”</p>
            ) : null}
            {clash.note ? (
                <p className="label mt-0.5 text-pretty">{clash.note}</p>
            ) : null}
        </div>
    );
}

function FactAction({
    icon,
    label,
    active = false,
    onClick,
}: {
    icon: 'pin' | 'pencil' | 'close' | 'refresh';
    label: string;
    active?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            aria-label={label}
            className={cn(
                'focus-ring rounded p-0.5 transition-colors hover:text-ink',
                active ? 'text-knowledge' : 'text-muted',
            )}
        >
            <Icon name={icon} className="h-3 w-3" />
        </button>
    );
}

/**
 * Group by first tag, largest group first, with untagged facts last. One
 * group means the headings are noise, so the caller drops them.
 *
 * @return [string, Fact[]][]
 */
function groupByTag(facts: Fact[]): [string, Fact[]][] {
    const groups = new Map<string, Fact[]>();

    for (const fact of facts) {
        const tag = fact.tags?.[0] ?? 'untagged';
        groups.set(tag, [...(groups.get(tag) ?? []), fact]);
    }

    return [...groups.entries()].sort(([aTag, a], [bTag, b]) => {
        if (aTag === 'untagged') {
            return 1;
        }

        if (bTag === 'untagged') {
            return -1;
        }

        return b.length - a.length;
    });
}
