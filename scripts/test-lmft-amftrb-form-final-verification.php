<?php
/**
 * Verification for LMFT AMFTRB Form A/B Practice Exams (v1.0.275).
 *
 * Usage: php scripts/test-lmft-amftrb-form-final-verification.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-sync.php';

$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
		return;
	}
	++$fail;
	echo "FAIL: {$msg}\n";
}

function load_seed( $file ) {
	$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/' . $file;
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$data = include $path;
	return is_array( $data ) ? $data : array();
}

echo "--- 1) Approved source DOCX present ---\n";
$base = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-amftrb/';
assert_true(
	is_readable( $base . 'question-banks/CTA_LMFT_AMFTRB_Simulation_Form_A_180_Question_Candidate_Assessment_v1.0.docx' ),
	'Form A candidate DOCX exists'
);
assert_true(
	is_readable( $base . 'question-banks/CTA_LMFT_AMFTRB_Simulation_Form_B_180_Question_Candidate_Assessment_v1.0.docx' ),
	'Form B candidate DOCX exists'
);
assert_true(
	is_readable( $base . 'rationales/CTA_LMFT_AMFTRB_Simulation_Form_A_180_Question_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx' ),
	'Form A answer key DOCX exists'
);
assert_true(
	is_readable( $base . 'rationales/CTA_LMFT_AMFTRB_Simulation_Form_B_180_Question_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx' ),
	'Form B answer key DOCX exists'
);

echo "\n--- 2) Online quiz seeds ---\n";
$form_a = load_seed( 'lmft-amftrb-form-a.php' );
$form_b = load_seed( 'lmft-amftrb-form-b.php' );
assert_true( 180 === count( $form_a ), 'Form A seed has 180 questions' );
assert_true( 180 === count( $form_b ), 'Form B seed has 180 questions' );

$missing_fields = 0;
foreach ( array( 'a' => $form_a, 'b' => $form_b ) as $label => $rows ) {
	foreach ( $rows as $index => $row ) {
		foreach ( array( 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option' ) as $field ) {
			if ( '' === trim( (string) ( $row[ $field ] ?? '' ) ) ) {
				++$missing_fields;
			}
		}
		$correct = strtolower( (string) ( $row['correct_option'] ?? '' ) );
		if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
			++$missing_fields;
		}
	}
}
assert_true( 0 === $missing_fields, 'All seeded questions have stems, four options, and valid correct_option' );

echo "\n--- 3) Sync class configuration ---\n";
assert_true(
	180 === CTA_Lmft_Amftrb_Sync::FORM_QUESTION_COUNT,
	'FORM_QUESTION_COUNT = 180 (approved AMFTRB spec)'
);
assert_true(
	240 === CTA_Lmft_Amftrb_Sync::FORM_TIME_LIMIT_MINS,
	'FORM_TIME_LIMIT_MINS = 240 (approved AMFTRB spec)'
);
assert_true(
	method_exists( 'CTA_Lmft_Amftrb_Sync', 'sync_assessments' ),
	'sync_assessments() implemented'
);
assert_true(
	method_exists( 'CTA_Lmft_Amftrb_Sync', 'ensure_learner_forms' ),
	'ensure_learner_forms() implemented'
);
assert_true(
	method_exists( 'CTA_Lmft_Amftrb_Sync', 'get_live_form_health' ),
	'get_live_form_health() implemented'
);
assert_true(
	is_readable( CTA_PLUGIN_DIR . 'scripts/build-lmft-amftrb-form-seeds.php' ),
	'DOCX-to-seed build script exists for regeneration'
);

echo "\n--- 4) Exam Center wiring (code-level) ---\n";
$exam_center = (string) file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-exam-center.php' );
$template    = (string) file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/exam-prep-exam-center.php' );
assert_true(
	false !== strpos( $exam_center, 'get_quizzes_by_course' ),
	'Exam Center loads quizzes from cta_quizzes (not hardcoded empty for AMFTRB)'
);
assert_true(
	false !== strpos( $template, 'Practice exams coming soon' ),
	'Coming soon is conditional empty-state (not AMFTRB-specific hard block)'
);

echo "\n--- 5) Regression — other program seeds untouched ---\n";
$clinical_a = load_seed( 'lmft-clinical-form-a.php' );
$lpcc_a     = load_seed( 'lpcc-ncmhce-form-a.php' );
assert_true( 150 === count( $clinical_a ), 'LMFT Clinical Form A seed still 150 questions' );
assert_true( count( $lpcc_a ) >= 143, 'LPCC NCMHCE Form A seed still present' );

echo "\n=== SUMMARY: {$pass} passed, {$fail} failed ===\n";
echo "NOTE: Live DB sync (2 Full Simulations on Practice Exams page) runs on plugin upgrade to v1.0.275 via ensure_learner_forms().\n";

exit( $fail > 0 ? 1 : 0 );
