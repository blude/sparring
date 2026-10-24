<?php
declare(strict_types=1);

/**
 * Self-check for the reply-shape metrics (evals/sparring/reply_shape.php):
 * question counts and runs, long sentences, tic detection, shape signature.
 * Hand-written replies with known answers; no network.
 * Run: php -d zend.assertions=1 tests/smoke_reply_shape.php
 */

// assert() is compiled out under zend.assertions=-1 (production php.ini):
// refuse to run rather than pass without checking anything.
if (ini_get('zend.assertions') !== '1') {
    fwrite(STDERR, basename(__FILE__) . ": needs php -d zend.assertions=1 (tests/run.sh sets it)\n");
    exit(1);
}

require __DIR__ . '/../evals/sparring/reply_shape.php';

// Three questions in a row, one paragraph, ends on "?".
$m = replyMetrics("Why that one? And who decides? Is it you, or the client?");
assert($m['questions'] === 3);
assert($m['questionRun'] === 3);
assert($m['endsWithQuestion'] === true);
assert($m['shape'] === '1:Q');

// A statement between two questions breaks the run.
$m = replyMetrics("Why? That seems off. Who decides?");
assert($m['questions'] === 2 && $m['questionRun'] === 1);

// A 26-word sentence counts as long; a short one does not.
$long = str_repeat('word ', 26) . 'end.';
$m = replyMetrics("Short one. {$long}");
assert($m['sentences'] === 2 && $m['longSentences'] === 1 && $m['maxSentenceWords'] === 27);

// Two paragraphs: statement, then question. Shape records both endings.
assert(replyMetrics("It breaks here.\n\nWhy does it hold?")['shape'] === '2:SQ');

// Runs continue across a paragraph break.
assert(replyMetrics("Why?\n\nAnd who?")['questionRun'] === 2);

// Empty input does not blow up.
$m = replyMetrics('');
assert($m['sentences'] === 0 && $m['endsWithQuestion'] === false);

// Tics: same opener twice, a repeated 3-gram, same shape on adjacent turns.
$tics = conversationTics([
    "Good, that's sharper. Now defend it.",
    "Good, that's weaker. Now defend it?",
    "Different start entirely.\n\nReally?",
]);
assert($tics['repeatedOpeners'] === 2);
assert($tics['repeatedTrigrams'] >= 1);
assert($tics['adjacentSameShape'] === 0 && $tics['adjacentPairs'] === 2);

$tics = conversationTics(["One thing.", "Another thing.", "Third thing."]);
assert($tics['adjacentSameShape'] === 2);

// Pooled summary over two conversations.
$s = summarizeShape([["Why? Who?", "Fine."], ["Just a statement."]]);
assert($s['replies'] === 3 && $s['conversations'] === 2);
assert($s['pctRepliesWith2PlusQuestions'] === 33.3);
assert($s['pctRepliesWithQuestionsInARow'] === 33.3);

echo basename(__FILE__) . " ok\n";
