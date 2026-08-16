<?php
/**
 * Smoke tests for LMFT Law & Ethics dashboard scaffold.
 *
 * Run: php scripts/test-lmft-law-ethics-scaffold.php
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

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function sanitize_key( $key ) {
	return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', strtolower( (string) $key ) ), '-' );
}

function wp_json_encode( $data, $options = 0 ) {
	unset( $options );
	return json_encode( $data );
}

function wp_mkdir_p( $target ) {
	return is_dir( $target ) || mkdir( $target, 0777, true );
}

function get_option( $key ) {
	unset( $key );
	return false;
}

function update_option( $key, $value ) {
	unset( $key, $value );
	return true;
}

function get_transient( $key ) {
	unset( $key );
	return false;
}

function set_transient( $key, $value, $expiration ) {
	unset( $key, $value, $expiration );
	return true;
}

function current_time( $type ) {
	unset( $type );
	return '2026-08-15 00:00:00';
}

function absint( $value ) {
	return abs( (int) $value );
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

function wp_kses_post( $html ) {
	return $html;
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-law-ethics-copy.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-law-ethics-sync.php';

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

$defs = new ReflectionMethod( 'CTA_Lmft_Law_Ethics_Sync', 'get_module_definitions' );
$defs->setAccessible( true );
$modules = $defs->invoke( null );
assert_test( 16 === count( $modules ), 'Sixteen modules defined (Start Here + Practice Act + 9 workbooks + Practice A/B + Final + Study Center + Program Close)' );
assert_test( false !== stripos( $modules[1]['title'], 'Practice Act' ), 'License module title is set' );
assert_test( false !== stripos( $modules[2]['title'], 'Workbook 1' ), 'Workbook 1 follows license module' );
assert_test( 'practice_a' === ( $modules[11]['kind'] ?? '' ), 'Practice Examination A is unit 11' );
assert_test( 'close' === ( $modules[15]['kind'] ?? '' ), 'Program Close is unit 15' );

$assets = CTA_Lmft_Law_Ethics_Sync::ensure_placeholder_assets();
assert_test( is_readable( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/start-here.html' ), 'Start Here lesson written' );
assert_test( is_readable( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/program-close.html' ), 'Program Close lesson written' );
assert_test( is_readable( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/wb01.html' ), 'Workbook 1 placeholder lesson written' );
assert_test( is_readable( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/study-tools/flashcard-study-center.json' ), 'Empty flashcard study center JSON exists' );

$start = file_get_contents( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/start-here.html' );
assert_test( false === stripos( $start, 'PLACEHOLDER' ), 'Start Here is no longer a placeholder shell' );
assert_test( false !== stripos( $start, 'Recommended Learning Sequence' ), 'Start Here includes learning sequence' );

$wb1 = file_get_contents( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/wb01.html' );
assert_test( false !== stripos( $wb1, 'Key Concepts' ), 'Workbook lesson includes tab-friendly headings' );

require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-lessons.php';
$map = CTA_Exam_Prep_Lessons::get_program_map();
assert_test( isset( $map['california-law-ethics-exam-preparation'] ) && 'lmft-law-ethics' === $map['california-law-ethics-exam-preparation'], 'Lesson program map wired for LMFT Law & Ethics slug' );

require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-flashcard-center.php';
class CTA_Exam_Access {
	public static function is_exam_prep( $course ) {
		unset( $course );
		return true;
	}
}
$deck_map = CTA_Exam_Prep_Flashcard_Center::get_deck_path_map();
assert_test( isset( $deck_map['california-law-ethics-exam-preparation'] ), 'Flashcard Study Center path mapped for LMFT Law & Ethics' );

$toolkits = CTA_Lmft_Law_Ethics_Sync::get_toolkit_material_definitions();
assert_test( 6 === count( $toolkits ), 'Six Study Center toolkit definitions registered' );
assert_test(
	false === stripos( (string) $toolkits[0]['title'], '_Corrected' ),
	'Toolkit display titles omit upload-copy suffixes'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );
