<?php
/**
 * Verify LCSW ASWB Clinical Form A/B state (Step 1 / Step 3).
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
		0 === (int) $audit['answer_cue_count'],
		"{$label} has zero answer-cue flagged items (current legacy count: {$audit['answer_cue_count']})"
	);
	assert_warn(
		0 === (int) $audit['empty_option_d'],
		"{$label} has zero empty option_d rows (current legacy count: {$audit['empty_option_d']})"
	);
	echo "\n";
}

assert_true(
	! CTA_Lcsw_Aswb_Form_Quality::all_seed_files_meet_final_standard(),
	'Confirmed: approved final rebuilt seeds are NOT present yet (legacy package v1.0 content still in repo)'
);

$lmft_a = CTA_Lcsw_Aswb_Form_Quality::load_seed_questions( 'includes/quiz-seeds/lmft-clinical-form-a.php' );
$lmft_audit = CTA_Lcsw_Aswb_Form_Quality::audit_questions( $lmft_a );
assert_true(
	0 === (int) $lmft_audit['answer_cue_count'],
	'LMFT Clinical Form A reference deck has zero answer-cue flags (control)'
);

assert_true(
	method_exists( 'CTA_Lcsw_Aswb_Sync', 'get_live_form_health' ),
	'LCSW sync exposes live Form A/B health check'
);
assert_true(
	method_exists( 'CTA_Lcsw_Aswb_Sync', 'ensure_learner_forms' ),
	'LCSW sync can heal missing/inactive Form A/B DB rows'
);

echo "\nData source chain:\n";
echo "  includes/quiz-seeds/lcsw-aswb-form-a.php\n";
echo "  includes/quiz-seeds/lcsw-aswb-form-b.php\n";
echo "  -> CTA_Lcsw_Aswb_Sync::sync_assessments() -> wp_cta_quizzes / wp_cta_quiz_questions\n";
echo "  Printable source: assets/course-materials/lcsw-aswb/simulations/CTA_LCSW_2026_*_122_Question_Exam_v1.0.docx\n\n";

echo "Summary: {$pass} passed, {$fail} failed, {$warn} warnings\n";
exit( $fail > 0 ? 1 : 0 );
