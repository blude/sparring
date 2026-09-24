<?php
declare(strict_types=1);

/**
 * "Poor woman's RAG" ingestion. Syncs data/curriculum/*.md into the
 * curriculum_chunks FTS5 table so Store::searchCurriculum() (general
 * free-text search) and Store::searchCurriculumConcepts() (vocabulary-
 * restricted turn-1 auto-grounding, see Sparring::processTurn and
 * spec/L3-SE-04-system-prompt.adoc's TF-03) can both query it. Run on the
 * host, never web-routed — this script has no HTTP entry point.
 *
 * Rebuilds the ENTIRE curriculum_chunks table on every run (delete + reinsert
 * in one transaction) rather than diffing against what's already stored —
 * this is what makes editing or deleting a source file converge correctly:
 * a file removed from data/curriculum/ is simply absent from this run's
 * insert set, so its chunk is gone after commit with no separate cleanup
 * pass needed.
 *
 * Usage: php bin/import_curriculum.php [directory]   (defaults to config's CURRICULUM_DATA_DIR)
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Store.php';

/**
 * One markdown file -> one curriculum chunk. A leading `---`...`---` front
 * matter block is stripped so it doesn't pollute the indexed/prompt-facing
 * body — best-effort line matching, not a YAML parser. One field IS read:
 * `typ: stub` marks a redirect-only page (renamed/merged concept, body is
 * just a pointer to the real page) and is rejected rather than indexed —
 * every other front-matter field is still ignored. An unterminated
 * front-matter block (opens with `---` but no closing line) is left
 * untouched rather than guessed at. Title is the first ATX H1 found anywhere
 * in the body after stripping (not just the first line — a file can open
 * with plain prose before its heading), else a filename-derived fallback;
 * the H1 line (if any) is left in $body too — simpler than surgically
 * removing it, and harmless duplication.
 *
 * @return array{ok: bool, reason?: string, title?: string, body?: string, path?: string}
 */
function parse_curriculum_file(string $path, string $content): array
{
    // Normalized once up front: CRLF (or stray CR) line endings would
    // otherwise survive explode("\n", ...) as a trailing "\r" on every line,
    // silently defeating the exact '---' string comparison below on any
    // Windows-authored/exported markdown file.
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $lines = explode("\n", $content);
    $frontMatterLines = [];
    if (($lines[0] ?? null) === '---') {
        $closingIndex = null;
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '---') {
                $closingIndex = $i;
                break;
            }
        }
        if ($closingIndex !== null) {
            $frontMatterLines = array_slice($lines, 1, $closingIndex - 1);
            $lines = array_slice($lines, $closingIndex + 1);
        }
    }
    foreach ($frontMatterLines as $line) {
        if (preg_match('/^typ:\s*stub\s*$/', trim($line))) {
            return ['ok' => false, 'reason' => 'stub page (typ: stub) — redirect only, not indexed'];
        }
    }
    $body = trim(implode("\n", $lines));

    if ($body === '') {
        return ['ok' => false, 'reason' => 'empty after stripping front matter'];
    }

    $title = null;
    foreach ($lines as $line) {
        if (preg_match('/^#\s+(.+)$/', trim($line), $m)) {
            $title = trim($m[1]);
            break;
        }
    }
    if ($title === null) {
        $title = str_replace(['-', '_'], ' ', basename($path, '.md'));
    }

    return ['ok' => true, 'title' => $title, 'body' => $body, 'path' => basename($path)];
}

/**
 * Full-corpus rebuild: every current data/curriculum/*.md file becomes exactly
 * one row in curriculum_chunks (see parse_curriculum_file's doc for why a
 * deleted file needs no separate cleanup here).
 *
 * Two conditions abort the whole run (throw, before replaceCurriculumChunks()
 * is ever called) rather than proceeding with a partial result: a missing/
 * wrong directory and an unreadable file mid-scan. Both are I/O-level
 * failures, not content problems — proceeding anyway would let the
 * full-rebuild design turn a transient error (typo'd path, a permissions
 * hiccup) into permanent data loss, silently wiping or shrinking the table
 * with nothing louder than a rejected-file log line to notice it by. A
 * *content* problem in one file (parse_curriculum_file() returning
 * ok: false — e.g. empty after stripping front matter) stays non-fatal and
 * reported via $rejected, same as bin/import_pilot.php's precedent: that's
 * an operator-visible bad file, not a sign the read itself can't be trusted.
 */
function import_curriculum_directory(Store $store, string $dir): array
{
    if (!is_dir($dir)) {
        throw new RuntimeException("curriculum directory not found: $dir");
    }

    $chunks = [];
    $rejected = [];

    $files = glob(rtrim($dir, '/') . '/*.md') ?: [];
    sort($files);

    foreach ($files as $path) {
        $name = basename($path);
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("unreadable file: $name — aborting import, curriculum_chunks left untouched");
        }

        $result = parse_curriculum_file($path, $raw);
        if (!$result['ok']) {
            $rejected[] = ['file' => $name, 'reason' => $result['reason']];
            continue;
        }

        $chunks[] = ['title' => $result['title'], 'body' => $result['body'], 'path' => $result['path']];
    }

    $counts = $store->replaceCurriculumChunks($chunks);

    return [
        'imported' => $chunks,
        'rejected' => $rejected,
        'before' => $counts['before'],
        'after' => $counts['after'],
    ];
}

// --- entry point ---
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("this script runs on the host only\n");
}

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    exit(
        "Usage: php bin/import_curriculum.php [directory]   (defaults to config's CURRICULUM_DATA_DIR)\n" .
        "Rebuilds the entire curriculum_chunks table from *.md files in the directory on every\n" .
        "run — a file removed from disk since the last run is dropped, not just added/changed ones.\n"
    );
}

$dir = $argv[1] ?? CURRICULUM_DATA_DIR;
$store = new Store(STORE_DB_PATH);
try {
    $outcome = import_curriculum_directory($store, $dir);
} catch (RuntimeException $e) {
    fwrite(STDERR, "error: {$e->getMessage()}\n");
    exit(1);
}

printf("imported %d curriculum file(s) from %s\n", count($outcome['imported']), $dir);
foreach ($outcome['imported'] as $row) {
    printf("  ok   %-30s title=%s\n", $row['path'], $row['title']);
}
foreach ($outcome['rejected'] as $row) {
    printf("  FAIL %-30s %s\n", $row['file'], $row['reason']);
}
if ($outcome['rejected'] !== []) {
    printf("%d file(s) rejected — see above\n", count($outcome['rejected']));
}
printf("curriculum_chunks: %d -> %d\n", $outcome['before'], $outcome['after']);
