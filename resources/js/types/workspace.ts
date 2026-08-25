import type { KnowledgeState, ResearchMode, ThreadStatus } from './research';

export interface RailThread {
    id: number;
    title: string;
    status: ThreadStatus;
    knowledge_state: KnowledgeState;
    facts_count: number;
    sources_count: number;
    is_busy: boolean;
    pinned: boolean;
    unread: number;
    updated_at: string;
}

export interface ActivitySnapshot {
    active_run: {
        id: number;
        thread_id: number;
        phase: string | null;
        status: string;
        started_at: string | null;
    } | null;
    knowledge_pending: number;
    ollama_reachable: boolean;
    credits_spent: number;
    credits_budgeted: number;
    checked_at: string;
}

export interface SinceLastOpened {
    runs: number;
    facts: number;
    questions: number;
    credits: number;
    since: string;
}

export interface SearchResults {
    threads: { id: number; title: string; status: ThreadStatus }[];
    facts: {
        id: number;
        thread_id: number;
        thread_title: string;
        statement: string;
    }[];
    sources: {
        id: number;
        thread_id: number;
        title: string | null;
        url: string;
        domain: string | null;
    }[];
    /** Facts were matched by meaning because the words did not match. */
    semantic: boolean;
}

export interface WorkspaceSharedProps {
    rail: RailThread[];
    activity: ActivitySnapshot;
    setup: {
        /** No key has been entered and the prompt has not been dismissed. */
        needs_tabstack_key: boolean;
    };
}

export interface ThreadComposerDefaults {
    mode: ResearchMode;
    credit_budget: number;
    max_depth: number;
    costs: Record<ResearchMode, number>;
}
