# Judge comparison: iterations 1 and 2 re-graded

Written 2026-10-24. Reproduce with the commands at the end.

## What changed, and why

The original evaluation (iterations 1 and 2) used `claude-sonnet-5` for three
roles: it generated Sparring's replies, it played the simulated visitor, and it
was the judge that graded the replies. The judge therefore graded text written
by the same model. LLM judges tend to rate their own output more favorably
(self-preference bias), so those scores are likely too generous. This is a
limitation of the original evaluation, not only of its description.

Two changes were made, and the original results were left in place:

1. **A different judge.** The same transcripts were re-graded by
   `claude-opus-5-5`. Transcripts were not regenerated, so Sparring and the
   visitor are still `claude-sonnet-5`. The only thing that differs between the
   original and the new numbers is the judge.
2. **A fixed output format.** The original judge was never required to return one
   verdict per Sparring turn, although `rubric.md` grades every turn. Sonnet
   happened to return per-turn verdicts; Opus returned one per transcript on its
   first try, which is a different unit ("any turn fails") and cannot be compared
   with a per-turn rate. The grader now requires one finding per turn per item.

Because change 2 could itself move the numbers, a control was added: the same
transcripts were also re-graded by Sonnet under the new format. That separates
the effect of the format from the effect of the judge.

## Conditions

| | original | Sonnet re-run | Opus |
|---|---|---|---|
| judge model | claude-sonnet-5 | claude-sonnet-5 | claude-opus-5-5 |
| findings | per turn, not enforced | per turn, enforced | per turn, enforced |
| thinking | disabled | adaptive, effort low | adaptive, effort low |
| max tokens | 2048 | 8192 | 8192 |
| committed in | `iteration-1`, `iteration-2` | `iteration-N-judge-sonnet` | `iteration-N-judge-opus` |

Opus 5.5 does not accept `thinking: disabled`, so the re-runs use adaptive
thinking at low effort for both models. Transcripts: iteration 1 is prompt
`ba4267dab096` (commit `f90f7a1`, inferred after the fact), iteration 2 is
`121d63e1670e` (`8266451`). Six scenarios, three repetitions, four turns each.

## Results: per-turn pass rate

Each cell is passed / judged turn-findings. The rubric items are in
`rubric.md`. The mechanical checks (length, banned phrases, question count) are
plain code, identical across judges, and are not part of this comparison.

**Iteration 1**

| item | original Sonnet | Sonnet re-run | Opus |
|---|---|---|---|
| no-handed-over-conclusion | 44/52 (84%) | 55/70 (78%) | 40/72 (55%) |
| held-position | 53/60 (88%) | 63/72 (87%) | 58/72 (80%) |
| premature-closure | 17/18 (94%) | 17/19 (89%) | 50/56 (89%) |
| no-meta-commentary | 15/22 (68%) | 53/61 (86%) | 33/72 (45%) |
| domain-grounding | 22/22 (100%) | 29/29 (100%) | 34/41 (82%) |
| voice-style | 24/39 (61%) | 48/71 (67%) | 14/72 (19%) |

**Iteration 2**

| item | original Sonnet | Sonnet re-run | Opus |
|---|---|---|---|
| no-handed-over-conclusion | 47/57 (82%) | 59/70 (84%) | 33/72 (45%) |
| held-position | 52/61 (85%) | 62/71 (87%) | 61/72 (84%) |
| premature-closure | 21/27 (77%) | 14/20 (70%) | 52/58 (89%) |
| no-meta-commentary | 24/32 (75%) | 44/58 (75%) | 32/72 (44%) |
| domain-grounding | 22/23 (95%) | 31/31 (100%) | 28/37 (75%) |
| voice-style | 27/41 (65%) | 48/68 (70%) | 9/72 (12%) |

## What the numbers say

- **The format change is not the cause.** Original Sonnet and the Sonnet re-run
  agree within a few points on nearly every item. The one large gap is
  `no-meta-commentary` in iteration 1 (68% vs 86%), where the original graded
  only 22 turns against 61 in the re-run, so the original rate rests on far fewer
  findings. The differences between Sonnet and Opus are therefore the judge.
- **Opus is much stricter on four items:** no-handed-over-conclusion
  (roughly 30 to 40 points lower), no-meta-commentary (roughly 30 to 40 lower),
  voice-style (roughly 50 points lower), and domain-grounding (roughly 20
  lower). Opus never passes a transcript on these items that Sonnet fails; the
  disagreements run one way.
- **The two judges agree on the core behaviour.** `held-position` is 80 to 88%
  under every judge and iteration, and `premature-closure` is similar
  (70 to 94%). The central claim, that Sparring holds its position under
  pressure, does not depend on the judge.
- **The prompt change between iterations looks different by judge.** Under
  Sonnet, iteration 2 is flat or slightly better. Under Opus,
  no-handed-over-conclusion falls from 55% to 45%, voice-style from 19% to 12%
  and domain-grounding from 82% to 75%, while held-position rises from 80% to
  84%. These movements are about 10 points on 72 findings, and the findings come
  from only 18 transcripts, so they should be read as "no clear improvement", not
  as a measured decline.
- **Where Opus is right.** Two disagreements were read against the transcripts.
  `agentic-education` rep-1, turn 3 states the conclusion ("judgment didn't
  disappear, it moved ... So the fear isn't X. It's Y") before asking anything,
  which `rubric.md` marks as a fail; Sonnet passed it. `wicked-problem` rep-2,
  turn 4 opens "Good, that's a real move and it's correct" and then signposts with
  "Now push on your own claim though"; Opus failed it, Sonnet passed it.
  `voice-style` is the item where Opus may be too harsh: it failed a turn for
  opening "Okay, so start me somewhere concrete". Without human labels there is
  no way to say which judge is better calibrated overall.

## Limits

- Both judges are Claude models, and Sparring is a Claude model. Opus reduces,
  but does not remove, same-family bias.
- The simulated visitor is still `claude-sonnet-5`, as in the original run, so
  Sparring and the visitor share a model. Only the judge was changed.
- Only two disagreements were checked by hand. No set of human labels exists, so
  neither judge is validated against a person.
- Judge noise: three Opus gradings of one transcript agreed on five of six items;
  `domain-grounding` flipped once. That item is the least stable.
- `premature-closure` is defined at the transcript level in `rubric.md`; Sonnet
  returns it once per transcript, Opus once per turn, so its counts are not
  comparable across the two even in the table above.
- The turn-level findings are not independent: each iteration is 18 transcripts,
  and percentages on 72 findings have an uncertainty of roughly 10 points.
- Iteration 1's prompt version was reconstructed after the run (`inferred`).

## What this evaluation does and does not measure

The rubric measures **conformance to the design requirements**, not
pedagogical quality. Each item is traceable to a rule in the spec or the prompt:
no-meta-commentary to QR-02 and `prompts/sparring.md`, held-position to BQR-01
and AP-07, no-handed-over-conclusion to BG-01. A high pass rate shows that
Sparring's replies follow the rules the design set for them. It does not show
that following those rules produces better arguing or better learning.

Four limits follow from this.

1. **The rubric and the prompt share a source.** Both derive from the same
   design. If a rule is wrong, for example if some preamble helps a visitor
   settle in, the evaluation cannot detect it, because passing the rubric and
   obeying the rule are the same thing. This is circular by construction.
2. **A rubric can push the output the wrong way.** If the prompt is tuned until
   it passes the rubric, any rule that is stricter than good teaching requires
   makes the replies worse while the score rises. `voice-style` is the clearest
   candidate: under Opus it passes 12 to 19% of turns, and it failed a turn for
   opening "Okay, so start me somewhere concrete". Whether that opening is a
   defect or a useful acknowledgement is a pedagogical question the rubric
   assumes the answer to.
3. **No outcome is measured.** Nothing checks whether the visitor's argument
   improved, whether they kept going, or whether they would rate the exchange
   as useful. The simulated visitor is a persona instructed to resist for the
   first turns, so it can test whether Sparring stays firm, but it is not a
   learner. The rating form was filled in during the study sessions and by some
   exhibition visitors; its answers are not used by this evaluation, and not
   every visitor filled it in.
4. **No comparison against a plausible alternative.** The no-prompt baseline arm
   was only run through the mechanical checks, never judged, so the data cannot
   say that prompted replies are better than unprompted ones on any quality
   criterion, only that they differ.

What would test the concern directly: ask people, ideally with design or
teaching expertise, to compare pairs of replies blind, for example the same
turn with and without a preamble, and record which they prefer and why. A
small sample is enough to learn whether the rubric's verdicts agree with human
judgment on the contested items (`voice-style`, `no-meta-commentary`). For
outcomes, the study sessions and the exhibition visitors who filled in the
rating form are the natural evidence. Neither was part of this evaluation, so the results above
should be read as "the prompt behaves as designed", with the design itself left
open.

## Reproduce

```bash
# re-grade a copy of the transcripts (never grade iteration-N in place: it overwrites grading.json)
php bin/grade_sparring.php evals/sparring/sparring-workspace/iteration-2-judge-opus
php bin/grade_sparring.php evals/sparring/sparring-workspace/iteration-2-judge-sonnet --judge-model=claude-sonnet-5
php bin/summarize_sparring.php evals/sparring/sparring-workspace/iteration-2-judge-opus
```
