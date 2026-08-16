# Element Design Concept: Backend Service

**Element:** SE-03 Backend service
**Element profile:** Backend service / API
**Parent:** System Design Concept
**Status:** Draft

**Sections that apply.** Goals, use cases, both technical interface sections, technical functions, entities, quality requirements, and constraints. User interfaces do not apply: this element has no human-facing surface. Its only human actor is UT-03, who reaches it through the operator interface (TI-05) rather than through a UI.

**A note on the datastore.** Per AP-04, storage is held inside this element rather than identified as a separate software element at L2. Its entities are therefore specified here, in section 7.

---

## 1. Goals

**G-01 — Produce Sparring responses and hold every rule that governs them**
The element shall be the sole location of generation, moderation, rate limiting, and session policy, so that no rule is editable from a device the project does not control.
*Success criteria (qualitative):* No client contains a provider credential or any limit the element does not independently enforce.
*Satisfies:* SG-01, SG-04

**G-02 — Keep a session usable across interruption**
The element shall resolve a session identifier to its full state and history for as long as the session is live.
*Success criteria (qualitative):* A session fetched after an interruption returns identical content to what its participant last saw.
*Satisfies:* SG-03

**G-03 — Persist every interaction with its origin fixed at creation**
The element shall record sessions and exchanges tagged pilot or live at the moment of creation, with no later assignment or correction.
*Success criteria (qualitative):* Exhibition material is extractable by filtering one stored value.
*Satisfies:* SG-05

**G-04 — Decide what may be stored, and let the visitor's own choice decide what may be projected**
The element shall assess each contribution for suitability before it is stored, rejecting an unsuitable one so the visitor can edit and resubmit rather than silently hiding it after the fact. Whether a *stored* exchange is projected is then a separate question, answered by the visitor's own projection consent, not by re-running suitability at display time.
*Success criteria (qualitative):* Nothing unsuitable is ever stored. A session that declined projection consent never appears on the wall regardless of content; a session that granted it does, for as long as its content keeps passing the write-time check.
*Satisfies:* SG-07

**G-05 — Keep the display surface populated**
The element shall supply display material at all times, falling back to pilot-origin sessions when insufficient live material is eligible.
*Success criteria (qualitative):* The display feed is never empty while the element is running and pilot material exists.
*Satisfies:* SG-06

**G-06 — Absorb load and misuse without failing**
The element shall reject excess requests, over-length contributions, and over-long sessions as expected conditions, and shall continue serving other visitors when it does.
*Success criteria (qualitative):* A rejected request affects only its own session.
*Satisfies:* SG-04

---

## 2. Use cases

Use cases at this level are thin, because most of this element's behaviour is reached through technical interfaces rather than as user-facing functionality. The one exception is the operator path, which is a genuine use case with a human actor.

### UC-01 — The operator seeds pilot material
*Actors:* UT-03
*Prerequisites:* Pilot transcripts exist in the expected structured form.
*Achieves:* G-03, G-05
*Realises:* SSc-03

- **ST-01-1** *(Activity)* UT-03 places a transcript file where the element can read it and invokes the import path (TI-05).
- **ST-01-2** *(Function-call)* The element calls TF-06 with the file contents. TF-06 validates each transcript, creates its session and exchanges with origin fixed as pilot, and returns a per-transcript outcome.
- **ST-01-3** *(Activity)* The element reports how many transcripts were imported and names any that were rejected.

**Alternative scenarios**

- **EX-01-1** *(extends ST-01-2)* One or more transcripts are malformed. Those are rejected and named; the remainder are imported. The import does not abort. *Outcome:* Alternative-success.

**Note.** This path performs no generation and never calls PE-01. The Sparring responses already exist in the transcripts, and regenerating them would produce material that no pilot session actually contained.

---

## 3. User interfaces

Not applicable. This element presents no user interface. UT-03 reaches it through TI-05, which is a technical interface invoked directly on the host rather than a surface.

---

## 4. Technical functions

**TF-01 — Process a visitor turn**
*Detail level:* Stepwise
*Achieves:* G-01, G-04, G-06

The element's central function. Everything that happens between a visitor pressing submit and a response returning is here, which is what keeps retry and error handling in one place rather than scattered across interfaces.

- **FS-01-1** *(Function-call)* Call TF-03 with the request origin. If it reports the limit reached, return that condition and stop.
- **FS-01-2** *(Entity-access)* Read the session from E-01 by its identifier (E-01.1). If absent or expired, return a session-unknown condition and stop.
- **FS-01-3** *(Data-operation)* Compare E-01.8 (turnCount) against the session turn allowance. If the allowance is exhausted, return a turn-limit condition and stop.
- **FS-01-4** *(Data-operation)* Trim the contribution and check it against the length bound. If it is empty or over-length, return a rejected condition and stop. No provider call has been made at this point, which matters because this is the cheapest rejection path and the one most likely to fire.
- **FS-01-5** *(Function-call)* Call TF-02 with the contribution. TF-02 returns a suitability outcome.
- **FS-01-6** *(Data-operation)* If TF-02 reported the contribution unsuitable, return a content-flagged condition and stop. Nothing is written to E-01 or E-02, and no provider call for a response is made — this is the same cheap-rejection reasoning as FS-01-4, just one check later. The session itself is not altered; only this turn is rejected.
- **FS-01-7** *(Entity-access)* Read the session's prior exchanges from E-02 (E-02.3, E-02.4) in order, to supply conversational context.
- **FS-01-8** *(Outbound-call)* Call TO-01 with the Sparring system prompt, the prior exchanges, and the new contribution. Expect a response text. *Call behaviour:* Synchronous. Timeout TBC, to be set from measured response times (SQR-01). Two attempts, with a short delay between them, on transport failure, timeout, or a provider 5xx. **No retry on a provider rate-limit or quota response**, because retrying a rate limit deepens it; return a generation-failed condition immediately. **No retry on a content-policy refusal from the provider**, which is a determinate outcome rather than a transient fault. On exhausted attempts, return a generation-failed condition. Nothing is written to E-02 on any failure path, so a failed turn leaves no partial exchange in the record.
- **FS-01-9** *(Entity-access)* Write a new exchange to E-02 carrying the contribution (E-02.3), the response (E-02.4), the session reference (E-02.2), and its position (E-02.5).
- **FS-01-10** *(Entity-access)* Increment E-01.8 (turnCount) and update E-01.9 (lastActiveAt).
- **FS-01-11** *(Function-call)* If this was the session's first exchange, call TF-05 to derive the scenario statement and write it to E-01.5.
- **FS-01-12** *(Data-operation)* Return the response, the remaining turn allowance, and the session state.

**Alternative flows**

- **FA-01-1** *(extends FS-01-8)* Provider rate limit, quota exhaustion, timeout, transport failure after retries, or content-policy refusal. All return a generation-failed condition to the caller and write nothing. Grouping is deliberate: the calling client takes the same action for every one of these, and distinguishing them would produce a taxonomy nobody acts on. The distinction is preserved in logs rather than in the response. *Outcome:* Terminate.
- **FA-01-2** *(extends FS-01-6)* The contribution is unsuitable. Unlike FA-01-1, this is not terminal — the caller (SE-01) tells the visitor to edit and resubmit, and the input field re-enables with the text preserved. *Outcome:* Resume.

**TF-02 — Assess a contribution for projection suitability**
*Detail level:* Stepwise
*Achieves:* G-04

Determines whether a contribution may be stored and answered at all — and, because only stored content can ever be projected (G-04), this is also what keeps unsuitable material off the public surface. It determines whether *this turn* proceeds, not whether the visitor's session does: on an unsuitable result the visitor edits and resubmits, they are not cut off.

- **FS-02-1** *(Data-operation)* Check the contribution against a maintained term list covering profanity. A match returns unsuitable without a provider call.
- **FS-02-2** *(Outbound-call)* Call TO-02 with the contribution and a classification instruction. Expect one of: suitable, contains-personal-information, targets-real-person. *Call behaviour:* Synchronous, short timeout, single attempt, no retry. **On any failure, return unsuitable.** Failing closed is correct here: the cost of not projecting a suitable contribution is that one item is missing from a wall, and the cost of projecting an unsuitable one is unrecoverable once it is six feet tall in a public room.
- **FS-02-3** *(Data-operation)* Map the classification to an outcome and return it.

**The distinction this function must get right (AP-07).** *Targets-real-person* means the harmful content — hate speech, harassment, threats, sexual content — is aimed at or about a real, identifiable person or group. It does **not** mean a visitor who is hostile toward, arguing with, pressuring, or instructing the Sparring partner to abandon its position, however extreme the tone — the Sparring partner is software, not a person, and that visitor is engaged with the exercise; their attempt is the observation the installation exists to produce. The classification instruction must state this distinction explicitly rather than leaving it to the model's default reading of "unsafe language", because hostility-at-the-AI and harm-at-a-person can otherwise look similar and only one should be withheld.

**A second distinction the instruction must also get right, found in testing.** Stating the AP-07 distinction was not enough on its own: without also stating what the exercise's subject matter actually *is*, the classifier had nothing to check arguing-the-exercise against, which produced misclassifications in the other direction. Both the topic itself and the target-based carve-out need to be explicit in the instruction — omitting either produces a failure mode this requirement exists to prevent, from a different direction.

*Contains-personal-information* covers names, contact details, and health, financial, or comparably personal circumstances. This case is expected to arise most often in exactly the situation AP-07 protects: a visitor under real pressure disclosing something real while trying to get the system to relent. That turn is rejected and not stored; the visitor edits it and continues — the session is not ended over it.

**TF-03 — Enforce request limits**
*Detail level:* Narrative
*Achieves:* G-06

Reads the current window for the request origin from E-03, increments it, and reports whether the allowance is exceeded. Windows are keyed on a hashed origin (E-03.1), so no raw address is stored. Expired windows are reset in place rather than swept by a separate process, which avoids a scheduled job on an unattended host. Threshold and window length are TBC, to be set below the provider's own limits per SC-04. No goal relation beyond G-06: this is a guard, not business logic.

**TF-04 — Assemble display material**
*Detail level:* Stepwise
*Achieves:* G-05, G-04

Produces the ordered items the projection renders. Every decision about what appears on the wall is made here, so that SE-02 carries no policy (C-02 of that element).

- **FS-04-1** *(Entity-access)* Read live-origin sessions from E-01 where E-01.6 (displayable) is true and at least one exchange exists, ordered by E-01.9 (lastActiveAt) descending, up to the item limit.
- **FS-04-2** *(Data-operation)* If fewer items were found than the limit, continue at FS-04-3. Otherwise continue at FS-04-4.
- **FS-04-3** *(Entity-access)* Read pilot-origin sessions from E-01 under the same eligibility conditions, ordered by E-01.9 descending, and append until the limit is reached.
- **FS-04-4** *(Entity-access)* For each selected session, read its most recent exchange from E-02 (E-02.3, E-02.4). Which exchange to select is an open decision (SE-02 TBC-04); it is made here and changing it does not affect SE-02.
- **FS-04-5** *(Data-operation)* Build each item from the session's scenario (E-01.5), the exchange pair, the origin (E-01.4), and a recency position. Return the ordered list.

**TF-05 — Derive a scenario statement**
*Detail level:* Narrative
*Achieves:* G-05

Produces the short context line shown above an exchange, from the session's first visitor contribution. The initial approach is to trim that contribution to a bounded length, which costs nothing. If opening contributions prove to read badly as context lines when checked against pilot transcripts, the fallback is a single generation call via TO-02, with the result written once to E-01.5 and never recomputed. Computing it per render would multiply provider cost by the polling rate, which is the specific mistake this note exists to prevent.

**TF-06 — Import pilot transcripts**
*Detail level:* Stepwise
*Achieves:* G-03, G-05

- **FS-06-1** *(Data-operation)* Parse the supplied file and validate each transcript's structure. Collect malformed ones for reporting rather than aborting.
- **FS-06-2** *(Entity-access)* For each valid transcript, write a session to E-01 with E-01.4 (origin) set to `pilot`, E-01.6 (displayable) set to true, and E-01.7 (consentGranted) set to true. E-01.10 (tosAgreed) and E-01.11 (projectionConsent) are left unset — pilot sessions have no visitor to ask, and displayable is set directly rather than derived from a projection consent that doesn't apply here.
- **FS-06-3** *(Entity-access)* Write each exchange to E-02 in transcript order.
- **FS-06-4** *(Function-call)* Call TF-05 for each session to derive its scenario statement.
- **FS-06-5** *(Data-operation)* Return per-transcript outcomes.

**Alternative flows**

- **FA-06-1** *(extends FS-06-1)* A transcript is malformed. It is recorded as rejected and the import continues with the remainder. *Outcome:* Resume.

**TF-07 — Refine a session title**
*Detail level:* Narrative

Called once per session by TI-06, after the session's first exchange exists. Attempts a short (≤38 character) title via TO-02 with a title-generation instruction; on any failure (timeout, malformed response, or any other exception) falls back to the same trim-based derivation TF-05 uses for the scenario statement, just capped at 38 characters instead of TF-05's own bound. Either path always produces a usable title — there is no "couldn't generate one" outcome from the caller's point of view. Writes the result to E-01.12 once and never recomputes it: called again for a session that already has one, it returns the stored value unchanged rather than repeating the provider call. No goal relation: like TF-04 in SE-01, this is presentation logic serving SE-01's header, not a stated business goal.

---

## 5. Technical interfaces (inbound)

**TI-01 — Open a session**
*Call type:* Synchronous
*Calling elements:* SE-01 (TO-01)
*Input:* Three consent booleans, optional as a group; absent on creation, all three required together on the decision call: agreement to take part, retention consent, projection consent.
*Output:* Session identifier (string), turn allowance (integer), session state (enum), origin (enum). Origin is always included — cheap to compute, and consumed only by SE-01's own debug-mode display (QR-06), not gated on this side.
*Action:* Creates a session in E-01 with origin fixed as `live` and a generated identifier, or records the three-part decision against an existing session by writing E-01.7, E-01.10, and E-01.11 — and sets E-01.6 (displayable) directly from the projection choice in the same write. Returns immediately.
*Error cases:*
- Malformed request: missing or non-boolean value on any of the three, on the decision call.
- Agreement to take part is false or absent: rejected before it becomes a stored state (unlike the other two, which are legitimate declines) — this is what makes participation itself, as opposed to retention or projection, a precondition rather than an opt-out.
- Session unknown: the identifier supplied with a decision does not resolve.

**TI-02 — Submit a contribution**
*Call type:* Synchronous
*Calling elements:* SE-01 (TO-02)
*Input:* Session identifier (string, required), contribution text (string, required).
*Output:* The completed exchange (visitor contribution and Sparring response), remaining turn allowance (integer), session state (enum). Returned once generation has completed. Every response, regardless of outcome, also carries rate-limit remaining (integer, from TF-03); an `ok` response additionally carries generation time in milliseconds (from FS-01-8's timing), and a flagged response carries the moderation reason (from TF-02's outcome — `blocked-term`, `contains-personal-information`, `targets-real-person`, or `llm-classification` if the classifier call itself failed). All three are cheap to compute and are included unconditionally. The exact reason is consumed only by SE-01's own debug-mode display (QR-06); SE-01's standard (non-debug) UI derives a coarser two-category hint from the same value — see QR-06's note.
*Action:* Calls TF-01 with the session identifier and contribution, and returns its result. Response time is dominated by the provider call in TF-01 FS-01-8 and is expected to run to several seconds.
*Error cases:*
- Request limit reached (from TF-03).
- Session unknown or expired.
- Turn limit reached.
- Contribution rejected: empty or over-length.
- Contribution flagged: unsuitable per TF-02 (FA-01-2) — distinct from generation-failed. The visitor edits and resubmits; nothing about the session changes.
- Generation failed: any condition grouped by FA-01-1.

*Unlike the earlier design this superseded, the response's condition list is not silent about suitability — a flagged contribution is a distinct, visible outcome (FA-01-2), because the visitor needs to know to edit their message. What the response never carries is which specific field or word triggered the flag, or anything about sessions other than the caller's own. The visitor-facing UI does, deliberately, distinguish two broad categories — personal information versus inappropriate content — from the moderation reason; that coarse hint is not the same as exposing the exact classification or wordlist match, which stays debug-only (QR-06 in L3-SE-01-input-client.md).*

**TI-03 — Retrieve a session**
*Call type:* Synchronous
*Calling elements:* SE-01 (TO-03)
*Input:* Session identifier (string, required).
*Output:* Session state (enum), turn allowance and remaining count (integers), ordered exchanges (array), whether the consent decision has been recorded (boolean) — one flag covering all three parts, since they are always recorded together — and origin (enum), same rationale as TI-01's.
*Action:* Reads the session from E-01 and its exchanges from E-02 in order. Returns immediately.
*Error cases:*
- Session unknown or expired.

**TI-04 — Retrieve display material**
*Call type:* Synchronous
*Calling elements:* SE-02 (TO-01)
*Input:* None.
*Output:* An ordered list of items, each carrying a session identifier, scenario statement, one visitor contribution, one Sparring response, an origin value, and a recency position.
*Action:* Calls TF-04 and returns its result. Returns immediately.
*Error cases:* None specific to this interface. An empty list is a valid response and occurs only when no pilot material has been imported.

*This interface takes no parameters by design. Item count, ordering, exchange selection, and pilot fallback are all decided in TF-04, so that a change to any of them requires no change in SE-02 and no coordination between the two.*

**TI-05 — Import pilot material**
*Call type:* Synchronous
*Calling elements:* UT-03, directly on the host.
*Input:* A structured transcript file.
*Output:* Per-transcript outcomes: imported or rejected, with a reason for each rejection.
*Action:* Calls TF-06. Not reachable over the public endpoint (C-03).
*Error cases:*
- File unreadable or unparseable in full.
- Individual transcript malformed, reported per transcript rather than as a failure of the call.

**TI-06 — Refine a session title**
*Call type:* Synchronous
*Calling elements:* SE-01 (TO-04)
*Input:* Session identifier (string, required).
*Output:* Refined title (string) — always present when the call succeeds.
*Action:* Calls TF-07 and returns its result. Returns once the provider call (or its fallback) completes.
*Error cases:*
- Session unknown.
- Malformed request.
- No exchange yet exists for the session — the caller normally only invokes this after the first exchange completes.

---

## 6. Technical interfaces (outbound)

**TO-01 — Request a Sparring response**
*Statement:* This interface obtains a Sparring partner reply to a visitor contribution by calling the language generation API (PE-01). Used for every visitor turn.
*Input:* The Sparring system prompt, the session's prior exchanges as conversational context, the new visitor contribution, and generation parameters.
*Output:* Response text.
*Error cases:* Rate limit exceeded. Quota exhausted. Content-policy refusal. Provider server error. Timeout. Transport failure. Authentication failure.
*Authoritative spec:* Provider documentation. No element in this system holds the authoritative specification.
*Calls:* PE-01

**TO-02 — Request a classification**
*Statement:* This interface obtains a suitability classification, a scenario statement, or a session title for a visitor contribution, by calling the language generation API (PE-01). Used by TF-02 on every turn, by TF-05 only if the trimmed-contribution approach proves inadequate, and by TF-07 once per session.
*Input:* The contribution text and a classification instruction, a summarisation instruction, or a title-generation instruction.
*Output:* A classification value, a short statement, or a short title.
*Error cases:* As TO-01.
*Authoritative spec:* Provider documentation.
*Calls:* PE-01

*Call behaviour is specified on the calling function steps (TF-01 FS-01-8, TF-02 FS-02-2), or in the calling function's own narrative for TF-07, not here. The three differ substantially: TF-01 retries and fails open to an error the visitor sees; TF-02 does not retry and fails closed to unsuitable; TF-07 does not retry and fails open to a trim-based fallback, never an error.*

---

## 7. Entities

**E-01 — Session**
*Persistence:* Persistent
*Refines:* BE-01

| ID | Attribute | Type | Required | Description |
|---|---|---|---|---|
| E-01.1 | sessionId | string | required | Generated identifier, sufficiently random to resist enumeration. Travels in the client address (AP-03), so it is not secret and nothing sensitive may be derived from it. |
| E-01.2 | createdAt | datetime | required | When the session was created. |
| E-01.3 | scenarioSource | enum | required | `first-contribution` or `generated`. Records how E-01.5 was produced, so a later change of approach is visible in the data. |
| E-01.4 | origin | enum | required | `pilot` or `live`. Written at creation and never modified (SQR-05). |
| E-01.5 | scenario | string | optional | The context line shown with the exchange. Absent until the first exchange exists. |
| E-01.6 | displayable | boolean | required | Whether the session may appear on the projection. Defaults false; set directly from E-01.11 (projectionConsent) when the consent decision is recorded — not by moderation. Nothing unsuitable can reach E-02 in the first place (G-04/AP-05), so there is no separate moderation-driven display flag to maintain. Independent of E-01.7. |
| E-01.7 | consentGranted | boolean | optional | Whether the participant agreed to retention. Absent until the decision is recorded. Independent of E-01.6 (SQR-06). |
| E-01.8 | turnCount | integer | required | Exchanges completed. Compared against the turn allowance. |
| E-01.9 | lastActiveAt | datetime | required | Time of the most recent exchange. Drives display ordering and expiry. |
| E-01.10 | tosAgreed | boolean | optional | Whether the participant agreed to the terms of participation. Absent until recorded; once recorded it is always true — a decline is rejected at TI-01 before it becomes a stored value (unlike E-01.7 and E-01.11, which legitimately store `false`). |
| E-01.11 | projectionConsent | boolean | optional | Whether the participant agreed to projection. Absent until the decision is recorded. Drives E-01.6 directly. Independent of E-01.7 — a session may be retained and not projected, or projected and not retained. |
| E-01.12 | title | string | optional | Short (≤38 character) header title, refined once from the first contribution and never recomputed (TF-07). Absent until refined. |

*E-01.6, E-01.7, and E-01.11 govern three separate things: whether the public surface may currently show this session, whether it may be kept after the exhibition, and the visitor's own projection choice — the first is now derived from the third, the second stays fully independent of both. E-01.10 is different in kind from the other two consent fields: it gates participation itself rather than a data-processing purpose, which is why it is never stored as a decline (see L1 BC-02, L2 SC-05).*

**E-02 — Exchange**
*Persistence:* Persistent
*Refines:* BE-02

| ID | Attribute | Type | Required | Description |
|---|---|---|---|---|
| E-02.1 | exchangeId | integer | required | Identifier, ordered by creation. |
| E-02.2 | sessionId | string | required | Reference to E-01.1. |
| E-02.3 | visitorContribution | string | required | The visitor's turn as submitted, after trimming. |
| E-02.4 | sparringResponse | string | required | The reply produced for that turn. |
| E-02.5 | position | integer | required | Position within the session, from one. |
| E-02.6 | createdAt | datetime | required | When the exchange completed. |

*The pair is stored as one record rather than as two turn records. This follows the display unit (BE-02) and removes the possibility of a stored contribution with no response, which is the state a failed generation would otherwise leave behind.*

**E-03 — Rate limit window**
*Persistence:* Transient. Reset in place on expiry rather than swept.

| ID | Attribute | Type | Required | Description |
|---|---|---|---|---|
| E-03.1 | originHash | string | required | Hash of the request origin. The raw address is never stored. |
| E-03.2 | windowStart | datetime | required | Start of the current counting window. |
| E-03.3 | requestCount | integer | required | Requests counted in the current window. |

*Purely technical, with no L1 business entity counterpart.*

---

## 8. Quality requirements

**QR-01 — Generation is bounded and its bound is enforced here** *(Performance)*
*Applies to:* TF-01, TO-01
*Acceptance criteria:* TF-01 abandons a generation request at a fixed bound and returns a generation-failed condition. The bound is TBC, to be set from measured response times, and must be below SE-01's own wait bound so that the element decides the outcome rather than the client timing out first.
*Supports:* SQR-01

**QR-02 — Restarts unattended** *(Availability)*
*Applies to:* The element overall
*Acceptance criteria:* The process restarts automatically after failure. No in-memory state is required for correctness after a restart, because everything needed is in E-01 through E-03.
*Supports:* SQR-02

**QR-03 — A rejected request affects only its own session** *(Availability)*
*Applies to:* TF-01, TF-03
*Acceptance criteria:* Rate limiting, turn limits, and generation failures return a condition for the requesting session and leave every other session serviceable.
*Supports:* SQR-02

**QR-04 — The provider credential never leaves the host** *(Security)*
*Applies to:* TO-01, TO-02
*Acceptance criteria:* The credential is read from host configuration, is absent from version control, and appears in no response body, error message, or log line.
*Supports:* SQR-04

**QR-05 — Limits are enforced on every path** *(Security)*
*Applies to:* TF-01, TF-03, TI-02
*Acceptance criteria:* Contribution length, session turn allowance, and request rate are each checked in this element regardless of whether a client has already checked them.
*Supports:* SQR-04

**QR-06 — Origin is written once** *(Data-integrity)*
*Applies to:* E-01, TF-06, TI-01
*Acceptance criteria:* E-01.4 is set at creation and no code path updates it. No process infers origin from any other value.
*Supports:* SQR-05

**QR-07 — No partial exchange is ever stored** *(Data-integrity)*
*Applies to:* TF-01
*Acceptance criteria:* E-02 is written only after a response has been received. Every failure path in FS-01-8 writes nothing.
*Supports:* SQR-05
*Element specific:* Yes

**QR-08 — Suitability assessment fails closed** *(Compliance)*
*Applies to:* TF-02
*Acceptance criteria:* Every failure of TF-02, including provider unavailability, yields an unsuitable outcome. No path treats an unavailable classifier as permission to project.
*Supports:* SQR-06

**QR-09 — Projection eligibility and retention consent are never conflated** *(Compliance)*
*Applies to:* E-01, TF-01, TF-04, TI-01
*Acceptance criteria:* E-01.7 (retention) is read and written independently of E-01.6/E-01.11 (projection). E-01.6 is permitted to derive from E-01.11 — that is the one intentional coupling, recorded once at consent time, not re-derived elsewhere — but no path derives E-01.7 from either, or E-01.11 from E-01.7.
*Supports:* SQR-06

**QR-10 — No raw request origin is stored** *(Compliance)*
*Applies to:* E-03, TF-03
*Acceptance criteria:* Only the hashed origin is written. The raw value exists in memory for the duration of the request and nowhere else.
*Element specific:* Yes

**QR-11 — Infrastructure faults degrade to a generic error, not a leak** *(Security)*
*Applies to:* TI-01, TI-02, TI-03, TI-04
*Acceptance criteria:* An uncaught exception on any public endpoint (missing credential, unwritable store, or any other infrastructure fault) returns a generic JSON error with no file path, stack trace, or exception message.
*Supports:* SQR-04

---

## 9. Constraints

**C-01 — PHP with a file-backed store on one instance** *(Technical)*
*Source:* SC-01.
*Applies to:* The element overall, E-01 through E-03.
*Acceptance criteria:* Runs as a single process group against a single store file, with no external database, cache, or queue service.
*Consequence:* Rules out any design requiring concurrent write scaling or a background worker. Long-running work must complete within the request, which is why TF-01 is synchronous, why TF-05 caches its result rather than recomputing, and why TF-07's title refinement is triggered by a dedicated client call (TI-06) rather than a server-side background job — there is no queue or worker process for SE-01 to hand it to.
*Implements:* SC-01

**C-02 — Provider limits bound the element's own limits** *(Integration)*
*Source:* SC-04.
*Applies to:* TF-03, TF-01.
*Acceptance criteria:* The request rate threshold in TF-03 is set below the provider's limit, so that visitors meet this element's limit rather than the provider's.
*Consequence:* Rules out unthrottled forwarding. Sets an upper bound on concurrent visitors, TBC once the threshold is chosen.
*Implements:* SC-04

**C-03 — The operator path is not publicly reachable** *(Security)*
*Source:* SC-03, and the absence of any authentication in this system.
*Applies to:* TI-05.
*Acceptance criteria:* TI-05 is invoked on the host and is not routed on the public endpoint.
*Consequence:* Rules out remote import and, with it, the need for an authentication mechanism this system otherwise has no use for. Seeding requires host access.
*Element specific:* Yes

**C-04 — No administrative interface, no analytics layer** *(Business)*
*Source:* SC-03.
*Applies to:* The element overall.
*Acceptance criteria:* Operator tasks — seeding, extraction, reset, and backup — are performed directly against the host and the store file, each its own single-purpose script (`bin/import_pilot.php`, `bin/export.php`, `bin/reset_db.php`, `bin/backup_db.php`).
*Consequence:* Rules out building any surface for inspecting or moderating sessions during the exhibition. Extraction is a query against the store; reset and backup are, respectively, a bulk delete and a file-level snapshot against the same store, guarded by an explicit confirmation flag rather than by any authentication this system otherwise has no use for (C-03).
*Implements:* SC-03

**C-05 — Attempts to bypass the Sparring stance are not filtered** *(Business)*
*Source:* AP-07.
*Applies to:* TF-02.
*Acceptance criteria:* The classification instruction distinguishes hostility directed at the Sparring partner (suitable, however extreme the tone) from harmful content targeting a real person or group (targets-real-person). A visitor arguing the Sparring partner out of its position, however hostile, produces a suitable classification. The instruction also states the exercise's actual subject matter explicitly (TF-02) — necessary alongside the target-based distinction, not instead of it; found missing in testing, where its absence alone caused on-topic contributions to fail this same acceptance criterion from the other direction.
*Consequence:* There is no off-topic filter of any kind — disengagement (spam, unrelated chat) is not classified or withheld. Sessions in which the friction is successfully talked away are retained and projected like any other (subject to the visitor's own projection consent, SC-05).
*Element specific:* Yes

**C-06 — Classifier input is untrusted data, not instruction** *(Security)*
*Source:* this review.
*Applies to:* TF-02.
*Acceptance criteria:* The contribution text is delimited and labeled as data in the classification prompt, and delimiter characters that could close that boundary are stripped from the contribution before assembly.
*Consequence:* Distinct from C-05 — that constraint accepts persona-steering as the exhibit's own subject; this one closes the separate case of a contribution engineered to flip the moderation verdict itself, which has no comparable justification for staying open.
*Element specific:* Yes
