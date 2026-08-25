---
paths:
  - 'app/{Jobs,Services,Http/Controllers}/**'
---

# Controllers

## Curated facts survive rebuilds; dismissed rows must never be deleted
facts.pinned_at / edited_at / dismissed_at mark a fact as the reader's, not the model's. KnowledgeRebuilder only deletes facts where all three are null. Dismissed rows in particular must stay in the table: DistillRunJob's duplicate check reads every fact's embedding (including dismissed ones) as the "known" set, so deleting a dismissed fact lets the next run re-add it. Use Fact::active() for anything the reader sees — brief, chat retrieval, export, contradiction candidates.
