/*
 * SE-01: no build step (C-04). Implements TF-01 (establish session), TF-02
 * (retention decision), TF-03 (submit), TF-04 (render history) and their
 * alternative flows, including the resolved moderation gap: a flagged
 * contribution is rejected, not the session ended — the field re-enables
 * with the text preserved so the visitor can edit and resubmit.
 */

/**
 * Pure decision table for handleContributionResult's outcomes, minus 'ok'
 * and 'turn-limit' (those two don't select from a message table — see the
 * switch in handleContributionResult below). status/moderationReason in,
 * {message, restoreText, enableComposer, wiggle, sound} out. No DOM, so
 * it's unit-testable without a browser (tests/smoke_dojo.js).
 */
window.SparringDojoOutcome = {
    resolveOutcome: function (status, moderationReason) {
        var strings = window.STRINGS.dojo;
        switch (status) {
            case 'rate-limited':
                return { message: strings.outcomeRateLimited, restoreText: true, enableComposer: true, wiggle: true, sound: 'fumble' };

            case 'rejected':
                return { message: strings.outcomeRejected, restoreText: true, enableComposer: true, wiggle: true, sound: 'fumble' };

            case 'content-flagged': {
                // Coarse category only, never the exact reason — see
                // spec/L3-SE-03-backend-service.md's note on this field.
                var message = strings.outcomeFlaggedGeneric; // fallback: classifier failure, real reason unknown
                if (moderationReason === 'contains-personal-information') {
                    message = strings.outcomeFlaggedPersonalInfo;
                } else if (moderationReason === 'targets-real-person' || moderationReason === 'blocked-term') {
                    message = strings.outcomeFlaggedInappropriate;
                }
                return { message: message, restoreText: true, enableComposer: true, wiggle: true, sound: 'fumble' };
            }

            case 'session-unknown':
                return { message: strings.outcomeSessionUnknown, restoreText: false, enableComposer: false, wiggle: false, sound: null };

            default: // generation-failed, or anything unrecognised
                return { message: strings.outcomeGenerationFailed, restoreText: true, enableComposer: true, wiggle: true, sound: 'fumble' };
        }
    },
};

(function () {
    'use strict';

    var MAX_CHARS = window.CONTRIBUTION_MAX_CHARS || 600;
    var WAIT_MS = window.SE01_WAIT_BOUND_MS || 25000;
    var DEBUG = new URLSearchParams(window.location.search).get('debug') === '1';
    // Draft persistence: sessionStorage only (temporary, tab-scoped) so an
    // accidental reload doesn't lose an unsent contribution.
    var DRAFT_KEY = 'sparring-draft';

    var historyEl = document.getElementById('history');
    var playbookEl = document.getElementById('playbook');
    var retentionEl = document.getElementById('retention');
    var composerEl = document.getElementById('composer');
    var fieldEl = document.getElementById('contribution');
    var submitEl = document.getElementById('submit');
    var charRemainingEl = document.getElementById('char-remaining');
    var sessionTitleEl = document.getElementById('session-title');
    var avatarBtn = document.getElementById('avatar-btn');
    var avatarPopover = document.getElementById('avatar-popover');
    var avatarAliasEl = document.getElementById('avatar-alias');
    var newSessionBtn = document.getElementById('new-session-btn');
    var titleCardEl = document.getElementById('title-card');
    var replyQuoteEl = document.getElementById('reply-quote');
    var replyQuoteTextEl = document.getElementById('reply-quote-text');
    var replyQuoteCancelBtn = document.getElementById('reply-quote-cancel');
    var evalDialogEl = document.getElementById('eval-dialog');
    var evalFormEl = document.getElementById('eval-form');
    var evalFeedbackEl = document.getElementById('eval-feedback');
    var evalSkipBtn = document.getElementById('eval-skip');
    var evalSubmitBtn = document.getElementById('eval-submit');
    var evalSuccessEl = document.getElementById('eval-success');
    var evalSuccessMessageEl = document.getElementById('eval-success-message');
    var evalStartNewBtn = document.getElementById('eval-start-new');
    var evalGoToStartBtn = document.getElementById('eval-go-to-start');

    var sessionId = null;
    var statusTurnEl = null; // the one managed "status" entry in #history, if any (see setHistoryStatus)
    var optimisticTurnEl = null; // visitor turn shown ahead of the server response (see submitContribution); pruned on any non-'ok' outcome
    var pendingReplyQuote = null; // {exchangeId, text} — QR-reply flow; only ever set on a fresh session's first turn, cleared on cancel or on 'ok'
    var titleRequested = false; // guards the fire-and-forget /api/title fetch to once per page load (see handleContributionResult)
    var debugPanel = null;
    var lastDebugInfo = {};

    /*
    |--------------------------------------------------------------------------
    | Identity: avatar + alias
    |--------------------------------------------------------------------------
    |
    | Revealed only once consent is recorded.
    |
    */
    function revealIdentity(id) {
        avatarBtn.textContent = window.SparringIdentity.avatar(id);
        avatarAliasEl.textContent = window.SparringIdentity.alias(id);
        avatarBtn.hidden = false;
    }

    function closePopover() {
        avatarPopover.hidden = true;
        avatarBtn.setAttribute('aria-expanded', 'false');
    }

    avatarBtn.addEventListener('click', function (event) {
        event.stopPropagation();
        var opening = avatarPopover.hidden;
        avatarPopover.hidden = !opening;
        avatarBtn.setAttribute('aria-expanded', String(opening));
    });

    document.addEventListener('click', function (event) {
        if (!avatarPopover.hidden && !avatarPopover.contains(event.target) && event.target !== avatarBtn) {
            closePopover();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closePopover();
    });

    newSessionBtn.addEventListener('click', function () {
        if (window.confirm(window.STRINGS.dojo.confirmEndSession)) {
            sessionStorage.removeItem(DRAFT_KEY); // ending session should not leave next session's composer pre-filled
            showEvalDialog(sessionId, function () {
                window.location.href = '/';
            });
        }
    });

    /*
    |--------------------------------------------------------------------------
    | QR-reply flow: quote chip above the composer
    |--------------------------------------------------------------------------
    |
    | Shown for a fresh session only (never on resumeSession — see
    | createSession() below), never editable, never counted against
    | CONTRIBUTION_MAX_CHARS since the quoted text never enters fieldEl.value.
    |
    */
    function showReplyQuote(quote) {
        pendingReplyQuote = quote;
        replyQuoteTextEl.textContent = quote.text;
        replyQuoteEl.hidden = false;
    }

    // Cancel button: full clear — nothing left to send, chip gone for good.
    function cancelReplyQuote() {
        pendingReplyQuote = null;
        replyQuoteEl.hidden = true;
    }

    replyQuoteCancelBtn.addEventListener('click', cancelReplyQuote);

    // Same text prepended into both the optimistic bubble (submitContribution)
    // and the persisted exchange (Sparring::processTurn) — kept in one place
    // so the two never drift out of sync with each other. Label comes from
    // window.STRINGS (dojo.replyQuote.label via t(), i.e. this request's own
    // locale) rather than a literal, matching Sparring::processTurn exactly.
    function withQuotePrefix(quote, text) {
        return window.STRINGS.dojo.replyQuoteLabel + ' "' + quote.text + '"\n\n' + text;
    }

    /*
    |--------------------------------------------------------------------------
    | Debug mode (?debug=1)
    |--------------------------------------------------------------------------
    |
    | Surfaces values already computed server-side, nothing new to compute.
    |
    */
    function updateDebugPanel(info) {
        if (!DEBUG) return;
        if (!debugPanel) {
            debugPanel = document.createElement('pre');
            debugPanel.id = 'debug-panel';
            document.querySelector('main').appendChild(debugPanel);
        }
        Object.assign(lastDebugInfo, info);
        debugPanel.textContent = JSON.stringify(lastDebugInfo, null, 2);
    }

    fieldEl.setAttribute('maxlength', String(MAX_CHARS));

    /*
    |--------------------------------------------------------------------------
    | Rendering (TF-04)
    |--------------------------------------------------------------------------
    |
    | All content inserted as text, never markup (QR-04 / display QR-05
    | counterpart).
    |
    */
    function appendTurn(role, text) {
        var el = document.createElement('div');
        el.className = 'turn ' + role;
        el.textContent = text;
        historyEl.appendChild(el);
        historyEl.scrollTop = historyEl.scrollHeight;
        return el;
    }

    // Status now lives inside #history as a managed entry (formerly a
    // separate #status element) — every prior in-progress/error/limit
    // message routes through here. Always clears whatever status entry
    // exists first, then creates a fresh one if there's a message — so
    // calling setHistoryStatus(null) is how every call site clears it, and
    // there is never more than one status entry in the history at a time.
    function setHistoryStatus(message, pending) {
        if (statusTurnEl) {
            statusTurnEl.remove();
            statusTurnEl = null;
        }
        if (!message) return;
        statusTurnEl = document.createElement('div');
        statusTurnEl.className = 'turn turn--status' + (pending ? ' turn--pending' : '');
        statusTurnEl.setAttribute('role', 'status');
        statusTurnEl.textContent = message;
        historyEl.appendChild(statusTurnEl);
        historyEl.scrollTop = historyEl.scrollHeight;
    }

    // Next "thinking" status: one call per turn, walks up
    // window.STRINGS.dojo.thinkingStatuses and wraps back to the start —
    // no timer, the message just changes turn to turn.
    var thinkingStatusIndex = -1;
    function nextThinkingStatus() {
        var statuses = window.STRINGS.dojo.thinkingStatuses;
        thinkingStatusIndex = (thinkingStatusIndex + 1) % statuses.length;
        return statuses[thinkingStatusIndex];
    }

    // Removes the optimistically-placed visitor turn (see submitContribution)
    // when the server outcome isn't 'ok' — history stays a mirror of
    // confirmed exchanges, never a submission that didn't land.
    function clearOptimisticTurn() {
        if (!optimisticTurnEl) return;
        optimisticTurnEl.remove();
        optimisticTurnEl = null;
    }

    // Session title, raw-text variant: used only on resumeSession (page
    // reload mid-session), where the visitor's first contribution is already
    // known synchronously from history and there's no fresh /api/title round
    // trip to wait on. Write-once (a resumed session never re-derives its
    // title from later exchanges). A fresh submission does NOT call this —
    // #session-title stays "Untitled" until refineSessionTitle (below) has a
    // real answer; see submitContribution.
    function setSessionTitle(text) {
        if (sessionTitleEl.classList.contains('set')) return;
        sessionTitleEl.textContent = text;
        sessionTitleEl.classList.add('set');
    }

    // Sets the title from /api/title's resolved value (LLM title, or its
    // trim-based fallback — either way, a real answer, never raw untruncated
    // text). Unlike setSessionTitle this always overwrites — fired at most
    // once per page load (titleRequested guard at the call site).
    function refineSessionTitle(text) {
        sessionTitleEl.textContent = text;
        sessionTitleEl.classList.add('set');
    }

    /*
    |--------------------------------------------------------------------------
    | Juiciness (TODO.md JUICYNESS)
    |--------------------------------------------------------------------------
    |
    | Purely presentational, layered on top of the flows above, never gates
    | them. Every trigger below checks its own isJuicyOn() flag, so
    | config.php can kill any one of these independently with no code change.
    |
    */

    // One-time overlay shown once the consent decision is recorded (UC-01)
    // — not tied to any particular submission, so it never competes with
    // TF-03's in-progress state. On-screen time below matches the three
    // lines' animations in dojo.css: line 1 slide (1000ms) + line 2's
    // delay+slide (1050ms + 1000ms) + line 3's delay+grow (2100ms + 1000ms),
    // i.e. line 3 finishes at 3100ms.
    function showTitleCard() {
        if (!window.isJuicyOn('titleCard')) return;
        window.SparringSfx.playSequence('titleCard'); // own isJuicyOn('sound') check gates this independently
        titleCardEl.classList.add('collapsed'); // start at 0 height while still [hidden]
        titleCardEl.hidden = false;
        void titleCardEl.offsetWidth; // commit the collapsed layout before animating away from it
        requestAnimationFrame(function () {
            titleCardEl.classList.remove('collapsed'); // grows 0 -> full height
        });
        setTimeout(function () {
            // fires as the "SPAR!" line's grow animation starts (line 3's own delay, see comment above)
            var rect = titleCardEl.getBoundingClientRect();
            window.SparringParticles.burst(rect.left + rect.width / 2, rect.top + rect.height / 2, 'titleCard', 3);
        }, 2100);
        setTimeout(function () {
            titleCardEl.classList.add('collapsed'); // shrinks back to 0
            setTimeout(function () { titleCardEl.hidden = true; }, 250);
        }, 3100);
    }

    // Punch animation + particle burst on the submit button, plus a sound.
    function triggerPunch() {
        if (window.isJuicyOn('punch')) {
            submitEl.classList.remove('punching');
            void submitEl.offsetWidth; // restart the animation if retriggered before the previous one finished
            submitEl.classList.add('punching');
            var rect = submitEl.getBoundingClientRect();
            window.SparringParticles.burst(rect.left + rect.width / 2, rect.top + rect.height / 2);
        }
        window.SparringSfx.play('punch');
    }

    // Shake the composer on a rejected/failed submission.
    function triggerWiggle() {
        if (!window.isJuicyOn('wiggle')) return;
        composerEl.classList.remove('wiggling');
        void composerEl.offsetWidth;
        composerEl.classList.add('wiggling');
    }

    function updateCharRemaining() {
        charRemainingEl.textContent = window.STRINGS.dojo.charsRemaining.replace('{n}', String(MAX_CHARS - fieldEl.value.length));
        autoGrowField();
    }

    // Grow the textarea to fit its content, up to the 5-line cap set in
    // dojo.css (max-height); overflow-y:auto there takes over past that.
    // Reset to 'auto' first so scrollHeight can shrink back down, not just grow.
    function autoGrowField() {
        fieldEl.style.height = 'auto';
        fieldEl.style.height = fieldEl.scrollHeight + 'px';
    }

    function setComposerEnabled(enabled) {
        fieldEl.disabled = !enabled;
        submitEl.disabled = !enabled || fieldEl.value.trim() === '';
    }

    function applySessionState(state, turnsRemaining) {
        if (state === 'complete') {
            setComposerEnabled(false);
            setHistoryStatus(window.STRINGS.dojo.sessionComplete, false);
        } else if (state === 'open') {
            setComposerEnabled(true);
        }
        if (typeof turnsRemaining === 'number') {
            composerEl.dataset.turnsRemaining = String(turnsRemaining);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | End-of-session feedback dialog (TODO.md "Session Evaluation")
    |--------------------------------------------------------------------------
    |
    | Always skippable — no field is required. Shown at most once per session
    | per browser (sessionStorage flag, same idiom as DRAFT_KEY), so resuming
    | an already-complete session on reload doesn't re-show it. Answers/
    | feedback POST fire-and-forget to /api/evaluate — a failed request
    | shouldn't block the visitor from leaving, same as the /api/title call.
    */
    // Glove-rating widgets have no visible text of their own (see dojo.php/
    // dojo.css) — this is what shows the picked option's label above them.
    // One listener for every question, delegated on the form and wired
    // once (not per showEvalDialog call): the widgets exist for the whole
    // page lifetime, there's nothing to clean up on close.
    evalFormEl.addEventListener('change', function (event) {
        var input = event.target;
        if (input.type !== 'radio') return;
        var fieldsetEl = input.closest('fieldset.eval-question');
        var labelEl = fieldsetEl && fieldsetEl.querySelector('.eval-scale-label');
        if (labelEl) labelEl.textContent = input.dataset.label || '';
    });

    function evalShownKey(id) {
        return 'sparring-eval-shown-' + id;
    }

    function showEvalDialog(id, onClose) {
        if (!evalDialogEl || sessionStorage.getItem(evalShownKey(id))) {
            if (onClose) onClose();
            return;
        }
        sessionStorage.setItem(evalShownKey(id), '1');
        evalDialogEl.hidden = false;
        newSessionBtn.disabled = true; // a second "End session" press while this is open would abandon whatever's typed here

        // Shared by Skip and Submit — both end the form the same way, just
        // with different copy (and Submit alone has a request in flight).
        // Neither actually leaves here: one of onStartNew/onGoToStart below
        // does that, once the visitor picks. That pause is also what gives
        // Submit's fetch time to land before anything navigates away.
        function showSuccess(message) {
            if (message) evalSuccessMessageEl.textContent = message;
            evalFormEl.hidden = true;
            evalSuccessEl.hidden = false;
            evalSkipBtn.removeEventListener('click', onSkip);
            evalSubmitBtn.removeEventListener('click', onSubmit);
            evalStartNewBtn.addEventListener('click', onStartNew);
            evalGoToStartBtn.addEventListener('click', onGoToStart);
        }
        function onSkip() {
            showSuccess(window.STRINGS.dojo.evalSkipMessage);
        }
        function onSubmit() {
            var answers = {};
            evalDialogEl.querySelectorAll('fieldset[data-question]').forEach(function (fieldsetEl) {
                var checked = fieldsetEl.querySelector('input[type="radio"]:checked');
                if (checked) answers[fieldsetEl.dataset.question] = Number(checked.value);
            });
            // keepalive: true so the request survives if the visitor navigates
            // away right after pressing Send — without it, the browser can
            // abort an in-flight fetch on navigation, silently dropping the
            // submission.
            fetch('/api/evaluate', {
                method: 'POST',
                keepalive: true,
                body: JSON.stringify({ sessionId: id, answers: answers, feedback: evalFeedbackEl.value.trim() }),
            }).catch(function () {});
            showSuccess(); // keeps the server-rendered "Feedback received…" copy
        }
        function onStartNew() {
            // Reopens the dojo surface fresh, in place — no session
            // identifier in the address, same as UC-04's own outcome, just
            // without a round trip through the start screen.
            evalStartNewBtn.removeEventListener('click', onStartNew);
            evalGoToStartBtn.removeEventListener('click', onGoToStart);
            window.location.href = '/dojo';
        }
        function onGoToStart() {
            evalStartNewBtn.removeEventListener('click', onStartNew);
            evalGoToStartBtn.removeEventListener('click', onGoToStart);
            window.location.href = '/';
        }
        evalSkipBtn.addEventListener('click', onSkip);
        evalSubmitBtn.addEventListener('click', onSubmit);
    }

    /*
    |--------------------------------------------------------------------------
    | TF-01: establish the session (UC-01 / UC-03)
    |--------------------------------------------------------------------------
    */
    function urlSessionId() {
        return new URLSearchParams(window.location.search).get('s');
    }

    function setUrlSessionId(id) {
        var url = new URL(window.location.href);
        url.searchParams.set('s', id);
        window.history.replaceState(null, '', url); // no history entry added (ST-01-2)
    }

    function establishSession() {
        var existing = urlSessionId();
        if (existing) {
            return fetch('/api/session-state?sessionId=' + encodeURIComponent(existing))
                .then(function (res) {
                    if (res.status === 404) return null; // EX-03-1: unknown/expired, fall through to creation
                    if (!res.ok) throw new Error('session-state-failed');
                    return res.json();
                })
                .then(function (data) {
                    if (data) return resumeSession(existing, data);
                    return createSession();
                })
                .catch(function () {
                    setHistoryStatus(window.STRINGS.dojo.installationUnavailable, false);
                });
        }
        return createSession();
    }

    function createSession() {
        return fetch('/api/session', { method: 'POST', body: JSON.stringify({}) })
            .then(function (res) {
                if (!res.ok) throw new Error('create-failed');
                return res.json();
            })
            .then(function (data) {
                sessionId = data.sessionId;
                setUrlSessionId(sessionId);
                retentionEl.hidden = false; // ST-01-3: decision presented, field stays disabled
                showPlaybook(); // brand-new session, no turns yet
                // QR-reply flow: shown before consent too — gives the visitor
                // context for what they're walking into. Never shown on a
                // resumed session (see resumeSession, no equivalent call there).
                if (window.REPLY_QUOTE) showReplyQuote(window.REPLY_QUOTE);
            })
            .catch(function () {
                setHistoryStatus(window.STRINGS.dojo.installationUnavailable, false);
            });
    }

    // Fades #playbook in (0 -> 0.8 opacity per its own CSS). Only ever called
    // for a new/empty session — see createSession and resumeSession below.
    function showPlaybook() {
        playbookEl.classList.add('fading'); // start at 0 opacity while still [hidden]
        playbookEl.hidden = false;
        void playbookEl.offsetWidth; // commit the 0-opacity state before animating away from it
        requestAnimationFrame(function () {
            playbookEl.classList.remove('fading'); // fades 0 -> 0.8
        });
    }

    // Fades #playbook out, then [hidden]s it once the fade finishes so it
    // stops intercepting taps (matches the 300ms transition in dojo.css).
    function hidePlaybook() {
        if (playbookEl.hidden) return; // already hidden, or never shown this session
        playbookEl.classList.add('fading'); // fades 0.8 -> 0
        setTimeout(function () { playbookEl.hidden = true; }, 300);
    }

    function resumeSession(id, data) {
        sessionId = id;
        data.exchanges.forEach(function (exchange) {
            appendTurn('visitor', exchange.visitorContribution);
            appendTurn('sparring', exchange.sparringResponse);
        });
        if (data.exchanges.length > 0) setSessionTitle(data.exchanges[0].visitorContribution);
        // Playbook only belongs on a new/empty session — resuming one with
        // history skips it entirely (stays [hidden], no fade either way).
        if (data.exchanges.length === 0) showPlaybook();
        retentionEl.hidden = data.sessionState !== 'awaiting-decision'; // EX-03-2: still shown if consent was never recorded
        if (data.sessionState !== 'awaiting-decision') revealIdentity(id); // consent already recorded
        applySessionState(data.sessionState, data.turnsRemaining);
        updateDebugPanel({ sessionId: id, origin: data.origin, sessionState: data.sessionState, turnsRemaining: data.turnsRemaining });
    }

    /*
    |--------------------------------------------------------------------------
    | TF-02: record the consent decision
    |--------------------------------------------------------------------------
    |
    | ToS required; retention and projection are real opt-outs.
    |
    */
    var tosCheckbox = document.getElementById('consent-tos');
    var projectionCheckbox = document.getElementById('consent-projection');
    var retentionCheckbox = document.getElementById('consent-retention');
    var confirmButton = document.getElementById('consent-confirm');

    tosCheckbox.addEventListener('change', function () {
        confirmButton.disabled = !tosCheckbox.checked;
    });

    confirmButton.addEventListener('click', function () {
        confirmButton.disabled = true; // guard against a double-tap firing two consent POSTs (and double-sending OPENING_MESSAGE below)
        fetch('/api/session', {
            method: 'POST',
            body: JSON.stringify({
                sessionId: sessionId,
                tosAgreed: tosCheckbox.checked,
                retentionGranted: retentionCheckbox.checked,
                projectionGranted: projectionCheckbox.checked,
            }),
        })
            .then(function (res) {
                if (!res.ok) throw new Error('consent-failed');
                return res.json();
            })
            .then(function (data) {
                retentionEl.hidden = true;
                revealIdentity(sessionId); // consent just recorded — first point alias/avatar may be shown
                applySessionState(data.sessionState, data.turnsRemaining);
                updateDebugPanel({ sessionId: sessionId, origin: data.origin, sessionState: data.sessionState, turnsRemaining: data.turnsRemaining });
                showTitleCard();
                // QR-code-seeded session: auto-send the opening line as the first
                // turn instead of waiting for the visitor to type one. Skipped when
                // a reply quote is pending — that's a more specific signal than a
                // generic numbered opener, and both can't own the same first turn.
                if (window.OPENING_MESSAGE && !pendingReplyQuote && data.sessionState === 'open') {
                    submitContribution(window.OPENING_MESSAGE);
                }
            })
            .catch(function () {
                confirmButton.disabled = false; // let the visitor retry
                setHistoryStatus(window.STRINGS.dojo.consentFailed, false);
            });
    });

    /*
    |--------------------------------------------------------------------------
    | TF-03: submit a contribution
    |--------------------------------------------------------------------------
    */
    function submitContribution(text) {
        hidePlaybook(); // first sent message auto-dismisses the Playbook card
        // No instant raw-text placeholder here (that was setSessionTitle's old job) —
        // #session-title stays "Untitled" until /api/title resolves to a real title
        // (LLM or its trim-fallback), fired once turn 1 succeeds (see 'ok' case below).
        window.SparringSfx.unlock(); // first tap of the session: the user gesture AudioContext needs on iOS Safari
        triggerPunch();

        setComposerEnabled(false);
        // QR-reply flow: prepend the quote into the optimistic bubble (matches
        // what the server will persist) and hide the chip instantly — both
        // happen right here, not on the server round trip, so sending feels
        // immediate. pendingReplyQuote itself stays set until 'ok' confirms
        // the turn landed, so a failure can restore the chip for retry.
        optimisticTurnEl = appendTurn('visitor', pendingReplyQuote ? withQuotePrefix(pendingReplyQuote, text) : text); // pruned on failure (clearOptimisticTurn)
        if (pendingReplyQuote) replyQuoteEl.hidden = true;
        fieldEl.value = ''; // cached in `text`/submittedText below, restored on failure
        sessionStorage.removeItem(DRAFT_KEY); // sent — draft below restores it again on failure
        updateCharRemaining();
        setHistoryStatus(nextThinkingStatus(), true); // in-progress state, shown synchronously (QR-01: within 300ms)

        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, WAIT_MS);

        fetch('/api/contribute', {
            method: 'POST',
            signal: controller.signal,
            // replyToExchangeId: only ever meaningful on the first turn; an
            // undefined value here is dropped by JSON.stringify, so no separate
            // branch is needed once pendingReplyQuote is cleared (see 'ok' below).
            body: JSON.stringify({ sessionId: sessionId, contribution: text, replyToExchangeId: pendingReplyQuote ? pendingReplyQuote.exchangeId : undefined }),
        })
            .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
            .then(function (result) {
                clearTimeout(timeout);
                handleContributionResult(result.data, text);
            })
            .catch(function () {
                clearTimeout(timeout);
                // FA-03-1 grouping: timeout or transport failure is treated as a generation failure.
                handleContributionResult({ status: 'generation-failed' }, text);
            });
    }

    function handleContributionResult(data, submittedText) {
        // sessionState/turnsRemaining are only present on some outcomes (e.g. 'ok');
        // merge them only when present so a rate-limited/rejected response doesn't
        // blank out the last good values via updateDebugPanel's Object.assign.
        var debugInfo = { rateLimitRemaining: data.rateLimitRemaining, generationMs: data.generationMs, moderationReason: data.moderationReason };
        if (data.sessionState !== undefined) debugInfo.sessionState = data.sessionState;
        if (data.turnsRemaining !== undefined) debugInfo.turnsRemaining = data.turnsRemaining;
        updateDebugPanel(debugInfo);
        setHistoryStatus(null); // clear the pending "thinking" entry — once, here, covers every branch below
        switch (data.status) {
            case 'ok':
                optimisticTurnEl = null; // confirmed — stop tracking it, nothing left to prune
                pendingReplyQuote = null; // chip already hidden at submit time (see submitContribution) — landed, nothing left to retry
                appendTurn('sparring', data.exchange.sparringResponse);
                applySessionState(data.sessionState, data.turnsRemaining);
                // Fire-and-forget title generation: doesn't block anything above,
                // fires once per page load. 'title-pending' drives the shimmer
                // (dojo.css) only for the span this fetch is actually in flight —
                // not for "Untitled" in general, which also shows before any
                // submission. Removed in .finally() so it comes off on failure too;
                // any failure (network, malformed body) just leaves #session-title
                // on its default "Untitled" state, no longer shimmering.
                if (!titleRequested) {
                    titleRequested = true;
                    sessionTitleEl.classList.add('title-pending');
                    fetch('/api/title', { method: 'POST', body: JSON.stringify({ sessionId: sessionId }) })
                        .then(function (res) { return res.json(); })
                        .then(function (titleData) { if (titleData && titleData.title) refineSessionTitle(titleData.title); })
                        .catch(function () {})
                        .finally(function () { sessionTitleEl.classList.remove('title-pending'); });
                }
                if (data.sessionState === 'complete') {
                    window.SparringSfx.playSequence('sessionEnd');
                    showEvalDialog(sessionId); // no onClose — finished conversation stays visible
                } else {
                    setComposerEnabled(true);
                    window.SparringSfx.play('parry');
                }
                break;

            case 'turn-limit':
                clearOptimisticTurn();
                applySessionState('complete');
                window.SparringSfx.playSequence('sessionEnd');
                showEvalDialog(sessionId);
                break;

            // Resolved moderation gate: reject-and-edit, session stays open. Every
            // other status (rate-limited, rejected, content-flagged, session-unknown,
            // generation-failed, or anything unrecognised) is a message/behavior lookup
            // — see resolveOutcome() above for the actual decision table.
            default: {
                const outcome = window.SparringDojoOutcome.resolveOutcome(data.status, data.moderationReason);
                clearOptimisticTurn();
                setHistoryStatus(outcome.message, false);
                if (outcome.restoreText) fieldEl.value = submittedText;
                // didn't land — chip was hidden optimistically at submit time (see
                // submitContribution); pendingReplyQuote is still set, so bring it
                // back alongside the restored text for a retry.
                if (outcome.restoreText && pendingReplyQuote) replyQuoteEl.hidden = false;
                if (outcome.enableComposer) setComposerEnabled(true);
                if (outcome.wiggle) triggerWiggle();
                if (outcome.sound) window.SparringSfx.play(outcome.sound);
            }
        }
        if (fieldEl.value) sessionStorage.setItem(DRAFT_KEY, fieldEl.value); // re-persist text restored on failure branches above
        updateCharRemaining();
    }

    fieldEl.addEventListener('input', function () {
        updateCharRemaining();
        submitEl.disabled = fieldEl.disabled || fieldEl.value.trim() === '';
        sessionStorage.setItem(DRAFT_KEY, fieldEl.value);
    });

    // Enter sends; Shift+Enter or Option/Alt+Enter inserts a line break
    // (textarea default already does the line break, so only Enter alone
    // needs intercepting to submit instead).
    fieldEl.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || event.shiftKey || event.altKey) return;
        event.preventDefault();
        submitEl.click();
    });

    composerEl.addEventListener('submit', function (event) {
        event.preventDefault();
        var text = fieldEl.value.trim();
        if (text === '' || fieldEl.disabled) return;
        submitContribution(text);
    });

    fieldEl.value = sessionStorage.getItem(DRAFT_KEY) || ''; // restore draft lost on reload (composer disabled until session resolves)
    updateCharRemaining();
    establishSession();
})();
