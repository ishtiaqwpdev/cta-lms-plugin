<?php
/**
 * Smoke tests for LCSW ASWB product identity (title + artwork path).
 *
 * Run: php scripts/test-lcsw-aswb-product-identity.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'CTA_PLUGIN_URL', 'https://example.test/wp-content/plugins/cta-lms/' );

function esc_url_raw( $url ) {
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
}

function sanitize_text_field( $str ) {
	return trim( strip_tags( (string) $str ) );
}

function sanitize_title( $title ) {
	return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', strtolower( (string) $title ) ), '-' );
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

function wp_json_encode( $data ) {
	return json_encode( $data );
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-sync.php';

$failures = 0;

function assert_eq( $actual, $expected, $label ) {
	global $failures;
	if ( $actual !== $expected ) {
		echo "FAIL: {$label}\n  expected: {$expected}\n  actual:   {$actual}\n";
		++$failures;
		return;
	}
	echo "PASS: {$label}\n";
}

function assert_true( $condition, $label ) {
	assert_eq( (bool) $condition, true, $label );
}

assert_eq( CTA_Lcsw_Aswb_Sync::PUBLIC_TITLE, 'LCSW ASWB Clinical Exam Preparation', 'PUBLIC_TITLE constant' );
assert_eq( CTA_Lcsw_Aswb_Sync::SLUG, 'lcsw-aswb-clinical-exam-preparation', 'Canonical slug' );
assert_true( CTA_Lcsw_Aswb_Sync::is_stale_display_title( 'LCSW California Clinical Exam Preparation' ), 'Legacy public title detected' );
assert_true( ! CTA_Lcsw_Aswb_Sync::is_stale_display_title( 'LCSW ASWB Clinical Exam Preparation' ), 'Approved public title not stale' );

$asset = CTA_PLUGIN_DIR . CTA_Lcsw_Aswb_Sync::THUMBNAIL_REL;
assert_true( is_readable( $asset ), 'Bundled artwork file exists' );

$url = CTA_Lcsw_Aswb_Sync::resolve_approved_thumbnail_url();
assert_true( false !== strpos( $url, 'CTA_LCSW_ASWB_Clinical_Exam_Preparation_Program_Website_Image_v1.0.png' ), 'Thumbnail URL resolves to bundled asset' );

$meta = ( new ReflectionClass( 'CTA_Lcsw_Aswb_Sync' ) )->getMethod( 'get_syllabus_meta' );
$meta->setAccessible( true );
$syllabus = $meta->invoke( null );
assert_eq( $syllabus['public_title'], 'LCSW ASWB Clinical Exam Preparation', 'Syllabus public_title' );
assert_true( false === strpos( $syllabus['image_alt'], 'California Clinical' ), 'Image alt uses ASWB naming' );

echo PHP_EOL . ( 0 === $failures ? 'All tests passed.' : "{$failures} test(s) failed." ) . PHP_EOL;
exit( 0 === $failures ? 0 : 1 );
