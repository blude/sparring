<?php
declare(strict_types=1);

/**
 * Appends commits since the last "docs: update CHANGELOG through <hash>"
 * commit to CHANGELOG.md (grouped under `## YYYY-MM-DD` headings, newest
 * first, merging into today's heading if it's already the first one), then
 * sets composer.json's version to 0.<N>.0 where N is the resulting count of
 * date headings — one 0.1 per distinct day of work, recomputed from the file
 * every run so version drift can't accumulate silently (see CHANGELOG.md
 * 2026-08-27 entries for how it drifted before this script existed).
 *
 * Leaves the commit itself to the caller — this only edits the two files.
 *
 * Usage: php bin/bump_changelog.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

$repoRoot = dirname(__DIR__);
$changelogPath = $repoRoot . '/CHANGELOG.md';
$composerPath = $repoRoot . '/composer.json';

function git(string $cmd): string {
    $out = shell_exec("git -C " . escapeshellarg(dirname(__DIR__)) . " $cmd");
    if ($out === null) {
        fwrite(STDERR, "git command failed: $cmd\n");
        exit(1);
    }
    return trim($out);
}

// Find the hash the last bump commit updated through, from its own message.
$lastBumpSubject = git("log -1 --grep='^docs: update CHANGELOG through ' --format='%s'");
if ($lastBumpSubject === '' || !preg_match('/through ([0-9a-f]+)/', $lastBumpSubject, $m)) {
    fwrite(STDERR, "no prior \"docs: update CHANGELOG through <hash>\" commit found — nothing to diff from.\n");
    exit(1);
}
$since = $m[1];

$log = git("log --format='%h%x09%ad%x09%s' --date=short {$since}..HEAD");
if ($log === '') {
    fwrite(STDERR, "no commits since {$since} — nothing to do.\n");
    exit(1);
}

// Group commits by date, preserving git log's newest-first order both across
// and within groups (matches the file's existing convention).
$groups = []; // date => [line, ...]
foreach (explode("\n", $log) as $row) {
    [$hash, $date, $subject] = explode("\t", $row, 3);
    $groups[$date][] = "- `{$hash}` {$subject}";
}

$changelog = file_get_contents($changelogPath);

foreach ($groups as $date => $lines) {
    $entryText = implode("\n", $lines);
    $heading = "## {$date}";
    if (str_contains($changelog, "{$heading}\n")) {
        // Today's heading already exists (a bump already ran today) — merge in.
        $changelog = preg_replace(
            '/(' . preg_quote($heading, '/') . "\n\n)/",
            "$1{$entryText}\n",
            $changelog,
            1
        );
    } else {
        // New heading goes right after the intro, before the first existing one.
        $changelog = preg_replace(
            '/(Generated from git history\. Grouped by commit date, newest first\.\n\n)/',
            "$1{$heading}\n\n{$entryText}\n\n",
            $changelog,
            1
        );
    }
}

file_put_contents($changelogPath, $changelog);

$headingCount = preg_match_all('/^## \d{4}-\d{2}-\d{2}$/m', $changelog);
$newVersion = "0.{$headingCount}.0";

$composer = file_get_contents($composerPath);
$composer = preg_replace('/"version": "[^"]+"/', "\"version\": \"{$newVersion}\"", $composer, 1);
file_put_contents($composerPath, $composer);

$headHash = git("rev-parse --short HEAD");
fwrite(STDOUT, "CHANGELOG.md updated through {$headHash}, version set to {$newVersion} ({$headingCount} date headings).\n");
fwrite(STDOUT, "Review the diff, then commit as: docs: update CHANGELOG through {$headHash}\n");
