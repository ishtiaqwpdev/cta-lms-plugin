<?php
/**
 * Verify LPCC 8 audio tracks: files, order, titles, runtimes, open access, admin deny.
 *
 * Run: C:\xampp\php\php.exe scripts/test-lpcc-audio-supplement.php
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
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		return is_string( $s ) ? trim( $s ) : '';
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $t ) {
		return $t;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-course-materials.php';
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

$expected = array(
	1 => array( 'needle' => 'Audio_Track_01_', 'title' => 'NCMHCE Case Reasoning: Sections, Qualifiers, and Evidence', 'runtime' => '3:58' ),
	2 => array( 'needle' => 'Audio_Track_02_', 'title' => 'Professional Identity, Intake, Assessment, and Differential Reasoning', 'runtime' => '10:37' ),
	3 => array( 'needle' => 'Audio_Track_03_', 'title' => 'Crisis, Trauma, Abuse, Violence, and Level-of-Care Sequencing', 'runtime' => '4:13' ),
	4 => array( 'needle' => 'Audio_Track_04_', 'title' => 'Conceptualization, Planning, Measurement, Progress, and Termination', 'runtime' => '4:05' ),
	5 => array( 'needle' => 'Audio_Track_05_', 'title' => 'Counseling Theories, Therapeutic Alliance, and Core Skills', 'runtime' => '4:09' ),
	6 => array( 'needle' => 'Audio_Track_06_', 'title' => 'Evidence-Informed Interventions and Context-Responsive Care', 'runtime' => '7:30' ),
	7 => array( 'needle' => 'Audio_Track_07_', 'title' => 'Modality, Referral, Collaboration, and California Professional Practice', 'runtime' => '7:26' ),
	8 => array( 'needle' => 'Audio_Track_08_', 'title' => 'Integrated Review, Error Repair, and Form A/Form B Readiness', 'runtime' => '6:47' ),
);

assert_true(
	'48 minutes 49 seconds' === CTA_Lpcc_Ncmhce_Sync::COMBINED_AUDIO_RUNTIME,
	'Authoritative combined runtime constant'
);

$ref_meta = new ReflectionClass( 'CTA_Lpcc_Ncmhce_Sync' );
$gsm      = $ref_meta->getMethod( 'get_syllabus_meta' );
$gsm->setAccessible( true );
$meta     = $gsm->invoke( null );
assert_true(
	isset( $meta['audio_combined_runtime'] ) && '48 minutes 49 seconds' === $meta['audio_combined_runtime'],
	'Syllabus meta stores combined runtime'
);
assert_true(
	isset( $meta['audio_tracks'] ) && 8 === (int) $meta['audio_tracks'],
	'Syllabus meta audio track count is 8'
);

$gdesc = $ref_meta->getMethod( 'get_program_description_html' );
$gdesc->setAccessible( true );
$html = (string) $gdesc->invoke( null );
assert_true( false !== strpos( $html, '48 minutes 49 seconds' ), 'Program description includes combined runtime' );
assert_true( false === strpos( $html, 'about 49 minutes' ), 'Program description no longer says about 49 minutes' );

$map = CTA_Lpcc_Ncmhce_Sync::get_audio_placement_map();
assert_true( 8 === count( $map ), 'Placement map has 8 tracks' );

$root = CTA_PLUGIN_DIR . 'assets/course-materials/lpcc-ncmhce/';
$prev = 0;
foreach ( $expected as $n => $exp ) {
	assert_true( isset( $map[ $n ] ), "Track {$n} present in map" );
	assert_true( $exp['title'] === $map[ $n ]['title'], "Track {$n} exact title" );
	assert_true( $exp['runtime'] === $map[ $n ]['runtime'], "Track {$n} runtime {$exp['runtime']}" );
	assert_true( false !== strpos( $map[ $n ]['file'], $exp['needle'] ), "Track {$n} package filename" );
	assert_true( is_file( $root . $map[ $n ]['file'] ), "Track {$n} file on disk" );
	assert_true( (int) $n === $prev + 1, "Track {$n} sequential playlist order" );
	$prev = (int) $n;

	$meta = CTA_Lpcc_Ncmhce_Sync::resolve_audio_meta(
		array(
			'file_path' => $map[ $n ]['file'],
			'title'     => $map[ $n ]['title'],
		)
	);
	assert_true( $meta && (int) $meta['track'] === $n && $meta['runtime'] === $exp['runtime'], "Track {$n} resolve_audio_meta" );
}

// Material map: audio block consecutive, no unlock gates, no admin docs.
$ref = new ReflectionClass( 'CTA_Lpcc_Ncmhce_Sync' );
$mm  = $ref->getMethod( 'get_material_map' );
$mm->setAccessible( true );
$items = $mm->invoke( null );

$audio_idxs = array();
foreach ( $items as $i => $it ) {
	$file = (string) ( $it['file'] ?? '' );
	if ( 0 === strpos( $file, 'audio/CTA_LPCC_Audio_Track_' ) ) {
		$audio_idxs[] = $i;
		assert_true( empty( $it['unlock_after_quiz_type'] ), 'Audio has no unlock gate: ' . basename( $file ) );
	}
	$hay = $file . ' ' . (string) ( $it['title'] ?? '' );
	assert_true(
		false === stripos( $hay, 'Recording Guide' ) && false === stripos( $hay, 'Completion Record' ) && false === stripos( $hay, '00_Admin' ),
		'No admin Recording Guide/Completion Record in learner map item'
	);
}
assert_true( 8 === count( $audio_idxs ), 'Material map includes exactly 8 audio files' );
$contiguous = true;
for ( $i = 1; $i < count( $audio_idxs ); $i++ ) {
	if ( $audio_idxs[ $i ] !== $audio_idxs[ $i - 1 ] + 1 ) {
		$contiguous = false;
	}
}
assert_true( $contiguous, 'Eight tracks appear as contiguous playlist block' );

assert_true(
	CTA_Course_Materials::is_admin_restricted_source_path( '00_Admin/CTA_LPCC_Recording_Guide.docx' ),
	'00_Admin Recording Guide denied'
);
assert_true(
	CTA_Course_Materials::is_admin_restricted_source_path( 'path/Completion_Record_v1.0.docx' ),
	'Completion Record path denied'
);
assert_true(
	! CTA_Course_Materials::is_admin_restricted_source_path( 'assets/course-materials/lpcc-ncmhce/audio/CTA_LPCC_Audio_Track_01_x.mp3' ),
	'Learner MP3 not denied'
);

$tpl = file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/course-materials.php' );
assert_true( false !== strpos( $tpl, 'CTA_Lpcc_Ncmhce_Sync' ), 'Learner template resolves LPCC audio runtimes' );
assert_true( false !== strpos( $tpl, 'Download MP3' ), 'Download MP3 control present' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
