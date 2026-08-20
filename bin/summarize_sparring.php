<?php
declare(strict_types=1);

/**
 * Compact markdown summary over graded eval output — per scenario, per arm
 * (real prompt vs. --baseline), pass rate for each assertion across reps,
 * plus a top-line "core score" and an optional two-iteration diff.
 *
 * skill-creator's aggregate_benchmark.py/generate_review.py assume its own
 * eval-<id>/{with_skill,without_skill} single-shot A/B layout; this suite's
 * scenario-id/{rep-N,baseline}/ layout (built for multi-rep pass-rate
 * tracking across a multi-turn transcript, not a single-shot comparison)
 * doesn't map onto that cleanly, so this is a small purpose-built summary
 * instead of reshaping the run output to fit a tool built for a different
 * shape.
 *
 * Core score deliberately excludes `[flag]`-prefixed assertions (advisory —
 * question-mark count, "move" word use): blending those into one number
 * with the hard rubric items (banned phrases, Mermaid fence, and all 6
 * judge rubric items) dilutes signal — a prompt could look "worse" purely
 * because visitors asked more follow-up questions, unrelated to quality.
 * One core number plus the full breakdown underneath, not one number alone
 * — a drop in the core score should be immediately traceable to which
 * assertion moved.
 *
 * Usage:
 *   php bin/summarize_sparring.php <workspace-dir>
 *   php bin/summarize_sparring.php <workspace-dir> --compare=<other-workspace-dir>
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (!isset($argv[1]) || $argv[1] === '--help' || $argv[1] === '-h') {
    exit("Usage: php bin/summarize_sparring.php <workspace-dir> [--compare=<other-workspace-dir>]\n");
}

/**
 * path shape: <workspace>/<scenario-id>/<rep-N|baseline>/grading.json
 * Returns [scenario][armKey][assertionText] = ['pass' => n, 'total' => n],
 * armKey is 'prompt' (real sparring.md) or 'baseline' (--baseline arm).
 */
function collectGrading(string $workspaceDir): array
{
    $gradingFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspaceDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'grading.json') {
            $gradingFiles[] = $file->getPathname();
        }
    }
    sort($gradingFiles);

    $byScenarioArm = [];
    foreach ($gradingFiles as $path) {
        $parts = explode('/', $path);
        $arm = $parts[count($parts) - 2];
        $scenario = $parts[count($parts) - 3];
        $armKey = $arm === 'baseline' ? 'baseline' : 'prompt';

        $grading = json_decode((string) file_get_contents($path), true);
        foreach ($grading['expectations'] ?? [] as $expectation) {
            $byScenarioArm[$scenario][$armKey][$expectation['text']]['pass'] =
                ($byScenarioArm[$scenario][$armKey][$expectation['text']]['pass'] ?? 0) + ($expectation['passed'] ? 1 : 0);
            $byScenarioArm[$scenario][$armKey][$expectation['text']]['total'] =
                ($byScenarioArm[$scenario][$armKey][$expectation['text']]['total'] ?? 0) + 1;
        }
    }
    ksort($byScenarioArm);
    return $byScenarioArm;
}

function isFlag(string $assertionText): bool
{
    return str_starts_with($assertionText, '[flag]');
}

function rate(int $pass, int $total): int
{
    return $total > 0 ? (int) round(100 * $pass / $total) : 0;
}

/** Aggregate pass/total across every scenario for one arm, hard-fail assertions only. */
function coreScore(array $byScenarioArm, string $armKey): array
{
    $pass = 0;
    $total = 0;
    foreach ($byScenarioArm as $arms) {
        foreach ($arms[$armKey] ?? [] as $text => $counts) {
            if (isFlag($text)) {
                continue;
            }
            $pass += $counts['pass'];
            $total += $counts['total'];
        }
    }
    return ['pass' => $pass, 'total' => $total];
}

function printReport(array $byScenarioArm, string $workspaceDir): void
{
    echo "# Sparring eval summary — {$workspaceDir}\n\n";

    echo "## Core score (excludes [flag] advisory items)\n\n";
    foreach (['prompt' => 'with sparring.md', 'baseline' => 'baseline (no system prompt)'] as $armKey => $label) {
        $core = coreScore($byScenarioArm, $armKey);
        if ($core['total'] === 0) {
            continue;
        }
        echo "- {$label}: {$core['pass']}/{$core['total']} (" . rate($core['pass'], $core['total']) . "%)\n";
    }
    echo "\n";

    foreach ($byScenarioArm as $scenario => $arms) {
        echo "## {$scenario}\n\n";
        foreach (['prompt', 'baseline'] as $armKey) {
            if (!isset($arms[$armKey])) {
                continue;
            }
            echo '### ' . ($armKey === 'prompt' ? 'with sparring.md' : 'baseline (no system prompt)') . "\n\n";
            echo "| assertion | pass rate |\n|---|---|\n";
            foreach ($arms[$armKey] as $text => $counts) {
                $r = rate($counts['pass'], $counts['total']);
                $marker = $r === 100 ? '' : ($r === 0 ? ' ⚠' : ' ~');
                echo "| {$text} | {$counts['pass']}/{$counts['total']} ({$r}%){$marker} |\n";
            }
            echo "\n";
        }
    }
}

/**
 * Diff two iterations' 'prompt' arm — the arm that actually changes when you
 * edit sparring.md and re-run. Baseline doesn't move between iterations of
 * the same prompt, so it's left out of the diff (still visible via a plain
 * printReport() on either directory individually).
 */
function printCompare(array $before, array $after, string $beforeDir, string $afterDir): void
{
    echo "# Sparring eval comparison\n\n";
    echo "- before: {$beforeDir}\n";
    echo "- after:  {$afterDir}\n\n";

    $coreBefore = coreScore($before, 'prompt');
    $coreAfter = coreScore($after, 'prompt');
    $rateBefore = rate($coreBefore['pass'], $coreBefore['total']);
    $rateAfter = rate($coreAfter['pass'], $coreAfter['total']);
    $delta = $rateAfter - $rateBefore;
    $sign = $delta > 0 ? '+' : '';
    echo "## Core score (with sparring.md, excludes [flag] items)\n\n";
    echo "- before: {$coreBefore['pass']}/{$coreBefore['total']} ({$rateBefore}%)\n";
    echo "- after:  {$coreAfter['pass']}/{$coreAfter['total']} ({$rateAfter}%)\n";
    echo "- delta:  {$sign}{$delta} points\n\n";

    // Per-assertion movers, biggest |delta| first — surfaces what actually
    // shifted instead of making you scan every row for the change.
    $movers = [];
    $scenarios = array_unique(array_merge(array_keys($before), array_keys($after)));
    sort($scenarios);
    foreach ($scenarios as $scenario) {
        $beforeAssertions = $before[$scenario]['prompt'] ?? [];
        $afterAssertions = $after[$scenario]['prompt'] ?? [];
        $texts = array_unique(array_merge(array_keys($beforeAssertions), array_keys($afterAssertions)));
        foreach ($texts as $text) {
            $b = $beforeAssertions[$text] ?? null;
            $a = $afterAssertions[$text] ?? null;
            $rb = $b !== null ? rate($b['pass'], $b['total']) : null;
            $ra = $a !== null ? rate($a['pass'], $a['total']) : null;
            $d = ($rb !== null && $ra !== null) ? $ra - $rb : null;
            $movers[] = [
                'scenario' => $scenario,
                'text' => $text,
                'before' => $b === null ? 'n/a' : "{$rb}%",
                'after' => $a === null ? 'n/a' : "{$ra}%",
                'delta' => $d,
            ];
        }
    }
    usort($movers, fn($x, $y) => abs($y['delta'] ?? 0) <=> abs($x['delta'] ?? 0));

    echo "## Per-assertion movers (largest change first)\n\n";
    echo "| scenario | assertion | before | after | delta |\n|---|---|---|---|---|\n";
    foreach ($movers as $m) {
        $deltaLabel = $m['delta'] === null ? 'n/a' : (($m['delta'] > 0 ? '+' : '') . $m['delta']);
        echo "| {$m['scenario']} | {$m['text']} | {$m['before']} | {$m['after']} | {$deltaLabel} |\n";
    }
}

$workspaceDir = rtrim($argv[1], '/');
if (!is_dir($workspaceDir)) {
    fwrite(STDERR, "No such directory: {$workspaceDir}\n");
    exit(1);
}

$compareDir = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--compare=')) {
        $compareDir = rtrim(substr($arg, strlen('--compare=')), '/');
    }
}

$byScenarioArm = collectGrading($workspaceDir);
if ($byScenarioArm === []) {
    fwrite(STDERR, "No grading.json files found under {$workspaceDir} — run bin/grade_sparring.php first.\n");
    exit(1);
}

if ($compareDir === null) {
    printReport($byScenarioArm, $workspaceDir);
    exit;
}

if (!is_dir($compareDir)) {
    fwrite(STDERR, "No such directory: {$compareDir}\n");
    exit(1);
}
$compareScenarioArm = collectGrading($compareDir);
if ($compareScenarioArm === []) {
    fwrite(STDERR, "No grading.json files found under {$compareDir} — run bin/grade_sparring.php first.\n");
    exit(1);
}
printCompare($compareScenarioArm, $byScenarioArm, $compareDir, $workspaceDir);
