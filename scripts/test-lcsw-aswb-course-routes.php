<?php
/**
 * Verify LCSW ASWB Clinical slug landing page + legacy redirect configuration.
 *
 * Usage: php scripts/test-lcsw-aswb-course-routes.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );
define( 'CTA_VERSION', '1.0.249-test' );

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', strtolower( (string) $title ) ), '-' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-sync.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-course-routes.php';

$pass = 0;
$fail = 0;

function assert_eq( $expected, $actual, $label ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		++$pass;
		echo "PASS: {$label}\n";
		return;
	}
	++$fail;
	echo "FAIL: {$label}\n";
	echo "  expected: {$expected}\n";
	echo "  actual:   {$actual}\n";
}

echo "=== LCSW ASWB Clinical — Course Routes ===\n\n";

assert_eq( 'lcsw-aswb-clinical-exam-preparation', CTA_Lcsw_Aswb_Sync::SLUG, 'Canonical slug constant' );
assert_eq( 'lcsw-california-clinical-exam-preparation', CTA_Lcsw_Aswb_Sync::LEGACY_SLUG, 'Legacy slug constant' );

$defs = CTA_Course_Routes::get_program_route_defs();
assert_eq( 1, count( $defs ), 'Exactly one route definition registered' );
assert_eq( 'lcsw-aswb-clinical-exam-preparation', (string) ( $defs[0]['slug'] ?? '' ), 'Route definition canonical slug' );
assert_eq(
	array( 'lcsw-california-clinical-exam-preparation' ),
	(array) ( $defs[0]['legacy_slugs'] ?? array() ),
	'Route definition legacy slugs'
);

$parsed = CTA_Course_Routes::parse_shortcode_course_id( '[cta_single_course course_id="11"]' );
assert_eq( 11, $parsed, 'Shortcode course_id attribute parses' );

$courses_php = file_get_contents( CTA_PLUGIN_DIR . 'public/class-cta-courses.php' );
assert_eq(
	true,
	false !== strpos( $courses_php, "'course_id' => 0" ) && false !== strpos( $courses_php, 'shortcode_atts' ),
	'Single course shortcode accepts course_id attribute'
);

$cta_lms = file_get_contents( CTA_PLUGIN_DIR . 'cta-lms.php' );
assert_eq(
	true,
	false !== strpos( $cta_lms, 'CTA_Course_Routes::get_canonical_url( $course_id )' ),
	'cta_lms_get_single_course_url prefers canonical landing page URL'
);

$stripe = file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-stripe.php' );
assert_eq(
	true,
	false !== strpos( $stripe, 'cta_lms_get_single_course_url( $course_id )' ),
	'Stripe checkout uses canonical course URL helper'
);

echo "\n=== Summary ===\n";
echo "PASS: {$pass}\n";
echo "FAIL: {$fail}\n";

exit( $fail > 0 ? 1 : 0 );
