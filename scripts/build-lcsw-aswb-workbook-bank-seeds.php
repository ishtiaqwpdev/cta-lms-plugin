<?php
/**
 * Build LCSW ASWB Clinical workbook practice-bank quiz seeds from approved DOCX banks.
 *
 * Source (learner + controlled rationales in one file per workbook):
 *   assets/course-materials/lcsw-aswb/question-banks/CTA_LCSW_WB{N}_17_Question_Bank_v1.0.docx
 *
 * Output:
 *   includes/quiz-seeds/lcsw-aswb-wb{N}-bank.php
 *
 * Usage (repo root):
 *   C:\xampp\php\php.exe scripts/build-lcsw-aswb-workbook-bank-seeds.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
$src  = $root . '/assets/course-materials/lcsw-aswb/question-banks';
$out  = $root . '/includes/quiz-seeds';

/**
 * @param string $path DOCX path.
 * @return string[]
 */
function cta_lcsw_bank_docx_lines( $path ) {
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

	$out = array();
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
 * Parse approved LCSW workbook bank DOCX (questions + rationales + exam strategy).
 * Strips Question ID / Difficulty / Question Type / Primary Concept from learner fields.
 *
 * @param string[] $lines DOCX paragraph lines.
 * @return array<int,array<string,string>>
 */
function cta_lcsw_parse_workbook_bank( array $lines ) {
	$questions = array();
	$i         = 0;
	$n         = count( $lines );

	while ( $i < $n ) {
		if ( ! preg_match( '/^LCSW-WB\d+-QB-Q\d+$/', $lines[ $i ] ) ) {
			++$i;
			continue;
		}

		++$i;
		while ( $i < $n && 'Question' !== $lines[ $i ] ) {
			++$i;
		}
		if ( $i >= $n ) {
			break;
		}
		++$i;
		if ( $i >= $n ) {
			break;
		}

		$stem = $lines[ $i ];
		++$i;

		$opts = array(
			'a' => '',
			'b' => '',
			'c' => '',
			'd' => '',
		);
		while ( $i < $n && preg_match( '/^([A-D])\.\s+(.+)$/', $lines[ $i ], $m ) ) {
			$opts[ strtolower( $m[1] ) ] = trim( $m[2] );
			++$i;
		}

		$correct = '';
		if ( $i < $n && preg_match( '/^Correct Answer:\s*([A-D])$/i', $lines[ $i ], $m ) ) {
			$correct = strtolower( $m[1] );
			++$i;
		}

		if ( $i < $n && preg_match( '/^Rationales?/i', $lines[ $i ] ) ) {
			++$i;
		}

		$rats = array();
		while ( $i < $n && preg_match( '/^([A-D])\.\s+(Correct|Incorrect)\.\s*(.*)$/i', $lines[ $i ], $m ) ) {
			$rats[ strtolower( $m[1] ) ] = trim( $m[2] . '. ' . $m[3] );
			++$i;
		}

		$strategy = '';
		if ( $i < $n && preg_match( '/^CTA Exam Strategy(.*)$/i', $lines[ $i ], $m ) ) {
			$strategy = trim( $m[1] );
			if ( '' === $strategy ) {
				++$i;
				if ( $i < $n && ! preg_match( '/^LCSW-WB|^Easy |^Moderate |^Difficult |^Question ID$/i', $lines[ $i ] ) ) {
					$strategy = $lines[ $i ];
					++$i;
				}
			} else {
				++$i;
			}
		}

		if ( '' === $stem || '' === $correct || '' === $opts['a'] || '' === $opts['b'] || '' === $opts['c'] ) {
			throw new RuntimeException( 'Incomplete question near line offset ' . $i );
		}
		if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
			throw new RuntimeException( 'Invalid correct option near line offset ' . $i );
		}
		if ( '' === $opts[ $correct ] ) {
			throw new RuntimeException( 'Correct option letter has empty choice text' );
		}

		$exp_parts = array();
		foreach ( array( 'a', 'b', 'c', 'd' ) as $key ) {
			if ( '' === $opts[ $key ] ) {
				continue;
			}
			$letter = strtoupper( $key );
			if ( isset( $rats[ $key ] ) ) {
				$exp_parts[] = $letter . '. ' . $rats[ $key ];
			}
		}
		if ( '' !== $strategy ) {
			$exp_parts[] = 'CTA Exam Strategy: ' . $strategy;
		}

		$questions[] = array(
			'question_text'  => $stem,
			'option_a'       => $opts['a'],
			'option_b'       => $opts['b'],
			'option_c'       => $opts['c'],
			'option_d'       => $opts['d'],
			'correct_option' => $correct,
			'explanation'    => implode( "\n\n", $exp_parts ),
		);
	}

	return $questions;
}

/**
 * @param array $questions Questions.
 * @param int   $wb        Workbook number.
 * @return string
 */
function cta_lcsw_export_bank_seed( array $questions, $wb ) {
	$out  = "<?php\n";
	$out .= "/**\n";
	$out .= " * CTA LCSW ASWB Clinical — Workbook {$wb} — 17-question practice bank.\n";
	$out .= " * Built from CTA_LCSW_WB{$wb}_17_Question_Bank_v1.0.docx (approved package content).\n";
	$out .= " * Learner-facing fields omit Question ID / difficulty / type / concept metadata.\n";
	$out .= " */\n";
	$out .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n";
	$out .= "return array(\n";

	foreach ( $questions as $q ) {
		$out .= "\tarray(\n";
		foreach ( array( 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation' ) as $key ) {
			$pad = 'question_text' === $key || 'correct_option' === $key || 'explanation' === $key ? '  ' : '       ';
			if ( 'correct_option' === $key ) {
				$pad = ' ';
			}
			if ( 'explanation' === $key ) {
				$pad = '    ';
			}
			$out .= "\t\t'{$key}'{$pad}=> " . var_export( (string) $q[ $key ], true ) . ",\n";
		}
		$out .= "\t),\n";
	}

	$out .= ");\n";
	return $out;
}

$failed = 0;
for ( $wb = 1; $wb <= 12; $wb++ ) {
	$file = $src . '/CTA_LCSW_WB' . $wb . '_17_Question_Bank_v1.0.docx';
	if ( ! is_readable( $file ) ) {
		fwrite( STDERR, "MISSING WB{$wb}: {$file}\n" );
		++$failed;
		continue;
	}

	try {
		$questions = cta_lcsw_parse_workbook_bank( cta_lcsw_bank_docx_lines( $file ) );
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

	$dest = $out . '/lcsw-aswb-wb' . $wb . '-bank.php';
	$php  = cta_lcsw_export_bank_seed( $questions, $wb );
	if ( false === file_put_contents( $dest, $php ) ) {
		fwrite( STDERR, "WRITE FAIL WB{$wb}: {$dest}\n" );
		++$failed;
		continue;
	}

	echo "OK WB{$wb}: 17 questions -> {$dest}\n";
}

if ( $failed > 0 ) {
	fwrite( STDERR, "Failed: {$failed}\n" );
	exit( 1 );
}

echo "All 12 LCSW ASWB workbook bank seeds built.\n";
exit( 0 );
