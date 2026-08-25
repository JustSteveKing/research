export type ResearchMode = 'fast' | 'balanced';

export type WatchCadence = 'daily' | 'weekly' | 'monthly';

/** What the last scheduled re-run of the seed question turned up. */
export interface WatchReport {
    checked_at: string;
    run_id?: number;
    facts?: number;
    sources?: number;
    disputes?: number;
    stale?: number;
    headline?: string;
    /** Set instead of a headline when the watch had to be switched off. */
    stopped?: string;
}

export type ThreadStatus =
    'idle' | 'running' | 'paused' | 'complete' | 'failed';

export type RunStatus =
    'queued' | 'streaming' | 'completed' | 'failed' | 'cancelled';

export type QuestionStatus = 'pending' | 'queued' | 'done' | 'rejected';

export type KnowledgeState =
    | 'idle'
    | 'queued'
    | 'distilling'
    | 'checking'
    | 'planning'
    | 'writing'
    | 'mapping'
    | 'failed';

export interface ThreadSummary {
    id: number;
    title: string;
    seed_question: string;
    status: ThreadStatus;
    mode: ResearchMode;
    credit_budget: number;
    credits_spent: number;
    credits_reserved: number;
    auto_follow_up: boolean;
    runs_count: number;
    sources_count: number;
    facts_count: number;
    updated_at: string;
}

export interface Thread {
    id: number;
    title: string;
    seed_question: string;
    status: ThreadStatus;
    mode: ResearchMode;
    credit_budget: number;
    credits_spent: number;
    credits_reserved: number;
    available_credits: number;
    auto_follow_up: boolean;
    max_depth: number;
    brief: string | null;
    brief_generated_at: string | null;
    coverage: CoverageTopic[];
    coverage_mapped_at: string | null;
    watch_cadence: WatchCadence | null;
    watched_at: string | null;
    last_watch_report: WatchReport | null;
    knowledge_error: string | null;
    knowledge_state: KnowledgeState;
    knowledge_state_label: string;
    is_busy: boolean;
    pinned: boolean;
    sources_count: number;
    facts_count: number;
    is_running: boolean;
}

/** A sub-topic a complete answer needs, and how much of it is in hand. */
export interface CoverageTopic {
    topic: string;
    why: string | null;
    facts_count: number;
    status: 'open' | 'thin' | 'covered';
}

export interface RunEvent {
    id: number;
    event: string;
    message: string | null;
    created_at: string;
}

export interface Run {
    id: number;
    parent_run_id: number | null;
    query: string;
    mode: ResearchMode;
    depth: number;
    status: RunStatus;
    phase: string | null;
    report: string | null;
    error: string | null;
    credits_cost: number;
    started_at: string | null;
    completed_at: string | null;
    pages_analyzed: number | null;
    events: RunEvent[];
}

export interface FollowUp {
    id: number;
    question: string;
    origin: 'gap' | 'ollama' | 'user';
    status: QuestionStatus;
    score: number;
    rationale: string | null;
}

export interface Source {
    id: number;
    url: string;
    domain: string | null;
    title: string | null;
    summary: string | null;
    relevance: string | null;
    reliability: string | null;
    claims: string[] | null;
    cited_count: number;
    published_at: string | null;
}

export interface Fact {
    id: number;
    source_id: number | null;
    statement: string;
    tags: string[] | null;
    confidence: number;
    /** Kept through a rebuild, and never shown as stale. */
    pinned: boolean;
    edited: boolean;
    dismissed: boolean;
    /** Older than the staleness window, so it wants a re-check. */
    stale: boolean;
    age_days: number;
    asserted_at: string;
    source: {
        id: number;
        url: string;
        title: string | null;
        domain: string | null;
    } | null;
}

/** One side of a disagreement; `thread` is set only when it was borrowed. */
export interface ContradictionSide {
    id: number;
    statement: string;
    thread: { id: number; title: string } | null;
}

export interface Contradiction {
    id: number;
    note: string | null;
    similarity: number;
    fact_ids: [number, number];
    sides: [ContradictionSide, ContradictionSide];
}

export interface RelatedThread {
    id: number;
    title: string;
    similarity: number;
    facts_count: number;
    shared_domains: string[];
}

/** A fact another thread learned that answers this thread's topic too. */
export interface BorrowedFact {
    id: number;
    statement: string;
    confidence: number;
    thread: { id: number; title: string };
    source: { url: string; domain: string | null } | null;
}

export type ExportFormat = 'markdown' | 'json' | 'bibtex';

export interface ChatMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    citations:
        { url: string; title: string | null; thread: string | null }[] | null;
    created_at: string;
}

export interface ThreadDefaults {
    mode: ResearchMode;
    credit_budget: number;
    max_depth: number;
    costs: Record<ResearchMode, number>;
}
