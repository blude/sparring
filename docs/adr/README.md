# Architecture Decision Records

Numbered, immutable records of implementation-level technical decisions:
stack, tooling, and reversible engineering calls. One decision per file,
`NNNN-short-title.md`, in [Nygard lightweight][nygard] form (Status /
Context / Decision / Consequences).

## How this relates to `spec/`

`spec/` is the *design record* — what has to be true about the installation
and why, modelled in the four-level framework (L1 solution, L2 system, L3
element, LX realization). Behavioural and product decisions belong there.

`docs/adr/` is narrower and lower: how the code is built. A decision that
would just restate a spec constraint does not need an ADR — but several
ADRs here *cite* a spec item (`AP-01` … `AP-04`) because the spec states
the principle and the ADR records the concrete engineering choice that
follows from it.

## Adding one

1. Copy the shape of an existing record.
2. Next free number. Never renumber or delete — a wrong decision gets a
   new ADR that supersedes the old one, and the old one's Status changes
   to `Superseded by NNNN`.
3. Status is one of: `Accepted`, `Superseded by NNNN`, `Deprecated`.
4. Keep it to roughly one screen. Link the commit(s) and any `spec/`,
   `TODO.md`, or `prompts/CHANGELOG.md` entry that carries the detail.

Most records below were back-filled on 2026-09-10 from `spec/`,
`CHANGELOG.md`, `TODO.md`, `prompts/CHANGELOG.md`, and the commit history.
Their "Date" is the original decision date where it could be recovered.

## Index

| # | Title | Status |
|---|-------|--------|
| [0001](0001-vanilla-php-sqlite-no-framework.md) | Vanilla PHP + SQLite, no framework, no build step | Accepted |
| [0002](0002-four-level-design-framework.md) | Four-level design framework as the design record | Accepted |
| [0003](0003-assert-based-smoke-tests.md) | Assert-based smoke tests, no test framework | Accepted |
| [0004](0004-all-logic-in-the-backend.md) | All logic and credentials server-side; clients are presentation only | Accepted |
| [0005](0005-polling-not-push.md) | Polling, not push, for the wall display | Accepted |
| [0006](0006-session-identity-in-the-url.md) | Session identity travels in the URL; no accounts, no identity cookies | Accepted |
| [0007](0007-one-file-backed-sqlite-datastore.md) | One file-backed SQLite datastore in WAL mode, inside the backend | Accepted |
| [0008](0008-llm-provider-abstraction.md) | LLM provider abstraction with an env-selected provider | Accepted |
| [0009](0009-curriculum-retrieval-via-fts5.md) | Curriculum retrieval via SQLite FTS5, no vector store | Accepted |
| [0010](0010-juiciness-effect-framework.md) | Juiciness effects on native web APIs, zero dependencies, kill switches | Accepted |
| [0011](0011-client-only-debug-flags.md) | Debug flags are client-only; the backend has no "mode" concept | Accepted |
| [0012](0012-extended-thinking-disabled.md) | Extended thinking disabled on both Anthropic calls | Accepted, revisit planned |
| [0013](0013-mermaid-rendering-removed.md) | Mermaid diagram rendering in responses | Superseded by removal |
| [0014](0014-ios-keyboard-viewport-fix.md) | iOS keyboard viewport handled with `--vvh` + scroll-lock, not a hand-rolled handler | Accepted |

[nygard]: https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions
