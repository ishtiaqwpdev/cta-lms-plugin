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
	'lpcc-ncmhce'     => $root . '/assets/course-materials/lpcc-ncmhce/workbooks',
	'lcsw-aswb'       => $root . '/assets/course-materials/lcsw-aswb/workbooks',
	'lmft-clinical'   => $root . '/assets/course-materials/lmft-clinical/workbooks',
	'lmft-amftrb'     => $root . '/assets/course-materials/lmft-amftrb/workbooks',
	'lpcc-law-ethics' => $root . '/assets/course-materials/lpcc-law-ethics/workbooks',
	'lcsw-law-ethics' => $root . '/assets/course-materials/lcsw-law-ethics/workbooks',
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
	$out = preg_replace( "/\n{3,}/", "\n\n", $out );
	return trim( (string) $out );
}

/**
 * Whether a Word boolean property is effectively on.
 *
 * @param DOMElement $r Run element.
 * @param DOMXPath   $xpath XPath.
 * @param string     $prop Local name (b|i|u).
 * @return bool
 */
function cta_docx_run_has_prop( DOMElement $r, DOMXPath $xpath, $prop ) {
	$nodes = $xpath->query( './w:rPr/w:' . $prop, $r );
	if ( ! $nodes || ! $nodes->length ) {
		return false;
	}
	$el = $nodes->item( 0 );
	if ( ! $el instanceof DOMElement ) {
		return false;
	}
	$val = $el->getAttributeNS( 'http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val' );
	if ( '' === $val ) {
		$val = $el->getAttribute( 'w:val' );
	}
	if ( '' === $val ) {
		return true;
	}
	$val = strtolower( (string) $val );
	return ! in_array( $val, array( '0', 'false', 'off' ), true );
}

/**
 * Decide if a space is missing between adjacent Word text runs.
 *
 * @param string $prev Previous text.
 * @param string $next Next text.
 * @return bool
 */
function cta_docx_needs_space_between( $prev, $next ) {
	if ( '' === $prev || '' === $next ) {
		return false;
	}
	if ( preg_match( '/\s$/u', $prev ) || preg_match( '/^\s/u', $next ) ) {
		return false;
	}
	// Punctuation that normally needs a following space.
	if ( preg_match( '/[,;:]$/u', $prev ) && preg_match( '/^[A-Za-z0-9]/u', $next ) ) {
		return true;
	}
	// ALL-CAPS / banner label → Title Case or sentence body.
	if ( preg_match( '/[A-Z]{2,}[A-Z0-9\/&\-]*$/u', $prev ) && preg_match( '/^[A-Z][a-z]/u', $next ) ) {
		return true;
	}
	if ( preg_match( '/[A-Z]{2,}$/u', $prev ) && preg_match( '/^[a-z]{2,}/u', $next ) ) {
		return true;
	}
	// TitleCaseWordNextWord (e.g. EthicsExam, ProgramStudent).
	if ( preg_match( '/[a-z]$/u', $prev ) && preg_match( '/^[A-Z][a-z]/u', $next ) ) {
		return true;
	}
	// Digit/letter boundary: 2026Version, 1Pass.
	if ( preg_match( '/[0-9]$/u', $prev ) && preg_match( '/^[A-Za-z]/u', $next ) ) {
		return true;
	}
	if ( preg_match( '/[A-Za-z]$/u', $prev ) && preg_match( '/^[0-9]/u', $next ) ) {
		return true;
	}
	return false;
}

/**
 * Collect visible plain text from an element, preserving intentional spaces.
 *
 * @param DOMElement $el Element.
 * @param DOMXPath   $xpath XPath.
 * @return string
 */
function cta_docx_element_text( DOMElement $el, DOMXPath $xpath ) {
	$out   = '';
	$nodes = $xpath->query( './/w:t | .//w:br | .//w:tab', $el );
	if ( ! $nodes ) {
		return '';
	}

	foreach ( $nodes as $node ) {
		if ( ! $node instanceof DOMElement ) {
			continue;
		}
		$local = $node->localName;
		if ( 'br' === $local || 'tab' === $local ) {
			$piece = ' ';
		} else {
			$piece = (string) $node->textContent;
		}

		if ( '' === $piece ) {
			continue;
		}

		if ( '' !== $out && cta_docx_needs_space_between( $out, $piece ) ) {
			$out .= ' ';
		}
		$out .= $piece;
	}

	return $out;
}

/**
 * Inline HTML for a paragraph's runs (bold/italic preserved).
 *
 * @param DOMElement $p Paragraph.
 * @param DOMXPath   $xpath XPath.
 * @return string Escaped inline HTML.
 */
function cta_docx_inline_html( DOMElement $p, DOMXPath $xpath ) {
	$html = '';
	$runs = $xpath->query( './w:r', $p );
	if ( ! $runs || ! $runs->length ) {
		// Fallback for unusual structures.
		$text = trim( preg_replace( '/\s+/u', ' ', cta_docx_element_text( $p, $xpath ) ) );
		return '' === $text ? '' : htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	$plain_acc = '';
	foreach ( $runs as $r ) {
		if ( ! $r instanceof DOMElement ) {
			continue;
		}
		$piece = '';
		foreach ( $xpath->query( './w:t | ./w:br | ./w:tab', $r ) as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			if ( 'br' === $node->localName || 'tab' === $node->localName ) {
				$piece .= ' ';
			} else {
				$piece .= (string) $node->textContent;
			}
		}
		if ( '' === $piece ) {
			continue;
		}
		if ( '' !== $plain_acc && cta_docx_needs_space_between( $plain_acc, $piece ) ) {
			$html     .= ' ';
			$plain_acc .= ' ';
		}
		$plain_acc .= $piece;

		$esc = htmlspecialchars( $piece, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		if ( cta_docx_run_has_prop( $r, $xpath, 'b' ) ) {
			$esc = '<strong>' . $esc . '</strong>';
		}
		if ( cta_docx_run_has_prop( $r, $xpath, 'i' ) ) {
			$esc = '<em>' . $esc . '</em>';
		}
		$html .= $esc;
	}

	return trim( preg_replace( '/\s+/u', ' ', $html ) );
}

/**
 * Split banner-style ALL-CAPS label from following body for callout cells.
 *
 * @param string $text Plain text.
 * @return string Text with "\n" between label and body when appropriate.
 */
function cta_docx_split_banner_label( $text ) {
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	if ( '' === $text || false !== strpos( $text, "\n" ) ) {
		return $text;
	}

	// Leading ALL-CAPS banner words only (do not greedily scan into the body).
	// e.g. "STUDENT-FACING … MATERIAL All practice…" or "WELCOME This program…".
	if ( ! preg_match(
		'/^((?:[A-Z]{2,}[A-Z0-9\-\/]*(?:[ ,:&]+[A-Z]{2,}[A-Z0-9\-\/]*)*))\s+([A-Z][a-z][\s\S]+)$/u',
		$text,
		$m
	) ) {
		return $text;
	}

	$label   = trim( $m[1] );
	$body    = trim( $m[2] );
	$letters = preg_replace( '/[^A-Za-z]/u', '', $label );
	$lower   = preg_replace( '/[^a-z]/u', '', $letters );
	if ( strlen( $letters ) < 5 || '' === $body ) {
		return $text;
	}
	if ( '' !== $lower && ( strlen( $lower ) / strlen( $letters ) ) >= 0.2 ) {
		return $text;
	}

	return $label . "\n" . $body;
}

/**
 * @param DOMElement $p Paragraph.
 * @param DOMXPath   $xpath XPath.
 * @return string
 */
function cta_docx_paragraph_html( DOMElement $p, DOMXPath $xpath ) {
	$style       = '';
	$style_nodes = $xpath->query( './w:pPr/w:pStyle/@w:val', $p );
	if ( $style_nodes && $style_nodes->length ) {
		$style = (string) $style_nodes->item( 0 )->nodeValue;
	}

	$inline = cta_docx_inline_html( $p, $xpath );
	if ( '' === $inline ) {
		return '';
	}

	$style_l = strtolower( $style );

	if ( false !== strpos( $style_l, 'heading1' ) || 'title' === $style_l ) {
		return '<h2 class="cta-lesson-h2">' . $inline . '</h2>';
	}
	if ( false !== strpos( $style_l, 'heading2' ) ) {
		return '<h3 class="cta-lesson-h3">' . $inline . '</h3>';
	}
	if ( false !== strpos( $style_l, 'heading3' ) || false !== strpos( $style_l, 'heading4' ) ) {
		return '<h4 class="cta-lesson-h4">' . $inline . '</h4>';
	}
	if ( false !== strpos( $style_l, 'list' ) || $xpath->query( './w:pPr/w:numPr', $p )->length ) {
		return '<li class="cta-lesson-li">' . $inline . '</li>';
	}

	return '<p class="cta-lesson-p">' . $inline . '</p>';
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

	$parts      = array( '<div class="cta-lesson-table-wrap"><table class="cta-lesson-table"><tbody>' );
	$row_index  = 0;
	$headerish  = false;

	// Peek first row: treat as header when every cell is short / label-like.
	$first_cells = $xpath->query( './w:tr[1]/w:tc', $tbl );
	if ( $first_cells && $first_cells->length > 1 ) {
		$looks_header = true;
		foreach ( $first_cells as $tc ) {
			$t = trim( preg_replace( '/\s+/u', ' ', cta_docx_element_text( $tc, $xpath ) ) );
			if ( '' === $t || strlen( $t ) > 80 || preg_match( '/[.!?].+[A-Za-z]/u', $t ) ) {
				$looks_header = false;
				break;
			}
		}
		$headerish = $looks_header;
	}

	foreach ( $rows as $tr ) {
		$parts[] = '<tr>';
		$cells   = $xpath->query( './w:tc', $tr );
		$tag     = ( 0 === $row_index && $headerish ) ? 'th' : 'td';
		foreach ( $cells as $tc ) {
			$cell_bits = array();
			foreach ( $xpath->query( './w:p', $tc ) as $p ) {
				$plain = trim( preg_replace( '/\s+/u', ' ', cta_docx_element_text( $p, $xpath ) ) );
				if ( '' === $plain ) {
					continue;
				}
				$plain = cta_docx_split_banner_label( $plain );
				$lines = preg_split( "/\n+/", $plain );
				$line_html = array();
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( '' !== $line ) {
						$line_html[] = htmlspecialchars( $line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
					}
				}
				if ( $line_html ) {
					$cell_bits[] = implode( '<br>', $line_html );
				}
			}
			$parts[] = '<' . $tag . '>' . ( $cell_bits ? implode( '<br>', $cell_bits ) : '&nbsp;' ) . '</' . $tag . '>';
		}
		$parts[] = '</tr>';
		$row_index++;
	}
	$parts[] = '</tbody></table></div>';
	return implode( '', $parts );
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

/**
 * Write a lesson HTML file.
 *
 * @param string $out_path Output path.
 * @param string $program Program key.
 * @param int    $num Workbook number (0 = start-here).
 * @param string $html Inner HTML.
 * @param string $source_note Optional source comment.
 * @return void
 */
function cta_write_lesson_file( $out_path, $program, $num, $html, $source_note = '' ) {
	$html = cta_wrap_lists( $html );
	$label = $num > 0 ? ( 'workbook ' . (int) $num ) : 'start-here';
	$wrapped = "<!-- CTA Exam Prep lesson: {$program} {$label} -->\n";
	if ( '' !== $source_note ) {
		$wrapped .= '<!-- ' . str_replace( array( '-->', "\n" ), array( '', ' ' ), $source_note ) . " -->\n";
	}
	$wrapped .= '<article class="cta-lesson-article" data-program="' . htmlspecialchars( $program, ENT_QUOTES, 'UTF-8' ) . '" data-workbook="' . (int) $num . '">' . "\n"
		. $html . "\n</article>\n";
	file_put_contents( $out_path, $wrapped );
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
		$files = glob( $dir . '/*Candidate_Edition*.docx' );
	}
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
			if ( '' === trim( $html ) ) {
				throw new RuntimeException( 'Empty HTML' );
			}
			$out = sprintf( '%s/wb%02d.html', $out_dir, $num );
			cta_write_lesson_file( $out, $program, $num, $html );
			$converted++;
			echo "OK {$program} WB{$num} -> " . basename( $out ) . ' (' . filesize( $out ) . " bytes)\n";
		} catch ( Throwable $e ) {
			$failed[] = "{$program}/{$base}: " . $e->getMessage();
			fwrite( STDERR, "FAIL {$base}: " . $e->getMessage() . "\n" );
		}
	}

	// Law & Ethics Start Here orientation DOCX (if present).
	$start_dirs = array(
		dirname( $dir ) . '/start-here',
		$dir,
	);
	$start_file = null;
	foreach ( $start_dirs as $sd ) {
		if ( ! is_dir( $sd ) ) {
			continue;
		}
		$candidates = glob( $sd . '/*Start_Here*.docx' );
		if ( ! $candidates ) {
			$candidates = glob( $sd . '/*Start*Here*.docx' );
		}
		if ( $candidates ) {
			// Prefer orientation over license-specific modules.
			usort(
				$candidates,
				static function ( $a, $b ) {
					$as = stripos( basename( $a ), 'Orientation' ) !== false ? 0 : 1;
					$bs = stripos( basename( $b ), 'Orientation' ) !== false ? 0 : 1;
					return $as <=> $bs;
				}
			);
			$start_file = $candidates[0];
			break;
		}
	}

	if ( $start_file ) {
		try {
			$html = cta_docx_to_html( $start_file );
			if ( '' === trim( $html ) ) {
				throw new RuntimeException( 'Empty HTML' );
			}
			$out = $out_dir . '/start-here.html';
			cta_write_lesson_file( $out, $program, 0, $html, basename( $start_file ) );
			$converted++;
			echo "OK {$program} start-here -> start-here.html (" . filesize( $out ) . " bytes)\n";
		} catch ( Throwable $e ) {
			$failed[] = "{$program}/start-here: " . $e->getMessage();
			fwrite( STDERR, "FAIL start-here {$program}: " . $e->getMessage() . "\n" );
		}
	}
}

echo "\nConverted: {$converted}\n";
if ( $failed ) {
	echo "Issues:\n- " . implode( "\n- ", $failed ) . "\n";
	exit( $converted > 0 ? 0 : 1 );
}
