import { useState } from 'react';
import ThreadExportController from '@/actions/App/Http/Controllers/ThreadExportController';
import Button from '@/components/ui/button';
import type { ExportFormat } from '@/types/research';

const formats: { id: ExportFormat; label: string; hint: string }[] = [
    { id: 'markdown', label: 'Markdown', hint: 'brief, facts, sources' },
    { id: 'json', label: 'JSON', hint: 'everything, machine-readable' },
    { id: 'bibtex', label: 'BibTeX', hint: 'bibliography only' },
];

/**
 * Research has to be able to leave the app. Dismissed facts are left out of
 * every format; disputed ones are kept and marked.
 */
export default function ExportMenu({ threadId }: { threadId: number }) {
    const [open, setOpen] = useState(false);

    return (
        <div className="relative">
            <Button
                type="button"
                variant="ghost"
                icon="download"
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={() => setOpen((current) => !current)}
            >
                Export
            </Button>

            {open ? (
                <>
                    <button
                        type="button"
                        aria-hidden
                        tabIndex={-1}
                        onClick={() => setOpen(false)}
                        className="fixed inset-0 z-10 cursor-default"
                    />

                    <ul
                        role="menu"
                        className="rise-in absolute bottom-full left-0 z-20 mb-1.5 w-56 rounded border border-rule bg-panel p-1 shadow-lg"
                    >
                        {formats.map((format) => (
                            <li key={format.id} role="none">
                                <a
                                    role="menuitem"
                                    href={ThreadExportController.url([
                                        threadId,
                                        format.id,
                                    ])}
                                    download
                                    onClick={() => setOpen(false)}
                                    className="focus-ring flex items-baseline gap-2 rounded px-2 py-1.5 transition-colors hover:bg-ground"
                                >
                                    <span className="text-[13px]">
                                        {format.label}
                                    </span>
                                    <span className="label ml-auto">
                                        {format.hint}
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ul>
                </>
            ) : null}
        </div>
    );
}
