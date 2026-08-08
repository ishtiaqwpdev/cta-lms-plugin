<?php
/**
 * Convert Exam Prep student workbook DOCX files to readable HTML lessons.
 *
 * Usage (repo root):
 *   C:\xampp\php\php.exe scripts/convert-workbook-docx-to-html.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );

$programs = array(
	'lpcc-ncmhce'      => $root . '/assets/course-materials/lpcc-ncmhce/workbooks',
	'lcsw-aswb'        => $root . '/assets/course-materials/lcsw-aswb/workbooks',
	'lmft-clinical'    => $root . '/assets/course-materials/lmft-clinical/workbooks',
	'lmft-amftrb'      => $root . '/assets/course-materials/lmft-amftrb/workbooks',
	'lpcc-law-ethics'  => $root . '/assets/course-materials/lpcc-law-ethics/workbooks',
	'lcsw-law-ethics'  => $root . '/assets/course-materials/lcsw-law-ethics/workbooks',
);

/**
 * Convert one DOCX to sanitized HTML fragment.
 *
 * @param string $docx_path Absolute path.
 * @return string HTML.
 */
function cta_docx_to_html( $docx_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		throw new RuntimeException( 'ZipArchive required' );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $docx_path ) ) {
		throw new RuntimeException( 'Cannot open: ' . $docx_path );
	}

	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();

	if ( false === $xml || '' === $xml ) {
		throw new RuntimeException( 'Missing document.xml: ' . $docx_path );
	}

	$xml = preg_replace( '/w:rsid[A-Za-z]*="[^"]*"/', '', $xml );
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadXML( $xml );
	$xpath = new DOMXPath( $dom );
	$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

	$body = $xpath->query( '//w:body' )->item( 0 );
	if ( ! $body ) {
		return '';
	}

	$html = array();
	foreach ( $body->childNodes as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}
		$local = $node->localName;
		if ( 'p' === $local ) {
			$block = cta_docx_paragraph_html( $node, $xpath );
			if ( '' !== $block ) {
				$html[] = $block;
			}
		} elseif ( 'tbl' === $local ) {
			$table = cta_docx_table_html( $node, $xpath );
			if ( '' !== $table ) {
				$html[] = $table;
			}
		}
	}

	$out = implode( "\n", $html );
	// Collapse excessive empties.
	$out = preg_replace( "/\n{3,}/", "\n\n", $out );
	return trim( (string) $out );
}

/**
 * @param DOMElement $p Paragraph.
 * @param DOMXPath   $xpath XPath.
 * @return string
 */
function cta_docx_paragraph_html( DOMElement $p, DOMXPath $xpath ) {
	$style = '';
	$style_nodes = $xpath->query( './w:pPr/w:pStyle/@w:val', $p );
	if ( $style_nodes && $style_nodes->length ) {
		$style = (string) $style_nodes->item( 0 )->nodeValue;
	}

	$text = cta_docx_element_text( $p, $xpath );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	if ( '' === $text ) {
		return '';
	}

	$escaped = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	$style_l = strtolower( $style );

	if ( false !== strpos( $style_l, 'heading1' ) || 'title' === $style_l ) {
		return '<h2 class="cta-lesson-h2">' . $escaped . '</h2>';
	}
	if ( false !== strpos( $style_l, 'heading2' ) ) {
		return '<h3 class="cta-lesson-h3">' . $escaped . '</h3>';
	}
	if ( false !== strpos( $style_l, 'heading3' ) || false !== strpos( $style_l, 'heading4' ) ) {
		return '<h4 class="cta-lesson-h4">' . $escaped . '</h4>';
	}
	if ( false !== strpos( $style_l, 'list' ) || $xpath->query( './w:pPr/w:numPr', $p )->length ) {
		return '<li class="cta-lesson-li">' . $escaped . '</li>';
	}

	return '<p class="cta-lesson-p">' . $escaped . '</p>';
}

/**
 * @param DOMElement $tbl Table.
 * @param DOMXPath   $xpath XPath.
 * @return string
 */
function cta_docx_table_html( DOMElement $tbl, DOMXPath $xpath ) {
	$rows = $xpath->query( './w:tr', $tbl );
	if ( ! $rows || ! $rows->length ) {
		return '';
	}

	$parts = array( '<table class="cta-lesson-table"><tbody>' );
	foreach ( $rows as $tr ) {
		$parts[] = '<tr>';
		$cells = $xpath->query( './w:tc', $tr );
		foreach ( $cells as $tc ) {
			$cell_bits = array();
			foreach ( $xpath->query( './w:p', $tc ) as $p ) {
				$t = trim( preg_replace( '/\s+/u', ' ', cta_docx_element_text( $p, $xpath ) ) );
				if ( '' !== $t ) {
					$cell_bits[] = htmlspecialchars( $t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
				}
			}
			$parts[] = '<td>' . ( $cell_bits ? implode( '<br>', $cell_bits ) : '&nbsp;' ) . '</td>';
		}
		$parts[] = '</tr>';
	}
	$parts[] = '</tbody></table>';
	return implode( '', $parts );
}

/**
 * Collect visible text from an element.
 *
 * @param DOMElement $el Element.
 * @param DOMXPath   $xpath XPath.
 * @return string
 */
function cta_docx_element_text( DOMElement $el, DOMXPath $xpath ) {
	$chunks = array();
	foreach ( $xpath->query( './/w:t', $el ) as $t ) {
		$chunks[] = $t->textContent;
	}
	// Soft line breaks.
	foreach ( $xpath->query( './/w:br', $el ) as $br ) {
		$chunks[] = ' ';
	}
	return implode( '', $chunks );
}

/**
 * Wrap consecutive <li> into <ul>.
 *
 * @param string $html HTML.
 * @return string
 */
function cta_wrap_lists( $html ) {
	return preg_replace_callback(
		'/(?:<li class="cta-lesson-li">.*?<\/li>\s*)+/s',
		static function ( $m ) {
			return '<ul class="cta-lesson-ul">' . trim( $m[0] ) . '</ul>';
		},
		$html
	);
}

/**
 * Extract workbook number from filename.
 *
 * @param string $name Filename.
 * @return int
 */
function cta_workbook_num_from_name( $name ) {
	if ( preg_match( '/WB(\d{1,2})|_WB(\d{1,2})_/i', $name, $m ) ) {
		$n = ! empty( $m[1] ) ? $m[1] : $m[2];
		return (int) $n;
	}
	return 0;
}

$converted = 0;
$failed    = array();

foreach ( $programs as $program => $dir ) {
	if ( ! is_dir( $dir ) ) {
		fwrite( STDERR, "Missing dir: {$dir}\n" );
		continue;
	}

	$out_dir = dirname( $dir ) . '/lessons';
	if ( ! is_dir( $out_dir ) && ! mkdir( $out_dir, 0755, true ) && ! is_dir( $out_dir ) ) {
		fwrite( STDERR, "Cannot create: {$out_dir}\n" );
		continue;
	}

	$files = glob( $dir . '/*Student_Workbook*.docx' );
	if ( ! $files ) {
		$files = glob( $dir . '/*.docx' );
	}

	foreach ( (array) $files as $file ) {
		$base = basename( $file );
		$num  = cta_workbook_num_from_name( $base );
		if ( $num < 1 ) {
			$failed[] = "skip (no WB#): {$base}";
			continue;
		}

		try {
			$html = cta_docx_to_html( $file );
			$html = cta_wrap_lists( $html );
			if ( '' === trim( $html ) ) {
				throw new RuntimeException( 'Empty HTML' );
			}

			$wrapped = "<!-- CTA Exam Prep lesson: {$program} workbook {$num} -->\n"
				. '<article class="cta-lesson-article" data-program="' . htmlspecialchars( $program, ENT_QUOTES, 'UTF-8' ) . '" data-workbook="' . (int) $num . '">' . "\n"
				. $html . "\n</article>\n";

			$out = sprintf( '%s/wb%02d.html', $out_dir, $num );
			file_put_contents( $out, $wrapped );
			$converted++;
			echo "OK {$program} WB{$num} -> " . basename( $out ) . ' (' . strlen( $wrapped ) . " bytes)\n";
		} catch ( Throwable $e ) {
			$failed[] = "{$program}/{$base}: " . $e->getMessage();
			fwrite( STDERR, "FAIL {$base}: " . $e->getMessage() . "\n" );
		}
	}
}

echo "\nConverted: {$converted}\n";
if ( $failed ) {
	echo "Issues:\n- " . implode( "\n- ", $failed ) . "\n";
	exit( $converted > 0 ? 0 : 1 );
}
