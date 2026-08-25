---
paths:
  - 'app/Services/Tabstack/**'
---

# Tabstack

## Tabstack /research is SSE-only; tests replay fixtures
POST https://api.tabstack.ai/v1/research always streams text/event-stream — there is no JSON mode. Events: start, planning|searching|prefetching|analyzing|following|evaluating|outlining|writing|judging|iteration :start/:end, then complete or error. The report and metadata (citedPages, gapEvaluations, metrics) only arrive on the complete event. HttpTabstackClient feeds chunks through App\Support\SseParser; FakeTabstackClient replays database/fixtures/*.sse. phpunit.xml forces TABSTACK_API_KEY empty and RESEARCH_OFFLINE=true so tests can never spend credits — keep it that way.
