<?php

namespace App\Support;

/**
 * Small vector helpers for the locally stored embeddings. Threads hold
 * hundreds of facts, so plain PHP maths is fast enough and avoids pulling in
 * a vector extension that would complicate the desktop bundle.
 */
class Vector
{
    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $index => $value) {
            $other = $b[$index];
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Rank candidates against a query vector.
     *
     * @param  array<int, float>  $query
     * @param  iterable<TKey, array<int, float>|null>  $candidates  keyed by identifier
     * @return array<TKey, float> similarity keyed by identifier, best first
     *
     * @template TKey of array-key
     */
    public static function topK(array $query, iterable $candidates, int $k): array
    {
        $scores = [];

        foreach ($candidates as $key => $vector) {
            if (! is_array($vector) || $vector === []) {
                continue;
            }

            $scores[$key] = self::cosine($query, $vector);
        }

        arsort($scores);

        return array_slice($scores, 0, $k, preserve_keys: true);
    }

    /**
     * The highest similarity found between the vector and any candidate.
     *
     * @param  array<int, float>  $query
     * @param  iterable<array-key, array<int, float>|null>  $candidates
     */
    public static function maxSimilarity(array $query, iterable $candidates): float
    {
        $best = 0.0;

        foreach ($candidates as $vector) {
            if (! is_array($vector) || $vector === []) {
                continue;
            }

            $best = max($best, self::cosine($query, $vector));
        }

        return $best;
    }
}
