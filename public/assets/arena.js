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
                || existing.sparringResponse !== item.sparringResponse
                || existing.exchangeId !== item.exchangeId
                || existing.replyCount !== item.replyCount) {
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
    var lastExchangeCount = null; // skip the DOM write when unchanged, same principle as EX-01-2 below

    // Header clock: date + time, client-rendered from the system clock and
    // window.LOCALE (set in arena.php, otherwise unread by any JS). Minute
    // resolution is enough on a wall no one watches second-by-second.
    function updateClock() {
        // dateStyle/timeStyle presets can't be hand-tuned, and German's
        // 'short' month otherwise renders with a trailing period ("22. Aug.
        // 2026") — formatToParts lets us drop just that one, keeping the
        // day's own period (which German date style does want).
        var parts = new Intl.DateTimeFormat(window.LOCALE, {
            day: 'numeric', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(new Date());
        document.getElementById('clock').textContent = parts
            .map(function (p) { return p.type === 'month' ? p.value.replace(/\.$/, '') : p.value; })
            .join('');
    }
    updateClock();
    setInterval(updateClock, 30000);

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

    // QR-reply flow: links a wall item's AI response to /dojo?r=<exchangeId>
    // and shows its reply counter. `el.dataset.exchangeId` lets updateItem skip
    // rebuilding the QR SVG on a poll where only the counter changed.
    function renderReply(el, qrEl, countEl, item) {
        if (el.dataset.exchangeId !== String(item.exchangeId)) {
            el.dataset.exchangeId = String(item.exchangeId);
            qrEl.textContent = '';
            var link = document.createElement('a');
            link.href = location.origin + '/dojo?r=' + item.exchangeId;
            link.target = '_blank';
            qrEl.appendChild(link);
            window.SparringQr.renderInto(link, link.href);
        }
        // Reaction-style pill: glove icon + bare count, hidden at 0 (EX-01-3).
        // The full sentence stays as an aria-label — sighted users get the
        // glove as the "replies" cue, screen readers still hear a sentence.
        // Icon is a static child built once in buildItemElement; only the
        // number text updates here, so we never clobber it.
        countEl.hidden = item.replyCount === 0;
        countEl.setAttribute('aria-label', window.REPLY_COUNT_LABEL.replace('{n}', String(item.replyCount)));
        countEl.querySelector('.reply-count-number').textContent = String(item.replyCount);
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
                if (typeof data.exchangeCount === 'number' && data.exchangeCount !== lastExchangeCount) {
                    lastExchangeCount = data.exchangeCount;
                    document.getElementById('exchange-count').textContent =
                        window.EXCHANGE_COUNT_LABEL.replace('{n}', String(data.exchangeCount));
                }
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

        // div, not <p> — a <p> can't validly contain visitorName/contributionText's
        // block children (the browser would silently close the <p> early).
        // contribution-text mirrors response-text: its own child so a plain
        // textContent update (below) never wipes the nested visitor-name.
        var contribution = document.createElement('div');
        contribution.className = 'contribution';
        var contributionText = document.createElement('div');
        contributionText.className = 'contribution-text';
        contributionText.textContent = trim(item.visitorContribution, TRIM_CHARS);
        contribution.append(visitorName, contributionText);

        var response = document.createElement('div');
        response.className = 'response';
        // Actual rendered content lives in its own child, never touched
        // directly — renderResponse/mermaid's renderInto both wipe whatever
        // container they're given, so the QR/reply-count badges below live
        // as .response's *siblings* to that child, not inside it (they'd
        // get erased on every re-render otherwise).
        var responseText = document.createElement('div');
        responseText.className = 'response-text';
        renderResponse(responseText, item.sparringResponse);

        var replyQr = document.createElement('div');
        replyQr.className = 'reply-qr';
        var replyCount = document.createElement('div');
        replyCount.className = 'reply-count';
        var replyCountIcon = document.createElement('span');
        replyCountIcon.className = 'reply-count-icon';
        var replyCountNumber = document.createElement('span');
        replyCountNumber.className = 'reply-count-number';
        replyCount.append(replyCountIcon, replyCountNumber);
        // Both badges nested in .response (not responseText) so their
        // absolute position anchors to the bubble, not the card.
        response.append(responseText, replyQr, replyCount);
        renderReply(el, replyQr, replyCount, item);

        el.append(scenario, contribution, response);

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
        el.querySelector('.contribution-text').textContent = trim(item.visitorContribution, TRIM_CHARS);
        renderResponse(el.querySelector('.response-text'), item.sparringResponse);
        renderReply(el, el.querySelector('.reply-qr'), el.querySelector('.reply-count'), item);
        if (window.isJuicyOn('displayEntrance')) {
            el.classList.remove('updating');
            void el.offsetWidth; // force the removal to commit so re-adding the class retriggers the animation
            el.classList.add('updating');
        }
    }

    function removeItem(sessionId) {
        var el = wall.querySelector('[data-session-id="' + sessionId + '"]');
        if (el) el.remove(); // compacts only its own column, same as baseline single-column removal
    }

    poll();
    setInterval(poll, POLL_MS);
})();
