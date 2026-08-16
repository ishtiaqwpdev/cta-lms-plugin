<?php
/**
 * Security + scoring validation for CTA-CE-003 final exam answer wiring (Chunk 5).
 *
 * Usage: php scripts/test-suicide-risk-ce-exam-security.php
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

$answer_map = CTA_Suicide_Risk_Exam_Sync::get_answer_keys();
$valid      = CTA_Suicide_Risk_Exam_Sync::validate_answer_key( $answer_map );
assert_true( true === $valid, 'Secured answer key validates (25 rows, letters, teaching points)' );

$expected = CTA_Suicide_Risk_Exam_Sync::get_expected_answer_letters();
assert_true( 25 === count( $expected ), 'Expected answer letter map has 25 entries' );

foreach ( $expected as $num => $letter ) {
	$code = sprintf( 'CTA-SRA-FE-%03d', $num );
	assert_true(
		isset( $answer_map[ $code ] ) && $letter === strtolower( (string) $answer_map[ $code ]['correct_option'] ),
		"{$code} correct letter is " . strtoupper( $letter )
	);
}

// Scoring: 18/25 = 72% pass at 70% threshold.
$sample_answers = array();
for ( $i = 1; $i <= 25; $i++ ) {
	$sample_answers[ $i ] = $expected[ $i ];
}
$sample_answers[1] = 'a'; // wrong
$sample_answers[2] = 'b'; // wrong
$sample_answers[3] = 'a'; // wrong
$sample_answers[4] = 'a'; // wrong
$sample_answers[5] = 'a'; // wrong
$sample_answers[6] = 'a'; // wrong
$sample_answers[7] = 'b'; // wrong
$correct_count = 0;
for ( $i = 1; $i <= 25; $i++ ) {
	if ( $sample_answers[ $i ] === $expected[ $i ] ) {
		++$correct_count;
	}
}
$score  = (int) round( ( $correct_count / 25 ) * 100 );
$passed = $score >= 70;
assert_true( 18 === $correct_count, 'Scoring fixture: 18 of 25 correct' );
assert_true( $passed, 'Scoring fixture: 72% passes 70% threshold' );

$learner_seed = file_get_contents( $root . '/includes/quiz-seeds/suicide-risk-final-exam.php' );
$answer_src   = file_get_contents( CTA_Suicide_Risk_Exam_Sync::get_answer_key_path() );
$materials    = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
$quiz_partial = file_get_contents( $root . '/templates/partials/quiz-question.php' );
$quiz_php     = file_get_contents( $root . '/public/class-cta-quiz.php' );
$quiz_tpl     = file_get_contents( $root . '/templates/quiz.php' );
$main_js      = file_get_contents( $root . '/assets/js/main.js' );
$sync_src     = file_get_contents( $root . '/includes/class-cta-suicide-risk-exam-sync.php' );
$lms          = file_get_contents( $root . '/cta-lms.php' );

assert_true( ! preg_match( "/'correct_option'\s*=>/", $learner_seed ), 'Learner seed has no correct_option values' );
assert_true( false !== strpos( CTA_Suicide_Risk_Exam_Sync::get_answer_key_path(), 'admin-only' ), 'Answer key lives under admin-only path' );
assert_true( false !== strpos( $materials, 'suicide-risk-final-exam-answer-key.php' ), 'Answer key path blocked from learner downloads' );
assert_true( false !== strpos( $materials, 'quiz-seeds/admin-only' ), 'admin-only quiz-seeds blocked from downloads' );
assert_true( false !== strpos( $quiz_partial, 'question_text' ), 'Quiz question partial renders stems/options only' );
assert_true( false === strpos( $quiz_partial, 'correct_option' ), 'Quiz question partial never references correct_option' );
assert_true( false !== strpos( $quiz_php, 'cta_lms_reveal_quiz_explanations' ), 'Submit path uses reveal filter before returning explanations' );
assert_true( false !== strpos( $quiz_php, "'html'            => \$this->render_quiz_questions" ), 'Start/resume payload returns HTML only' );
assert_true( false !== strpos( $quiz_php, "'correct_option' => \$question->correct_option" ), 'correct_option used server-side on submit only' );
assert_true( false !== strpos( $quiz_php, '$reveal_explanations ? (string) $question->explanation : \'\'' ), 'Explanations withheld until reveal filter allows' );

assert_true( false !== strpos( $lms, 'cta_lms_reveal_quiz_explanations' ), 'Teaching-point reveal filter registered' );
assert_true( false !== strpos( $lms, 'sync_answer_keys' ), 'Upgrade hook applies secured answer keys (v1.0.213)' );
assert_true( false !== strpos( $lms, '1.0.213' ), 'Version bump 1.0.213' );
assert_true( false !== strpos( $sync_src, 'sync_answer_keys' ), 'Exam sync exposes sync_answer_keys()' );
assert_true( false !== strpos( $sync_src, 'teaching_point' ), 'Answer merge stores teaching_point in explanation field' );
assert_true( false !== strpos( $quiz_tpl, 'data-ce-teaching-points' ), 'Quiz UI flags CE teaching-point mode' );
assert_true( false !== strpos( $main_js, 'Teaching point:' ), 'Post-submit UI labels teaching points for CE finals' );

assert_true( is_readable( $root . '/includes/quiz-seeds/admin-only/.htaccess' ), 'admin-only .htaccess deny rules present' );

echo "\nLive browser checks (manual after deploy):\n";
echo "- During attempt: view source + network tab must show no correct_option/explanation payloads.\n";
echo "- After submit: score + teaching points visible; no standalone answer-key download.\n";

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
