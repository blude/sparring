<?php
declare(strict_types=1);

/**
 * Blind pairwise sheet for a human reader: the turn-1 reply from two
 * iterations (or two prompts) for the same scenario and repetition, side by
 * side with the arm hidden and the A/B order randomized. The rubric and the
 * shape metrics cannot say whether a reply got *better*; this is the cheap way
 * to get that judgment (see evals/sparring/judge-comparison.md "Limits").
 *
 * Usage: php bin/blind_pairs.php <dir-x> <dir-y> [--max=N] [--seed=N] [--out=<dir>]
 *   --max   Number of pairs to include (default 12), sampled with the seed.
 *   --seed  Makes the sample and the A/B order reproducible (default 1).
 *   --out   Output directory (default evals/sparring/blind-pairs/<x>-vs-<y>).
 *
 * Writes sheet.md (to rate) and key.json (which arm is A/B for each pair; don't
 * open it until you have rated). Only turn 1 is paired: both arms answer the
 * same pinned opener there. From turn 2 on each run has its own simulated
 * visitor, so replies are no longer answers to the same message. Turn 1 also
 * says nothing about how replies change late in a session.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

$positional = [];
$max = 12;
$seed = 1;
$out = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--max=')) {
        $max = (int) substr($arg, 6);
    } elseif (str_starts_with($arg, '--seed=')) {
        $seed = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--out=')) {
        $out = rtrim(substr($arg, 6), '/');
    } else {
        $positional[] = rtrim($arg, '/');
    }
}
if (count($positional) !== 2) {
    exit("Usage: php bin/blind_pairs.php <dir-x> <dir-y> [--max=N] [--seed=N] [--out=<dir>]\n");
}
[$dirX, $dirY] = $positional;

/** @return array<string, array{opener:string, reply:string}> keyed "scenario/rep-N" */
function turnOneReplies(string $dir): array
{
    if (!is_dir($dir)) {
        fwrite(STDERR, "No such directory: {$dir}\n");
        exit(1);
    }
    $found = [];
    foreach (glob("{$dir}/*/rep-*/transcript.json") ?: [] as $path) {
        $t = json_decode((string) file_get_contents($path), true);
        if (!is_array($t) || !empty($t['baseline']) || empty($t['exchanges'][0])) {
            continue;
        }
        $key = basename(dirname($path, 2)) . '/' . basename(dirname($path));
        $found[$key] = [
            'opener' => $t['exchanges'][0]['visitorContribution'],
            'reply' => trim($t['exchanges'][0]['sparringResponse']),
        ];
    }
    ksort($found);
    return $found;
}

$x = turnOneReplies($dirX);
$y = turnOneReplies($dirY);
$keys = array_values(array_intersect(array_keys($x), array_keys($y)));
if ($keys === []) {
    fwrite(STDERR, "No scenario/rep appears in both directories.\n");
    exit(1);
}

mt_srand($seed);
shuffle($keys);
$keys = array_slice($keys, 0, $max);

$out ??= 'evals/sparring/blind-pairs/' . basename($dirX) . '-vs-' . basename($dirY);
if (!is_dir($out) && !mkdir($out, 0775, true)) {
    fwrite(STDERR, "Cannot create {$out}\n");
    exit(1);
}

$sheet = "# Blind comparison: {$max} pairs at most\n\n"
    . "Each pair answers the same visitor message. For each, say which reply you would rather receive.\n"
    . "The order is randomized; don't open `key.json` until you have rated all pairs.\n\n"
    . "**How to answer.** Decide which reply you would rather receive, A or B (or `same` if you really cannot\n"
    . "tell), and reply in chat with one line per pair and a few words on why if you have a reason.\n"
    . "Example: `1: A - shorter, easier to follow`.\n\n";
$key = ['x' => $dirX, 'y' => $dirY, 'seed' => $seed, 'pairs' => []];

foreach ($keys as $i => $k) {
    $n = $i + 1;
    $xIsA = mt_rand(0, 1) === 1;
    [$a, $b] = $xIsA ? [$x[$k], $y[$k]] : [$y[$k], $x[$k]];
    $key['pairs'][$n] = ['scenario/rep' => $k, 'A' => $xIsA ? 'x' : 'y', 'B' => $xIsA ? 'y' : 'x'];

    // Same pair in the layout skill-creator's eval viewer reads: one directory per
    // run with outputs/, and the prompt from eval_metadata.json. Names carry no arm.
    $pairDir = sprintf('%s/viewer/pair-%02d', $out, $n);
    if (!is_dir("{$pairDir}/outputs") && !mkdir("{$pairDir}/outputs", 0775, true)) {
        fwrite(STDERR, "Cannot create {$pairDir}/outputs\n");
        exit(1);
    }
    file_put_contents("{$pairDir}/eval_metadata.json", json_encode([
        'eval_id' => $n,
        'eval_name' => sprintf('pair-%02d', $n),
        'prompt' => "Visitor: " . $x[$k]['opener'] . "\n\nWhich reply would you rather receive? Type A or B in the feedback box "
            . "(or \"same\" if you really can't tell), then a few words on why if you have a reason. "
            . "Example: A - shorter, easier to follow.",
        'assertions' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    file_put_contents("{$pairDir}/outputs/reply-A.md", $a['reply'] . "\n");
    file_put_contents("{$pairDir}/outputs/reply-B.md", $b['reply'] . "\n");

    $sheet .= "## Pair {$n}\n\n"
        . "**Visitor:** " . $x[$k]['opener'] . "\n\n"
        . "**Reply A**\n\n" . $a['reply'] . "\n\n"
        . "**Reply B**\n\n" . $b['reply'] . "\n\n"
        . "- I would rather receive: A / B / same\n"
        . "- Notes:\n\n---\n\n";
}

file_put_contents("{$out}/sheet.md", $sheet);
file_put_contents("{$out}/key.json", json_encode($key, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, count($keys) . " pairs -> {$out}/sheet.md (key: {$out}/key.json)\n");
