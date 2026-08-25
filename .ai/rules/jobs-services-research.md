---
paths:
  - 'app/{Jobs,Services/Research}/**'
---

# Jobs Services Research

## Never deduplicate facts by cosine — it deletes contradictions
Measured against nomic-embed-text: a reworded claim scores 0.977, the same claim with a different date ("targets 2027" vs "targets 2030") scores 0.980. A contradiction is *more* similar than a paraphrase, so no threshold separates them. DistillRunJob therefore only drops exact repeats (normalised statement text); DetectContradictionsJob puts every pair above contradiction_min_similarity (0.70, no ceiling) to the model, which returns duplicate / contradiction / distinct and the job deletes, flags or keeps. research.duplicate_threshold now applies to follow-up questions only.

Reference spread for that embedder: unrelated ~0.45, adjacent topic ~0.59, same subject ~0.66, restatement >0.90. Tune any new similarity floor against those, not against round numbers or the test stub in tests/Pest.php (which is word-overlap and spreads much wider).
