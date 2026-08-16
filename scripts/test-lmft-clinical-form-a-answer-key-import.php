<?php
/**
 * Validate LMFT Clinical Form A admin answer key import (PROMPT 13–18).
 *
 * Usage: php scripts/test-lmft-clinical-form-a-answer-key-import.php
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
require_once $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php';

$records = CTA_Lmft_Clinical_Form_A_Answer_Sync::get_answer_records();
assert_true( 150 === count( $records ), 'Form A admin answer key defines 150 records (complete set)' );

$valid = CTA_Lmft_Clinical_Form_A_Answer_Sync::validate_answer_key( $records );
assert_true( true === $valid, 'Admin answer key validates (letters, rationales, core/calibration status)' );

$q101 = $records['CTA-LMFT-CA-FA-101'] ?? array();
assert_true( 'a' === (string) ( $q101['correct_option'] ?? '' ), 'Question 101 correct answer is A' );

$q126 = $records['CTA-LMFT-CA-FA-126'] ?? array();
assert_true( 'c' === (string) ( $q126['correct_option'] ?? '' ), 'Question 126 correct answer is C' );
assert_true( 'Core' === (string) ( $q126['core_calibration_status'] ?? '' ), 'Question 126 core_calibration_status stored as Core' );
assert_true(
	false !== strpos( (string) ( $q126['rationales']['C'] ?? '' ), 'Comparing previous functioning with changes in responsibilities' ),
	'Question 126 choice C rationale preserved verbatim'
);

$q150 = $records['CTA-LMFT-CA-FA-150'] ?? array();
assert_true( 'b' === (string) ( $q150['correct_option'] ?? '' ), 'Question 150 correct answer is B' );
assert_true(
	false !== strpos( (string) ( $q150['rationales']['B'] ?? '' ), 'stress-amplified co-parenting inconsistency' ),
	'Question 150 choice B rationale preserved verbatim'
);

$learner = CTA_Lmft_Clinical_Form_A_Sync::get_questions();
assert_true(
	150 === count( $learner ),
	'Learner Form A seed defines 150 questions'
);
assert_true(
	'x' === (string) ( $learner[0]['correct_option'] ?? '' ),
	'Learner seed for Q1 still has pending correct_option (not merged into learner file)'
);
assert_true(
	'' === trim( (string) ( $learner[0]['explanation'] ?? '' ) ),
	'Learner seed for Q1 has no inline rationale text'
);
assert_true(
	'x' === (string) ( $learner[149]['correct_option'] ?? '' ),
	'Learner seed for Q150 still has pending correct_option (not merged into learner file)'
);

assert_true(
	150 === CTA_Lmft_Clinical_Form_A_Answer_Sync::IMPORTED_THROUGH,
	'Sync tracks admin answer import through item 150 (complete)'
);
assert_true(
	150 === CTA_Lmft_Clinical_Form_A_Answer_Sync::TARGET_QUESTION_COUNT,
	'Form A target question count is 150'
);

$lms         = file_get_contents( $root . '/cta-lms.php' );
$quiz_php    = file_get_contents( $root . '/public/class-cta-quiz.php' );
$quiz_js     = file_get_contents( $root . '/assets/js/main.js' );
$materials   = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
$answer_src  = file_get_contents( $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-a-answer-key-126-150.php' );
$sync_src    = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php' );

assert_true( false !== strpos( $lms, '1.0.238' ), 'Version bump 1.0.238' );
assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys' ),
	'Upgrade hook applies Form A secured answer keys'
);
assert_true(
	false !== strpos( $answer_src, 'ADMIN ONLY' ),
	'Answer key chunk 126–150 marked admin-only'
);
assert_true(
	false !== strpos( $answer_src, 'CTA-LMFT-CA-FA-150' ),
	'Question codes assigned for admin items 126–150'
);
assert_true(
	false === strpos( $answer_src, 'CTA-LMFT-CA-FB-' ),
	'Form A admin key does not reference Form B codes'
);
assert_true(
	false !== strpos( $materials, 'lmft-clinical-form-a-answer-key.php' ),
	'Answer key path blocked from learner downloads'
);
assert_true(
	false !== strpos( $sync_src, "'explanation'    => ''" ) || false !== strpos( $sync_src, "'explanation'    => \"\"" ),
	'Sync writes correct_option only; DB explanation stays empty'
);
assert_true(
	false !== strpos( $lms, 'cta_lms_reveal_quiz_correct_answers' ),
	'Form A suppresses inline correct-letter reveal via filter'
);
assert_true(
	false !== strpos( $quiz_php, 'cta_lms_reveal_quiz_correct_answers' ),
	'Submit path respects correct-answer reveal filter'
);
assert_true(
	false !== strpos( $quiz_js, 'item.correct_option' ),
	'Learner JS handles suppressed correct_option after submit'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
