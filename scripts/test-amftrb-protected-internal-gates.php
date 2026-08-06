<?php
/**
 * Unit test: AMFTRB internal folders denied; rationales gated until preserved attempt.
 *
 * Run: php scripts/test-amftrb-protected-internal-gates.php
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
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		unset( $type );
		return '2026-08-06 12:00:00';
	}
}

$GLOBALS['cta_user_meta'] = array();

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key, $single = false ) {
		$store = $GLOBALS['cta_user_meta'][ $user_id ][ $key ] ?? null;
		if ( $single ) {
			return null === $store ? '' : $store;
		}
		return null === $store ? array() : array( $store );
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) {
		$GLOBALS['cta_user_meta'][ $user_id ][ $key ] = $value;
		return true;
	}
}

class CTA_Fake_Wpdb {
	public $prefix = 'wp_';
	public function prepare( $query ) {
		return $query;
	}
	public function get_var( $query ) {
		unset( $query );
		return 0;
	}
}

$GLOBALS['wpdb'] = new CTA_Fake_Wpdb();
global $wpdb;
$wpdb = $GLOBALS['wpdb'];

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

	public static function uses_assessment_gates( $course ) {
		if ( ! is_object( $course ) ) {
			return false;
		}
		$slug = isset( $course->slug ) ? (string) $course->slug : '';
		return 'lmft-amftrb-national-exam-preparation' === $slug;
	}
}

class CTA_Database {
	public static $enrollment = true;
	public static $course;

	public static function get_user_enrollment( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		if ( ! self::$enrollment ) {
			return null;
		}
		return (object) array( 'status' => 'active' );
	}

	public static function get_course( $course_id ) {
		unset( $course_id );
		return self::$course;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-cta-course-materials.php';

$pass = 0;
$fail = 0;

function cta_assert( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		echo "PASS: {$label}\n";
		++$pass;
	} else {
		echo "FAIL: {$label}\n";
		++$fail;
	}
}

CTA_Database::$course = (object) array(
	'id'           => 99,
	'slug'         => 'lmft-amftrb-national-exam-preparation',
	'product_type' => 'exam_prep',
);

// Internal controls paths must be denied.
$internal_paths = array(
	'03_INTERNAL_CONTROLS/Assessment_and_Program_Blueprints/foo.docx',
	'_packages/foo/03_INTERNAL_CONTROLS/Audio_Production/bar.txt',
	'Program_Architecture_and_Audits/audit.csv',
	'Protected_Inventory/inventory.xlsx',
	'Workbook_Blueprints/wb1.docx',
	'02_PROTECTED_RATIONALES/Workbook_Banks/key.docx',
);
foreach ( $internal_paths as $p ) {
	cta_assert(
		CTA_Course_Materials::is_admin_restricted_source_path( $p ),
		"deny marker: {$p}"
	);
}

// Learner audio path must NOT be denied by Audio_Production marker.
cta_assert(
	! CTA_Course_Materials::is_admin_restricted_source_path( 'assets/course-materials/lmft-amftrb/audio/track01.mp3' ),
	'learner audio path not denied'
);

$rationale = (object) array(
	'id'                     => 1,
	'course_id'              => 99,
	'title'                  => 'Workbook 1 — Answer Key and Detailed Rationales',
	'file_path'              => 'assets/course-materials/lmft-amftrb/rationales/WB1.docx',
	'file_url'               => '',
	'unlock_after_quiz_type' => 'wb1_bank',
);

$candidate = (object) array(
	'id'               => 2,
	'course_id'        => 99,
	'title'            => 'Workbook 1 — 17-Question Candidate Bank',
	'file_path'        => 'assets/course-materials/lmft-amftrb/question-banks/WB1_Candidate_Bank.docx',
	'file_url'         => '',
	'is_practice_test' => 1,
);

cta_assert(
	'wb1_bank' === CTA_Course_Materials::infer_preserved_attempt_type( $candidate ),
	'infer wb1_bank from candidate bank'
);

cta_assert(
	! CTA_Course_Materials::user_can_access( 7, $rationale ),
	'rationale locked before preserved attempt'
);

$result = CTA_Course_Materials::mark_preserved_attempt( 7, 99, 'wb1_bank' );
cta_assert( true === $result, 'mark preserved attempt succeeds' );

cta_assert(
	CTA_Course_Materials::user_has_preserved_attempt( 7, 99, 'wb1_bank' ),
	'preserved attempt recorded'
);

cta_assert(
	CTA_Course_Materials::user_can_access( 7, $rationale ),
	'rationale unlocked after preserved attempt'
);

cta_assert(
	CTA_Course_Materials::user_can_access( 7, $candidate ),
	'candidate bank accessible while enrolled'
);

$filtered = CTA_Course_Materials::filter_student_visible_resources(
	array(
		$candidate,
		(object) array(
			'id'        => 3,
			'title'     => 'Internal Blueprint',
			'file_path' => '03_INTERNAL_CONTROLS/Workbook_Blueprints/x.docx',
			'file_url'  => '',
		),
	)
);
cta_assert( 1 === count( $filtered ), 'filter strips internal-controls resource from list' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
