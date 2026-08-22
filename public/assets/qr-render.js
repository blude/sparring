/*
 * Thin wrapper around the vendored qrcode-generator (public/assets/vendor/
 * qrcode-generator.min.js — kazuhikoarase, MIT, unmodified) for arena.js (SE-02).
 * Builds the <svg> itself via createElementNS + one <rect> per dark module —
 * no innerHTML, same "no path renders untrusted markup" discipline
 * mermaid-render.js follows for QR-05 (the target text here is always a
 * server-built /dojo?r=<id> URL, never visitor-authored, but the
 * discipline costs nothing to keep consistent).
 */
window.SparringQr = (function () {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';

    // The vendored library needs an explicit typeNumber (no auto-sizing) —
    // try the smallest that fits, per the library's own documented usage.
    // 'M' (~15% error correction) is the common default for a scannable code
    // at exhibition viewing distance without unnecessarily bloating modules.
    function buildQr(text) {
        for (var typeNumber = 1; typeNumber <= 40; typeNumber++) {
            try {
                var qr = qrcode(typeNumber, 'M');
                qr.addData(text);
                qr.make();
                return qr;
            } catch (e) {
                // 'code length overflow' at this typeNumber — try the next size up.
            }
        }
        return null; // text too long for any QR version; caller leaves the container empty
    }

    // Renders `text` (a URL) into `container` (clearing it first) as an
    // inline SVG QR code, scaled to fill via viewBox — actual on-screen size
    // is entirely up to the container's CSS.
    function renderInto(container, text) {
        container.textContent = '';
        var qr = buildQr(text);
        if (!qr) return;

        var count = qr.getModuleCount();
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 ' + count + ' ' + count);
        svg.setAttribute('shape-rendering', 'crispEdges');

        var background = document.createElementNS(SVG_NS, 'rect');
        background.setAttribute('width', String(count));
        background.setAttribute('height', String(count));
        background.setAttribute('fill', '#fff');
        svg.appendChild(background);

        for (var row = 0; row < count; row++) {
            for (var col = 0; col < count; col++) {
                if (!qr.isDark(row, col)) continue;
                var rect = document.createElementNS(SVG_NS, 'rect');
                rect.setAttribute('x', String(col));
                rect.setAttribute('y', String(row));
                rect.setAttribute('width', '1');
                rect.setAttribute('height', '1');
                rect.setAttribute('fill', '#000');
                svg.appendChild(rect);
            }
        }

        container.appendChild(svg);
    }

    return { renderInto: renderInto };
})();
