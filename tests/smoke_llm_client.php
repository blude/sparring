<?php
declare(strict_types=1);

/**
 * Self-check for provider selection (createLlmClient()) and OpenAiLlmClient's
 * pure request/response helpers. No network, no real API key.
 * Run: php tests/smoke_llm_client.php
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/LlmClientInterface.php';
require __DIR__ . '/../src/AnthropicLlmClient.php';
require __DIR__ . '/../src/OpenAiLlmClient.php';

/*
|--------------------------------------------------------------------------
| provider dispatch
|--------------------------------------------------------------------------
*/

// AnthropicLlmClient's constructor requires a key present (QR-04) even though
// none of this actually calls the provider — supply a dummy one for the check.
$savedProvider = getenv(LLM_PROVIDER_ENV);
$savedAnthropicKey = getenv(ANTHROPIC_API_KEY_ENV);
putenv(ANTHROPIC_API_KEY_ENV . '=test-key');

putenv(LLM_PROVIDER_ENV); // unset — default
assert(createLlmClient() instanceof AnthropicLlmClient);

putenv(LLM_PROVIDER_ENV . '=anthropic');
assert(createLlmClient() instanceof AnthropicLlmClient);

putenv(LLM_PROVIDER_ENV . '=openai');
assert(createLlmClient() instanceof OpenAiLlmClient);

putenv($savedProvider === false ? LLM_PROVIDER_ENV : LLM_PROVIDER_ENV . '=' . $savedProvider);
putenv($savedAnthropicKey === false ? ANTHROPIC_API_KEY_ENV : ANTHROPIC_API_KEY_ENV . '=' . $savedAnthropicKey);

/*
|--------------------------------------------------------------------------
| OpenAiLlmClient::extractText()
|--------------------------------------------------------------------------
*/

assert(OpenAiLlmClient::extractText(['choices' => [['message' => ['content' => 'hello']]]]) === 'hello');
assert(OpenAiLlmClient::extractText(['choices' => []]) === null);
assert(OpenAiLlmClient::extractText(['choices' => [['message' => ['content' => null]]]]) === null);
assert(OpenAiLlmClient::extractText(null) === null);

/*
|--------------------------------------------------------------------------
| OpenAiLlmClient::finishReason()
|--------------------------------------------------------------------------
*/

assert(OpenAiLlmClient::finishReason(['choices' => [['finish_reason' => 'stop']]]) === 'stop');
assert(OpenAiLlmClient::finishReason(['choices' => [['finish_reason' => 'content_filter']]]) === 'content_filter');
assert(OpenAiLlmClient::finishReason(['choices' => []]) === null);

/*
|--------------------------------------------------------------------------
| OpenAiLlmClient::classifyFailure()
|--------------------------------------------------------------------------
*/

assert(OpenAiLlmClient::classifyFailure(429, null) === 'provider rate limit or quota exhausted');
assert(OpenAiLlmClient::classifyFailure(401, null) === 'provider rejected the request');
// generateResponse() intercepts 5xx before calling classifyFailure() to retry it;
// classifyFailure() itself treats any >=400 (including 5xx) as a rejection —
// correct for classify()/generateTitle()'s single-attempt, no-retry calls.
assert(OpenAiLlmClient::classifyFailure(500, null) === 'provider rejected the request');
assert(OpenAiLlmClient::classifyFailure(200, ['choices' => [['finish_reason' => 'content_filter']]]) === 'provider declined the request (content policy)');
assert(OpenAiLlmClient::classifyFailure(200, ['choices' => [['finish_reason' => 'stop']]]) === null);

/*
|--------------------------------------------------------------------------
| AnthropicLlmClient::classifyGenerationFailure()
|--------------------------------------------------------------------------
*/

// Exception-class equivalent of OpenAiLlmClient::classifyFailure() above —
// AnthropicLlmClient surfaces failures as typed exceptions, not a raw HTTP
// status/decoded body, so the class name is the input instead.
assert(AnthropicLlmClient::classifyGenerationFailure(Anthropic\Core\Exceptions\RateLimitException::class)
    === 'provider rate limit or quota exhausted');
assert(AnthropicLlmClient::classifyGenerationFailure(Anthropic\Core\Exceptions\APIStatusException::class)
    === 'provider rejected the request');
// InternalServerException/APIConnectionException are retryable in generateResponse()'s
// own loop, not a terminal failure classifyGenerationFailure() names a message for.
assert(AnthropicLlmClient::classifyGenerationFailure(Anthropic\Core\Exceptions\InternalServerException::class) === null);
assert(AnthropicLlmClient::classifyGenerationFailure(Anthropic\Core\Exceptions\APIConnectionException::class) === null);
assert(AnthropicLlmClient::classifyGenerationFailure(RuntimeException::class) === null); // unrelated class: no match

echo "smoke_llm_client: ok\n";
