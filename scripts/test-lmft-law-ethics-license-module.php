<?php
/**
 * Verification tests for LMFT Law & Ethics license-specific module content.
 *
 * Run: php scripts/test-lmft-law-ethics-license-module.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-lessons.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-workbooks.php';

$passed = 0;
$failed = 0;

function assert_test( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		++$passed;
		return;
	}
	echo "FAIL: {$label}\n";
	++$failed;
}

$html_path = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/license-module.html';
$seed_path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/lmft-law-ethics-license-25.php';

assert_test( is_readable( $html_path ), 'License module HTML exists' );
assert_test( is_readable( $seed_path ), 'License module quiz seed exists' );

$html = file_get_contents( $html_path );
$seed = include $seed_path;

assert_test( is_array( $seed ) && 25 === count( $seed ), 'Exactly 25 questions in quiz seed' );

$expected_answers = array( 'b', 'd', 'a', 'c', 'b', 'd', 'a', 'c', 'd', 'b', 'c', 'a', 'b', 'd', 'c', 'a', 'b', 'c', 'd', 'a', 'b', 'c', 'd', 'a', 'c' );
foreach ( $expected_answers as $index => $letter ) {
	$got = strtolower( (string) ( $seed[ $index ]['correct_option'] ?? '' ) );
	assert_test( $letter === $got, sprintf( 'Question %d answer key is %s', $index + 1, strtoupper( $letter ) ) );
}

assert_test( false !== stripos( $html, 'PROGRAM BOUNDARY:' ), 'Program boundary notice present verbatim' );
assert_test( 8 === preg_match_all( '/Unit \d+:/', $html ), 'All 8 instructional units present' );

$objectives_block = '';
if ( preg_match( '/Learning Objectives<\/h2>(.*?)<h2/s', $html, $m ) ) {
	$objectives_block = $m[1];
}
assert_test( 12 === substr_count( $objectives_block, '<li class="cta-lesson-li">' ), 'Exactly 12 learning objectives listed' );

assert_test( false !== stripos( $html, 'STATUS → SETTING → SCOPE → SOURCE → SAFEGUARD → STEP → RECORD' ), 'CTA LMFT decision sequence preserved' );
assert_test( false === stripos( $html, 'correct_option' ), 'No answer metadata in learner HTML' );

$sanitized = CTA_Exam_Prep_Lessons::sanitize_lesson_html( $html );
assert_test( false !== stripos( $sanitized, 'Unit 8:' ), 'Sanitized HTML retains Unit 8 content' );

$module = (object) array(
	'title' => 'LMFT Practice Act, AMFT Professional Identity & California Examination Distinctions',
);
assert_test( CTA_Exam_Prep_Workbooks::is_license_module( $module ), 'License module detector matches title' );
assert_test( ! CTA_Exam_Prep_Workbooks::is_start_here_module( $module ), 'License module is not Start Here' );

echo "\nQ1 loaded: " . trim( (string) ( $seed[0]['question_text'] ?? '' ) ) . "\n";
echo "Q25 loaded: " . trim( (string) ( $seed[24]['question_text'] ?? '' ) ) . "\n";

echo "\nLearning Objectives (first 3):\n";
preg_match_all( '/<li class="cta-lesson-li">(.*?)<\/li>/s', $objectives_block, $obj_matches );
for ( $i = 0; $i < min( 3, count( $obj_matches[1] ) ); ++$i ) {
	echo '  ' . ( $i + 1 ) . '. ' . wp_strip_all_tags( html_entity_decode( $obj_matches[1][ $i ] ) ) . "\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );
