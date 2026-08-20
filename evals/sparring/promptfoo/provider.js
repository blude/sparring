'use strict';

/**
 * promptfoo custom provider — drives one full multi-turn transcript per test.
 *
 * Real arm (config.baseline = false) hits the actual running app over HTTP
 * (POST /api/session ×2, then POST /api/contribute per turn) — unlike the old
 * bin/eval_sparring.php, which calls AnthropicLlmClient::generateResponse()
 * directly and so never exercises Sparring::processTurn()'s RateLimiter,
 * moderation gate, or 600-char cap. Going through the real endpoint closes
 * that gap; see the plan this was built from for the full reasoning.
 *
 * Baseline arm (config.baseline = true) can't go through processTurn() at all
 * (there's no way to omit the system prompt from a real request) — it stays a
 * direct Anthropic call, on the SAME model as the real arm deliberately (the
 * baseline's whole point is a bare-vs-prompted comparison on one model, so
 * making it swappable to a cheaper model would break the comparison).
 *
 * Visitor-persona simulation (both arms) is a separate, configurable role —
 * it's eval scaffolding, not the thing under test, so it can run against a
 * local model to cut cost. See visitorProviderConfig() below.
 */

const Anthropic = require('@anthropic-ai/sdk');
const OpenAI = require('openai');

const APP_URL = process.env.SPARRING_EVAL_APP_URL || 'http://localhost:8080';

// Mirrors config.php's RATE_LIMIT_MAX_REQUESTS=10 / RATE_LIMIT_WINDOW_SECONDS=60, which
// buckets by client origin — every local eval request hashes to the same bucket under
// `php -S`. 6.5s keeps a single-threaded run (promptfooconfig.yaml pins maxConcurrency: 1)
// comfortably under 10 requests/60s.
// ponytail: fixed-interval pacing, not adaptive backoff — upgrade if a full run ever
// needs to go faster than the ~8min this pacing costs across a full scenario set.
const TURN_PACE_MS = Number(process.env.SPARRING_EVAL_PACE_MS || 6500);

// Mirrors config.php's GENERATION_MODEL. Not read from config.php — this harness has no
// PHP dependency by design (it drives the app over HTTP, doesn't require() it).
const GENERATION_MODEL = 'claude-sonnet-5';

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function anthropicClient() {
  const apiKey = process.env.ANTHROPIC_API_KEY;
  if (!apiKey) throw new Error('ANTHROPIC_API_KEY is not set');
  return new Anthropic({ apiKey });
}

// Visitor-sim provider selection — independent of the judge's (grade.js has its own copy
// of this same pattern). Defaults to Anthropic; set to anything else + a base URL to point
// at a local OpenAI-compatible server (Ollama, LM Studio, vLLM, ...).
function visitorProviderConfig() {
  return {
    kind: process.env.SPARRING_EVAL_VISITOR_PROVIDER || 'anthropic',
    model: process.env.SPARRING_EVAL_VISITOR_MODEL || GENERATION_MODEL,
    baseURL: process.env.SPARRING_EVAL_VISITOR_BASE_URL || null,
  };
}

/**
 * Single-message visitor simulation — ported verbatim (prompt text unchanged) from
 * bin/eval_sparring.php's nextVisitorTurn(). The full transcript-so-far renders as plain
 * text in one 'user' message rather than role-flipped multi-turn messages, so there's no
 * alternation/first-role constraint to get wrong.
 */
async function nextVisitorTurn(scenario, priorExchanges) {
  const { kind, model, baseURL } = visitorProviderConfig();

  let transcript = '';
  for (const ex of priorExchanges) {
    transcript += `Visitor: ${ex.visitorContribution}\nSparring: ${ex.sparringResponse}\n\n`;
  }

  const system = 'You are role-playing a visitor at a public AI-sparring exhibition kiosk, arguing with an AI called '
    + 'Sparring about a Digital Design topic.\n\n'
    + `Persona: ${scenario.persona}\n`
    + `Directive: ${scenario.directive}\n\n`
    + 'Stay fully in character as this visitor. Never mention you are an AI, a simulation, or a test. '
    + 'Reply with only the visitor\'s next message, in plain conversational prose, under 500 characters. '
    + 'No preamble, no quotation marks, no meta-commentary — just the message itself.';

  const userContent = `${transcript.trim()}\n\nWrite only your (the visitor's) next message now.`;

  if (kind === 'anthropic') {
    const client = anthropicClient();
    const response = await client.messages.create({
      model,
      max_tokens: 256,
      system,
      messages: [{ role: 'user', content: userContent }],
      thinking: { type: 'disabled' }, // this project disables extended thinking everywhere — see AnthropicLlmClient
    });
    const block = response.content.find((b) => b.type === 'text');
    if (!block) throw new Error('visitor simulation returned no text content');
    return block.text.trim();
  }

  if (!baseURL) {
    throw new Error('SPARRING_EVAL_VISITOR_BASE_URL is required when SPARRING_EVAL_VISITOR_PROVIDER is not "anthropic"');
  }
  const client = new OpenAI({ baseURL, apiKey: process.env.SPARRING_EVAL_VISITOR_API_KEY || 'not-needed' });
  const response = await client.chat.completions.create({
    model,
    max_tokens: 256,
    messages: [
      { role: 'system', content: system },
      { role: 'user', content: userContent },
    ],
  });
  const text = response.choices?.[0]?.message?.content;
  if (!text) throw new Error('visitor simulation (local) returned no content');
  return text.trim();
}

/**
 * Ported verbatim from bin/eval_sparring.php's baselineGenerate()/buildMessages() — the
 * no-system-prompt comparison arm. Deliberately not provider-configurable (see file header).
 */
async function baselineGenerate(priorExchanges, newContribution) {
  const client = anthropicClient();
  const messages = [];
  for (const ex of priorExchanges) {
    messages.push({ role: 'user', content: ex.visitorContribution });
    messages.push({ role: 'assistant', content: ex.sparringResponse });
  }
  messages.push({ role: 'user', content: newContribution });

  const response = await client.messages.create({
    model: GENERATION_MODEL,
    max_tokens: 1024,
    messages,
    thinking: { type: 'disabled' },
    // no `system` — this is the whole point of the baseline arm
  });
  const block = response.content.find((b) => b.type === 'text');
  if (!block) throw new Error('baseline generation returned no text content');
  return block.text;
}

// Node's fetch wraps the real failure reason (connection refused, DNS, TLS, ...) inside
// error.cause and leaves error.message as the useless literal "fetch failed" — this
// unwraps it so a dead APP_URL actually says so instead of forcing a debugger.
function describeFetchError(e) {
  return e.cause?.message || e.message;
}

async function createSession() {
  const createRes = await fetch(`${APP_URL}/api/session`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{}',
  });
  const created = await createRes.json();
  if (!created.sessionId) throw new Error(`session creation failed: ${JSON.stringify(created)}`);

  // projectionGranted: false — eval sessions must never reach the projection wall.
  // Store::recordConsentDecision sets displayable = projectionGranted, and
  // Sparring::assembleDisplayMaterial() pulls 'live'-origin sessions for the wall.
  const consentRes = await fetch(`${APP_URL}/api/session`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      sessionId: created.sessionId,
      tosAgreed: true,
      retentionGranted: true,
      projectionGranted: false,
    }),
  });
  const consented = await consentRes.json();
  if (consented.sessionState === undefined) {
    throw new Error(`consent recording failed: ${JSON.stringify(consented)}`);
  }
  return created.sessionId;
}

// Renders a transcript as plain conversational text for promptfoo view — the structured
// version (what grade.js actually reads) travels separately via the `metadata` field.
function formatTranscript(transcript) {
  const lines = transcript.exchanges.flatMap((ex, i) => [
    `Turn ${i + 1} — Visitor: ${ex.visitorContribution}`,
    `Turn ${i + 1} — Sparring: ${ex.sparringResponse}`,
  ]);
  if (transcript.endedEarly) {
    lines.push('', `[ended early: ${transcript.endedEarly}]`);
  }
  return lines.join('\n\n') || '[no turns completed]';
}

async function contribute(sessionId, contribution) {
  const res = await fetch(`${APP_URL}/api/contribute`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ sessionId, contribution }),
  });
  return res.json();
}

class SparringProvider {
  constructor(options) {
    this.providerId = options.id || 'sparring-provider';
    this.config = options.config || {};
  }

  id() {
    return this.providerId;
  }

  async callApi(_prompt, context) {
    const scenario = context.vars;
    // Override lets the "cheap smoke check before a full run" step in the plan's rollout
    // shrink turns without editing scenario files, mirroring the old suite's --turns flag.
    const turns = Number(process.env.SPARRING_EVAL_TURNS || scenario.turns || 4);
    const baseline = Boolean(this.config.baseline);

    let sessionId = null;
    if (!baseline) {
      try {
        sessionId = await createSession();
      } catch (e) {
        return { error: `session setup failed against ${APP_URL} — is \`php -S localhost:8080 -t public public/index.php\` running? (${describeFetchError(e)})` };
      }
    }

    const exchanges = [];
    let contribution = scenario.opener;
    let endedEarly = null;

    for (let turn = 1; turn <= turns; turn++) {
      let sparringResponse;

      if (baseline) {
        try {
          sparringResponse = await baselineGenerate(exchanges, contribution);
        } catch (e) {
          endedEarly = `baseline generation failed: ${e.message}`;
          break;
        }
      } else {
        if (turn > 1) await sleep(TURN_PACE_MS); // pace-sleep BEFORE each call, see TURN_PACE_MS comment
        let result;
        try {
          result = await contribute(sessionId, contribution);
          if (result.status === 'rate-limited') {
            // One-shot retry — defensive, not the primary defense (pacing is).
            await sleep(60_000);
            result = await contribute(sessionId, contribution);
          }
        } catch (e) {
          endedEarly = `contribute request failed against ${APP_URL} (${describeFetchError(e)})`;
          break;
        }
        if (result.status !== 'ok') {
          // Non-'ok' (content-flagged, turn-limit, rate-limited again, generation-failed,
          // ...) is a legitimate outcome to record, not a crash — this is the whole point
          // of driving the real endpoint. Stop the loop; whatever turns were gathered are
          // still gradeable.
          endedEarly = result.status;
          break;
        }
        sparringResponse = result.exchange.sparringResponse;
      }

      exchanges.push({ visitorContribution: contribution, sparringResponse });

      if (turn < turns) {
        try {
          contribution = await nextVisitorTurn(scenario, exchanges);
        } catch (e) {
          endedEarly = `visitor simulation failed: ${e.message}`;
          break;
        }
      }
    }

    const transcript = { scenarioId: scenario.id, baseline, endedEarly, exchanges };

    return {
      // Human-readable — this is what promptfoo view shows as "the response". The
      // structured transcript grade.js actually grades goes in `metadata` instead, so the
      // UI shows a real conversation, not a JSON blob.
      output: formatTranscript(transcript),
      metadata: { transcript },
    };
  }
}

module.exports = SparringProvider;
