<?php
/**
 * Unit test: Form B is independent of Form A in Exam Prep.
 *
 * Run: C:\xampp\php\php.exe scripts/test-form-b-no-form-a-lock.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
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

if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}

require_once dirname( __DIR__ ) . '/includes/class-cta-course-materials.php';

function cta_assert( $cond, $label ) {
	if ( $cond ) {
		echo "PASS: {$label}\n";
		return true;
	}
	echo "FAIL: {$label}\n";
	return false;
}

$pass = true;

$result = CTA_Course_Materials::assert_form_b_accessible( 1, 99 );
$pass   = cta_assert( true === $result, 'assert_form_b_accessible always true (no Form A)' ) && $pass;
$pass   = cta_assert( ! is_wp_error( $result ), 'assert_form_b_accessible is not WP_Error' ) && $pass;

$pass = cta_assert(
	CTA_Course_Materials::user_meets_unlock_gate( 1, 99, 'form_b_ready' ),
	'form_b_ready download gate open without Form A'
) && $pass;

$fake_resource = (object) array(
	'title'     => 'Form B — 122-Question Comprehensive Simulation (Candidate Exam)',
	'file_path' => 'Form_B_Exam_v1.docx',
	'file_url'  => '',
);
$inferred = CTA_Course_Materials::infer_exam_form_download_gate( $fake_resource );
$pass     = cta_assert( 'form_b_ready' === $inferred, 'Form B download still classified as form_b_ready' ) && $pass;
$pass     = cta_assert(
	CTA_Course_Materials::user_meets_unlock_gate( 1, 99, $inferred ),
	'Inferred Form B gate is open'
) && $pass;

// CE-style final quiz unlock is unrelated — modules_complete still gated.
$pass = cta_assert(
	! CTA_Course_Materials::user_meets_unlock_gate( 1, 99, 'modules_complete' ),
	'modules_complete still requires real completion helpers (CE path intact)'
) && $pass;

echo $pass ? "\nALL PASSED\n" : "\nSOME FAILED\n";
exit( $pass ? 0 : 1 );
