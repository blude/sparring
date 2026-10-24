# Resume notes: reply-shape work and the prompt redesign

Written 2026-10-24, end of day. Read this first when picking the work back up.
The findings behind it are in `reply-shape-findings.md`; the judge comparison for
the thesis is in `judge-comparison.md`.

## Where things are

| | |
|---|---|
| `develop` | Opus judge (`bin/grade_sparring.php`), the `--thinking` eval flag, `judge-comparison.md` and the re-graded iterations 1 and 2. This is the thesis record. Untouched by everything below. |
| `feat/prompt-reply-shape` (current, not pushed, about 19 commits ahead) | Everything else: reply-shape metrics, `persona-request` scenario, blind-pair tool, prompt edits A and B, spec changes, iterations 3 to 6, the redesign brief and the Fable-drafted candidate. |
| `feat/dojo-thinking` (not merged) | Dojo debug view for model thinking, `SPARRING_THINKING=1`, ADR 0020. Includes the `display: "summarized"` fix. Not tried in a browser yet. |
| `feat/promptfoo-eval-migration` (separate worktree, not merged) | An older promptfoo harness. Not used here. |

The thesis rule from earlier: don't change the prompt, rubric, scenarios or grader
on `develop` before the thesis is finished. `prompts/sparring.md` on the working
branch already carries edits A and B; `develop` still has the original.

## What the work established (details in the findings doc)

1. **Judge.** Original scores used a Sonnet judge grading Sonnet's replies; Opus is
   much stricter on four items. Both are kept; `judge-comparison.md` explains it.
2. **Tester complaints reproduce in real data.** Stacked questions, long sentences,
   uniform shape; worst in the three study sessions. Shape metrics are in
   `bin/shape_sparring.php`.
3. **Real replies grow with turn position** (88 to 130 words per reply, 16% to
   38% sentences of 25+ words from turns 1-4 to 9-16). All simulated runs are 4
   turns, so this is not testable yet.
4. **Thinking on vs off:** no clear difference, in shape metrics, rubric or a
   12-pair blind read. The API omits thinking text by default
   (`display: "summarized"` needed), so iteration 3 ran thinking with empty text.
5. **Patching clauses (edits A, B) moved little.** A: 2+ `?` from 51% to 40%.
   B stage 1 (iteration 5): clause "only the last paragraph may ask" mostly
   ignored; stopped at the gate.
6. **Blind read (author, 12 turn-1 pairs):** wanted find-out-before-explaining, a
   short paraphrase check for ambiguous or second-language messages, plain direct
   asks. The check conflicts with QR-02 in the spec.
7. **Redesign from scratch (Fable, iteration 6 stage 1):** two-paragraph skeleton
   gone (0% against 83%), 2+ `?` down, 56 words per reply on two scenarios. Not
   judged. Flags: banned words slipped, persona reply over the paragraph cap, a
   leaned position under verdict pressure.

## Next steps, in order

1. **Stage 2 of the redesign A/B** (about 40 calls). Same prompt file:
   ```
   set -a; . ./.env; set +a      # key lives in .env (never print it)
   php bin/eval_sparring.php --iteration=6 --reps=1 \
       --prompt=prompts/candidates/redesign-1.md \
       --scenario=wicked-problem,feasibility-desirability,adversarial-argue-out-of-stance,meta-question
   php bin/grade_sparring.php evals/sparring/sparring-workspace/iteration-6   # Opus judge
   ```
   Then compare against `iteration-2-judge-opus` and `iteration-4` with
   `php bin/shape_sparring.php <dirs>` and the judged items (script snippet in the
   findings doc style: per-turn pass rates). Guard items: held-position and
   no-handed-over-conclusion must not drop more than about 10 points.
2. **Blind pairs against the redesign** (cheap taste check):
   `php bin/blind_pairs.php evals/sparring/sparring-workspace/iteration-4 evals/sparring/sparring-workspace/iteration-6 --seed=3`
   then run skill-creator's viewer on the `viewer/` folder it writes
   (`python3 <skill-creator plugin dir>/eval-viewer/generate_review.py <out>/viewer`,
   port 3117, answers saved to `feedback.json`; the key stays unopened until rated).
3. **Decide what to do with the candidate's flags** before any widening: bring
   banned-word wording back to an explicit list, add a hard "two paragraphs at
   most" line, reconsider the persona verdict lean. Make it `redesign-2.md`, not an
   edit of `redesign-1.md`, so iteration 6 keeps its provenance.
4. **Long-session slice** (about 64 calls per prompt arm): one or two scenarios at
   `--turns=16`, 1 rep. First check the simulated visitor stays coherent for 16
   turns (its directive only covers the first three).
5. **Confirmatory 3-rep run** for the winner only, after the stages above are
   positive. Full iteration is about 160 calls.
6. **If the redesign wins:** SE-04 needs a rewrite (C-04 tag list, QR-02 exception,
   convergence rule, name), plus `prompts/CHANGELOG.md` and the spec HTML rebuild
   (`bin/build_spec.sh`). Nothing in the spec is changed for it yet.

## Open decisions (yours)

- **Name.** Fable's draft says "You are Sparring"; the current prompt says Popov.
- **Turn-cap convergence.** Spec C-06 says "approaching 16"; the draft converges
  past the halfway point. Which do you want?
- **QR-02.** Keep "never restate the visitor's turn", or allow a one-line
  understanding check for second-language messages (as the blind read suggested).
- **"Many replies have no ask at all"** in the draft: acceptable, or too loose?
- **What goes into the thesis** and whether the redesign A/B is in scope for the
  deadline. The judge-comparison correction is the must-have; the rest is optional.
- **Merging.** Branch plan for `feat/prompt-reply-shape` and `feat/dojo-thinking`
  after the thesis; nothing has been pushed.
- **Rubric validity.** A judged item for "reads like a person" was planned and not
  built. The rubric still rewards conformance to the spec, not quality.

## Practical notes

- API key: `.env` in the repo root. Source it in the same command as the run.
  Background runs can't see an exported key from an earlier command.
- `bin/eval_sparring.php --prompt=<file>` swaps only the prompt text (reflection;
  the client is a `final` class). The transcript stamps hash, commit state and
  file path. `uncommittedChanges: true` just means the file wasn't committed then.
- `blind_pairs.php` pairs turn 1 only (the one place both arms answer the same
  message). Re-running with the same seed reproduces the same sample and order.
- Fable 5.1: thinking always on, no `thinking: disabled`; the brief draft call cost
  about $0.65. Access works on this account.
- Costs so far, rough: iterations 3 and 4 about 160 calls each, iterations 5 and 6
  about 24 each, plus two re-grades and a few probes.
- A key mistake to avoid: don't print `key.json` or the A/B mapping before the
  ratings are in. It happened once during the first sheet (fixed by reseeding).
