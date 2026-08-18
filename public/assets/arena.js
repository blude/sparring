/*
 * SE-02: no build step (C-04), no framework. Implements TF-01 (poll),
 * TF-02 (reconcile — the whole reason unchanged items don't redraw),
 * TF-03 (fit to space), TF-04 (render, text-only per QR-05 except a
 * sparring response's own ```mermaid fence — see mermaid-render.js).
 *
 * TBC-01: masonry layout, DISPLAY_COLUMNS columns. Each session is placed
 * once (pickColumn) and never moves after — that's what keeps QR-02
 * ("an add leaves every other item undisturbed") true without a packing
 * library: an add only ever touches the bottom of one column.
 */

/**
 * TF-02's diff, pulled out of reconcile() below: displayed (sessionId ->
 * item Map) and the incoming items array in, {toAdd, toUpdate, toRemove,
 * next} out. No DOM, so it's unit-testable without a browser
 * (tests/smoke_arena.js).
 */
window.SparringArenaDiff = {
    diff: function (displayed, items) {
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

        return { toAdd: toAdd, toUpdate: toUpdate, toRemove: toRemove, next: incoming };
    },
};

(function () {
    'use strict';

    var POLL_MS = window.POLL_INTERVAL_MS || 4000;
    var DEBUG = new URLSearchParams(window.location.search).get('debug') === '1';
    var TRIM_CHARS = 500; // TF-03: estimate for a ~900px column, 2 items tall, at 1920px; TBC-05 pending real HE-02 tuning
    var COLUMN_COUNT = window.DISPLAY_COLUMNS || 3;
    var MAX_PER_COLUMN = Math.ceil((window.DISPLAY_ITEM_LIMIT || 9) / COLUMN_COUNT); // QR-06: bounds worst-case column height
    var wall = document.getElementById('wall');
    var columns = buildColumns();
    var displayed = new Map(); // sessionId -> item, mirrors E-01 of this element
    var placement = new Map(); // sessionId -> column element, sticky for the item's lifetime

    function buildColumns() {
        var cols = [];
        for (var i = 0; i < COLUMN_COUNT; i++) {
            var col = document.createElement('div');
            col.className = 'column';
            wall.appendChild(col);
            cols.push(col);
        }
        return cols;
    }

    // Masonry placement: shortest-by-rendered-height among columns under the
    // cap, not shortest-by-count — that's what lets items balance by actual
    // content weight instead of forcing equal rows.
    function pickColumn() {
        var best = null;
        columns.forEach(function (col) {
            if (col.children.length >= MAX_PER_COLUMN) return;
            if (!best || col.offsetHeight < best.offsetHeight) best = col;
        });
        return best || columns[0]; // cap only bites if LIMIT/COLUMNS accounting is off; never leave an item unplaced
    }

    function trim(text, n) {
        return text.length > n ? text.slice(0, n - 1) + '…' : text;
    }

    // Sparring response only (never .contribution, which is visitor text and
    // stays plain-trimmed textContent): a mermaid fence is exempt from
    // TRIM_CHARS (it would otherwise get cut mid-syntax) and rendered via
    // the shared helper; anything else keeps today's trim + textContent.
    function renderResponse(el, text) {
        if (window.SparringMermaid.extract(text)) {
            window.SparringMermaid.renderInto(el, text);
        } else {
            el.textContent = trim(text, TRIM_CHARS);
        }
    }

    // --- TF-01: retrieve display material ---
    function poll() {
        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, Math.max(POLL_MS - 500, 1000));

        fetch('/api/recent-exchanges', { signal: controller.signal })
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
        var result = window.SparringArenaDiff.diff(displayed, items);

        if (result.toAdd.length === 0 && result.toUpdate.length === 0 && result.toRemove.length === 0) {
            return; // EX-01-2: identical to what's shown, do nothing
        }

        result.toRemove.forEach(removeItem);
        result.toUpdate.forEach(updateItem);
        result.toAdd.forEach(addItem);

        displayed = result.next;
    }

    // --- TF-04: render (add/update/remove/reorder), text only ---
    function buildItemElement(item) {
        var el = document.createElement('article');
        // Entrance animation only — never applied to toUpdate (QR-02): the
        // 'entering' class is only ever set here, on a freshly-built node.
        el.className = 'exchange' + (window.isJuicyOn('displayEntrance') ? ' entering' : '');
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

        var response = document.createElement('div');
        response.className = 'response';
        renderResponse(response, item.sparringResponse);

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
        var col = pickColumn();
        placement.set(item.sessionId, col);
        col.appendChild(el); // only this column's height changes; every other column is untouched (QR-02)
        void el.offsetWidth; // force the 'entering' style to commit before scheduling its removal — this
        // whole insertion happens inside poll()'s Promise chain, not a direct user gesture, and a bare
        // rAF there can race ahead of the browser's paint (no forced style flush in between), which
        // makes the entrance land instantly with no visible transition instead of animating in.
        requestAnimationFrame(function () { el.classList.remove('entering'); });
    }

    function updateItem(item) {
        var el = wall.querySelector('[data-session-id="' + item.sessionId + '"]');
        if (!el) { addItem(item); return; }
        el.querySelector('.scenario').textContent = item.scenario;
        el.querySelector('.contribution').textContent = trim(item.visitorContribution, TRIM_CHARS);
        renderResponse(el.querySelector('.response'), item.sparringResponse);
        if (window.isJuicyOn('displayEntrance')) {
            el.classList.remove('updating');
            void el.offsetWidth; // force the removal to commit so re-adding the class retriggers the animation
            el.classList.add('updating');
        }
    }

    function removeItem(sessionId) {
        var el = wall.querySelector('[data-session-id="' + sessionId + '"]');
        if (el) el.remove(); // compacts only its own column, same as baseline single-column removal
        placement.delete(sessionId);
    }

    poll();
    setInterval(poll, POLL_MS);
})();
