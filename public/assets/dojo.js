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

/**
 * Pure helper for the composer's segmented char-progress bar: how many of
 * `segments` bars are lit for a field of `len` chars against a `max`-char
 * limit. ceil so the very first typed char lights bar 1 (the bar feels
 * responsive), Math.min so an over-limit paste can't light more bars than
 * exist. No DOM — unit-tested in tests/smoke_dojo.js.
 */
window.SparringDojoCharProgress = {
    fillCount: function (len, max, segments) {
        if (len <= 0) return 0;
        return Math.min(segments, Math.ceil((len / max) * segments));
    },
};

(function () {
    'use strict';

    var MAX_CHARS = window.CONTRIBUTION_MAX_CHARS || 600;
    // Composer char-progress bar: MAX_CHARS mapped onto this many segments.
    var CHAR_SEGMENTS = 10;
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
    var charProgressEl = document.getElementById('char-progress');
    var sessionTitleEl = document.getElementById('session-title');
    var avatarBtn = document.getElementById('avatar-btn');
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
    var confirmDialogEl = document.getElementById('confirm-dialog');
    var confirmCancelBtn = document.getElementById('confirm-cancel');
    var confirmOkBtn = document.getElementById('confirm-ok');

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
        avatarAliasEl.hidden = false; // sits above #session-title, always visible once consent is recorded
    }

    /*
    |--------------------------------------------------------------------------
    | Custom confirm dialog ("End this session?") — replaces window.confirm(),
    | which blocks all further script execution (and every other browser
    | tool) while open. Same shape as showEvalDialog's own Skip/Submit
    | wiring: listeners added on open, removed on whichever action fires.
    |--------------------------------------------------------------------------
    */
    function showConfirmDialog(onConfirm) {
        fadeInGate(confirmDialogEl);

        function close() {
            fadeOutGate(confirmDialogEl);
            confirmCancelBtn.removeEventListener('click', onCancel);
            confirmOkBtn.removeEventListener('click', onOk);
        }
        function onCancel() {
            close();
        }
        function onOk() {
            close();
            onConfirm();
        }
        confirmCancelBtn.addEventListener('click', onCancel);
        confirmOkBtn.addEventListener('click', onOk);
    }

    newSessionBtn.addEventListener('click', function () {
        sessionStorage.removeItem(DRAFT_KEY); // ending session should not leave next session's composer pre-filled
        // Nothing said yet — no session worth confirming the end of, and
        // nothing to evaluate. Skip both dialogs and go straight to the start.
        if (!sessionHasTurns()) {
            window.location.href = '/';
            return;
        }
        showConfirmDialog(function () {
            showEvalDialog(sessionId);
        });
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

    // Build the progress-bar segments once (script is `defer`, so #char-progress
    // is already parsed). aria-valuemax tracks MAX_CHARS so the one config knob
    // stays the single source. prevFilled lets updateCharProgress tell a real
    // keystroke that lit a new bar (juice) from a reset/restore (no juice).
    var charSegments = [];
    for (var s = 0; s < CHAR_SEGMENTS; s++) {
        var segEl = document.createElement('span');
        charProgressEl.appendChild(segEl);
        charSegments.push(segEl);
    }
    charProgressEl.setAttribute('aria-valuemax', String(MAX_CHARS));
    var prevFilled = 0;

    // HOT_AT: once this many bars are lit (>=80% of the limit used) the lit
    // bars pulse (CSS, .pulsing class) — the "running out of room" warning.
    var HOT_AT = Math.ceil(CHAR_SEGMENTS * 0.8);

    function updateCharProgress(animate) {
        var len = fieldEl.value.length;
        var filled = window.SparringDojoCharProgress.fillCount(len, MAX_CHARS, CHAR_SEGMENTS);
        charProgressEl.setAttribute('aria-valuenow', String(len));

        // Segment fill / pulse only change when the lit count changes — a
        // keystroke that doesn't cross a boundary just updates aria + autogrow.
        if (filled !== prevFilled) {
            for (var i = 0; i < CHAR_SEGMENTS; i++) {
                charSegments[i].classList.toggle('filled', i < filled);
                charSegments[i].classList.remove('pulsing');
            }
            // Re-add .pulsing to every lit bar after one reflow, so a bar lit
            // after the warning already engaged shares the others' animation
            // phase instead of starting its own timeline (out-of-sync pulse).
            if (filled >= HOT_AT) {
                void charProgressEl.offsetWidth;
                for (var p = 0; p < filled; p++) {
                    charSegments[p].classList.add('pulsing');
                }
            }

            // Juice only when real typing lit new bars — never on submit-clear,
            // draft-restore, or a rejected-submission text restore (all pass
            // animate=false). One particle burst per newly-lit bar; one shake.
            if (animate && filled > prevFilled) {
                for (var j = prevFilled; j < filled; j++) {
                    var r = charSegments[j].getBoundingClientRect();
                    window.SparringParticles.burst(r.left + r.width / 2, r.top + r.height / 2, 'charProgress');
                }
                if (window.isJuicyOn('charProgress')) {
                    charProgressEl.classList.remove('bumping');
                    void charProgressEl.offsetWidth; // restart the shake if retriggered mid-flight
                    charProgressEl.classList.add('bumping');
                }
                // Cue for every newly-lit bar. One play per update tick (like
                // the shake), not per bar — a multi-bar paste is one "charge".
                // play() self-checks isJuicyOn('sound').
                window.SparringSfx.play('charge');
            }
            prevFilled = filled;
        }

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
    | Always skippable — no field is required. The rating/feedback form itself
    | is answered at most once per session per browser (sessionStorage flag,
    | same idiom as DRAFT_KEY, set on actual completion — see showSuccess
    | below), so a later "End session" press goes straight to the same
    | confirmation screen instead of asking again. Answers/feedback POST
    | fire-and-forget to /api/evaluate — a failed request shouldn't block the
    | visitor from leaving, same as the /api/title call.
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

    // SA-01-8: when the turn limit is hit we don't drop the visitor straight
    // into the feedback dialog — an inline block in #history offers a choice
    // first: keep going (lift the ceiling, server-side) or go to feedback.
    // The deliberate "Finish" button (see newSessionBtn) is unaffected — it
    // still goes straight to showEvalDialog.
    var endChoiceEl = null; // the one inline end-of-session choice block, if shown

    function showTurnLimitChoice() {
        if (endChoiceEl) return; // already shown — don't stack a second one
        setHistoryStatus(null); // drop the "…reached its limit" status line — this block carries that copy itself

        endChoiceEl = document.createElement('div');
        endChoiceEl.className = 'turn turn--endchoice';
        endChoiceEl.setAttribute('role', 'group');

        var copyEl = document.createElement('p');
        copyEl.className = 'endchoice-copy';
        copyEl.textContent = window.STRINGS.dojo.sessionComplete;

        var actionsEl = document.createElement('div');
        actionsEl.className = 'endchoice-actions';
        var extendBtn = document.createElement('button');
        extendBtn.type = 'button';
        extendBtn.className = 'endchoice-extend';
        extendBtn.textContent = window.STRINGS.dojo.turnLimitExtend;
        var feedbackBtn = document.createElement('button');
        feedbackBtn.type = 'button';
        feedbackBtn.className = 'endchoice-feedback';
        feedbackBtn.textContent = window.STRINGS.dojo.turnLimitFeedback;
        actionsEl.appendChild(feedbackBtn); // primary, on top
        actionsEl.appendChild(extendBtn);

        endChoiceEl.appendChild(copyEl);
        endChoiceEl.appendChild(actionsEl);
        historyEl.appendChild(endChoiceEl);
        historyEl.scrollTop = historyEl.scrollHeight;

        function removeBlock() {
            if (endChoiceEl) { endChoiceEl.remove(); endChoiceEl = null; }
        }

        feedbackBtn.addEventListener('click', function () {
            removeBlock();
            showEvalDialog(sessionId);
        });

        extendBtn.addEventListener('click', function () {
            extendBtn.disabled = true;
            feedbackBtn.disabled = true;
            fetch('/api/extend-session', {
                method: 'POST',
                body: JSON.stringify({ sessionId: sessionId }),
            })
                .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
                .then(function (data) {
                    removeBlock();
                    applySessionState('open', data.turnsRemaining); // re-enables the composer
                    window.SparringSfx.play('parry');
                })
                .catch(function () {
                    extendBtn.disabled = false;
                    feedbackBtn.disabled = false;
                    setHistoryStatus(window.STRINGS.dojo.outcomeGenerationFailed, false);
                });
        });
    }

    function showEvalDialog(id) {
        if (!evalDialogEl) {
            window.location.href = '/'; // defensive only — the markup should always be there
            return;
        }
        fadeInGate(evalDialogEl);
        newSessionBtn.disabled = true; // a second "End session" press while this is open would abandon whatever's typed here

        // Shared by Skip and Submit — both end the form the same way, just
        // with different copy (and Submit alone has a request in flight).
        // Neither actually leaves here: one of onStartNew/onGoToStart below
        // does that, once the visitor picks. That pause is also what gives
        // Submit's fetch time to land before anything navigates away.
        //
        // The "don't show again" flag is set HERE, on actual completion —
        // not on open. Setting it on open meant a reload before Skip/Submit
        // (dialog shown, never finished) left it set anyway: the next "End
        // session" press would hit the early-return above and silently fall
        // through to onClose (navigate away) instead of showing the dialog
        // again, with no way for the visitor to ever answer it.
        function showSuccess(message) {
            sessionStorage.setItem(evalShownKey(id), '1');
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

        if (sessionStorage.getItem(evalShownKey(id))) {
            // Already answered in an earlier attempt — reloading doesn't
            // bring the confirmation screen back on its own (it's DOM
            // state, not persisted), so a later "End session" press has
            // nothing to show the visitor but the same two follow-up
            // actions. Skips straight there rather than asking again, and
            // — this is the bug this branch replaces — rather than
            // silently falling through to onClose's hard navigate.
            showSuccess(window.STRINGS.dojo.evalSkipMessage);
            return;
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
                fadeInGate(retentionEl); // ST-01-3: decision presented, field stays disabled
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

    // Fades a .gate-card dialog in (0 -> 1 opacity per its own CSS) — shared
    // by #retention, #eval-dialog and #confirm-dialog. Same choreography as
    // showPlaybook/hidePlaybook below, generalized since all three gate-cards
    // fade identically.
    function fadeInGate(el) {
        el.classList.add('fading'); // start at 0 opacity while still [hidden]
        el.hidden = false;
        void el.offsetWidth; // commit the 0-opacity state before animating away from it
        requestAnimationFrame(function () {
            el.classList.remove('fading'); // fades 0 -> 1
        });
    }

    // Fades a .gate-card dialog out, then [hidden]s it once the fade finishes
    // (matches the 300ms transition in dojo.css).
    function fadeOutGate(el) {
        if (el.hidden) return; // already hidden
        el.classList.add('fading'); // fades 1 -> 0
        setTimeout(function () { el.hidden = true; }, 300);
    }

    // Fades #playbook in (0 -> 0.8 opacity per its own CSS). Called once for a
    // new/empty session (createSession/resumeSession); dismissed for good on
    // the first composer focus and never brought back.
    function showPlaybook() {
        if (!playbookEl.hidden) return; // already up — don't re-trigger the fade
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
        // Reloading a limit-ended session gets the same inline choice as hitting
        // the limit live (SA-01-8) — applySessionState only disables the field.
        if (data.sessionState === 'complete') showTurnLimitChoice();
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
                fadeOutGate(retentionEl);
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
        hidePlaybook(); // no-op for a typed turn (focus already dismissed it); still covers auto-sent openers that never touch the field
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
        updateCharProgress(false); // field just cleared — sync the bar, no juice
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
                    showTurnLimitChoice(); // SA-01-8: inline extend-or-feedback, not the dialog straight away
                } else {
                    setComposerEnabled(true);
                    window.SparringSfx.play('parry');
                }
                break;

            case 'turn-limit':
                clearOptimisticTurn();
                applySessionState('complete');
                window.SparringSfx.playSequence('sessionEnd');
                showTurnLimitChoice(); // SA-01-8: inline extend-or-feedback, not the dialog straight away
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
        updateCharProgress(false); // text restored after a rejected submit — no celebratory burst
    }

    fieldEl.addEventListener('input', function () {
        updateCharProgress(true);
        submitEl.disabled = fieldEl.disabled || fieldEl.value.trim() === '';
        sessionStorage.setItem(DRAFT_KEY, fieldEl.value);
    });

    function sessionHasTurns() {
        return historyEl.querySelector('.turn.visitor, .turn.sparring') !== null;
    }

    // Playbook gets out of the way the instant the visitor engages the
    // composer and stays gone — blurring the field without sending doesn't
    // bring it back.
    fieldEl.addEventListener('focus', hidePlaybook);

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

    // iOS software-keyboard handling. iOS Safari does not shrink 100dvh (or
    // innerHeight, or the CSS layout viewport) when the keyboard opens — only
    // window.visualViewport.height reflects the space above it. Two parts,
    // verified together in the iOS Simulator:
    //   1. Publish --vvh = visualViewport.height so <body> (height:
    //      var(--vvh, 100dvh) in dojo.css) shrinks to the visible band and
    //      the flex column keeps #top-bar / #history / composer inside it.
    //   2. iOS still reveal-scrolls the whole document to lift the focused
    //      composer above the keyboard, which drags #top-bar off the top.
    //      Snap the page back to 0 — it never legitimately scrolls (html/body
    //      overflow:hidden, #history is the only scroller, and element scroll
    //      events don't reach window), so this only ever undoes iOS's shove.
    // Chrome Android already gets a real resize from interactive-widget=
    // resizes-content in the meta. No visualViewport (older engines) — the
    // CSS 100dvh fallback stands. Technique: github.com/mattpilott/ios-chat.
    var vv = window.visualViewport;
    if (vv) {
        var publishViewportHeight = function () {
            if (vv.scale > 1) return; // ignore pinch-zoom (kept for a11y, see dojo.css)
            // Shrinking --vvh shrinks #history's height, which pushes its
            // "bottom" further down while scrollTop stays put — so a history
            // that was pinned to the latest message ends up scrolled short of
            // it when the keyboard opens. Re-pin if it was at the bottom.
            var wasAtBottom = historyEl.scrollHeight - historyEl.scrollTop - historyEl.clientHeight < 40;
            document.documentElement.style.setProperty('--vvh', vv.height + 'px');
            if (wasAtBottom) historyEl.scrollTop = historyEl.scrollHeight;
        };
        vv.addEventListener('resize', publishViewportHeight);
        publishViewportHeight();

        window.addEventListener('scroll', function () {
            if (vv.scale > 1) return; // don't fight a pinch-zoom pan
            if (window.scrollX || window.scrollY) window.scrollTo(0, 0);
        }, { passive: true });
    }

    fieldEl.value = sessionStorage.getItem(DRAFT_KEY) || ''; // restore draft lost on reload (composer disabled until session resolves)
    updateCharProgress(false); // initial draft length — fill the bar, don't fire 10 bursts on load
    establishSession();
})();
