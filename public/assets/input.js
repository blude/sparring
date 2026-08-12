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

    var historyEl = document.getElementById('history');
    var retentionEl = document.getElementById('retention');
    var composerEl = document.getElementById('composer');
    var fieldEl = document.getElementById('contribution');
    var submitEl = document.getElementById('submit');
    var charRemainingEl = document.getElementById('char-remaining');
    var statusEl = document.getElementById('status');
    var avatarBtn = document.getElementById('avatar-btn');
    var avatarPopover = document.getElementById('avatar-popover');
    var avatarAliasEl = document.getElementById('avatar-alias');
    var newSessionBtn = document.getElementById('new-session-btn');
    var titleCardEl = document.getElementById('title-card');

    var sessionId = null;
    var sessionState = null; // 'awaiting-decision' | 'open' | 'complete' | 'failed'
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

    newSessionBtn.addEventListener('click', function () {
        if (window.confirm('Start a new session? Your current session will no longer be shown.')) {
            window.location.href = 'input.php';
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

    // --- rendering (TF-04): all content inserted as text, never markup (QR-04 / display QR-05 counterpart) ---
    function appendTurn(role, text) {
        var el = document.createElement('div');
        el.className = 'turn ' + role;
        el.textContent = text;
        historyEl.appendChild(el);
        historyEl.scrollTop = historyEl.scrollHeight;
    }

    function setStatus(message) {
        statusEl.textContent = message || '';
    }

    // --- juiciness (TODO.md JUICYNESS): purely presentational, layered on
    // top of the flows above, never gates them. Every trigger below checks
    // its own isJuicyOn() flag, so config.php can kill any one of these
    // independently with no code change. ---

    // One-time overlay shown once the consent decision is recorded (UC-01)
    // — not tied to any particular submission, so it never competes with
    // TF-03's in-progress state. On-screen time below matches the two
    // lines' slide-through animations in input.css (line 1 duration + line
    // 2's delay + line 2 duration).
    function showTitleCard() {
        if (!window.isJuicyOn('titleCard')) return;
        titleCardEl.classList.add('collapsed'); // start at 0 height while still [hidden]
        titleCardEl.hidden = false;
        void titleCardEl.offsetWidth; // commit the collapsed layout before animating away from it
        requestAnimationFrame(function () {
            titleCardEl.classList.remove('collapsed'); // grows 0 -> full height
        });
        setTimeout(function () {
            titleCardEl.classList.add('collapsed'); // shrinks back to 0
            setTimeout(function () { titleCardEl.hidden = true; }, 250);
        }, 1250);
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
    }

    function setComposerEnabled(enabled) {
        fieldEl.disabled = !enabled;
        submitEl.disabled = !enabled || fieldEl.value.trim() === '';
    }

    function applySessionState(state, turnsRemaining) {
        sessionState = state;
        if (state === 'complete') {
            setComposerEnabled(false);
            setStatus('This session has reached its limit — thanks for sparring.');
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
            return fetch('api/session_state.php?sessionId=' + encodeURIComponent(existing))
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
                    setStatus('The installation is not accepting sessions right now — reload to retry.');
                });
        }
        return createSession();
    }

    function createSession() {
        return fetch('api/session.php', { method: 'POST', body: JSON.stringify({}) })
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
                setStatus('The installation is not accepting sessions right now — reload to retry.');
            });
    }

    function resumeSession(id, data) {
        sessionId = id;
        data.exchanges.forEach(function (exchange) {
            appendTurn('visitor', exchange.visitorContribution);
            appendTurn('sparring', exchange.sparringResponse);
        });
        retentionEl.hidden = true; // ST-03-3: retention decision is not asked again
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
        fetch('api/session.php', {
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
                setStatus('Could not record that choice — try again.');
            });
    });

    // --- TF-03: submit a contribution ---
    function submitContribution(text) {
        window.SparringSfx.unlock(); // first tap of the session: the user gesture AudioContext needs on iOS Safari
        triggerPunch();

        setComposerEnabled(false);
        setStatus('Sparring is thinking…'); // in-progress state, shown synchronously (QR-01: within 300ms)

        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, WAIT_MS);

        fetch('api/contribute.php', {
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
        switch (data.status) {
            case 'ok':
                appendTurn('visitor', data.exchange.visitorContribution);
                appendTurn('sparring', data.exchange.sparringResponse);
                fieldEl.value = '';
                setStatus('');
                updateCharRemaining();
                applySessionState(data.sessionState, data.turnsRemaining);
                if (data.sessionState !== 'complete') setComposerEnabled(true);
                window.SparringSfx.play('success');
                break;

            case 'rate-limited':
                setStatus('Too many requests — wait a moment and try again.');
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('error');
                break;

            case 'turn-limit':
                applySessionState('complete');
                break;

            case 'rejected':
                setStatus('That message is empty or too long — edit it and try again.');
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('error');
                break;

            case 'content-flagged':
                // Resolved moderation gate: reject-and-edit, session stays open.
                setStatus("That message can't be shown here — edit it and try again.");
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('error');
                break;

            case 'session-unknown':
                setStatus('This session is no longer available — reload to start a new one.');
                break;

            default: // generation-failed, or anything unrecognised
                setStatus('The installation cannot respond right now — try again.');
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                triggerWiggle();
                window.SparringSfx.play('error');
        }
        updateCharRemaining();
    }

    fieldEl.addEventListener('input', function () {
        updateCharRemaining();
        submitEl.disabled = fieldEl.disabled || fieldEl.value.trim() === '';
    });

    composerEl.addEventListener('submit', function (event) {
        event.preventDefault();
        var text = fieldEl.value.trim();
        if (text === '' || fieldEl.disabled) return;
        submitContribution(text);
    });

    updateCharRemaining();
    establishSession();
})();
