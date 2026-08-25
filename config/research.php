<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Thread Defaults
    |--------------------------------------------------------------------------
    |
    | Defaults applied when a new research thread is opened. The credit budget
    | is a hard ceiling: the autopilot will refuse to spawn a follow-up run
    | once the reserved plus spent credits leave no room for another run.
    |
    */

    'default_mode' => env('RESEARCH_DEFAULT_MODE', 'fast'),

    'default_budget' => (int) env('RESEARCH_DEFAULT_BUDGET', 2000),

    'max_depth' => (int) env('RESEARCH_MAX_DEPTH', 3),

    /*
    |--------------------------------------------------------------------------
    | Knowledge Building
    |--------------------------------------------------------------------------
    */

    /**
     * Web research runs on its own queue; the local-model work gets another so
     * a slow distillation never blocks the next research stream.
     */
    'queue' => env('RESEARCH_QUEUE', 'research'),

    'knowledge_queue' => env('RESEARCH_KNOWLEDGE_QUEUE', 'knowledge'),

    'follow_ups_per_run' => (int) env('RESEARCH_FOLLOW_UPS_PER_RUN', 4),

    /**
     * Cosine similarity above which two follow-up questions are considered
     * the same question and the newcomer is discarded.
     *
     * Facts are deliberately not deduplicated this way. Measured against
     * nomic-embed-text, a reworded claim scores 0.977 and a flat
     * contradiction of the same claim scores 0.980 — the ordering is not even
     * reliable, let alone separable by a threshold. DetectContradictionsJob
     * asks the model instead. Questions have no such failure mode: two
     * near-identical questions are the same question.
     */
    'duplicate_threshold' => (float) env('RESEARCH_DUPLICATE_THRESHOLD', 0.92),

    'chat_context_facts' => (int) env('RESEARCH_CHAT_CONTEXT_FACTS', 24),

    /*
    |--------------------------------------------------------------------------
    | Cross-Thread Knowledge
    |--------------------------------------------------------------------------
    |
    | Threads are not sealed: a question can be answered by something another
    | thread already learned. Facts from other threads are retrieved only when
    | they clear the similarity floor, and are always labelled with the thread
    | they came from so the reader can tell them apart.
    |
    */

    'cross_thread_facts' => (int) env('RESEARCH_CROSS_THREAD_FACTS', 8),

    /**
     * Floors are set from the spread nomic-embed-text actually produces, not
     * from round numbers: unrelated sentences sit near 0.45, adjacent topics
     * near 0.59, the same subject near 0.66, and restatements above 0.9.
     */
    'cross_thread_min_similarity' => (float) env('RESEARCH_CROSS_THREAD_MIN_SIMILARITY', 0.62),

    'related_threads' => (int) env('RESEARCH_RELATED_THREADS', 5),

    'related_thread_min_similarity' => (float) env('RESEARCH_RELATED_THREAD_MIN_SIMILARITY', 0.62),

    /*
    |--------------------------------------------------------------------------
    | Contradictions and Staleness
    |--------------------------------------------------------------------------
    */

    /**
     * Facts closer than this are about the same claim, so they are worth
     * putting to the model. There is no upper bound: the most similar pairs
     * are precisely the ones where a duplicate and a contradiction are
     * indistinguishable by vector alone.
     */
    'contradiction_min_similarity' => (float) env('RESEARCH_CONTRADICTION_MIN_SIMILARITY', 0.7),

    /**
     * Pairs sent to the local model per run. Checking every candidate would
     * cost more than the distillation that produced them.
     */
    'contradiction_pairs_per_run' => (int) env('RESEARCH_CONTRADICTION_PAIRS_PER_RUN', 24),

    /*
    |--------------------------------------------------------------------------
    | Coverage
    |--------------------------------------------------------------------------
    |
    | The model names the sub-topics a complete answer needs; the facts that
    | speak to each are counted here, against the same embedding spread the
    | other floors are set from.
    |
    */

    'coverage_topics' => (int) env('RESEARCH_COVERAGE_TOPICS', 6),

    'coverage_min_similarity' => (float) env('RESEARCH_COVERAGE_MIN_SIMILARITY', 0.62),

    /** Fewer facts than this on a sub-topic and it is thin rather than covered. */
    'coverage_thin_below' => (int) env('RESEARCH_COVERAGE_THIN_BELOW', 3),

    /**
     * A fact older than this is shown as stale: still stored, no longer
     * trusted without a re-check.
     */
    'stale_after_days' => (int) env('RESEARCH_STALE_AFTER_DAYS', 180),

    /**
     * Replay recorded SSE fixtures instead of calling Tabstack. Useful for
     * working on the UI without spending credits.
     */
    'offline' => (bool) env('RESEARCH_OFFLINE', false),

];
