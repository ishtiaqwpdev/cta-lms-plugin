<?php
/**
 * Static checks for LPCC NCMHCE legacy Form A/B archive + v2.0 cutover wiring.
 *
 * Usage: php scripts/test-lpcc-ncmhce-legacy-forms-archive.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

require_once $root . '/includes/class-cta-lpcc-ncmhce-legacy-forms-archive.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-a-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-b-sync.php';

$passed = 0;
$failed = 0;

function assert_true( $cond, $label ) {
	global $passed, $failed;
	if ( $cond ) {
		++$passed;
		echo "PASS: {$label}\n";
	} else {
		++$failed;
		echo "FAIL: {$label}\n";
	}
}

$lms = file_get_contents( $root . '/cta-lms.php' );

assert_true(
	class_exists( 'CTA_Lpcc_Ncmhce_Legacy_Forms_Archive' ),
	'Archive class exists'
);
assert_true(
	class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' ) && class_exists( 'CTA_Lpcc_Ncmhce_Form_B_Sync' ),
	'Live Form A/B sync classes exist'
);
assert_true(
	143 === count( CTA_Lpcc_Ncmhce_Form_A_Sync::get_questions() ),
	'Live Form A seed has 143 items'
);
assert_true(
	143 === count( CTA_Lpcc_Ncmhce_Form_B_Sync::get_questions() ),
	'Live Form B seed has 143 items'
);
assert_true(
	false !== strpos( CTA_Lpcc_Ncmhce_Form_A_Sync::get_questions()[0]['question_text'], CTA_Lpcc_Ncmhce_Legacy_Forms_Archive::V2_FORM_A_Q1_NEEDLE ),
	'Form A Q1 matches v2.0 fingerprint (Maya R.)'
);
assert_true(
	false !== strpos( CTA_Lpcc_Ncmhce_Form_B_Sync::get_questions()[0]['question_text'], CTA_Lpcc_Ncmhce_Legacy_Forms_Archive::V2_FORM_B_Q1_NEEDLE ),
	'Form B Q1 matches v2.0 fingerprint (Lena M.)'
);
assert_true(
	225 === CTA_Lpcc_Ncmhce_Form_A_Sync::TIME_LIMIT && 225 === CTA_Lpcc_Ncmhce_Form_B_Sync::TIME_LIMIT,
	'Live forms use 225-minute timer'
);
assert_true(
	20 === CTA_Lpcc_Ncmhce_Form_A_Sync::SORT_ORDER && 30 === CTA_Lpcc_Ncmhce_Form_B_Sync::SORT_ORDER,
	'Live forms occupy Practice Exam sort slots (20 / 30)'
);
assert_true(
	false !== strpos( $lms, 'CTA_Lpcc_Ncmhce_Legacy_Forms_Archive::perform_v2_cutover' ),
	'Upgrade hook runs atomic v2 cutover at 1.0.265'
);
assert_true(
	false !== strpos( $lms, 'class-cta-lpcc-ncmhce-legacy-forms-archive.php' ),
	'Archive class is included from cta-lms.php'
);
assert_true(
	method_exists( 'CTA_Lpcc_Ncmhce_Legacy_Forms_Archive', 'is_learner_accessible_quiz' ),
	'Learner accessibility guard exists for archived quizzes'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
