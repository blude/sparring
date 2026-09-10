# 12. Extended thinking disabled on both Anthropic calls

- **Status:** Accepted, revisit planned
- **Date:** 2026-08-14 (back-filled 2026-09-10)
- **References:** originally commit `61de139` (then in `src/LlmClient.php`); now `src/AnthropicLlmClient.php` (`thinking: ['type' => 'disabled']` on all calls); memory note `sparring-thinking-disabled`

## Context

The Anthropic model default-enables extended thinking. Sparring makes two
calls: response generation (TO-01, ~1024-token budget) and a small
classification call (TO-02, ~64-token budget).

On TO-02, thinking could consume the whole 64-token budget before any JSON
text, producing `stop_reason: max_tokens` with no text block. That throws,
and the fail-closed catch (`FS-02-2`) then flags a benign contribution as
`content-flagged`. This was confirmed live — a real benign submission was
rejected this way.

On TO-01, no starvation was observed across a full measured 10-turn
session (thinking fired ~70% of turns, 60–110 tokens each, worst case
305/1024). There was also no measured quality benefit — the persona prompt
already caps replies to a few sentences — so thinking there is billed
tokens producing nothing the visitor sees.

## Decision

Disable extended thinking on every Anthropic call
(`thinking: ['type' => 'disabled']`), in the Anthropic client only.

## Consequences

- TO-02 classification is reliable: the whole small budget goes to the
  JSON response.
- TO-01 stops paying for reasoning that is discarded.
- The reasoning is lost rather than shown. The intended revisit is an
  experiment that *surfaces* the thinking step in the visible chat
  history, giving the visitor insight into the sparring partner's
  reasoning. That would be new visible behaviour (new UC/TF, touching the
  exchange record and SE-02 display material), checked against `spec/`
  when built — not a tweak to this decision. Until then, disabled stands.
