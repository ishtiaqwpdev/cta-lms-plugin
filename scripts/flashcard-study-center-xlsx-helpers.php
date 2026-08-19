<?php
/**
 * Shared XLSX helpers for Flashcard Study Center build scripts.
 *
 * @package CTA_LMS
 */

if ( ! function_exists( 'cta_fsc_read_xlsx_sheet' ) ) {

/**
 * @param string $path XLSX path.
 * @param string $sheet_name Sheet name.
 * @return array<int,array<string,string>>
 */
function cta_fsc_read_xlsx_sheet( $path, $sheet_name ) {
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
			$col = cta_fsc_col_letters_to_index( $m[1] );
			$val = cta_fsc_cell_value( $cell, $shared );
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
function cta_fsc_col_letters_to_index( $letters ) {
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
function cta_fsc_cell_value( DOMElement $cell, array $shared ) {
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

}
