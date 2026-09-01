// Force-directed render of the Sparring spec requirement network.
//
// Same graph as docs/spec-network.gen.mjs (parsed once in ./spec-graph.mjs),
// drawn as a physics simulation instead of a shelf of blocks:
//   - every requirement ID is a point
//   - every typed trace link is an edge
//   - point radius grows with the number of connections (degree)
//   - only the well-connected points get a floating label
//   - colour = design level by default, or requirement type with --by-type
//
// Fruchterman-Reingold layout: O(n^2) repulsion + per-edge attraction +
// mild gravity, fixed seed and iteration count, so re-running produces a
// byte-identical file (good for committing). No deps, no browser.
//
// Usage:
//   node docs/spec-network-force.gen.mjs [seed] [--by-type]

import { writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { nodes, edges, crossLevel, degree, TIERS, TIER_RANK } from "./spec-graph.mjs";

const OUT_DIR = dirname(fileURLToPath(import.meta.url));
const seed = Number(process.argv.find((a) => /^\d+$/.test(a))) || 20260901;
const BY_TYPE = process.argv.includes("--by-type");

// ---------------------------------------------------------------------------
// deterministic RNG (mulberry32) — same one feature-cloud.gen.mjs uses
// ---------------------------------------------------------------------------
function rng(s) {
  let a = s >>> 0;
  return () => {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

// ---------------------------------------------------------------------------
// colour
// ---------------------------------------------------------------------------
// L1 carries the Sparring brand red (#d32f2f, the wordmark colour — see
// public/assets/arena.css --brand-red); L2 and L3 are pushed well away from
// it and from each other on the wheel — deep teal (~180°) and violet
// (~255°). Red/green is skipped on purpose (colour-blind ambiguity).
const LEVEL_COLOR = { 1: "#d32f2f", 2: "#0f8b8b", 3: "#6b4fc9" }; // L1 / L2 / L3
const LEVEL_NAME = { 1: "L1 Lösung", 2: "L2 System", 3: "L3 Element" };

// One hue per prefix, evenly spaced round the wheel, in TIERS order.
function typeColor(prefix) {
  const i = TIER_RANK.get(prefix) ?? 0;
  const hue = Math.round((i / TIERS.length) * 360);
  return `hsl(${hue} 62% 45%)`;
}
const colorOf = (n) => (BY_TYPE ? typeColor(n.prefix) : LEVEL_COLOR[n.level]);

// ---------------------------------------------------------------------------
// simulation
// ---------------------------------------------------------------------------
// A force sim has nothing to say about a node with no edges, so the 16
// unconnected requirements are held out and parked in a corner instead of
// being flung around by repulsion alone (which distorts the whole fit).
const ALL = [...nodes.values()];
const NODE_LIST = ALL.filter((n) => degree.get(n.nid) > 0); // simulated
const ORPHANS = ALL.filter((n) => degree.get(n.nid) === 0); // parked
const INDEX = new Map(NODE_LIST.map((n, i) => [n.nid, i]));
const N = NODE_LIST.length;
const EDGE_IX = edges.map((e) => [INDEX.get(e.src), INDEX.get(e.dst)]);

const ITERS = 700;

// aspectW/aspectH only set the shape the sim spreads into; the result is
// scaled to fill the real frame afterwards, so nothing pins to a wall.
function simulate(aspectW, aspectH) {
  const rand = rng(seed);
  const S = 1000; // nominal working size
  const W = S * (aspectW / Math.max(aspectW, aspectH));
  const H = S * (aspectH / Math.max(aspectW, aspectH));
  const k = 0.9 * Math.sqrt((W * H) / N); // ideal edge length
  const cutoff = k * 4; // ignore repulsion past this (FR speedup + calmer edges)
  const cx = W / 2;
  const cy = H / 2;

  // start on a jittered disk in the middle
  const x = new Float64Array(N);
  const y = new Float64Array(N);
  for (let i = 0; i < N; i++) {
    const r = (0.15 + 0.7 * rand()) * Math.min(W, H) * 0.45;
    const a = rand() * Math.PI * 2;
    x[i] = cx + Math.cos(a) * r;
    y[i] = cy + Math.sin(a) * r;
  }

  const dx = new Float64Array(N);
  const dy = new Float64Array(N);
  let temp = Math.min(W, H) * 0.1;
  const cool = temp / (ITERS + 1);

  for (let it = 0; it < ITERS; it++) {
    dx.fill(0);
    dy.fill(0);

    // repulsion between every pair (within cutoff)
    for (let i = 0; i < N; i++) {
      for (let j = i + 1; j < N; j++) {
        let vx = x[i] - x[j];
        let vy = y[i] - y[j];
        let d2 = vx * vx + vy * vy;
        if (d2 < 0.01) {
          vx = (i - j) * 0.01 + 0.001;
          vy = 0.01;
          d2 = vx * vx + vy * vy;
        }
        const d = Math.sqrt(d2);
        if (d > cutoff) continue;
        const f = (k * k) / d;
        const ux = (vx / d) * f;
        const uy = (vy / d) * f;
        dx[i] += ux;
        dy[i] += uy;
        dx[j] -= ux;
        dy[j] -= uy;
      }
    }

    // attraction along edges
    for (const [a, b] of EDGE_IX) {
      const vx = x[a] - x[b];
      const vy = y[a] - y[b];
      const d = Math.hypot(vx, vy) || 0.01;
      const f = (d * d) / k;
      const ux = (vx / d) * f;
      const uy = (vy / d) * f;
      dx[a] -= ux;
      dy[a] -= uy;
      dx[b] += ux;
      dy[b] += uy;
    }

    // gravity toward centre — stronger for low-degree nodes (few springs of
    // their own), and anisotropic so the cloud spreads along the frame's
    // long axis instead of balling up
    const gLong = 0.014; // pull along the long axis (lets it spread)
    const gShort = 0.055; // pull along the short axis (keeps it in frame)
    const gx = W >= H ? gLong : gShort;
    const gy = W >= H ? gShort : gLong;
    for (let i = 0; i < N; i++) {
      // low-degree / isolated nodes carry almost no layout information, so
      // pull them in hard instead of letting repulsion fling them to a wall
      const boost = 1 + 6 / (degree.get(NODE_LIST[i].nid) + 1);
      dx[i] += (cx - x[i]) * gx * boost;
      dy[i] += (cy - y[i]) * gy * boost;
    }

    // displace, capped by temperature (no wall clamp — fit happens after)
    for (let i = 0; i < N; i++) {
      const d = Math.hypot(dx[i], dy[i]) || 1;
      const step = Math.min(d, temp);
      x[i] += (dx[i] / d) * step;
      y[i] += (dy[i] / d) * step;
    }
    temp -= cool;
  }
  return { x, y };
}

// ---------------------------------------------------------------------------
// radius from degree
// ---------------------------------------------------------------------------
const maxDeg = Math.max(...degree.values());
function radiusOf(nid) {
  return 3 + Math.sqrt(degree.get(nid)) * 1.7; // deg 0 -> 3, deg 30 -> ~12
}
const LABEL_MIN_DEG = 6; // ~30 of 191 points get a label

// ---------------------------------------------------------------------------
// SVG
// ---------------------------------------------------------------------------
function esc(s) {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function render(orient) {
  const W = orient === "landscape" ? 1600 : 1000;
  const H = orient === "landscape" ? 1000 : 1600;
  const PAD = 64;
  const { x, y } = simulate(W - 2 * PAD, H - 2 * PAD);

  // fit the settled cloud into the padded frame (uniform scale, centred).
  // Use the 1st..99th percentile span, not absolute min/max, so a couple of
  // stray low-degree points can't shrink the whole diagram to fill the frame.
  const span = (arr, lo, hi) => {
    const s = [...arr].sort((a, b) => a - b);
    return [s[Math.floor(lo * (s.length - 1))], s[Math.ceil(hi * (s.length - 1))]];
  };
  const [minX, maxX] = span(x, 0.03, 0.97);
  const [minY, maxY] = span(y, 0.03, 0.97);
  // Stretch X and Y independently to fill the frame. A force blob settles
  // roughly round; a uniform fit then leaves big margins in a non-square
  // page. Positions distort to the page aspect, circles stay circles.
  const sx = (W - 2 * PAD) / (maxX - minX);
  const sy = (H - 2 * PAD - 150) / (maxY - minY); // leave room for the caption block
  const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
  // the ~6% of points outside the percentile span get clamped to the margin
  const px = (i) => clamp(PAD + (x[i] - minX) * sx, 8, W - 8);
  const py = (i) => clamp(PAD + (y[i] - minY) * sy, 8, H - 40);

  const scale = 410 / Math.max(W, H); // longer edge -> 410mm (fits A3)
  const out = [];
  out.push(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${(W * scale).toFixed(1)}mm" height="${
      (H * scale).toFixed(1)
    }mm" viewBox="0 0 ${W} ${H}" font-family="'SF Pro Text', -apple-system, 'Helvetica Neue', Arial, sans-serif">`,
  );
  out.push(`<rect width="${W}" height="${H}" fill="#ffffff"/>`);

  // edges first, under the points
  out.push(`<g stroke="#9a9488" stroke-width="0.7" opacity="0.35">`);
  for (const [a, b] of EDGE_IX) {
    out.push(
      `<line x1="${px(a).toFixed(1)}" y1="${py(a).toFixed(1)}" x2="${px(b).toFixed(1)}" y2="${py(b).toFixed(1)}"/>`,
    );
  }
  out.push(`</g>`);

  // points
  for (let i = 0; i < N; i++) {
    const n = NODE_LIST[i];
    out.push(
      `<circle cx="${px(i).toFixed(1)}" cy="${py(i).toFixed(1)}" r="${radiusOf(n.nid).toFixed(
        1,
      )}" fill="${colorOf(n)}" fill-opacity="0.9" stroke="#ffffff" stroke-width="0.8"/>`,
    );
  }

  // floating labels for the well-connected points: white halo so they read
  // over the edges, a small box-based declutter, and the document key added
  // when the same bare ID is labelled twice (e.g. SE-01 vs SE-02 UI-01)
  const labelled = NODE_LIST.map((n, i) => ({ n, i }))
    .filter(({ n }) => degree.get(n.nid) >= LABEL_MIN_DEG)
    .sort((a, b) => degree.get(b.n.nid) - degree.get(a.n.nid)); // big nodes first
  const idCount = new Map();
  for (const { n } of labelled) idCount.set(n.id, (idCount.get(n.id) || 0) + 1);
  const placed = []; // {x, y} of labels already emitted
  out.push(
    `<g font-size="10" font-weight="600" fill="#1c1a15" paint-order="stroke" stroke="#ffffff" stroke-width="3" stroke-linejoin="round">`,
  );
  for (const { n, i } of labelled) {
    const text = idCount.get(n.id) > 1 ? `${n.id} (${n.key})` : n.id;
    const r = radiusOf(n.nid);
    const tx = px(i) + r + 4;
    let ty = py(i) + 3.5;
    // nudge vertically until it clears earlier labels at a similar x
    for (let guard = 0; guard < 12; guard++) {
      const hit = placed.some((p) => Math.abs(p.x - tx) < 70 && Math.abs(p.y - ty) < 12);
      if (!hit) break;
      ty += 12;
    }
    placed.push({ x: tx, y: ty });
    out.push(`<text x="${tx.toFixed(1)}" y="${ty.toFixed(1)}">${esc(text)}</text>`);
  }
  out.push(`</g>`);

  // unconnected requirements: just name them in the lower-left dead space —
  // a force sim can't place a node with no edges, and 16 loose dots read as
  // noise
  if (ORPHANS.length) {
    const names = ORPHANS.map((n) => `${n.id} (${n.key})`);
    const perLine = 6;
    out.push(
      `<text x="${PAD}" y="${H - 132}" font-size="10" fill="#6b6459">ohne Verknüpfung — keine typisierte Traceability-Beziehung (${ORPHANS.length}):</text>`,
    );
    for (let j = 0; j < names.length; j += perLine) {
      out.push(
        `<text x="${PAD}" y="${H - 116 + (j / perLine) * 14}" font-size="9" fill="#8a8375">${esc(
          names.slice(j, j + perLine).join(",  "),
        )}</text>`,
      );
    }
  }

  // legend
  const keys = BY_TYPE
    ? TIERS.filter((p) => NODE_LIST.some((n) => n.prefix === p)).map((p) => [typeColor(p), p])
    : [1, 2, 3].map((l) => [LEVEL_COLOR[l], LEVEL_NAME[l]]);
  let lx = PAD;
  const lyRow = H - 30;
  out.push(
    `<text x="${PAD}" y="${H - 46}" font-size="11" fill="#262219">` +
      `Sparring Anforderungsnetz — ${nodes.size} Punkte, ${EDGE_IX.length} Traceability-Kanten ` +
      `(${crossLevel} ebenenübergreifend). Punktgröße = Anzahl der Verbindungen; Beschriftung ab Grad ≥ ${LABEL_MIN_DEG}.` +
      `</text>`,
  );
  out.push(`<g font-size="11" fill="#262219">`);
  for (const [c, name] of keys) {
    out.push(`<circle cx="${lx + 5}" cy="${lyRow - 4}" r="5" fill="${c}"/>`);
    out.push(`<text x="${lx + 15}" y="${lyRow}">${esc(String(name))}</text>`);
    lx += 22 + String(name).length * 7.2;
  }
  out.push(`</g>`);

  out.push("</svg>\n");
  return out.join("\n");
}

for (const orient of ["landscape", "portrait"]) {
  const svg = render(orient);
  const file = `spec-network-force.${orient}.svg`;
  writeFileSync(join(OUT_DIR, file), svg);
  console.log(
    `${file.padEnd(34)} ${N} points, ${EDGE_IX.length} edges, colour=${
      BY_TYPE ? "type" : "level"
    }, seed ${seed}`,
  );
}
