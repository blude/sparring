// Git commit punchcard for Sparring — portrait, hours on the vertical axis.
//
// Reads the current branch's history (git log, by author date) and emits an
// A3 portrait SVG: 24 hour rows down, 7 weekday columns across, one circle
// per (weekday, hour) cell with area proportional to the commit count.
//
// It is a snapshot of history as it stands now — re-run it whenever you
// want a fresh card. Deterministic for a given repo state; no deps beyond
// the git binary. Text uses a plain Helvetica/Arial stack so it stays
// editable and correctly placed in Illustrator.
//
// Usage:  node docs/commit-punchcard/punchcard.gen.mjs
// Writes punchcard.<stamp>.svg (UTC YYYYMMDD-HHMMSS).

import { execSync } from "node:child_process";
import { writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join, basename } from "node:path";
import { STAMP } from "../stamp.mjs";

const OUT = join(dirname(fileURLToPath(import.meta.url)), `punchcard.${STAMP}.svg`);

// ---------------------------------------------------------------------------
// data: weekday (1=Mon .. 7=Sun) x hour (0..23) commit counts, by author date
// ---------------------------------------------------------------------------
const rows = execSync("git log --pretty=format:%ad --date=format:%u-%H", {
  encoding: "utf8",
}).trim().split("\n");

const counts = Array.from({ length: 8 }, () => new Array(24).fill(0)); // [1..7][0..23]
for (const r of rows) {
  const [d, h] = r.split("-").map(Number);
  if (d >= 1 && d <= 7 && h >= 0 && h <= 23) counts[d][h]++;
}
const maxC = Math.max(...counts.flat());
const total = rows.length;

const dates = execSync("git log --pretty=format:%ad --date=short", { encoding: "utf8" })
  .trim()
  .split("\n");
const first = dates[dates.length - 1];
const last = dates[0];

// ---------------------------------------------------------------------------
// layout (viewBox 297 x 420 — A3 portrait)
// ---------------------------------------------------------------------------
const W = 297, H = 420;
const LEFT = 52, RIGHT = 272; // weekday column band
const TOP = 78, BOT = 406; // hour row band (leaves a header block up top)
const stepX = (RIGHT - LEFT) / 6; // 7 weekday columns
const stepY = (BOT - TOP) / 23; // 24 hour rows
const MAXR = 6;

const X = (d) => LEFT + (d - 1) * stepX; // d = 1..7
const Y = (h) => TOP + h * stepY; // h = 0..23
const radius = (c) => (c > 0 ? Math.sqrt(c / maxC) * MAXR : 0);

const DAYS = ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"];
const INK = "#2b2b2b", MUTE = "#6b6b6b", DOT = "#45506b", GRID = "#dcd8cf";

const out = [];
out.push(
  `<svg xmlns="http://www.w3.org/2000/svg" width="${W}mm" height="${H}mm" viewBox="0 0 ${W} ${H}" font-family="'Helvetica Neue', Arial, sans-serif">`,
);
out.push(`<rect width="${W}" height="${H}" fill="#faf9f7"/>`);
out.push(`<text x="24" y="24" font-size="13" font-weight="700" fill="${INK}">Sparring &#8212; Git-Commit-Lochkarte</text>`);
out.push(
  `<text x="24" y="35" font-size="8" fill="${MUTE}">${total} Commits &#183; ${first} &#8211; ${last} &#183; nach Autorendatum</text>`,
);

// legend: circle area ∝ commits/hour, three reference sizes, its own row so
// it never collides with the title on the narrow portrait sheet
out.push(`<text x="24" y="52" font-size="7.5" fill="${MUTE}">Kreisfl&#228;che &#8733; Commits/Stunde</text>`);
const legendVals = [1, Math.round(maxC / 2), maxC];
legendVals.forEach((v, i) => {
  const cx = 150 + i * 30;
  out.push(`<circle cx="${cx}" cy="49" r="${radius(v).toFixed(2)}" fill="${DOT}"/>`);
  out.push(`<text x="${cx + 10}" y="52" font-size="7" fill="${MUTE}">${v}</text>`);
});

// weekday column labels (top)
DAYS.forEach((name, i) => {
  out.push(
    `<text x="${X(i + 1).toFixed(2)}" y="${(TOP - 9).toFixed(2)}" font-size="9" fill="${MUTE}" text-anchor="middle">${name}</text>`,
  );
});

// hour row labels (left, every 2h) + rotated axis title
for (let h = 0; h <= 22; h += 2) {
  out.push(
    `<text x="${(LEFT - 12).toFixed(2)}" y="${(Y(h) + 2.5).toFixed(2)}" font-size="7.5" fill="${MUTE}" text-anchor="end">${h}</text>`,
  );
}
const midY = ((TOP + BOT) / 2).toFixed(2);
out.push(
  `<text transform="rotate(-90 18 ${midY})" x="18" y="${midY}" font-size="9" fill="${MUTE}" text-anchor="middle">Uhrzeit</text>`,
);

// background grid dots
for (let d = 1; d <= 7; d++) {
  for (let h = 0; h <= 23; h++) {
    out.push(`<circle cx="${X(d).toFixed(2)}" cy="${Y(h).toFixed(2)}" r="0.45" fill="${GRID}"/>`);
  }
}

// data circles
for (let d = 1; d <= 7; d++) {
  for (let h = 0; h <= 23; h++) {
    const c = counts[d][h];
    if (!c) continue;
    out.push(
      `<circle cx="${X(d).toFixed(2)}" cy="${Y(h).toFixed(2)}" r="${radius(c).toFixed(2)}" fill="${DOT}"/>`,
    );
  }
}

out.push("</svg>\n");
writeFileSync(OUT, out.join("\n"));
console.log(`${basename(OUT)}  ${total} commits, ${first} – ${last}, max ${maxC}/h`);
