<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;

/** TF-01 FS-01-8/FA-01-1: every generation failure the caller sees is this one type. */
final class GenerationFailedException extends RuntimeException
{
}

/**
 * Thin wrapper over anthropic-ai/sdk for TO-01 (generation) and TO-02
 * (classification). Holds no policy of its own — call behaviour (retries,
 * what's retryable) matches what TF-01/TF-02 specify, not a generic default.
 */
final class LlmClient
{
    // C-06: name of the wrapper tag prompts/moderation.md uses around
    // {{CONTRIBUTION}} (its <contribution>/</contribution> lines). Kept as a
    // constant, referenced from classify() below, so the two can't drift silently.
    private const DELIMITER_TAG = 'contribution';

    private Client $client;
    private string $sparringPrompt;
    private string $moderationPromptTemplate;

    public function __construct(?string $apiKey = null)
    {
        $key = $apiKey ?? (getenv(ANTHROPIC_API_KEY_ENV) ?: null);
        if ($key === null || $key === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set (QR-04: never in config.php or version control)');
        }
        $this->client = new Client(apiKey: $key);
        $this->sparringPrompt = (string) file_get_contents(SPARRING_PROMPT_PATH);
        $this->moderationPromptTemplate = (string) file_get_contents(MODERATION_PROMPT_PATH);
    }

    /**
     * TO-01. $priorExchanges: ordered list of ['visitorContribution' => ..., 'sparringResponse' => ...].
     * Two attempts with a short delay, on transport failure / timeout / provider 5xx only.
     * No retry on rate-limit or quota (retrying deepens it) or on a content-policy refusal
     * (determinate outcome). Every failure path throws — caller writes nothing (QR-07).
     *
     * maxRetries is forced to 0: TF-01 FS-01-8 specifies exactly two attempts with no retry
     * on certain conditions, so the SDK's own default retry (2 more, on top of ours, with no
     * visibility into which error triggered it) would fight that policy rather than serve it.
     */
    public function generateResponse(array $priorExchanges, string $newContribution): string
    {
        $messages = [];
        foreach ($priorExchanges as $exchange) {
            $messages[] = ['role' => 'user', 'content' => $exchange['visitorContribution']];
            $messages[] = ['role' => 'assistant', 'content' => $exchange['sparringResponse']];
        }
        $messages[] = ['role' => 'user', 'content' => $newContribution];

        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = $this->client->messages->create(
                    model: GENERATION_MODEL,
                    maxTokens: 1024,
                    system: $this->sparringPrompt,
                    messages: $messages,
                    requestOptions: ['timeout' => (float) GENERATION_TIMEOUT_SECONDS, 'maxRetries' => 0],
                    // Model default-enables extended thinking. Measured across a full
                    // 10-turn session: it fires on ~70% of turns as context grows, costs
                    // 60-110 tokens each time (billed, never shown to the visitor), stays
                    // well inside the 1024 budget either way (worst case seen: 305/1024),
                    // and produces no visible difference in the Sparring partner's replies
                    // — the persona prompt already caps them to a few sentences. Disabled
                    // for the wasted cost, not because it was starving output.
                    thinking: ['type' => 'disabled'],
                );
            } catch (RateLimitException $e) {
                throw new GenerationFailedException('provider rate limit or quota exhausted', 0, $e);
            } catch (InternalServerException|APIConnectionException $e) {
                $lastError = $e;
                if ($attempt < 2) {
                    usleep(300_000);
                }
                continue;
            } catch (APIStatusException $e) {
                throw new GenerationFailedException('provider rejected the request', 0, $e);
            }

            if ($response->stopReason === 'refusal') {
                throw new GenerationFailedException('provider declined the request (content policy)');
            }

            foreach ($response->content as $block) {
                if ($block->type === 'text') {
                    return $block->text;
                }
            }
            throw new GenerationFailedException('provider returned no text content');
        }

        throw new GenerationFailedException('generation failed after retries', 0, $lastError);
    }

    /**
     * TO-02 classification half. Single attempt, no retry — TF-02 fails closed on
     * ANY failure, so every failure mode (network, 4xx/5xx, malformed response) is
     * left to propagate as an exception; the caller (Sparring::assessSuitability)
     * is the one and only place that maps "couldn't classify" to "unsuitable".
     */
    public function classify(string $contribution): string
    {
        // C-06: strip lookalike delimiter tags — any case, any internal whitespace
        // (</contribution>, </CONTRIBUTION>, </ contribution >, ...) — so a visitor
        // can't close the prompt's <contribution> wrapper early and splice
        // instructions after it. Scoped to this classification prompt only: the
        // stored/displayed contribution (Sparring::processTurn) is never touched,
        // so a visitor legitimately typing the literal string isn't silently edited.
        $tag = preg_quote(self::DELIMITER_TAG, '/');
        $sanitized = preg_replace('/<\/?\s*' . $tag . '\s*>/i', '', $contribution);

        $prompt = str_replace('{{CONTRIBUTION}}', $sanitized, $this->moderationPromptTemplate);

        $response = $this->client->messages->create(
            model: CLASSIFICATION_MODEL,
            maxTokens: 64,
            messages: [['role' => 'user', 'content' => $prompt]],
            requestOptions: ['timeout' => 8.0, 'maxRetries' => 0], // FS-02-2: short timeout, single attempt
            // Model default-enables extended thinking, which alone can exceed the
            // 64-token budget and leave zero room for the actual classification —
            // stop_reason comes back max_tokens with no text block, which
            // assessSuitability's fail-closed catch (FS-02-2) then reads as
            // unsuitable. Disable; a fixed three-value enum doesn't need it.
            thinking: ['type' => 'disabled'],
            outputConfig: [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'classification' => [
                                'type' => 'string',
                                'enum' => ['suitable', 'contains-personal-information', 'targets-real-person'],
                            ],
                        ],
                        'required' => ['classification'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        );

        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data) && isset($data['classification']) && is_string($data['classification'])) {
                    return $data['classification'];
                }
                break;
            }
        }

        throw new RuntimeException('classification response missing a valid classification');
    }
}
