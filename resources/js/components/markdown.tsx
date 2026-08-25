import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

export default function Markdown({
    children,
    className,
    onSourceClick,
}: {
    children: string;
    className?: string;
    /** Called with the href when a link matches a source the thread already holds. */
    onSourceClick?: (url: string) => void;
}) {
    return (
        <div className={cn('prose-research', className)}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    a: ({ href, children: label, ...props }) => (
                        <a
                            {...props}
                            href={href}
                            target="_blank"
                            rel="noreferrer"
                            onClick={(event) => {
                                if (!href || !onSourceClick) {
                                    return;
                                }

                                // Left click peeks the source in the inspector;
                                // cmd-click still opens the page itself.
                                if (!event.metaKey && !event.ctrlKey) {
                                    event.preventDefault();
                                    onSourceClick(href);
                                }
                            }}
                        >
                            {label}
                        </a>
                    ),
                }}
            >
                {children}
            </ReactMarkdown>
        </div>
    );
}
