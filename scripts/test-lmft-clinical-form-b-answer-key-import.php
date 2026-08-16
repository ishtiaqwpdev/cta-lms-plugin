<?php
/**
 * Validate LMFT Clinical Form B admin answer key import (PROMPT 19–24).
 *
 * Usage: php scripts/test-lmft-clinical-form-b-answer-key-import.php
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
require_once $root . '/includes/class-cta-lmft-clinical-form-b-answer-sync.php';

$records = CTA_Lmft_Clinical_Form_B_Answer_Sync::get_answer_records();
assert_true( 150 === count( $records ), 'Form B admin answer key defines 150 records (complete PROMPT 19–24 set)' );

$valid = CTA_Lmft_Clinical_Form_B_Answer_Sync::validate_answer_key( $records );
assert_true( true === $valid, 'Admin answer key validates (letters, rationales, core/calibration status)' );

$q126 = $records['CTA-LMFT-CA-FB-126'] ?? array();
assert_true( 'd' === (string) ( $q126['correct_option'] ?? '' ), 'Question 126 correct answer is D' );
assert_true( 'Core' === (string) ( $q126['core_calibration_status'] ?? '' ), 'Question 126 core_calibration_status stored as Core' );
assert_true(
	false !== strpos( (string) ( $q126['rationales']['D'] ?? '' ), 'generalized depressive symptoms' ),
	'Question 126 choice D rationale preserved verbatim'
);

$q150 = $records['CTA-LMFT-CA-FB-150'] ?? array();
assert_true( 'a' === (string) ( $q150['correct_option'] ?? '' ), 'Question 150 correct answer is A' );
assert_true( 'Core' === (string) ( $q150['core_calibration_status'] ?? '' ), 'Question 150 core_calibration_status stored as Core' );
assert_true(
	false !== strpos( (string) ( $q150['rationales']['A'] ?? '' ), 'consent-based progression' ),
	'Question 150 choice A rationale preserved verbatim'
);

$learner = CTA_Lmft_Clinical_Form_B_Sync::get_questions();
assert_true(
	150 === count( $learner ),
	'Learner Form B seed defines 150 questions'
);
assert_true(
	'x' === (string) ( $learner[0]['correct_option'] ?? '' ),
	'Learner seed for Q1 still has pending correct_option (not merged into learner file)'
);
assert_true(
	'x' === (string) ( $learner[124]['correct_option'] ?? '' ),
	'Learner seed for Q125 still has pending correct_option (not merged into learner file)'
);

assert_true(
	150 === CTA_Lmft_Clinical_Form_B_Answer_Sync::IMPORTED_THROUGH,
	'Sync tracks admin answer import through item 150'
);
assert_true(
	150 === CTA_Lmft_Clinical_Form_B_Answer_Sync::TARGET_QUESTION_COUNT,
	'Form B target question count is 150'
);

$lms        = file_get_contents( $root . '/cta-lms.php' );
$materials  = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
$answer_src = file_get_contents( $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-b-answer-key-126-150.php' );
$sync_src   = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-b-answer-sync.php' );

assert_true( false !== strpos( $lms, '1.0.244' ), 'Version bump 1.0.244' );
assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys' ),
	'Upgrade hook applies Form B secured answer keys'
);
assert_true(
	false !== strpos( $answer_src, 'ADMIN ONLY' ),
	'Answer key chunk 126–150 marked admin-only'
);
assert_true(
	false !== strpos( $answer_src, 'CTA-LMFT-CA-FB-150' ),
	'Question codes assigned for admin items 126–150'
);
assert_true(
	false === strpos( $answer_src, 'CTA-LMFT-CA-FA-' ),
	'Form B admin key does not reference Form A codes'
);
assert_true(
	false !== strpos( $materials, 'lmft-clinical-form-b-answer-key.php' ),
	'Form B answer key path blocked from learner downloads'
);
assert_true(
	false !== strpos( $sync_src, "'explanation'    => ''" ) || false !== strpos( $sync_src, "'explanation'    => \"\"" ),
	'Sync writes correct_option only; DB explanation stays empty'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
