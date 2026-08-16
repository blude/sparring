/*
 * Shared mermaid diagram rendering, used by input.js (SE-01) and display.js
 * (SE-02) alike. Only ever called on sparring-authored text — visitor text
 * always stays plain textContent at the call site, never routed through
 * here. This is the one intentional, narrowly-scoped exception to QR-04
 * (SE-01) / QR-05 (SE-02) "no path renders it as markup": a single fenced
 * ```mermaid block is parsed by mermaid.js in 'strict' security mode
 * (sanitizes labels, disables click/script bindings) and only its resulting
 * SVG is inserted as markup. Anything else — no fence, or a fence that
 * fails to parse — falls back to plain textContent, same as before this
 * existed.
 */
window.SparringMermaid = (function () {
    'use strict';

    mermaid.initialize({ startOnLoad: false, securityLevel: 'strict' });

    var counter = 0;

    // Pure string function: matches exactly one ```mermaid fenced block.
    // Returns {before, diagram, after} (before/after may be '') or null if
    // no well-formed fence is present. No DOM here — this is what
    // tests/smoke_mermaid.js exercises directly.
    function extract(text) {
        var match = /```mermaid\r?\n([\s\S]*?)\r?\n```/.exec(text);
        if (!match) return null;
        return {
            before: text.slice(0, match.index).trim(),
            diagram: match[1].trim(),
            after: text.slice(match.index + match[0].length).trim(),
        };
    }

    // Renders `text` into `container` (clearing it first): plain text if no
    // diagram fence, or before-text + SVG diagram + after-text if one is
    // present. Any parse/render failure falls back to the raw original text
    // — never a partial DOM, never a library error surfaced to the visitor.
    function renderInto(container, text) {
        container.textContent = '';
        var parts = extract(text);
        if (!parts) {
            container.textContent = text;
            return;
        }
        if (parts.before) {
            var before = document.createElement('p');
            before.textContent = parts.before;
            container.appendChild(before);
        }
        var diagramEl = document.createElement('div');
        diagramEl.className = 'diagram';
        container.appendChild(diagramEl);

        counter += 1;
        mermaid.render('mermaid-diagram-' + counter, parts.diagram)
            .then(function (result) {
                diagramEl.innerHTML = result.svg; // sole markup-insertion point; input is mermaid-'strict'-parsed SVG only
            })
            .catch(function () {
                container.textContent = text; // malformed diagram: discard the partial DOM built above, show raw text
            });

        if (parts.after) {
            var after = document.createElement('p');
            after.textContent = parts.after;
            container.appendChild(after);
        }
    }

    return { extract: extract, renderInto: renderInto };
})();
