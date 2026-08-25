---
paths:
  - 'app/{Support/FullTextQuery.php,Http/Controllers/SearchController.php}'
---

# Support Controllers

## Palette search is FTS5 with a semantic fallback
threads/facts/sources each have a SQLite FTS5 external-content index kept current by triggers (see create_search_indexes). Never build a MATCH expression from raw input — FullTextQuery::expression() quotes each term, ANDs them and prefix-wildcards the last, because a half-typed query is otherwise a syntax error rather than a miss. Everything degrades to LIKE on non-sqlite drivers.

When keyword search returns fewer than 3 facts and the query is 3+ words, SearchController re-ranks facts by embedding and sets `semantic: true` in the response so the palette can label the section differently. That is the only path that spends an embedding call per keystroke, which is why it is gated.
