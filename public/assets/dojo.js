/*
 * SE-01: no build step (C-04). Implements TF-01 (establish session), TF-02
 * (retention decision), TF-03 (submit), TF-04 (render history) and their
 * alternative flows, including the resolved moderation gap: a flagged
 * contribution is rejected, not the session ended — the field re-enables
 * with the text preserved so the visitor can edit and resubmit.
 */
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

    var sessionId = null;
    var sessionState = null; // 'awaiting-decision' | 'open' | 'complete' | 'failed'
    var statusTurnEl = null; // the one managed "status" entry in #history, if any (see setHistoryStatus)
    var optimisticTurnEl = null; // visitor turn shown ahead of the server response (see submitContribution); pruned on any non-'ok' outcome
    var titleRequested = false; // guards the fire-and-forget /api/title fetch to once per page load (see handleContributionResult)
    var debugPanel = null;
    var lastDebugInfo = {};

    // --- identity: avatar + alias, revealed only once consent is recorded ---
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

    // TODO: "End session" should end the session early and show the
    // feedback/evaluation dialog, not just bounce home. For now it still
    // navigates back to the home screen — confirmation dialog unchanged.
    newSessionBtn.addEventListener('click', function () {
        if (window.confirm('End this session? Your current session will no longer be shown.')) {
            sessionStorage.removeItem(DRAFT_KEY); // ending session should not leave next session's composer pre-filled
            window.location.href = '/';
        }
    });

    // --- debug mode (?debug=1): surfaces values already computed server-side, nothing new to compute ---
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

    // --- rendering (TF-04): all content inserted as text, never markup (QR-04 /
    // display QR-05 counterpart) — except a sparring turn's own ```mermaid fence,
    // see mermaid-render.js for the narrowly-scoped exception. Visitor turns
    // (role === 'visitor') and status turns always stay plain textContent. ---
    function appendTurn(role, text) {
        var el = document.createElement('div');
        el.className = 'turn ' + role;
        if (role === 'sparring') {
            window.SparringMermaid.renderInto(el, text);
        } else {
            el.textContent = text;
        }
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

    // --- juiciness (TODO.md JUICYNESS): purely presentational, layered on
    // top of the flows above, never gates them. Every trigger below checks
    // its own isJuicyOn() flag, so config.php can kill any one of these
    // independently with no code change. ---

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
        charRemainingEl.textContent = (MAX_CHARS - fieldEl.value.length) + ' characters left';
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
        sessionState = state;
        if (state === 'complete') {
            setComposerEnabled(false);
            setHistoryStatus('This session has reached its limit — thanks for sparring.', false);
        } else if (state === 'open') {
            setComposerEnabled(true);
        }
        if (typeof turnsRemaining === 'number') {
            composerEl.dataset.turnsRemaining = String(turnsRemaining);
        }
    }

    // --- TF-01: establish the session (UC-01 / UC-03) ---
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
                    setHistoryStatus('The installation is not accepting sessions right now — reload to retry.', false);
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
            })
            .catch(function () {
                setHistoryStatus('The installation is not accepting sessions right now — reload to retry.', false);
            });
    }

    function resumeSession(id, data) {
        sessionId = id;
        data.exchanges.forEach(function (exchange) {
            appendTurn('visitor', exchange.visitorContribution);
            appendTurn('sparring', exchange.sparringResponse);
        });
        if (data.exchanges.length > 0) setSessionTitle(data.exchanges[0].visitorContribution);
        playbookEl.hidden = data.exchanges.length > 0; // already past the "Instructions" state if there's history
        retentionEl.hidden = data.sessionState !== 'awaiting-decision'; // EX-03-2: still shown if consent was never recorded
        if (data.sessionState !== 'awaiting-decision') revealIdentity(id); // consent already recorded
        applySessionState(data.sessionState, data.turnsRemaining);
        updateDebugPanel({ sessionId: id, origin: data.origin, sessionState: data.sessionState, turnsRemaining: data.turnsRemaining });
    }

    // --- TF-02: record the consent decision (ToS required, retention/projection are real opt-outs) ---
    var tosCheckbox = document.getElementById('consent-tos');
    var projectionCheckbox = document.getElementById('consent-projection');
    var retentionCheckbox = document.getElementById('consent-retention');
    var confirmButton = document.getElementById('consent-confirm');

    tosCheckbox.addEventListener('change', function () {
        confirmButton.disabled = !tosCheckbox.checked;
    });

    confirmButton.addEventListener('click', function () {
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
            })
            .catch(function () {
                setHistoryStatus('Could not record that choice — try again.', false);
            });
    });

    // --- TF-03: submit a contribution ---
    function submitContribution(text) {
        playbookEl.hidden = true; // first sent message auto-dismisses the Playbook card
        // No instant raw-text placeholder here (that was setSessionTitle's old job) —
        // #session-title stays "Untitled" until /api/title resolves to a real title
        // (LLM or its trim-fallback), fired once turn 1 succeeds (see 'ok' case below).
        window.SparringSfx.unlock(); // first tap of the session: the user gesture AudioContext needs on iOS Safari
        triggerPunch();

        setComposerEnabled(false);
        optimisticTurnEl = appendTurn('visitor', text); // shown ahead of the response; pruned on failure (clearOptimisticTurn)
        fieldEl.value = ''; // cached in `text`/submittedText below, restored on failure
        sessionStorage.removeItem(DRAFT_KEY); // sent — draft below restores it again on failure
        updateCharRemaining();
        setHistoryStatus('Consequently sparring…', true); // in-progress state, shown synchronously (QR-01: within 300ms)

        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, WAIT_MS);

        fetch('/api/contribute', {
            method: 'POST',
            signal: controller.signal,
            body: JSON.stringify({ sessionId: sessionId, contribution: text }),
        })
            .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
            .then(function (result) {
                clearTimeout(timeout);
                handleContributionResult(result.res.status, result.data, text);
            })
            .catch(function () {
                clearTimeout(timeout);
                // FA-03-1 grouping: timeout or transport failure is treated as a generation failure.
                handleContributionResult(502, { status: 'generation-failed' }, text);
            });
    }

    function handleContributionResult(httpStatus, data, submittedText) {
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
                } else {
                    setComposerEnabled(true);
                    window.SparringSfx.play('parry');
                }
                break;

            case 'rate-limited':
                clearOptimisticTurn();
                setHistoryStatus('Too many requests — wait a moment and try again.', false);
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('fumble');
                break;

            case 'turn-limit':
                clearOptimisticTurn();
                applySessionState('complete');
                window.SparringSfx.playSequence('sessionEnd');
                break;

            case 'rejected':
                clearOptimisticTurn();
                setHistoryStatus('That message is empty or too long — edit it and try again.', false);
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('fumble');
                break;

            case 'content-flagged': {
                // Resolved moderation gate: reject-and-edit, session stays open.
                // Coarse category only, never the exact reason — see
                // spec/L3-SE-03-backend-service.md's note on this field.
                const reason = data.moderationReason;
                let message = "That message can't be shown here — edit it and try again."; // fallback: classifier failure, real reason unknown
                if (reason === 'contains-personal-information') {
                    message = "That message includes personal information and can't be shown here — edit it and try again.";
                } else if (reason === 'targets-real-person' || reason === 'blocked-term') {
                    message = "That message isn't appropriate for this exhibition — edit it and try again.";
                }
                clearOptimisticTurn();
                setHistoryStatus(message, false);
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('fumble');
                break;
            }

            case 'session-unknown':
                clearOptimisticTurn();
                setHistoryStatus('This session is no longer available — reload to start a new one.', false);
                break;

            default: // generation-failed, or anything unrecognised
                clearOptimisticTurn();
                setHistoryStatus('The installation cannot respond right now — try again.', false);
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('fumble');
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
