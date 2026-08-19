<?php
declare(strict_types=1);

/**
 * Self-check for the small pure helpers that don't warrant their own file:
 * derive_scenario_statement() (src/scenario.php), resolve_opening_message()
 * (config.php), AbstractLlmClient's stripDelimiterTag(), and
 * bin/import_pilot.php's validate_transcript().
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

// --- resolve_opening_message() ---
assert(resolve_opening_message('wicked-problems') === 'Sparring Scenario: Some decisions can never fully be "solved" — only managed.');
assert(resolve_opening_message('not-a-real-id') === null);
assert(resolve_opening_message(null) === null);
assert(resolve_opening_message('') === null);

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

echo "smoke_domain: ok\n";
