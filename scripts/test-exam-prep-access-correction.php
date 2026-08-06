<?php
/**
 * Verify Access Correction Notice: Exam Prep open from enrollment; CE gates remain; admin deny intact.
 *
 * Run: C:\xampp\php\php.exe scripts/test-exam-prep-access-correction.php
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
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d ) {
		return json_encode( $d );
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
		unset( $course );
		return false;
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

require_once CTA_PLUGIN_DIR . 'includes/class-cta-course-materials.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-sync.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lpcc-ncmhce-sync.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-sync.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-clinical-sync.php';

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

$amftrb = (object) array( 'id' => 1, 'product_type' => 'exam_prep', 'slug' => 'lmft-amftrb-national-exam-preparation' );
$lpcc   = (object) array( 'id' => 2, 'product_type' => 'exam_prep', 'slug' => 'lpcc-ncmhce-exam-preparation' );
$lcsw   = (object) array( 'id' => 4, 'product_type' => 'exam_prep', 'slug' => 'lcsw-aswb-clinical-exam-preparation' );
$lmft   = (object) array( 'id' => 5, 'product_type' => 'exam_prep', 'slug' => 'lmft-california-clinical-exam-preparation' );
$ce     = (object) array( 'id' => 3, 'product_type' => 'ce', 'slug' => 'some-ce' );

assert_true( ! CTA_Exam_Access::uses_assessment_gates( $amftrb ), 'AMFTRB uses_assessment_gates = false' );
assert_true( ! CTA_Exam_Access::uses_assessment_gates( $lpcc ), 'LPCC uses_assessment_gates = false' );
assert_true( ! CTA_Exam_Access::uses_assessment_gates( $lcsw ), 'LCSW uses_assessment_gates = false' );
assert_true( ! CTA_Exam_Access::uses_assessment_gates( $lmft ), 'LMFT Clinical uses_assessment_gates = false' );

CTA_Database::$enrollment = (object) array( 'status' => 'active' );

foreach ( array( $amftrb, $lpcc, $lcsw, $lmft ) as $course ) {
	CTA_Database::$course = $course;
	foreach ( array( 'form_a', 'form_b', 'form_b_ready', 'wb1_bank', 'checkpoint_1', 'modules_complete', 'form_a_remediation' ) as $gate ) {
		$res = (object) array(
			'id'                     => 1,
			'course_id'              => (int) $course->id,
			'title'                  => 'Rationale ' . $gate,
			'file_path'              => 'rationales/sample.docx',
			'file_url'               => '',
			'unlock_after_quiz_type' => $gate,
		);
		assert_true(
			CTA_Course_Materials::user_can_access( 9, $res ),
			$course->slug . " open despite legacy gate={$gate}"
		);
		assert_true(
			'' === CTA_Course_Materials::get_unlock_lock_message( 9, $res ),
			$course->slug . " no lock message for gate={$gate}"
		);
	}
}

assert_true(
	CTA_Course_Materials::user_meets_unlock_gate( 9, 1, 'form_b_ready' ),
	'form_b_ready never requires Form A remediation'
);

CTA_Database::$course = $ce;
$ce_res = (object) array(
	'id'                     => 5,
	'course_id'              => 3,
	'title'                  => 'CE handout',
	'file_path'              => 'materials/ce.pdf',
	'file_url'               => '',
	'unlock_after_quiz_type' => 'modules_complete',
);
assert_true( ! CTA_Course_Materials::user_can_access( 9, $ce_res ), 'CE still gated by modules_complete' );

assert_true(
	CTA_Course_Materials::is_admin_restricted_source_path( '90_Admin_Restricted/blueprints.docx' ),
	'Admin/internal paths still denied'
);
assert_true(
	CTA_Course_Materials::is_admin_restricted_source_path( '03_INTERNAL_CONTROLS/x.docx' ),
	'Internal controls still denied'
);

// Material maps: no unlock keys remain on AMFTRB / LCSW / LMFT / LPCC maps.
$maps = array(
	'CTA_Lmft_Amftrb_Sync'   => 'get_material_map',
	'CTA_Lpcc_Ncmhce_Sync'   => 'get_material_map',
	'CTA_Lcsw_Aswb_Sync'     => 'get_material_map',
	'CTA_Lmft_Clinical_Sync' => 'get_material_map',
);
foreach ( $maps as $class => $method ) {
	$ref = new ReflectionClass( $class );
	$m   = $ref->getMethod( $method );
	$m->setAccessible( true );
	$items = $m->invoke( null );
	$bad   = 0;
	foreach ( $items as $it ) {
		if ( ! empty( $it['unlock_after_quiz_type'] ) ) {
			++$bad;
		}
	}
	assert_true( 0 === $bad, "{$class} material map has zero unlock gates" );
}

$tpl = file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/course-materials.php' );
assert_true( false !== strpos( $tpl, 'cta-materials-advisory' ), 'Advisory Form A→B banner present' );
assert_true( false !== strpos( $tpl, 'guidance only' ), 'Advisory text says guidance only' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
