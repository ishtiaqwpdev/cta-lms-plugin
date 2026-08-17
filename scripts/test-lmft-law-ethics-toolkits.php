<?php
/**
 * Verification tests for LMFT Law & Ethics Study Center toolkits (CTA-EP-001).
 *
 * Run: php scripts/test-lmft-law-ethics-toolkits.php
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

function sanitize_text_field( $text ) {
	return trim( (string) $text );
}

function sanitize_key( $text ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $text ) );
}

function sanitize_file_name( $text ) {
	return basename( (string) $text );
}

function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-law-ethics-sync.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-downloads.php';

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

$defs = CTA_Lmft_Law_Ethics_Sync::get_toolkit_material_definitions();

assert_test( 6 === count( $defs ), 'Exactly six toolkit definitions registered' );

$expected_titles = array(
	'45-Chapter Exam Traps & Correction Rules Toolkit',
	'45-Chapter Master Study Map & Readiness Checklist Toolkit',
	'Exam Strategy & Study Planning Toolkit',
	'High-Yield California Ethics Decision Guides Toolkit',
	'High-Yield California Law Decision Guides Toolkit',
	'High-Yield Numbers, Timelines & Trigger Words Toolkit',
);

foreach ( $expected_titles as $index => $title ) {
	assert_test(
		isset( $defs[ $index ] ) && $defs[ $index ]['title'] === $title,
		'Toolkit ' . ( $index + 1 ) . ' display title matches approved copy'
	);
}

$forbidden_ui_tokens = array( '_v1.1_Corrected', '_Corrected', 'v1.1', 'CTA_LE_LMFT_', '.docx' );
foreach ( $defs as $def ) {
	$title = (string) $def['title'];
	foreach ( $forbidden_ui_tokens as $token ) {
		assert_test(
			false === stripos( $title, $token ),
			'Display title does not leak "' . $token . '": ' . $title
		);
	}
}

$package_dir = CTA_PLUGIN_DIR . CTA_Lmft_Law_Ethics_Sync::PACKAGE_TOOLKIT_DIR;
$on_disk     = 0;
foreach ( $defs as $def ) {
	$path = CTA_PLUGIN_DIR . CTA_Lmft_Law_Ethics_Sync::MATERIALS_REL . ltrim( (string) $def['file'], '/' );
	if ( is_readable( $path ) ) {
		++$on_disk;
	}
}

if ( 6 === $on_disk ) {
	echo "INFO: All six toolkit DOCX files are present on disk.\n";
} else {
	echo "WARN: Toolkit DOCX files missing ({$on_disk}/6). Copy package to:\n";
	echo "  {$package_dir}\n";
}

// Classification: titles must land in Study Toolkits category.
$mock_resource       = new stdClass();
$mock_resource->title = $defs[0]['title'];
$mock_resource->file_path = $defs[0]['file'];
$mock_resource->file_url  = '';
$mock_resource->module_id = 0;
$mock_resource->is_practice_test = 0;

$ref = new ReflectionClass( 'CTA_Exam_Prep_Downloads' );
$method = $ref->getMethod( 'classify_resource' );
$method->setAccessible( true );
$category = $method->invoke( null, $mock_resource, 'docx' );
assert_test( 'toolkits' === $category, 'Toolkit resources classify into Study Toolkits category' );

echo "\nDone: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
