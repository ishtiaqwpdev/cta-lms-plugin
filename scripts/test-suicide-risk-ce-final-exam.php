<?php
/**
 * Validate CTA-CE-003 learner final exam seed (Chunk 4 — no answer keys).
 *
 * Usage: php scripts/test-suicide-risk-ce-final-exam.php
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

require_once $root . '/includes/class-cta-suicide-risk-exam-sync.php';

$questions = CTA_Suicide_Risk_Exam_Sync::get_questions();
assert_true( 25 === count( $questions ), 'Exactly 25 questions in seed file' );

$valid = CTA_Suicide_Risk_Exam_Sync::validate_question_bank( $questions );
assert_true( true === $valid, 'Question bank validation (25 unique CTA-SRA-FE-001..025, no answers)' );

$seed_src = file_get_contents( $root . '/includes/quiz-seeds/suicide-risk-final-exam.php' );
assert_true( ! preg_match( "/'correct_option'\s*=>/", $seed_src ), 'Seed file contains no correct_option values' );
assert_true( ! preg_match( "/'explanation'\s*=>/", $seed_src ), 'Seed file contains no explanation values' );

$crisis = CTA_Suicide_Risk_Exam_Sync::CRISIS_RESOURCE_NOTE;
$copy   = CTA_Suicide_Risk_Exam_Sync::COPYRIGHT_NOTICE;
assert_true( false !== strpos( $crisis, '988 is available by call, text, or chat' ), 'Crisis-resource note verbatim' );
assert_true( false !== strpos( $copy, 'Copyright © 2026 Clinical Training and Supervision Academy' ), 'Copyright notice verbatim' );

$sync_src = file_get_contents( $root . '/includes/class-cta-suicide-risk-exam-sync.php' );
assert_true( false !== strpos( $sync_src, 'passing_score' ) && false !== strpos( $sync_src, '70' ), 'Passing score 70%' );
assert_true( false !== strpos( $sync_src, 'time_limit_mins' ) && false !== strpos( $sync_src, '0' ), 'No time limit' );
assert_true( false !== strpos( $sync_src, 'max_attempts' ) && false !== strpos( $sync_src, '0' ), 'Unlimited attempts (max_attempts=0)' );
assert_true( false !== strpos( $sync_src, 'PENDING_CORRECT_OPTION' ), 'Answer keys deferred to Chunk 5 placeholder' );
assert_true( method_exists( 'CTA_Suicide_Risk_Exam_Sync', 'ensure' ), 'Exam sync exposes ensure() self-heal' );
assert_true( false === strpos( $sync_src, 'unpublish_all_ce_courses_pending_cepa' ), 'Content sync must not mass-unpublish CE courses' );

$lms = file_get_contents( $root . '/cta-lms.php' );
assert_true( false !== strpos( $lms, 'CTA_Suicide_Risk_Exam_Sync' ), 'Exam sync registered in cta-lms.php' );
assert_true( false !== strpos( $lms, 'CTA_Suicide_Risk_Exam_Sync::ensure' ), 'Exam ensure wired in upgrade/player heal' );

$quiz_tpl = file_get_contents( $root . '/templates/quiz.php' );
assert_true( false !== strpos( $quiz_tpl, 'exam_instructions' ), 'Quiz template renders exam instructions' );

$quiz_js = file_get_contents( $root . '/assets/js/main.js' );
assert_true( false === preg_match( '/shuffle.*quiz|quiz.*shuffle|random.*cta-quiz/i', $quiz_js ), 'No quiz question randomization in JS' );

echo "\nExam settings: 25 questions, 70% pass (18/25), unlimited attempts, no time limit, fixed order.\n";
echo "Chunk 5 will apply secured correct_option + explanation values.\n";

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
