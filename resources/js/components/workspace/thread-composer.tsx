import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import Button from '@/components/ui/button';
import Overlay from '@/components/workspace/overlay';
import type { ResearchMode } from '@/types/research';
import type { ThreadComposerDefaults } from '@/types/workspace';

/**
 * Opening a thread is the one moment where cost, depth and autonomy are chosen,
 * so the dialog states the price of the choice as it is made.
 */
export default function ThreadComposer({
    open,
    onClose,
    defaults,
}: {
    open: boolean;
    onClose: () => void;
    defaults: ThreadComposerDefaults;
}) {
    const [mode, setMode] = useState<ResearchMode>(defaults.mode);
    const [budget, setBudget] = useState(defaults.credit_budget);

    const perRun = defaults.costs[mode];
    const runs = Math.floor(budget / perRun);

    return (
        <Overlay open={open} onClose={onClose} label="New research thread">
            <Form
                action={ThreadController.store()}
                onSuccess={onClose}
                className="space-y-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div>
                            <div className="flex items-baseline justify-between">
                                <label
                                    htmlFor="seed_question"
                                    className="label"
                                >
                                    What do you want to know?
                                </label>
                                <span className="label">
                                    Runs until the budget is spent
                                </span>
                            </div>
                            <textarea
                                id="seed_question"
                                name="seed_question"
                                rows={3}
                                autoFocus
                                placeholder="What is the state of solid-state battery production?"
                                className="focus-ring mt-2 w-full resize-none rounded border border-rule bg-ground px-3 py-2 text-sm outline-none"
                            />
                            {errors.seed_question ? (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.seed_question}
                                </p>
                            ) : null}
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <span className="label">Depth per run</span>
                                <div className="mt-2 flex gap-1">
                                    {(
                                        ['fast', 'balanced'] as ResearchMode[]
                                    ).map((option) => (
                                        <label
                                            key={option}
                                            className={`focus-ring flex-1 cursor-pointer rounded border px-2 py-1.5 text-center text-xs capitalize transition ${
                                                mode === option
                                                    ? 'border-ink'
                                                    : 'border-rule hover:border-muted'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="mode"
                                                value={option}
                                                checked={mode === option}
                                                onChange={() => setMode(option)}
                                                className="sr-only"
                                            />
                                            {option}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <label
                                    htmlFor="credit_budget"
                                    className="label"
                                >
                                    Budget
                                </label>
                                <input
                                    id="credit_budget"
                                    name="credit_budget"
                                    type="number"
                                    min={perRun}
                                    step={50}
                                    value={budget}
                                    onChange={(event) =>
                                        setBudget(Number(event.target.value))
                                    }
                                    className="focus-ring mt-2 w-full rounded border border-rule bg-ground px-2 py-1.5 font-mono text-xs outline-none"
                                />
                            </div>

                            <div>
                                <label htmlFor="max_depth" className="label">
                                    Follow-up depth
                                </label>
                                <input
                                    id="max_depth"
                                    name="max_depth"
                                    type="number"
                                    min={0}
                                    max={10}
                                    defaultValue={defaults.max_depth}
                                    className="focus-ring mt-2 w-full rounded border border-rule bg-ground px-2 py-1.5 font-mono text-xs outline-none"
                                />
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-xs text-muted">
                            <input
                                type="checkbox"
                                name="auto_follow_up"
                                value="1"
                                defaultChecked
                            />
                            Keep researching on its own until the budget runs
                            out
                        </label>

                        {errors.credit_budget ? (
                            <p className="text-xs text-red-600">
                                {errors.credit_budget}
                            </p>
                        ) : null}

                        <div className="flex items-center justify-between border-t border-rule pt-4">
                            <p className="label tnum">
                                {mode} · {perRun} cr per run · about {runs} runs
                                in budget
                            </p>

                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    onClick={onClose}
                                    variant="ghost"
                                    mono
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    variant="solid"
                                >
                                    {processing
                                        ? 'Starting…'
                                        : `Research · ${perRun} cr`}
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </Form>
        </Overlay>
    );
}
