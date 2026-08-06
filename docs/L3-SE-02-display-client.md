# Element Design Concept: Display Client

**Element:** SE-02 Display client
**Element profile:** User-facing frontend (presentation only, no input)
**Parent:** System Design Concept
**Status:** Draft. **Layout treatment is TO BE REFINED (SC-06).** This document specifies a baseline complete enough to build against, and marks each open decision explicitly.

**Sections that apply.** Goals, user interfaces, technical functions, outbound interfaces, entities, quality requirements, and constraints. Use cases are thin and unusual here: the element has one user type who does not interact with it. UC-01 is written anyway, because the element's timing behaviour is only specifiable as a flow, and an absent use case section would read as an oversight rather than as a property of a non-interactive surface. Inbound technical interfaces do not apply.

---

## 1. Goals

**G-01 — Make turn-taking visible to people who are not participating**
The element shall present visitor contributions and Sparring responses as paired exchanges, so that an observer sees a challenge and what provoked it rather than an isolated reply.
*Success criteria (qualitative):* An observer reading one projected item can tell that the AI declined to simply resolve the request.
*Satisfies:* SG-02

**G-02 — Remain populated at all times**
The element shall never present an empty or near-empty surface while running, including before the first visitor of the day and between live sessions.
*Success criteria (qualitative):* The projection carries readable content continuously from start-up onward.
*Satisfies:* SG-06

**G-03 — Stay readable and stable without attention**
The element shall run for the duration of exhibition hours without reload, recover from backend interruption on its own, and update without visible redraw of unchanged content.
*Success criteria (qualitative):* No operator action is required at any point during opening hours. Unchanged items do not flicker or reflow on update.
*Satisfies:* SG-02, SG-06
*Rationale:* SQR-02. A projection that visibly redraws every few seconds reads as a status screen rather than as part of the work.

---

## 2. Use cases

### UC-01 — An observer reads the projection
*Actors:* UT-02, SE-03
*Prerequisites:* The element is running and has completed at least one successful retrieval.
*Achieves:* G-01, G-02, G-03
*Realises:* SSc-02

UT-02 performs no action in this use case. The steps describe the element's own cycle, which is what determines what an observer sees at any moment.

- **ST-01-1** *(Function-call)* On a fixed interval, the element calls TF-01, which retrieves current display material from SE-03.
- **ST-01-2** *(Function-call)* The element calls TF-02 with the retrieved material. TF-02 compares it against what is currently shown and determines what has changed.
- **ST-01-3** *(Function-call)* For each item that is new or changed, the element calls TF-03, which fits the item to the available space.
- **ST-01-4** *(Activity)* The element applies the changes, giving prominence to newly arrived material and receding older material. Unchanged items are left untouched.
- **ST-01-5** *(User-interaction)* UT-02 reads the surface. No input is available or expected.

**Alternative scenarios**

- **EX-01-1** *(extends ST-01-1)* Retrieval fails. The element retains what it is currently showing and retries on the next interval. Nothing on the surface indicates the failure, because an error message on a gallery wall is worse than slightly stale content. *Outcome:* Resume.
- **EX-01-2** *(extends ST-01-2)* The retrieved material is identical to what is shown. The element does nothing. *Outcome:* Terminate.
- **EX-01-3** *(extends ST-01-1)* Retrieval has failed continuously for a sustained period. The element continues showing its last material indefinitely rather than clearing. The threshold at which this becomes distinguishable from normal operation is TBC. *Outcome:* Resume.

---

## 3. User interfaces

### UI-01 — Projection surface
*User type:* UT-02
*Realises:* UC-01

The whole projected area. No controls, no cursor, no navigation, no scroll. The surface is a fixed viewport whose contents change over time.

**Baseline composition (agreed, build against this).** A vertical list of uniform items, most recent first. Every item has the same shape: a scenario line, then a visitor contribution, then the Sparring response. One exchange pair per session, so each session contributes exactly one item.

Two properties of this baseline are settled and should not be revisited without a reason:

- **The exchange pair is the unit.** A single turn shown alone conveys nothing about friction. The pair is the smallest thing that shows the mechanism.
- **Every item has the same shape.** A design that renders the newest session differently from the rest requires two layouts, two truncation rules, and a decision about what happens when the newest session is unusually long. Uniformity removes all three.

**Visible data**

| Item | Type | Displayed | Source | Conditions |
|---|---|---|---|---|
| Scenario line | string | What the session is about, giving an isolated exchange its context | E-01.2 | Per item, always |
| Visitor contribution | string | The visitor's turn | E-01.3 | Per item, always |
| Sparring response | string | The reply to that turn | E-01.4 | Per item, always |
| Recency position | integer | Implied by the item's position and treatment, not shown as a value | E-01.5 | Per item, always |

**Actions.** None. The element accepts no input.

**Input fields.** None.

**Navigation.** None.

### Open decisions (SC-06)

Each is deliberately deferred until real pilot transcripts can be seen on HE-02 at exhibition viewing distance. None of them changes the interface SE-03 provides, so they can be resolved after the rest of the system works.

**TBC-01 — Treatment of prominence and recency.** Three candidates were considered and none is yet chosen:

- *Size gradient.* Newest item largest, older items progressively smaller and dimmer. Conveys accumulation well. Weakness: older items become texture rather than content, so most of the surface is not actually readable.
- *Rotation.* One or two items at full size, cycling on a timer, with an indicator of how many others exist. Every item gets a turn at being readable. Weakness: loses the sense of accumulation, and requires a live-session priority rule so that a visitor who has just contributed sees their own exchange rather than waiting for the cycle.
- *Hybrid.* A large primary region plus a thin strip of recent items. Retains both properties at the cost of more layout work.

**TBC-02 — Whether a live session interrupts.** Only applicable if TBC-01 resolves to rotation or hybrid. If a visitor's exchange does not appear promptly on the wall, the connection between typing and projection is lost, and that connection is much of what makes the piece an installation rather than a screen. Under the size-gradient baseline this is automatic and needs no rule.

**TBC-03 — Dwell time, if any.** A fixed interval will feel wrong in both directions: too long for a short exchange, too short for a dense one. Scaling to content length is the obvious alternative. Not decidable without real transcripts.

**TBC-04 — Which exchange pair per session.** SE-03 currently supplies the most recent, on the reasoning that friction has usually built by then. The most demonstrative pair may be the second or third. To be checked against pilot material. This is a change to SE-03's TF-04 if revised, not to this element.

**TBC-05 — Item count and truncation.** How many items fit, and how a long contribution is trimmed. Begin with a fixed item count plus a character bound, and only measure rendered height if that proves inadequate. See TF-03.

**TBC-06 — Whether pilot-origin items are visually distinguishable.** They carry an origin value and could be marked. Leaving them unmarked keeps the surface uniform; marking them is more honest to an observer who assumes everything shown is live. Not urgent, but a decision rather than an oversight.

---

## 4. Technical functions

**TF-01 — Retrieve display material**
*Detail level:* Stepwise
*Achieves:* G-02, G-03

- **FS-01-1** *(Outbound-call)* Call TO-01 for current display material. *Call behaviour:* Synchronous, no retry within a cycle, timeout shorter than the polling interval so that requests cannot overlap. A failure is not escalated: the function returns the previous material unchanged. Consecutive failures are counted for TBC-03 but trigger no action. Polling interval TBC, expected in the 2 to 5 second range.
- **FS-01-2** *(Data-operation)* Validate that the response has the expected shape. Discard it entirely if not, rather than rendering a partial surface.
- **FS-01-3** *(Data-operation)* Return the material for reconciliation.

**Alternative flows**

- **FA-01-1** *(extends FS-01-1)* Timeout, transport failure, or an unexpected status. Return the currently held material, increment the failure count, and take no other action. Grouping is intentional: the element's response is the same for every failure mode. *Outcome:* Resume.

**TF-02 — Reconcile against what is displayed**
*Detail level:* Stepwise
*Achieves:* G-03

Determines the minimum change needed, so that unchanged items are not redrawn. This function is the whole reason the projection does not flicker.

- **FS-02-1** *(Entity-access)* Read the currently displayed items from E-01.
- **FS-02-2** *(Data-operation)* Match retrieved items against displayed items by session identifier (E-01.1).
- **FS-02-3** *(Data-operation)* Produce three sets: items to add, items whose content has changed, and items to remove. An item present in both with identical content appears in none of them.
- **FS-02-4** *(Entity-access)* Write the new set to E-01 once the changes have been applied.

**TF-03 — Fit an item to the available space**
*Detail level:* Narrative
*Achieves:* G-01

Trims an item's contribution and response so that the item occupies its allotted space without overflow, preserving enough of both halves that the exchange still reads as an exchange. Trimming only one side would leave a challenge with no visible provocation, which defeats G-01. The initial approach is a character bound per side; rendered-height measurement is the fallback if that proves inadequate (TBC-05).

**TF-04 — Render the surface**
*Detail level:* Narrative

Applies the reconciled changes to UI-01 with transitions on entry, recession, and exit. All content from SE-03 is inserted as text, never as markup. No goal relation: this is presentation logic serving a use case that already references the goals.

---

## 5. Technical interfaces (inbound)

None. No element calls this one. It is a leaf that only reads.

---

## 6. Technical interfaces (outbound)

**TO-01 — Retrieve display material**
*Statement:* This interface obtains the set of sessions currently eligible for projection by calling the backend service (SE-03). Used on every polling cycle.
*Input:* None. The element requests current material and does not parameterise the request, so that item selection remains a backend decision and this element carries no policy.
*Output:* An ordered list of items, each carrying a session identifier, a scenario statement, one visitor contribution, one Sparring response, an origin value, and a recency marker.
*Error cases:* Service unavailable. Malformed or unexpected response shape.
*Authoritative spec:* SE-03 element design, TI-04.

*No `Calls → PE-` relation applies. This element reaches no partner element.*

---

## 7. Entities

**E-01 — Displayed material**
*Persistence:* In-memory. Held only to support reconciliation. Lost on reload and rebuilt on the next cycle, which is acceptable because a reload costs one polling interval.

| ID | Attribute | Type | Required | Description |
|---|---|---|---|---|
| E-01.1 | sessionId | string | required | Identity for matching in TF-02. Opaque to this element. |
| E-01.2 | scenario | string | required | The context line shown above the exchange. |
| E-01.3 | visitorContribution | string | required | The visitor's turn, as supplied. |
| E-01.4 | sparringResponse | string | required | The reply to that turn, as supplied. |
| E-01.5 | recencyRank | integer | required | Position in the ordering supplied by SE-03. Not computed here. |
| E-01.6 | origin | enum | required | `pilot` or `live`. Not currently rendered. Retained for TBC-06. |

*The element derives no ordering, performs no selection, and stores nothing beyond the current cycle. Every decision about what appears is made by SE-03.*

---

## 8. Quality requirements

**QR-01 — Readable at exhibition viewing distance** *(Usability)*
*Applies to:* UI-01
*Acceptance criteria (qualitative):* A complete exchange is readable by a standing observer at the room's typical viewing distance, verified on HE-02 in the exhibition space before opening. Not verified in a browser window.
*Supports:* SQR-03

**QR-02 — Unchanged content does not redraw** *(Usability)*
*Applies to:* TF-02, TF-04
*Acceptance criteria:* An update that adds one item leaves every other item visually undisturbed. No full-surface repaint occurs on a polling cycle.
*Supports:* SQR-03

**QR-03 — Runs unattended for the full opening period** *(Availability)*
*Applies to:* The element overall
*Acceptance criteria (qualitative):* Runs for the duration of exhibition hours without reload, memory growth that degrades rendering, or accumulated state that requires clearing.
*Supports:* SQR-02

**QR-04 — Backend interruption is invisible on the surface** *(Availability)*
*Applies to:* TF-01
*Acceptance criteria:* A failed retrieval leaves the surface unchanged and produces no visible error. Recovery requires no reload.
*Supports:* SQR-02, SQR-07
*Rationale:* This deliberately departs from SQR-07's general rule that failures should be visible. SQR-07 protects the participant, who needs to know why nothing is happening. An observer needs no such signal, and an error message projected on a gallery wall is a worse outcome than briefly stale content.

**QR-05 — Visitor content cannot alter the surface** *(Security)*
*Applies to:* TF-04
*Acceptance criteria:* All content from SE-03 is inserted as text. No path renders it as markup.
*Element specific:* Yes
*Rationale:* This surface is public and unattended, so the consequence of an injection is a projected wall rather than one person's phone.

**QR-06 — Fixed viewport, no scroll, no overflow** *(Compatibility)*
*Applies to:* UI-01, TF-03
*Acceptance criteria:* Content never extends beyond the projected area at the target resolution. No scrollbar appears under any content length.
*Element specific:* Yes

---

## 9. Constraints

**C-01 — Layout treatment is an open decision** *(Organisational)*
*Source:* SC-06.
*Applies to:* UI-01, TF-03, TF-04.
*Acceptance criteria:* The baseline in section 3 is implemented first. TBC-01 through TBC-06 are resolved against real pilot transcripts on HE-02 before opening.
*Consequence:* Rules out encoding a specific prominence treatment into TO-01's request or into SE-03's response shape. The interface must serve any of the three candidates without change.
*Implements:* SC-06

**C-02 — This element holds no selection or ordering policy** *(Technical)*
*Source:* AP-01.
*Applies to:* TF-01, TO-01, E-01.
*Acceptance criteria:* The element renders what SE-03 returns, in the order returned. It performs no filtering, no sorting, and no eligibility assessment.
*Consequence:* Rules out client-side filtering of pilot material or of items the element judges unsuitable. Changing what appears is a backend change.
*Implements:* SC-01

**C-03 — Single fixed target resolution** *(Technical)*
*Source:* SC-03.
*Applies to:* UI-01.
*Acceptance criteria:* Renders correctly at the projection's native resolution. No responsive behaviour below or above it.
*Consequence:* Rules out responsive layout work. Requires the projection resolution to be confirmed before UI-01 is finalised, which is a dependency on HE-02 procurement being settled.
*Implements:* SC-03

**C-04 — No build step** *(Technical)*
*Source:* SC-03.
*Applies to:* The element overall.
*Acceptance criteria:* Ships as files served directly, with no compilation or bundling.
*Consequence:* Rules out any framework requiring a build.
*Implements:* SC-03
