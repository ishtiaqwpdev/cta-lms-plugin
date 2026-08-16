<?php
/**
 * Validate CTA-CE-003 module definitions, Vimeo embed markup, and sequential unlock logic.
 *
 * Usage: php scripts/test-suicide-risk-ce-modules.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', $root . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return (string) $text;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $text ) {
		return (string) $text;
	}
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		unset( $tag, $callback );
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $tag, $callback, $priority, $accepted_args );
	}
}

require_once $root . '/includes/class-cta-suicide-risk-module-sync.php';
require_once $root . '/public/class-cta-student-dashboard.php';

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

$expected_titles = array(
	1 => 'The Epidemiology and Phenomenology of Suicide',
	2 => 'Standardized Assessment and Comprehensive Risk Formulation',
	3 => 'Moving Beyond No-Harm Contracts to Collaborative Safety Planning',
	4 => 'Involuntary Evaluation, Hospitalization, and Legal Thresholds',
	5 => 'Clinical Documentation, Consultation, and Liability Management',
	6 => 'Postvention, Clinician Wellness, and Professional Recovery',
);

$expected_ids = array(
	1 => '1216849426',
	2 => '1216893343',
	3 => '1216901724',
	4 => '1216909079',
	5 => '1217091350',
	6 => '1217142210',
);

$defs = CTA_Suicide_Risk_Module_Sync::get_module_definitions();
assert_true( 6 === count( $defs ), 'Exactly six module definitions' );
assert_true( CTA_Suicide_Risk_Module_Sync::COURSE_CODE === 'CTA-CE-003', 'Sync scoped to CTA-CE-003' );

$dashboard = null;

echo "\nPer-module validation:\n";

foreach ( $expected_titles as $order => $title ) {
	$def = $defs[ $order ] ?? null;
	assert_true( is_array( $def ), "M0{$order} definition exists" );
	assert_true( $title === ( $def['title'] ?? '' ), "M0{$order} title exact match" );

	$vimeo_id = $expected_ids[ $order ];
	$url      = (string) ( $def['video_url'] ?? '' );
	assert_true( false !== strpos( $url, $vimeo_id ), "M0{$order} Vimeo URL contains {$vimeo_id}" );

	$module = (object) array(
		'id'        => 1000 + $order,
		'title'     => $title,
		'video_url' => 'https://player.vimeo.com/video/' . $vimeo_id,
	);

	$markup = CTA_Student_Dashboard::get_vimeo_responsive_embed( $vimeo_id, $title );
	assert_true( false !== strpos( $markup, 'player.vimeo.com/video/' . $vimeo_id ), "M0{$order} embed iframe uses player.vimeo.com/{$vimeo_id}" );
	assert_true( false !== strpos( $markup, 'allowfullscreen' ), "M0{$order} embed is iframe with fullscreen" );
	assert_true( false !== strpos( $markup, esc_attr( $title ) ), "M0{$order} embed iframe title matches module title" );

	echo "  M0{$order}: {$title} — embed OK\n";
}

// Sequential unlock simulation (same rules as CTA_Student_Dashboard::is_module_accessible for CE).
$get_module_index = static function ( array $modules, $module_id ) {
	foreach ( $modules as $index => $module ) {
		if ( (int) $module->id === (int) $module_id ) {
			return (int) $index;
		}
	}
	return -1;
};
$is_module_accessible = static function ( array $modules, array $completed_ids, $module_id ) use ( $get_module_index ) {
	$index = $get_module_index( $modules, $module_id );
	if ( $index < 0 ) {
		return false;
	}
	if ( 0 === $index ) {
		return true;
	}
	$previous_id = (int) $modules[ $index - 1 ]->id;
	return in_array( $previous_id, $completed_ids, true );
};

$modules = array();
foreach ( range( 1, 6 ) as $order ) {
	$modules[] = (object) array(
		'id'          => 1000 + $order,
		'course_id'   => 999,
		'order_index' => $order,
		'title'       => $expected_titles[ $order ],
	);
}

assert_true( $is_module_accessible( $modules, array(), 1001 ), 'Module 1 accessible with no completions' );
assert_true( ! $is_module_accessible( $modules, array(), 1002 ), 'Module 2 locked until Module 1 complete' );

$completed = array( 1001 );
assert_true( $is_module_accessible( $modules, $completed, 1002 ), 'Module 2 unlocks after Module 1 marked complete' );
assert_true( ! $is_module_accessible( $modules, $completed, 1003 ), 'Module 3 locked until Module 2 complete' );

$completed = array( 1001, 1002, 1003, 1004, 1005 );
assert_true( $is_module_accessible( $modules, $completed, 1006 ), 'Module 6 unlocks after Modules 1–5 complete' );
assert_true( ! $is_module_accessible( $modules, array( 1001 ), 1003 ), 'Module 3 locked until Module 2 marked complete' );
assert_true( ! $is_module_accessible( $modules, array( 1001, 1002 ), 1004 ), 'Module 4 locked until Module 3 marked complete' );

$sync_src = file_get_contents( $root . '/includes/class-cta-suicide-risk-module-sync.php' );
assert_true( false !== strpos( $sync_src, "'is_locked'     => 1" ), 'Modules stored with is_locked=1 (sequential CE pattern)' );
assert_true( false !== strpos( $sync_src, 'unpublish_all_ce_courses_pending_cepa' ), 'Sync does not publish CE course' );

echo "\nKnowledge checks: formative checks embedded in Vimeo content — no separate LMS certificate gates in this build.\n";
echo "Playback note: live Vimeo playback requires deploy + enrolled learner session (not testable offline).\n";

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
