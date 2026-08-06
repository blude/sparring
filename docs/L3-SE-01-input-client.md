# Element Design Concept: Input Client

**Element:** SE-01 Input client
**Element profile:** User-facing frontend
**Parent:** System Design Concept
**Status:** Draft

**Sections that apply.** All sections apply, with user interfaces and use cases as the core. Technical functions are thin by design: per AP-01, this element holds no generation, no moderation, and no session policy. Entities cover in-memory view state only, since nothing is stored on the device.

---

## 1. Goals

**G-01 — Reach a working input surface with nothing but a scan**
The element shall present a usable input surface immediately on opening, with no installation, registration, or instruction step.
*Success criteria (qualitative):* A visitor who has never seen the piece submits their first contribution without asking anyone how it works.
*Satisfies:* SG-01

**G-02 — Hold the visitor's place through interruption**
The element shall restore a session and its exchanges after a reload, a locked device, or a backgrounded tab.
*Success criteria (qualitative):* Reloading restores the full session with no loss of prior exchanges and no repeated consent step.
*Satisfies:* SG-03

**G-03 — Make waiting and failure legible**
The element shall show that a response is being produced, and shall state plainly when one cannot be.
*Success criteria (quantitative):* An in-progress state appears within 300 ms of submission.
*Satisfies:* SG-04
*Rationale:* Several seconds of unexplained stillness in a gallery reads as a broken piece, not a thinking one.

**G-04 — Make unsuitable input visible**
The element shall show that an input is invalid and should require the user to modify it's content before sending again.
*Rationale:* Participants should'd be allowed to edit their input to fix the problem.
*Note:* this is the authoritative behavior for unsuitable content — an earlier version of L2's AP-05 described the session ending instead; that reading is superseded (see L2 AP-05's own note on why).

---

## 2. Use cases

### UC-01 — Begin a session
*Actors:* UT-01, SE-03
*Prerequisites:* None. This is the entry point.
*Achieves:* G-01
*Realises:* SSc-01

- **ST-01-1** *(Function-call)* On load, the element calls TF-01 with the address contents. TF-01 finds no session identifier and requests a new session, returning the identifier and an empty session.
- **ST-01-2** *(Activity)* The element places the identifier in the address without adding a history entry.
- **ST-01-3** *(User-interaction)* UI-01 presents the consent decision: agreement to take part, plus independent retention and projection choices. The contribution field is present but not usable.
- **ST-01-4** *(User-interaction)* UT-01 records their three decisions on UI-01.
- **ST-01-5** *(Function-call)* The element calls TF-02 with the three decisions. TF-02 sends them to SE-03 and returns confirmation.
- **ST-01-6** *(Activity)* The element enables the contribution field. UC-02 becomes available.

**Alternative scenarios**

- **EX-01-1** *(extends ST-01-1)* Session creation fails. UI-01 states that the installation is not accepting sessions and offers a retry. *Outcome:* Terminate.
- **EX-01-2** *(extends ST-01-4)* UT-01 declines retention, projection, or both. The decision(s) are recorded as declined and the flow continues unchanged from ST-01-5 — these are real opt-outs, not preconditions. Nothing about the surface differs afterwards. *Outcome:* Alternative-success.
- **EX-01-3** *(extends ST-01-4)* UT-01 does not agree to take part. Unlike EX-01-2, the flow does not proceed to ST-01-5: the Confirm action stays disabled and the contribution field remains unusable. *Outcome:* Terminate.

### UC-02 — Contribute a turn
*Actors:* UT-01, SE-03
*Prerequisites:* UC-01 completed, or UC-03 completed.
*Achieves:* G-01, G-03
*Realises:* SSc-01

- **ST-02-1** *(User-interaction)* UT-01 enters a contribution in UI-01's input field. The remaining character allowance is shown as they type.
- **ST-02-2** *(User-interaction)* UT-01 submits.
- **ST-02-3** *(Activity)* The element disables the input field and shows the in-progress state.
- **ST-02-4** *(Function-call)* The element calls TF-03 with the session identifier and the contribution. TF-03 submits it and returns the completed exchange.
- **ST-02-5** *(Function-call)* The element calls TF-04 with the returned exchange, which appends it to the rendered history.
- **ST-02-6** *(Activity)* The element clears the in-progress state, empties and re-enables the input field, and scrolls the newest exchange into view.

**Alternative scenarios**

- **EX-02-1** *(extends ST-02-4)* SE-03 reports that the request limit has been reached. UI-01 states this and re-enables the field. The typed contribution is preserved. *Outcome:* Resume.
- **EX-02-2** *(extends ST-02-4)* SE-03 reports that the session has reached its turn limit. UI-01 presents the session as complete and leaves the field disabled. The history remains readable. *Outcome:* Terminate.
- **EX-02-3** *(extends ST-02-4)* SE-03 reports that no response could be produced. UI-01 states plainly that the installation cannot respond right now and re-enables the field with the contribution preserved. *Outcome:* Resume.
- **EX-02-4** *(extends ST-02-4)* The request does not complete within the element's own wait bound. The element stops waiting and behaves as in EX-02-3. *Outcome:* Resume.
- **EX-02-5** *(extends ST-02-4)* SE-03 reports the contribution as flagged (unsuitable). UI-01 states that the message can't be shown and re-enables the field with the contribution preserved, same shape as EX-02-1/EX-02-3. The session itself is unaffected. *Outcome:* Resume.

### UC-03 — Resume an interrupted session
*Actors:* UT-01, SE-03
*Prerequisites:* A session identifier is present in the address.
*Achieves:* G-02
*Realises:* SSc-01

- **ST-03-1** *(Function-call)* On load, the element calls TF-01 with the address contents. TF-01 finds an identifier and fetches the existing session from SE-03.
- **ST-03-2** *(Function-call)* The element calls TF-04 with the returned exchanges, which renders the history.
- **ST-03-3** *(Activity)* The element restores the surface to its correct state: input enabled if the session is open, presented as complete if it has reached its limit. The consent decision is not asked again.

**Alternative scenarios**

- **EX-03-1** *(extends ST-03-1)* The identifier is unknown to SE-03 or has expired. The element discards it and continues as UC-01 from ST-01-1. *Outcome:* Alternative-success.

---

## 3. User interfaces

### UI-01 — Session surface
*User type:* UT-01
*Realises:* UC-01, UC-02, UC-03

A single scrolling surface. The exchange history occupies the upper region, the input field is anchored below it. There is no navigation, no menu, and no second view.

**Visible data**

| Item | Type | Displayed | Source | Conditions |
|---|---|---|---|---|
| Consent notice | string | What happens to the session, what each of the three decisions means, and which one is required | Static | Until the decision is recorded |
| Exchange history | array | Visitor contributions and Sparring responses in order, visually distinguished by speaker | E-01.4 | Once at least one exchange exists |
| In-progress state | boolean | That a response is being produced | E-01.5 | While a submission is outstanding |
| Remaining turns | integer | How many contributions remain in the session | E-01.3 | Always, once the session is open |
| Character allowance | integer | Characters remaining for the current contribution | TF-04 | While the field holds content |
| Status message | string | Plain statement of a limit reached or a failure | E-01.6 | When set |

**Actions**

| Action | Represents | Use case | Conditions |
|---|---|---|---|
| Record consent decisions | Three checkboxes, none preselected — agreement to take part, retention, projection — plus one Confirm action, enabled only once the participation checkbox is checked | UC-01 | Before the session is open |
| Submit contribution | Button press, and keyboard submit on devices with a physical keyboard | UC-02 | Enabled only when the field holds content, the session is open, and no submission is outstanding |
| Retry | Button press | UC-01, UC-02 | Only alongside a failure status message |

**Input fields**

| Field | Type | Entered | Validation (UI level only) | Use case |
|---|---|---|---|---|
| Contribution | string | What the visitor wants to work on, or their reply to the Sparring partner | Required. Bounded length, enforced at the field and again by SE-03. | UC-02 |

**Navigation**

None. The element has one surface. The address carries the session identifier but is never used to move between views.

**Presentation notes.** The consent decision must be answerable without scrolling on a small phone, or it becomes an obstacle at the exact moment the visitor is deciding whether to engage at all. None of the three checkboxes is preselected: a preselected or visually dominant "accept" is a dark pattern and defeats the point of asking. This holds even for the participation checkbox, which is required to proceed — required is not the same as defaulted-on.

---

## 4. Technical functions

**TF-01 — Establish the session**
*Detail level:* Stepwise
*Achieves:* G-01, G-02

Determines whether a session already exists and produces one either way, so that the calling use case does not branch on it.

- **FS-01-1** *(Data-operation)* Read the session identifier from the address. If absent, continue at FS-01-3.
- **FS-01-2** *(Outbound-call)* Call TO-03 with the identifier. On success, return the session and its exchanges, and stop. *Call behaviour:* Synchronous, single attempt, 10 second timeout. A not-found or expired response is not an error and continues at FS-01-3. A timeout or transport failure surfaces to the caller as a failure.
- **FS-01-3** *(Outbound-call)* Call TO-01 to create a session. Return the identifier and an empty exchange list. *Call behaviour:* Synchronous, two attempts with a 1 second delay between them, 10 second timeout per attempt. Exhausted attempts surface to the caller as a failure.

**Alternative flows**

- **FA-01-1** *(extends FS-01-2)* The identifier is unknown or expired. Discard it and continue at FS-01-3. *Outcome:* Resume.

**TF-02 — Record the consent decision**
*Detail level:* Narrative
*Achieves:* G-01

Sends the visitor's three decisions — agreement to take part, retention, projection — to SE-03 via TO-01 and holds the returned confirmation in E-01. Synchronous, two attempts, 10 second timeout per attempt. Exhausted attempts leave the session unopened and surface a failure, because opening the input field without a recorded decision would retain content the visitor never agreed to. A declined-participation response is not a transport failure and is handled separately (EX-01-3): the field simply stays unusable.

**TF-03 — Submit a contribution**
*Detail level:* Stepwise
*Achieves:* G-01, G-03

- **FS-03-1** *(Data-operation)* Trim the contribution and reject it locally if it is empty or exceeds the bound, without calling out.
- **FS-03-2** *(Outbound-call)* Call TO-02 with the session identifier and the contribution. *Call behaviour:* Synchronous. No retry, because a retried submission risks producing a duplicate exchange and the visitor is watching. Wait bound set slightly above SE-03's own generation bound, which is TBC alongside SQR-01. On rate-limit, turn-limit, flagged-content, or generation-failure responses, return the reported condition to the caller rather than treating it as a fault. On timeout or transport failure, return a generation-failure condition.
- **FS-03-3** *(Data-operation)* Append the returned exchange to E-01 and decrement the remaining turn count.

**Alternative flows**

- **FA-03-1** *(extends FS-03-2)* Any reported condition is returned unchanged to the caller, which decides what UI-01 shows. Grouping is intentional: this function does not distinguish between them because it takes the same action for all. *Outcome:* Resume.

**TF-04 — Render the exchange history**
*Detail level:* Narrative

Produces the visible history from E-01. Visitor and Sparring contributions are visually distinguished. Content from SE-03 is inserted as text, never as markup, so that anything a visitor types cannot alter the surface. No goal relation: this is presentation logic serving use cases that already reference the goals.

---

## 5. Technical interfaces (inbound)

None. The element provides no interface to other elements. It is reached by a person opening a URL, which is not an inbound technical interface in the sense this section covers.

---

## 6. Technical interfaces (outbound)

**TO-01 — Open a session**
*Statement:* This interface creates a session and records the consent decision by calling the backend service (SE-03). Used for establishing a session before any contribution is possible.
*Input:* Three consent booleans — agreement to take part, retention, projection — required together on the decision call; all absent on the creation call.
*Output:* Session identifier, turn allowance, session state.
*Error cases:* Service unavailable. Malformed request. Declined to agree to take part — distinct from a malformed request, since the shape is valid but the value is a hard no.
*Authoritative spec:* SE-03 element design, TI-01.

**TO-02 — Submit a contribution**
*Statement:* This interface submits a visitor contribution and obtains the Sparring response by calling the backend service (SE-03). Used for every turn after the session is open.
*Input:* Session identifier, contribution text.
*Output:* The completed exchange, updated remaining turn count, session state.
*Error cases:* Request limit reached. Session turn limit reached. Contribution flagged as unsuitable — the visitor edits and resubmits; the session is unaffected. Generation failed. Session unknown or expired. Contribution rejected as malformed or over-length.
*Authoritative spec:* SE-03 element design, TI-02.

**TO-03 — Retrieve a session**
*Statement:* This interface fetches an existing session and its exchanges by calling the backend service (SE-03). Used for restoring state after an interruption.
*Input:* Session identifier.
*Output:* Session state, turn allowance and remaining count, ordered exchanges.
*Error cases:* Session unknown or expired. Service unavailable.
*Authoritative spec:* SE-03 element design, TI-03.

*No `Calls → PE-` relation applies. This element reaches no partner element directly, per AP-01.*

---

## 7. Entities

**E-01 — Session view state**
*Persistence:* In-memory. Discarded when the page unloads, and rebuilt from SE-03 on the next load. Nothing is written to device storage.
*Refines:* BE-01 (partially; the authoritative record is SE-03's E-01)

| ID | Attribute | Type | Required | Description |
|---|---|---|---|---|
| E-01.1 | sessionId | string | required | Identifier held in the address. Opaque to this element. |
| E-01.2 | sessionState | enum | required | `awaiting-decision`, `open`, `complete`, `failed` |
| E-01.3 | turnsRemaining | integer | required | Contributions still permitted. Supplied by SE-03, never computed here. |
| E-01.4 | exchanges | array | required | Ordered exchanges, each a visitor contribution and its Sparring response. May be empty. |
| E-01.5 | submissionOutstanding | boolean | required | Whether a submission is awaiting a response. Drives the in-progress state. |
| E-01.6 | statusMessage | string | optional | Plain statement of the current limit or failure. Cleared on the next successful submission. |

*The device holds no consent record. The decision is sent to SE-03 and its consequences are enforced there. Holding a local copy would create a second source of truth for the one thing that must not have one.*

---

## 8. Quality requirements

**QR-01 — The in-progress state appears immediately** *(Performance)*
*Applies to:* UI-01, TF-03
*Acceptance criteria:* The state is visible within 300 ms of submission and persists until the response or a failure arrives.
*Supports:* SQR-01

**QR-02 — Every failure produces a statement** *(Usability)*
*Applies to:* UI-01, TF-01, TF-03
*Acceptance criteria (qualitative):* No condition leaves the surface unresponsive or unexplained. Every path that stops shows what happened and whether the visitor can continue.
*Supports:* SQR-07

**QR-03 — Usable on a phone held in one hand** *(Usability)*
*Applies to:* UI-01
*Acceptance criteria (qualitative):* Input field and submit action reachable one-handed on a small phone with the software keyboard raised. The consent decision is answerable without scrolling.
*Supports:* SQR-03
*Element specific:* Yes

**QR-04 — Visitor content cannot alter the surface** *(Security)*
*Applies to:* TF-04
*Acceptance criteria:* All content returned from SE-03 is inserted as text. No path renders it as markup.
*Element specific:* Yes

**QR-05 — Works on current mobile browsers without a build step** *(Compatibility)*
*Applies to:* The element overall
*Acceptance criteria (qualitative):* Functions on current mobile Safari and Chrome. No transpilation, bundling, or framework runtime.
*Supports:* SQR-02
*Rationale:* SC-03. A build pipeline is one more thing that can be broken at 9am on opening day.

---

## 9. Constraints

**C-01 — No credential, no policy, no generation in this element** *(Technical)*
*Source:* AP-01.
*Applies to:* All technical functions and outbound interfaces.
*Acceptance criteria:* The element contains no provider credential and no limit, filter, or generation logic beyond field-level input bounds.
*Consequence:* Rules out any direct call to PE-01 and any client-side enforcement that SE-03 does not repeat.
*Implements:* SC-01

**C-02 — Session identity lives in the address, not in device storage** *(Technical)*
*Source:* AP-03.
*Applies to:* TF-01, E-01.
*Acceptance criteria:* The identifier is read from and written to the address only. No cookie, no local storage, no session storage.
*Consequence:* Rules out silent identity persistence across visits. A visitor who closes the tab has ended their session, which is the intended behaviour.
*Implements:* SC-05

**C-03 — Consent is decided before input is possible** *(Legal-regulatory)*
*Source:* SC-05.
*Applies to:* UC-01, UI-01.
*Acceptance criteria:* The contribution field cannot be submitted until a decision is recorded. What actually gates it is agreement to take part specifically — retention and projection are recorded at the same moment but, as real opt-outs, do not themselves block anything. None of the three options is preselected or visually favoured.
*Consequence:* Rules out deferring the decision until after the first contribution. Rules out treating retention or projection decline as equivalent to declining to take part.
*Implements:* SC-05

**C-04 — No build step** *(Technical)*
*Source:* SC-03.
*Applies to:* The element overall.
*Acceptance criteria:* The element ships as files served directly, with no compilation or bundling.
*Consequence:* Rules out any framework requiring a build. Deployment is a file copy.
*Implements:* SC-03
