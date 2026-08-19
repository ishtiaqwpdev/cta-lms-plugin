<?php
/**
 * Build LMFT AMFTRB Form A/B online quiz seeds from approved DOCX assessments.
 *
 * Sources:
 *   assets/course-materials/lmft-amftrb/question-banks/CTA_LMFT_AMFTRB_Simulation_Form_{A|B}_180_Question_Candidate_Assessment_v1.0.docx
 *   assets/course-materials/lmft-amftrb/rationales/CTA_LMFT_AMFTRB_Simulation_Form_{A|B}_180_Question_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx
 *
 * Output:
 *   includes/quiz-seeds/lmft-amftrb-form-a.php
 *   includes/quiz-seeds/lmft-amftrb-form-b.php
 *
 * Usage: php scripts/build-lmft-amftrb-form-seeds.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root = dirname( __DIR__ );
$base = $root . '/assets/course-materials/lmft-amftrb';
$out  = $root . '/includes/quiz-seeds';

/**
 * @param string $path DOCX path.
 * @return string[]
 */
function cta_amftrb_docx_lines( $path ) {
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
 * @param string[] $lines Candidate assessment DOCX lines.
 * @return array<int,array<string,string>>
 */
function cta_amftrb_parse_candidate_form( array $lines ) {
	$questions = array();
	$i         = 0;
	$n         = count( $lines );

	while ( $i < $n ) {
		if ( ! preg_match( '/^(\d+)\.\s+(.+)$/', $lines[ $i ], $m ) ) {
			++$i;
			continue;
		}

		$num  = (int) $m[1];
		$stem = (string) $m[2];
		++$i;

		$choices = array(
			'a' => '',
			'b' => '',
			'c' => '',
			'd' => '',
		);

		foreach ( array( 'A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd' ) as $upper => $lower ) {
			if ( $i < $n && preg_match( '/^' . $upper . '\.\s+(.+)$/', $lines[ $i ], $cm ) ) {
				$choices[ $lower ] = (string) $cm[1];
				++$i;
			}
		}

		if ( $i < $n && preg_match( '/^Answer:/i', $lines[ $i ] ) ) {
			++$i;
		}

		if ( '' === $stem || '' === $choices['a'] || '' === $choices['b'] || '' === $choices['c'] || '' === $choices['d'] ) {
			continue;
		}

		$questions[ $num ] = array(
			'question_text' => $stem,
			'option_a'      => $choices['a'],
			'option_b'      => $choices['b'],
			'option_c'      => $choices['c'],
			'option_d'      => $choices['d'],
		);
	}

	ksort( $questions );
	return array_values( $questions );
}

/**
 * @param string[] $lines Answer key DOCX lines.
 * @return array<int,array{correct_option:string,explanation:string}>
 */
function cta_amftrb_parse_answer_key( array $lines ) {
	$answers = array();
	$i       = 0;
	$n       = count( $lines );

	while ( $i < $n ) {
		if ( ! preg_match( '/^Item\s+(\d+)\s*-\s*Controlled Rationale/i', $lines[ $i ], $m ) ) {
			++$i;
			continue;
		}

		$num = (int) $m[1];
		++$i;

		$correct     = '';
		$explanation = '';

		while ( $i < $n && ! preg_match( '/^CORRECT ANSWER:\s*([A-D])\b/i', $lines[ $i ], $am ) ) {
			++$i;
		}
		if ( $i >= $n ) {
			break;
		}

		$correct = strtolower( (string) $am[1] );
		++$i;

		while ( $i < $n ) {
			if ( preg_match( '/^Item\s+\d+\s*-\s*Controlled Rationale/i', $lines[ $i ] ) ) {
				break;
			}
			if ( preg_match( '/^Why the keyed option is best:\s*(.*)$/i', $lines[ $i ], $wm ) ) {
				$explanation = trim( (string) $wm[1] );
				++$i;
				while ( $i < $n
					&& ! preg_match( '/^Option-by-Option|^Item\s+\d+\s*-\s*Controlled|^Post-Attempt|^STOP BEFORE/i', $lines[ $i ] ) ) {
					$explanation .= ' ' . $lines[ $i ];
					++$i;
				}
				$explanation = trim( preg_replace( '/\s+/u', ' ', $explanation ) );
				break;
			}
			++$i;
		}

		if ( '' !== $correct ) {
			$answers[ $num ] = array(
				'correct_option' => $correct,
				'explanation'    => $explanation,
			);
		}
	}

	return $answers;
}

/**
 * @param array<int,array<string,string>> $candidate Candidate rows (0-indexed).
 * @param array<int,array{correct_option:string,explanation:string}> $answers Answer key by item number (1-indexed).
 * @return array<int,array<string,string>>
 */
function cta_amftrb_merge_form_questions( array $candidate, array $answers ) {
	$merged = array();
	foreach ( $candidate as $index => $row ) {
		$num = $index + 1;
		if ( ! isset( $answers[ $num ] ) ) {
			throw new RuntimeException( 'Missing answer key for item ' . $num );
		}
		$merged[] = array_merge(
			$row,
			array(
				'correct_option' => $answers[ $num ]['correct_option'],
				'explanation'    => $answers[ $num ]['explanation'],
			)
		);
	}
	return $merged;
}

/**
 * @param array<int,array<string,string>> $questions Question rows.
 * @param string                          $title     Seed header title.
 * @return string
 */
function cta_amftrb_export_form_seed( array $questions, $title ) {
	$out  = "<?php\n";
	$out .= "/**\n * {$title}\n * Generated from approved AMFTRB DOCX. Do not hand-edit unless regenerating.\n */\n";
	$out .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\nreturn array(\n";

	foreach ( $questions as $question ) {
		$out .= "\tarray(\n";
		foreach ( array( 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation' ) as $field ) {
			$out .= "\t\t'{$field}'  => " . var_export( (string) ( $question[ $field ] ?? '' ), true ) . ",\n";
		}
		$out .= "\t),\n";
	}

	$out .= ");\n";
	return $out;
}

/**
 * @param string $form A|B.
 * @return array<int,array<string,string>>
 */
function cta_amftrb_build_form_questions( $form ) {
	$form = strtoupper( (string) $form );
	if ( ! in_array( $form, array( 'A', 'B' ), true ) ) {
		throw new InvalidArgumentException( 'Invalid form: ' . $form );
	}

	global $base;
	$candidate = $base . '/question-banks/CTA_LMFT_AMFTRB_Simulation_Form_' . $form . '_180_Question_Candidate_Assessment_v1.0.docx';
	$key       = $base . '/rationales/CTA_LMFT_AMFTRB_Simulation_Form_' . $form . '_180_Question_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx';

	if ( ! is_readable( $candidate ) || ! is_readable( $key ) ) {
		throw new RuntimeException( 'Missing approved Form ' . $form . ' DOCX source files.' );
	}

	$candidate_rows = cta_amftrb_parse_candidate_form( cta_amftrb_docx_lines( $candidate ) );
	$answer_rows    = cta_amftrb_parse_answer_key( cta_amftrb_docx_lines( $key ) );

	if ( 180 !== count( $candidate_rows ) ) {
		throw new RuntimeException( 'Form ' . $form . ' candidate parse expected 180 questions; got ' . count( $candidate_rows ) );
	}
	if ( 180 !== count( $answer_rows ) ) {
		throw new RuntimeException( 'Form ' . $form . ' answer key parse expected 180 answers; got ' . count( $answer_rows ) );
	}

	return cta_amftrb_merge_form_questions( $candidate_rows, $answer_rows );
}

$failed = 0;
foreach ( array( 'A', 'B' ) as $form ) {
	try {
		$questions = cta_amftrb_build_form_questions( $form );
	} catch ( Throwable $e ) {
		fwrite( STDERR, 'FAIL Form ' . $form . ': ' . $e->getMessage() . PHP_EOL );
		++$failed;
		continue;
	}

	$dest = $out . '/lmft-amftrb-form-' . strtolower( $form ) . '.php';
	$php  = cta_amftrb_export_form_seed(
		$questions,
		'CTA LMFT AMFTRB National — Form ' . $form . ' — 180-Question Comprehensive Simulation'
	);

	if ( false === file_put_contents( $dest, $php ) ) {
		fwrite( STDERR, 'WRITE FAIL Form ' . $form . ': ' . $dest . PHP_EOL );
		++$failed;
		continue;
	}

	echo 'OK Form ' . $form . ': 180 questions -> ' . $dest . PHP_EOL;
	echo '  Q1 correct: ' . $questions[0]['correct_option'] . ' | ' . substr( $questions[0]['question_text'], 0, 60 ) . '...' . PHP_EOL;
}

if ( $failed > 0 ) {
	exit( 1 );
}

echo "Both AMFTRB form seeds built successfully.\n";
