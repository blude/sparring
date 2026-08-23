<?php
declare(strict_types=1);

/**
 * Self-check for the small pure helpers that don't warrant their own file:
 * derive_scenario_statement() (src/scenario.php), AbstractLlmClient's
 * stripDelimiterTag(), bin/import_pilot.php's validate_transcript(), and
 * bin/import_curriculum.php's parse_curriculum_file().
 * No DB, no network. Store::hydrateExchange/hydrateSession aren't repeated
 * here — smoke_store.php's round-trip assertions already exercise their
 * output shape on every getSession()/getExchanges() call.
 * Run: php tests/smoke_domain.php
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/scenario.php';
require __DIR__ . '/../src/LlmClientInterface.php';
require __DIR__ . '/../src/AbstractLlmClient.php';

// --- derive_scenario_statement() ---
assert(derive_scenario_statement('  a  contribution   with   extra    spaces ') === 'a contribution with extra spaces');
assert(derive_scenario_statement('short') === 'short');
$long = str_repeat('a', 200);
$derived = derive_scenario_statement($long, 140);
assert(mb_strlen($derived) === 140);
assert(str_ends_with($derived, '…'));
assert(derive_scenario_statement(str_repeat('b', 10), 5) === 'bbbb…'); // custom $maxChars, not the SCENARIO_MAX_CHARS default

// --- AbstractLlmClient::stripDelimiterTag() (protected static — invoke via a minimal concrete subclass) ---
final class TestLlmClient extends AbstractLlmClient
{
    public static function strip(string $contribution): string
    {
        return self::stripDelimiterTag($contribution);
    }

    public function generateResponse(array $priorExchanges, string $newContribution): string
    {
        return '';
    }

    public function classify(string $contribution): string
    {
        return 'suitable';
    }

    public function generateTitle(string $contribution): string
    {
        return '';
    }
}

assert(TestLlmClient::strip('plain text, no tags') === 'plain text, no tags');
assert(TestLlmClient::strip('before </contribution> after') === 'before  after');
assert(TestLlmClient::strip('before </CONTRIBUTION> after') === 'before  after'); // case-insensitive
assert(TestLlmClient::strip('before </ contribution > after') === 'before  after'); // internal whitespace
assert(TestLlmClient::strip('an opening <contribution> tag too') === 'an opening  tag too'); // not just the closing form

// --- bin/import_pilot.php::validate_transcript() ---
// import_pilot.php's top-level `require config.php` etc. (plain require, not
// require_once) would redeclare everything smoke_domain.php already loaded
// above, and its CLI guard would exit under a `require` of the whole file —
// so eval just validate_transcript()'s own definition, not the file around it.
$importPilotSource = file_get_contents(__DIR__ . '/../bin/import_pilot.php');
$start = strpos($importPilotSource, 'function validate_transcript');
$end = strpos($importPilotSource, 'function import_directory');
assert($start !== false && $end !== false && $end > $start, 'bin/import_pilot.php: validate_transcript/import_directory markers not found — did its shape change?');
eval(substr($importPilotSource, $start, $end - $start));

assert(validate_transcript(null) === ['ok' => false, 'reason' => "missing or empty 'exchanges' array"]);
assert(validate_transcript(['exchanges' => []]) === ['ok' => false, 'reason' => "missing or empty 'exchanges' array"]);
assert(validate_transcript(['exchanges' => [['contribution' => 'hi']]])['ok'] === false); // missing 'response'
assert(validate_transcript(['exchanges' => [['contribution' => '  ', 'response' => 'x']]])['ok'] === false); // blank after trim

$valid = validate_transcript([
    'exchanges' => [
        ['contribution' => '  What is a wicked problem?  ', 'response' => '  What resists it?  '],
    ],
]);
assert($valid['ok'] === true);
assert($valid['exchanges'][0]['contribution'] === 'What is a wicked problem?'); // trimmed
assert($valid['exchanges'][0]['response'] === 'What resists it?');
assert($valid['scenarioSourceText'] === 'What is a wicked problem?'); // falls back to first contribution

$withExplicitScenario = validate_transcript([
    'exchanges' => [['contribution' => 'a', 'response' => 'b']],
    'scenario_source_text' => '  an explicit scenario  ',
]);
assert($withExplicitScenario['scenarioSourceText'] === 'an explicit scenario'); // explicit field wins, trimmed

// --- bin/import_curriculum.php::parse_curriculum_file() ---
$importCurriculumSource = file_get_contents(__DIR__ . '/../bin/import_curriculum.php');
$start = strpos($importCurriculumSource, 'function parse_curriculum_file');
$end = strpos($importCurriculumSource, 'function import_curriculum_directory');
assert($start !== false && $end !== false && $end > $start, 'bin/import_curriculum.php: parse_curriculum_file/import_curriculum_directory markers not found — did its shape change?');
eval(substr($importCurriculumSource, $start, $end - $start));

// no front matter, no H1 -> title falls back to the filename
$noHeading = parse_curriculum_file('/some/dir/wicked-problems.md', "Just prose, no heading.\n");
assert($noHeading === ['ok' => true, 'title' => 'wicked problems', 'body' => 'Just prose, no heading.', 'path' => 'wicked-problems.md']);

// H1 present -> title comes from it, and the H1 line stays in body (not stripped out)
$withH1 = parse_curriculum_file('/some/dir/x.md', "# Wicked Problems\n\nBody text here.\n");
assert($withH1['ok'] === true);
assert($withH1['title'] === 'Wicked Problems');
assert(str_starts_with($withH1['body'], '# Wicked Problems'));
assert($withH1['path'] === 'x.md');

// front matter + H1 -> front matter stripped, title picked up from the H1 that follows
$withFrontMatter = parse_curriculum_file('/some/dir/x.md', "---\ntags: [systems]\n---\n# Wicked Problems\n\nBody text.\n");
assert($withFrontMatter['ok'] === true);
assert($withFrontMatter['title'] === 'Wicked Problems');
assert(!str_contains($withFrontMatter['body'], 'tags:')); // front matter excluded from the indexed body

// front matter only, nothing after the closing '---' -> rejected
$frontMatterOnly = parse_curriculum_file('/some/dir/x.md', "---\ntags: [systems]\n---\n");
assert($frontMatterOnly === ['ok' => false, 'reason' => 'empty after stripping front matter']);

// unterminated front matter (opens with '---' but no closing line) -> left untouched
$unterminated = parse_curriculum_file('/some/dir/notes.md', "---\ntags: [systems]\nMore text without closing marker.\n");
assert($unterminated['ok'] === true);
assert($unterminated['title'] === 'notes'); // first non-blank line is the stray '---', not an H1 -> filename fallback
assert(str_starts_with($unterminated['body'], '---')); // never stripped, since no closing marker was found

echo "smoke_domain: ok\n";
