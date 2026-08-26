<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/AbstractLlmClient.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

/**
 * PE-01 via any OpenAI-Chat-Completions-compatible endpoint: OpenAI itself,
 * or a local server such as LM Studio (same wire format, different base URL
 * and no real API key needed). Same TO-01/TO-02 contract and TF-01/TF-02
 * retry policy as AnthropicLlmClient, translated from Anthropic's typed
 * exceptions to this API's HTTP status codes / finish_reason.
 */
final class OpenAiLlmClient extends AbstractLlmClient
{
    private Client $http;
    private string $generationModel;
    private string $classificationModel;

    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $baseUrl = getenv(OPENAI_BASE_URL_ENV) ?: OPENAI_BASE_URL_DEFAULT;
        $apiKey = getenv(OPENAI_API_KEY_ENV) ?: ''; // empty is fine — e.g. LM Studio ignores it
        $this->generationModel = getenv(OPENAI_GENERATION_MODEL_ENV) ?: OPENAI_GENERATION_MODEL_DEFAULT;
        $this->classificationModel = getenv(OPENAI_CLASSIFICATION_MODEL_ENV) ?: OPENAI_CLASSIFICATION_MODEL_DEFAULT;

        $this->http = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'headers' => ['Authorization' => 'Bearer ' . $apiKey],
            'http_errors' => false, // read status ourselves — see classifyFailure()
        ]);

        $this->loadPrompts();
    }

    /*
    |--------------------------------------------------------------------------
    | Generation (TO-01)
    |--------------------------------------------------------------------------
    */

    /**
     * Same turn-1 curriculum grounding as AnthropicLlmClient::buildSystemBlocks()
     * — see that method's doc for why the static prompt and any per-turn
     * grounding text stay in separate messages. OpenAI's chat-completions
     * shape takes multiple system-role messages rather than Anthropic's
     * block array, but the intent is identical.
     *
     * ponytail: verified against the real Anthropic API for buildSystemBlocks()
     * (see AnthropicLlmClient's doc), but NOT verified live here — no
     * OPENAI_API_KEY configured to test against. Two role:system messages
     * is convention for OpenAI's own API, not a documented contract the way
     * Anthropic's system array is, and this client also targets OpenAI-
     * compatible local servers (LM Studio etc. — see createLlmClient()'s
     * doc in config.php), which are exactly where a second system message
     * is most likely to be rejected or silently merged wrong. If a target
     * turns out not to honor it: concatenate $groundingContext into the
     * first message's content instead of appending a second message.
     *
     * @return list<array{role: string, content: string}>
     */
    public static function buildSystemMessages(string $sparringPrompt, ?string $groundingContext): array
    {
        $messages = [['role' => 'system', 'content' => $sparringPrompt]];
        if ($groundingContext !== null && $groundingContext !== '') {
            $messages[] = ['role' => 'system', 'content' => $groundingContext];
        }
        return $messages;
    }

    /** TO-01, same two-attempt policy as AnthropicLlmClient::generateResponse(). */
    public function generateResponse(array $priorExchanges, string $newContribution, ?string $groundingContext = null): string
    {
        $messages = self::buildSystemMessages($this->sparringPrompt, $groundingContext);
        foreach ($priorExchanges as $exchange) {
            $messages[] = ['role' => 'user', 'content' => $exchange['visitorContribution']];
            $messages[] = ['role' => 'assistant', 'content' => $exchange['sparringResponse']];
        }
        $messages[] = ['role' => 'user', 'content' => $newContribution];

        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = $this->http->request('POST', 'chat/completions', [
                    'json' => [
                        'model' => $this->generationModel,
                        'max_tokens' => 1024,
                        'messages' => $messages,
                    ],
                    'timeout' => (float) GENERATION_TIMEOUT_SECONDS,
                ]);
            } catch (ConnectException $e) {
                $lastError = $e;
                if ($attempt < 2) {
                    usleep(300_000);
                }
                continue;
            }

            $status = $response->getStatusCode();
            $decoded = json_decode((string) $response->getBody(), true);

            if ($status >= 500) {
                $lastError = new RuntimeException("provider returned HTTP $status");
                if ($attempt < 2) {
                    usleep(300_000);
                }
                continue;
            }

            $message = self::classifyFailure($status, is_array($decoded) ? $decoded : null);
            if ($message !== null) {
                throw new GenerationFailedException($message);
            }

            $text = self::extractText($decoded);
            if ($text !== null) {
                return $text;
            }
            throw new GenerationFailedException('provider returned no text content');
        }

        throw new GenerationFailedException('generation failed after retries', 0, $lastError);
    }

    /*
    |--------------------------------------------------------------------------
    | Classification (TO-02)
    |--------------------------------------------------------------------------
    */

    /** TO-02 classification half. Single attempt, no retry — same fail-closed contract as Anthropic's. */
    public function classify(string $contribution): string
    {
        $sanitized = self::stripDelimiterTag($contribution);
        $prompt = str_replace('{{CONTRIBUTION}}', $sanitized, $this->moderationPromptTemplate);

        $decoded = $this->requestJsonSchema($this->classificationModel, $prompt, 64, 8.0, 'classification', [
            'type' => 'object',
            'properties' => [
                'classification' => [
                    'type' => 'string',
                    'enum' => ['suitable', 'contains-personal-information', 'targets-real-person'],
                ],
            ],
            'required' => ['classification'],
            'additionalProperties' => false,
        ]);

        $text = self::extractText($decoded);
        $data = $text !== null ? json_decode($text, true) : null;
        if (is_array($data) && isset($data['classification']) && is_string($data['classification'])) {
            return $data['classification'];
        }

        throw new RuntimeException('classification response missing a valid classification');
    }

    /*
    |--------------------------------------------------------------------------
    | Title generation
    |--------------------------------------------------------------------------
    */

    /** Same fallback-on-failure contract as AnthropicLlmClient::generateTitle(). */
    public function generateTitle(string $contribution): string
    {
        $sanitized = self::stripDelimiterTag($contribution);
        $prompt = str_replace('{{CONTRIBUTION}}', $sanitized, $this->titlePromptTemplate);

        $decoded = $this->requestJsonSchema($this->classificationModel, $prompt, 64, 8.0, 'title', [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ]);

        $text = self::extractText($decoded);
        $data = $text !== null ? json_decode($text, true) : null;
        if (is_array($data) && isset($data['title']) && is_string($data['title']) && trim($data['title']) !== '') {
            return trim($data['title']);
        }

        throw new RuntimeException('title response missing a valid title');
    }

    /*
    |--------------------------------------------------------------------------
    | Request helpers
    |--------------------------------------------------------------------------
    */

    /** Shared single-attempt structured-output request for classify()/generateTitle(). */
    private function requestJsonSchema(string $model, string $prompt, int $maxTokens, float $timeout, string $schemaName, array $schema): array
    {
        $response = $this->http->request('POST', 'chat/completions', [
            'json' => [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => $schemaName, 'schema' => $schema, 'strict' => true],
                ],
            ],
            'timeout' => $timeout,
        ]);

        $decoded = json_decode((string) $response->getBody(), true);
        $message = self::classifyFailure($response->getStatusCode(), is_array($decoded) ? $decoded : null);
        if ($message !== null) {
            throw new GenerationFailedException($message);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /*
    |--------------------------------------------------------------------------
    | Pure response helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Maps an HTTP status + decoded body to a GenerationFailedException message,
     * or null when the call should be treated as a (potential) success — i.e.
     * the caller should go read the content. Pure/static so it's unit-testable
     * without a live HTTP call; see tests/smoke_llm_client.php.
     */
    public static function classifyFailure(int $status, ?array $decoded): ?string
    {
        if ($status === 429) {
            return 'provider rate limit or quota exhausted';
        }
        if ($status >= 400) {
            return 'provider rejected the request';
        }
        if (self::finishReason($decoded) === 'content_filter') {
            return 'provider declined the request (content policy)';
        }
        return null;
    }

    /** Pure helper: choices[0].finish_reason, or null if absent/malformed. */
    public static function finishReason(?array $decoded): ?string
    {
        $reason = $decoded['choices'][0]['finish_reason'] ?? null;
        return is_string($reason) ? $reason : null;
    }

    /** Pure helper: choices[0].message.content, or null if absent/malformed. */
    public static function extractText(?array $decoded): ?string
    {
        $text = $decoded['choices'][0]['message']['content'] ?? null;
        return is_string($text) ? $text : null;
    }
}
