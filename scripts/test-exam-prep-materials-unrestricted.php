<?php
/**
 * Unit test: Exam Prep downloadables unrestricted after enrollment.
 *
 * Run: C:\xampp\php\php.exe scripts/test-exam-prep-materials-unrestricted.php
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

class CTA_Exam_Access {
	const PRODUCT_TYPE_EXAM_PREP = 'exam_prep';

	public static function is_exam_prep( $course_or_type ) {
		if ( is_object( $course_or_type ) ) {
			$type = isset( $course_or_type->product_type ) ? (string) $course_or_type->product_type : 'ce';
		} else {
			$type = (string) $course_or_type;
		}
		return self::PRODUCT_TYPE_EXAM_PREP === $type;
	}

	public static function has_active_access( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return true;
	}
}

class CTA_CE_Access {
	public static function is_ce_course( $course ) {
		return ! CTA_Exam_Access::is_exam_prep( $course );
	}

	public static function has_active_access( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return true;
	}
}

class CTA_Database {
	public static $enrollment;
	public static $course;

	public static function get_user_enrollment( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return self::$enrollment;
	}

	public static function get_course( $course_id ) {
		unset( $course_id );
		return self::$course;
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

CTA_Database::$enrollment = (object) array( 'status' => 'active' );
CTA_Database::$course     = (object) array( 'id' => 10, 'product_type' => 'exam_prep' );

$gated_types = array(
	'modules_complete',
	'form_a',
	'form_b',
	'form_b_ready',
	'wb1_bank',
	'checkpoint_1',
	'form_a_remediation',
);

foreach ( $gated_types as $gate ) {
	$resource = (object) array(
		'id'                     => 1,
		'course_id'              => 10,
		'title'                  => 'Study resource ' . $gate,
		'file_path'              => 'materials/sample.docx',
		'file_url'               => '',
		'unlock_after_quiz_type' => $gate,
	);
	$pass = cta_assert(
		CTA_Course_Materials::user_can_access( 7, $resource ),
		"Exam Prep access open despite gate={$gate}"
	) && $pass;
	$pass = cta_assert(
		'' === CTA_Course_Materials::get_unlock_lock_message( 7, $resource ),
		"Exam Prep lock message empty for gate={$gate}"
	) && $pass;
}

// CE still honors unlock gates when set.
CTA_Database::$course = (object) array( 'id' => 20, 'product_type' => 'ce' );
$ce_resource          = (object) array(
	'id'                     => 2,
	'course_id'              => 20,
	'title'                  => 'CE handout',
	'file_path'              => 'materials/ce.pdf',
	'file_url'               => '',
	'unlock_after_quiz_type' => 'modules_complete',
);
$pass = cta_assert(
	! CTA_Course_Materials::user_can_access( 7, $ce_resource ),
	'CE resource still gated by modules_complete'
) && $pass;

echo $pass ? "\nALL PASSED\n" : "\nSOME FAILED\n";
exit( $pass ? 0 : 1 );
