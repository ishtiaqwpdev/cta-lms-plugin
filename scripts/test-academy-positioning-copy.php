<?php
/**
 * Verify academy positioning copy helpers (no WordPress bootstrap).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		return $text;
	}
}

require_once __DIR__ . '/../includes/class-cta-academy-positioning.php';

if ( ! defined( 'CTA_VERSION' ) ) {
	define( 'CTA_VERSION', '1.0.269' );
}

$passed = 0;
$failed = 0;

function assert_contains( $label, $haystack, $needle ) {
	global $passed, $failed;

	if ( false !== strpos( $haystack, $needle ) ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}

	++$failed;
	echo "FAIL: {$label} — expected to contain \"{$needle}\"\n";
}

function assert_not_contains( $label, $haystack, $needle ) {
	global $passed, $failed;

	if ( false === strpos( $haystack, $needle ) ) {
		++$passed;
		echo "PASS: {$label}\n";
		return;
	}

	++$failed;
	echo "FAIL: {$label} — should not contain \"{$needle}\"\n";
}

$meta = CTA_Academy_Positioning::meta_description();
assert_contains( 'meta includes exam preparation', $meta, 'exam preparation' );
assert_contains( 'meta includes clinical supervision', $meta, 'clinical supervision' );
assert_contains( 'meta includes continuing education context', strtolower( $meta ), 'ce' );

$footer = CTA_Academy_Positioning::footer_tagline();
assert_contains( 'footer includes exam preparation', $footer, 'exam preparation' );
assert_not_contains( 'footer omits narrow BBS-only framing', $footer, 'BBS-compliant continuing education and clinical supervision' );

$top = CTA_Academy_Positioning::top_bar_tagline();
assert_contains( 'top bar includes exam preparation', $top, 'exam preparation' );

$replacements = CTA_Academy_Positioning::get_legacy_replacements();
assert_contains(
	'replacement map covers live top bar',
	implode( ' ', array_keys( $replacements ) ),
	'California-focused continuing education and clinical supervision'
);
assert_contains(
	'replacement map covers live footer',
	implode( ' ', array_keys( $replacements ) ),
	'Practical, accessible continuing education and clinical supervision'
);

$sample = CTA_Academy_Positioning::get_legacy_replacements();
$test   = 'Practical, accessible continuing education and clinical supervision for California mental health professionals.';
foreach ( $sample as $old => $new ) {
	if ( false !== strpos( $test, $old ) ) {
		$test = str_replace( $old, $new, $test );
	}
}
assert_contains( 'replacement applies to live footer sample', $test, 'exam preparation' );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
