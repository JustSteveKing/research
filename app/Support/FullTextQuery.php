<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns what someone typed into the command palette into an FTS5 MATCH
 * expression.
 *
 * Raw input cannot go near MATCH: quotes, hyphens and bare operators are all
 * syntax there, and a half-typed query would throw rather than simply find
 * nothing. Every term is quoted, joined with AND, and the last one gets a
 * prefix wildcard so results narrow as the reader keeps typing.
 */
class FullTextQuery
{
    public static function available(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            return $available = false;
        }

        try {
            DB::selectOne("select count(*) as total from facts_fts where facts_fts match 'a'");

            return $available = true;
        } catch (Throwable) {
            return $available = false;
        }
    }

    /**
     * Null when there is nothing searchable left after cleaning.
     */
    public static function expression(string $term): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $term, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return null;
        }

        $last = array_key_last($words);

        $quoted = array_map(
            fn (string $word, int|string $index): string => $index === $last
                ? '"'.$word.'"*'
                : '"'.$word.'"',
            $words,
            array_keys($words),
        );

        return implode(' AND ', $quoted);
    }

    /**
     * Row ids for a table's FTS index, best match first.
     *
     * @return list<int>
     */
    public static function ids(string $table, string $term, int $limit): array
    {
        $expression = self::expression($term);

        if ($expression === null || ! self::available()) {
            return [];
        }

        try {
            $rows = DB::select(
                "select rowid from {$table}_fts where {$table}_fts match ? order by bm25({$table}_fts) limit ?",
                [$expression, $limit],
            );
        } catch (Throwable) {
            // A term FTS5 still refuses is a miss, not an error page.
            return [];
        }

        return array_values(array_map(fn (object $row): int => (int) ($row->rowid ?? 0), $rows));
    }
}
