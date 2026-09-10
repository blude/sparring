# 8. LLM provider abstraction with an env-selected provider

- **Status:** Accepted
- **Date:** 2026-08 (back-filled 2026-09-10)
- **References:** `src/AbstractLlmClient.php`, `src/LlmClientInterface.php`, `src/AnthropicLlmClient.php`, `src/OpenAiLlmClient.php`; `README.md` "Switch LLM provider"; `config.php` `--- LLM (PE-01) ---`

## Context

Sparring's default and production provider is Anthropic. During
development it is useful to run against OpenAI, or against a local model
served through LM Studio's OpenAI-compatible endpoint, without touching
call sites. Both providers are used for two distinct calls: response
generation (TO-01) and a small classification call (TO-02).

## Decision

Define `LlmClientInterface` and an `AbstractLlmClient` base holding the
provider-agnostic parts (response and failure classification helpers,
system-message assembly). `AnthropicLlmClient` and `OpenAiLlmClient`
implement the provider-specific calls. The concrete client is chosen at
construction from `LLM_PROVIDER` in the environment; model IDs and base
URL come from `OPENAI_*` / Anthropic env vars with defaults in
`config.php`.

## Consequences

- Swapping providers is an `.env` change, no code edit. Local LM Studio
  works by pointing `OPENAI_BASE_URL` at it and setting the loaded model
  ID explicitly (the `gpt-4.1` defaults are wrong for local use).
- Two implementations to keep in step; their pure response and
  failure-classification helpers plus `buildSystemMessages()` /
  `buildSystemBlocks()` are covered by `tests/smoke_llm_client.php`.
- Provider-specific concerns (Anthropic prompt-cache blocks, thinking
  config) live in the Anthropic client only — see ADR 0012.
