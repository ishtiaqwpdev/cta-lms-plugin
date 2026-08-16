<?php
/**
 * Validate LMFT Clinical Comprehensive Simulation Form B import (PROMPT 07–12).
 *
 * Usage: php scripts/test-lmft-clinical-form-b-import.php
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

require_once $root . '/includes/class-cta-lmft-clinical-form-b-sync.php';

$questions = CTA_Lmft_Clinical_Form_B_Sync::get_questions();
assert_true( 150 === count( $questions ), 'Form B seed defines 150 total slots' );
assert_true( 150 === CTA_Lmft_Clinical_Form_B_Sync::count_imported_items( $questions ), 'All 150 learner items imported with no placeholders' );

$expected_q126_stem = "Sixteen months after a spouse dies, a client reports that for the past six weeks there has been persistent depressed mood, pervasive inability to experience pleasure, insomnia, impaired concentration, and global worthlessness. The client misses the spouse but says the primary distress is \u{201c}feeling like a failure as a person in every part of my life\u{201d} rather than persistent yearning or preoccupation with the deceased. Which diagnosis is MOST supported?";
assert_true(
	$expected_q126_stem === (string) ( $questions[125]['question_text'] ?? '' ),
	'Question 126 stem matches PROMPT 12 verbatim'
);
assert_true(
	'Prolonged grief disorder' === (string) ( $questions[125]['option_a'] ?? '' ),
	'Question 126 choice A preserved in order'
);

$expected_q150_stem = 'A client who survived sexual assault is in a safe, supportive relationship and wants more physical closeness with the partner. Certain kinds of touch still trigger freezing and sudden withdrawal. The client does not want to avoid intimacy indefinitely and asks for a way to move gradually while remaining in control. Which intervention should the therapist use?';
assert_true(
	$expected_q150_stem === (string) ( $questions[149]['question_text'] ?? '' ),
	'Question 150 stem matches PROMPT 12 verbatim'
);
assert_true(
	'Plan nonsexual affection with explicit opt-in choices, rehearse brief consent signals for each contact, and use a shared comfort scale before and after each type of touch.' === (string) ( $questions[149]['option_b'] ?? '' ),
	'Question 150 choice B preserved in order'
);

assert_true(
	false === ( 0 === strpos( (string) ( $questions[149]['question_text'] ?? '' ), '[Import pending' ) ),
	'Question 150 is a real imported item, not a placeholder'
);

assert_true(
	'Comprehensive Simulation - Form B' === CTA_Lmft_Clinical_Form_B_Sync::FORM_TITLE,
	'Assessment title matches requested label'
);
assert_true(
	150 === CTA_Lmft_Clinical_Form_B_Sync::IMPORTED_THROUGH,
	'Sync tracks import progress through item 150'
);
assert_true(
	'active' === CTA_Lmft_Clinical_Form_B_Sync::resolve_quiz_status( $questions ),
	'Form activates once all 150 learner items are imported'
);

$lms       = file_get_contents( $root . '/cta-lms.php' );
$quiz_js   = file_get_contents( $root . '/assets/js/main.js' );
$items_src = file_get_contents( $root . '/includes/quiz-seeds/lmft-clinical-form-b-items-126-150.php' );

assert_true( false !== strpos( $lms, '1.0.232' ), 'Version bump 1.0.232' );
assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Form_B_Sync::sync' ),
	'Upgrade hook syncs rebuilt Form B'
);
assert_true(
	false !== strpos( $items_src, 'CTA-LMFT-CA-FB-150' ),
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
