import Icon from '@/components/ui/icon';
import { cn } from '@/lib/utils';
import type { WatchReport as Report } from '@/types/research';

/**
 * What the last scheduled check found. "Nothing new" is reported as loudly as
 * a change, because a watch that only speaks up when something moves is
 * indistinguishable from one that has quietly stopped working.
 */
export default function WatchReport({ report }: { report: Report }) {
    const stopped = report.stopped !== undefined;
    const disputed = (report.disputes ?? 0) > 0;

    return (
        <aside
            className={cn(
                'mb-6 flex items-start gap-2.5 rounded border p-3',
                stopped || disputed
                    ? 'border-signal/40 bg-signal/5'
                    : 'border-rule bg-panel/40',
            )}
        >
            <Icon
                name={stopped ? 'warning' : disputed ? 'split' : 'clock'}
                className={cn(
                    'mt-0.5',
                    stopped || disputed ? 'text-signal' : 'text-muted',
                )}
            />

            <div className="min-w-0">
                <p className="text-[13px] leading-5 text-pretty">
                    {report.stopped ?? report.headline}
                </p>
                <p className="label mt-0.5">
                    Last checked{' '}
                    {new Date(report.checked_at).toLocaleString([], {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    })}
                    {(report.stale ?? 0) > 0
                        ? ` · ${report.stale} older facts have aged out`
                        : null}
                </p>
            </div>
        </aside>
    );
}
