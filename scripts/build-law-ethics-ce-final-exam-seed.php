<?php
/**
 * Build CTA-CE-001 final examination quiz seed from official Aug 2026 DOCX sources.
 *
 * Usage (repo root):
 *   C:\xampp\php\php.exe scripts/build-law-ethics-ce-final-exam-seed.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$base = $root . '/_packages/CTA_CE_001_Law_Ethics_CE';

/**
 * @param string $dir Directory.
 * @param string $filename Filename.
 * @return string
 */
function cta_find_package_file( $dir, $filename ) {
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
function cta_docx_lines( $path ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		throw new RuntimeException( 'Cannot open DOCX: ' . $path );
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

/**
 * @param string[] $lines Learner DOCX lines.
 * @return array<int,array{question_text:string,option_a:string,option_b:string,option_c:string,option_d:string}>
 */
function cta_parse_learner_exam( array $lines ) {
	$questions = array();
	$current   = null;

	foreach ( $lines as $line ) {
		if ( preg_match( '/^(\d+)\.\s+(.+)$/', $line, $m ) ) {
			if ( $current ) {
				$questions[ (int) $current['num'] ] = $current;
			}
			$current = array(
				'num'            => (int) $m[1],
				'question_text'  => trim( $m[2] ),
				'option_a'       => '',
				'option_b'       => '',
				'option_c'       => '',
				'option_d'       => '',
				'correct_option' => '',
				'explanation'    => '',
			);
			continue;
		}
		if ( ! $current ) {
			continue;
		}
		if ( preg_match( '/^([A-D])\.\s+(.+)$/', $line, $m ) ) {
			$key = 'option_' . strtolower( $m[1] );
			$current[ $key ] = trim( $m[2] );
		}
	}
	if ( $current ) {
		$questions[ (int) $current['num'] ] = $current;
	}

	ksort( $questions );
	return array_values( $questions );
}

/**
 * @param string[] $lines Rationales DOCX lines.
 * @return array<int,array{correct:string,explanation:string}>
 */
function cta_parse_rationales_exam( array $lines ) {
	$meta = array();
	$count = count( $lines );

	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! preg_match( '/^QUESTION\s+(\d+)$/i', $lines[ $i ], $m ) ) {
			continue;
		}
		$num = (int) $m[1];
		$correct = '';
		$parts   = array();
		$takeaway = '';
		$source   = '';

		for ( $j = $i + 1; $j < $count; $j++ ) {
			if ( $j > $i + 1 && preg_match( '/^QUESTION\s+\d+$/i', $lines[ $j ] ) ) {
				break;
			}
			$line = $lines[ $j ];
			if ( preg_match( '/^Correct Answer:\s*([A-D])$/i', $line, $cm ) ) {
				$correct = strtolower( $cm[1] );
				continue;
			}
			if ( 0 === strcasecmp( $line, 'CLINICAL TAKEAWAY' ) ) {
				$takeaway = isset( $lines[ $j + 1 ] ) ? trim( (string) $lines[ $j + 1 ] ) : '';
				continue;
			}
			if ( 0 === strncmp( $line, 'Source Alignment:', 17 ) ) {
				$source = trim( substr( $line, 17 ) );
				continue;
			}
			if ( preg_match( '/^Option\s+([A-D])$/i', $line, $om ) ) {
				$label = strtoupper( $om[1] );
				$body  = isset( $lines[ $j + 1 ] ) ? trim( (string) $lines[ $j + 1 ] ) : '';
				if ( '' !== $body ) {
					if ( 0 === stripos( $body, 'Correct.' ) ) {
						$parts[] = 'Why ' . $label . ' is correct: ' . trim( preg_replace( '/^Correct\.\s*/i', '', $body ) );
					} else {
						$parts[] = 'Why ' . $label . ' is incorrect: ' . trim( preg_replace( '/^Incorrect\.\s*/i', '', $body ) );
					}
				}
			}
		}

		$explanation = implode( "\n\n", array_filter( $parts ) );
		if ( '' !== $takeaway ) {
			$explanation .= ( '' !== $explanation ? "\n\n" : '' ) . 'CTA Clinical Takeaway: ' . $takeaway;
		}
		if ( '' !== $source ) {
			$explanation .= ( '' !== $explanation ? "\n\n" : '' ) . 'Source Alignment: ' . $source;
		}

		$meta[ $num ] = array(
			'correct'     => $correct,
			'explanation' => $explanation,
		);
	}

	return $meta;
}

/**
 * @param mixed $value Value.
 * @return string
 */
function cta_export_string( $value ) {
	$value = (string) $value;
	$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
	$value = str_replace(
		array( '\\', "'", "\n" ),
		array( '\\\\', "\\'", '\\n' ),
		$value
	);
	return $value;
}

$learner_path = cta_find_package_file( $base, 'CTA_CaliforniaLawEthics_Final_Exam_Learner_REVISED_v2.1.docx' );
$rat_path     = cta_find_package_file( $base, 'CTA_California_Law_Ethics_Final_Exam_Detailed_Rationales_v2.1.docx' );

if ( '' === $learner_path || '' === $rat_path ) {
	fwrite( STDERR, "Source DOCX not found under {$base}\n" );
	exit( 1 );
}

$learner_q = cta_parse_learner_exam( cta_docx_lines( $learner_path ) );
$rat_meta  = cta_parse_rationales_exam( cta_docx_lines( $rat_path ) );

if ( 25 !== count( $learner_q ) ) {
	fwrite( STDERR, 'Expected 25 learner questions, got ' . count( $learner_q ) . "\n" );
	exit( 1 );
}

$merged = array();
foreach ( $learner_q as $index => $row ) {
	$num = $index + 1;
	if ( empty( $rat_meta[ $num ]['correct'] ) ) {
		fwrite( STDERR, "Missing correct answer for question {$num}\n" );
		exit( 1 );
	}
	$row['correct_option'] = $rat_meta[ $num ]['correct'];
	$row['explanation']    = $rat_meta[ $num ]['explanation'];
	unset( $row['num'] );
	$merged[] = $row;
}

$out_path = $root . '/includes/quiz-seeds/law-ethics-final-exam.php';
$php      = "<?php\n";
$php     .= "/**\n";
$php     .= " * Official California Law & Ethics (CTA-CE-001) final examination.\n";
$php     .= " *\n";
$php     .= " * Generated from:\n";
$php     .= " * - CTA_CaliforniaLawEthics_Final_Exam_Learner_REVISED_v2.1.docx\n";
$php     .= " * - CTA_California_Law_Ethics_Final_Exam_Detailed_Rationales_v2.1.docx\n";
$php     .= " *\n";
$php     .= " * @package CTA_LMS\n";
$php     .= " */\n\n";
$php     .= "/**\n * @return array[] */\n";
$php     .= "return array(\n";

foreach ( $merged as $row ) {
	$php .= "\tarray(\n";
	foreach ( array( 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation' ) as $key ) {
		$php .= "\t\t'" . $key . "' => '" . cta_export_string( $row[ $key ] ?? '' ) . "',\n";
	}
	$php .= "\t),\n";
}

$php .= ");\n";

file_put_contents( $out_path, $php );
echo "Wrote {$out_path} (" . count( $merged ) . " questions)\n";
