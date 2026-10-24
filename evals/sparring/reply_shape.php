<?php
declare(strict_types=1);

/**
 * Surface-shape metrics for Sparring replies: how a reply is built, not what
 * it argues. Pure functions, no I/O, so the smoke test can pin them and
 * bin/shape_sparring.php can run them over either simulated transcripts or
 * real sessions from the store.
 *
 * Built from tester feedback (stacked questions, long sentences, recurring
 * tics, every reply the same skeleton). The judged rubric and the
 * Socratic-move repetition check can't see these: two replies can use
 * different moves and still have identical shape.
 *
 * Sentences are split on . ! ? … followed by whitespace. That mis-splits
 * abbreviations ("z.B. so") a little; the same splitter is applied to every
 * arm, so comparisons between arms stay fair.
 */

const REPLY_SHAPE_LONG_SENTENCE_WORDS = 25;

/** @return list<string> */
function replySentences(string $reply): array
{
    $parts = preg_split('/(?<=[.!?…])\s+/u', trim($reply), -1, PREG_SPLIT_NO_EMPTY);
    return $parts === false ? [] : $parts;
}

/** @return list<string> lowercase word tokens */
function replyWords(string $text): array
{
    preg_match_all("/[\\p{L}\\p{N}'’-]+/u", mb_strtolower($text), $m);
    return $m[0];
}

/**
 * Per-reply measurements.
 *
 * questionRun: longest run of consecutive sentences ending in "?" (across
 * paragraph breaks) — "multiple questions in a row".
 * shape: paragraph count (capped at 3) plus whether each paragraph ends in
 * "?"; replies with the same shape read as the same skeleton.
 *
 * @return array{questions:int, questionRun:int, endsWithQuestion:bool,
 *               sentences:int, longSentences:int, maxSentenceWords:int,
 *               words:int, shape:string}
 */
function replyMetrics(string $reply): array
{
    $sentences = replySentences($reply);

    $run = $questionRun = 0;
    $longSentences = $maxWords = 0;
    foreach ($sentences as $s) {
        $run = str_ends_with($s, '?') ? $run + 1 : 0;
        $questionRun = max($questionRun, $run);
        $n = count(replyWords($s));
        $maxWords = max($maxWords, $n);
        $longSentences += $n >= REPLY_SHAPE_LONG_SENTENCE_WORDS ? 1 : 0;
    }

    $paragraphs = preg_split('/\n\s*\n/', trim($reply), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $shape = min(count($paragraphs), 3) . ':' . implode('', array_map(
        fn(string $p) => str_ends_with(trim($p), '?') ? 'Q' : 'S',
        array_slice($paragraphs, 0, 3)
    ));

    return [
        'questions' => substr_count($reply, '?'),
        'questionRun' => $questionRun,
        'endsWithQuestion' => $sentences !== [] && str_ends_with(end($sentences), '?'),
        'sentences' => count($sentences),
        'longSentences' => $longSentences,
        'maxSentenceWords' => $maxWords,
        'words' => count(replyWords($reply)),
        'shape' => $shape,
    ];
}

/**
 * Tic measures across the replies of ONE conversation (list in turn order):
 * repeatedOpeners = replies that open with the same first two words as
 * another reply; repeatedTrigrams = distinct 3-word phrases that occur in
 * two or more different replies. Meaningful per conversation, not pooled.
 *
 * @param list<string> $replies
 * @return array{repeatedOpeners:int, repeatedTrigrams:int, adjacentSameShape:int, adjacentPairs:int}
 */
function conversationTics(array $replies): array
{
    $openers = [];
    $trigramTurns = [];
    $shapes = [];
    foreach ($replies as $i => $reply) {
        $words = replyWords($reply);
        $openers[] = implode(' ', array_slice($words, 0, 2));
        for ($j = 0; $j + 2 < count($words); $j++) {
            $trigramTurns[implode(' ', array_slice($words, $j, 3))][$i] = true;
        }
        $shapes[] = replyMetrics($reply)['shape'];
    }

    $openerCounts = array_count_values($openers);
    $repeatedOpeners = count(array_filter($openers, fn($o) => $o !== '' && $openerCounts[$o] > 1));

    $adjacentSame = 0;
    for ($i = 1; $i < count($shapes); $i++) {
        $adjacentSame += $shapes[$i] === $shapes[$i - 1] ? 1 : 0;
    }

    return [
        'repeatedOpeners' => $repeatedOpeners,
        'repeatedTrigrams' => count(array_filter($trigramTurns, fn($turns) => count($turns) > 1)),
        'adjacentSameShape' => $adjacentSame,
        'adjacentPairs' => max(count($shapes) - 1, 0),
    ];
}

/**
 * Pooled summary over many conversations (each a list of replies in turn order).
 *
 * @param list<list<string>> $conversations
 * @return array<string, int|float>
 */
function summarizeShape(array $conversations): array
{
    $replies = $multiQ = $runOfTwo = $endsQ = 0;
    $sentences = $longSentences = $words = $maxSentence = 0;
    $repOpen = $adjSame = $adjPairs = $trigrams = 0;

    foreach ($conversations as $conv) {
        $tics = conversationTics($conv);
        $repOpen += $tics['repeatedOpeners'];
        $adjSame += $tics['adjacentSameShape'];
        $adjPairs += $tics['adjacentPairs'];
        $trigrams += $tics['repeatedTrigrams'];
        foreach ($conv as $reply) {
            $m = replyMetrics($reply);
            $replies++;
            $multiQ += $m['questions'] >= 2 ? 1 : 0;
            $runOfTwo += $m['questionRun'] >= 2 ? 1 : 0;
            $endsQ += $m['endsWithQuestion'] ? 1 : 0;
            $sentences += $m['sentences'];
            $longSentences += $m['longSentences'];
            $words += $m['words'];
            $maxSentence = max($maxSentence, $m['maxSentenceWords']);
        }
    }

    $pct = fn(int $a, int $b) => $b === 0 ? 0.0 : round(100 * $a / $b, 1);
    return [
        'conversations' => count($conversations),
        'replies' => $replies,
        'pctRepliesWith2PlusQuestions' => $pct($multiQ, $replies),
        'pctRepliesWithQuestionsInARow' => $pct($runOfTwo, $replies),
        'pctRepliesEndingInQuestion' => $pct($endsQ, $replies),
        'meanWordsPerReply' => $replies === 0 ? 0.0 : round($words / $replies, 1),
        'meanWordsPerSentence' => $sentences === 0 ? 0.0 : round($words / $sentences, 1),
        'pctSentencesAtLeast25Words' => $pct($longSentences, $sentences),
        'maxSentenceWords' => $maxSentence,
        'pctRepliesWithRepeatedOpener' => $pct($repOpen, $replies),
        'repeatedTrigramsPerConversation' => $conversations === [] ? 0.0 : round($trigrams / count($conversations), 1),
        'pctAdjacentSameShape' => $pct($adjSame, $adjPairs),
    ];
}
