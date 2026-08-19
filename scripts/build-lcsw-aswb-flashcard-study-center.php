<?php
/**
 * Build LCSW ASWB Clinical Flashcard Study Center JSON from approved xlsx export.
 *
 * Usage:
 *   php scripts/build-lcsw-aswb-flashcard-study-center.php --source=path/to/export.xlsx
 *   php scripts/build-lcsw-aswb-flashcard-study-center.php --source=... --dry-run
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root    = dirname( __DIR__ );
$options = getopt( '', array( 'source:', 'dry-run', 'output:' ) );
$source  = isset( $options['source'] ) ? (string) $options['source'] : '';
$dry_run = isset( $options['dry-run'] );
$output  = isset( $options['output'] )
	? (string) $options['output']
	: $root . '/assets/course-materials/lcsw-aswb/study-tools/flashcard-study-center.json';

if ( '' === $source || ! is_readable( $source ) ) {
	fwrite( STDERR, "Usage: php scripts/build-lcsw-aswb-flashcard-study-center.php --source=path/to/export.xlsx [--dry-run]\n" );
	exit( 1 );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return trim( preg_replace( '/[^a-z0-9_\-]+/', '-', $key ), '-' );
	}
}

/**
 * @param string $path XLSX path.
 * @param string $sheet_name Sheet name.
 * @return array<int,array<string,string>>
 */
function lcsw_fsc_read_xlsx_sheet( $path, $sheet_name ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		throw new RuntimeException( 'Cannot open xlsx: ' . $path );
	}

	$workbook_xml = $zip->getFromName( 'xl/workbook.xml' );
	$rels_xml     = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
	if ( false === $workbook_xml || false === $rels_xml ) {
		$zip->close();
		throw new RuntimeException( 'Missing workbook metadata in xlsx.' );
	}

	$wb = new DOMDocument();
	$wb->loadXML( $workbook_xml );
	$xpath = new DOMXPath( $wb );
	$xpath->registerNamespace( 'x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
	$xpath->registerNamespace( 'r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );

	$rid = '';
	foreach ( $xpath->query( '//x:sheets/x:sheet' ) as $sheet_node ) {
		if ( ! $sheet_node instanceof DOMElement ) {
			continue;
		}
		if ( $sheet_name === (string) $sheet_node->getAttribute( 'name' ) ) {
			$rid = (string) $sheet_node->getAttributeNS( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id' );
			break;
		}
	}

	if ( '' === $rid ) {
		$zip->close();
		throw new RuntimeException( 'Sheet not found: ' . $sheet_name );
	}

	$rels = new DOMDocument();
	$rels->loadXML( $rels_xml );
	$rx = new DOMXPath( $rels );
	$rx->registerNamespace( 'r', 'http://schemas.openxmlformats.org/package/2006/relationships' );

	$target = '';
	foreach ( $rx->query( '//r:Relationship' ) as $rel ) {
		if ( ! $rel instanceof DOMElement ) {
			continue;
		}
		if ( $rid === (string) $rel->getAttribute( 'Id' ) ) {
			$target = (string) $rel->getAttribute( 'Target' );
			break;
		}
	}

	if ( '' === $target ) {
		$zip->close();
		throw new RuntimeException( 'Sheet relationship missing for: ' . $sheet_name );
	}

	$target = ltrim( str_replace( '\\', '/', $target ), '/' );
	if ( 0 !== strpos( $target, 'xl/' ) ) {
		$target = 'xl/' . $target;
	}
	$sheet_path = $target;
	$sheet_xml  = $zip->getFromName( $sheet_path );
	$shared     = array();

	$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( false !== $shared_xml && '' !== $shared_xml ) {
		$sd = new DOMDocument();
		$sd->loadXML( $shared_xml );
		$sx = new DOMXPath( $sd );
		$sx->registerNamespace( 'x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
		foreach ( $sx->query( '//x:si' ) as $si ) {
			$parts = array();
			foreach ( $sx->query( './/x:t', $si ) as $t ) {
				$parts[] = $t->textContent;
			}
			$shared[] = implode( '', $parts );
		}
	}

	$zip->close();

	if ( false === $sheet_xml || '' === $sheet_xml ) {
		throw new RuntimeException( 'Empty sheet XML: ' . $sheet_path );
	}

	$doc = new DOMDocument();
	$doc->loadXML( $sheet_xml );
	$sxp = new DOMXPath( $doc );
	$sxp->registerNamespace( 'x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );

	$grid = array();
	foreach ( $sxp->query( '//x:sheetData/x:row' ) as $row ) {
		if ( ! $row instanceof DOMElement ) {
			continue;
		}
		$rnum = (int) $row->getAttribute( 'r' );
		foreach ( $sxp->query( './x:c', $row ) as $cell ) {
			if ( ! $cell instanceof DOMElement ) {
				continue;
			}
			$ref = (string) $cell->getAttribute( 'r' );
			if ( ! preg_match( '/^([A-Z]+)(\d+)$/', $ref, $m ) ) {
				continue;
			}
			$col = lcsw_fsc_col_letters_to_index( $m[1] );
			$val = lcsw_fsc_cell_value( $cell, $shared );
			$grid[ $rnum ][ $col ] = $val;
		}
	}

	if ( empty( $grid ) ) {
		return array();
	}

	ksort( $grid );
	$header_row = reset( $grid );
	if ( ! is_array( $header_row ) ) {
		return array();
	}

	$headers = array();
	foreach ( $header_row as $col => $label ) {
		$headers[ $col ] = trim( (string) $label );
	}

	$rows = array();
	foreach ( $grid as $rnum => $cells ) {
		if ( 1 === $rnum ) {
			continue;
		}
		$row = array();
		foreach ( $headers as $col => $header ) {
			$row[ $header ] = isset( $cells[ $col ] ) ? (string) $cells[ $col ] : '';
		}
		if ( '' === trim( implode( '', $row ) ) ) {
			continue;
		}
		$rows[] = $row;
	}

	return $rows;
}

/**
 * @param string $letters Column letters.
 * @return int 1-based column index.
 */
function lcsw_fsc_col_letters_to_index( $letters ) {
	$letters = strtoupper( (string) $letters );
	$num     = 0;
	$len     = strlen( $letters );
	for ( $i = 0; $i < $len; $i++ ) {
		$num = $num * 26 + ( ord( $letters[ $i ] ) - 64 );
	}
	return $num;
}

/**
 * @param DOMElement $cell Cell node.
 * @param array      $shared Shared strings.
 * @return string
 */
function lcsw_fsc_cell_value( DOMElement $cell, array $shared ) {
	$type = (string) $cell->getAttribute( 't' );
	if ( 'inlineStr' === $type ) {
		$parts = array();
		foreach ( $cell->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't' ) as $t ) {
			$parts[] = $t->textContent;
		}
		return implode( '', $parts );
	}

	$v_nodes = $cell->getElementsByTagNameNS( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'v' );
	if ( 0 === $v_nodes->length ) {
		return '';
	}
	$raw = (string) $v_nodes->item( 0 )->textContent;
	if ( 's' === $type ) {
		$idx = (int) $raw;
		return isset( $shared[ $idx ] ) ? (string) $shared[ $idx ] : '';
	}
	return $raw;
}

try {
	$sheet_rows = lcsw_fsc_read_xlsx_sheet( $source, 'Flashcards' );
} catch ( Throwable $e ) {
	fwrite( STDERR, $e->getMessage() . PHP_EOL );
	exit( 1 );
}

if ( 180 !== count( $sheet_rows ) ) {
	fwrite( STDERR, 'Expected 180 flashcard rows; found ' . count( $sheet_rows ) . PHP_EOL );
	exit( 1 );
}

$domain_order = array();
$domain_map   = array();
$cards        = array();

foreach ( $sheet_rows as $index => $row ) {
	$card_id     = trim( (string) ( $row['Card ID'] ?? '' ) );
	$domain_code = trim( (string) ( $row['Domain'] ?? '' ) );
	$domain_name = trim( (string) ( $row['Domain Name'] ?? '' ) );
	$front       = (string) ( $row['Front'] ?? '' );
	$back        = (string) ( $row['Back'] ?? '' );
	$memory_cue  = trim( (string) ( $row['Memory Cue'] ?? '' ) );

	if ( '' === $card_id || '' === $front || '' === $back ) {
		fwrite( STDERR, 'Row ' . ( $index + 2 ) . ' missing Card ID, Front, or Back.' . PHP_EOL );
		exit( 1 );
	}

	$domain_key = sanitize_key( $domain_name !== '' ? $domain_name : $domain_code );
	if ( '' === $domain_key ) {
		fwrite( STDERR, 'Row ' . ( $index + 2 ) . ' missing domain.' . PHP_EOL );
		exit( 1 );
	}

	if ( ! isset( $domain_map[ $domain_key ] ) ) {
		$domain_map[ $domain_key ] = array(
			'key'   => $domain_key,
			'label' => $domain_name !== '' ? $domain_name : $domain_code,
			'order' => count( $domain_order ) + 1,
		);
		$domain_order[] = $domain_key;
	}

	$card = array(
		'id'           => $card_id,
		'sort_order'   => $index + 1,
		'domain'       => $domain_key,
		'domain_label' => $domain_name !== '' ? $domain_name : $domain_code,
		'front'        => $front,
		'back'         => $back,
		'meta'         => array(
			'domain_code'     => $domain_code,
			'visibility'      => 'Learner',
			'active'          => true,
			'content_version' => '1.0',
		),
	);

	if ( '' !== $memory_cue ) {
		$card['memory_cue'] = $memory_cue;
	}

	$cards[] = $card;
}

$payload = array(
	'program'        => 'lcsw-aswb',
	'title'          => 'LCSW ASWB Clinical — Flashcard Study Center',
	'version'        => '1.0',
	'expected_total' => 180,
	'domains'        => array_values( $domain_map ),
	'cards'          => $cards,
);

$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( false === $json ) {
	fwrite( STDERR, "JSON encode failed.\n" );
	exit( 1 );
}

echo 'Cards: ' . count( $cards ) . PHP_EOL;
echo 'Domains: ' . count( $domain_map ) . PHP_EOL;
echo 'First card: ' . $cards[0]['id'] . ' | ' . substr( $cards[0]['front'], 0, 60 ) . '...' . PHP_EOL;
echo 'Last card: ' . $cards[ count( $cards ) - 1 ]['id'] . ' | ' . substr( $cards[ count( $cards ) - 1 ]['front'], 0, 60 ) . '...' . PHP_EOL;

if ( $dry_run ) {
	echo "Dry run — no file written.\n";
	exit( 0 );
}

file_put_contents( $output, $json . PHP_EOL );
echo 'Wrote ' . $output . PHP_EOL;
