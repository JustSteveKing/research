---
paths:
  - 'app/Jobs/**'
  - app/Jobs/DetectContradictionsJob.php
  - app/Jobs/RunResearchJob.php
---

# Jobs

## Local-model jobs need retry_after above every job timeout
Ollama distillation/brief jobs run for minutes. The database queue's default retry_after (90s) re-reserves them mid-run, and with tries=1 that surfaces as "DistillRunJob has been attempted too many times" and silently loses the facts. Ordering that must hold: job $timeout < NativePHP queue_worker timeout < queue.connections.database.retry_after (currently 900 < 1200 < 2400). tests/Feature/JobQueueRoutingTest.php asserts it. Web research runs on the `research` queue, local model work on `knowledge`, so a slow distillation never blocks the next stream.

## Contradiction candidates sit between the contradiction floor and the duplicate threshold
Distillation already drops facts above research.duplicate_threshold (0.92), so a clash can only live below it. Candidates are pairs with cosine in [contradiction_min_similarity, duplicate_threshold), drawn from every thread — a conflict with another topic's research counts. Pairs are stored once, ordered by Contradiction::orderPair (lowest fact id first), and existing pairs (resolved included) are skipped so a rebuild never re-asks the model. Read either side with Contradiction::forFacts().

## Retry transport failures only, and lock just the stream
tries = 3. TabstackException carries isRetryable(): transport errors, 429 and 5xx retry; 4xx does not, and an SSE `error` event is always final. The credit reservation is held across attempts and settled once in fail()/success, so a retry never double-charges; each attempt deletes the run's RunEvents first because the stream replays from the top.

The one-at-a-time cap is a Cache::lock around the network call only, not a WithoutOverlapping middleware. The middleware holds for the whole job, and the knowledge chain dispatched at the end of handle() queues the next run — which would then be locked out by the job that queued it. Tests caught this as starved autopilot chains under the sync queue.
