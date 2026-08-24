<?php
declare(strict_types=1);

/**
 * Compact markdown summary over graded eval output — per scenario, per arm
 * (real prompt vs. --baseline), pass rate for each assertion across reps.
 *
 * skill-creator's aggregate_benchmark.py/generate_review.py assume its own
 * eval-<id>/{with_skill,without_skill} single-shot A/B layout; this suite's
 * scenario-id/{rep-N,baseline}/ layout (built for multi-rep pass-rate
 * tracking across a multi-turn transcript, not a single-shot comparison)
 * doesn't map onto that cleanly, so this is a small purpose-built summary
 * instead of reshaping the run output to fit a tool built for a different
 * shape.
 *
 * Usage: php bin/summarize_sparring.php <workspace-dir>
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (!isset($argv[1])) {
    exit("Usage: php bin/summarize_sparring.php <workspace-dir>\n");
}

$workspaceDir = rtrim($argv[1], '/');
if (!is_dir($workspaceDir)) {
    fwrite(STDERR, "No such directory: {$workspaceDir}\n");
    exit(1);
}

$gradingFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspaceDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getFilename() === 'grading.json') {
        $gradingFiles[] = $file->getPathname();
    }
}
sort($gradingFiles);

if ($gradingFiles === []) {
    fwrite(STDERR, "No grading.json files found under {$workspaceDir} — run bin/grade_sparring.php first.\n");
    exit(1);
}

// path shape: <workspace>/<scenario-id>/<rep-N|baseline>/grading.json
$byScenarioArm = [];
foreach ($gradingFiles as $path) {
    $parts = explode('/', $path);
    $arm = $parts[count($parts) - 2];
    $scenario = $parts[count($parts) - 3];
    $isBaseline = $arm === 'baseline';
    $armKey = $isBaseline ? 'baseline' : 'prompt';

    $grading = json_decode((string) file_get_contents($path), true);
    foreach ($grading['expectations'] ?? [] as $expectation) {
        $byScenarioArm[$scenario][$armKey][$expectation['text']]['pass'] =
            ($byScenarioArm[$scenario][$armKey][$expectation['text']]['pass'] ?? 0) + ($expectation['passed'] ? 1 : 0);
        $byScenarioArm[$scenario][$armKey][$expectation['text']]['total'] =
            ($byScenarioArm[$scenario][$armKey][$expectation['text']]['total'] ?? 0) + 1;
    }
}

ksort($byScenarioArm);
echo "# Sparring eval summary — {$workspaceDir}\n\n";
foreach ($byScenarioArm as $scenario => $arms) {
    echo "## {$scenario}\n\n";
    foreach (['prompt', 'baseline'] as $armKey) {
        if (!isset($arms[$armKey])) {
            continue;
        }
        echo '### ' . ($armKey === 'prompt' ? 'with sparring.md' : 'baseline (no system prompt)') . "\n\n";
        echo "| assertion | pass rate |\n|---|---|\n";
        foreach ($arms[$armKey] as $text => $counts) {
            $rate = $counts['total'] > 0 ? (int) round(100 * $counts['pass'] / $counts['total']) : 0;
            $marker = $rate === 100 ? '' : ($rate === 0 ? ' ⚠' : ' ~');
            echo "| {$text} | {$counts['pass']}/{$counts['total']} ({$rate}%){$marker} |\n";
        }
        echo "\n";
    }
}
