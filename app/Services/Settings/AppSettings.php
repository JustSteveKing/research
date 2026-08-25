<?php

namespace App\Services\Settings;

use App\Enums\ResearchMode;
use InvalidArgumentException;

/**
 * The settings a reader can change from inside the app, read through the store
 * first and falling back to config (and therefore .env) when nothing has been
 * saved. That ordering is what lets a packaged app be configured by the person
 * running it while a checkout keeps working from .env alone.
 *
 * Values are memoised for the life of the instance, not the life of the
 * process: the container binds this per resolve so a long-running queue worker
 * picks up a key entered in the UI on its next job instead of at restart.
 */
class AppSettings
{
    public const TABSTACK_API_KEY = 'tabstack.api_key';

    public const OLLAMA_BASE_URL = 'ollama.base_url';

    public const OLLAMA_CHAT_MODEL = 'ollama.chat_model';

    public const OLLAMA_EMBED_MODEL = 'ollama.embed_model';

    public const RESEARCH_DEFAULT_MODE = 'research.default_mode';

    public const RESEARCH_DEFAULT_BUDGET = 'research.default_budget';

    public const RESEARCH_MAX_DEPTH = 'research.max_depth';

    public const SETUP_DISMISSED = 'setup.dismissed';

    /**
     * Every writable key, mapped to whether its value is a secret.
     *
     * @var array<string, bool>
     */
    private const KEYS = [
        self::TABSTACK_API_KEY => true,
        self::OLLAMA_BASE_URL => false,
        self::OLLAMA_CHAT_MODEL => false,
        self::OLLAMA_EMBED_MODEL => false,
        self::RESEARCH_DEFAULT_MODE => false,
        self::RESEARCH_DEFAULT_BUDGET => false,
        self::RESEARCH_MAX_DEPTH => false,
        self::SETUP_DISMISSED => false,
    ];

    /** @var array<string, string|null> */
    private array $memo = [];

    public function __construct(protected SettingsStore $store) {}

    public function tabstackKey(): ?string
    {
        return $this->value(self::TABSTACK_API_KEY, config('services.tabstack.key'));
    }

    /**
     * True when the key came from .env rather than from the store, which means
     * clearing it in the UI will not take effect.
     */
    public function tabstackKeyComesFromEnv(): bool
    {
        return blank($this->stored(self::TABSTACK_API_KEY))
            && filled(config('services.tabstack.key'));
    }

    public function ollamaBaseUrl(): string
    {
        return (string) $this->value(self::OLLAMA_BASE_URL, config('services.ollama.base_url'));
    }

    public function ollamaChatModel(): string
    {
        return (string) $this->value(self::OLLAMA_CHAT_MODEL, config('services.ollama.chat_model'));
    }

    public function ollamaEmbedModel(): string
    {
        return (string) $this->value(self::OLLAMA_EMBED_MODEL, config('services.ollama.embed_model'));
    }

    /**
     * @return array{mode: string, credit_budget: int, max_depth: int}
     */
    public function researchDefaults(): array
    {
        return [
            'mode' => ResearchMode::from(
                (string) $this->value(self::RESEARCH_DEFAULT_MODE, config('research.default_mode')),
            )->value,
            'credit_budget' => (int) $this->value(self::RESEARCH_DEFAULT_BUDGET, config('research.default_budget')),
            'max_depth' => (int) $this->value(self::RESEARCH_MAX_DEPTH, config('research.max_depth')),
        ];
    }

    /**
     * Whether the app should ask for a Tabstack key before anything else.
     * Offline mode is a deliberate choice to replay fixtures, so it is not
     * treated as an unfinished setup.
     */
    public function needsSetup(): bool
    {
        return blank($this->tabstackKey())
            && ! config('research.offline')
            && blank($this->stored(self::SETUP_DISMISSED));
    }

    public function dismissSetup(): void
    {
        $this->put([self::SETUP_DISMISSED => '1']);
    }

    /**
     * @param  array<string, string|int|null>  $values
     */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::KEYS)) {
                throw new InvalidArgumentException("Unknown setting [{$key}].");
            }

            $this->store->set($key, $value === null ? null : (string) $value, self::KEYS[$key]);

            unset($this->memo[$key]);
        }
    }

    protected function value(string $key, mixed $fallback): ?string
    {
        $stored = $this->stored($key);

        if (filled($stored)) {
            return $stored;
        }

        return filled($fallback) ? (string) $fallback : null;
    }

    protected function stored(string $key): ?string
    {
        // array_key_exists rather than ??=, so an absent key is asked for once
        // per instance instead of on every read.
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $this->store->get($key, self::KEYS[$key]);
        }

        return $this->memo[$key];
    }
}
