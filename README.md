# Research

A local-first research workspace. You ask a question, it runs real web research,
and a local model distils what came back into durable facts you own — with the
sources attached, the contradictions surfaced, and the gaps named.

Built with Laravel, Inertia + React and [NativePHP](https://nativephp.com), and
shipped as a desktop app. Your research lives in a SQLite file on your machine.

## What it does

- **Threads.** A thread is a question you keep. It holds every run, every fact,
  and a synthesised brief that gets rewritten as the thread learns.
- **Runs.** Web research is performed by [Tabstack](https://tabstack.ai)'s
  `/research` endpoint, streamed live over SSE. Two modes: `fast` (250 credits)
  and `balanced` (350). Each thread has a hard credit budget.
- **Facts.** A local Ollama model distils each run's report into individual
  claims and attributes every one to a cited URL. Facts you pin, edit or dismiss
  are yours and survive rebuilds.
- **Contradictions.** Pairs of facts that look like they are about the same claim
  are put to the model, which calls them duplicate, contradiction or distinct.
  Cosine similarity alone cannot tell a paraphrase from a flat contradiction, so
  it is never asked to.
- **Coverage.** The model names the sub-topics a complete answer needs, then the
  facts speaking to each are counted, so a thin area shows up as thin.
- **Follow-ups and autopilot.** Each run proposes the next questions; the
  autopilot spawns them until the budget or the depth limit runs out.
- **Cross-thread knowledge.** Facts from other threads are borrowed when they
  clear a similarity floor, always labelled with where they came from.
- **Watch mode.** A thread can re-research its own seed question daily, weekly or
  monthly and report what changed.
- **Chat.** Ask a thread questions; retrieval is over its own facts.
- **Search.** SQLite FTS5 across threads, facts and sources, with a semantic
  re-rank when keywords come up short.
- **Export.** Markdown, JSON or BibTeX.

## Requirements

- PHP 8.3+ with the `zip` extension
- Composer 2
- Node 22+
- [Ollama](https://ollama.com) running locally, with a chat model and
  `nomic-embed-text` pulled
- A [Tabstack](https://tabstack.ai) API key for live research

## Getting started

```bash
composer setup
```

That installs dependencies, creates `.env`, generates a key, migrates and builds
the frontend.

The Tabstack key, the Ollama connection and the new-thread defaults are all
entered on the **Settings** page — a packaged app asks for the key on first run.
Settings live outside the app bundle, so an update cannot clear them: the
desktop app keeps them in Electron's store under your application-support
directory, with the API key wrapped by the OS keychain, and everything else
keeps them in `storage/app/private/settings.json`.

`.env` still works, and still wins when nothing has been saved, which is handy
for a checkout:

```dotenv
TABSTACK_API_KEY=your-key

OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_CHAT_MODEL=gemma4:latest
OLLAMA_EMBED_MODEL=nomic-embed-text
```

Pull the models Ollama needs:

```bash
ollama pull nomic-embed-text
ollama pull gemma4
```

No Tabstack key? Set `RESEARCH_OFFLINE=true` and runs replay recorded fixtures
from `database/fixtures/`. Nothing is charged and the whole UI still works.

### Running it

```bash
composer native:dev   # the desktop app, with Vite watching
composer dev          # the web app, queue and Vite
```

The desktop app does **not** use `database/database.sqlite` — it repoints at
`database/nativephp.sqlite` on boot. After adding a migration, run both:

```bash
php artisan migrate
php artisan native:migrate
```

### Tests

```bash
composer test        # pint, phpstan and the suite
php artisan test --compact
```

`phpunit.xml` forces `TABSTACK_API_KEY` empty and `RESEARCH_OFFLINE=true`, so the
suite can never spend credits.

## Configuration

Everything tunable lives in `config/research.php` and is env-overridable. The
similarity floors in there are set against the spread `nomic-embed-text` actually
produces (unrelated ~0.45, adjacent topic ~0.59, same subject ~0.66, restatement
>0.90) — not against round numbers. Read the comments before changing one.

Queue workers are declared in `config/nativephp.php` and auto-start with the app:
`research` for web runs, `knowledge` for local model work, so a slow distillation
never blocks the next stream.

## Building the desktop app

Locally:

```bash
php artisan native:build mac arm64
php artisan native:build linux x64
```

Artifacts land in `nativephp/electron/dist/`.

In CI, `.github/workflows/build.yml` builds macOS arm64 and Linux x64 on a `v*`
tag and uploads them to a draft GitHub Release:

```bash
git tag v1.0.0
git push origin v1.0.0
```

Builds are currently **unsigned** — macOS will show a Gatekeeper warning on first
open. Signing and notarization are wired through `NATIVEPHP_APPLE_ID`,
`NATIVEPHP_APPLE_ID_PASS` and `NATIVEPHP_APPLE_TEAM_ID` when you have certificates
to hand.

## Contributing

Project conventions that are not obvious from the code live in `.ai/rules/`,
indexed by path in `.ai/rules/index.md`. Read the row matching whatever you are
about to touch — several of them exist because the obvious approach was tried and
was wrong.

## License

MIT. See [LICENSE](LICENSE).
