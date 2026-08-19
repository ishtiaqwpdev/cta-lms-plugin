<?php
/**
 * Verify CTA-CE-003 approved course image wiring (replaces admin placeholder).
 *
 * Usage: php scripts/test-suicide-risk-course-image.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );
define( 'CTA_PLUGIN_URL', 'https://example.test/wp-content/plugins/cta-lms-plugin/' );

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return 'https://example.test/wp-content' . (string) $path;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		unset( $key );
		return $default;
	}
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $path, $output, $post_type ) {
		unset( $path, $output, $post_type );
		return null;
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) {
			unset( $args );
		}
	}
}

if ( ! function_exists( 'wp_reset_postdata' ) ) {
	function wp_reset_postdata() {
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $id ) {
		unset( $id );
		return '';
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-suicide-risk-certificate-sync.php';

$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
		return;
	}
	++$fail;
	echo "FAIL: {$msg}\n";
}

echo "=== CTA-CE-003 Suicide Risk Course Image ===\n\n";

$bundled = CTA_PLUGIN_DIR . 'assets/course-images/CTA_Suicide_Risk_Course_Image.png';
$sync_src = (string) file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-suicide-risk-certificate-sync.php' );
$syllabus = (string) file_get_contents( CTA_PLUGIN_DIR . 'includes/syllabus/cta-syllabus-data.php' );
$lms      = (string) file_get_contents( CTA_PLUGIN_DIR . 'cta-lms.php' );
$card_tpl = (string) file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/course-card.php' );
$progress = (string) file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/progress-card.php' );

assert_true( is_readable( $bundled ), 'Bundled approved PNG exists in assets/course-images/' );
assert_true(
	false !== strpos( $sync_src, 'sync_thumbnail' )
	&& false === strpos( $sync_src, 'sync_placeholder_thumbnail' ),
	'Certificate sync exposes sync_thumbnail and no longer forces placeholder thumbnail'
);
assert_true(
	false === strpos( $sync_src, 'thumbnail_is_placeholder' . "           = true" )
	&& false === strpos( $sync_src, "'thumbnail_is_placeholder'           => true" ),
	'Certificate sync no longer sets thumbnail_is_placeholder in syllabus meta'
);
assert_true(
	false !== strpos( $sync_src, 'Suicide.png' )
	&& false !== strpos( $sync_src, 'CTA_Suicide_Risk_Course_Image.png' ),
	'Thumbnail resolver checks Media Library Suicide.png and bundled PNG'
);
assert_true(
	false === stripos( $syllabus, 'pending client approval' ),
	'Syllabus seed alt text no longer mentions pending client approval'
);
assert_true(
	false !== strpos( $syllabus, 'Advanced Suicide Risk Assessment course image from Clinical Training and Supervision Academy' ),
	'Syllabus seed uses final learner-facing image alt text'
);
assert_true(
	false === stripos( $syllabus, 'thumbnail_is_placeholder' ),
	'Syllabus seed no longer marks suicide course thumbnail as placeholder'
);
assert_true(
	false !== strpos( $lms, 'CTA_Suicide_Risk_Certificate_Sync::sync_thumbnail' )
	&& false !== strpos( $lms, '1.0.267' ),
	'Upgrade hook 1.0.267 runs approved thumbnail sync'
);

$url = CTA_Suicide_Risk_Certificate_Sync::resolve_approved_thumbnail_url();
assert_true( '' !== $url, 'resolve_approved_thumbnail_url returns a URL' );
assert_true(
	false === stripos( $url, 'ADMIN_PLACEHOLDER' ),
	'Resolved thumbnail URL is not the admin placeholder SVG'
);
assert_true(
	false !== stripos( $url, 'CTA_Suicide_Risk_Course_Image.png' )
	|| false !== stripos( $url, 'Suicide.png' ),
	'Resolved thumbnail URL points to approved PNG asset'
);

assert_true(
	false !== strpos( $card_tpl, 'image_alt' )
	&& false !== strpos( $progress, 'image_alt' ),
	'Catalog and dashboard cards use syllabus image_alt'
);

echo "\n=== Summary ===\n";
echo "PASS: {$pass}\n";
echo "FAIL: {$fail}\n";

exit( $fail > 0 ? 1 : 0 );
