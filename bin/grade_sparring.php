<?php
declare(strict_types=1);

/**
 * Grades transcript.json files produced by bin/eval_sparring.php.
 *
 * Mechanical checks (banned phrases, length, question marks, Mermaid fence
 * validity) run first, in plain code, free and zero-variance. Judged checks
 * (evals/sparring/rubric.md's interpretive items, plus per-turn Socratic
 * move labeling from evals/sparring/socratic-moves.json) go through one
 * structured-output LLM call per transcript. Both land in the same
 * grading.json, `expectations: [{text, passed, evidence}]` — the exact
 * field names the skill-creator eval-viewer depends on.
 *
 * Usage: php bin/grade_sparring.php <workspace-dir>
 *   e.g. php bin/grade_sparring.php evals/sparring/sparring-workspace/iteration-1
 *
 * Walks every transcript.json found anywhere under the given directory and
 * writes a sibling grading.json next to each one.
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../vendor/autoload.php';

use Anthropic\Client;

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true) || !isset($argv[1])) {
    exit("Usage: php bin/grade_sparring.php <workspace-dir>\n");
}

$key = getenv(ANTHROPIC_API_KEY_ENV) ?: null;
if ($key === null || $key === '') {
    fwrite(STDERR, "ANTHROPIC_API_KEY is not set — the judge pass needs it too.\n");
    exit(1);
}

$workspaceDir = rtrim($argv[1], '/');
if (!is_dir($workspaceDir)) {
    fwrite(STDERR, "No such directory: {$workspaceDir}\n");
    exit(1);
}

$rubric = (string) file_get_contents(__DIR__ . '/../evals/sparring/rubric.md');
$moves = json_decode((string) file_get_contents(__DIR__ . '/../evals/sparring/socratic-moves.json'), true);
$moveKeys = array_column($moves['socratic_moves'], 'key');
$moveKeys = array_merge($moveKeys, array_column($moves['sparring_moves'], 'key'));

$client = new Client(apiKey: $key);

/*
|--------------------------------------------------------------------------
| Mechanical checks — plain code, zero variance
|--------------------------------------------------------------------------
*/

// Mirrors public/assets/mermaid-render.js's SparringMermaid.extract() regex
// exactly (tests/smoke_mermaid.js exercises the JS original) — a fence is
// well-formed if this matches; anything else (no fence, or an unterminated
// one) is fine too, since the client falls back to plain text either way.
function mermaidFenceOk(string $text): bool
{
    if (!str_contains($text, '```mermaid')) {
        return true; // no fence attempted — nothing to validate
    }
    return (bool) preg_match('/```mermaid\r?\n([\s\S]*?)\r?\n```/', $text);
}

function mechanicalChecks(array $exchanges): array
{
    $sparringTurns = array_column($exchanges, 'sparringResponse');

    $bannedFail = ['load-bearing', 'failure mode'];
    $failures = [];
    foreach ($bannedFail as $phrase) {
        foreach ($sparringTurns as $i => $turn) {
            if (stripos($turn, $phrase) !== false) {
                $failures[$phrase][] = $i + 1;
            }
        }
    }

    $moveFlags = [];
    foreach ($sparringTurns as $i => $turn) {
        // whole-word match — "move" is fine inside e.g. "movement"
        if (preg_match('/\bmoves?\b/i', $turn) && !preg_match('/\bchess\b/i', $turn)) {
            $moveFlags[] = $i + 1;
        }
    }

    $lengthFailures = [];
    $questionFlags = [];
    $fenceFailures = [];
    foreach ($sparringTurns as $i => $turn) {
        $paragraphs = array_filter(array_map('trim', preg_split('/\n\s*\n/', $turn)));
        $words = str_word_count($turn);
        if (count($paragraphs) > 2 || $words > 140) { // 140: soft ~120-word ceiling + buffer
            $lengthFailures[] = $i + 1;
        }
        if (substr_count($turn, '?') > 1) {
            $questionFlags[] = $i + 1;
        }
        if (!mermaidFenceOk($turn)) {
            $fenceFailures[] = $i + 1;
        }
    }

    $mk = fn(string $text, array $failingTurns, string $evidenceNoun) => [
        'text' => $text,
        'passed' => $failingTurns === [],
        'evidence' => $failingTurns === []
            ? 'clean across all turns'
            : "turn(s) " . implode(', ', $failingTurns) . " — {$evidenceNoun}",
    ];

    return [
        $mk("no banned phrase 'load-bearing'", $failures['load-bearing'] ?? [], "contains 'load-bearing'"),
        $mk("no banned phrase 'failure mode'", $failures['failure mode'] ?? [], "contains 'failure mode'"),
        $mk("[flag] word 'move(s)' used outside a chess context", $moveFlags, "uses 'move'/'moves' — check it's not standing in for decision/step/action"),
        $mk('length fits phone/wall reading (≤2 paragraphs, ≤~120 words)', $lengthFailures, 'turn runs long'),
        $mk('[flag] at most one question per turn', $questionFlags, 'turn asks more than one question'),
        $mk('well-formed Mermaid fence when one is attempted', $fenceFailures, 'unterminated or malformed ```mermaid fence'),
    ];
}

/*
|--------------------------------------------------------------------------
| Judge pass — one structured-output call per transcript
|--------------------------------------------------------------------------
*/

function judgeSchema(array $moveKeys): array
{
    return [
        'type' => 'object',
        'properties' => [
            'moveLabels' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'turn' => ['type' => 'integer'],
                        'move' => ['type' => 'string', 'enum' => $moveKeys],
                    ],
                    'required' => ['turn', 'move'],
                    'additionalProperties' => false,
                ],
            ],
            'findings' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'item' => [
                            'type' => 'string',
                            'enum' => [
                                'no-handed-over-conclusion',
                                'held-position',
                                'premature-closure',
                                'no-meta-commentary',
                                'domain-grounding',
                                'voice-style',
                            ],
                        ],
                        'passed' => ['type' => 'boolean'],
                        'evidence' => ['type' => 'string'],
                        'state' => ['type' => ['string', 'null']], // for held-position: held|obstruction|capitulated
                    ],
                    'required' => ['item', 'passed', 'evidence', 'state'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['moveLabels', 'findings'],
        'additionalProperties' => false,
    ];
}

function judgeTranscript(Client $client, string $rubric, array $moveKeys, array $transcript): array
{
    $lines = [];
    foreach ($transcript['exchanges'] as $i => $exchange) {
        $lines[] = 'Turn ' . ($i + 1) . " — Visitor: {$exchange['visitorContribution']}";
        $lines[] = 'Turn ' . ($i + 1) . " — Sparring: {$exchange['sparringResponse']}";
    }
    $transcriptText = implode("\n", $lines);

    $prompt = $rubric . "\n\n---\n\nTranscript to grade (scenario: {$transcript['scenarioId']}):\n\n" . $transcriptText;

    $response = $client->messages->create(
        model: GENERATION_MODEL,
        maxTokens: 2048,
        messages: [['role' => 'user', 'content' => $prompt]],
        requestOptions: ['timeout' => (float) GENERATION_TIMEOUT_SECONDS, 'maxRetries' => 0],
        thinking: ['type' => 'disabled'],
        outputConfig: [
            'format' => [
                'type' => 'json_schema',
                'schema' => judgeSchema($moveKeys),
            ],
        ],
    );

    foreach ($response->content as $block) {
        if ($block->type === 'text') {
            $data = json_decode($block->text, true);
            if (is_array($data)) {
                return $data;
            }
        }
    }
    throw new RuntimeException('judge returned no valid structured output');
}

/**
 * Adjacent-turn move repetition, computed on the judge's labels — checked
 * here in plain code rather than asked of the judge holistically, per
 * rubric.md item 5: classifying into the closed 14-item vocabulary is far
 * more stable than a holistic "was there variety?" judgment.
 */
function moveVarietyFinding(array $moveLabels): array
{
    $byTurn = [];
    foreach ($moveLabels as $entry) {
        $byTurn[$entry['turn']] = $entry['move'];
    }
    ksort($byTurn);
    $repeats = [];
    $prevTurn = null;
    $prevMove = null;
    foreach ($byTurn as $turn => $move) {
        if ($prevMove !== null && $move === $prevMove) {
            $repeats[] = "{$prevTurn}→{$turn}: {$move}";
        }
        $prevTurn = $turn;
        $prevMove = $move;
    }
    return [
        'text' => 'no repeated Socratic/Sparring move on adjacent turns',
        'passed' => $repeats === [],
        'evidence' => $repeats === []
            ? 'labels: ' . implode(', ', array_map(fn($t, $m) => "{$t}:{$m}", array_keys($byTurn), $byTurn))
            : 'repeated on ' . implode('; ', $repeats),
    ];
}

/*
|--------------------------------------------------------------------------
| Walk the workspace
|--------------------------------------------------------------------------
*/

$transcriptFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspaceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getFilename() === 'transcript.json') {
        $transcriptFiles[] = $file->getPathname();
    }
}
sort($transcriptFiles);

if ($transcriptFiles === []) {
    fwrite(STDERR, "No transcript.json files found under {$workspaceDir}\n");
    exit(1);
}

foreach ($transcriptFiles as $path) {
    $transcript = json_decode((string) file_get_contents($path), true);
    if (!is_array($transcript) || empty($transcript['exchanges'])) {
        fwrite(STDERR, "Skipping empty/unreadable transcript: {$path}\n");
        continue;
    }

    $expectations = mechanicalChecks($transcript['exchanges']);

    if (!$transcript['baseline']) {
        // Judge rubric applies to the real-prompt arm — grading the
        // no-prompt baseline against sparring.md's own rules is meaningless.
        try {
            $judged = judgeTranscript($client, $rubric, $moveKeys, $transcript);
            $expectations[] = moveVarietyFinding($judged['moveLabels'] ?? []);
            foreach ($judged['findings'] ?? [] as $finding) {
                $evidence = $finding['evidence'];
                if (!empty($finding['state'])) {
                    $evidence = "[{$finding['state']}] {$evidence}";
                }
                $expectations[] = [
                    'text' => $finding['item'],
                    'passed' => $finding['passed'],
                    'evidence' => $evidence,
                ];
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "Judge failed for {$path}: {$e->getMessage()}\n");
        }
    }

    $gradingPath = dirname($path) . '/grading.json';
    file_put_contents($gradingPath, json_encode(
        ['expectations' => $expectations],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ));
    $passCount = count(array_filter($expectations, fn($e) => $e['passed']));
    fwrite(STDERR, "{$path} -> {$gradingPath} ({$passCount}/" . count($expectations) . " passed)\n");
}
