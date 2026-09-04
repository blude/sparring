<?php
declare(strict_types=1);

/**
 * Self-check for the small pure helpers that don't warrant their own file:
 * derive_scenario_statement() (src/scenario.php), resolve_opening_message()
 * and resolve_display_config() (config.php), AbstractLlmClient's stripDelimiterTag(),
 * bin/import_pilot.php's validate_transcript(),
 * bin/import_curriculum.php's parse_curriculum_file(), and
 * bin/export.php's render_session_index().
 * No DB, no network. Store::hydrateExchange/hydrateSession aren't repeated
 * here — smoke_store.php's round-trip assertions already exercise their
 * output shape on every getSession()/getExchanges() call.
 * Run: php tests/smoke_domain.php
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/scenario.php';
require __DIR__ . '/../src/LlmClientInterface.php';
require __DIR__ . '/../src/AbstractLlmClient.php';

/*
|--------------------------------------------------------------------------
| derive_scenario_statement()
|--------------------------------------------------------------------------
*/

assert(derive_scenario_statement('  a  contribution   with   extra    spaces ') === 'a contribution with extra spaces');
assert(derive_scenario_statement('short') === 'short');
$long = str_repeat('a', 200);
$derived = derive_scenario_statement($long, 140);
assert(mb_strlen($derived) === 140);
assert(str_ends_with($derived, '…'));
assert(derive_scenario_statement(str_repeat('b', 10), 5) === 'bbbb…'); // custom $maxChars, not the SCENARIO_MAX_CHARS default
assert(derive_scenario_statement($long, null) === $long); // null $maxChars: unbounded, used by the title fallback

/*
|--------------------------------------------------------------------------
| resolve_opening_message()
|--------------------------------------------------------------------------
*/

assert(resolve_opening_message('1') === 'Sparring Scenario: Some decisions can never fully be "solved" — only managed.');
assert(resolve_opening_message('99') === null);
assert(resolve_opening_message(null) === null);
assert(resolve_opening_message('') === null);

/*
|--------------------------------------------------------------------------
| resolve_display_config() — ?d= whitelist lookup with default fallback
|--------------------------------------------------------------------------
*/

$_GET['d'] = 'dev';
assert(resolve_display_config() === ['name' => 'dev'] + DISPLAY_CONFIGS['dev']);
$_GET['d'] = 'venue';
assert(resolve_display_config()['itemLimit'] === 12);
$_GET['d'] = 'bogus'; // unknown name -> default
assert(resolve_display_config()['name'] === DISPLAY_CONFIG_DEFAULT);
unset($_GET['d']); // absent -> default
assert(resolve_display_config()['name'] === DISPLAY_CONFIG_DEFAULT);
assert(isset(DISPLAY_CONFIGS[DISPLAY_CONFIG_DEFAULT])); // default must name a real config

/*
|--------------------------------------------------------------------------
| AbstractLlmClient::stripDelimiterTag() (protected static — invoke via a minimal concrete subclass)
|--------------------------------------------------------------------------
*/

final class TestLlmClient extends AbstractLlmClient
{
    public static function strip(string $contribution): string
    {
        return self::stripDelimiterTag($contribution);
    }

    public function generateResponse(array $priorExchanges, string $newContribution, ?string $groundingContext = null): string
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

/*
|--------------------------------------------------------------------------
| bin/import_pilot.php::validate_transcript()
|--------------------------------------------------------------------------
*/

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

// title is optional — omitted entirely stays null, no error
assert($valid['title'] === null);

// present and short: trimmed, kept as-is
$withTitle = validate_transcript([
    'exchanges' => [['contribution' => 'a', 'response' => 'b']],
    'title' => '  A short title  ',
]);
assert($withTitle['ok'] === true);
assert($withTitle['title'] === 'A short title');

// present and blank after trim: rejected, same as a blank contribution/response
assert(validate_transcript(['exchanges' => [['contribution' => 'a', 'response' => 'b']], 'title' => '   '])['ok'] === false);

// no length cap — a long title passes through unbounded, same as the LLM/fallback path
$longTitle = validate_transcript([
    'exchanges' => [['contribution' => 'a', 'response' => 'b']],
    'title' => str_repeat('x', 200),
]);
assert($longTitle['ok'] === true);
assert(mb_strlen($longTitle['title']) === 200);

// reply_to is optional — omitted stays null
assert($valid['replyTo'] === null);

// present and valid: trimmed, kept as the target seed's stem
$withReplyTo = validate_transcript([
    'exchanges' => [['contribution' => 'a', 'response' => 'b']],
    'reply_to' => '  wicked-problem  ',
]);
assert($withReplyTo['ok'] === true);
assert($withReplyTo['replyTo'] === 'wicked-problem');

// present and junk: rejected, same as a blank title
assert(validate_transcript(['exchanges' => [['contribution' => 'a', 'response' => 'b']], 'reply_to' => '   '])['ok'] === false);
assert(validate_transcript(['exchanges' => [['contribution' => 'a', 'response' => 'b']], 'reply_to' => 42])['ok'] === false);

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
assert($unterminated['title'] === 'notes'); // no line anywhere matches the H1 pattern -> filename fallback
assert(str_starts_with($unterminated['body'], '---')); // never stripped, since no closing marker was found

// H1 preceded by preamble text -> title is still found, not a filename fallback
// (title detection scans the whole body, not just the first non-blank line)
$h1AfterPreamble = parse_curriculum_file('/some/dir/x.md', "Some intro prose before the heading.\n\n# Wicked Problems\n\nBody text.\n");
assert($h1AfterPreamble['ok'] === true);
assert($h1AfterPreamble['title'] === 'Wicked Problems');
assert(str_starts_with($h1AfterPreamble['body'], 'Some intro prose'));

// CRLF line endings (e.g. a Windows-authored file) must not defeat the exact
// '---' front-matter delimiter comparison
$crlf = parse_curriculum_file('/some/dir/x.md', "---\r\ntags: [systems]\r\n---\r\n# Wicked Problems\r\n\r\nBody text.\r\n");
assert($crlf['ok'] === true);
assert($crlf['title'] === 'Wicked Problems');
assert(!str_contains($crlf['body'], 'tags:')); // front matter stripped despite CRLF

// typ: stub -> rejected regardless of body content (redirect-only page, not indexed)
$stub = parse_curriculum_file('/some/dir/x.md', "---\nname: Old Name\ntyp: stub\n---\n# Old Name (Stub)\n\nMoved elsewhere.\n");
assert($stub === ['ok' => false, 'reason' => 'stub page (typ: stub) — redirect only, not indexed']);

// typ: konzept (or any non-stub value) -> not rejected, front matter still stripped as usual
$nonStub = parse_curriculum_file('/some/dir/x.md', "---\nname: X\ntyp: konzept\n---\n# X\n\nBody.\n");
assert($nonStub['ok'] === true);

/*
|--------------------------------------------------------------------------
| bin/export.php::render_session_index() — --index mode row builder
|--------------------------------------------------------------------------
*/

// Same trick as validate_transcript above: export.php's top-level `require`
// + CLI guard would run on a whole-file require, so eval just this one
// function's definition, bounded by the `$store = new Store` that follows it.
$exportSource = file_get_contents(__DIR__ . '/../bin/export.php');
$start = strpos($exportSource, 'function render_session_index');
$end = strpos($exportSource, '$store = new Store(STORE_DB_PATH);');
assert($start !== false && $end !== false && $end > $start, 'bin/export.php: render_session_index / $store markers not found — did its shape change?');
eval(substr($exportSource, $start, $end - $start));

$sess = [
    ['id' => 'AAAA1111', 'createdAt' => '2026-08-22T09:15:00Z', 'title' => 'Creator inquiry', 'origin' => 'live', 'turnCount' => 3, 'lastActiveAt' => '2026-08-22T09:40:00Z', 'displayable' => true],
    ['id' => 'BBBB2222', 'createdAt' => '2026-08-23T11:00:00Z', 'title' => null, 'origin' => 'pilot', 'turnCount' => 1, 'lastActiveAt' => '2026-08-23T11:05:00Z', 'displayable' => false],
];
$counts = ['AAAA1111' => 3, 'BBBB2222' => 1];

// default columns: id - date - title - N exchanges
assert(render_session_index($sess, $counts, null) ===
    "AAAA1111 - 2026-08-22 - Creator inquiry - 3 exchanges\n" .
    "BBBB2222 - 2026-08-23 - (untitled) - 1 exchange\n"); // null title -> (untitled); singular "exchange" at 1

// empty --columns= is treated as "use the default set", same as null
assert(render_session_index($sess, $counts, '') === render_session_index($sess, $counts, null));

// custom column set, whitespace around keys tolerated
assert(render_session_index($sess, $counts, 'origin, displayable , turns') ===
    "live - yes - 3\n" .
    "pilot - no - 1\n");

// datetime column is the full timestamp, date is just the day
assert(render_session_index([$sess[0]], $counts, 'datetime') === "2026-08-22T09:15:00Z\n");

// a session with no exchanges recorded -> count 0, plural
assert(render_session_index([['id' => 'CCCC3333', 'createdAt' => '2026-08-24T00:00:00Z', 'title' => 't']], [], 'exchanges') === "0 exchanges\n");

// no sessions -> empty string, not a lone newline
assert(render_session_index([], $counts, null) === '');

// unknown column key -> InvalidArgumentException naming the bad key
$threw = false;
try {
    render_session_index($sess, $counts, 'id,bogus');
} catch (InvalidArgumentException $e) {
    $threw = true;
    assert(str_contains($e->getMessage(), "'bogus'"));
}
assert($threw, 'render_session_index should reject an unknown --columns key');

echo "smoke_domain: ok\n";
