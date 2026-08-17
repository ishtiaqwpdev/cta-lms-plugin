<?php
/**
 * Extract tables + targeted metadata from Form A v2.0 answer-key XML.
 * Does not print answer letters or rationale bodies.
 *
 * Usage: php scripts/_scan_lpcc_form_a_v2_key_tables.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root     = dirname( __DIR__ );
$xml_path = $root . '/_tmp_lpcc_form_a_v2_key/unzip/word/document.xml';
$paras    = file( $root . '/_tmp_lpcc_form_a_v2_key/paras.txt', FILE_IGNORE_NEW_LINES );

$xml = file_get_contents( $xml_path );
$xml = preg_replace( '/w:rsid[A-Za-z]*="[^"]*"/', '', $xml );
libxml_use_internal_errors( true );
$dom = new DOMDocument();
$dom->loadXML( $xml );
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

echo "=== TABLES ===\n";
$tables = $xpath->query( '//w:tbl' );
echo 'table_count=' . $tables->length . "\n";
$ti = 0;
foreach ( $tables as $tbl ) {
	++$ti;
	$rows = $xpath->query( './w:tr', $tbl );
	echo "\n-- table {$ti} rows=" . $rows->length . " --\n";
	$ri = 0;
	foreach ( $rows as $tr ) {
		++$ri;
		$cells = $xpath->query( './w:tc', $tr );
		$vals  = array();
		foreach ( $cells as $tc ) {
			$vals[] = $cell_text( $xpath, $tc );
		}
		$joined = implode(' | ', $vals);
		// Redact final-key letter strings.
		$joined = preg_replace( '/\b[A-D]{11,15}\b/', '[KEY-REDACTED]', $joined );
		if ( strlen( $joined ) > 300 ) {
			$joined = substr( $joined, 0, 300 ) . '…';
		}
		echo "  r{$ri}: {$joined}\n";
	}
}

echo "\n=== CONTROLLED METADATA LINES (keys redacted) ===\n";
$meta_n = 0;
$keys_n = 0;
$key_lens = array();
foreach ( $paras as $i => $raw ) {
	$line = trim( (string) $raw );
	if ( preg_match( '/Controlled metadata/i', $line ) ) {
		++$meta_n;
		$red = preg_replace( '/Final key:\s*[A-D]+/i', 'Final key: [REDACTED]', $line );
		echo sprintf( "%4d: %s\n", $i + 1, $red );
	}
	if ( preg_match( '/Final key:\s*([A-D]+)/i', $line, $m ) ) {
		++$keys_n;
		$key_lens[] = strlen( $m[1] );
	}
}
echo "metadata_lines={$meta_n} final_key_strings={$keys_n} key_lengths=" . implode( ',', $key_lens ) . "\n";

echo "\n=== FIELD-TEST / SCORED / PASS LANGUAGE ===\n";
$re = '/field[-\s]?test|scored-style|passing|pass rate|cut score|threshold|70\s*%|percent correct|unscored|do not score|rationale release|after submission|review after|per-case|per case/i';
foreach ( $paras as $i => $raw ) {
	$line = trim( (string) $raw );
	if ( '' === $line || strlen( $line ) > 240 ) {
		continue;
	}
	if ( preg_match( $re, $line ) ) {
		$red = preg_replace( '/Final key:\s*[A-D]+/i', 'Final key: [REDACTED]', $line );
		echo sprintf( "%4d: %s\n", $i + 1, $red );
	}
}

echo "\n=== CASE 3 / 4 SECTION LINES ===\n";
$in = 0;
foreach ( $paras as $i => $raw ) {
	$line = trim( (string) $raw );
	if ( preg_match( '/^CASE\s+3\b/u', $line ) ) {
		$in = 3;
	} elseif ( preg_match( '/^CASE\s+4\b/u', $line ) ) {
		$in = 4;
	} elseif ( preg_match( '/^CASE\s+5\b/u', $line ) ) {
		break;
	}
	if ( $in && preg_match( '/^(CASE|SECTION)\b/u', $line ) ) {
		echo sprintf( "%4d: %s\n", $i + 1, $line );
	}
}
