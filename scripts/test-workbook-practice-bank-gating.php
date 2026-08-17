<?php
/**
 * Verify workbook Practice Banks unlock after the matching workbook only.
 *
 * Usage: php scripts/test-workbook-practice-bank-gating.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$fail = 0;
$pass = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
function assert_true( $cond, $msg ) {
	global $fail, $pass;
	if ( $cond ) {
		echo "PASS: {$msg}\n";
		++$pass;
	} else {
		echo "FAIL: {$msg}\n";
		++$fail;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $t Text.
	 * @return string
	 */
	function __( $t ) {
		return $t;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $t Text.
	 * @return string
	 */
	function sanitize_key( $t ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $t ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $v Value.
	 * @return int
	 */
	function absint( $v ) {
		return abs( (int) $v );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub.
	 */
	class WP_Error {
		/** @var string */
		public $code;
		/** @var string */
		public $message;

		/**
		 * @param string $code    Code.
		 * @param string $message Message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return (string) $this->message;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

require_once $root . '/includes/class-cta-exam-prep-lessons.php';
require_once $root . '/includes/class-cta-exam-prep-workbooks.php';

assert_true( class_exists( 'CTA_Exam_Prep_Workbooks' ), 'Workbooks class loads' );

$wb1_bank = (object) array(
	'id'        => 101,
	'title'     => 'Workbook 1 — 17-Question Practice Bank',
	'quiz_type' => 'wb1_bank',
);
$wb2_bank = (object) array(
	'id'        => 102,
	'title'     => 'Workbook 2 — 17-Question Practice Bank',
	'quiz_type' => 'wb2_bank',
);
$form_a = (object) array(
	'id'        => 200,
	'title'     => 'Form A Comprehensive Simulation',
	'quiz_type' => 'form_a',
);

assert_true( CTA_Exam_Prep_Workbooks::is_workbook_quiz( $wb1_bank ), 'wb1_bank is workbook-scoped' );
assert_true( CTA_Exam_Prep_Workbooks::is_workbook_quiz( $wb2_bank ), 'wb2_bank is workbook-scoped' );
assert_true( ! CTA_Exam_Prep_Workbooks::is_workbook_quiz( $form_a ), 'form_a is not workbook-scoped' );
assert_true( 1 === CTA_Exam_Prep_Workbooks::workbook_number_from_quiz( $wb1_bank ), 'WB1 number from quiz' );
assert_true( 2 === CTA_Exam_Prep_Workbooks::workbook_number_from_quiz( $wb2_bank ), 'WB2 number from quiz' );

$msg1 = CTA_Exam_Prep_Workbooks::workbook_practice_bank_lock_message( $wb1_bank );
$msg2 = CTA_Exam_Prep_Workbooks::workbook_practice_bank_lock_message( $wb2_bank );
assert_true( false !== strpos( $msg1, 'Workbook 1' ), 'WB1 lock message names Workbook 1' );
assert_true( false !== strpos( $msg2, 'Workbook 2' ), 'WB2 lock message names Workbook 2' );
assert_true( false === stripos( $msg1, 'all program workbooks' ), 'WB1 lock message is not program-wide' );

if ( ! class_exists( 'CTA_Database' ) ) {
	/**
	 * Minimal database stub for module matching.
	 */
	class CTA_Database {
		/** @var array<int,object> */
		public static $modules = array();

		/**
		 * @param int $course_id Course ID.
		 * @return array
		 */
		public static function get_course_modules( $course_id ) {
			return self::$modules;
		}

		/**
		 * @param int $user_id   User ID.
		 * @param int $course_id Course ID.
		 * @return object|null
		 */
		public static function get_user_enrollment( $user_id, $course_id ) {
			return null;
		}
	}
}

CTA_Database::$modules = array(
	(object) array( 'id' => 11, 'title' => 'Workbook 1: Assessment Foundations' ),
	(object) array( 'id' => 12, 'title' => 'Workbook 2: Diagnosis' ),
	(object) array( 'id' => 13, 'title' => 'Workbook 3: Treatment' ),
);

$course              = (object) array( 'id' => 50, 'slug' => 'lpcc-ncmhce-exam-preparation' );
$enrollment_wb1_only = (object) array( 'modules_completed' => wp_json_encode( array( 11 ) ) );
$enrollment_none     = (object) array( 'modules_completed' => wp_json_encode( array() ) );

assert_true(
	array( 11 ) === CTA_Exam_Prep_Workbooks::completed_module_ids_from_enrollment( $enrollment_wb1_only ),
	'Completed module IDs parse from enrollment'
);

$matched = CTA_Exam_Prep_Workbooks::find_matching_workbook_module( 50, $wb1_bank );
assert_true( $matched && 11 === (int) $matched->id, 'WB1 bank matches Workbook 1 module' );
$matched2 = CTA_Exam_Prep_Workbooks::find_matching_workbook_module( 50, $wb2_bank );
assert_true( $matched2 && 12 === (int) $matched2->id, 'WB2 bank matches Workbook 2 module' );

assert_true(
	CTA_Exam_Prep_Workbooks::user_completed_matching_workbook( 1, 50, $wb1_bank, $enrollment_wb1_only ),
	'WB1 bank unlocks when only Workbook 1 is complete'
);
assert_true(
	! CTA_Exam_Prep_Workbooks::user_completed_matching_workbook( 1, 50, $wb2_bank, $enrollment_wb1_only ),
	'WB2 bank stays locked when only Workbook 1 is complete'
);
assert_true(
	! CTA_Exam_Prep_Workbooks::user_completed_matching_workbook( 1, 50, $wb1_bank, $enrollment_none ),
	'WB1 bank locked when Workbook 1 incomplete'
);

$ok = CTA_Exam_Prep_Workbooks::assert_can_access_workbook_practice_bank( 1, $course, $wb1_bank, $enrollment_wb1_only );
assert_true( true === $ok, 'assert allows WB1 Start/Retry after Workbook 1 complete' );

$blocked = CTA_Exam_Prep_Workbooks::assert_can_access_workbook_practice_bank( 1, $course, $wb1_bank, $enrollment_none );
assert_true( is_wp_error( $blocked ), 'assert blocks WB1 when Workbook 1 incomplete' );
assert_true(
	is_wp_error( $blocked ) && false !== strpos( $blocked->get_error_message(), 'Workbook 1' ),
	'blocked message references Workbook 1 specifically'
);
assert_true(
	is_wp_error( $blocked ) && false === stripos( $blocked->get_error_message(), 'all program workbooks' ),
	'blocked message does not require all program workbooks'
);

$lock_wb1 = CTA_Exam_Prep_Workbooks::get_quiz_card_lock_state( $wb1_bank, $course, 1, $enrollment_wb1_only, false, false );
assert_true( empty( $lock_wb1['locked'] ), 'Card: WB1 unlocked with only WB1 complete (program incomplete)' );

$lock_wb2 = CTA_Exam_Prep_Workbooks::get_quiz_card_lock_state( $wb2_bank, $course, 1, $enrollment_wb1_only, false, false );
assert_true( ! empty( $lock_wb2['locked'] ), 'Card: WB2 still locked with only WB1 complete' );

$lock_form = CTA_Exam_Prep_Workbooks::get_quiz_card_lock_state( $form_a, $course, 1, $enrollment_wb1_only, false, false );
assert_true( ! empty( $lock_form['locked'] ), 'Card: Form A still requires all workbooks' );
assert_true(
	false !== stripos( (string) $lock_form['lock_msg'], 'all program workbooks' ),
	'Card: Form A keeps program-wide lock message'
);

$quiz_src = file_get_contents( $root . '/public/class-cta-quiz.php' );
assert_true(
	false !== strpos( $quiz_src, 'assert_can_access_workbook_practice_bank' ),
	'Quiz Start/Retry path uses per-workbook Practice Bank gate'
);
assert_true(
	false !== strpos( $quiz_src, 'is_workbook_quiz' ),
	'Quiz render distinguishes workbook Practice Banks from program assessments'
);

$dash_src = file_get_contents( $root . '/public/class-cta-student-dashboard.php' );
assert_true(
	false !== strpos( $dash_src, 'get_quiz_card_lock_state' ),
	'Dashboard quiz cards use per-quiz lock helper'
);

$plugin = file_get_contents( $root . '/cta-plugin.php' );
assert_true( false !== strpos( $plugin, '1.0.261' ), 'Plugin version bumped to 1.0.261' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
