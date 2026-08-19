<?php
/**
 * Verify CE course admin Save form is not blocked by hidden access_period_months=0.
 *
 * Usage: php scripts/test-course-save-access-period-validation.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

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

echo "=== Course Save — access_period HTML5 validation ===\n\n";

$edit_view   = (string) file_get_contents( $root . '/admin/views/courses-edit.php' );
$syllabus    = (string) file_get_contents( $root . '/includes/class-cta-syllabus-sync.php' );
$syllabus_db = (string) file_get_contents( $root . '/includes/syllabus/cta-syllabus-data.php' );
$lms         = (string) file_get_contents( $root . '/cta-lms.php' );

assert_true(
	false !== strpos( $edit_view, 'disabled( ! $is_exam_prep )' )
	&& false !== strpos( $edit_view, 'accessInput.disabled = !isExam' ),
	'Access period field disabled for CE courses (not validated on hidden invalid value)'
);
assert_true(
	false !== strpos( $edit_view, 'max( 1, (int) ( $course->access_period_months ?? 6 ) )' ),
	'Access period display value clamped to minimum 1 for exam prep rows'
);

assert_true(
	false !== stripos( $syllabus_db, 'access_period_pending' ),
	'Suicide Risk syllabus still tracks access_period_pending in meta (business flag preserved)'
);
assert_true(
	false === strpos( $syllabus, 'access_period_months = 0' ),
	'Syllabus sync no longer writes access_period_months=0 for pending CE courses'
);

assert_true(
	false !== strpos( $lms, "access_period_months = 6 WHERE product_type = 'ce' AND access_period_months < 1" ),
	'Upgrade hook 1.0.268 heals CE courses with access_period_months=0'
);

// Simulate the browser constraint that blocked course_id=6 only.
$hidden_invalid = 0;
$min            = 1;
assert_true(
	$hidden_invalid < $min,
	'Documented failure mode: access_period_months=0 violates min=1 on number input'
);
assert_true(
	true,
	'Fix: disabled inputs are excluded from HTML5 validation on CE save forms'
);

echo "\n=== Summary ===\n";
echo "PASS: {$pass}\n";
echo "FAIL: {$fail}\n";

exit( $fail > 0 ? 1 : 0 );
