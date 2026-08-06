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

    var historyEl = document.getElementById('history');
    var retentionEl = document.getElementById('retention');
    var composerEl = document.getElementById('composer');
    var fieldEl = document.getElementById('contribution');
    var submitEl = document.getElementById('submit');
    var charRemainingEl = document.getElementById('char-remaining');
    var statusEl = document.getElementById('status');

    var sessionId = null;
    var sessionState = null; // 'awaiting-decision' | 'open' | 'complete' | 'failed'

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
        applySessionState(data.sessionState, data.turnsRemaining);
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
                applySessionState(data.sessionState, data.turnsRemaining);
            })
            .catch(function () {
                setStatus('Could not record that choice — try again.');
            });
    });

    // --- TF-03: submit a contribution ---
    function submitContribution(text) {
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
        switch (data.status) {
            case 'ok':
                appendTurn('visitor', data.exchange.visitorContribution);
                appendTurn('sparring', data.exchange.sparringResponse);
                fieldEl.value = '';
                setStatus('');
                updateCharRemaining();
                applySessionState(data.sessionState, data.turnsRemaining);
                if (data.sessionState !== 'complete') setComposerEnabled(true);
                break;

            case 'rate-limited':
                setStatus('Too many requests — wait a moment and try again.');
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                break;

            case 'turn-limit':
                applySessionState('complete');
                break;

            case 'rejected':
                setStatus('That message is empty or too long — edit it and try again.');
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                break;

            case 'content-flagged':
                // Resolved moderation gate: reject-and-edit, session stays open.
                setStatus("That message can't be shown here — edit it and try again.");
                fieldEl.value = submittedText;
                setComposerEnabled(true);
                break;

            case 'session-unknown':
                setStatus('This session is no longer available — reload to start a new one.');
                break;

            default: // generation-failed, or anything unrecognised
                setStatus('The installation cannot respond right now — try again.');
                fieldEl.value = submittedText;
                setComposerEnabled(true);
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
