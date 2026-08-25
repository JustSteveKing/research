import EmptyState from '@/components/ui/empty-state';
import Icon from '@/components/ui/icon';
import Pill from '@/components/ui/pill';
import type { Source } from '@/types/research';

export default function SourceList({
    sources,
    highlightedUrl = null,
}: {
    sources: Source[];
    highlightedUrl?: string | null;
}) {
    if (sources.length === 0) {
        return (
            <EmptyState
                title="No sources cited yet"
                body="Pages the research engine reads and cites land here."
            />
        );
    }

    return (
        <ul className="space-y-4">
            {sources.map((source) => (
                <li
                    key={source.id}
                    id={`source-${source.id}`}
                    className={
                        highlightedUrl === source.url
                            ? 'rounded border-b border-rule bg-knowledge/10 pb-5 transition last:border-0'
                            : 'border-b border-rule pb-5 transition last:border-0'
                    }
                >
                    <div className="flex items-baseline justify-between gap-4">
                        <a
                            href={source.url}
                            target="_blank"
                            rel="noreferrer"
                            className="focus-ring group/link inline-flex items-start gap-1 rounded text-[13px] leading-5 font-medium text-pretty transition-colors hover:text-knowledge"
                        >
                            <span>{source.title ?? source.url}</span>
                            <Icon
                                name="external"
                                className="mt-1 opacity-0 transition-opacity group-hover/link:opacity-100"
                            />
                        </a>
                        {source.relevance || source.reliability ? (
                            <Pill
                                tone={
                                    source.relevance === 'high'
                                        ? 'knowledge'
                                        : 'neutral'
                                }
                                className="shrink-0"
                            >
                                {[source.relevance, source.reliability]
                                    .filter(Boolean)
                                    .join(' / ')}
                            </Pill>
                        ) : null}
                    </div>

                    <p className="label tnum mt-1">
                        {source.domain} · cited {source.cited_count}×
                        {source.published_at
                            ? ` · published ${new Date(source.published_at).toLocaleDateString()}`
                            : null}
                    </p>

                    {source.summary ? (
                        <p className="mt-1.5 text-xs leading-relaxed text-muted">
                            {source.summary}
                        </p>
                    ) : null}

                    {source.claims && source.claims.length > 0 ? (
                        <ul className="mt-2 space-y-1">
                            {source.claims.map((claim) => (
                                <li
                                    key={claim}
                                    className="border-l border-knowledge/40 pl-2 text-xs leading-relaxed"
                                >
                                    {claim}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </li>
            ))}
        </ul>
    );
}
