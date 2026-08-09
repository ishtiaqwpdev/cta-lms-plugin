<?php
/**
 * Smoke test: CTA-CE-001 final exam seed + sync class.
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );
require_once $root . '/includes/quiz-seeds/law-ethics-final-exam.php';

$questions = include $root . '/includes/quiz-seeds/law-ethics-final-exam.php';
$pass      = 0;
$fail      = 0;

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

assert_true( is_array( $questions ) && 25 === count( $questions ), 'Seed has 25 questions' );

$first = $questions[0] ?? array();
assert_true( ! empty( $first['question_text'] ), 'Q1 stem present' );
assert_true( 'a' === ( $first['correct_option'] ?? '' ), 'Q1 correct answer is A' );
assert_true( ! empty( $first['explanation'] ), 'Q1 detailed rationale present' );

$sync = file_get_contents( $root . '/includes/class-cta-law-ethics-exam-sync.php' );
assert_true( false !== strpos( $sync, 'CTA-CE-001' ), 'Sync scoped to CTA-CE-001' );
assert_true( false !== strpos( $sync, 'Final Examination' ), 'Quiz title is Final Examination' );
assert_true( false !== strpos( $sync, 'passing_score' ) && false !== strpos( $sync, '70' ), 'Passing score 70%' );
assert_true( false !== strpos( $sync, 'max_attempts' ) && false !== strpos( $sync, '0' ), 'Unlimited attempts (0)' );
assert_true( false !== strpos( $sync, 'time_limit_mins' ) && false !== strpos( $sync, '0' ), 'No time limit' );
assert_true( false !== strpos( $sync, 'unpublish_all_ce_courses_pending_cepa' ), 'CEPA draft hold enforced' );

$player = file_get_contents( $root . '/templates/dashboard-ce-player.php' );
assert_true( false !== strpos( $player, 'Final Examination' ), 'Learner player heading uses Final Examination' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
