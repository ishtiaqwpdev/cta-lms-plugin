<?php
/**
 * Build LMFT AMFTRB workbook practice-bank quiz seeds from approved DOCX banks.
 *
 * Candidate: assets/course-materials/lmft-amftrb/question-banks/CTA_LMFT_AMFTRB_WB{N}_17_Question_Candidate_Bank_v1.0.docx
 * Rationales: assets/course-materials/lmft-amftrb/rationales/ (Answer Key or Controlled Answer Key per workbook)
 *
 * Output: includes/quiz-seeds/lmft-amftrb-wb{N}-bank.php
 *
 * Usage: php scripts/build-lmft-amftrb-workbook-bank-seeds.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$base = $root . '/assets/course-materials/lmft-amftrb';
$out  = $root . '/includes/quiz-seeds';

/**
 * @param string $path DOCX path.
 * @return string[]
 */
function cta_amftrb_bank_docx_lines( $path ) {
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

	$lines = array();
	foreach ( $xpath->query( '//w:p' ) as $p ) {
		$t = '';
		foreach ( $xpath->query( './/w:t', $p ) as $node ) {
			$t .= $node->textContent;
		}
		$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
		if ( '' !== $t ) {
			$lines[] = $t;
		}
	}
	return $lines;
}

/**
 * @param int $wb Workbook number.
 * @return string
 */
function cta_amftrb_rationale_docx_path( $base, $wb ) {
	$wb = (int) $wb;
	$candidates = array(
		$base . '/rationales/CTA_LMFT_AMFTRB_WB' . $wb . '_17_Question_Answer_Key_and_Detailed_Rationales_v1.0.docx',
		$base . '/rationales/CTA_LMFT_AMFTRB_WB' . $wb . '_17_Question_Answer_Key_and_Detailed_Rationales_v1.1.docx',
		$base . '/rationales/CTA_LMFT_AMFTRB_WB' . $wb . '_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
	);
	foreach ( $candidates as $path ) {
		if ( is_readable( $path ) ) {
			return $path;
		}
	}
	throw new RuntimeException( 'Rationale DOCX not found for workbook ' . $wb );
}

/**
 * Extract A-D options from one or more merged DOCX lines.
 *
 * @param string[] $lines Lines.
 * @param int      $i     Current index (updated by reference).
 * @param int      $n     Line count.
 * @return array<string,string>
 */
function cta_amftrb_extract_options_from_lines( array $lines, &$i, $n ) {
	$blob = '';
	while ( $i < $n ) {
		if ( preg_match( '/^(My answer:|Answer:)/i', $lines[ $i ] ) ) {
			break;
		}
		if ( preg_match( '/^\d+\.\s/', $lines[ $i ] ) ) {
			break;
		}
		if ( preg_match( '/^Workbook \d+ Candidate/i', $lines[ $i ] ) ) {
			break;
		}
		$blob .= ' ' . $lines[ $i ];
		++$i;
	}

	$blob = trim( preg_replace( '/\s*Answer:.*$/i', '', $blob ) );
	$opts = array(
		'a' => '',
		'b' => '',
		'c' => '',
		'd' => '',
	);

	if ( preg_match_all( '/\b([A-D])\.\s+/', $blob, $marks, PREG_OFFSET_CAPTURE ) ) {
		$count = count( $marks[1] );
		for ( $j = 0; $j < $count; $j++ ) {
			$letter = strtolower( (string) $marks[1][ $j ][0] );
			$start  = (int) $marks[0][ $j ][1] + strlen( (string) $marks[0][ $j ][0] );
			$end    = isset( $marks[0][ $j + 1 ] ) ? (int) $marks[0][ $j + 1 ][1] : strlen( $blob );
			$opts[ $letter ] = trim( substr( $blob, $start, $end - $start ) );
		}
	}

	return $opts;
}

/**
 * @param string[] $lines Candidate bank lines.
 * @return array<int,array<string,string>>
 */
function cta_amftrb_parse_candidate_bank( array $lines ) {
	$questions = array();
	$in_block  = false;
	$i         = 0;
	$n         = count( $lines );

	while ( $i < $n ) {
		if ( preg_match( '/Candidate Questions|Candidate Bank/i', $lines[ $i ] ) ) {
			$in_block = true;
			++$i;
			continue;
		}

		if ( ! $in_block || ! preg_match( '/^(\d+)\.\s+(.+)$/', $lines[ $i ], $m ) ) {
			++$i;
			continue;
		}

		$num = (int) $m[1];
		if ( $num < 1 || $num > 17 ) {
			++$i;
			continue;
		}

		$stem = (string) $m[2];
		++$i;

		$opts = cta_amftrb_extract_options_from_lines( $lines, $i, $n );

		if ( $i < $n && preg_match( '/^(My answer:|Answer:)/i', $lines[ $i ] ) ) {
			++$i;
		}

		if ( '' === $stem || '' === $opts['a'] || '' === $opts['b'] || '' === $opts['c'] || '' === $opts['d'] ) {
			throw new RuntimeException( 'Incomplete candidate question ' . $num );
		}

		$questions[ $num ] = array(
			'question_text' => $stem,
			'option_a'      => $opts['a'],
			'option_b'      => $opts['b'],
			'option_c'      => $opts['c'],
			'option_d'      => $opts['d'],
		);
	}

	if ( 17 !== count( $questions ) ) {
		throw new RuntimeException( 'Expected 17 candidate questions; got ' . count( $questions ) );
	}

	ksort( $questions );
	return array_values( $questions );
}

/**
 * Parse one rationale block after OPTION-BY-OPTION header (legacy WB1-5 format).
 *
 * @param string[] $lines Lines.
 * @param int      $i     Index (updated by reference).
 * @param int      $n     Count.
 * @return array{rats:array<string,string>,strategy:string}
 */
function cta_amftrb_parse_legacy_option_rationales( array $lines, &$i, $n ) {
	$rats     = array();
	$strategy = '';

	foreach ( array( 'a', 'b', 'c', 'd' ) as $letter ) {
		if ( $i < $n && strtoupper( $letter ) === $lines[ $i ] ) {
			++$i;
		}
		if ( $i < $n && preg_match( '/^(CORRECT\.|Not best\.)/i', $lines[ $i ] ) ) {
			$rats[ $letter ] = $lines[ $i ];
			++$i;
		}
	}

	if ( $i < $n && preg_match( '/^CTA EXAM STRATEGY\s*(.*)$/i', $lines[ $i ], $sm ) ) {
		$strategy = trim( (string) $sm[1] );
		++$i;
	}

	return array(
		'rats'     => $rats,
		'strategy' => $strategy,
	);
}

/**
 * Parse one rationale block after Option-by-Option Rationales (controlled WB6-12 format).
 *
 * @param string[] $lines Lines.
 * @param int      $i     Index (updated by reference).
 * @param int      $n     Count.
 * @return array{rats:array<string,string>,strategy:string}
 */
function cta_amftrb_parse_controlled_option_rationales( array $lines, &$i, $n ) {
	$rats     = array();
	$strategy = '';

	while ( $i < $n && preg_match( '/^([A-D]):\s*(.+)$/', $lines[ $i ], $rm ) ) {
		$rats[ strtolower( $rm[1] ) ] = trim( (string) $rm[2] );
		++$i;
	}

	if ( $i < $n && preg_match( '/^CTA exam strategy:\s*(.*)$/i', $lines[ $i ], $sm ) ) {
		$strategy = trim( (string) $sm[1] );
		++$i;
	}

	return array(
		'rats'     => $rats,
		'strategy' => $strategy,
	);
}

/**
 * @param array<string,string> $rats     Option rationales.
 * @param string               $strategy Exam strategy line.
 * @return string
 */
function cta_amftrb_build_explanation( array $rats, $strategy ) {
	$exp_parts = array();
	foreach ( array( 'a', 'b', 'c', 'd' ) as $letter ) {
		if ( isset( $rats[ $letter ] ) ) {
			$exp_parts[] = strtoupper( $letter ) . '. ' . $rats[ $letter ];
		}
	}
	if ( '' !== $strategy ) {
		$exp_parts[] = 'CTA Exam Strategy: ' . $strategy;
	}
	return implode( "\n\n", $exp_parts );
}

/**
 * @param string[] $lines Rationale key lines.
 * @return array<int,array{correct_option:string,explanation:string}>
 */
function cta_amftrb_parse_rationale_bank( array $lines ) {
	$answers = array();
	$i       = 0;
	$n       = count( $lines );

	while ( $i < $n ) {
		if ( preg_match( '/^Question (\d+): Correct Answer ([A-D])/i', $lines[ $i ], $m ) ) {
			$num     = (int) $m[1];
			$correct = strtolower( (string) $m[2] );
			++$i;

			while ( $i < $n && ! preg_match( '/^OPTION-BY-OPTION RATIONALE/i', $lines[ $i ] ) ) {
				++$i;
			}
			if ( $i >= $n ) {
				break;
			}
			++$i;

			$parsed = cta_amftrb_parse_legacy_option_rationales( $lines, $i, $n );
			$answers[ $num ] = array(
				'correct_option' => $correct,
				'explanation'    => cta_amftrb_build_explanation( $parsed['rats'], $parsed['strategy'] ),
			);
			continue;
		}

		if ( preg_match( '/^Item (\d+) - Controlled Rationale/i', $lines[ $i ], $m ) ) {
			$num = (int) $m[1];
			++$i;

			while ( $i < $n && ! preg_match( '/^CORRECT ANSWER:\s*([A-D])\b/i', $lines[ $i ], $am ) ) {
				++$i;
			}
			if ( $i >= $n ) {
				break;
			}

			$correct = strtolower( (string) $am[1] );
			++$i;

			while ( $i < $n && ! preg_match( '/^Option-by-Option Rationales/i', $lines[ $i ] ) ) {
				++$i;
			}
			if ( $i >= $n ) {
				break;
			}
			++$i;

			$parsed = cta_amftrb_parse_controlled_option_rationales( $lines, $i, $n );
			$answers[ $num ] = array(
				'correct_option' => $correct,
				'explanation'    => cta_amftrb_build_explanation( $parsed['rats'], $parsed['strategy'] ),
			);
			continue;
		}

		++$i;
	}

	if ( 17 !== count( $answers ) ) {
		throw new RuntimeException( 'Expected 17 rationale blocks; got ' . count( $answers ) );
	}

	return $answers;
}

/**
 * @param array<int,array<string,string>> $candidate Candidate questions (0-indexed).
 * @param array<int,array{correct_option:string,explanation:string}> $answers 1-indexed.
 * @return array<int,array<string,string>>
 */
function cta_amftrb_merge_bank_questions( array $candidate, array $answers ) {
	$merged = array();
	foreach ( $candidate as $index => $row ) {
		$num = $index + 1;
		if ( ! isset( $answers[ $num ] ) ) {
			throw new RuntimeException( 'Missing rationale for question ' . $num );
		}
		$merged[] = array_merge(
			$row,
			$answers[ $num ]
		);
	}
	return $merged;
}

/**
 * @param array<int,array<string,string>> $questions Questions.
 * @param int                             $wb        Workbook number.
 * @return string
 */
function cta_amftrb_export_bank_seed( array $questions, $wb ) {
	$out  = "<?php\n";
	$out .= "/**\n";
	$out .= " * CTA LMFT AMFTRB National — Workbook {$wb} — 17-question practice bank.\n";
	$out .= " * Built from approved candidate bank + controlled answer key DOCX.\n";
	$out .= " */\n";
	$out .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n";
	$out .= "return array(\n";

	foreach ( $questions as $q ) {
		$out .= "\tarray(\n";
		foreach ( array( 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation' ) as $key ) {
			$out .= "\t\t'{$key}'  => " . var_export( (string) $q[ $key ], true ) . ",\n";
		}
		$out .= "\t),\n";
	}

	$out .= ");\n";
	return $out;
}

$failed = 0;
for ( $wb = 1; $wb <= 12; $wb++ ) {
	$candidate = $base . '/question-banks/CTA_LMFT_AMFTRB_WB' . $wb . '_17_Question_Candidate_Bank_v1.0.docx';
	if ( ! is_readable( $candidate ) ) {
		fwrite( STDERR, "MISSING candidate WB{$wb}: {$candidate}\n" );
		++$failed;
		continue;
	}

	try {
		$rationale = cta_amftrb_rationale_docx_path( $base, $wb );
		$c_rows    = cta_amftrb_parse_candidate_bank( cta_amftrb_bank_docx_lines( $candidate ) );
		$a_rows    = cta_amftrb_parse_rationale_bank( cta_amftrb_bank_docx_lines( $rationale ) );
		$questions = cta_amftrb_merge_bank_questions( $c_rows, $a_rows );
	} catch ( Throwable $e ) {
		fwrite( STDERR, "PARSE FAIL WB{$wb}: " . $e->getMessage() . "\n" );
		++$failed;
		continue;
	}

	if ( 17 !== count( $questions ) ) {
		fwrite( STDERR, "COUNT FAIL WB{$wb}: expected 17 got " . count( $questions ) . "\n" );
		++$failed;
		continue;
	}

	$dest = $out . '/lmft-amftrb-wb' . $wb . '-bank.php';
	$php  = cta_amftrb_export_bank_seed( $questions, $wb );
	if ( false === file_put_contents( $dest, $php ) ) {
		fwrite( STDERR, "WRITE FAIL WB{$wb}: {$dest}\n" );
		++$failed;
		continue;
	}

	echo "OK WB{$wb}: 17 questions -> {$dest}\n";
}

if ( $failed > 0 ) {
	exit( 1 );
}

echo "All 12 LMFT AMFTRB workbook bank seeds built.\n";
