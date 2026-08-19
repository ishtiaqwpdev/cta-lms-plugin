<?php
/**
 * Extract paragraph text from Form B v2.0 controlled answer-key document.xml.
 * CLI only. Writes local tmp paras for admin parse — never a learner route.
 *
 * Usage: php scripts/_extract_lpcc_form_b_v2_key_paras.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$xml_path = dirname( __DIR__ ) . '/_tmp_lpcc_form_b_v2_key/unzip/word/document.xml';
if ( ! is_readable( $xml_path ) ) {
	fwrite( STDERR, "Missing {$xml_path}\n" );
	exit( 1 );
}

$xml = file_get_contents( $xml_path );
$xml = preg_replace( '/w:rsid[A-Za-z]*="[^"]*"/', '', $xml );
libxml_use_internal_errors( true );
$dom = new DOMDocument();
$dom->loadXML( $xml );
$xpath = new DOMXPath( $dom );
$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

$paras = $xpath->query( '//w:body/w:p' );
$out   = array();
foreach ( $paras as $p ) {
	$texts = $xpath->query( './/w:t', $p );
	$line  = '';
	foreach ( $texts as $t ) {
		$line .= $t->textContent;
	}
	$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
	$out[] = $line;
}

$dump = dirname( __DIR__ ) . '/_tmp_lpcc_form_b_v2_key/paras.txt';
file_put_contents( $dump, implode( "\n", $out ) );
echo 'paragraphs=' . count( $out ) . "\n";
echo 'nonempty=' . count( array_filter( $out, static function ( $l ) { return '' !== $l; } ) ) . "\n";
echo "wrote {$dump}\n";
