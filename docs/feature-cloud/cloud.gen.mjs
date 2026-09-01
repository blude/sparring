// Static word-cloud generator for the Sparring feature inventory.
//
// Emits print-ready A3 SVG (vector, selectable text, no raster) with a
// hand-rolled Archimedean-spiral layout — no browser, no canvas, no deps.
// Two clouds from the same source as docs/features.md:
//   - words:  the 197 feature titles broken into terms, sized by recurrence
//   - titles: each title kept whole, short punchy ones sized bigger
//
// Usage:
//   node docs/feature-cloud/cloud.gen.mjs [seed] [--landscape]
//   (default is A3 portrait, 297×420mm — it packs the long title strings
//    tighter than landscape does)
//
// Layout is deterministic for a given seed, so re-running produces a
// byte-identical file (good for committing). Try a few seeds and keep the
// one that composes best:  node docs/feature-cloud/cloud.gen.mjs 7
//
// Text width is estimated from a rough per-glyph advance table (no font
// metrics engine available headless); collision padding absorbs the error.
// The SVG asks for 'SF Pro Text' with a plain sans-serif fallback — the
// width table is estimated generously, so any similar sans renders without
// clipping or overlap.

import { writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const OUT_DIR = dirname(fileURLToPath(import.meta.url));

// ---------------------------------------------------------------------------
// source data: the 197 feature titles, verbatim (backticks stripped)
// ---------------------------------------------------------------------------
const TITLES = [
  "Scan-to-spar entry","Single scrolling surface","Turn submission","Live character allowance",
  "Remaining-turns indicator","\"Sparring is thinking…\" status","Draft persistence","Playbook card",
  "Text-only rendering","Composer disclaimer","Holds a position instead of answering","Named persona (\"Popov\")",
  "Four-stage reasoning engine, run every turn","14-move vocabulary","Move variation","Refuses premature closure",
  "Engages the specific opening","Convergence near the turn cap","Deliberate fallibility","Reply-language matching",
  "Design-tradition grounding","Short register","Banned vocabulary + no meta-commentary",
  "Adversarial pressure met with more challenge","Never claims to be \"an AI following a policy\"",
  "Never reveals/quotes/paraphrases its own prompt","Meta-question handling","Sustained-hostility de-escalation",
  "Tangential-swerve dodge","Single central turn function","Cheap-reject gate order","No partial exchange ever stored",
  "Prior exchanges as context","Bounded generation","Retry policy","Grouped failure condition",
  "Auto-restart, no in-memory state","Gate at submission, not at display","Turn rejected, session continues",
  "Two-stage check","Fails closed","Adversarial-vs-target distinction","No off-topic filter",
  "Classifier input treated as data","Coarse visitor-facing hint","Per-origin rate limit","Hashed origin only",
  "In-place window reset","Session TTL","Limits enforced on every path","Orphaned-session pruning",
  "Polling, not push","Reconcile diff (no flicker)","Masonry layout","Exchange pair as the unit",
  "Uniform item shape","Fit-to-space truncation","Recency eviction","Never-empty surface",
  "Backend outage is invisible","Header","Entry / update motion","No sound on the wall","Generated nickname",
  "Generated avatar emoji","Same derivation on both clients","Gated on consent","Identity popover",
  "Session id in the URL","Resume after interruption","Unknown / expired id",
  "Deliberate \"End session\" / \"New session\"","Guessable-resistant ids",
  "Three independent decisions, made once up front","No preselected checkbox","Answerable without scrolling",
  "Projection ≠ retention","Decline-to-participate ends the flow","No device-side consent copy",
  "Nothing identifying reaches the wall","Consented-only export","Opening-prompt QR codes",
  "Reply-to-the-wall QR codes","Reply-quote card","Reply counters on the wall",
  "Curriculum-grounding trigger awareness","AI-refined session title","Best-effort, non-blocking","Two consumers",
  "Used as the de-facto session identifier on the wall","Context line above each exchange",
  "Derivation source recorded","End-of-session feedback prompt","Always fully skippable",
  "Answered at most once per session","Glove-rating widget","Fire-and-forget submit",
  "Server-side answer filtering","Separate session_evaluations table","Static domain grounding",
  "Turn-1 retrieval","Vocabulary-restricted matching","Degrades to nothing","FTS5 index",
  "General free-text search","Known gaps documented","Global + per-effect kill switches",
  "\"READY? / GET SET. / SPAR!\" title card","Punch animation + particle burst on submit",
  "Procedural sound effects","Wiggle / shake on a rejected submission","Display entrance animation",
  "Reduced-motion respect","Compositor-only","sfx-debug.php","Shipped then reverted: Mermaid diagram rendering",
  "Two UI locales","First-visit language match","Explicit EN/DE switcher","AI reply language is separate",
  "German pilot seeds","Catalogue-parity + fallback","SE-01 web app manifest","SE-02 web app manifest",
  "Arena touch lockdown","iOS status-bar / splash tuning","No service worker / offline shell","Cache-busting",
  "Structured transcript import","Per-transcript validation","Origin fixed at creation","Optional transcript title",
  "Optional reply_to link","No administrative interface, by constraint","bin/export.php","bin/backup_db.php",
  "bin/reset_db.php","bin/prune_orphaned_sessions.php",
  "bin/import_curriculum.php / clear_curriculum.php / probe_curriculum.php","bin/deploy.sh",
  "Confirmation-flag guards instead of auth","Provider dispatch","OpenAI-compatible / local models",
  "Per-role models","Prompt caching","Extended thinking disabled","Pure helper functions",
  "Credential never leaves the host","All logic server-side","Text-only rendering on both clients",
  "QR SVGs built element-by-element","Prompt-injection defence","Generic error on infrastructure fault",
  "Whitelist validation at trust boundaries","Docroot separation","Every visitor-facing failure is stated",
  "Router 404 / 500 page","Trailing-slash canonicalisation","Wall never clears on backend loss",
  "Title / evaluation failures are silent","?debug=1 panel on /dojo and /arena",
  "Cheap extra fields always returned","Multi-turn simulated-visitor harness","Six scenarios",
  "3 reps + pass-rate reporting","Mechanical grading","LLM-judge grading",
  "Programmatic move-repetition check","Baseline arm","bin/summarize_sparring.php",
  "Assert-based smoke tests, no framework","PHP suites","Node suites","Committed curriculum fixtures",
  "php -l","Conventional Commits hook","Four-level framework","AsciiDoc → HTML build",
  "The system prompt is modelled as a first-class element","Deliberate non-modelling","Prompt changelog",
  "Landing page (/)","Playful start + display design","Philosophy page (/philosophy)","Credits page (/credits)",
  "Privacy & Terms pages","Shared page chrome","Open Graph / Twitter Card","Custom typography",
  "Theme colour + favicon + apple-touch-icon","ASCII-art source banner","Custom in-page confirm dialog",
  "Everything is expendable and capped","Runs unattended for days","The friction is the exhibit",
  "GDPR on public user-generated content","Legible from across the room",
];

// ---------------------------------------------------------------------------
// tokeniser: title text -> weighted term list  (mirrors the live artifact)
// ---------------------------------------------------------------------------
const STOP = new Set(
  ("a an the and or of to in on at by for is are be as it its own with without no not "
    + "each every one up front made most both this that these those from into than then so about above under "
    + "over out off after before per via instead when where what how who whom which whatever only just still "
    + "more less few many any all some none other another same such being been has have had do does did will "
    + "would can could may might must should shall you they we them their your my me us if but because while "
    + "once also yet vs se eg ie etc aka new two three four use used using within across onto").split(" ")
);
const MERGE = {
  clients: "client", models: "model", codes: "code", ids: "id", exchanges: "exchange", decisions: "decision",
  suites: "suite", scenarios: "scenario", locales: "locale", titles: "title", guards: "guard", failures: "failure",
  checks: "check", fields: "field", gaps: "gap", consumers: "consumer", reps: "rep", seeds: "seed",
  sessions: "session", turns: "turn", prompts: "prompt", limits: "limit", pages: "page", rules: "rule",
  moves: "move", tests: "test", clears: "clear",
};
const norm = (w) => MERGE[w.toLowerCase()] || w.toLowerCase();

function tokenCounts(titles) {
  const counts = Object.create(null);
  for (const t of titles) {
    for (const raw of t.replace(/[’']/g, "").split(/[^a-z0-9]+/i)) {
      if (!raw || /^\d+$/.test(raw)) continue;
      const w = norm(raw);
      if (w.length < 2 || STOP.has(w)) continue;
      counts[w] = (counts[w] || 0) + 1;
    }
  }
  return counts;
}

// inline self-check on the one bit of real logic
{
  const c = tokenCounts(["Session id in the URL", "Session TTL", "Two-stage check", "checks and fields", "the OF and"]);
  console.assert(c.session === 2, `merge/count: session=2 expected, got ${c.session}`);
  console.assert(c.check === 2, `plural merge: check=2 expected, got ${c.check}`);
  console.assert(c.and === undefined && c.the === undefined, "stopwords dropped");
}

// ---------------------------------------------------------------------------
// text width estimate — rough per-glyph advance as a fraction of font size
// ---------------------------------------------------------------------------
const NARROW = new Set("iIjltf.,;:'|!()[]".split(""));
const WIDE = new Set("mwMW".split(""));
const CAP = new Set("ABCDEFGHKNOQRSUVXYZ".split(""));
// Advances are estimated for a bold sans without a metrics engine; kept
// deliberately generous (+ the trailing multiplier) so the layout never
// clips or overlaps when the SVG renders in a slightly wider fallback face.
function textWidth(str, fs) {
  let em = 0;
  for (const ch of str) {
    if (ch === " ") em += 0.32;
    else if (NARROW.has(ch)) em += 0.34;
    else if (WIDE.has(ch)) em += 0.95;
    else if (CAP.has(ch)) em += 0.76;
    else if (ch >= "0" && ch <= "9") em += 0.60;
    else em += 0.60;
  }
  return em * fs * 1.12;
}

// ---------------------------------------------------------------------------
// deterministic RNG (mulberry32)
// ---------------------------------------------------------------------------
function rng(seed) {
  let a = seed >>> 0;
  return () => {
    a |= 0; a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

// ---------------------------------------------------------------------------
// layout: spiral-place a sorted [text, weight] list into a W x H box
// ---------------------------------------------------------------------------
const PAL = { hot: "#d32f2f", gold: "#986a1a", ink: "#262219", muted: "#8a8375" };

function colourFor(rankFrac) {
  return rankFrac < 0.06 ? PAL.hot : rankFrac < 0.24 ? PAL.gold : rankFrac < 0.72 ? PAL.ink : PAL.muted;
}

function layout(list, opts) {
  const { W, H, margin, aRatio, minFs, maxFs, gamma, rand } = opts;
  const cx = W / 2, cy = H / 2;
  const maxW = list[0][1];
  const diag = Math.hypot(W, H);
  const placed = []; // {x0,y0,x1,y1,text,fs,fill,cx,cy}
  const skipped = [];

  const overlaps = (a) =>
    placed.some((b) => a.x0 < b.x1 && a.x1 > b.x0 && a.y0 < b.y1 && a.y1 > b.y0);

  list.forEach(([text, weight], i) => {
    const t = Math.pow(weight / maxW, gamma);
    const baseFs = minFs + t * (maxFs - minFs);
    const fill = colourFor(i / list.length);
    // jitter the spiral start so equal-weight words don't stack identically
    const phase = rand() * Math.PI * 2;

    for (const shrink of [1, 0.9, 0.8, 0.7, 0.6, 0.5, 0.42]) {
      const fs = baseFs * shrink;
      const w = textWidth(text, fs) + opts.pad;
      const h = fs * 1.0 + opts.pad * 0.6;
      let done = false;
      // Archimedean spiral: r grows ~7px per turn, sampled at ~7px arc steps
      // so it actually threads gaps instead of jumping over them.
      let ang = phase;
      for (let step = 0; step < 90000; step++) {
        const r = 1.15 * (ang - phase);
        ang += Math.min(0.45, 7 / Math.max(r, 5));
        const px = cx + Math.cos(ang) * r * aRatio;
        const py = cy + Math.sin(ang) * r;
        const box = { x0: px - w / 2, y0: py - h / 2, x1: px + w / 2, y1: py + h / 2 };
        if (r > diag) break; // spiral has left the page
        if (box.x0 < margin || box.x1 > W - margin || box.y0 < margin || box.y1 > H - margin) continue;
        if (overlaps(box)) continue;
        placed.push({ ...box, text, fs, fill, cx: px, cy: py });
        done = true;
        break;
      }
      if (done) return;
    }

    // gap-filling pass: the spiral gives up early once the middle is dense;
    // raster-scan the whole page at the smallest size for any leftover slot.
    const fs = baseFs * 0.42;
    const w = textWidth(text, fs) + opts.pad;
    const h = fs * 1.0 + opts.pad * 0.6;
    for (let py = margin + h / 2; py <= H - margin - h / 2; py += 14) {
      for (let px = margin + w / 2; px <= W - margin - w / 2; px += 14) {
        const box = { x0: px - w / 2, y0: py - h / 2, x1: px + w / 2, y1: py + h / 2 };
        if (overlaps(box)) continue;
        placed.push({ ...box, text, fs, fill, cx: px, cy: py });
        return;
      }
    }
    skipped.push(text);
  });

  return { placed, skipped };
}

// ---------------------------------------------------------------------------
// SVG emit
// ---------------------------------------------------------------------------
const esc = (s) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

function svg(placed, meta, opts) {
  const { W, H, mmW, mmH } = opts;
  const rings = [];
  for (let k = 1; k <= 7; k++) {
    rings.push(`<circle cx="${W / 2}" cy="${H / 2}" r="${k * (Math.min(W, H) / 14)}" />`);
  }
  const words = placed
    .map(
      (p) =>
        `  <text x="${p.cx.toFixed(1)}" y="${(p.cy + p.fs * 0.34).toFixed(1)}" ` +
        `font-size="${p.fs.toFixed(1)}" fill="${p.fill}">${esc(p.text)}</text>`
    )
    .join("\n");

  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${mmW}mm" height="${mmH}mm"
     viewBox="0 0 ${W} ${H}" font-family="'SF Pro Text', sans-serif"
     font-weight="700" text-anchor="middle">
  <rect width="${W}" height="${H}" fill="#ffffff"/>
  <g stroke="#262219" stroke-opacity="0.05" fill="none">
${rings.map((r) => "    " + r).join("\n")}
  </g>
  <g>
${words}
  </g>
  <text x="${(opts.margin).toFixed(0)}" y="${(H - opts.margin + 22).toFixed(0)}" text-anchor="start"
        font-size="15" font-weight="400" fill="#8a8375">Sparring — feature inventory · ${meta.shown} of ${meta.total} ${meta.unit} · スパーリング</text>
</svg>
`;
}

// ---------------------------------------------------------------------------
// run
// ---------------------------------------------------------------------------
const argv = process.argv.slice(2);
const seed = Number(argv.find((a) => /^\d+$/.test(a)) ?? 2);
const portrait = !argv.includes("--landscape");

// A3 at ~96dpi
const A_LONG = 1587, A_SHORT = 1122;
const W = portrait ? A_SHORT : A_LONG;
const H = portrait ? A_LONG : A_SHORT;
const mmW = portrait ? 297 : 420;
const mmH = portrait ? 420 : 297;
const aRatio = portrait ? 0.72 : 1.42;
const margin = 62;

const wordCounts = tokenCounts(TITLES);
const UNIQUE_TERMS = Object.keys(wordCounts).length;
// A3 legibly holds ~150 words; the long single-occurrence tail is dropped.
const WORD_LIST = Object.entries(wordCounts).sort((a, b) => b[1] - a[1]).slice(0, 150);

const TITLE_LIST = TITLES
  .map((t) => {
    const clean = t.length > 40 ? t.slice(0, 38).replace(/[\s,/]+\S*$/, "") + "…" : t;
    const w = Math.max(6, Math.min(20, Math.round(30 - clean.length * 0.34)));
    return [clean, w];
  })
  .sort((a, b) => b[1] - a[1]);

const jobs = [
  {
    name: "words.svg",
    list: WORD_LIST,
    unit: "terms",
    opts: { minFs: 17, maxFs: portrait ? 118 : 104, gamma: 0.72, pad: 10 },
  },
  {
    name: "titles.svg",
    list: TITLE_LIST,
    unit: "titles",
    opts: { minFs: 9, maxFs: portrait ? 22 : 22, gamma: 1.0, pad: 5 },
  },
];

for (const job of jobs) {
  const { placed, skipped } = layout(job.list, {
    W, H, margin, aRatio: job.unit === "titles" ? aRatio * 1.12 : aRatio,
    rand: rng(seed + job.name.length), ...job.opts,
  });
  const meta = { shown: placed.length, total: job.list.length, unit: job.unit };
  const out = svg(placed, meta, { W, H, mmW, mmH, margin });
  writeFileSync(join(OUT_DIR, job.name), out);
  const pct = Math.round((placed.length / job.list.length) * 100);
  console.log(`${job.name.padEnd(26)} ${placed.length}/${job.list.length} placed (${pct}%)  ${mmW}×${mmH}mm  seed ${seed}`);
  if (skipped.length && job.unit === "terms" && pct < 88) {
    console.warn(`  low placement — try another seed. skipped: ${skipped.slice(0, 8).join(", ")}…`);
  }
}
