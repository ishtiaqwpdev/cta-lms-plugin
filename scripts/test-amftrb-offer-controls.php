<?php
/**
 * Verify AMFTRB approved offer controls in code/config.
 *
 * Run: C:\xampp\php\php.exe scripts/test-amftrb-offer-controls.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! function_exists( '__' ) ) {
	function __( $t, $d = null ) {
		unset( $d );
		return $t;
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $t ) {
		return $t;
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-sync.php';
require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-access.php';

$pass = 0;
$fail = 0;
function assert_eq( $got, $want, $label ) {
	global $pass, $fail;
	if ( (string) $got === (string) $want ) {
		echo "PASS: {$label}\n";
		++$pass;
	} else {
		echo "FAIL: {$label} — got=[" . $got . "] want=[" . $want . "]\n";
		++$fail;
	}
}
function assert_true( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		echo "PASS: {$label}\n";
		++$pass;
	} else {
		echo "FAIL: {$label}\n";
		++$fail;
	}
}

assert_eq( CTA_Lmft_Amftrb_Sync::TITLE, 'CTA LMFT AMFTRB National Exam Preparation Program', 'Formal/internal name' );
assert_eq( CTA_Lmft_Amftrb_Sync::PUBLIC_TITLE, 'LMFT AMFTRB National Exam Preparation', 'Public display name' );
assert_eq( CTA_Lmft_Amftrb_Sync::PRICE, '329', 'Price $329' );
assert_eq( CTA_Lmft_Amftrb_Sync::ACCESS_MONTHS, '6', 'Access period 6 months' );
assert_eq( CTA_Lmft_Amftrb_Sync::CLASSIFICATION, 'Exam Preparation Program | No CE Credit', 'Classification' );
assert_eq( CTA_Lmft_Amftrb_Sync::AUDIO_TRACK_COUNT, '12', 'Audio track count' );
assert_eq( CTA_Lmft_Amftrb_Sync::COMBINED_AUDIO_RUNTIME, '1:15:26.811', 'Combined audio runtime' );

$ref = new ReflectionClass( 'CTA_Lmft_Amftrb_Sync' );
$m   = $ref->getMethod( 'get_syllabus_meta' );
$m->setAccessible( true );
$meta = $m->invoke( null );
assert_eq( $meta['public_title'], 'LMFT AMFTRB National Exam Preparation', 'Meta public_title' );
assert_eq( $meta['course_classification'], 'Exam Preparation Program | No CE Credit', 'Meta classification' );
assert_eq( $meta['audio_tracks'], '12', 'Meta audio_tracks' );
assert_eq( $meta['audio_combined_runtime'], '1:15:26.811', 'Meta audio_combined_runtime' );

$d = $ref->getMethod( 'get_program_description_html' );
$d->setAccessible( true );
$html = $d->invoke( null );
assert_true( false !== strpos( $html, '1:15:26.811' ), 'Description includes exact combined runtime' );
assert_true( false !== strpos( $html, 'Exam Preparation Program | No CE Credit' ), 'Description uses approved classification' );

$map = CTA_Lmft_Amftrb_Sync::get_audio_placement_map();
assert_eq( count( $map ), '12', 'Placement map has 12 tracks' );

$tpl = file_get_contents( CTA_PLUGIN_DIR . 'templates/single-course.php' );
assert_true( false !== strpos( $tpl, 'Recorded Audio:' ), 'Product page shows Recorded Audio field' );
assert_true( false !== strpos( $tpl, 'combined runtime' ), 'Product page shows combined runtime' );
assert_true( false !== strpos( $tpl, 'Exam Preparation Program | No CE Credit' ), 'Product page default classification wording' );

foreach ( CTA_Exam_Access::get_default_programs() as $p ) {
	if ( 'lmft-amftrb-national-exam-preparation' !== ( $p['slug'] ?? '' ) ) {
		continue;
	}
	assert_eq( $p['title'], 'CTA LMFT AMFTRB National Exam Preparation Program', 'Exam-access formal title' );
	assert_eq( $p['public_title'], 'LMFT AMFTRB National Exam Preparation', 'Exam-access public title' );
	assert_eq( $p['price'], '329', 'Exam-access price' );
	assert_eq( $p['course_classification'] ?? '', 'Exam Preparation Program | No CE Credit', 'Exam-access classification' );
	assert_true( false !== strpos( (string) $p['description'], '1:15:26.811' ), 'Exam-access description runtime' );
}

echo "\n{$pass} passed, {$fail} failed\n";
echo "NOTE: Live public product page still HOLD (draft). Admin preview after plugin 1.0.149 upgrade confirms UI fields.\n";
exit( $fail > 0 ? 1 : 0 );
