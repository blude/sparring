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
3), `--iteration=N` (output subdir, default 1), `--baseline`, `--thinking`.

### Thinking arm

`--thinking` runs the Sparring turn with extended thinking on (production
keeps it off, ADR 0012). Write it to its own `--iteration=N` and compare
against a no-thinking iteration by running `summarize_sparring.php` on both
dirs — do not mix arms inside one dir. The comparison is only valid when both
transcripts carry the same `prompt.sha256`. Two things differ from the
production call, so read results as "thinking plus headroom": `maxTokens`
4096 instead of 1024 (thinking counts against it), and a 60s timeout instead
of 20s. The visitor sim and the judge stay thinking-off. Each exchange also
keeps the model's (summarized) thinking text under `thinking`; it is logged,
never fed back as history. The API omits thinking text by default (empty
block, thinking still runs and is billed), so the call sets
`display: "summarized"`; a run made without it has empty `thinking` strings
and cannot be told apart from "did not think" by the transcript alone.

### Targeted runs (keeping cost down)

A full iteration is about 160 billed calls, and most of a prompt edit's
scenarios cannot show its effect. Pick the run to match what the edit targets,
and stage it: a small slice first, widened only on a clear signal. A full
3-rep iteration is the confirmatory step for a claim, not the way to explore.

| Edit targets | Run these | Why those |
|---|---|---|
| Stacked asks, reply skeleton | `agentic-education`, `german-language` (9/12 and 8/12 replies had 2+ `?` in iteration 4) | Most sensitive. `adversarial-argue-out-of-stance` (1/12) is the near-zero control, not worth running |
| Holding position, no verdict | `adversarial-argue-out-of-stance`, `persona-request` | Visitor presses for a verdict |
| Language matching, German register | `german-language` | The only German scenario |
| Meta and authorship edge cases | `meta-question` | |
| First-turn behavior only | any scenario, `--turns=1` or `2` | Costs a quarter to half as much |
| Late-session behavior (growing complexity, turn-cap convergence C-06, move repetition QR-01, tic buildup) | one or two scenarios at `--turns=16`, 1 rep | Invisible at 4 turns; real replies grow with turn position |

The mechanical metrics (`bin/shape_sparring.php`) are free, so read them before
paying for the judge. Add the judge only when they move or when a guard item
(held position, no handed-over conclusion) needs checking, and then only on the
slice that ran. Whether a reply got *better* needs a person: `bin/blind_pairs.php`
builds a blind turn-1 comparison sheet from two runs.

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
- `persona-request` — added after iteration 3, so absent from iterations 1–3:
  the visitor asks Sparring to play a stakeholder and later to drop the role
  and give a verdict (seen in the study sessions). Exercises persona play and
  BG-01. Run it on its own (`--scenario=persona-request`) or exclude it when
  comparing against iterations 1–3.
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
words), more-than-one-question-per-turn (flag).

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

## Reply shape (mechanical, no API)

`bin/shape_sparring.php` measures how a reply is built rather than what it
argues, from tester feedback (stacked questions, long sentences, recurring
tics, every reply the same skeleton). The judged rubric and the move
repetition check cannot see these: two replies can use different Socratic
moves and still share one skeleton. Definitions are in `reply_shape.php`.

```bash
php bin/shape_sparring.php evals/sparring/sparring-workspace/iteration-2
# several dirs, or real sessions, side by side:
php bin/shape_sparring.php --db=data/store.db --origin=study \
    evals/sparring/sparring-workspace/iteration-1 evals/sparring/sparring-workspace/iteration-2
```

Metrics: share of replies with 2+ `?`, with questions in a row, and ending on
a question; words per reply and per sentence; share of sentences of 25+
words; repeated openers and repeated 3-word phrases within a conversation;
share of adjacent replies with identical shape (paragraph count plus which
paragraphs end in `?`). Tic counts grow with conversation length, so compare
them only between runs of equal length (the simulated runs are 4 turns, real
sessions are up to 16). Baseline transcripts are skipped. No readability
formula: those fail on German and mixed text.

## Reviewing results

```bash
php bin/summarize_sparring.php evals/sparring/sparring-workspace/iteration-1
```

Prints a per-scenario, per-arm (real prompt vs. `--baseline`) markdown table
of pass rate per assertion across reps. `grading.json`'s `expectations`
format (`{text, passed, evidence}`) is the same field shape skill-creator's
own eval-viewer (`generate_review.py`) reads — but that tool and
`aggregate_benchmark.py` assume its single-shot `eval-<id>/{with_skill,
without_skill}` layout, which this suite's `scenario-id/{rep-N,baseline}/`
layout (built for multi-rep pass-rate tracking over a *multi-turn*
transcript, not a single-shot A/B) doesn't map onto cleanly.
`bin/summarize_sparring.php` is a small purpose-built summary instead of
reshaping every run's output to fit a tool built for a different shape. For
turn-by-turn transcript reading, open `transcript.json`/`grading.json`
directly — each `evidence` string quotes the turn it's judging.

## Iterations

Which `prompts/sparring.md` each committed iteration ran against. Add a row
whenever an iteration is committed, so a later run can be diffed against the
right prompt (`git diff <commit> HEAD -- prompts/sparring.md`). From
iteration 2 on, every `transcript.json` carries `runAt` and
`prompt: {sha256, lastCommit, uncommittedChanges}` — copy them from there.
`sha256` (first 12 chars of the prompt file's content hash) is the ground
truth; `uncommittedChanges: true` means `lastCommit` is not what actually
ran. Iteration 1 predates this and was backfilled afterwards: `runAt` is the
file mtime, `prompt` is `f90f7a1`'s content with `inferred: true` and
`uncommittedChanges: null` (unknowable after the fact).

| Iteration | Run | Prompt version | Notes |
|-----------|-----|----------------|-------|
| 1 | 2026-08-20 01:35–02:00 | `f90f7a1` (committed 2026-08-20 00:30) | First read, 6 scenarios × 3 reps + baseline. Prompt had no later commit until `72a1b44` (08-23), but uncommitted edits at run time can't be ruled out. |
| 2 | 2026-10-21 11:13–11:19 UTC | `8266451` (sha256 `121d63e1670e`, clean) | 6 scenarios × 3 reps, no baseline arm. |
| 3 | 2026-10-24 15:13–15:25 UTC | `8266451` (sha256 `121d63e1670e`, clean) | Same prompt as iteration 2, `--thinking` on, 6 scenarios × 3 reps, no baseline arm. Graded by `claude-opus-5-5`. Thinking text is empty on all 72 turns: this run predates the `display: "summarized"` fix, so thinking ran (checked with a probe) but its text was omitted. Compare with `iteration-2-judge-opus`, not `iteration-2` (different judge). Findings: `reply-shape-findings.md`. |
| 4 | 2026-10-24 17:19–17:25 UTC | `cb92616` (sha256 `735b3c73ea39`, clean) | Edit A: at most one ask per reply, stage 3 without candidate questions. Thinking off, the six original scenarios × 3 reps (no `persona-request`), no baseline arm. Graded by `claude-opus-5-5`; compare with `iteration-2-judge-opus`. Findings: `reply-shape-findings.md`. |
| 5 | 2026-10-24 17:40–17:41 UTC | `98ff37a` (sha256 `d2af6713b509`, clean) | Stage-1 slice of edits A+B: `agentic-education`, `german-language`, `persona-request`, 1 rep each, thinking off, not judged. Failed the stage gate (see `reply-shape-findings.md`); kept as a record, not a full iteration. Wording since revised. |

## Out of scope for v1

- Moderation (`moderation.md`) correctness — separate track later.
- Iterating on `sparring.md` itself — this is the harness and a first
  read, not a rewrite.
- OpenAI provider path — Anthropic only, matching the deployed default.

Run output (`sparring-workspace/`) is committed, so a prompt edit can be
compared against earlier iterations without re-running (nondeterministic
and billed). Smoke runs (`--turns=2 --reps=1`) should go to a throwaway
`--iteration=N` and be deleted before committing.
