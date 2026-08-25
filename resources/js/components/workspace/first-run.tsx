import { Form, Link } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import Button from '@/components/ui/button';
import Overlay from '@/components/workspace/overlay';

/**
 * A packaged app ships with no credentials, and .env is not a surface the
 * reader can reach. The first window therefore asks for the one key the app
 * cannot work without, and remembers if it is waved away.
 */
export default function FirstRun({ open }: { open: boolean }) {
    return (
        <Overlay
            open={open}
            // Dismissing has to be a decision that is recorded, so the
            // backdrop and Escape do not quietly close this.
            onClose={() => {}}
            label="Set up research"
        >
            <h2 className="text-sm font-medium">Add a Tabstack API key</h2>

            <p className="mt-2 max-w-prose text-sm text-muted">
                Web research runs on Tabstack and costs credits. Everything else
                — distilling facts, spotting contradictions, chat — runs on your
                machine through Ollama and costs nothing.
            </p>

            <Form
                action={SettingsController.update()}
                className="mt-5 space-y-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div>
                            <label
                                htmlFor="first_run_tabstack_api_key"
                                className="label"
                            >
                                API key
                            </label>
                            <input
                                id="first_run_tabstack_api_key"
                                name="tabstack_api_key"
                                type="password"
                                autoFocus
                                autoComplete="off"
                                spellCheck={false}
                                placeholder="tbk_…"
                                className="focus-ring mt-2 w-full rounded border border-rule bg-ground px-3 py-2 font-mono text-xs outline-none"
                            />
                            {errors.tabstack_api_key ? (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.tabstack_api_key}
                                </p>
                            ) : null}
                            <p className="mt-2 text-xs text-muted">
                                Stored in your operating system's keychain, not
                                in the app bundle.
                            </p>
                        </div>

                        <div className="flex items-center justify-between border-t border-rule pt-4">
                            <Link
                                href={SettingsController.skipSetup()}
                                as="button"
                                type="button"
                                className="focus-ring rounded text-xs text-muted underline underline-offset-2 hover:text-ink"
                            >
                                Skip — replay the sample stream
                            </Link>

                            <Button
                                type="submit"
                                disabled={processing}
                                variant="solid"
                            >
                                {processing ? 'Saving…' : 'Save key'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </Overlay>
    );
}
