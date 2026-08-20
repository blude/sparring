'use strict';

/**
 * promptfoo custom assertion — grades one transcript (the JSON string provider.js
 * returned as `output`) against evals/sparring/rubric.md.
 *
 * The judge and mechanical-check *code* is ported from bin/grade_sparring.php into this
 * one file, deliberately — the earlier draft of this plan shelled out to the PHP script
 * instead, but the whole point of this migration is a judge that's easy to open and edit,
 * not one preserved behind a subprocess call. bin/grade_sparring.php itself is untouched;
 * the two share no code.
 *
 * The rubric *content* (rubric.md, socratic-moves.json) is NOT duplicated here — both
 * files are read directly from evals/sparring/, so editing them updates this judge and
 * the old suite's judge identically, from one place.
 *
 * Every check (mechanical + judged + move-variety) is returned as its own
 * componentResults entry, so promptfoo view shows a per-item breakdown for the single
 * assertion call — see the plan's Design section for why one call, not N assertions.
 */

const fs = require('fs');
const path = require('path');
const Anthropic = require('@anthropic-ai/sdk');
const OpenAI = require('openai');

const RUBRIC = fs.readFileSync(path.join(__dirname, '..', 'rubric.md'), 'utf8');
const MOVES = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'socratic-moves.json'), 'utf8'));
const MOVE_KEYS = [
  ...MOVES.socratic_moves.map((m) => m.key),
  ...MOVES.sparring_moves.map((m) => m.key),
];

// Mirrors config.php's GENERATION_MODEL — the default judge model when
// SPARRING_EVAL_JUDGE_PROVIDER is left at "anthropic" (matches production).
const GENERATION_MODEL = 'claude-sonnet-5';

/*
|--------------------------------------------------------------------------
| Mechanical checks — plain code, free, zero variance. Ported from
| bin/grade_sparring.php's mechanicalChecks(). One deliberate difference: word
| count here is a plain whitespace split instead of PHP's str_word_count(),
| which is more reliable on the german-language scenario's umlauts — an
| intentional improvement while porting, not an oversight.
|--------------------------------------------------------------------------
*/

function mermaidFenceOk(text) {
  if (!text.includes('```mermaid')) return true; // no fence attempted — nothing to validate
  return /```mermaid\r?\n([\s\S]*?)\r?\n```/.test(text);
}

function wordCount(text) {
  return text.trim().split(/\s+/).filter(Boolean).length;
}

function mechanicalChecks(exchanges) {
  const turns = exchanges.map((e) => e.sparringResponse);

  const mk = (text, failingTurns, evidenceNoun) => ({
    text,
    passed: failingTurns.length === 0,
    evidence: failingTurns.length === 0
      ? 'clean across all turns'
      : `turn(s) ${failingTurns.join(', ')} — ${evidenceNoun}`,
  });

  const bannedFail = (phrase) => turns
    .map((t, i) => (t.toLowerCase().includes(phrase) ? i + 1 : null))
    .filter((n) => n !== null);

  // whole-word match — "move" is fine inside e.g. "movement"
  const moveFlags = turns
    .map((t, i) => (/\bmoves?\b/i.test(t) && !/\bchess\b/i.test(t) ? i + 1 : null))
    .filter((n) => n !== null);

  const lengthFailures = [];
  const questionFlags = [];
  const fenceFailures = [];
  turns.forEach((t, i) => {
    const paragraphs = t.split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean);
    if (paragraphs.length > 2 || wordCount(t) > 140) lengthFailures.push(i + 1); // 140: soft ~120-word ceiling + buffer
    if ((t.match(/\?/g) || []).length > 1) questionFlags.push(i + 1);
    if (!mermaidFenceOk(t)) fenceFailures.push(i + 1);
  });

  return [
    mk("no banned phrase 'load-bearing'", bannedFail('load-bearing'), "contains 'load-bearing'"),
    mk("no banned phrase 'failure mode'", bannedFail('failure mode'), "contains 'failure mode'"),
    mk("[flag] word 'move(s)' used outside a chess context", moveFlags, "uses 'move'/'moves' — check it's not standing in for decision/step/action"),
    mk('length fits phone/wall reading (≤2 paragraphs, ≤~120 words)', lengthFailures, 'turn runs long'),
    mk('[flag] at most one question per turn', questionFlags, 'turn asks more than one question'),
    mk('well-formed Mermaid fence when one is attempted', fenceFailures, 'unterminated or malformed ```mermaid fence'),
  ];
}

/*
|--------------------------------------------------------------------------
| Judge — one structured-output call per transcript, against rubric.md.
| Configurable provider: defaults to Anthropic (matches production), or an
| OpenAI-compatible local server (Ollama, LM Studio, vLLM, ...) via
| SPARRING_EVAL_JUDGE_* env vars. See README.md for setup.
|--------------------------------------------------------------------------
*/

function judgeSchema() {
  return {
    type: 'object',
    properties: {
      moveLabels: {
        type: 'array',
        items: {
          type: 'object',
          properties: {
            turn: { type: 'integer' },
            move: { type: 'string', enum: MOVE_KEYS },
          },
          required: ['turn', 'move'],
          additionalProperties: false,
        },
      },
      findings: {
        type: 'array',
        items: {
          type: 'object',
          properties: {
            item: {
              type: 'string',
              enum: [
                'no-handed-over-conclusion',
                'held-position',
                'premature-closure',
                'no-meta-commentary',
                'domain-grounding',
                'voice-style',
              ],
            },
            passed: { type: 'boolean' },
            evidence: { type: 'string' },
            state: { type: ['string', 'null'] }, // held-position only: held|obstruction|capitulated
          },
          required: ['item', 'passed', 'evidence', 'state'],
          additionalProperties: false,
        },
      },
    },
    required: ['moveLabels', 'findings'],
    additionalProperties: false,
  };
}

function judgeProviderConfig() {
  return {
    kind: process.env.SPARRING_EVAL_JUDGE_PROVIDER || 'anthropic',
    model: process.env.SPARRING_EVAL_JUDGE_MODEL || GENERATION_MODEL,
    baseURL: process.env.SPARRING_EVAL_JUDGE_BASE_URL || null,
  };
}

function buildJudgePrompt(transcript) {
  const lines = transcript.exchanges.flatMap((ex, i) => [
    `Turn ${i + 1} — Visitor: ${ex.visitorContribution}`,
    `Turn ${i + 1} — Sparring: ${ex.sparringResponse}`,
  ]);
  return `${RUBRIC}\n\n---\n\nTranscript to grade (scenario: ${transcript.scenarioId}):\n\n${lines.join('\n')}`;
}

// Best-effort JSON extraction — only reached when a local judge model doesn't honor strict
// schema output. A local judge that silently mis-parses is worse than the old suite's
// opaque-but-reliable one (see plan's "Risk to flag" note), so this stays narrow: find the
// first {...} block and give up rather than guessing further.
function extractJson(text) {
  const match = text.match(/\{[\s\S]*\}/);
  if (!match) throw new Error('no JSON object found in judge output');
  return JSON.parse(match[0]);
}

async function judgeTranscript(transcript) {
  const { kind, model, baseURL } = judgeProviderConfig();
  const prompt = buildJudgePrompt(transcript);
  const schema = judgeSchema();

  if (kind === 'anthropic') {
    const apiKey = process.env.ANTHROPIC_API_KEY;
    if (!apiKey) throw new Error('ANTHROPIC_API_KEY is not set — the judge needs it too');
    const client = new Anthropic({ apiKey });
    const response = await client.messages.create({
      model,
      max_tokens: 2048,
      messages: [{ role: 'user', content: prompt }],
      thinking: { type: 'disabled' },
      output_config: { format: { type: 'json_schema', schema } },
    });
    const block = response.content.find((b) => b.type === 'text');
    if (!block) throw new Error('judge returned no valid structured output');
    return JSON.parse(block.text);
  }

  if (!baseURL) {
    throw new Error('SPARRING_EVAL_JUDGE_BASE_URL is required when SPARRING_EVAL_JUDGE_PROVIDER is not "anthropic"');
  }
  const client = new OpenAI({ baseURL, apiKey: process.env.SPARRING_EVAL_JUDGE_API_KEY || 'not-needed' });
  try {
    const response = await client.chat.completions.create({
      model,
      max_tokens: 2048,
      messages: [{ role: 'user', content: prompt }],
      response_format: { type: 'json_schema', json_schema: { name: 'sparring_judge', schema, strict: true } },
    });
    return JSON.parse(response.choices[0].message.content);
  } catch (e) {
    // Fallback: local server doesn't support strict schema — ask plainly, extract JSON.
    const response = await client.chat.completions.create({
      model,
      max_tokens: 2048,
      messages: [{
        role: 'user',
        content: `${prompt}\n\nRespond with ONLY a single JSON object matching this schema, no other text:\n${JSON.stringify(schema)}`,
      }],
    });
    return extractJson(response.choices[0].message.content);
  }
}

/*
|--------------------------------------------------------------------------
| Move-variety — adjacent-turn repetition on the judge's labels, checked in
| plain code rather than asked of the judge holistically. Ported verbatim
| from bin/grade_sparring.php's moveVarietyFinding() — rubric.md item 5's own
| reasoning: classifying into the closed vocabulary is far more stable than a
| holistic "was there variety?" judgment.
|--------------------------------------------------------------------------
*/

function moveVarietyFinding(moveLabels) {
  const byTurn = new Map();
  for (const entry of moveLabels) byTurn.set(entry.turn, entry.move);
  const turnsSorted = [...byTurn.keys()].sort((a, b) => a - b);

  const repeats = [];
  let prevTurn = null;
  let prevMove = null;
  for (const turn of turnsSorted) {
    const move = byTurn.get(turn);
    if (prevMove !== null && move === prevMove) repeats.push(`${prevTurn}→${turn}: ${move}`);
    prevTurn = turn;
    prevMove = move;
  }

  return {
    text: 'no repeated Socratic/Sparring move on adjacent turns',
    passed: repeats.length === 0,
    evidence: repeats.length === 0
      ? `labels: ${turnsSorted.map((t) => `${t}:${byTurn.get(t)}`).join(', ')}`
      : `repeated on ${repeats.join('; ')}`,
  };
}

/*
|--------------------------------------------------------------------------
| promptfoo custom-assertion entry point
|--------------------------------------------------------------------------
*/

module.exports = async (output) => {
  const transcript = JSON.parse(output);
  const expectations = mechanicalChecks(transcript.exchanges);

  // Judge rubric applies to the real-prompt arm only — grading the no-prompt baseline
  // against sparring.md's own rules is meaningless (same rule as the old suite).
  if (!transcript.baseline && transcript.exchanges.length > 0) {
    try {
      const judged = await judgeTranscript(transcript);
      expectations.push(moveVarietyFinding(judged.moveLabels || []));
      for (const finding of judged.findings || []) {
        const evidence = finding.state ? `[${finding.state}] ${finding.evidence}` : finding.evidence;
        expectations.push({ text: finding.item, passed: finding.passed, evidence });
      }
    } catch (e) {
      expectations.push({ text: 'judge call succeeded', passed: false, evidence: `judge failed: ${e.message}` });
    }
  }

  if (transcript.endedEarly) {
    expectations.push({
      text: 'transcript completed all turns',
      passed: false,
      evidence: `stopped early: ${transcript.endedEarly}`,
    });
  }

  // Core score deliberately excludes [flag]-prefixed advisory items — same reasoning as
  // the old suite's summarize_sparring.php: blending advisories into pass/fail would let a
  // prompt look "worse" for reasons unrelated to quality (e.g. a visitor asking more
  // follow-up questions).
  const nonFlag = expectations.filter((e) => !e.text.startsWith('[flag]'));
  const passedCount = nonFlag.filter((e) => e.passed).length;

  return {
    pass: nonFlag.every((e) => e.passed),
    score: nonFlag.length ? passedCount / nonFlag.length : 1,
    reason: `${passedCount}/${nonFlag.length} core checks passed`,
    componentResults: expectations.map((e) => ({
      pass: e.passed,
      score: e.passed ? 1 : 0,
      reason: e.evidence,
      namedScores: { [e.text]: e.passed ? 1 : 0 },
    })),
  };
};
