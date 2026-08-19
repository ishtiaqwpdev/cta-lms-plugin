<?php
/**
 * Scan Form B v2.0 controlled answer-key tables.
 * CLI only.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$xml  = $root . '/_tmp_lpcc_form_b_v2_key/unzip/word/document.xml';
$dom  = new DOMDocument();
$dom->loadXML( file_get_contents( $xml ) );
$xpath = new DOMXPath( $dom );
$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

$cell_text = static function ( DOMXPath $xpath, DOMNode $cell ) {
	$texts = $xpath->query( './/w:t', $cell );
	$line  = '';
	foreach ( $texts as $t ) {
		$line .= $t->textContent;
	}
	return trim( preg_replace( '/\s+/u', ' ', $line ) );
};

$tables = $xpath->query( '//w:tbl' );
echo 'table_count=' . $tables->length . "\n";
$ti = 0;
foreach ( $tables as $tbl ) {
	++$ti;
	$rows = $xpath->query( './w:tr', $tbl );
	$joined_rows = array();
	foreach ( $rows as $tr ) {
		$cells = $xpath->query( './w:tc', $tr );
		$vals  = array();
		foreach ( $cells as $tc ) {
			$vals[] = $cell_text( $xpath, $tc );
		}
		$joined_rows[] = implode( ' | ', $vals );
	}
	$blob = implode( ' ', $joined_rows );
	$preview = substr( preg_replace( '/\s+/', ' ', $blob ), 0, 220 );
	echo "TABLE {$ti}: rows=" . $rows->length . ' preview=' . $preview . "\n";
}
