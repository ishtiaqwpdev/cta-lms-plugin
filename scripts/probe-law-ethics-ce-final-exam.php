<?php
/**
 * Probe CTA-CE-001 final exam DOCX structure (CLI).
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$base = $root . '/_packages/CTA_CE_001_Law_Ethics_CE';

/**
 * @param string $dir Base dir.
 * @param string $filename Filename.
 * @return string
 */
function find_file( $dir, $filename ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
	foreach ( $it as $file ) {
		if ( $file->isFile() && $file->getFilename() === $filename ) {
			return $file->getPathname();
		}
	}
	return '';
}

/**
 * @param string $path DOCX path.
 * @return string[]
 */
function docx_lines( $path ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		throw new RuntimeException( 'Cannot open: ' . $path );
	}
	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();
	if ( false === $xml || '' === $xml ) {
		return array();
	}
	$dom = new DOMDocument();
	$dom->loadXML( $xml );
	$xpath = new DOMXPath( $dom );
	$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
	$out   = array();
	foreach ( $xpath->query( '//w:p' ) as $p ) {
		$t = '';
		foreach ( $xpath->query( './/w:t', $p ) as $node ) {
			$t .= $node->textContent;
		}
		$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
		if ( '' !== $t ) {
			$out[] = $t;
		}
	}
	return $out;
}

$learner_path = find_file( $base, 'CTA_CaliforniaLawEthics_Final_Exam_Learner_REVISED_v2.1.docx' );
$rat_path     = find_file( $base, 'CTA_California_Law_Ethics_Final_Exam_Detailed_Rationales_v2.1.docx' );

echo "Learner: {$learner_path}\n";
echo "Rationales: {$rat_path}\n\n";

$learner = docx_lines( $learner_path );
echo 'Learner lines: ' . count( $learner ) . "\n";
for ( $i = 0; $i < min( 120, count( $learner ) ); $i++ ) {
	echo ( $i + 1 ) . ': ' . $learner[ $i ] . "\n";
}

$rat = docx_lines( $rat_path );
echo 'Rationales lines: ' . count( $rat ) . "\n";

echo "\n--- Rationales Q1 section ---\n";
for ( $i = 130; $i < min( 210, count( $rat ) ); $i++ ) {
	echo ( $i + 1 ) . ': ' . $rat[ $i ] . "\n";
}
