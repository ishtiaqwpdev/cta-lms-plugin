<?php
/**
 * Validate LMFT Clinical Comprehensive Simulation Form A import (PROMPT 01–06).
 *
 * Usage: php scripts/test-lmft-clinical-form-a-import.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
	} else {
		++$fail;
		echo "FAIL: {$msg}\n";
	}
}

require_once $root . '/includes/class-cta-lmft-clinical-form-a-sync.php';

$questions = CTA_Lmft_Clinical_Form_A_Sync::get_questions();
assert_true( 150 === count( $questions ), 'Form A seed defines 150 total slots' );
assert_true( 150 === CTA_Lmft_Clinical_Form_A_Sync::count_imported_items( $questions ), 'All 150 learner items imported with no placeholders' );

$expected_q126_stem = "A client who previously maintained stable employment begins missing work and having repeated disagreements with coworkers after the employer reorganizes the department and substantially changes the client's responsibilities. Which assessment would be MOST useful?";
assert_true(
	$expected_q126_stem === (string) ( $questions[125]['question_text'] ?? '' ),
	'Question 126 stem matches PROMPT 06 verbatim'
);
assert_true(
	'Assess mood, sleep, anxiety, concentration, and whether symptoms changed around the workplace reorganization.' === (string) ( $questions[125]['option_a'] ?? '' ),
	'Question 126 choice A preserved in order'
);

$expected_q150_stem = 'Parents of a 9-year-old with sensory and transition difficulties disagree sharply about discipline. One parent relies on highly structured routines; the other often changes expectations to prevent meltdowns. Both parents are exhausted, argue about each other\u{2019}s approach, and the child increasingly seeks a different answer from each parent. Which intervention should the therapist use to improve the parenting relationship?';
assert_true(
	$expected_q150_stem === (string) ( $questions[149]['question_text'] ?? '' ),
	'Question 150 stem matches PROMPT 06 verbatim'
);
assert_true(
	'Identify stress-driven differences and establish shared responses for recurring parenting challenges that predictably trigger conflict.' === (string) ( $questions[149]['option_b'] ?? '' ),
	'Question 150 choice B preserved in order'
);

assert_true(
	false === ( 0 === strpos( (string) ( $questions[149]['question_text'] ?? '' ), '[Import pending' ) ),
	'Question 150 is a real imported item, not a placeholder'
);

assert_true(
	'Form A — Comprehensive Simulation' === CTA_Lmft_Clinical_Form_A_Sync::FORM_TITLE,
	'Assessment title matches requested label'
);
assert_true(
	150 === CTA_Lmft_Clinical_Form_A_Sync::IMPORTED_THROUGH,
	'Sync tracks import progress through item 150'
);
assert_true(
	'active' === CTA_Lmft_Clinical_Form_A_Sync::resolve_quiz_status( $questions ),
	'Form activates once all 150 learner items are imported'
);

$lms       = file_get_contents( $root . '/cta-lms.php' );
$quiz_js   = file_get_contents( $root . '/assets/js/main.js' );
$items_src = file_get_contents( $root . '/includes/quiz-seeds/lmft-clinical-form-a-items-126-150.php' );

assert_true( false !== strpos( $lms, '1.0.226' ), 'Version bump 1.0.226' );
assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Form_A_Sync::sync' ),
	'Upgrade hook syncs rebuilt Form A'
);
assert_true(
	false !== strpos( $items_src, 'CTA-LMFT-CA-FA-150' ),
	'Question codes assigned for items 126–150'
);
assert_true(
	false === preg_match( '/shuffle.*cta-quiz|cta-quiz.*shuffle|random.*cta-quiz-question/i', $quiz_js ),
	'No quiz question or answer-choice randomization in learner JS'
);
assert_true(
	false !== strpos( file_get_contents( $root . '/includes/class-cta-database.php' ), 'ORDER BY order_index ASC' ),
	'Questions render in fixed order_index sequence'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
