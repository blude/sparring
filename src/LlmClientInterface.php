<?php
declare(strict_types=1);

/** TF-01 FS-01-8/FA-01-1: every generation failure the caller sees is this one type. */
final class GenerationFailedException extends RuntimeException
{
}

/**
 * PE-01's contract, independent of which provider (Anthropic, OpenAI, or an
 * OpenAI-compatible local server such as LM Studio) actually serves it —
 * see AnthropicLlmClient / OpenAiLlmClient and config.php's createLlmClient().
 */
interface LlmClientInterface
{
    /** TO-01. $priorExchanges: ordered list of ['visitorContribution' => ..., 'sparringResponse' => ...]. */
    public function generateResponse(array $priorExchanges, string $newContribution): string;

    /** TO-02 classification half. Returns one of 'suitable'|'contains-personal-information'|'targets-real-person'. */
    public function classify(string $contribution): string;

    /** Short (TITLE_MAX_CHARS) header title derived from a session's first contribution. */
    public function generateTitle(string $contribution): string;
}
