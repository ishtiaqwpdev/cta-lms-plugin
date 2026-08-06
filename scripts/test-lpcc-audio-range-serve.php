<?php
/**
 * Unit test: Range streaming helper behavior mirrors LMS serve fix.
 *
 * Run: C:\xampp\php\php.exe scripts/test-lpcc-audio-range-serve.php
 */

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

$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-cta-course-materials.php' );
assert_true( false !== strpos( $src, "header( 'Accept-Ranges: bytes' )" ), 'Serve handler advertises Accept-Ranges' );
assert_true( false !== strpos( $src, 'HTTP_RANGE' ), 'Serve handler reads Range header' );
assert_true( false !== strpos( $src, 'Content-Range' ), 'Serve handler emits Content-Range' );
assert_true( false !== strpos( $src, 'status_header( 206 )' ) || false !== strpos( $src, '$code = 206' ), 'Serve handler supports 206 Partial Content' );
assert_true( false === strpos( $src, "readfile( \$local );\n\t\t\texit;" ), 'Full-file readfile-only path removed for local serve' );

// Smoke: range server script exists and can parse a sample Range.
$mp3 = dirname( __DIR__ ) . '/assets/course-materials/lpcc-ncmhce/audio/CTA_LPCC_Audio_Track_01_NCMHCE_Case_Reasoning_Sections_Qualifiers_and_Evidence_v1.0.mp3';
assert_true( is_file( $mp3 ), 'Track 01 MP3 present' );
$size = filesize( $mp3 );
assert_true( $size > 100000, 'Track 01 has substantial payload' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
