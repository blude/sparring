/*
 * Start screen easter egg: tap either glove to bounce it and fire punch
 * juice (sound + particles); 5 taps across either glove within 1s pops
 * #egg-dialog. Decorative only — start.php isn't a modeled UI surface, see
 * CLAUDE.md's note on unmodeled static/decorative pages.
 */

/**
 * Pure tap-window logic behind the 5-taps-in-1s trigger. No DOM, so it's
 * unit-testable without a browser (tests/smoke_start.js). timestamps is the
 * caller's running list of tap times (ms); returns the list trimmed to the
 * last windowMs plus this tap, and whether that trim just reached the
 * threshold (which also resets the returned list to empty, so a further
 * tap starts a fresh count rather than re-triggering every tap after 5).
 */
window.SparringStartTaps = {
    recordTap: function (timestamps, now, windowMs, threshold) {
        windowMs = windowMs || 1000;
        threshold = threshold || 5;
        var kept = timestamps.filter(function (t) { return now - t < windowMs; });
        kept.push(now);
        if (kept.length >= threshold) {
            return { timestamps: [], triggered: true };
        }
        return { timestamps: kept, triggered: false };
    },
};

(function () {
    'use strict';

    var gloves = document.querySelectorAll('.glove-bounce');
    var dialog = document.getElementById('egg-dialog');
    var dismissBtn = document.getElementById('egg-dismiss');
    var tapTimestamps = [];

    gloves.forEach(function (glove) {
        glove.addEventListener('click', function (event) {
            window.SparringSfx.unlock(); // first tap of the session: user gesture AudioContext needs on iOS Safari
            window.SparringSfx.play('punch');
            window.SparringParticles.burst(event.clientX, event.clientY, 'punch');

            // Restart the bounce even mid-animation: remove the class, force
            // a reflow, then re-add it — a bare re-add on an already-.bouncing
            // element is a no-op since the class never left.
            glove.classList.remove('bouncing');
            void glove.offsetWidth;
            glove.classList.add('bouncing');

            var result = window.SparringStartTaps.recordTap(tapTimestamps, Date.now());
            tapTimestamps = result.timestamps;
            if (result.triggered) {
                dialog.hidden = false;
            }
        });
    });

    dismissBtn.addEventListener('click', function () {
        dialog.hidden = true;
    });
})();
