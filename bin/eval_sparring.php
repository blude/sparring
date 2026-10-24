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
 *                              [--reps=N] [--iteration=N] [--baseline] [--thinking]
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
 *   --thinking        Run the Sparring turn with extended thinking on (production
 *                     keeps it off, ADR 0012). Write to its own --iteration and
 *                     compare against a no-thinking iteration on the same prompt
 *                     hash — see README "Thinking arm". Not combinable with --baseline.
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
        "Usage: php bin/eval_sparring.php [--scenario=<id>[,<id>...]] [--turns=N] [--reps=N] [--iteration=N] [--baseline] [--thinking]\n"
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
$thinking = in_array('--thinking', $argv, true);
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
if ($baseline && $thinking) {
    // The no-prompt arm with thinking on would be a third experiment, not a comparison arm.
    fwrite(STDERR, "--thinking and --baseline can't be combined.\n");
    exit(1);
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

// Which prompt this run measured, stamped into every transcript so a later
// iteration can be diffed against the right version. The content hash is the
// ground truth (it also catches uncommitted edits); the commit is a
// human-friendly pointer and is null if git isn't available. The baseline arm
// sends no prompt, but is stamped too so an iteration's arms stay comparable.
$promptSource = (string) file_get_contents(SPARRING_PROMPT_PATH);
$gitDir = escapeshellarg(dirname(SPARRING_PROMPT_PATH));
$promptFile = escapeshellarg(SPARRING_PROMPT_PATH);
$promptCommit = trim((string) shell_exec("git -C {$gitDir} log -1 --format=%h -- {$promptFile} 2>/dev/null"));
$promptDirty = trim((string) shell_exec("git -C {$gitDir} status --porcelain -- {$promptFile} 2>/dev/null")) !== '';
$promptInfo = [
    'sha256' => substr(hash('sha256', $promptSource), 0, 12),
    'lastCommit' => $promptCommit !== '' ? $promptCommit : null,
    'uncommittedChanges' => $promptDirty,
];

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
 * --thinking arm: the same payload AnthropicLlmClient::generateResponse() sends
 * (cached system block + history), but with thinking on. Lives here instead of
 * as a flag on the production client so ADR 0012's disabled-thinking path stays
 * untouched. maxTokens is raised from production's 1024 because thinking tokens
 * count against it and starving the reply text would confound the comparison.
 *
 * @return array{text: string, thinking: string}
 */
function thinkingGenerate(Client $client, string $sparringPrompt, array $priorExchanges, string $newContribution): array
{
    $response = $client->messages->create(
        model: GENERATION_MODEL,
        maxTokens: 4096,
        system: AnthropicLlmClient::buildSystemBlocks($sparringPrompt, null),
        messages: buildMessages($priorExchanges, $newContribution),
        // Longer than production's 20s bound: thinking adds latency, and a timeout here would drop the turn.
        requestOptions: ['timeout' => 60.0, 'maxRetries' => 0],
        // display: this model omits thinking text by default (the block comes back with an empty string,
        // though the thinking still runs and is billed); 'summarized' returns it so the transcript can log it.
        thinking: ['type' => 'adaptive', 'display' => 'summarized'],
    );
    $text = null;
    $thought = '';
    foreach ($response->content as $block) {
        if ($block->type === 'thinking') {
            $thought .= $block->thinking;
        } elseif ($block->type === 'text' && $text === null) {
            $text = $block->text;
        }
    }
    if ($text === null) {
        throw new RuntimeException('thinking generation returned no text content');
    }
    return ['text' => $text, 'thinking' => $thought];
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
            $thought = null;
            try {
                if ($thinking) {
                    ['text' => $sparringResponse, 'thinking' => $thought] =
                        thinkingGenerate($rawClient, $promptSource, $priorExchanges, $contribution);
                } else {
                    $sparringResponse = $baseline
                        ? baselineGenerate($rawClient, $priorExchanges, $contribution)
                        : $llm->generateResponse($priorExchanges, $contribution);
                }
            } catch (Throwable $e) {
                fwrite(STDERR, "[{$scenario['id']}][{$armLabel}-{$rep}][turn {$turn}] generation failed: {$e->getMessage()}\n");
                break;
            }

            $exchange = ['visitorContribution' => $contribution, 'sparringResponse' => $sparringResponse];
            $priorExchanges[] = $exchange;
            // Logged only, never fed back as history (buildMessages reads the two keys above) —
            // production wouldn't replay thinking text to the model either.
            $turnLog[] = $thought === null ? $exchange : $exchange + ['thinking' => $thought];

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
            'thinking' => $thinking,
            'rep' => $rep,
            'runAt' => date('c'),
            'prompt' => $promptInfo,
            'persona' => $scenario['persona'] ?? null,
            'directive' => $scenario['directive'] ?? null,
            'exchanges' => $turnLog,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $totalRuns++;
        fwrite(STDERR, "[{$scenario['id']}][{$armLabel}-{$rep}] " . count($turnLog) . "/{$turns} turns -> {$outDir}/transcript.json\n");
    }
}

fwrite(STDERR, "Done. {$totalRuns} transcript(s) written under {$workspace}\n");
