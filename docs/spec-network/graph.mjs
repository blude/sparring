// Shared parser for the Sparring spec requirement graph.
//
// Reads the six AsciiDoc design docs in spec/ and exports the requirement
// network as { nodes, edges, crossLevel } plus the config tables. Both
// network.gen.mjs and force.gen.mjs consume this so the graph is parsed one way.
//
// Nodes: every top-level requirement/element ID (BG-, SG-, G-, TF-, QR-, ...).
// Excluded, because they are not requirements in their own right:
//   - step-level anchors  (FS-, FA-, ST-, EX-, PS-, PA-, SA-)
//   - entity attributes   (E-01.8 and the like)
//   - open questions       (TBC-)
//
// Edges: directed, from the typed traceability lines ONLY (_Satisfies:_,
// _Realises:_, _Refines:_, _Achieves:_, _Supports:_, _Applies to:_,
// _Implements:_, plus a few smaller ones). Free prose mentions of an ID
// elsewhere in a section are not edges — counting those turns ~320 real
// trace links into a ~1000-line hairball.

import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const SPEC_DIR = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "spec");

// ---------------------------------------------------------------------------
// which files, in which design level
// ---------------------------------------------------------------------------
// `key` namespaces the IDs: L3 element docs each restart their numbering
// (every SE-0x file has its own G-01, TF-01, QR-01, ...), so a node is
// identified by "<file key>:<id>", not the bare id.
export const FILES = [
  { name: "L1-solution-design-concept.adoc", level: 1, key: "L1" },
  { name: "L2-system-design-concept.adoc", level: 2, key: "L2" },
  { name: "L3-SE-01-input-client.adoc", level: 3, key: "SE-01" },
  { name: "L3-SE-02-display-client.adoc", level: 3, key: "SE-02" },
  { name: "L3-SE-03-backend-service.adoc", level: 3, key: "SE-03" },
  { name: "L3-SE-04-system-prompt.adoc", level: 3, key: "SE-04" },
];
// xref target filename -> file key, for resolving cross-document links.
const FILEKEY_BY_NAME = new Map(FILES.map((f) => [f.name, f.key]));

// Prefix -> display order, grouped by level.
export const TIERS = [
  // L1 — solution design concept
  "BG", "VP", "VCA", "BE", "BP", "BQR", "BC",
  // L2 — system design concept
  "SG", "AP", "UT", "SE", "HE", "SSc", "SQR", "SC",
  // L3 — element design concept
  "PE", "G", "UC", "UI", "TF", "TI", "TO", "E", "QR", "C",
];
export const TIER_RANK = new Map(TIERS.map((p, i) => [p, i]));
const KNOWN_PREFIX = new Set(TIERS);

// Relation keywords that actually express traceability. Anything else
// (_Acceptance criteria:_, _Rationale:_, ...) may mention IDs but is not
// a trace link, so it must not be scanned.
const REL_KEYWORDS = [
  "Satisfies", "Realises", "Realizes", "Refines", "Achieves", "Supports",
  "Applies to", "Implements", "Interacts with", "Calls", "Constrained by",
  "Used by", "Derived",
];
const REL_RE = new RegExp(`_(${REL_KEYWORDS.join("|")}):_(.*)$`);

// ---------------------------------------------------------------------------
// parse
// ---------------------------------------------------------------------------
// A top-level anchor: [[BG-01]] — captured group is prefix + number, with
// NO trailing ".1" (attribute) or "-1" (step), because those don't close
// with "]]" right after the digits.
const ANCHOR_RE = /\[\[([A-Za-z]+)-(\d+)\]\]/g;
// One reference token inside a relation line, either form:
//   xref:L2-system-design-concept.adoc#SG-04[...]   (cross-document)
//   <<SG-04,...>>                                    (same document)
// Trailing .N / -N (attribute / step) is captured so it can be normalised off.
const REF_RE =
  /xref:([\w.-]+\.adoc)#([A-Za-z]+-\d+(?:\.\d+)?(?:-\d+)?)\[|<<([A-Za-z]+-\d+(?:\.\d+)?(?:-\d+)?)[,>]/g;

/** @type {Map<string, {nid:string,key:string,id:string,prefix:string,level:number}>} */
export const nodes = new Map(); // "key:id" -> node
const rawEdges = []; // { src, dst, rel }  (src/dst are "key:id")

function baseId(ref) {
  // E-01.8 -> E-01 ; FS-01-4 -> FS-01 (then dropped, FS isn't a node prefix)
  const m = ref.match(/^([A-Za-z]+)-(\d+)/);
  return m ? `${m[1]}-${m[2]}` : ref;
}

for (const { name, level, key } of FILES) {
  const text = readFileSync(join(SPEC_DIR, name), "utf8");
  let current = null; // "key:id" of the section we're inside

  for (const line of text.split("\n")) {
    // Register every top-level anchor on this line, and remember the last
    // one as the section we're now in.
    let am;
    ANCHOR_RE.lastIndex = 0;
    while ((am = ANCHOR_RE.exec(line))) {
      const prefix = am[1];
      if (!KNOWN_PREFIX.has(prefix)) continue; // FS/ST/EX/... and TBC
      const id = `${prefix}-${am[2]}`;
      const nid = `${key}:${id}`;
      if (!nodes.has(nid)) nodes.set(nid, { nid, key, id, prefix, level });
      current = nid;
    }

    // Typed traceability line -> one edge per referenced ID.
    const rm = line.match(REL_RE);
    if (rm && current) {
      const rel = rm[1];
      const tail = rm[2];
      let fm;
      REF_RE.lastIndex = 0;
      while ((fm = REF_RE.exec(tail))) {
        // fm[1]/fm[2] = xref file + id ; fm[3] = same-document id
        const targetKey = fm[2] ? FILEKEY_BY_NAME.get(fm[1]) : key;
        const targetId = baseId(fm[2] || fm[3]);
        if (!targetKey) continue; // xref to a file we don't model
        const dst = `${targetKey}:${targetId}`;
        if (dst !== current) rawEdges.push({ src: current, dst, rel });
      }
    }
  }
}

// Keep only edges whose both ends are real nodes; dedupe on (src,dst),
// first relation label wins.
const seen = new Set();
/** @type {{src:string,dst:string,rel:string}[]} */
export const edges = [];
for (const e of rawEdges) {
  if (!nodes.has(e.src) || !nodes.has(e.dst)) continue;
  const key = `${e.src} ${e.dst}`;
  if (seen.has(key)) continue;
  seen.add(key);
  edges.push(e);
}

// --- self-check: the parser is the fragile part, so pin its output --------
export const crossLevel = edges.filter(
  (e) => nodes.get(e.src).level !== nodes.get(e.dst).level,
).length;
function assert(cond, msg) {
  if (!cond) throw new Error(`spec-graph self-check failed: ${msg}`);
}
// ~191 top-level anchors across the six docs (TBC- and step/attribute
// anchors excluded); a big swing means the anchor regex or file list moved.
assert(nodes.size > 170 && nodes.size < 210, `node count ${nodes.size} out of band`);
assert(crossLevel > 40, `only ${crossLevel} cross-level edges — xref form likely unparsed`);
const hasEdge = (s, d) => edges.some((e) => e.src === s && e.dst === d);
// same-document <<>> link, normalised, points at the right namespace
assert(hasEdge("SE-03:TF-01", "SE-03:G-01"), "missing SE-03 TF-01->G-01 (Achieves)");
// cross-document xref: link resolves to the target file's namespace
assert(hasEdge("SE-03:G-01", "L2:SG-04"), "missing SE-03 G-01->L2 SG-04 (Satisfies)");
assert(hasEdge("L2:SG-01", "L1:BG-02"), "missing L2 SG-01->L1 BG-02 (Satisfies)");

// Degree (in + out) per node id — handy for both renderers.
export const degree = new Map([...nodes.keys()].map((k) => [k, 0]));
for (const e of edges) {
  degree.set(e.src, degree.get(e.src) + 1);
  degree.set(e.dst, degree.get(e.dst) + 1);
}
