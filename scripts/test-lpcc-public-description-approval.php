<?php
/**
 * Verify LPCC public description audio advertising is gated on test approval.
 *
 * Run: C:\xampp\php\php.exe scripts/test-lpcc-public-description-approval.php
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

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lpcc-ncmhce-sync.php';

$pass = 0;
$fail = 0;
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

$verify = CTA_PLUGIN_DIR . 'docs/LPCC_Audio_Playback_Verification.md';
$approval = CTA_PLUGIN_DIR . 'docs/LPCC_Public_Description_Audio_Approval.md';
assert_true( is_file( $verify ), 'Playback verification doc exists' );
$verify_txt = file_get_contents( $verify );
assert_true( false !== strpos( $verify_txt, 'allPass' ) || ( false !== strpos( $verify_txt, '**PASS**' ) && false !== strpos( $verify_txt, 'Set as a whole' ) ), 'Verification doc records PASS' );
assert_true( is_file( $approval ), 'Public description approval doc exists' );
assert_true( false !== strpos( file_get_contents( $approval ), 'APPROVED' ), 'Approval doc marked APPROVED' );

assert_true( true === CTA_Lpcc_Ncmhce_Sync::AUDIO_PUBLIC_ADVERTISING_APPROVED, 'Advertising flag true only after test pass' );
assert_true( CTA_Lpcc_Ncmhce_Sync::audio_public_advertising_approved(), 'audio_public_advertising_approved()' );

$ref = new ReflectionClass( 'CTA_Lpcc_Ncmhce_Sync' );
$gd  = $ref->getMethod( 'get_program_description_html' );
$gd->setAccessible( true );
$html = (string) $gd->invoke( null );
assert_true( false !== strpos( $html, 'Eight screen-free audio-review tracks' ), 'Program HTML advertises eight tracks' );
assert_true( false !== strpos( $html, '48 minutes 49 seconds' ), 'Program HTML includes combined runtime' );
assert_true( false !== strpos( $html, 'Eight recorded audio-review tracks are included' ), 'Closing line confirms audio included' );

$gsm = $ref->getMethod( 'get_syllabus_meta' );
$gsm->setAccessible( true );
$meta = $gsm->invoke( null );
assert_true( ! empty( $meta['audio_public_approved'] ), 'Syllabus meta marks audio public approved' );
assert_true( '48 minutes 49 seconds' === (string) $meta['audio_combined_runtime'], 'Syllabus meta combined runtime present' );
assert_true( false !== strpos( (string) $meta['short_description'], 'eight audio-review tracks' ), 'Short description includes audio' );
assert_true( false !== strpos( (string) $meta['meta_description'], 'eight audio-review tracks' ), 'Meta description includes audio' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
