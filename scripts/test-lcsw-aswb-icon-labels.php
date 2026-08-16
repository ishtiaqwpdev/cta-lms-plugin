<?php
/**
 * Verify LCSW ASWB Clinical program overview labels (PROMPT — icon labels).
 *
 * Usage: php scripts/test-lcsw-aswb-icon-labels.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]+/', '-', (string) $title ) );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}

require_once $root . '/includes/class-cta-exam-prep-getting-started.php';

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

$course = (object) array(
	'id'   => 11,
	'slug' => 'lcsw-aswb-clinical-exam-preparation',
);

$config  = CTA_Exam_Prep_Getting_Started::get_config_for_course( $course );
$domains = (array) ( $config['exam_overview']['domains'] ?? array() );

echo "=== LCSW ASWB Clinical — Program Overview Labels ===\n\n";

assert_eq( 'lcsw-aswb', CTA_Exam_Prep_Getting_Started::program_key_for_course( $course ), 'Program key resolves to lcsw-aswb' );
assert_eq( 4, count( $domains ), 'Exactly four overview labels configured' );
assert_eq( 'Values & Ethics', (string) ( $domains[0] ?? '' ), 'Label 1' );
assert_eq( 'Assessment & Planning', (string) ( $domains[1] ?? '' ), 'Label 2' );
assert_eq( 'Intervention & Practice', (string) ( $domains[2] ?? '' ), 'Label 3' );
assert_eq( 'Practice Simulations', (string) ( $domains[3] ?? '' ), 'Label 4' );

$default = CTA_Exam_Prep_Getting_Started::get_program_configs()['default']['exam_overview']['domains'] ?? array();
assert_eq(
	'[Domain / content area 1]',
	(string) ( $default[0] ?? '' ),
	'Default program config remains unchanged (shared fallback)'
);

echo "\n=== Summary ===\n";
echo "PASS: {$pass}\n";
echo "FAIL: {$fail}\n";

exit( $fail > 0 ? 1 : 0 );
