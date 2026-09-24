<?php
declare(strict_types=1);

require_once __DIR__ . '/LlmClientInterface.php';

/**
 * LLM_PROVIDER=fake: a deterministic, offline stand-in for the real
 * providers, so the whole request flow (API endpoints, Sparring, Store, the
 * phone and wall clients) runs without an API key, network or cost. For
 * local UI work and tests/smoke_http.php, never for the exhibition.
 *
 * Every reply is prefixed "(fake)" so a fake exchange can't pass for a real
 * one on the wall. Markers in a contribution trigger the failure paths a
 * real provider only produces unpredictably:
 *
 *   [fake:personal]          classify() → contains-personal-information
 *   [fake:real-person]       classify() → targets-real-person
 *   [fake:classify-error]    classify() throws (moderation fails closed)
 *   [fake:generation-error]  generateResponse() throws (generation-failed)
 *
 * FAKE_LLM_DELAY_MS (env, default 0) delays generateResponse(), to see the
 * clients' waiting states.
 */
final class FakeLlmClient implements LlmClientInterface
{
    public const RESPONSE_PREFIX = '(fake)';
    private const DELAY_ENV = 'FAKE_LLM_DELAY_MS';

    public function generateResponse(array $priorExchanges, string $newContribution, ?string $groundingContext = null): string
    {
        if (str_contains($newContribution, '[fake:generation-error]')) {
            throw new GenerationFailedException('fake generation failure');
        }

        $delayMs = (int) (getenv(self::DELAY_ENV) ?: 0);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $turn = count($priorExchanges) + 1;
        return sprintf(
            '%s Turn %d. You said "%s". What would have to be true for the opposite to hold?',
            self::RESPONSE_PREFIX,
            $turn,
            self::excerpt($newContribution, 60)
        );
    }

    public function classify(string $contribution): string
    {
        if (str_contains($contribution, '[fake:classify-error]')) {
            throw new RuntimeException('fake classification failure');
        }
        return match (true) {
            str_contains($contribution, '[fake:personal]') => 'contains-personal-information',
            str_contains($contribution, '[fake:real-person]') => 'targets-real-person',
            default => 'suitable',
        };
    }

    public function generateTitle(string $contribution): string
    {
        return self::RESPONSE_PREFIX . ' ' . self::excerpt($contribution, 30);
    }

    private static function excerpt(string $text, int $maxChars): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) > $maxChars ? mb_substr($text, 0, $maxChars) . '...' : $text;
    }
}
