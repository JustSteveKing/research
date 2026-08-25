---
paths:
  - 'app/{Services/Research/RelatedThreads.php,Jobs/SynthesizeThreadBriefJob.php}'
---

# Research

## A thread is only matchable once it has a brief
threads.embedding is the topic vector, written by SynthesizeThreadBriefJob from title + seed question + brief. RelatedThreads and the "borrowed facts" panel both return nothing until that job has run, which is correct — before a brief there is nothing to match on. If the brief prompt changes, the vector changes with it, so rebuilds re-match threads for free.
