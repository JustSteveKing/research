<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text indexes for the command palette.
 *
 * These are SQLite FTS5 external-content tables: the index stores only the
 * terms and points back at the real row, so there is no second copy of the
 * data to drift. Triggers keep them current. Everything here is skipped on
 * any other driver, and SearchController falls back to LIKE.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    protected array $indexes = [
        'threads' => ['title', 'seed_question'],
        'facts' => ['statement'],
        'sources' => ['title', 'url', 'domain'],
    ];

    public function up(): void
    {
        if (! $this->supported()) {
            return;
        }

        foreach ($this->indexes as $table => $columns) {
            $list = implode(', ', $columns);

            DB::statement("create virtual table {$table}_fts using fts5({$list}, content='{$table}', content_rowid='id')");

            DB::statement("create trigger {$table}_fts_insert after insert on {$table} begin
                insert into {$table}_fts(rowid, {$list}) values (new.id, {$this->values($columns, 'new')});
            end");

            DB::statement("create trigger {$table}_fts_delete after delete on {$table} begin
                insert into {$table}_fts({$table}_fts, rowid, {$list}) values ('delete', old.id, {$this->values($columns, 'old')});
            end");

            DB::statement("create trigger {$table}_fts_update after update on {$table} begin
                insert into {$table}_fts({$table}_fts, rowid, {$list}) values ('delete', old.id, {$this->values($columns, 'old')});
                insert into {$table}_fts(rowid, {$list}) values (new.id, {$this->values($columns, 'new')});
            end");

            DB::statement("insert into {$table}_fts(rowid, {$list}) select id, {$list} from {$table}");
        }
    }

    public function down(): void
    {
        if (! $this->supported()) {
            return;
        }

        foreach (array_keys($this->indexes) as $table) {
            foreach (['insert', 'delete', 'update'] as $event) {
                DB::statement("drop trigger if exists {$table}_fts_{$event}");
            }

            DB::statement("drop table if exists {$table}_fts");
        }
    }

    /**
     * @param  list<string>  $columns
     */
    protected function values(array $columns, string $alias): string
    {
        return implode(', ', array_map(fn (string $column): string => "{$alias}.{$column}", $columns));
    }

    protected function supported(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
