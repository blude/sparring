<?php
declare(strict_types=1);

/**
 * TF-05: the scenario line shown above an exchange. Initial approach is a bounded
 * trim of the opening contribution — costs nothing, no provider call. If trimmed
 * openers read badly against real transcripts, the fallback is a single generation
 * call, written once to E-01.5 and never recomputed (that recompute-per-render
 * mistake would multiply provider cost by the polling rate).
 */
function derive_scenario_statement(string $contribution, int $maxChars = SCENARIO_MAX_CHARS): string
{
    $trimmed = trim(preg_replace('/\s+/', ' ', $contribution) ?? $contribution);
    if (mb_strlen($trimmed) <= $maxChars) {
        return $trimmed;
    }
    return mb_substr($trimmed, 0, $maxChars - 1) . '…';
}
