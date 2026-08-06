# L3 — Element Design Concept: Backend Service

**Element:** SE-03 Backend service
**Element profile:** Backend service / API
**Parent:** L2 System Design Concept
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

**G-04 — Decide what may be projected, independently of what may be retained**
The element shall assess contributions for projection suitability and gate the display feed on that assessment, without affecting the participant's own session.
*Success criteria (qualitative):* A session marked ineligible for projection continues to serve its participant with no observable difference.
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
- **FS-01-6** *(Entity-access)* If TF-02 reported the contribution unsuitable, write `false` to E-01.6 (displayable). The session is not otherwise altered and processing continues, per AP-05.
- **FS-01-7** *(Entity-access)* Read the session's prior exchanges from E-02 (E-02.3, E-02.4) in order, to supply conversational context.
- **FS-01-8** *(Outbound-call)* Call TO-01 with the Sparring system prompt, the prior exchanges, and the new contribution. Expect a response text. *Call behaviour:* Synchronous. Timeout TBC, to be set from measured response times (SQR-01). Two attempts, with a short delay between them, on transport failure, timeout, or a provider 5xx. **No retry on a provider rate-limit or quota response**, because retrying a rate limit deepens it; return a generation-failed condition immediately. **No retry on a content-policy refusal from the provider**, which is a determinate outcome rather than a transient fault. On exhausted attempts, return a generation-failed condition. Nothing is written to E-02 on any failure path, so a failed turn leaves no partial exchange in the record.
- **FS-01-9** *(Entity-access)* Write a new exchange to E-02 carrying the contribution (E-02.3), the response (E-02.4), the session reference (E-02.2), and its position (E-02.5).
- **FS-01-10** *(Entity-access)* Increment E-01.8 (turnCount) and update E-01.9 (lastActiveAt).
- **FS-01-11** *(Function-call)* If this was the session's first exchange, call TF-05 to derive the scenario statement and write it to E-01.5.
- **FS-01-12** *(Data-operation)* Return the response, the remaining turn allowance, and the session state.

**Alternative flows**

- **FA-01-1** *(extends FS-01-8)* Provider rate limit, quota exhaustion, timeout, transport failure after retries, or content-policy refusal. All return a generation-failed condition to the caller and write nothing. Grouping is deliberate: the calling client takes the same action for every one of these, and distinguishing them would produce a taxonomy nobody acts on. The distinction is preserved in logs rather than in the response. *Outcome:* Terminate.

**TF-02 — Assess a contribution for projection suitability**
*Detail level:* Stepwise
*Achieves:* G-04

Determines whether a contribution may appear on the public surface. It never determines whether the visitor may continue.

- **FS-02-1** *(Data-operation)* Check the contribution against a maintained term list covering profanity. A match returns unsuitable without a provider call.
- **FS-02-2** *(Outbound-call)* Call TO-02 with the contribution and a classification instruction. Expect one of: suitable, contains-personal-information, off-exercise. *Call behaviour:* Synchronous, short timeout, single attempt, no retry. **On any failure, return unsuitable.** Failing closed is correct here: the cost of not projecting a suitable contribution is that one item is missing from a wall, and the cost of projecting an unsuitable one is unrecoverable once it is six feet tall in a public room.
- **FS-02-3** *(Data-operation)* Map the classification to an outcome and return it.

**The distinction this function must get right (AP-07).** *Off-exercise* means the visitor has stopped doing the exercise: unrelated chat, spam, testing whether the field accepts input. It does **not** mean a visitor who is arguing with the Sparring partner, pressuring it, instructing it to abandon its position, or otherwise trying to talk the friction away. That visitor is engaged with the exercise and their attempt is the observation the installation exists to produce. The classification instruction must state this distinction explicitly rather than leaving it to the model's default reading of "off-topic", because both cases superficially resemble not-doing-the-exercise and only one should be withheld.

*Contains-personal-information* covers names, contact details, and health, financial, or comparably personal circumstances. This case is expected to arise most often in exactly the situation AP-07 protects: a visitor under real pressure disclosing something real while trying to get the system to relent. That session must continue normally and must not be projected.

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
- **FS-06-2** *(Entity-access)* For each valid transcript, write a session to E-01 with E-01.4 (origin) set to `pilot`, E-01.6 (displayable) set to true, and E-01.7 (consentGranted) set to true.
- **FS-06-3** *(Entity-access)* Write each exchange to E-02 in transcript order.
- **FS-06-4** *(Function-call)* Call TF-05 for each session to derive its scenario statement.
- **FS-06-5** *(Data-operation)* Return per-transcript outcomes.

**Alternative flows**

- **FA-06-1** *(extends FS-06-1)* A transcript is malformed. It is recorded as rejected and the import continues with the remainder. *Outcome:* Resume.

---

## 5. Technical interfaces (inbound)

**TI-01 — Open a session**
*Call type:* Synchronous
*Calling elements:* SE-01 (TO-01)
*Input:* Retention decision (boolean, optional; absent on creation, supplied on the decision call).
*Output:* Session identifier (string), turn allowance (integer), session state (enum).
*Action:* Creates a session in E-01 with origin fixed as `live` and a generated identifier, or records the retention decision against an existing session by writing E-01.7. Returns immediately.
*Error cases:*
- Malformed request: missing or non-boolean decision on the decision call.
- Session unknown: the identifier supplied with a decision does not resolve.

**TI-02 — Submit a contribution**
*Call type:* Synchronous
*Calling elements:* SE-01 (TO-02)
*Input:* Session identifier (string, required), contribution text (string, required).
*Output:* The completed exchange (visitor contribution and Sparring response), remaining turn allowance (integer), session state (enum). Returned once generation has completed.
*Action:* Calls TF-01 with the session identifier and contribution, and returns its result. Response time is dominated by the provider call in TF-01 FS-01-8 and is expected to run to several seconds.
*Error cases:*
- Request limit reached (from TF-03).
- Session unknown or expired.
- Turn limit reached.
- Contribution rejected: empty or over-length.
- Generation failed: any condition grouped by FA-01-1.

*The response carries no indication of the suitability assessment. A session marked ineligible for projection receives an ordinary response, per AP-05 and G-04.*

**TI-03 — Retrieve a session**
*Call type:* Synchronous
*Calling elements:* SE-01 (TO-03)
*Input:* Session identifier (string, required).
*Output:* Session state (enum), turn allowance and remaining count (integers), ordered exchanges (array), whether a retention decision has been recorded (boolean).
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
*Statement:* This interface obtains a suitability classification for a visitor contribution, and optionally a scenario statement, by calling the language generation API (PE-01). Used by TF-02 on every turn, and by TF-05 only if the trimmed-contribution approach proves inadequate.
*Input:* The contribution text and a classification instruction, or the contribution text and a summarisation instruction.
*Output:* A classification value, or a short statement.
*Error cases:* As TO-01.
*Authoritative spec:* Provider documentation.
*Calls:* PE-01

*Call behaviour for both interfaces is specified on the calling function steps (TF-01 FS-01-8, TF-02 FS-02-2), not here. The two differ substantially: TF-01 retries and fails open to an error the visitor sees, TF-02 does not retry and fails closed to unsuitable.*

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
| E-01.6 | displayable | boolean | required | Whether the session may appear on the projection. Defaults true; set false by TF-01 FS-01-6. Independent of E-01.7. |
| E-01.7 | consentGranted | boolean | optional | Whether the participant agreed to retention. Absent until the decision is recorded. Independent of E-01.6 (SQR-06). |
| E-01.8 | turnCount | integer | required | Exchanges completed. Compared against the turn allowance. |
| E-01.9 | lastActiveAt | datetime | required | Time of the most recent exchange. Drives display ordering and expiry. |

*E-01.6 and E-01.7 are deliberately separate fields governing separate things: whether the public surface may show this session, and whether it may be kept after the exhibition. Combining them into one flag would make it impossible to retain a session that must not be projected, which is the exact case TF-02's personal-information outcome produces.*

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
*Acceptance criteria:* E-01.6 and E-01.7 are read and written independently. No path sets one from the other.
*Supports:* SQR-06

**QR-10 — No raw request origin is stored** *(Compliance)*
*Applies to:* E-03, TF-03
*Acceptance criteria:* Only the hashed origin is written. The raw value exists in memory for the duration of the request and nowhere else.
*Element specific:* Yes

---

## 9. Constraints

**C-01 — PHP with a file-backed store on one instance** *(Technical)*
*Source:* SC-01.
*Applies to:* The element overall, E-01 through E-03.
*Acceptance criteria:* Runs as a single process group against a single store file, with no external database, cache, or queue service.
*Consequence:* Rules out any design requiring concurrent write scaling or a background worker. Long-running work must complete within the request, which is why TF-01 is synchronous and why TF-05 caches its result rather than recomputing.
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
*Acceptance criteria:* Operator tasks are performed directly against the host and the store file.
*Consequence:* Rules out building any surface for inspecting or moderating sessions during the exhibition. Extraction is a query against the store.
*Implements:* SC-03

**C-05 — Attempts to bypass the Sparring stance are not filtered** *(Business)*
*Source:* AP-07.
*Applies to:* TF-02.
*Acceptance criteria:* The classification instruction distinguishes disengagement from adversarial engagement, and only the former yields an off-exercise outcome. A visitor arguing the Sparring partner out of its position produces a suitable classification.
*Consequence:* Rules out a general off-topic filter applied without this distinction. Sessions in which the friction is successfully talked away are retained and projected like any other.
*Element specific:* Yes
