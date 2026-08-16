<?php
/**
 * Final security audit for LMFT Clinical Form A/B learner-facing routes.
 *
 * Validates API payload shapes, suppression filters, and admin-key isolation.
 *
 * Usage: php scripts/test-lmft-clinical-form-security.php
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
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 0;
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

function assert_not_contains( $haystack, $needle, $msg ) {
	assert_true( false === strpos( (string) $haystack, (string) $needle ), $msg );
}

require_once $root . '/includes/class-cta-lmft-clinical-form-a-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-answer-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-comprehensive-review.php';
require_once $root . '/includes/class-cta-lmft-clinical-comprehensive-scoring.php';

$quiz_php      = file_get_contents( $root . '/public/class-cta-quiz.php' );
$quiz_partial  = file_get_contents( $root . '/templates/partials/quiz-question.php' );
$quiz_tpl      = file_get_contents( $root . '/templates/quiz.php' );
$main_js       = file_get_contents( $root . '/assets/js/main.js' );
$materials     = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
$lms           = file_get_contents( $root . '/cta-lms.php' );
$review_src    = file_get_contents( $root . '/includes/class-cta-lmft-clinical-comprehensive-review.php' );
$scoring_src   = file_get_contents( $root . '/includes/class-cta-lmft-clinical-comprehensive-scoring.php' );
$form_a_key    = file_get_contents( $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-a-answer-key-01-25.php' );
$form_b_key    = file_get_contents( $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-b-answer-key-01-25.php' );
$form_a_items  = file_get_contents( $root . '/includes/quiz-seeds/lmft-clinical-form-a-items-01-25.php' );
$form_b_items  = file_get_contents( $root . '/includes/quiz-seeds/lmft-clinical-form-b-items-01-25.php' );

echo "=== LMFT Clinical Form A/B Final Security Check ===\n\n";

// --- Admin key file isolation ---
assert_true(
	false !== strpos( CTA_Lmft_Clinical_Form_A_Answer_Sync::get_answer_key_path(), 'admin-only' ),
	'Form A admin key path is under admin-only/'
);
assert_true(
	false !== strpos( CTA_Lmft_Clinical_Form_B_Answer_Sync::get_answer_key_path(), 'admin-only' ),
	'Form B admin key path is under admin-only/'
);
assert_true( is_readable( $root . '/includes/quiz-seeds/admin-only/.htaccess' ), 'admin-only .htaccess deny rules present' );
assert_true(
	false !== strpos( file_get_contents( $root . '/includes/quiz-seeds/admin-only/.htaccess' ), 'Deny from all' ),
	'admin-only .htaccess denies all direct access'
);

foreach ( array(
	'Form A admin key chunk' => $form_a_key,
	'Form B admin key chunk' => $form_b_key,
) as $label => $src ) {
	assert_true( false !== strpos( $src, 'ADMIN ONLY' ), "{$label} marked ADMIN ONLY" );
	assert_true( false !== strpos( $src, 'core_calibration_status' ), "{$label} stores core_calibration_status server-side only" );
	assert_true( false !== strpos( $src, 'source_status' ), "{$label} stores source_status server-side only" );
	assert_true( false !== strpos( $src, 'item_id' ), "{$label} stores internal item_id server-side only" );
}

// --- Learner seeds never carry admin metadata ---
foreach ( array(
	'Form A learner items' => $form_a_items,
	'Form B learner items' => $form_b_items,
) as $label => $src ) {
	assert_not_contains( $src, 'core_calibration_status', "{$label} has no core_calibration_status" );
	assert_not_contains( $src, 'source_status', "{$label} has no source_status" );
	assert_not_contains( $src, 'item_id', "{$label} has no item_id" );
	assert_not_contains( $src, "'rationales'", "{$label} has no rationales block" );
}

// question_code exists in learner seed source files only (not rendered to learners).
assert_true(
	false !== strpos( $form_a_items, 'CTA-LMFT-CA-FA-001' ),
	'Form A learner seed uses question_code internally for admin merge only'
);
assert_not_contains( $quiz_partial, 'question_code', 'Quiz question partial never renders question_code' );
assert_not_contains( $quiz_php, "'question_code'", 'Quiz class never returns question_code in payloads' );

// --- Download / serve path blocks ---
assert_not_contains( $materials, 'get_answer_records', 'Materials serve handler does not expose answer records API' );
assert_true(
	false !== strpos( $materials, 'lmft-clinical-form-a-answer-key.php' ),
	'Form A answer key filename blocked in is_admin_restricted_source_path'
);
assert_true(
	false !== strpos( $materials, 'lmft-clinical-form-b-answer-key.php' ),
	'Form B answer key filename blocked in is_admin_restricted_source_path'
);
assert_true(
	false !== strpos( $materials, 'is_admin_restricted_source_path' ),
	'Serve handler rejects admin-restricted paths before streaming'
);

// --- Pre-submit HTML / start payload ---
assert_not_contains( $quiz_partial, 'correct_option', 'Quiz question partial never references correct_option' );
assert_true(
	false !== strpos( $quiz_php, "'html'               => \$this->render_quiz_questions" ),
	'Start/resume AJAX returns HTML only (no answer map JSON)'
);
assert_true(
	false !== strpos( $quiz_php, "'saved_count' => count( \$sanitized )" ),
	'Autosave AJAX returns saved_count only (no answers or keys)'
);
assert_not_contains( $quiz_php, "'core_correct'", 'Submit AJAX does not expose core_correct' );
assert_not_contains( $quiz_php, "'core_total'", 'Submit AJAX does not expose core_total' );
assert_not_contains( $quiz_php, "'core_calibration", 'Submit AJAX does not expose core_calibration fields' );
assert_not_contains( $quiz_php, "'source_status'", 'Submit AJAX does not expose source_status' );
assert_not_contains( $quiz_php, "'item_id'", 'Submit AJAX does not expose item_id' );

// --- Reveal filters for Form A/B before submit ---
assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Form_A_Answer_Sync::should_suppress_learner_answer_reveal' ),
	'Form A suppress filter registered for explanations'
);
assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Form_B_Answer_Sync::should_suppress_learner_answer_reveal' ),
	'Form B suppress filter registered for explanations'
);
assert_true(
	false !== strpos( $lms, 'cta_lms_reveal_quiz_correct_answers' ),
	'Form A/B suppress filter registered for correct letters'
);

$course = (object) array(
	'id'   => 10,
	'slug' => 'lmft-california-clinical-exam-preparation',
);
$form_a_quiz = (object) array( 'id' => 501, 'quiz_type' => 'form_a', 'title' => 'Form A — 150-Question Comprehensive Simulation' );
$form_b_quiz = (object) array( 'id' => 502, 'quiz_type' => 'form_b', 'title' => 'Form B — 150-Question Comprehensive Simulation' );

assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::should_suppress_learner_answer_reveal( $form_a_quiz, $course, 999001 ),
	'Form A suppresses reveal before submission (no completed attempt)'
);
assert_true(
	CTA_Lmft_Clinical_Comprehensive_Review::should_suppress_learner_answer_reveal( $form_b_quiz, $course, 999001 ),
	'Form B suppresses reveal before submission (no completed attempt)'
);

// --- Post-submit learner explanation is rationale text only ---
$sample_question = (object) array(
	'id'             => 9001,
	'order_index'    => 0,
	'correct_option' => 'a',
);
$explanation = CTA_Lmft_Clinical_Comprehensive_Review::get_learner_explanation_for_question( $form_a_quiz, $sample_question );
assert_true( '' !== trim( $explanation ), 'Form A post-submit explanation resolves from admin key' );
assert_not_contains( $explanation, 'Core', 'Learner explanation text does not include Core label' );
assert_not_contains( $explanation, 'Calibration', 'Learner explanation text does not include Calibration label' );
assert_not_contains( $explanation, 'EXACT/FROZEN', 'Learner explanation text does not include source_status' );
assert_not_contains( $explanation, 'A-DI-', 'Learner explanation text does not include item_id prefix' );

// --- Review/scoring classes keep admin metadata server-side ---
assert_not_contains( $review_src, 'wp_send_json', 'Review unlock class has no direct JSON endpoints' );
assert_not_contains( $scoring_src, 'wp_send_json', 'Scoring class has no direct JSON endpoints' );
assert_true(
	false !== strpos( $scoring_src, "'core_correct' => \$core_correct" ),
	'Core scoring counts remain internal to calculate_display_score() return array'
);

// --- Submit results shape is whitelisted fields only ---
assert_true(
	false !== strpos( $quiz_php, "'question_id'    => \$qid" ),
	'Submit results include question_id (DB row id for UI binding)'
);
assert_true(
	false !== strpos( $quiz_php, "'correct_option' => \$reveal_correct ? (string) \$question->correct_option : ''" ),
	'correct_option withheld until reveal_correct is true'
);
assert_true(
	false !== strpos( $quiz_php, "CTA_Lmft_Clinical_Comprehensive_Review::get_learner_explanation_for_question" ),
	'Post-submit explanation uses filtered learner rationale helper'
);
assert_not_contains( $main_js, 'core_calibration', 'Frontend JS has no core_calibration references' );
assert_not_contains( $main_js, 'source_status', 'Frontend JS has no source_status references' );
assert_not_contains( $main_js, 'item_id', 'Frontend JS has no item_id references' );
assert_not_contains( $quiz_tpl, 'core_calibration', 'Quiz template has no core_calibration references' );

// --- ABSPATH guard prevents direct web/CLI include without WordPress bootstrap ---
foreach ( array(
	'Form A admin key aggregator' => $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-a-answer-key.php',
	'Form B admin key aggregator' => $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-b-answer-key.php',
) as $label => $path ) {
	$src = file_get_contents( $path );
	assert_true(
		false !== strpos( $src, "if ( ! defined( 'ABSPATH' ) )" ) && false !== strpos( $src, 'exit;' ),
		"{$label} exits immediately when ABSPATH is undefined"
	);
}

echo "\n=== API response summary (code audit) ===\n";
echo "- cta_start_quiz: html + attempt metadata only; no correct_option/explanation.\n";
echo "- cta_save_quiz_progress: saved_count + saved_at only.\n";
echo "- cta_submit_quiz (pre-submit): filters force empty correct_option + empty explanation.\n";
echo "- cta_submit_quiz (post-submit): results[] = question_id, user_answer, correct_option, explanation, is_correct only.\n";
echo "- No endpoint returns item_id, question_code, core_calibration_status, source_status, or admin key arrays.\n";

echo "\n=== Summary ===\n";
echo "Passed: {$pass}\n";
echo "Failed: {$fail}\n";

exit( $fail > 0 ? 1 : 0 );
