/*
 * SE-02: no build step (C-04), no framework. Implements TF-01 (poll),
 * TF-02 (reconcile — the whole reason unchanged items don't redraw),
 * TF-03 (fit to space), TF-04 (render, text-only per QR-05).
 */
(function () {
    'use strict';

    var POLL_MS = window.POLL_INTERVAL_MS || 4000;
    var DEBUG = new URLSearchParams(window.location.search).get('debug') === '1';
    var TRIM_CHARS = 260; // TF-03: character bound per side, both halves preserved
    var wall = document.getElementById('wall');
    var displayed = new Map(); // sessionId -> item, mirrors E-01 of this element

    function trim(text, n) {
        return text.length > n ? text.slice(0, n - 1) + '…' : text;
    }

    // --- TF-01: retrieve display material ---
    function poll() {
        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, Math.max(POLL_MS - 500, 1000));

        fetch('api/display.php', { signal: controller.signal })
            .then(function (res) {
                clearTimeout(timeout);
                if (!res.ok) return null; // FA-01-1: keep current material, no action taken
                return res.json();
            })
            .then(function (data) {
                if (!data || !Array.isArray(data.items)) return; // FS-01-2: discard malformed shape entirely
                reconcile(data.items);
            })
            .catch(function () {
                clearTimeout(timeout);
                // transport failure or timeout: retain what's shown, retry next interval (QR-04)
            });
    }

    // --- TF-02: reconcile against what is displayed ---
    function reconcile(items) {
        var incoming = new Map(items.map(function (item) { return [item.sessionId, item]; }));

        var toAdd = [];
        var toUpdate = [];
        incoming.forEach(function (item, id) {
            var existing = displayed.get(id);
            if (!existing) {
                toAdd.push(item);
            } else if (existing.scenario !== item.scenario
                || existing.visitorContribution !== item.visitorContribution
                || existing.sparringResponse !== item.sparringResponse) {
                toUpdate.push(item);
            }
        });

        var toRemove = [];
        displayed.forEach(function (_item, id) {
            if (!incoming.has(id)) toRemove.push(id);
        });

        if (toAdd.length === 0 && toUpdate.length === 0 && toRemove.length === 0) {
            return; // EX-01-2: identical to what's shown, do nothing
        }

        toRemove.forEach(removeItem);
        toUpdate.forEach(updateItem);
        toAdd.forEach(addItem);

        displayed = incoming;
        reorder(items);
    }

    // --- TF-04: render (add/update/remove/reorder), text only ---
    function buildItemElement(item) {
        var el = document.createElement('article');
        el.className = 'exchange entering';
        el.dataset.sessionId = item.sessionId;

        var scenario = document.createElement('div');
        scenario.className = 'scenario';
        scenario.textContent = item.scenario;

        var visitorName = document.createElement('div');
        visitorName.className = 'visitor-name';
        visitorName.textContent = window.SparringIdentity.alias(item.sessionId); // write-once: invariant per sessionId

        var contribution = document.createElement('p');
        contribution.className = 'contribution';
        contribution.textContent = trim(item.visitorContribution, TRIM_CHARS);

        var response = document.createElement('p');
        response.className = 'response';
        response.textContent = trim(item.sparringResponse, TRIM_CHARS);

        el.append(scenario, visitorName, contribution, response);

        if (DEBUG) {
            var debugTag = document.createElement('span');
            debugTag.className = 'debug-tag';
            debugTag.textContent = item.sessionId.slice(0, 8) + ' · ' + item.origin;
            el.append(debugTag);
        }

        return el;
    }

    function addItem(item) {
        var el = buildItemElement(item);
        wall.appendChild(el);
        requestAnimationFrame(function () { el.classList.remove('entering'); });
    }

    function updateItem(item) {
        var el = wall.querySelector('[data-session-id="' + item.sessionId + '"]');
        if (!el) { addItem(item); return; }
        el.querySelector('.scenario').textContent = item.scenario;
        el.querySelector('.contribution').textContent = trim(item.visitorContribution, TRIM_CHARS);
        el.querySelector('.response').textContent = trim(item.sparringResponse, TRIM_CHARS);
    }

    function removeItem(sessionId) {
        var el = wall.querySelector('[data-session-id="' + sessionId + '"]');
        if (el) el.remove();
    }

    function reorder(items) {
        items.forEach(function (item) {
            var el = wall.querySelector('[data-session-id="' + item.sessionId + '"]');
            if (el) wall.appendChild(el); // re-append in recency order; no-op for already-correct position
        });
    }

    poll();
    setInterval(poll, POLL_MS);
})();
