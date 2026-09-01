// Static network-diagram generator for the Sparring spec requirements.
//
// Reads the requirement graph from ./graph.mjs (see there for what counts
// as a node / an edge) and emits two print-ready A3 SVGs (vector,
// selectable text, no raster, no browser, no deps):
//   - network.landscape.svg  (A3 landscape, blocks flow wider)
//   - network.portrait.svg   (A3 portrait, blocks flow narrower)
//
// Labels are the bare ID, per request.
//
// Layout is a deterministic shelf-packer over per-(document, prefix) blocks,
// ordered L1 -> L2 -> L3, so re-running produces a byte-identical file
// (good for committing). No font-metrics engine headless, so node width is
// derived from the ID string length with generous padding.
//
// Usage:  node docs/spec-network/network.gen.mjs

import { writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { FILES, TIERS, nodes, edges, crossLevel } from "./graph.mjs";

const OUT_DIR = dirname(fileURLToPath(import.meta.url));

// ---------------------------------------------------------------------------
// layout: per-prefix blocks, shelf-packed into the page
// ---------------------------------------------------------------------------
// L1 carries the Sparring brand red (#d32f2f, the wordmark colour — see
// public/assets/arena.css --brand-red); L2 and L3 are pushed well away from
// it and from each other on the wheel — deep teal (~180°) and violet
// (~255°). Red/green is skipped on purpose (colour-blind ambiguity).
const LEVEL_TINT = { 1: "#fbe9e9", 2: "#e2f2f2", 3: "#eeeafb" };
const LEVEL_EDGE = { 1: "#c0504a", 2: "#2f8080", 3: "#6a5fbf" };
const NODE_FILL = "#ffffff";
const NODE_STROKE = "#3a3530";
const INK = "#262219";

const FS_NODE = 9; // node label font size (px)
const FS_BLOCK = 12; // block title font size
const NODE_H = 17;
const NODE_VGAP = 5;
const NODE_HGAP = 5;
const BLOCK_PAD = 9;
const BLOCK_TITLE_H = 18;
const BLOCK_GAP = 16;
const PAGE_MARGIN = 30;
const LEGEND_H = 46;

// node width: bare IDs are short and near-monospace; pad generously
function nodeWidth(id) {
  return Math.max(40, 10 + id.length * 6.4);
}

// Build one block per (document, prefix) pair, ordered by document then by
// tier. L3 IDs repeat across the SE-0x docs, so the block title carries the
// document key to keep the four "TF" groups (etc.) told apart.
function buildBlocks(maxBlockW) {
  const groups = new Map(); // "key prefix" -> node[]
  for (const n of nodes.values()) {
    const gk = `${n.key} ${n.prefix}`;
    if (!groups.has(gk)) groups.set(gk, []);
    groups.get(gk).push(n);
  }
  const blocks = [];
  for (const { key } of FILES) {
    for (const prefix of TIERS) {
      const list = groups.get(`${key} ${prefix}`);
      if (!list) continue;
      list.sort((a, b) => a.id.localeCompare(b.id, "en", { numeric: true }));
      const cellW = Math.max(...list.map((n) => nodeWidth(n.id)));
      // columns: roughly square-ish, but never wider than the page allows
      const fitCols = Math.max(
        1,
        Math.floor((maxBlockW - 2 * BLOCK_PAD + NODE_HGAP) / (cellW + NODE_HGAP)),
      );
      const cols = Math.min(fitCols, Math.max(2, Math.ceil(Math.sqrt(list.length * 1.4))));
      const rows = Math.ceil(list.length / cols);
      const w = 2 * BLOCK_PAD + cols * cellW + (cols - 1) * NODE_HGAP;
      const h = BLOCK_TITLE_H + 2 * BLOCK_PAD + rows * NODE_H + (rows - 1) * NODE_VGAP;
      const title = list[0].level === 3 ? `${key} · ${prefix}` : prefix;
      blocks.push({ key, prefix, title, level: list[0].level, list, cols, cellW, w, h });
    }
  }
  return blocks;
}

// Shelf-pack blocks left->right, wrap to a new shelf when the page width
// is exceeded. Assigns absolute x/y to every node.
function packAndPlace(blocks, pageW) {
  const usableW = pageW - 2 * PAGE_MARGIN;
  let x = PAGE_MARGIN;
  let y = PAGE_MARGIN;
  let shelfH = 0;
  const pos = new Map(); // id -> { x, y, w, h } (node centre-able rect)

  for (const b of blocks) {
    if (x > PAGE_MARGIN && x + b.w > PAGE_MARGIN + usableW) {
      x = PAGE_MARGIN;
      y += shelfH + BLOCK_GAP;
      shelfH = 0;
    }
    b.x = x;
    b.y = y;
    // place nodes within the block grid
    b.list.forEach((n, i) => {
      const c = i % b.cols;
      const r = Math.floor(i / b.cols);
      const nx = b.x + BLOCK_PAD + c * (b.cellW + NODE_HGAP);
      const ny = b.y + BLOCK_TITLE_H + BLOCK_PAD + r * (NODE_H + NODE_VGAP);
      pos.set(n.nid, { x: nx, y: ny, w: b.cellW, h: NODE_H });
    });
    x += b.w + BLOCK_GAP;
    shelfH = Math.max(shelfH, b.h);
  }
  const contentH = y + shelfH + BLOCK_GAP;
  return { pos, contentH };
}

// ---------------------------------------------------------------------------
// SVG emit
// ---------------------------------------------------------------------------
function esc(s) {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function centre(rect) {
  return { x: rect.x + rect.w / 2, y: rect.y + rect.h / 2 };
}

function render(orient) {
  // The two orientations are two *packings* of the same graph, not one
  // rotated: a wide pack width lays the blocks out in few tall shelves
  // (landscape), a narrow one wraps them into many shelves (portrait).
  const pageW = orient === "landscape" ? 1600 : 760;

  const blocks = buildBlocks(pageW - 2 * PAGE_MARGIN);
  const { pos, contentH } = packAndPlace(blocks, pageW);
  const pageH = Math.round(contentH + LEGEND_H);

  // Print size: scale so the longer edge is 410mm (fits an A3 sheet with
  // a margin), keeping the natural aspect of the packing.
  const scale = 410 / Math.max(pageW, pageH);

  const out = [];
  out.push(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${(pageW * scale).toFixed(1)}mm" height="${
      (pageH * scale).toFixed(1)
    }mm" viewBox="0 0 ${pageW} ${pageH}" font-family="'SF Pro Text', -apple-system, 'Helvetica Neue', Arial, sans-serif">`,
  );
  out.push(
    `<defs><marker id="arw" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="#3a3530"/></marker></defs>`,
  );
  out.push(`<rect width="${pageW}" height="${pageH}" fill="#ffffff"/>`);

  // blocks (tinted card + title)
  for (const b of blocks) {
    out.push(
      `<rect x="${b.x}" y="${b.y}" width="${b.w}" height="${b.h}" rx="6" fill="${
        LEVEL_TINT[b.level]
      }" stroke="#d8d2c8"/>`,
    );
    out.push(
      `<text x="${b.x + BLOCK_PAD}" y="${
        b.y + BLOCK_TITLE_H - 2
      }" font-size="${FS_BLOCK}" font-weight="700" fill="${INK}">${b.title}</text>`,
    );
  }

  // edges (drawn between block cards, under the node boxes, low opacity)
  for (const e of edges) {
    const a = pos.get(e.src);
    const z = pos.get(e.dst);
    if (!a || !z) continue;
    const p = centre(a);
    const q = centre(z);
    const col = LEVEL_EDGE[nodes.get(e.src).level];
    // gentle quadratic bow so parallel long links don't fully overlap
    const mx = (p.x + q.x) / 2;
    const my = (p.y + q.y) / 2;
    const dx = q.x - p.x;
    const dy = q.y - p.y;
    const len = Math.hypot(dx, dy) || 1;
    const bow = Math.min(40, len * 0.12);
    const cx = mx + (-dy / len) * bow;
    const cy = my + (dx / len) * bow;
    out.push(
      `<path d="M${p.x.toFixed(1)} ${p.y.toFixed(1)} Q${cx.toFixed(1)} ${cy.toFixed(
        1,
      )} ${q.x.toFixed(1)} ${q.y.toFixed(1)}" fill="none" stroke="${col}" stroke-width="1" opacity="0.28" marker-end="url(#arw)"/>`,
    );
  }

  // nodes
  for (const n of nodes.values()) {
    const r = pos.get(n.nid);
    if (!r) continue;
    out.push(
      `<rect x="${r.x}" y="${r.y}" width="${r.w}" height="${r.h}" rx="3" fill="${NODE_FILL}" stroke="${NODE_STROKE}" stroke-width="0.9"/>`,
    );
    out.push(
      `<text x="${r.x + r.w / 2}" y="${
        r.y + r.h / 2 + 3.2
      }" font-size="${FS_NODE}" text-anchor="middle" fill="${INK}">${esc(n.id)}</text>`,
    );
  }

  // legend
  const ly = pageH - LEGEND_H + 14;
  out.push(
    `<text x="${PAGE_MARGIN}" y="${ly}" font-size="11" fill="${INK}">` +
      `Sparring Anforderungsnetz — ${nodes.size} Knoten, ${edges.length} Traceability-Kanten ` +
      `(${crossLevel} ebenenübergreifend). Kanten: Satisfies / Realises / Refines / Achieves / Supports / Applies to / Implements.` +
      `</text>`,
  );
  out.push(
    `<text x="${PAGE_MARGIN}" y="${ly + 16}" font-size="11" fill="#6b6459">` +
      `Pfeil zeigt auf das nachverfolgte übergeordnete Element. Kartenfarbe = Entwurfsebene (L1 Lösung / L2 System / L3 Element). Beschriftungen nur IDs.` +
      `</text>`,
  );

  out.push("</svg>\n");
  return out.join("\n");
}

for (const orient of ["landscape", "portrait"]) {
  const svg = render(orient);
  const file = `network.${orient}.svg`;
  writeFileSync(join(OUT_DIR, file), svg);
  console.log(
    `${file.padEnd(28)} ${nodes.size} nodes, ${edges.length} edges (${crossLevel} cross-level)`,
  );
}
