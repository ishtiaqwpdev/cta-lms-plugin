<?php
/**
 * Validate LMFT California Clinical legacy Form A/B archive wiring (PROMPT 00).
 *
 * Usage: php scripts/test-lmft-clinical-legacy-forms-archive.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

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

require_once $root . '/includes/class-cta-lmft-clinical-legacy-forms-archive.php';

assert_true(
	10 === CTA_Lmft_Clinical_Legacy_Forms_Archive::TARGET_COURSE_ID,
	'Archive targets course_id=10'
);

assert_true(
	array( 'form_a', 'form_b' ) === CTA_Lmft_Clinical_Legacy_Forms_Archive::legacy_quiz_types(),
	'Legacy quiz types are form_a and form_b'
);

assert_true(
	4 === count( CTA_Lmft_Clinical_Legacy_Forms_Archive::legacy_resource_path_markers() ),
	'Four legacy simulation resources defined (Form A/B exams + rationales)'
);

assert_true(
	CTA_Lmft_Clinical_Legacy_Forms_Archive::title_is_archived( '[Archived] Form A — 150-Question Comprehensive Simulation' ),
	'Archived title prefix detected'
);

$mock_resource = (object) array(
	'title'     => '[Archived] Form A — 150-Question Comprehensive Simulation',
	'file_path' => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_A_150_Question_Exam_v1.0.docx',
);
assert_true(
	CTA_Lmft_Clinical_Legacy_Forms_Archive::is_archived_resource( $mock_resource ),
	'Archived resource flagged by title prefix'
);

$mock_legacy = (object) array(
	'title'     => 'Form B — 150-Question Comprehensive Simulation',
	'file_path' => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_B_150_Question_Exam_v1.0.docx',
);
assert_true(
	CTA_Lmft_Clinical_Legacy_Forms_Archive::matches_legacy_form_resource( $mock_legacy ),
	'Legacy Form B simulation matched before archive'
);

$lms          = file_get_contents( $root . '/cta-lms.php' );
$materials    = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
$lmft_sync    = file_get_contents( $root . '/includes/class-cta-lmft-clinical-sync.php' );
$database     = file_get_contents( $root . '/includes/class-cta-database.php' );
$quiz_class   = file_get_contents( $root . '/public/class-cta-quiz.php' );

assert_true( false !== strpos( $lms, '1.0.220' ), 'Version bump 1.0.220' );
assert_true(
	false !== strpos( $lms, 'CTA_Lmft_Clinical_Legacy_Forms_Archive::archive_legacy_forms' ),
	'Upgrade hook archives legacy Form A/B on deploy'
);
assert_true(
	false !== strpos( $lms, 'class-cta-lmft-clinical-legacy-forms-archive.php' ),
	'Archive class loaded in bootstrap'
);
assert_true(
	false !== strpos( $materials, 'is_archived_resource' ),
	'Course materials block archived downloads'
);
assert_true(
	false !== strpos( $lmft_sync, 'legacy_forms_archived' ),
	'LMFT sync skips re-seeding archived Form A/B'
);
assert_true(
	false !== strpos( $database, "status = 'active'" ),
	'Learner quiz queries filter to active status only'
);
assert_true(
	false !== strpos( $quiz_class, 'get_quiz_for_course' ),
	'Quiz page resolves assessments through active-only lookup'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
