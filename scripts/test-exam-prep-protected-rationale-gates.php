<?php
/**
 * Verify Exam Prep protected answer keys / rationales gate on assessment submit.
 *
 * Run: php scripts/test-exam-prep-protected-rationale-gates.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! function_exists( '__' ) ) {
	function __( $t, $d = null ) {
		unset( $d );
		return $t;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return is_string( $s ) ? trim( $s ) : '';
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
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

	public static function uses_assessment_gates( $course ) {
		if ( ! is_object( $course ) ) {
			return false;
		}
		return 'lmft-amftrb-national-exam-preparation' === (string) ( $course->slug ?? '' );
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
	public static $completed_quiz_types = array();

	public static function get_user_enrollment( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return self::$enrollment;
	}

	public static function get_course( $course_id ) {
		unset( $course_id );
		return self::$course;
	}
}

class CTA_Fake_Wpdb {
	public $prefix = 'wp_';
	public function prepare( $query, ...$args ) {
		if ( empty( $args ) ) {
			return $query;
		}
		$i = 0;
		return preg_replace_callback(
			'/%[ds]/',
			static function () use ( $args, &$i ) {
				$val = $args[ $i++ ] ?? '';
				return is_numeric( $val ) ? (string) (int) $val : "'" . addslashes( (string) $val ) . "'";
			},
			$query
		);
	}
	public function get_var( $query ) {
		if ( false !== strpos( $query, 'q.quiz_type' ) ) {
			foreach ( CTA_Database::$completed_quiz_types as $type ) {
				if ( false !== strpos( $query, "'{$type}'" ) ) {
					return 1;
				}
			}
			return 0;
		}
		unset( $query );
		return 0;
	}
}

$GLOBALS['wpdb'] = new CTA_Fake_Wpdb();
global $wpdb;

require_once CTA_PLUGIN_DIR . 'includes/class-cta-course-materials.php';

$pass = 0;
$fail = 0;
function assert_true( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		echo "PASS: {$label}\n";
		++$pass;
	} else {
		echo "FAIL: {$label}\n";
		++$fail;
	}
}

$lcsw = (object) array(
	'id'           => 4,
	'product_type' => 'exam_prep',
	'slug'         => 'lcsw-aswb-clinical-exam-preparation',
);
$open = (object) array(
	'id'           => 4,
	'course_id'    => 4,
	'title'        => 'Student Roadmap and Study Schedules',
	'file_path'    => 'student-support/schedules.docx',
	'file_url'     => '',
);
$form_a_rat = (object) array(
	'id'        => 10,
	'course_id' => 4,
	'title'     => 'Form A — Answer Key and Detailed Rationales',
	'file_path' => 'simulations/Form_A_Answer_Key.docx',
	'file_url'  => '',
);
$form_b_rat = (object) array(
	'id'        => 11,
	'course_id' => 4,
	'title'     => 'Form B — Answer Key and Detailed Rationales',
	'file_path' => 'simulations/Form_B_Answer_Key.docx',
	'file_url'  => '',
);

CTA_Database::$enrollment = (object) array( 'status' => 'active' );
CTA_Database::$course     = $lcsw;
CTA_Database::$completed_quiz_types = array();

assert_true(
	'form_a' === CTA_Course_Materials::infer_protected_rationale_unlock_type( $form_a_rat ),
	'infers form_a gate for Form A rationales'
);
assert_true(
	'form_b' === CTA_Course_Materials::infer_protected_rationale_unlock_type( $form_b_rat ),
	'infers form_b gate for Form B rationales'
);
assert_true(
	CTA_Course_Materials::user_can_access( 9, $open ),
	'non-gated study resource stays open'
);
assert_true(
	! CTA_Course_Materials::user_can_access( 9, $form_a_rat ),
	'Form A rationales locked before submit'
);
assert_true(
	'' !== CTA_Course_Materials::get_unlock_lock_message( 9, $form_a_rat ),
	'Form A lock message shown before submit'
);
assert_true(
	false !== strpos(
		CTA_Course_Materials::get_unlock_lock_message( 9, $form_a_rat ),
		'Complete Form A'
	),
	'Form A lock message mentions Form A'
);

CTA_Database::$completed_quiz_types = array( 'form_a' );
assert_true(
	CTA_Course_Materials::user_can_access( 9, $form_a_rat ),
	'Form A rationales unlock after Form A submit'
);
assert_true(
	! CTA_Course_Materials::user_can_access( 9, $form_b_rat ),
	'Form B rationales stay locked after only Form A submit'
);

CTA_Database::$completed_quiz_types = array( 'form_a', 'form_b' );
assert_true(
	CTA_Course_Materials::user_can_access( 9, $form_b_rat ),
	'Form B rationales unlock after Form B submit'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
