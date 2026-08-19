<?php
/**
 * Final verification for LPCC NCMHCE Form A/B simulation configuration (v1.0.277).
 *
 * Usage: php scripts/test-lpcc-ncmhce-simulation-final-verification.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

require_once $root . '/includes/class-cta-lpcc-ncmhce-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-a-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-b-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-legacy-forms-archive.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-simulation.php';

$passed = 0;
$failed = 0;

/**
 * @param bool   $ok Result.
 * @param string $label Label.
 */
function assert_true( $ok, $label ) {
	global $passed, $failed;
	if ( $ok ) {
		++$passed;
		echo "  PASS  {$label}\n";
	} else {
		++$failed;
		echo "  FAIL  {$label}\n";
	}
}

echo "=== LPCC NCMHCE Simulation — Final Verification ===\n\n";

$lms      = file_get_contents( $root . '/cta-lms.php' );
$quiz_php = file_get_contents( $root . '/public/class-cta-quiz.php' );
$quiz_tpl = file_get_contents( $root . '/templates/quiz.php' );
$main_js  = file_get_contents( $root . '/assets/js/main.js' );
$layout   = file_get_contents( $root . '/assets/css/layout.css' );
$sync     = file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-sync.php' );

echo "--- 1) Version + upgrade hook ---\n";
assert_true( false !== strpos( $lms, "define( 'CTA_VERSION', '1.0.277' )" ), 'Plugin version 1.0.277' );
assert_true( false !== strpos( $lms, '1.0.277' ) && false !== strpos( $lms, 'CTA_Lpcc_Ncmhce_Simulation::sync_simulation_time_limits' ), 'Upgrade hook heals NCMHCE timers' );
assert_true( false !== strpos( $lms, 'class-cta-lpcc-ncmhce-simulation.php' ), 'Simulation class required in bootstrap' );

echo "\n--- 2) Timer configuration (225 minutes) ---\n";
assert_true( 225 === (int) CTA_Lpcc_Ncmhce_Form_A_Sync::TIME_LIMIT, 'Form A sync TIME_LIMIT = 225' );
assert_true( 225 === (int) CTA_Lpcc_Ncmhce_Form_B_Sync::TIME_LIMIT, 'Form B sync TIME_LIMIT = 225' );
assert_true( 225 === (int) CTA_Lpcc_Ncmhce_Simulation::TIME_LIMIT_MINS, 'Simulation TIME_LIMIT_MINS = 225' );
assert_true( false !== strpos( $sync, "'time'      => 225" ), 'Legacy LPCC sync form defs use 225' );
assert_true( false !== strpos( $sync, '$time_limit = 225' ), 'Legacy replace_form_quiz default is 225' );

echo "\n--- 3) Question counts unchanged (143 each) ---\n";
assert_true( 143 === count( CTA_Lpcc_Ncmhce_Form_A_Sync::get_questions() ), 'Form A seed has 143 items' );
assert_true( 143 === count( CTA_Lpcc_Ncmhce_Form_B_Sync::get_questions() ), 'Form B seed has 143 items' );
assert_true( 143 === (int) CTA_Lpcc_Ncmhce_Form_A_Sync::TARGET_COUNT, 'Form A TARGET_COUNT = 143' );
assert_true( 143 === (int) CTA_Lpcc_Ncmhce_Form_B_Sync::TARGET_COUNT, 'Form B TARGET_COUNT = 143' );

echo "\n--- 4) Progressive case/section blueprint ---\n";
$form_a_sections = CTA_Lpcc_Ncmhce_Simulation::get_section_blueprint( 'form_a' );
$form_b_sections = CTA_Lpcc_Ncmhce_Simulation::get_section_blueprint( 'form_b' );
assert_true( 33 === count( $form_a_sections ), 'Form A blueprint has 33 sections (11 cases × 3)' );
assert_true( 33 === count( $form_b_sections ), 'Form B blueprint has 33 sections (11 cases × 3)' );

$form_a_q = 0;
foreach ( $form_a_sections as $section ) {
	$form_a_q += count( $section['question_indices'] );
}
$form_b_q = 0;
foreach ( $form_b_sections as $section ) {
	$form_b_q += count( $section['question_indices'] );
}
assert_true( 143 === $form_a_q, 'Form A section map covers 143 questions' );
assert_true( 143 === $form_b_q, 'Form B section map covers 143 questions' );
assert_true(
	14 === CTA_Lpcc_Ncmhce_Simulation::break_after_section_index(),
	'Scheduled break follows Case 5 section 3 (index 14)'
);
assert_true( 15 === (int) CTA_Lpcc_Ncmhce_Simulation::BREAK_MINUTES, 'Break duration is 15 minutes' );

echo "\n--- 5) Player wiring (progressive, locking, breaks) ---\n";
assert_true( false !== strpos( $quiz_tpl, 'data-ncmhce-simulation' ), 'Quiz template exposes NCMHCE simulation mode' );
assert_true( false !== strpos( $quiz_tpl, 'cta-ncmhce-continue' ), 'Continue button for section advance' );
assert_true( false !== strpos( $quiz_php, 'CTA_Lpcc_Ncmhce_Simulation::render_questions' ), 'Quiz renderer delegates to simulation player' );
assert_true( false !== strpos( $quiz_php, 'prepare_attempt_answers_for_storage' ), 'Save/submit merge NCMHCE meta + locking' );
assert_true( false !== strpos( $quiz_php, 'adjust_seconds_remaining' ), 'Exam timer pauses during break' );
assert_true( false !== strpos( $main_js, 'initNcmhceSimulationUi' ), 'JS initializes progressive section UI' );
assert_true( false !== strpos( $main_js, 'ncmhceExamTimerPaused' ), 'JS pauses item timer during break' );
assert_true( false !== strpos( $main_js, 'handleNcmhceContinue' ), 'JS one-way section advance' );
assert_true( false !== strpos( $main_js, 'showNcmhceBreak' ), 'JS scheduled break screen' );
assert_true( false !== strpos( $layout, 'cta-ncmhce-section' ), 'CSS for section-based presentation' );

echo "\n--- 6) Other programs unaffected ---\n";
assert_true( false !== strpos( $quiz_tpl, '$is_ncmhce_simulation' ), 'NCMHCE simulation flag is conditional in template' );
assert_true( false !== strpos( $main_js, 'if (isNcmhceSimulation)' ), 'NCMHCE behavior gated behind simulation flag' );

echo "\n=== Summary: {$passed} passed, {$failed} failed ===\n";
exit( $failed > 0 ? 1 : 0 );
