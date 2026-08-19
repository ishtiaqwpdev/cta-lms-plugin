<?php
/**
 * Learner-facing content audit — fail if exam-prep lesson HTML is still placeholder.
 *
 * This checks the same files the course player serves via CTA_Exam_Prep_Lessons
 * (assets/course-materials/{program}/lessons/*.html), not admin DB shells alone.
 *
 * Usage: php scripts/test-learner-facing-content-audit.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root      = dirname( __DIR__ );
$materials = $root . '/assets/course-materials';
$programs  = array(
	'lmft-law-ethics',
	'lpcc-law-ethics',
	'lcsw-law-ethics',
	'lmft-clinical',
	'lmft-amftrb',
	'lcsw-aswb',
	'lpcc-ncmhce',
);

$patterns = array(
	'data-placeholder="1"',
	'PLACEHOLDER — CONTENT PENDING',
	'structural shell only',
	'pending client content delivery',
	'final content is still pending',
	'[Placeholder]',
);

$failed  = 0;
$passed  = 0;
$reports = array();

foreach ( $programs as $program ) {
	$lesson_dir = $materials . '/' . $program . '/lessons';
	$row        = array(
		'program'      => $program,
		'lessons'      => 0,
		'placeholders' => array(),
		'tiny'         => array(),
		'ok'           => array(),
	);

	if ( ! is_dir( $lesson_dir ) ) {
		$row['placeholders'][] = '(missing lessons directory)';
		$reports[]             = $row;
		++$failed;
		continue;
	}

	$files = glob( $lesson_dir . '/wb*.html' );
	if ( ! $files ) {
		$files = glob( $lesson_dir . '/*.html' );
	}
	$row['lessons'] = count( (array) $files );

	foreach ( (array) $files as $file ) {
		$base = basename( $file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$html = (string) file_get_contents( $file );
		$size = strlen( $html );
		$hit  = array();
		foreach ( $patterns as $needle ) {
			if ( false !== stripos( $html, $needle ) ) {
				$hit[] = $needle;
			}
		}
		if ( $hit ) {
			$row['placeholders'][] = $base . ' [' . implode( ', ', array_unique( $hit ) ) . ']';
			++$failed;
			continue;
		}
		// Workbook bodies should be real content, not tiny shells.
		if ( preg_match( '/^wb\d{2}\.html$/i', $base ) && $size < 15000 ) {
			$row['tiny'][] = $base . ' (' . $size . ' bytes)';
			++$failed;
			continue;
		}
		$row['ok'][] = $base . ' (' . $size . ' bytes)';
		++$passed;
	}

	$reports[] = $row;
}

// CE course-image placeholder (learner-visible catalog art).
$suicide_img = $root . '/assets/course-images/CTA_Suicide_Risk_Course_Image.png';
$ce_note     = '';
if ( ! is_readable( $suicide_img ) ) {
	$ce_note = 'CE WARNING: Advanced Suicide Risk approved course image PNG missing from assets/course-images/.';
}

echo "Learner-facing workbook/lesson audit\n";
echo str_repeat( '=', 72 ) . "\n";
foreach ( $reports as $row ) {
	$status = empty( $row['placeholders'] ) && empty( $row['tiny'] ) ? 'PASS' : 'FAIL';
	echo "{$status}  {$row['program']}  lessons={$row['lessons']}\n";
	foreach ( $row['placeholders'] as $p ) {
		echo "  PLACEHOLDER: {$p}\n";
	}
	foreach ( $row['tiny'] as $t ) {
		echo "  TOO SMALL: {$t}\n";
	}
}
echo str_repeat( '-', 72 ) . "\n";
echo "Checked OK files: {$passed}\n";
if ( $ce_note ) {
	echo $ce_note . "\n";
}
echo "\nRule: never report content population complete until this script passes\n";
echo "and a learner-facing Workbook 1 page has been opened in the course player.\n";

exit( $failed > 0 ? 1 : 0 );
