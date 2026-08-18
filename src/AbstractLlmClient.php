<?php
declare(strict_types=1);

require_once __DIR__ . '/LlmClientInterface.php';

/**
 * Shared, provider-agnostic bits of LlmClientInterface: loading prompts/*.md
 * and stripping the classify()/generateTitle() delimiter tag (C-06). The
 * actual API calls (generateResponse/classify/generateTitle) stay in each
 * subclass — Anthropic SDK vs raw HTTP differ enough there that sharing more
 * than this would hide the difference rather than remove real duplication.
 */
abstract class AbstractLlmClient implements LlmClientInterface
{
    // C-06: name of the wrapper tag prompts/moderation.md and prompts/title.md
    // use around {{CONTRIBUTION}}. Kept as a constant so it can't drift silently.
    protected const DELIMITER_TAG = 'contribution';

    protected string $sparringPrompt;
    protected string $moderationPromptTemplate;
    protected string $titlePromptTemplate;

    protected function loadPrompts(): void
    {
        $this->sparringPrompt = (string) file_get_contents(SPARRING_PROMPT_PATH);
        $this->moderationPromptTemplate = (string) file_get_contents(MODERATION_PROMPT_PATH);
        $this->titlePromptTemplate = (string) file_get_contents(TITLE_PROMPT_PATH);
    }

    /**
     * Strips lookalike delimiter tags — any case, any internal whitespace
     * (</contribution>, </CONTRIBUTION>, </ contribution >, ...) — so a
     * visitor can't close the prompt's <contribution> wrapper early and
     * splice instructions after it. Scoped to classify()/generateTitle()
     * prompts only: the stored/displayed contribution is never touched.
     */
    protected static function stripDelimiterTag(string $contribution): string
    {
        $tag = preg_quote(static::DELIMITER_TAG, '/');
        return (string) preg_replace('/<\/?\s*' . $tag . '\s*>/i', '', $contribution);
    }
}
