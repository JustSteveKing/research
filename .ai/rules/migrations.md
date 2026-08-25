---
paths:
  - 'database/migrations/**'
---

# Migrations

## Migrations must be run twice: default and the NativePHP database
The desktop app does not use database/database.sqlite. NativeServiceProvider::rewriteDatabase() repoints database.default (and the queue's failed/batching/connection) at database/nativephp.sqlite whenever the app boots in native context. `php artisan migrate` from a terminal only touches the default connection, so a new column exists in tests and is missing at runtime — the symptom is "table X has no column named Y (Connection: nativephp)" from a queued job.

Always follow `php artisan migrate` with `php artisan native:migrate`. NativePHP auto-migrates only when nativephp.sqlite does not exist yet, so an established install never picks new migrations up on its own. Back the file up first; it holds real research.

To touch the desktop database from a script, bootstrap the app then call `(new Native\Desktop\NativeServiceProvider($app))->rewriteDatabase()` before any query. Note `php artisan list` and `tinker` are broken in this project by a duplicate `native:migrate:fresh` registration, so a standalone bootstrap script is the way in.
