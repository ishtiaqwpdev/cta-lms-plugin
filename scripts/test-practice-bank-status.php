<?php
/**
 * Verify Practice Bank status is independent of workbook module completion.
 *
 * Usage: php scripts/test-practice-bank-status.php
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

if ( ! function_exists( '__' ) ) {
	function __( $t ) { return $t; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $t ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $t ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) { return abs( (int) $v ); }
}

require_once $root . '/includes/class-cta-exam-prep-workbooks.php';

assert_true( class_exists( 'CTA_Exam_Prep_Workbooks' ), 'Workbooks class loads' );

$empty_cards = array();
assert_true(
	'not_available' === CTA_Exam_Prep_Workbooks::get_practice_bank_status_from_cards( $empty_cards ),
	'No quiz cards => not_available (Not Started label)'
);
assert_true(
	'Not Started' === CTA_Exam_Prep_Workbooks::get_practice_bank_status_label( 'not_available' ),
	'not_available learner label is Not Started'
);

$quiz = (object) array(
	'id'        => 11,
	'title'     => 'Workbook 1 — 17-Question Practice Bank',
	'quiz_type' => 'wb1_bank',
);

$not_started_card = array(
	'quiz'     => $quiz,
	'attempts' => array(),
	'active'   => null,
	'best'     => null,
	'passed'   => false,
);
assert_true(
	'not_started' === CTA_Exam_Prep_Workbooks::get_practice_bank_status( $not_started_card ),
	'Quiz present, no attempts => not_started'
);

$in_progress_card = array(
	'quiz'     => $quiz,
	'attempts' => array(),
	'active'   => (object) array(
		'id'           => 1,
		'completed_at' => null,
		'answers'      => '{"12":"a"}',
		'score'        => 0,
	),
	'best'     => null,
	'passed'   => false,
);
assert_true(
	'in_progress' === CTA_Exam_Prep_Workbooks::get_practice_bank_status( $in_progress_card ),
	'Active open attempt => in_progress'
);

$completed_card = array(
	'quiz'     => $quiz,
	'attempts' => array(
		(object) array(
			'id'           => 2,
			'completed_at' => '2026-08-17 10:00:00',
			'answers'      => '{"12":"b","13":"a"}',
			'score'        => 80,
			'passed'       => 1,
		),
	),
	'active'   => null,
	'best'     => (object) array(
		'id'           => 2,
		'completed_at' => '2026-08-17 10:00:00',
		'answers'      => '{"12":"b","13":"a"}',
		'score'        => 80,
		'passed'       => 1,
	),
	'passed'   => true,
);
assert_true(
	'completed' === CTA_Exam_Prep_Workbooks::get_practice_bank_status( $completed_card ),
	'Submitted attempt with answers => completed'
);

$ghost_completed = array(
	'quiz'     => $quiz,
	'attempts' => array(
		(object) array(
			'id'           => 3,
			'completed_at' => '2026-08-17 10:00:00',
			'answers'      => '{}',
			'score'        => 0,
			'passed'       => 0,
		),
	),
	'active'   => null,
	'best'     => (object) array(
		'id'           => 3,
		'completed_at' => '2026-08-17 10:00:00',
		'answers'      => '{}',
		'score'        => 0,
		'passed'       => 0,
	),
	'passed'   => false,
);
assert_true(
	'not_started' === CTA_Exam_Prep_Workbooks::get_practice_bank_status( $ghost_completed ),
	'Ghost completed attempt with empty answers => not_started'
);
assert_true(
	CTA_Exam_Prep_Workbooks::attempt_answers_are_empty( '{}' ),
	'Empty JSON object detected as ghost answers'
);
assert_true(
	CTA_Exam_Prep_Workbooks::attempt_answers_are_empty( '' ),
	'Empty string detected as ghost answers'
);
assert_true(
	! CTA_Exam_Prep_Workbooks::attempt_answers_are_empty( '{"1":"a"}' ),
	'Real answers not treated as empty'
);

// Workbook completion button must not say bare "Completed" next to Practice Bank.
$tabbed = file_get_contents( $root . '/templates/partials/exam-prep-workbook-tabbed.php' );
assert_true( false !== strpos( $tabbed, 'Workbook Completed' ), 'Workbook button says Workbook Completed' );
assert_true( false !== strpos( $tabbed, 'data-practice-bank-status' ), 'Practice Bank status attribute present' );
assert_true( false !== strpos( $tabbed, 'Practice Bank progress (separate from workbook completion)' ), 'Explicit separation hint present' );
assert_true(
	false === strpos( $tabbed, "esc_html__( 'Completed', 'cta-lms' )" )
		|| false !== strpos( $tabbed, 'Workbook Completed' ),
	'Bare Completed label removed from workbook actions'
);

$wb_php = file_get_contents( $root . '/templates/exam-prep-workbook.php' );
assert_true( false !== strpos( $wb_php, 'Workbook Completed' ), 'Non-tabbed workbook page also says Workbook Completed' );

$upgrade = file_get_contents( $root . '/cta-lms.php' );
assert_true( false !== strpos( $upgrade, 'reset_ghost_practice_bank_completions' ), 'Upgrade resets ghost bank completions' );
assert_true( false !== strpos( $upgrade, "1.0.259" ), 'Version 1.0.259 stamped' );

// Shared across programs: same helper class used by all exam-prep workbook pages.
assert_true(
	false !== strpos( file_get_contents( $root . '/templates/partials/exam-prep-progress-readiness.php' ), 'status_label' ),
	'Progress/Readiness shows bank status_label (all programs)'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
