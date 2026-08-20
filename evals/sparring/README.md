# Evaluation suite — prompts/sparring.md

Multi-turn eval harness for the Sparring system prompt. Closes `TODO.md`'s
"Add prompt evaluation suite". No prior automated coverage existed for
`sparring.md`'s conversational/pedagogical behavior — `tests/smoke_sparring.php`
and `tests/smoke_llm_client.php` test plumbing only (gates, status codes,
failure classification) against a `FakeLlmClient` that always returns the
same canned string.

## Why multi-turn simulation

Sparring is a stateful Socratic dialogue, not a single-shot task — its own
`core_mechanism` requires varying moves turn to turn and holding a position
under pressure across a session. A single-turn eval can't exercise either.
Each eval case is a **scenario**: a pinned opener plus a "visitor" persona
that argues back for a few turns against the real prompt, via live
Anthropic API calls.

## What this does NOT go through

`bin/eval_sparring.php` calls `AnthropicLlmClient::generateResponse()`
directly — the exact `system prompt + growing history → response` payload
production sends — not `Sparring::processTurn()`. `processTurn` adds four
things that would measure something other than the prompt under CLI
conditions they weren't built for:

- `RateLimiter` reads `$_SERVER['REMOTE_ADDR']`, absent under CLI — every
  simulated turn would likely hash to the same origin and trip the limiter
  mid-run.
- The moderation gate is a *separate* prompt (`moderation.md`) that fails
  closed and has a live known bug (`TODO.md`: benign messages sometimes
  blocked) — letting it eat an adversarial scenario measures moderation,
  not Sparring. Moderation gets its own (smaller, single-shot) eval track
  later, using `moderation.md`'s own worked examples as fixtures.
- `CONTRIBUTION_MAX_CHARS=600` would silently reject a slightly-long
  simulated visitor turn.
- Real `Store`/session/TTL bookkeeping adds nothing for a throwaway run.

## Running it

```bash
export ANTHROPIC_API_KEY=...   # real, billed calls

# Cheap smoke check before spending a full run's budget:
php bin/eval_sparring.php --scenario=wicked-problem --turns=2 --reps=1

# Full run, one scenario:
php bin/eval_sparring.php --scenario=wicked-problem

# Full run, every scenario, default 3 reps each (recommended for a real
# read — see "Why 3 reps" below):
php bin/eval_sparring.php

# No-prompt comparison arm (forced to 1 rep — see "Baseline" below):
php bin/eval_sparring.php --baseline

# Grade everything written to an iteration directory:
php bin/grade_sparring.php evals/sparring/sparring-workspace/iteration-1

# Summarize pass rates across the graded run:
php bin/summarize_sparring.php evals/sparring/sparring-workspace/iteration-1
```

Flags: `--scenario=<id>[,<id>...]` (default: all files in `scenarios/`),
`--turns=N` (override each scenario's own turn count), `--reps=N` (default
3), `--iteration=N` (output subdir, default 1), `--baseline`.

### Cost/time expectations

Default full run (6 scenarios × 4 turns × 2 calls/turn × 3 reps) ≈ 144
generation calls. Add the judge pass (1 call per non-baseline transcript,
18 of them) and the baseline arm (~48 calls, 1 rep) and a full iteration is
on the order of 200 calls. Budget real minutes, not seconds — this is
sequential, and each Sparring/visitor turn is a real network round trip.

### Why 3 reps

Three independently nondeterministic layers stack: the visitor simulation,
Sparring's own generation, and the judge. A single run's pass/fail per
scenario is noise — report **pass rate** across reps, not a boolean.

### Baseline

The no-prompt arm (`--baseline`, `system` omitted) is low-information — of
course a bare model answers directly instead of holding a Socratic
position. It's kept at 1 rep, cheap, mostly so the eval-viewer has a
comparison column. The baseline that actually matters for *iterating* on
the prompt is the previous version of `sparring.md` itself:

```bash
git show HEAD:prompts/sparring.md > /tmp/sparring-prev.md
# then diff behavior between the previous prompt and a working-tree edit
```

## Scenarios

Six for v1, in `scenarios/*.json`. Three (`wicked-problem`,
`agentic-education`, `feasibility-desirability`) reuse the verbatim opener
from the matching `data/pilot/*.json` transcript — hand-authored, in-voice
examples — but are **never graded for similarity** to the pilot response;
that would punish valid variation. The pilot responses are used only as
tone-calibration examples in `rubric.md`.

Each scenario pins its **opener** verbatim (so turn-1 responses are
directly comparable across reps and across future prompt edits) and gives
the visitor-persona simulation a `persona` + `directive` for turns 2+. The
directive matters: a naive visitor sim capitulates in one turn and never
stresses the "hold position under pressure" behavior the prompt is tuned
for, so every scenario tells the persona not to fully give in before
turn 3.

- `wicked-problem`, `agentic-education`, `feasibility-desirability` — core
  Socratic behavior on real design-theory topics (pilot openers).
- `adversarial-argue-out-of-stance` — `AP-07`: the visitor explicitly tries
  to instruct Sparring to drop its stance and "just answer." Correct
  behavior is neither refusal (obstruction) nor compliance (capitulation)
  but argumentative engagement.
- `meta-question` — `<edge_cases>`: "what is Sparring" should get the
  practice-fight analogy directly, not an explanation of the pedagogical
  mapping.
- `german-language` — FH Dortmund exhibition context; `sparring.md` carries
  German design-theory vocabulary and a German fill-the-blanks example,
  untested whether voice/quality holds in a German-language exchange.

## Grading

`bin/grade_sparring.php <workspace-dir>` walks every `transcript.json`
under the directory and writes a sibling `grading.json`
(`{expectations: [{text, passed, evidence}]}` — the field names the
skill-creator eval-viewer depends on).

**Mechanical** (plain code, free, zero variance, every rep): banned
phrases (`load-bearing`, `failure mode` hard-fail; `move`/`moves` outside
a chess context only *flags*, since the prompt allows the word there and
hard-failing produces false positives), length (≤2 paragraphs, ≤~120
words), more-than-one-question-per-turn (flag), Mermaid fence well-
formedness (mirrors the regex in `public/assets/mermaid-render.js`'s
`SparringMermaid.extract()`).

**Judge** (one structured-output LLM call per non-baseline transcript,
against `rubric.md`): handed-over-conclusion, held-position vs.
obstruction vs. premature-capitulation, premature closure, meta-commentary
/ lecturing about technique, domain-grounding precision, voice/style fit —
plus a per-turn Socratic-move label from `socratic-moves.json`'s closed
14-item vocabulary (the two tables in `sparring.md`). Adjacent-turn move
repetition is then checked **programmatically** on those labels, not asked
of the judge holistically — classifying into a fixed vocabulary is far
more stable than a holistic "was there variety?" judgment.

The baseline arm only gets mechanical checks — judging it against
`sparring.md`'s own rubric is meaningless since it never saw that prompt.

## Reviewing results

```bash
php bin/summarize_sparring.php evals/sparring/sparring-workspace/iteration-1
```

Prints a top-line **core score** plus a per-scenario, per-arm (real prompt
vs. `--baseline`) markdown table of pass rate per assertion across reps.
`grading.json`'s `expectations` format (`{text, passed, evidence}`) is the
same field shape skill-creator's own eval-viewer (`generate_review.py`)
reads — but that tool and `aggregate_benchmark.py` assume its single-shot
`eval-<id>/{with_skill, without_skill}` layout, which this suite's
`scenario-id/{rep-N,baseline}/` layout (built for multi-rep pass-rate
tracking over a *multi-turn* transcript, not a single-shot A/B) doesn't map
onto cleanly. `bin/summarize_sparring.php` is a small purpose-built summary
instead of reshaping every run's output to fit a tool built for a different
shape. For turn-by-turn transcript reading, open `transcript.json`/
`grading.json` directly — each `evidence` string quotes the turn it's
judging.

**Core score** deliberately excludes `[flag]`-prefixed assertions
(question-mark count, "move" word use — advisory, not failures): blending
those into one number with the hard rubric items would let a prompt look
"worse" just because visitors asked more follow-ups, unrelated to actual
quality. It's the mean pass rate across banned phrases, the Mermaid fence
check, and all 6 judge rubric items, aggregated across every scenario. Read
it alongside the breakdown underneath, not instead of it — a drop should be
immediately traceable to which specific assertion moved.

### Comparing two iterations

```bash
php bin/summarize_sparring.php evals/sparring/sparring-workspace/iteration-2 --compare=evals/sparring/sparring-workspace/iteration-1
```

The positional argument is the run you're checking, `--compare` points at
the run to diff against (so this reads "how does iteration 2 compare to
iteration 1"). Prints the core-score delta plus every `prompt`-arm
assertion sorted by largest `|delta|` first, so the assertions that
actually moved surface immediately instead of getting lost in a full
unsorted table. The `baseline` arm is left out of the diff — it doesn't
change between iterations of the same prompt edit, and is already visible
via a plain (non-`--compare`) run on either directory.

At 3 reps per scenario, three nondeterministic layers stack (visitor
simulation, Sparring's own generation, the judge) — don't trust a single
iteration's small delta (a few points) as real signal. Look for a
consistent direction across 2-3 iterations before concluding an edit
helped or hurt.

### Refine loop

1. Run the full suite against the current `sparring.md`, grade, summarize
   — this is your `iteration-1` baseline.
2. Edit `sparring.md` for a specific failure pattern you read in a
   `grading.json` `evidence` string (the evidence tells you what to fix;
   the pass/fail count only tells you where to look). Log the edit in
   `prompts/CHANGELOG.md` per this repo's convention.
3. Re-run just the scenario(s) that failed first — `--scenario=<id>`, cheap
   — to confirm the edit actually moved that assertion before spending on
   a full run.
4. If it moved the right way, run the full suite at `--iteration=2`, grade
   it, then `--compare=iteration-1` against the previous one. Check the
   movers table for collateral damage — fixing one thing (e.g. a turn-4
   capitulation pattern) can regress another (e.g. turn length), and the
   per-assertion diff catches that where a single core-score number
   wouldn't.

## Out of scope for v1

- Moderation (`moderation.md`) correctness — separate track later.
- Iterating on `sparring.md` itself — this is the harness and a first
  read, not a rewrite.
- OpenAI provider path — Anthropic only, matching the deployed default.

Run output (`sparring-workspace/`) is gitignored — only the runner,
scenarios, rubric, and this README are committed.
