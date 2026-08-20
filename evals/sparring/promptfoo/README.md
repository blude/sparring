# promptfoo harness — prompts/sparring.md

Replaces `bin/eval_sparring.php` + `bin/grade_sparring.php` + `bin/summarize_sparring.php`'s
orchestration and reporting. **The old suite is untouched and still runnable** — see
`evals/sparring/README.md`. Keep both around until you trust this one; nothing here deletes
anything from the old suite.

Scenarios (`evals/sparring/scenarios/*.json`), the rubric (`evals/sparring/rubric.md`), and
the closed move vocabulary (`evals/sparring/socratic-moves.json`) are shared, unmodified,
read straight out of `evals/sparring/` — editing them updates both suites' judge from one
place.

## Why this exists

- **UI.** `promptfoo view` gives a real browsable/diffable run history instead of a stdout
  markdown table.
- **Judge legibility.** The judge (rubric prompt + JSON schema + parsing) lives in
  `grade.js`, plain and readable, instead of behind a PHP `Client::messages->create()` call
  — the point is to make it easy to actually sit down and check whether the rubric is fair,
  not just to preserve it unread.
- **Cost.** The visitor-persona simulator and the judge are both eval scaffolding, not the
  thing under test — both are configurable to a local model. The Sparring turn itself always
  runs the real production model; that's what's being tested.
- **Closes a real coverage gap.** `provider.js` drives the actual running app over HTTP
  (`/api/session`, `/api/contribute`), so `Sparring::processTurn()`'s RateLimiter,
  moderation gate, and 600-char cap are exercised — the old suite bypasses all of these.

## Requirements

promptfoo needs **Node ≥22.22.0** (`node --version`). If your default Node is older
(`nvm install 22 && nvm use 22`, or similar) — `npm install` will pull it in fine on an
older Node, but `promptfoo`/`npx promptfoo` itself refuses to start below that version.

## Running it

```bash
npm install

# Start the real app first — provider.js drives it over HTTP.
export ANTHROPIC_API_KEY=...
php -S localhost:8080 -t public public/index.php

# In another shell:
npm run eval:sparring          # full run, all scenarios, 3 reps each
npm run eval:sparring:view     # opens the results UI
```

Cheap smoke check before a full run (same philosophy as the old suite — see its README):

```bash
SPARRING_EVAL_TURNS=2 npx promptfoo eval \
  -c evals/sparring/promptfoo/promptfooconfig.yaml \
  --filter-pattern wicked-problem --repeat 1
```

## Configuring a local model

The Sparring turn under test is never configurable (it must stay the real production model
— that's the point of the eval). The visitor simulator and the judge are, independently:

```bash
# Visitor-persona simulation
export SPARRING_EVAL_VISITOR_PROVIDER=local
export SPARRING_EVAL_VISITOR_MODEL=llama3.1
export SPARRING_EVAL_VISITOR_BASE_URL=http://localhost:11434/v1   # Ollama's OpenAI-compatible endpoint

# Judge
export SPARRING_EVAL_JUDGE_PROVIDER=local
export SPARRING_EVAL_JUDGE_MODEL=llama3.1
export SPARRING_EVAL_JUDGE_BASE_URL=http://localhost:11434/v1
```

Any OpenAI-compatible server works (Ollama, LM Studio, vLLM, llama.cpp's server mode, ...).
Leave the `*_PROVIDER` vars unset (or `anthropic`) to use the real Anthropic API — this is
the default and matches the old suite's behavior.

**Before trusting a full run against a local judge**, run the cheap smoke check above with
the local judge configured and open `promptfoo view` to confirm the structured output
actually parsed — not every local model honors strict JSON schema output reliably. `grade.js`
has a best-effort fallback parse for when it doesn't, but a silently-mis-parsing judge is
worse than no judge; check it before scaling up.

## Baseline arm

The no-system-prompt comparison arm (`sparring-baseline` provider) deliberately stays on
the same model as the real arm, regardless of `SPARRING_EVAL_*` overrides — its only purpose
is a bare-vs-prompted comparison on one model, and swapping its model would break that.

## Rate limiting

`config.php`'s `RATE_LIMIT_MAX_REQUESTS=10`/60s bucket keys on client origin, and every
local eval request hashes to the same bucket under `php -S`. `provider.js` paces itself
(~6.5s between real-arm turns, `SPARRING_EVAL_PACE_MS` to override) and
`promptfooconfig.yaml` pins `maxConcurrency: 1` — don't raise concurrency without also
addressing this, or the real arm will spend most of a run being rate-limited.

## What "done" looks like

Not re-validating `sparring.md`'s prompt quality — confirming this harness reproduces what
the old one covers. See the migration plan's Verification section for the specific checks.
