import { Form, Head, setLayoutProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import Panel from '@/components/panel';
import Button from '@/components/ui/button';
import type { ResearchMode } from '@/types/research';

interface Props {
    tabstack: {
        configured: boolean;
        /** The key came from .env, so clearing it here will not take effect. */
        from_env: boolean;
        base_url: string;
        replaying_fixtures: boolean;
    };
    ollama: {
        base_url: string;
        chat_model: string;
        embed_model: string;
        reachable: boolean;
        models: string[];
    };
    defaults: {
        mode: ResearchMode;
        credit_budget: number;
        max_depth: number;
        costs: Record<ResearchMode, number>;
    };
}

function Row({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: string;
    tone?: 'default' | 'good' | 'bad';
}) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-rule py-1.5 last:border-0">
            <span className="label">{label}</span>
            <span
                className={
                    tone === 'good'
                        ? 'tnum font-mono text-xs text-knowledge'
                        : tone === 'bad'
                          ? 'tnum font-mono text-xs text-red-700 dark:text-red-400'
                          : 'tnum font-mono text-xs'
                }
            >
                {value}
            </span>
        </div>
    );
}

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: ReactNode;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label htmlFor={id} className="label">
                {label}
            </label>
            {children}
            {error ? (
                <p className="mt-1 text-xs text-red-600">{error}</p>
            ) : hint ? (
                <p className="mt-1 text-xs text-muted">{hint}</p>
            ) : null}
        </div>
    );
}

const inputClass =
    'focus-ring mt-2 w-full rounded border border-rule bg-ground px-2 py-1.5 font-mono text-xs outline-none';

function SaveRow({ processing }: { processing: boolean }) {
    return (
        <div className="flex justify-end border-t border-rule pt-4">
            <Button type="submit" disabled={processing} variant="solid">
                {processing ? 'Saving…' : 'Save'}
            </Button>
        </div>
    );
}

export default function Settings({ tabstack, ollama, defaults }: Props) {
    setLayoutProps({ threadId: null, title: 'Settings' });

    return (
        <div className="pane">
            <Head title="Settings" />

            <div className="mx-auto max-w-4xl px-6 py-8">
                <p className="mb-6 max-w-prose text-sm text-muted">
                    Web research runs on Tabstack and costs credits. Everything
                    else runs on your machine through Ollama. Settings are
                    stored outside the app, so an update will not clear them.
                </p>

                <div className="grid gap-6 md:grid-cols-2">
                    <Panel title="Tabstack">
                        <Row
                            label="Endpoint"
                            value={`${tabstack.base_url}/research`}
                        />
                        <Row
                            label="API key"
                            value={
                                tabstack.configured ? 'configured' : 'missing'
                            }
                            tone={tabstack.configured ? 'good' : 'bad'}
                        />
                        <Row
                            label="Fast run"
                            value={`${defaults.costs.fast} credits`}
                        />
                        <Row
                            label="Balanced run"
                            value={`${defaults.costs.balanced} credits`}
                        />

                        <Form
                            action={SettingsController.update()}
                            resetOnSuccess
                            className="mt-5 space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Field
                                        id="tabstack_api_key"
                                        label="API key"
                                        error={errors.tabstack_api_key}
                                        hint={
                                            tabstack.configured
                                                ? 'Enter a new key to replace the stored one, or save empty to remove it.'
                                                : "Kept in your operating system's keychain."
                                        }
                                    >
                                        <input
                                            id="tabstack_api_key"
                                            name="tabstack_api_key"
                                            type="password"
                                            autoComplete="off"
                                            spellCheck={false}
                                            placeholder={
                                                tabstack.configured
                                                    ? '••••••••••••'
                                                    : 'tbk_…'
                                            }
                                            className={inputClass}
                                        />
                                    </Field>

                                    <SaveRow processing={processing} />
                                </>
                            )}
                        </Form>

                        {tabstack.from_env ? (
                            <p className="mt-4 rounded border border-rule p-3 text-sm text-muted">
                                This key is coming from{' '}
                                <code className="font-mono">.env</code>, which
                                wins over anything saved here. Remove
                                <code className="mx-1 font-mono">
                                    TABSTACK_API_KEY
                                </code>
                                to manage it from this page.
                            </p>
                        ) : null}

                        {tabstack.replaying_fixtures ? (
                            <p className="mt-4 rounded border border-signal/40 bg-signal/5 p-3 text-sm">
                                Runs are replaying the recorded sample stream.
                                Add a key above to research the live web.
                            </p>
                        ) : null}
                    </Panel>

                    <Panel title="Ollama">
                        <Row
                            label="Status"
                            value={ollama.reachable ? 'connected' : 'offline'}
                            tone={ollama.reachable ? 'good' : 'bad'}
                        />

                        <Form
                            action={SettingsController.update()}
                            className="mt-5 space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Field
                                        id="ollama_base_url"
                                        label="Host"
                                        error={errors.ollama_base_url}
                                    >
                                        <input
                                            id="ollama_base_url"
                                            name="ollama_base_url"
                                            type="url"
                                            defaultValue={ollama.base_url}
                                            className={inputClass}
                                        />
                                    </Field>

                                    <Field
                                        id="ollama_chat_model"
                                        label="Chat model"
                                        error={errors.ollama_chat_model}
                                    >
                                        <input
                                            id="ollama_chat_model"
                                            name="ollama_chat_model"
                                            list="ollama-models"
                                            defaultValue={ollama.chat_model}
                                            className={inputClass}
                                        />
                                    </Field>

                                    <Field
                                        id="ollama_embed_model"
                                        label="Embedding model"
                                        error={errors.ollama_embed_model}
                                        hint="Changing this invalidates stored embeddings; rebuild a thread to re-match it."
                                    >
                                        <input
                                            id="ollama_embed_model"
                                            name="ollama_embed_model"
                                            list="ollama-models"
                                            defaultValue={ollama.embed_model}
                                            className={inputClass}
                                        />
                                    </Field>

                                    <datalist id="ollama-models">
                                        {ollama.models.map((model) => (
                                            <option key={model} value={model} />
                                        ))}
                                    </datalist>

                                    <SaveRow processing={processing} />
                                </>
                            )}
                        </Form>

                        {ollama.reachable ? null : (
                            <p className="mt-4 rounded border border-rule p-3 text-sm text-muted">
                                Start Ollama to distil facts, propose follow-ups
                                and chat. Runs still work without it; the
                                knowledge base just stays empty.
                            </p>
                        )}
                    </Panel>

                    <Panel
                        title="New thread defaults"
                        className="md:col-span-2"
                    >
                        <Form
                            action={SettingsController.update()}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <Field
                                            id="default_mode"
                                            label="Mode"
                                            error={errors.default_mode}
                                        >
                                            <select
                                                id="default_mode"
                                                name="default_mode"
                                                defaultValue={defaults.mode}
                                                className={inputClass}
                                            >
                                                <option value="fast">
                                                    Fast · {defaults.costs.fast}{' '}
                                                    cr
                                                </option>
                                                <option value="balanced">
                                                    Balanced ·{' '}
                                                    {defaults.costs.balanced} cr
                                                </option>
                                            </select>
                                        </Field>

                                        <Field
                                            id="default_budget"
                                            label="Credit budget"
                                            error={errors.default_budget}
                                        >
                                            <input
                                                id="default_budget"
                                                name="default_budget"
                                                type="number"
                                                min={250}
                                                step={50}
                                                defaultValue={
                                                    defaults.credit_budget
                                                }
                                                className={inputClass}
                                            />
                                        </Field>

                                        <Field
                                            id="max_depth"
                                            label="Follow-up depth"
                                            error={errors.max_depth}
                                        >
                                            <input
                                                id="max_depth"
                                                name="max_depth"
                                                type="number"
                                                min={0}
                                                max={10}
                                                defaultValue={
                                                    defaults.max_depth
                                                }
                                                className={inputClass}
                                            />
                                        </Field>
                                    </div>

                                    <SaveRow processing={processing} />
                                </>
                            )}
                        </Form>
                    </Panel>
                </div>
            </div>
        </div>
    );
}
