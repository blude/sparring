<?php
declare(strict_types=1);

/**
 * Multi-turn eval runner for prompts/sparring.md (evals/sparring/). Drives a
 * simulated "visitor" persona against the real Sparring turn — generated
 * through AnthropicLlmClient::generateResponse(), i.e. the exact system
 * prompt + growing-history payload production sends — so the thing under
 * test runs production code. Sparring::processTurn() is deliberately NOT
 * used here: its RateLimiter/moderation/600-char/session gates would
 * measure those gates under CLI conditions they weren't built for, not
 * prompts/sparring.md itself (see evals/sparring/README.md).
 *
 * Visitor and judge roles call the vendored Anthropic SDK directly — only
 * the Sparring turn goes through production code, the eval scaffolding
 * doesn't need to.
 *
 * Usage:
 *   php bin/eval_sparring.php [--scenario=<id>[,<id>...]] [--turns=N]
 *                              [--reps=N] [--iteration=N] [--baseline]
 *
 *   --scenario=<id>  Comma-separated scenario ids (evals/sparring/scenarios/<id>.json).
 *                     Default: every scenario file.
 *   --turns=N         Override each scenario's own "turns" count.
 *   --reps=N          Independent repetitions per scenario (default 3 — see
 *                     README: three nondeterministic layers stack, a single
 *                     run's pass/fail is noise).
 *   --iteration=N     Output subdirectory iteration-<N> (default 1).
 *   --baseline        Run with the system prompt omitted (the no-prompt
 *                     comparison arm). Forces exactly 1 rep regardless of
 *                     --reps — see README for why.
 *
 * Smoke path before a full paid run: --scenario=<id> --turns=2 --reps=1
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/AnthropicLlmClient.php';

use Anthropic\Client;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit(
        "Usage: php bin/eval_sparring.php [--scenario=<id>[,<id>...]] [--turns=N] [--reps=N] [--iteration=N] [--baseline]\n"
    );
}

$key = getenv(ANTHROPIC_API_KEY_ENV) ?: null;
if ($key === null || $key === '') {
    fwrite(STDERR, "ANTHROPIC_API_KEY is not set — export it before running a live eval (this makes real, billed API calls).\n");
    exit(1);
}

$scenarioFilter = null;
$turnsOverride = null;
$reps = 3;
$iteration = 1;
$baseline = in_array('--baseline', $argv, true);
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--scenario=')) {
        $scenarioFilter = explode(',', substr($arg, strlen('--scenario=')));
    } elseif (str_starts_with($arg, '--turns=')) {
        $turnsOverride = (int) substr($arg, strlen('--turns='));
    } elseif (str_starts_with($arg, '--reps=')) {
        $reps = (int) substr($arg, strlen('--reps='));
    } elseif (str_starts_with($arg, '--iteration=')) {
        $iteration = (int) substr($arg, strlen('--iteration='));
    }
}
if ($baseline) {
    $reps = 1; // no-prompt arm is low-information by design (README) — kept cheap
}

$scenariosDir = __DIR__ . '/../evals/sparring/scenarios';
$scenarioFiles = glob($scenariosDir . '/*.json') ?: [];
sort($scenarioFiles);
if ($scenarioFilter !== null) {
    $scenarioFiles = array_values(array_filter(
        $scenarioFiles,
        fn(string $path) => in_array(basename($path, '.json'), $scenarioFilter, true)
    ));
}
if ($scenarioFiles === []) {
    fwrite(STDERR, "No matching scenario files in {$scenariosDir}\n");
    exit(1);
}

$llm = new AnthropicLlmClient($key); // real Sparring turn — production code, unmodified prompts/sparring.md
$rawClient = new Client(apiKey: $key); // visitor simulation + baseline arm — scaffolding, not under test

$workspace = __DIR__ . "/../evals/sparring/sparring-workspace/iteration-{$iteration}";

/**
 * From-scratch messages array, same shape AnthropicLlmClient::generateResponse()
 * builds internally — used for the --baseline arm, where we need the identical
 * payload minus the system prompt (AnthropicLlmClient always sends one).
 */
function buildMessages(array $priorExchanges, string $newContribution): array
{
    $messages = [];
    foreach ($priorExchanges as $exchange) {
        $messages[] = ['role' => 'user', 'content' => $exchange['visitorContribution']];
        $messages[] = ['role' => 'assistant', 'content' => $exchange['sparringResponse']];
    }
    $messages[] = ['role' => 'user', 'content' => $newContribution];
    return $messages;
}

function baselineGenerate(Client $client, array $priorExchanges, string $newContribution): string
{
    $response = $client->messages->create(
        model: GENERATION_MODEL,
        maxTokens: 1024,
        messages: buildMessages($priorExchanges, $newContribution),
        requestOptions: ['timeout' => (float) GENERATION_TIMEOUT_SECONDS, 'maxRetries' => 0],
        thinking: ['type' => 'disabled'],
        // no `system` — this is the whole point of the baseline arm
    );
    foreach ($response->content as $block) {
        if ($block->type === 'text') {
            return $block->text;
        }
    }
    throw new RuntimeException('baseline generation returned no text content');
}

/**
 * Single-message visitor simulation: the full transcript-so-far is rendered
 * as plain text in one 'user' message rather than role-flipped multi-turn
 * messages, so there's no alternation/first-role constraint to get wrong —
 * the persona and formatting rules live entirely in the system prompt.
 */
function nextVisitorTurn(Client $client, array $scenario, array $priorExchanges): string
{
    $transcript = '';
    foreach ($priorExchanges as $exchange) {
        $transcript .= "Visitor: {$exchange['visitorContribution']}\nSparring: {$exchange['sparringResponse']}\n\n";
    }

    $system = "You are role-playing a visitor at a public AI-sparring exhibition kiosk, arguing with an AI called "
        . "Sparring about a Digital Design topic.\n\n"
        . "Persona: {$scenario['persona']}\n"
        . "Directive: {$scenario['directive']}\n\n"
        . 'Stay fully in character as this visitor. Never mention you are an AI, a simulation, or a test. '
        . 'Reply with only the visitor\'s next message, in plain conversational prose, under 500 characters. '
        . 'No preamble, no quotation marks, no meta-commentary — just the message itself.';

    $response = $client->messages->create(
        model: GENERATION_MODEL,
        maxTokens: 256,
        system: $system,
        messages: [['role' => 'user', 'content' => trim($transcript) . "\n\nWrite only your (the visitor's) next message now."]],
        requestOptions: ['timeout' => (float) GENERATION_TIMEOUT_SECONDS, 'maxRetries' => 0],
        thinking: ['type' => 'disabled'],
    );
    foreach ($response->content as $block) {
        if ($block->type === 'text') {
            return trim($block->text);
        }
    }
    throw new RuntimeException('visitor simulation returned no text content');
}

$totalRuns = 0;
foreach ($scenarioFiles as $scenarioPath) {
    $scenario = json_decode((string) file_get_contents($scenarioPath), true);
    if (!is_array($scenario)) {
        fwrite(STDERR, "Skipping unreadable scenario file: {$scenarioPath}\n");
        continue;
    }
    $turns = $turnsOverride ?? (int) ($scenario['turns'] ?? 4);
    $armLabel = $baseline ? 'baseline' : 'rep';

    for ($rep = 1; $rep <= $reps; $rep++) {
        $priorExchanges = [];
        $contribution = $scenario['opener'];
        $turnLog = [];

        for ($turn = 1; $turn <= $turns; $turn++) {
            try {
                $sparringResponse = $baseline
                    ? baselineGenerate($rawClient, $priorExchanges, $contribution)
                    : $llm->generateResponse($priorExchanges, $contribution);
            } catch (Throwable $e) {
                fwrite(STDERR, "[{$scenario['id']}][{$armLabel}-{$rep}][turn {$turn}] generation failed: {$e->getMessage()}\n");
                break;
            }

            $exchange = ['visitorContribution' => $contribution, 'sparringResponse' => $sparringResponse];
            $priorExchanges[] = $exchange;
            $turnLog[] = $exchange;

            if ($turn < $turns) {
                try {
                    $contribution = nextVisitorTurn($rawClient, $scenario, $priorExchanges);
                } catch (Throwable $e) {
                    fwrite(STDERR, "[{$scenario['id']}][{$armLabel}-{$rep}][turn {$turn}] visitor simulation failed: {$e->getMessage()}\n");
                    break;
                }
            }
        }

        $outDir = $baseline
            ? "{$workspace}/{$scenario['id']}/baseline"
            : "{$workspace}/{$scenario['id']}/rep-{$rep}";
        if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
            fwrite(STDERR, "Could not create {$outDir}\n");
            continue;
        }
        file_put_contents($outDir . '/transcript.json', json_encode([
            'scenarioId' => $scenario['id'],
            'baseline' => $baseline,
            'rep' => $rep,
            'persona' => $scenario['persona'] ?? null,
            'directive' => $scenario['directive'] ?? null,
            'exchanges' => $turnLog,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $totalRuns++;
        fwrite(STDERR, "[{$scenario['id']}][{$armLabel}-{$rep}] " . count($turnLog) . "/{$turns} turns -> {$outDir}/transcript.json\n");
    }
}

fwrite(STDERR, "Done. {$totalRuns} transcript(s) written under {$workspace}\n");
