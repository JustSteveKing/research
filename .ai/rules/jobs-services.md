---
paths:
  - 'app/{Jobs,Services}/**'
---

# Jobs Services

## Research credits are reserved at dispatch, settled at completion
Every Tabstack /research call costs credits (fast 250, balanced 350). RunDispatcher reserves the cost on threads.credits_reserved inside a locked transaction before queueing RunResearchJob; the job moves it to credits_spent on success or releases it on failure/cancel. Never create a ResearchRun directly — go through RunDispatcher, or parallel runs can overshoot a thread's budget. ThreadAutopilot only spawns follow-ups while availableCredits() covers another run and depth <= max_depth.

## Fast mode returns bare cited pages — distil the report
A real fast-mode /research response only carries citedPages with id/url/title/sourceQueries and claims: [] — no summary, relevance, reliability, gapEvaluations, outline or metrics (balanced adds them). Anything that reads claims/summaries to build knowledge will produce nothing. DistillRunJob therefore distils the run's report and asks the model to attribute each fact to one of the cited URLs; ProposeFollowUpsJob includes the report for the same reason. database/fixtures/research-fast.sse mirrors the real shape and is what offline mode replays.
