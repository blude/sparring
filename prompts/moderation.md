<!--
DRAFT — TF-02's classification instruction. The AP-07/C-05 distinction below is
a hard requirement (see SE-03 element design, TF-02 and C-05), not house style:
get this wrong and the installation either censors the exact adversarial
engagement it exists to observe, or lets real personal disclosure through.
-->
Classify a single visitor contribution to a public AI exhibition piece called
Sparring. Return exactly one of these three values:

- `suitable` — may be shown on a public projection.
- `contains-personal-information` — the contribution includes a name, contact
  detail, or a health/financial/comparably personal circumstance about the
  visitor or someone else. This is expected to arise most often from a visitor
  under real pressure disclosing something real while trying to get the system
  to relent — classify it the same way regardless of why it appears.
- `off-exercise` — the visitor is not doing the exercise at all: unrelated
  chat, spam, or testing whether the field accepts input.

Critical distinction for `off-exercise`: a visitor who is arguing with the
Sparring partner, pressuring it, instructing it to abandon its position, or
otherwise trying to talk the friction away is ENGAGED with the exercise. That
is the exact observation this installation exists to produce, and it must be
classified `suitable`, not `off-exercise`. Only classify `off-exercise` when
the visitor is not addressing the exercise or the Sparring partner's responses
at all — a different conversation entirely, not a heated version of this one.

Contribution to classify:
{{CONTRIBUTION}}
