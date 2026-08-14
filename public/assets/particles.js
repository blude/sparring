/*
 * SE-01 punch-impact particle burst. A short-lived, full-viewport canvas
 * spawned on demand and removed once every particle has faded — no
 * persistent DOM node, nothing left behind across repeated bursts. Hand-
 * rolled rather than a library: a burst is ~30 lines of canvas math, well
 * under the bar for pulling in a dependency (C-04 has nothing to vendor).
 */
window.SparringParticles = (function () {
    'use strict';

    var PARTICLE_COUNT = 10;
    var LIFETIME_MS = 450;
    var COLORS = ['#d32f2f', '#ff6f60', '#ffd54f'];

    function burst(x, y, flag) {
        if (!window.isJuicyOn(flag || 'punch')) return;

        var canvas = document.createElement('canvas');
        canvas.className = 'particle-burst';
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        document.body.appendChild(canvas);
        var ctx = canvas.getContext('2d');

        var particles = [];
        for (var i = 0; i < PARTICLE_COUNT; i++) {
            var angle = (Math.PI * 2 * i) / PARTICLE_COUNT + Math.random() * 0.5;
            var speed = 2 + Math.random() * 3;
            particles.push({
                x: x,
                y: y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                color: COLORS[i % COLORS.length],
                size: 3 + Math.random() * 3,
            });
        }

        var start = performance.now();
        function frame(now) {
            var t = (now - start) / LIFETIME_MS;
            if (t >= 1) { canvas.remove(); return; }
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(function (p) {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.15; // gravity
                ctx.globalAlpha = 1 - t;
                ctx.fillStyle = p.color;
                ctx.fillRect(p.x, p.y, p.size, p.size);
            });
            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    return { burst: burst };
})();
