import Keycap from '@/components/ui/keycap';
import Overlay from '@/components/workspace/overlay';

const groups: { title: string; items: [string, string][] }[] = [
    {
        title: 'Anywhere',
        items: [
            ['⌘K', 'Search and commands'],
            ['⌘N', 'New thread'],
            ['⌘B', 'Collapse the thread rail'],
            ['⌘,', 'Settings'],
            ['?', 'This sheet'],
        ],
    },
    {
        title: 'In a thread',
        items: [
            ['⌘J', 'Chat over stored knowledge'],
            ['⌘\\', 'Show or hide the inspector'],
            ['⌘1 – ⌘4', 'Sources, facts, timeline, up next'],
            ['⌘R', 'Rebuild knowledge from stored reports'],
        ],
    },
    {
        title: 'In the palette',
        items: [
            ['↑ ↓', 'Move'],
            ['⏎', 'Run'],
            ['esc', 'Close'],
        ],
    },
];

export default function ShortcutsSheet({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    return (
        <Overlay open={open} onClose={onClose} label="Keyboard shortcuts">
            <div className="grid gap-6 sm:grid-cols-3">
                {groups.map((group) => (
                    <section key={group.title}>
                        <h2 className="label mb-2">{group.title}</h2>
                        <dl className="space-y-1.5">
                            {group.items.map(([keys, description]) => (
                                <div
                                    key={keys}
                                    className="flex items-baseline gap-2"
                                >
                                    <dt className="w-20 shrink-0">
                                        <Keycap keys={keys} />
                                    </dt>
                                    <dd className="text-xs leading-5 text-muted">
                                        {description}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                ))}
            </div>
        </Overlay>
    );
}
