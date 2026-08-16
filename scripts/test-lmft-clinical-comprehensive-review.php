<?php
/**
 * Validate LMFT Clinical Form A/B per-form review unlock logic.
 *
 * Usage: php scripts/test-lmft-clinical-comprehensive-review.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

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

require_once $root . '/includes/class-cta-lmft-clinical-comprehensive-scoring.php';
require_once $root . '/includes/class-cta-lmft-clinical-comprehensive-review.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-answer-sync.php';
require_once $root . '/includes/class-cta-course-materials.php';

$course = (object) array(
	'id'   => 10,
	'slug' => 'lmft-california-clinical-exam-preparation',
);

$form_a_quiz = (object) array(
	'id'        => 101,
	'quiz_type' => 'form_a',
	'title'     => 'Comprehensive Simulation - Form A',
);

$form_b_quiz = (object) array(
	'id'        => 102,
	'quiz_type' => 'form_b',
	'title'     => 'Comprehensive Simulation - Form B',
);

assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::applies_to_quiz( $form_a_quiz, $course ),
	'Review rules apply to Form A'
);
assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::applies_to_quiz( $form_b_quiz, $course ),
	'Review rules apply to Form B'
);
assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::should_suppress_learner_answer_reveal( $form_a_quiz, $course, 999001 ),
	'Form A review stays locked before Form A submission'
);
assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::should_suppress_learner_answer_reveal( $form_b_quiz, $course, 999001 ),
	'Form B review stays locked before Form B submission'
);

$form_a_record = CTA_Lmft_Clinical_Comprehensive_Review::get_answer_record_for_order( $form_a_quiz, 0 );
assert_true(
	is_array( $form_a_record ) && ! empty( $form_a_record['correct_option'] ),
	'Form A admin answer record resolves for question 1'
);

$form_a_rationale = CTA_Lmft_Clinical_Comprehensive_Review::get_learner_explanation_for_question(
	$form_a_quiz,
	(object) array(
		'order_index'    => 0,
		'correct_option' => strtolower( (string) ( $form_a_record['correct_option'] ?? 'a' ) ),
	)
);
assert_true(
	'' !== trim( $form_a_rationale ),
	'Form A learner rationale resolves from admin-only key'
);

$form_a_resource = (object) array(
	'title'     => 'Form A — Answer Key and Detailed Rationales',
	'file_path' => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_A_Answer_Key_and_Detailed_Rationales_v1.0.docx',
);
$form_b_resource = (object) array(
	'title'     => 'Form B — Answer Key and Detailed Rationales',
	'file_path' => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_B_Answer_Key_and_Detailed_Rationales_v1.0.docx',
);

assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::resource_is_form_a_review( $form_a_resource ),
	'Form A download resource maps to form_a gate'
);
assert_true(
	! CTA_Lmft_Clinical_Comprehensive_Review::resource_is_form_a_review( $form_b_resource ),
	'Form B download resource does not map to form_a gate'
);
assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::resource_is_form_b_review( $form_b_resource ),
	'Form B download resource maps to form_b gate'
);
assert_true(
	! CTA_Lmft_Clinical_Comprehensive_Review::resource_is_form_b_review( $form_a_resource ),
	'Form A download resource does not map to form_b gate'
);

assert_true(
	'form_a' === CTA_Course_Materials::infer_protected_rationale_unlock_type( $form_a_resource ),
	'Protected Form A resource unlock gate is form_a'
);
assert_true(
	'form_b' === CTA_Course_Materials::infer_protected_rationale_unlock_type( $form_b_resource ),
	'Protected Form B resource unlock gate is form_b'
);

$quiz_php      = file_get_contents( $root . '/public/class-cta-quiz.php' );
$exam_center   = file_get_contents( $root . '/includes/class-cta-exam-prep-exam-center.php' );
$form_a_sync   = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php' );
$form_b_sync   = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-b-answer-sync.php' );
$lms           = file_get_contents( $root . '/cta-lms.php' );

assert_true(
	false !== strpos( $quiz_php, 'get_learner_explanation_for_question' ),
	'Submit path injects secured rationales after form submission'
);
assert_true(
	false !== strpos( $quiz_php, 'Submitting this form is the unlock event' ),
	'Submit path reveals answers for the submitted form immediately'
);
assert_true(
	false !== strpos( $exam_center, 'user_has_completed_attempt' ),
	'Exam center review materials require a completed submission'
);
assert_true(
	false !== strpos( $form_a_sync, 'CTA_Lmft_Clinical_Comprehensive_Review::should_suppress_learner_answer_reveal' ),
	'Form A reveal filter delegates to per-form review unlock'
);
assert_true(
	false !== strpos( $form_b_sync, 'CTA_Lmft_Clinical_Comprehensive_Review::should_suppress_learner_answer_reveal' ),
	'Form B reveal filter delegates to per-form review unlock'
);
assert_true(
	false !== strpos( $lms, 'class-cta-lmft-clinical-comprehensive-review.php' ),
	'Review unlock class is bootstrapped'
);
assert_true(
	false !== strpos( $lms, '1.0.247' ),
	'Version bump 1.0.247'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
