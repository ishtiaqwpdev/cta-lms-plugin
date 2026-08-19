<?php
/**
 * Verify August 14 Final Form A/B replacement wiring (PROMPT / Implementation Guide v1.1).
 *
 * Usage: php scripts/test-lmft-clinical-final-forms-replacement.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', $root . '/' );
}

$pass = 0;
$fail = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
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

require_once $root . '/includes/class-cta-lmft-clinical-legacy-forms-archive.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-comprehensive-scoring.php';

echo "=== LMFT Clinical Final Form A/B Replacement Verification ===\n\n";

$form_a_path = $root . '/assets/course-materials/lmft-clinical/simulations/CTA_LMFT_CA_Clinical_Exam_Prep_Final_Form_A_v1.0.docx';
$form_b_path = $root . '/assets/course-materials/lmft-clinical/simulations/CTA_LMFT_CA_Clinical_Exam_Prep_Final_Form_B_v1.0.docx';
$admin_path  = $root . '/assets/course-materials/lmft-clinical/admin-only/CTA_LMFT_CA_Clinical_Exam_Prep_Final_Admin_Key_Rationales_v1.0.docx';
$july_a      = $root . '/assets/course-materials/lmft-clinical/simulations/_archived/CTA_LMFT_Comprehensive_Simulation_Form_A_150_Question_Exam_v1.0.docx';
$july_live   = $root . '/assets/course-materials/lmft-clinical/simulations/CTA_LMFT_Comprehensive_Simulation_Form_A_150_Question_Exam_v1.0.docx';

assert_true( is_readable( $form_a_path ), 'Final Form A DOCX installed in simulations/' );
assert_true( is_readable( $form_b_path ), 'Final Form B DOCX installed in simulations/' );
assert_true( is_readable( $admin_path ), 'Final Admin Key stored under admin-only/' );
assert_true( is_readable( $july_a ), 'July Form A archived under simulations/_archived/' );
assert_true( ! file_exists( $july_live ), 'July Form A removed from live simulations/ path' );

$fa = CTA_Lmft_Clinical_Form_A_Sync::get_questions();
$fb = CTA_Lmft_Clinical_Form_B_Sync::get_questions();

assert_true( 150 === count( $fa ), 'Form A seed has 150 questions' );
assert_true( 150 === count( $fb ), 'Form B seed has 150 questions' );
assert_true( 150 === CTA_Lmft_Clinical_Form_A_Sync::count_imported_items( $fa ), 'Form A has no import placeholders' );
assert_true( 150 === CTA_Lmft_Clinical_Form_B_Sync::count_imported_items( $fb ), 'Form B has no import placeholders' );
assert_true( 240 === CTA_Lmft_Clinical_Form_A_Sync::TIME_LIMIT_MINS, 'Form A timer 240 minutes' );
assert_true( 240 === CTA_Lmft_Clinical_Form_B_Sync::TIME_LIMIT_MINS, 'Form B timer 240 minutes' );

$q1a = (string) ( $fa[0]['question_text'] ?? '' );
$q1b = (string) ( $fb[0]['question_text'] ?? '' );

echo "\n--- Learner-facing Question 1 (from active seed banks) ---\n";
echo 'Form A Q1: ' . $q1a . "\n\n";
echo 'Form B Q1: ' . $q1b . "\n\n";

assert_true(
	false !== stripos( $q1a, CTA_Lmft_Clinical_Legacy_Forms_Archive::FINAL_FORM_A_Q1_NEEDLE ),
	'Form A Q1 matches August 14 Final fingerprint'
);
assert_true(
	false === stripos( $q1a, CTA_Lmft_Clinical_Legacy_Forms_Archive::LEGACY_JULY_FORM_A_Q1_NEEDLE ),
	'Form A Q1 is NOT the July partner-violence item'
);
assert_true(
	false !== stripos( $q1b, CTA_Lmft_Clinical_Legacy_Forms_Archive::FINAL_FORM_B_Q1_NEEDLE ),
	'Form B Q1 matches August 14 Final fingerprint'
);

assert_true(
	125 === CTA_Lmft_Clinical_Comprehensive_Scoring::SCORED_ITEM_COUNT,
	'Scoring denominator is 125 core items'
);
assert_true(
	150 === CTA_Lmft_Clinical_Comprehensive_Scoring::TOTAL_ITEM_COUNT,
	'Total presented items remain 150'
);

$lms = file_get_contents( $root . '/cta-lms.php' );
assert_true(
	false !== strpos( $lms, "version_compare( \$installed, '1.0.256', '<' )" ),
	'Upgrade hook 1.0.256 forces Final Form replacement'
);
assert_true(
	false !== strpos( $lms, 'archive_non_final_active_forms' ),
	'Upgrade archives non-final active Form A/B by Q1 fingerprint'
);

$sync = file_get_contents( $root . '/includes/class-cta-lmft-clinical-sync.php' );
assert_true(
	false !== strpos( $sync, 'CTA_LMFT_CA_Clinical_Exam_Prep_Final_Form_A_v1.0.docx' ),
	'Material map points at Final Form A printable'
);
assert_true(
	false === strpos( $sync, 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_A_150_Question_Exam_v1.0.docx' ),
	'Material map no longer registers live July Form A printable'
);

assert_true(
	false !== strpos( $lms, 'ensure_learner_final_forms' ),
	'Upgrade can heal missing Final Form A via ensure_learner_final_forms'
);
assert_true(
	method_exists( 'CTA_Lmft_Clinical_Form_A_Sync', 'get_live_quiz_health' ),
	'Form A sync verifies live quiz health before skipping re-seed'
);
assert_true(
	method_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive', 'get_active_final_form_quiz' ),
	'Legacy archive exposes active Final form lookup for Exam Center'
);

echo "\n=== Summary ===\nPASS: {$pass}\nFAIL: {$fail}\n";
exit( $fail > 0 ? 1 : 0 );
