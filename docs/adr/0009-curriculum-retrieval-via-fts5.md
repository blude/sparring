# 9. Curriculum retrieval via SQLite FTS5, no vector store

- **Status:** Accepted
- **Date:** 2026-08-23 (back-filled 2026-09-10)
- **References:** commit `75f00bf` "Add curriculum FTS5 ingestion + search (poor woman's RAG, step 1)"; `CLAUDE.md` "Test"; `src/Store.php` `searchCurriculum()` / `searchCurriculumConcepts()`; `tests/smoke_curriculum_retrieval.php`; `TODO.md` "Curriculum retrieval"

## Context

Turn-1 grounding wants a few relevant excerpts from the Digital Design
curriculum corpus (`data/curriculum/*.md`). The reflexive answer is an
embeddings pipeline and a vector database. That means an embedding-model
dependency or API, a vector store, and an ingestion job — a large addition
to a no-build, single-SQLite-file POC, for a corpus of a few dozen German
pages.

## Decision

Retrieve with SQLite's built-in FTS5 full-text index over the corpus.
Ranking is title-weighted BM25 with stopword filtering and prefix
matching. `searchCurriculumConcepts()` does a vocabulary-restricted lookup
for the turn-1 auto-grounding path. Ingestion is `bin/import_curriculum.php`
syncing an FTS table from disk; `bin/probe_curriculum.php` prints what a
query would surface. Described in-repo as "poor woman's RAG".

## Consequences

- Zero new dependencies; the index lives in the same `store.db` file.
- Lexical, not semantic: retrieval quality depends on the visitor's
  wording overlapping the corpus wording. Known gaps are tracked in
  `TODO.md` — hub-like pages out-ranking narrow ones, and English
  contributions coincidentally matching German titles with loanwords.
  These are treated as content-authorship and prompt-steering problems,
  not reasons to swap in vectors.
- Excerpts are never asserted as fact to the visitor, which lowers the
  cost of an imperfect match.
- Retrieval quality is pinned by `tests/smoke_curriculum_retrieval.php`
  against committed fixtures.
