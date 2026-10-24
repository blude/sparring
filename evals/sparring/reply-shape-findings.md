# Reply shape: tester feedback, measurement, and the thinking arm

Written 2026-10-24. Commands to reproduce are at the end.

## Why this was looked at

Three testers (none writing in their native language) said Sparring used
tics, complex sentence structures, several questions in a row, and that its
replies were uniform ("counter, question, counter, question"). Three people is a
very small sample, so this is treated as a hypothesis to test, not a verdict.

## The existing evaluation could not see it

The judged rubric checks content (handed-over conclusion, held position,
closure, meta-commentary, grounding, voice). The move-repetition check labels
the *Socratic move* of each turn from a closed vocabulary of 14. Two replies can
use different moves and still share one skeleton, so a variety check at the
move level cannot see a complaint about variety at the surface level. The
mechanical at-most-one-question check was already failing about 90% of
transcripts, but it is a pass/fail flag, so it did not show how bad or where.

## Measuring shape

`bin/shape_sparring.php` (definitions in `reply_shape.php`) measures how a reply
is built, in plain code: no API calls, no judge, no variance. Same
measurements, three sources:

| | study (3 testers) | live (exhibition) | simulated, iteration 2 |
|---|---|---|---|
| conversations / replies | 3 / 30 | 40 / 188 | 18 / 72 |
| replies with 2+ `?` | 83% | 55% | 51% |
| replies with questions in a row | 37% | 30% | 26% |
| replies ending on a question | 87% | 60% | 64% |
| words per reply | 143 | 104 | 117 |
| words per sentence | 21.0 | 17.7 | 18.4 |
| sentences of 25+ words | 38% | 25% | 28% |
| adjacent replies with the same shape | 41% | 32% | 35% |

- The testers' complaints show up in the data. Their sessions score worse than
  both the exhibition sessions and the simulated runs on every row.
- The simulated visitor reproduces the exhibition numbers roughly, so the
  harness is a fair proxy for the typical session, not for these three testers.
- Caveats: the testers' sessions are longer (about 10 turns against 4), three
  conversations are too few to generalize from, and the repeated-phrase counts
  grow with conversation length, so they are not compared across sources.

## What in the prompt produces this (hypotheses, not yet tested)

Read against `prompts/sparring.md` at `8266451`:

- "1–2 paragraphs" with a model that fills both gives a fixed skeleton:
  a long claim-and-counter paragraph, then a second paragraph starting
  "Because…" or "So…" that stacks one to three questions, often "is it X, or is
  it Y".
- "Shift between pointed questioning … and direct counter-argument" can be read
  as an instruction to alternate, which is the pattern the testers described.
- Stage 3 of `<core_mechanism>` has the model "draft a few candidate
  questions". With thinking off the model has no scratchpad, so "do stages 1–3
  silently" has nowhere to run, and the candidates can end up in the reply.
- "Vary how you pull" varies only how a reply *ends*, not its shape.
- "One question or challenge at a time" is one line against all of the above.

## Thinking arm: does giving the silent stages a scratchpad fix it?

Iteration 3 is iteration 2's prompt (`121d63e1670e`, unchanged) with extended
thinking on (`--thinking`, adaptive), so thinking is the only variable. Both are
graded by `claude-opus-5-5`.

| | iteration 2 | iteration 3 (thinking) |
|---|---|---|
| replies with 2+ `?` | 51% | 38% |
| replies with questions in a row | 26% | 15% |
| replies ending on a question | 64% | 43% |
| sentences of 25+ words | 28% | 23% |
| adjacent replies with the same shape | 35% | 41% |
| no-handed-over-conclusion | 45% | 47% |
| held-position | 84% | 78% |
| premature-closure | 89% | 80% |
| no-meta-commentary | 44% | 47% |
| voice-style | 12% | 19% |
| at most one question per turn (per transcript) | 11% | 22% |

Reading: questions moved the right way by about 13 points, which is inside the
noise (about 10 points on 72 replies) and short of the 20 points set in advance
as a win. Sentence length, tics and uniformity did not move; sameness of shape
got slightly worse. The guard items (held-position, premature-closure) fell
6 and 9 points, also within noise but in the direction the shorter-and-weaker
risk predicts. One run per arm, so the result is "no clear effect", not
"thinking does not help". It covers the shape metrics and the rubric only:
nobody has read the thinking-on replies against the thinking-off ones for how
they sound, so taste is untested, and the runs are 4 turns long (see Limits).

### A pitfall that hid the thinking

All 72 thinking texts in iteration 3 were empty, and the same happened in a
dojo session the author ran with thinking switched on and deliberately hard
questions. On this model
extended thinking is on by default but its text is **omitted** by default: the
response contains a `thinking` block with an empty string, and the thinking is
still billed. A probe on one real turn, same input, two settings:

| setting | thinking block | thinking text | output tokens |
|---|---|---|---|
| `adaptive` (default display) | present | 0 chars | 523 |
| `adaptive` + `display: "summarized"` | present | 629 chars | 758 |

With the default, the reply is 682 characters (about 170 tokens) out of 523
output tokens, so roughly 350 tokens were hidden thinking. Iteration 3 is
therefore a valid thinking run whose thinking text was not captured; empty text
cannot be read as "the model did not think". The eval runner and the dojo now
request `summarized`. The two probe replies differ, so the token gap is not
purely the thinking text.

## Edit A: at most one ask per reply (iteration 4)

Prompt change `cb92616` (sha256 `735b3c73ea39`): the "one question or challenge
at a time" line became "at most one ask per reply" (an ask is anything that
hands the turn back; no stacked asks, no "or is it…?" as a second question),
and stage 3 of `<core_mechanism>` no longer drafts "a few candidate
questions". Thinking off, the six original scenarios, Opus judge. "At most",
not "exactly", so a reply without an ask stays allowed.

| | iteration 2 | iteration 3 (thinking) | iteration 4 (edit A) |
|---|---|---|---|
| replies with 2+ `?` | 51% | 38% | 40% |
| replies with questions in a row | 26% | 15% | 14% |
| replies ending on a question | 64% | 43% | 65% |
| sentences of 25+ words | 28% | 23% | 28% |
| adjacent replies with the same shape | 35% | 41% | 28% |
| held-position | 84% | 78% | 81% |
| premature-closure | 89% | 80% | 83% |
| no-handed-over-conclusion | 45% | 47% | 47% |
| at most one question (per transcript) | 11% | 22% | 22% |
| length fits phone/wall (per transcript) | 33% | 38% | 50% |

Reading: replies with 2+ question marks fell about 11 points, as they did with
thinking, and neither reaches the 20 points set in advance as a win. The guard
items held within noise, and "at most" did not change the share of replies
that end on a question (64% → 65%), so the cap did not turn into "never ask".
Of the 29 replies in iteration 4 that still have 2+ question marks, 26 end on
a question and only 10 have two questions next to each other. The usual
pattern is a question in paragraph 1 ("But who decided what counts as
success?") followed by a "Because…" paragraph that ends in a second ask. The
rule is followed once per paragraph, not once per reply: the two-paragraph
skeleton, not the wording of the ask rule, produces the second ask. That is the
hypothesis edit B tests (shape and a "only the last paragraph may ask" clause).

## Edit B, stage 1 (iteration 5): did not clear the gate

Prompt `98ff37a` (sha256 `d2af6713b509`): edit A plus edit B (no alternation
by habit, 1-2 paragraphs as a cap with short one-idea sentences, a question
only in the last paragraph of a two-paragraph reply, a "vary the shape" block,
and short stakeholder voices). To keep spend down the run was staged: one rep
of `agentic-education`, `german-language` and the new `persona-request`
(24 calls, no judge), to be widened only on a clear signal.

Against iteration 4 on the two scenarios both runs share (24 replies against
8):

| | iteration 4 (edit A) | iteration 5 (A+B) |
|---|---|---|
| replies with 2+ `?` | 70% | 50% |
| replies with two paragraphs | 83% | 87% |
| `?` in a paragraph before the last | 41% | 37% |
| words per reply | 109 | 96 |

With 8 replies, a 20-point change is inside the noise, and the two-paragraph
rate did not move. Reading the 12 replies:

- The "only the last paragraph may ask" clause is mostly ignored. The model puts
  a rhetorical question or an imperative ask in paragraph 1 and another at the
  end.
- One paragraph works when it happens (`german-language` turn 1 is a single
  short reply with one ask), but nothing makes it the default.
- The stakeholder voice works but runs about 100 words as a quoted block,
  followed by an extra ask of Sparring's own ("Your move…").
- When the visitor demanded a verdict, turn 3 declined it well and then
  stacked two questions; turn 4 answered "underspecified, not wrong" and then
  added three more asks.
- The banned shorthand "move" appears in two persona replies.

Decision: stop at stage 1 (24 calls spent), do not widen. The rules were
followed locally while the two-paragraph skeleton stayed, which suggests
patching clauses into this prompt's structure may not be enough. Candidate
follow-ups: a revised B (one paragraph by default, rhetorical questions written
as statements, persona capped at two sentences), or an alternative prompt
designed from the requirements and compared with the current one.

## Blind comparison, thinking off vs on (iteration 2 vs 3, turn 1)

Twelve turn-1 pairs (same pinned opener, one reply from each run, arm hidden, A/B
randomized; `bin/blind_pairs.php`), rated by the author in skill-creator's
review viewer with a free-text note per pair. One rater who knows the design, so
this is a first read, not a result.

| preferred | pairs |
|---|---|
| iteration 3 (thinking on) | 5 |
| iteration 2 (thinking off) | 3 |
| neither | 4 |

Five against three on twelve pairs is no difference. The notes are the useful
part, and most of them are not about thinking:

- **Neither reply asks first.** In `wicked-problem` rep 1 both replies "outline
  completely what a wicked problem is"; the rater wanted a beat to ask what the
  learner already understands, which would also show their preconditions. A
  `meta-question` pair was rejected for the same reason, with a suggested start
  that is tentative and human ("I think I've heard that word before, maybe from
  martial arts…") followed by a question.
- **A paraphrase is wanted, where the spec forbids it.** For `feasibility-
  desirability` the rater wanted the reply to restate what the visitor said ("so
  your team said it can't be made, what are they not telling you…") to check it
  was understood before pushing. QR-02 (SE-04) says no response summarizes or
  recaps the visitor's turn, and the rubric fails it. For visitors writing in a
  second language, a short understanding-check may help more than it hurts,
  which is the case the rubric cannot see.
- **Blunter and more direct.** `adversarial-argue-out-of-stance`: "the best answer
  would be blunter", with a one-line example. Two other pairs were chosen for
  being "more precise and direct" and shorter.
- **Asks disguised as statements read as weak.** A reply that ended "So why does
  speed matter here." (an ask with no question mark) and an "Is X doing N, or Y
  doing M?" either/or were both criticised; the rater preferred a plain "Do you
  think … ?". The prompt's "vary how you pull" section is what produces the
  first form, so it is a design feature this note argues against.
- **Length cuts both ways.** Shorter was preferred in two pairs and "a bit longer
  but more legible" or "more holistic" in two others: legibility, not word
  count, is what the rater tracked. One German reply was marked down for a
  convoluted closing phrase.

Limits on top of the general ones: one rater, who is also the designer; turn 1
only (nothing about late-session behavior); the rubric and shape metrics scored
none of the above.

## Limits

- n = 3 testers; none of the numbers here validates the complaint, they only
  show that the data are consistent with it.
- Every simulated measure uses a `claude-sonnet-5` visitor that writes fluent,
  articulate text, unlike the testers, who wrote short and simple. That may
  understate how the replies read to them.
- All shape metrics are proxies for what the testers meant. A reply can pass
  every one and still read badly, or fail one and read well. No measure here
  says whether replies got *better*; that needs the blind human comparison
  described in `judge-comparison.md`.
- Single runs, 72 replies each, ± about 10 points of noise.
- Reply quality was never judged by a person. The thinking arm and every prompt
  edit were scored on shape metrics and the rubric only. `bin/blind_pairs.php`
  builds a blind sheet for that.
- Every simulated run is 4 turns; real sessions run up to 16. In the real
  sessions replies grow with turn position (turns 1-4: 88 words per reply and
  16% of sentences at 25+ words; turns 9-16: 130 words and 38%; 218 replies,
  only 9 of 47 sessions went past turn 8), so the growth in complexity that
  testers likely met is not visible in a 4-turn run. Turn-cap convergence
  (C-06), move repetition (QR-01) over a long session, tic accumulation and the
  share of turns on which thinking engages are all untested for the same
  reason.

## Reproduce

```bash
php bin/shape_sparring.php --db=data/store.db --origin=study \
    evals/sparring/sparring-workspace/iteration-2 evals/sparring/sparring-workspace/iteration-3
php bin/shape_sparring.php --db=data/store.db --origin=live
```
