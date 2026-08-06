<?php
/**
 * Verify AMFTRB public checkout HOLD layers (offline).
 *
 * Run: C:\xampp\php\php.exe scripts/test-amftrb-checkout-hold.php
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

require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-access.php';

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

// Catalog entry for AMFTRB must be draft + launch pending.
$amftrb = null;
foreach ( CTA_Exam_Access::get_default_programs() as $p ) {
	if ( 'lmft-amftrb-national-exam-preparation' === ( $p['slug'] ?? '' ) ) {
		$amftrb = $p;
		break;
	}
}
assert_true( is_array( $amftrb ), 'AMFTRB program present in exam-access catalog' );
assert_true( ! empty( $amftrb['launch_pending_testing'] ), 'AMFTRB launch_pending_testing = true' );
assert_true( 'draft' === ( $amftrb['status'] ?? '' ), 'AMFTRB catalog status = draft' );
assert_true( empty( $amftrb['commercial_pending'] ), 'AMFTRB commercial terms confirmed ($329) — hold is launch/testing only' );

$course = (object) array(
	'id'           => 99,
	'slug'         => 'lmft-amftrb-national-exam-preparation',
	'product_type' => 'exam_prep',
	'status'       => 'draft',
	'syllabus_meta'=> wp_json_encode(
		array(
			'launch_pending_testing' => true,
			'launch_status'          => 'draft_pending_testing',
		)
	),
);
assert_true( CTA_Exam_Access::is_exam_prep( $course ), 'AMFTRB recognized as exam_prep' );
assert_true( CTA_Exam_Access::launch_pending_testing( $course ), 'launch_pending_testing() true from syllabus_meta' );

$published_but_held = (object) array(
	'id'            => 100,
	'slug'          => 'lmft-amftrb-national-exam-preparation',
	'product_type'  => 'exam_prep',
	'status'        => 'published',
	'syllabus_meta' => wp_json_encode( array( 'launch_pending_testing' => true ) ),
);
assert_true( CTA_Exam_Access::launch_pending_testing( $published_but_held ), 'HOLD still true if status wrongly published with pending meta' );

$released = (object) array(
	'id'            => 101,
	'slug'          => 'lmft-amftrb-national-exam-preparation',
	'product_type'  => 'exam_prep',
	'status'        => 'published',
	'syllabus_meta' => wp_json_encode( array() ),
);
assert_true( ! CTA_Exam_Access::launch_pending_testing( $released ), 'HOLD clears only when launch_pending meta removed' );

// Sync class constants / syllabus meta.
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-sync.php';
$ref  = new ReflectionClass( 'CTA_Lmft_Amftrb_Sync' );
$meta = $ref->getMethod( 'get_syllabus_meta' );
$meta->setAccessible( true );
$sm = $meta->invoke( null );
assert_true( ! empty( $sm['launch_pending_testing'] ), 'AMFTRB sync syllabus_meta keeps launch_pending_testing' );
assert_true( 'draft_pending_testing' === ( $sm['launch_status'] ?? '' ), 'AMFTRB sync launch_status = draft_pending_testing' );

// Source guards: Stripe checkout rejects launch_pending.
$stripe = file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-stripe.php' );
assert_true( false !== strpos( $stripe, 'exam_prep_launch_pending' ), 'Stripe returns exam_prep_launch_pending error code' );
assert_true( false !== strpos( $stripe, 'launch_pending_testing' ), 'Stripe checks launch_pending_testing before checkout' );
assert_true( false !== strpos( $stripe, "status = 'published'" ), 'Stripe checkout requires published status' );

// Public catalog filters HOLD programs.
$courses_php = file_get_contents( CTA_PLUGIN_DIR . 'public/class-cta-courses.php' );
assert_true( false !== strpos( $courses_php, 'launch_pending_testing( $course )' ), 'Public catalog skips launch-pending exam prep' );
assert_true( false !== strpos( $courses_php, 'not available for purchase yet' ), 'Public single-course blocks launch-pending for non-admins' );

// UI: enroll button held.
$tpl = file_get_contents( CTA_PLUGIN_DIR . 'templates/single-course.php' );
assert_true( false !== strpos( $tpl, 'launch_pending' ), 'Single-course template has launch_pending hold UI' );
assert_true( false !== strpos( $tpl, 'final written release approval' ), 'Hold notice mentions written release approval' );

// Sync ensure_program forces draft.
$sync = file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-sync.php' );
assert_true( false !== strpos( $sync, "'status'               => 'draft'" ), 'AMFTRB ensure_program forces draft status' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );

/**
 * Minimal json_encode alias if WP missing.
 *
 * @param mixed $data Data.
 * @return string
 */
function wp_json_encode( $data ) {
	return json_encode( $data );
}
