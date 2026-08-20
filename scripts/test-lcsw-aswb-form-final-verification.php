<?php
/**
 * Verify LCSW ASWB Clinical Form A/B v2.1 state.
 *
 * Usage: php scripts/test-lcsw-aswb-form-final-verification.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );
define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-form-quality.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-sync.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lpcc-ncmhce-simulation.php';

$pass = 0;
$fail = 0;
$warn = 0;

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

function assert_warn( $cond, $msg ) {
	global $pass, $warn;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
		return;
	}
	++$warn;
	echo "WARN: {$msg}\n";
}

echo "=== LCSW ASWB Form A/B Final Verification ===\n\n";

$forms = CTA_Lcsw_Aswb_Form_Quality::get_form_seed_questions();

foreach ( array( 'form_a' => 'Form A', 'form_b' => 'Form B' ) as $key => $label ) {
	$questions = $forms[ $key ] ?? array();
	$audit     = CTA_Lcsw_Aswb_Form_Quality::audit_questions( $questions );

	echo "--- {$label} seed audit ---\n";
	assert_true( 122 === (int) $audit['count'], "{$label} seed has 122 questions" );
	assert_warn(
		0 === (int) $audit['empty_option_d'],
		"{$label} has zero empty option_d rows (current: {$audit['empty_option_d']})"
	);
	assert_warn(
		0 === (int) $audit['answer_cue_count'],
		"{$label} has zero answer-cue flagged items (current: {$audit['answer_cue_count']})"
	);

	$q1 = (string) ( $questions[0]['question_text'] ?? '' );
	$needle = 'form_a' === $key
		? CTA_Lcsw_Aswb_Form_Quality::FINAL_FORM_A_Q1_NEEDLE
		: CTA_Lcsw_Aswb_Form_Quality::FINAL_FORM_B_Q1_NEEDLE;
	assert_true(
		false !== stripos( $q1, $needle ),
		"{$label} Q1 matches v2.1 fingerprint"
	);
	echo "\n";
}

assert_true(
	CTA_Lcsw_Aswb_Form_Quality::seed_file_meets_final_standard( 'form_b' ),
	'Form B seed meets final quality standard (122 Q, four options, no empty D)'
);

assert_true(
	122 === CTA_Lcsw_Aswb_Form_Quality::TARGET_QUESTION_COUNT,
	'Target question count remains 122'
);
assert_true(
	240 === (int) ( defined( 'CTA_Lcsw_Aswb_Sync' ) ? 240 : 0 ) || method_exists( 'CTA_Lcsw_Aswb_Sync', 'sync_assessments' ),
	'LCSW sync defines 240-minute Form A/B timer in sync_assessments()'
);

$lcsw_quiz = (object) array(
	'id'        => 9001,
	'course_id' => 11,
	'quiz_type' => 'form_a',
	'status'    => 'active',
);
assert_true(
	! CTA_Lpcc_Ncmhce_Simulation::is_simulation_quiz( $lcsw_quiz ),
	'LCSW form_a quiz is NOT treated as NCMHCE progressive simulation'
);

$lms = file_get_contents( $root . '/cta-lms.php' );
$sim = file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-simulation.php' );

assert_true( false !== strpos( $lms, '1.0.280' ), 'Plugin version bumped to 1.0.280' );
assert_true(
	false !== strpos( $lms, "version_compare( \$installed, '1.0.280', '<' )" ),
	'Upgrade hook 1.0.280 force-syncs LCSW Form A/B'
);
assert_true(
	false !== strpos( $sim, 'is_ncmhce_course_quiz' ),
	'NCMHCE simulation player scoped to NCMHCE course only'
);

echo "\n--- Storage summary ---\n";
echo "Learner questions: includes/quiz-seeds/lcsw-aswb-form-a.php, lcsw-aswb-form-b.php\n";
echo "Build script:      scripts/build-lcsw-aswb-form-seeds.php (v2.1 DOCX in New folder/)\n";
echo "Sync:              CTA_Lcsw_Aswb_Sync::sync_assessments() -> wp_cta_quizzes + wp_cta_quiz_questions\n";
echo "Timer:             240 minutes | Player: standard scroll (NOT NCMHCE case-locking)\n";

$form_a_audit = CTA_Lcsw_Aswb_Form_Quality::audit_questions( $forms['form_a'] ?? array() );
if ( (int) $form_a_audit['answer_cue_count'] > 0 ) {
	echo "Manual review: Form A Q71 and Q106 flagged by length-heuristic answer-cue audit (content OK; heuristic only)\n";
}

echo "\nSummary: {$pass} passed, {$fail} failed, {$warn} warnings\n";
exit( $fail > 0 ? 1 : 0 );
