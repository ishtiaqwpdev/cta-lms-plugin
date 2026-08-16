<?php
/**
 * Confirm LMFT Clinical Form A/B are isolated from all CE completion flows.
 *
 * Usage: php scripts/test-lmft-clinical-ce-isolation.php
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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
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

class CTA_Database {
	public static function get_course( $course_id ) {
		unset( $course_id );
		return (object) array(
			'id'                 => 10,
			'slug'               => 'lmft-california-clinical-exam-preparation',
			'title'              => 'LMFT California Clinical Exam Preparation',
			'product_type'       => 'exam_prep',
			'ce_hours'           => 0,
			'awards_ce_hours'    => 0,
			'has_ce_certificate' => 0,
		);
	}
}

require_once $root . '/includes/class-cta-exam-access.php';
require_once $root . '/includes/class-cta-ce-completion.php';

$course = CTA_Database::get_course( 10 );

$sync_src       = file_get_contents( $root . '/includes/class-cta-lmft-clinical-sync.php' );
$form_a_src     = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-a-sync.php' );
$form_b_src     = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-b-sync.php' );
$form_a_key_src = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php' );
$form_b_key_src = file_get_contents( $root . '/includes/class-cta-lmft-clinical-form-b-answer-sync.php' );
$scoring_src    = file_get_contents( $root . '/includes/class-cta-lmft-clinical-comprehensive-scoring.php' );
$review_src     = file_get_contents( $root . '/includes/class-cta-lmft-clinical-comprehensive-review.php' );
$quiz_src       = file_get_contents( $root . '/public/class-cta-quiz.php' );
$cert_src       = file_get_contents( $root . '/public/class-cta-certificates.php' );
$dashboard_src  = file_get_contents( $root . '/public/class-cta-student-dashboard.php' );
$quiz_tpl       = file_get_contents( $root . '/templates/quiz.php' );
$main_js        = file_get_contents( $root . '/assets/js/main.js' );
$eval_q_src     = file_get_contents( $root . '/includes/class-cta-evaluation-questions.php' );

echo "=== LMFT Clinical CE Isolation Check ===\n\n";

// Course classification.
assert_true( CTA_Exam_Access::is_exam_prep( $course ), 'LMFT Clinical course is exam_prep product_type' );
assert_true( ! CTA_Exam_Access::awards_ce( $course ), 'LMFT Clinical does not award CE hours' );
assert_true( ! CTA_Exam_Access::has_ce_certificate( $course ), 'LMFT Clinical has_ce_certificate is false' );
assert_true( false !== strpos( $sync_src, "'product_type'         => 'exam_prep'" ), 'Sync seeds product_type exam_prep' );
assert_true( false !== strpos( $sync_src, "'ce_hours'             => 0" ), 'Sync seeds ce_hours 0' );
assert_true( false !== strpos( $sync_src, "'awards_ce_hours'      => 0" ), 'Sync seeds awards_ce_hours 0' );
assert_true( false !== strpos( $sync_src, "'has_ce_certificate'   => 0" ), 'Sync seeds has_ce_certificate 0' );

// Form A/B implementation classes do not wire CE completion.
foreach ( array(
	'form-a sync'     => $form_a_src,
	'form-b sync'     => $form_b_src,
	'form-a key sync' => $form_a_key_src,
	'form-b key sync' => $form_b_key_src,
	'scoring'         => $scoring_src,
	'review unlock'   => $review_src,
) as $label => $src ) {
	assert_not_contains( $src, 'CTA_CE_Completion', "{$label} does not reference CTA_CE_Completion" );
	assert_not_contains( $src, 'CTA_Certificates', "{$label} does not reference CTA_Certificates" );
	assert_not_contains( $src, 'ajax_submit_evaluation', "{$label} does not reference evaluation AJAX" );
	assert_not_contains( $src, 'ajax_submit_attestation', "{$label} does not reference attestation AJAX" );
	assert_not_contains( $src, 'Evaluation_Sync', "{$label} does not reference CE evaluation sync" );
}

// CE completion guards at course level.
$eval_block = CTA_CE_Completion::assert_can_access_evaluation( 1, 10 );
assert_true( is_wp_error( $eval_block ), 'CE evaluation blocked for exam prep course' );
assert_true(
	'cta_seq_exam_prep' === $eval_block->get_error_code(),
	'CE evaluation block uses cta_seq_exam_prep error code'
);

$attest_block = CTA_CE_Completion::assert_can_access_attestation( 1, 10 );
assert_true( is_wp_error( $attest_block ), 'CE attestation blocked for exam prep course' );

$cert_block = CTA_CE_Completion::assert_can_issue_certificate( 1, 10 );
assert_true( is_wp_error( $cert_block ), 'CE certificate blocked for exam prep course' );

// No dedicated LMFT Clinical evaluation seed.
assert_not_contains( $eval_q_src, 'lmft-california-clinical-exam-preparation', 'Evaluation questions router has no LMFT Clinical slug' );
assert_not_contains( $eval_q_src, 'Lmft_Clinical', 'Evaluation questions router has no LMFT Clinical sync class' );

// Quiz UI and server paths stay on exam-complete, not CE sequence.
assert_true( false !== strpos( $quiz_src, "if ( ! \$is_exam_prep && \$passed_attempt && \$evaluation && \$attestation && ! \$certificate )" ), 'Certificate recovery gated by !is_exam_prep' );
assert_true( false !== strpos( $quiz_src, "if ( ! \$is_exam_prep && \$certificate && \$evaluation && \$attestation && \$passed_attempt )" ), 'certificate_ready view_state gated by !is_exam_prep' );
assert_true( false !== strpos( $quiz_src, "if ( ! \$is_exam_prep && \$passed_attempt && \$evaluation && ! \$attestation" ), 'attestation view_state gated by !is_exam_prep' );
assert_true( false !== strpos( $quiz_src, "if ( ! \$is_exam_prep && \$passed_attempt && ( ! \$evaluation" ), 'evaluation view_state gated by !is_exam_prep' );
assert_true(
	false !== strpos( $quiz_src, "if ( \$is_exam_prep ) {" ) && false !== strpos( $quiz_src, "\$next_step = 'complete';" ),
	'Quiz submit sets next_step complete for exam prep'
);
assert_true( false !== strpos( $quiz_src, 'Exam Preparation Programs do not require a CE evaluation or certificate' ), 'Evaluation AJAX rejects exam prep' );
assert_true( false !== strpos( $quiz_src, 'Exam Preparation Programs do not require attestation' ), 'Attestation AJAX rejects exam prep' );

assert_true( false !== strpos( $cert_src, 'Exam Preparation Programs never issue CE certificates' ), 'Certificate generator rejects exam prep' );

assert_true( false !== strpos( $dashboard_src, "'certificate'     => \$is_exam ? null : \$certificate" ), 'Dashboard hides certificate for exam prep enrollments' );
assert_true( false !== strpos( $dashboard_src, 'CTA_Exam_Access::is_exam_prep( $course ) ) {' ), 'Dashboard finalize_course_completion exits for exam prep' );
assert_true( false !== strpos( $dashboard_src, 'CTA_Exam_Access::is_exam_prep( $course ) ) {' ), 'Dashboard complete_course exits for exam prep' );

assert_true( false !== strpos( $quiz_tpl, 'if ( empty( $is_exam_prep ) ) : ?>' ), 'Quiz template hides evaluation/attestation panels for exam prep' );
assert_true( false !== strpos( $quiz_tpl, 'data-quiz-panel="exam_complete"' ), 'Quiz template exposes exam_complete panel for exam prep' );

assert_true( false !== strpos( $main_js, 'nextStep === "complete" || isExamPrep' ), 'Frontend skips CE evaluation after exam prep pass' );

// Form-specific review unlock only references form_a/form_b gates, not CE artifacts.
assert_true( false !== strpos( $review_src, "'form_a', 'form_b'" ), 'Review unlock applies only to form_a/form_b quiz types' );
assert_not_contains( $review_src, 'certificate', 'Review unlock does not mention certificates' );
assert_not_contains( $review_src, 'evaluation', 'Review unlock does not mention evaluation' );

echo "\n=== Summary ===\n";
echo "Passed: {$pass}\n";
echo "Failed: {$fail}\n";

exit( $fail > 0 ? 1 : 0 );
