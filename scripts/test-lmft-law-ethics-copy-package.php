<?php
/**
 * Verification tests for LMFT Law & Ethics Point 6 Website/LMS Copy Package v1.1.
 *
 * Run: php scripts/test-lmft-law-ethics-copy-package.php
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

function wp_kses_post( $html ) {
	return $html;
}

function get_option( $key, $default = false ) {
	unset( $key );
	return $default;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_json_encode( $data, $options = 0 ) {
	unset( $options );
	return json_encode( $data );
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

assert_test(
	'CTA LMFT California Law & Ethics Exam Preparation Program' === CTA_Lmft_Law_Ethics_Copy::TITLE,
	'Exact program title'
);
assert_test( 199.0 === (float) CTA_Lmft_Law_Ethics_Copy::PRICE, 'Launch price $199' );
assert_test( 6 === (int) CTA_Lmft_Law_Ethics_Copy::ACCESS_MONTHS, 'Six-month access' );
assert_test( 15 === count( CTA_Lmft_Law_Ethics_Copy::faqs() ), 'Fifteen FAQ entries' );
assert_test( 5 === count( CTA_Lmft_Law_Ethics_Copy::checkout_acknowledgments() ), 'Five checkout acknowledgments' );
assert_test( 6 === count( CTA_Lmft_Law_Ethics_Copy::disclaimers() ), 'Six required disclaimers' );
assert_test( 8 === count( CTA_Lmft_Law_Ethics_Copy::what_is_included() ), 'Eight What Is Included bullets' );
assert_test( 16 === (int) CTA_Lmft_Law_Ethics_Copy::UNIT_TOTAL, 'Sixteen-unit progress total' );
assert_test( 80 === (int) CTA_Lmft_Law_Ethics_Copy::business_rules()['assessment_readiness_benchmark'], '80% readiness benchmark' );
assert_test( false === CTA_Lmft_Law_Ethics_Copy::business_rules()['assessment_benchmark_is_gate'], 'Benchmark is not a gate' );
assert_test( false === CTA_Lmft_Law_Ethics_Copy::business_rules()['publicly_purchasable'], 'Not publicly purchasable' );
assert_test( 'Under Review' === CTA_Lmft_Law_Ethics_Copy::CATALOG_STATUS, 'Catalog status Under Review' );

$meta = CTA_Lmft_Law_Ethics_Copy::syllabus_meta();
assert_test( ! empty( $meta['launch_pending_testing'] ), 'Launch pending testing flag set' );
assert_test( ! empty( $meta['hide_course_code_public'] ), 'Program code hidden from public surfaces' );
assert_test(
	'California LMFT Law & Ethics Exam Preparation | CTA' === $meta['seo_title'],
	'SEO title matches approved copy'
);
assert_test(
	false === stripos( wp_json_encode( $meta ), '990' ),
	'Meta payload never advertises 990 combined questions'
);

$blob = wp_json_encode( CTA_Lmft_Law_Ethics_Copy::what_is_included() )
	. CTA_Lmft_Law_Ethics_Copy::short_description()
	. CTA_Lmft_Law_Ethics_Copy::checkout_description();
assert_test( false === stripos( $blob, '990' ), 'Marketing copy never sums to 990' );

$defs = new ReflectionMethod( 'CTA_Lmft_Law_Ethics_Sync', 'get_module_definitions' );
$defs->setAccessible( true );
$modules = $defs->invoke( null );
assert_test( 16 === count( $modules ), 'Sixteen modules in approved sequence' );
assert_test( 'start' === $modules[0]['kind'], 'Unit 00 is Start Here' );
assert_test( 'license' === $modules[1]['kind'], 'Unit 01 is Practice Act module' );
assert_test( false !== stripos( $modules[2]['title'], 'Workbook 1' ), 'Workbook 1 follows Practice Act' );
assert_test( 'practice_a' === $modules[11]['kind'], 'Unit 11 Practice Examination A' );
assert_test( 'close' === $modules[15]['kind'], 'Unit 15 Program Close' );

$start = file_get_contents( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/start-here.html' );
assert_test( false !== stripos( $start, 'Welcome to the CTA LMFT California Law & Ethics Exam Preparation Program' ) || false !== stripos( $start, 'Welcome to the CTA LMFT California Law &amp; Ethics Exam Preparation Program' ), 'Start Here welcome copy present' );
assert_test( false !== stripos( $start, 'Practice Act module before Workbook 1' ), 'Start Here places Practice Act before Workbook 1' );
assert_test( false === stripos( $start, 'CTA-EP-001' ), 'Start Here omits internal program code' );
assert_test( false === stripos( $start, 'v1.1' ) && false === stripos( $start, 'Version' ), 'Start Here omits version labels' );

$close = file_get_contents( CTA_PLUGIN_DIR . 'assets/course-materials/lmft-law-ethics/lessons/program-close.html' );
assert_test( false !== stripos( $close, 'Final Study Check' ), 'Program Close includes Final Study Check' );
assert_test( 6 === substr_count( $close, 'cta-lesson-li' ), 'Program Close has six checklist items' );

$faqs = CTA_Lmft_Law_Ethics_Copy::faqs();
assert_test( 'Is this a continuing education course?' === $faqs[0]['question'], 'FAQ 1 question verbatim' );
assert_test( 'What is the refund policy?' === $faqs[14]['question'], 'FAQ 15 question verbatim' );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );
