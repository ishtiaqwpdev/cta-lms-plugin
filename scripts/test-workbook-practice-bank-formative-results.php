<?php
/**
 * Verify workbook Practice Banks do not use a pass/fail 70% threshold in UI.
 *
 * Usage: php scripts/test-workbook-practice-bank-formative-results.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$fail = 0;
$pass = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
function assert_true( $cond, $msg ) {
	global $fail, $pass;
	if ( $cond ) {
		echo "PASS: {$msg}\n";
		++$pass;
	} else {
		echo "FAIL: {$msg}\n";
		++$fail;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $t Text.
	 * @return string
	 */
	function __( $t ) {
		return $t;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $t Text.
	 * @return string
	 */
	function sanitize_key( $t ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $t ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $v Value.
	 * @return int
	 */
	function absint( $v ) {
		return abs( (int) $v );
	}
}

require_once $root . '/includes/class-cta-exam-prep-workbooks.php';

$wb1 = (object) array(
	'title'     => 'Workbook 1 — 17-Question Practice Bank',
	'quiz_type' => 'wb1_bank',
);
$wb2 = (object) array(
	'title'     => 'Workbook 2 — 17-Question Practice Bank',
	'quiz_type' => 'wb2_bank',
);
$form_a = (object) array(
	'title'     => 'Form A Comprehensive Simulation',
	'quiz_type' => 'form_a',
);
$form_b = (object) array(
	'title'     => 'Form B Comprehensive Simulation',
	'quiz_type' => 'form_b',
);
$checkpoint = (object) array(
	'title'     => 'Checkpoint 1 — Cumulative Assessment',
	'quiz_type' => 'checkpoint_1',
);

assert_true( CTA_Exam_Prep_Workbooks::is_formative_practice_bank( $wb1 ), 'WB1 bank is formative' );
assert_true( CTA_Exam_Prep_Workbooks::is_formative_practice_bank( $wb2 ), 'WB2 bank is formative' );
assert_true( ! CTA_Exam_Prep_Workbooks::is_formative_practice_bank( $form_a ), 'Form A is not formative' );
assert_true( ! CTA_Exam_Prep_Workbooks::is_formative_practice_bank( $form_b ), 'Form B is not formative' );
assert_true( ! CTA_Exam_Prep_Workbooks::is_formative_practice_bank( $checkpoint ), 'Checkpoint is not formative' );

$guidance = CTA_Exam_Prep_Workbooks::formative_practice_bank_guidance();
assert_true( false !== stripos( $guidance, 'learning resource' ), 'Guidance calls it a learning resource' );
assert_true( false !== stripos( $guidance, 'not a pass/fail' ), 'Guidance denies pass/fail' );
assert_true( false !== stripos( $guidance, 'rationales' ), 'Guidance points to rationales' );
assert_true( false === stripos( $guidance, '70' ), 'Guidance does not mention 70%' );

$js = file_get_contents( $root . '/assets/js/main.js' );
assert_true( false !== strpos( $js, 'renderFormativeBankResult' ), 'JS has formative results renderer' );
assert_true( false !== strpos( $js, 'Passing: " +' ) || false !== strpos( $js, '% (Passing: "' ), 'Form A/B still have Passing: label in shared fail path' );
assert_true( false !== strpos( $js, 'isFormativeBank || (data && data.formative)' ), 'Formative path branches before pass/fail UI' );

$tpl = file_get_contents( $root . '/templates/quiz.php' );
assert_true( false !== strpos( $tpl, 'data-formative-bank' ), 'Quiz template marks formative banks' );
assert_true( false !== strpos( $tpl, 'Learning resource (no pass/fail threshold)' ), 'Start panel hides 70% for formative banks' );
assert_true( false !== strpos( $tpl, 'Passing Score' ), 'Start panel still has Passing Score for gated exams' );

$quiz_src = file_get_contents( $root . '/public/class-cta-quiz.php' );
assert_true( false !== strpos( $quiz_src, "'formative'" ) || false !== strpos( $quiz_src, "'formative'      => true" ), 'Submit payload flags formative banks' );
assert_true( false !== strpos( $quiz_src, 'Congratulations! You passed with %d%%' ), 'Pass message remains for gated exams' );
assert_true( false !== strpos( $quiz_src, 'Passing score is %2$d%%' ), 'Fail message remains for gated exams' );

$lpcc = file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-sync.php' );
assert_true( false !== strpos( $lpcc, "'passing_score'   => 70" ), 'LPCC Form/checkpoint sync still stores 70%' );

$plugin = file_get_contents( $root . '/cta-plugin.php' );
assert_true( false !== strpos( $plugin, '1.0.262' ), 'Plugin version bumped to 1.0.262' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
