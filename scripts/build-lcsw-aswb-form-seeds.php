<?php
/**
 * Build LCSW ASWB Clinical Form A/B quiz seeds from approved v2.1 DOCX sources.
 *
 * Usage (repo root):
 *   C:\xampp\php\php.exe scripts/build-lcsw-aswb-form-seeds.php
 *   C:\xampp\php\php.exe scripts/build-lcsw-aswb-form-seeds.php --form=a
 *   C:\xampp\php\php.exe scripts/build-lcsw-aswb-form-seeds.php --dry-run
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root    = dirname( __DIR__ );
$options = getopt( '', array( 'form:', 'dry-run' ) );
$only    = isset( $options['form'] ) ? strtolower( (string) $options['form'] ) : '';
$dry_run = isset( $options['dry-run'] );

$sources = array(
	'a' => array(
		'candidate' => $root . '/New folder/CTA_LCSW_ASWB_Clinical_Comprehensive_Simulation_Form_A_Candidate_v2.1.docx',
		'key'       => $root . '/New folder/CTA_LCSW_ASWB_Clinical_Comprehensive_Simulation_Form_A_Controlled_Key_and_Rationales_v2.1.docx',
		'output'    => $root . '/includes/quiz-seeds/lcsw-aswb-form-a.php',
		'prefix'    => 'LCSW-SIM-A-Q',
	),
	'b' => array(
		'candidate' => $root . '/New folder/CTA_LCSW_ASWB_Clinical_Comprehensive_Simulation_Form_B_Candidate_v2.1.docx',
		'key'       => $root . '/New folder/CTA_LCSW_ASWB_Clinical_Comprehensive_Simulation_Form_B_Controlled_Key_and_Rationales_v2.1.docx',
		'output'    => $root . '/includes/quiz-seeds/lcsw-aswb-form-b.php',
		'prefix'    => 'LCSW-SIM-B-Q',
	),
);

/**
 * @param string $path DOCX path.
 * @return string[]
 */
function lcsw_form_docx_lines( $path ) {
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
 * @param string[] $lines Candidate DOCX lines.
 * @return array<int,array{question_text:string,option_a:string,option_b:string,option_c:string,option_d:string}>
 */
function lcsw_parse_candidate_questions( array $lines ) {
	$questions = array();
	$i         = 0;
	$n         = count( $lines );
	$started   = false;

	while ( $i < $n ) {
		if ( preg_match( '/Section\s+[12]\s.*Questions/i', $lines[ $i ] ) ) {
			$started = true;
			++$i;
			continue;
		}
		if ( ! $started ) {
			++$i;
			continue;
		}

		if ( ! preg_match( '/^(\d{1,3})\.\s+(.+)$/', $lines[ $i ], $m ) ) {
			++$i;
			continue;
		}

		$qnum = (int) $m[1];
		if ( $qnum < 1 || $qnum > 122 ) {
			++$i;
			continue;
		}

		$stem = trim( $m[2] );
		++$i;

		$opts = array(
			'a' => '',
			'b' => '',
			'c' => '',
			'd' => '',
		);
		while ( $i < $n && preg_match( '/^([A-D])\.\s+(.+)$/', $lines[ $i ], $om ) ) {
			$opts[ strtolower( $om[1] ) ] = trim( $om[2] );
			++$i;
		}

		while ( $i < $n && preg_match( '/^My answer:/i', $lines[ $i ] ) ) {
			++$i;
		}

		if ( '' === $stem || '' === $opts['a'] || '' === $opts['b'] || '' === $opts['c'] || '' === $opts['d'] ) {
			throw new RuntimeException( "Incomplete candidate question {$qnum}" );
		}

		$questions[ $qnum ] = array(
			'question_text' => $stem,
			'option_a'      => $opts['a'],
			'option_b'      => $opts['b'],
			'option_c'      => $opts['c'],
			'option_d'      => $opts['d'],
		);
	}

	if ( 122 !== count( $questions ) ) {
		throw new RuntimeException( 'Expected 122 candidate questions; found ' . count( $questions ) );
	}

	ksort( $questions );
	return array_values( $questions );
}

/**
 * @param string[] $lines Key DOCX lines.
 * @return array<int,string> Question number => correct letter.
 */
function lcsw_parse_quick_key( array $lines ) {
	$start = null;
	foreach ( $lines as $idx => $line ) {
		if ( 'Quick Key' === $line ) {
			$start = $idx + 1;
			break;
		}
	}
	if ( null === $start ) {
		throw new RuntimeException( 'Quick Key section not found' );
	}

	$i = $start;
	while ( $i < count( $lines ) && '1' !== $lines[ $i ] ) {
		++$i;
	}

	$keys = array();
	while ( $i < count( $lines ) && count( $keys ) < 122 ) {
		if ( ! preg_match( '/^\d{1,3}$/', $lines[ $i ] ) ) {
			break;
		}
		$qnum = (int) $lines[ $i++ ];
		if ( $qnum < 1 || $qnum > 122 ) {
			break;
		}
		// Placement ID, Source ID, Key, Status, Area, Difficulty, Cognitive.
		++$i;
		++$i;
		$key = strtolower( trim( (string) ( $lines[ $i++ ] ?? '' ) ) );
		++$i;
		++$i;
		++$i;
		++$i;

		if ( ! in_array( $key, array( 'a', 'b', 'c', 'd' ), true ) ) {
			throw new RuntimeException( "Invalid quick key for question {$qnum}: {$key}" );
		}
		$keys[ $qnum ] = $key;
	}

	if ( 122 !== count( $keys ) ) {
		throw new RuntimeException( 'Expected 122 quick-key rows; found ' . count( $keys ) );
	}

	return $keys;
}

/**
 * @param string $text Option + rationale text.
 * @return array{0:string,1:string} option text, rationale label+text.
 */
function lcsw_split_option_rationale( $text ) {
	$text = trim( (string) $text );
	if ( preg_match( '/^(.+?)\.(Best\.|Correct\.|Incorrect\.)\s*(.*)$/su', $text, $m ) ) {
		$label = str_replace( 'Best.', 'Correct.', $m[2] );
		return array( trim( $m[1] ), trim( $label . ' ' . $m[3] ) );
	}

	$starters = array(
		'This is a plausible',
		'This response narrows',
		'Family involvement',
		'Professional or family',
		'Professional values require',
		'Professional values',
		'Personal beliefs',
		'Overly broad',
		'Equality of procedure',
		'Ending services',
		'Waiting for',
		'Interpret current',
		'Focus only',
		'Ask the family',
		'Ask affected clients',
		'Determine competence',
		'Treat agreement',
		'Use a single',
		'Define resilience',
		'Provide another',
		'Delay home',
		'Cancel the leave',
		'Plan coverage',
		'Take leave',
		'Arrange involuntary',
		'Refer for',
		'Require an',
		'Tell the client',
		'Withdraw because',
		'Support communication',
		'Engage residents',
		'Respect the client',
		'Speak publicly',
		'Draft a formal',
		'Increase pressure',
		'Recenter the work',
		'Collaborate on',
		'Assess clinical',
		'Use a collaborative',
		'Discuss the financial',
		'Map responsibilities',
		'Begin recording',
		'Rely on the original',
		'Include the family',
		'Receive detailed',
		'Persuade the client',
		'Identify which family',
		'Complete individual',
		'Ask the family to select',
		'Choose one preferred',
		'Keep cases local',
		'Send the notes',
		'Refuse the request',
		'Mail the records',
		'Provide reassurance',
		'Explore childhood',
		'Recommend permanent',
		'Avoid asking',
		'End treatment immediately',
		'Continue indefinitely',
		'Contact the client',
		'Send another referral',
		'Ask the client to determine',
		'Focus on emotional support',
		'Require recording',
		'Address immediate risk',
		'Create a list',
		'Ignore missed visits',
		'Raise the equity concern',
		'Apply the policy equally',
	);

	foreach ( $starters as $starter ) {
		$pos = strpos( $text, '.' . $starter );
		if ( false !== $pos ) {
			$option = trim( substr( $text, 0, $pos ) );
			$rat    = trim( substr( $text, $pos + 1 ) );
			if ( ! preg_match( '/^(Best|Correct|Incorrect)\./', $rat ) ) {
				$rat = 'Incorrect. ' . $rat;
			}
			return array( $option, $rat );
		}
	}

	if ( preg_match( '/^(.+?\.)((?:Best|Correct|Incorrect)\..+)$/su', $text, $m ) ) {
		return array( rtrim( $m[1], '.' ), trim( $m[2] ) );
	}

	return array( $text, '' );
}

/**
 * @param string $line         Full "A. ..." rationale line.
 * @param string $letter       a|b|c|d.
 * @param string $option_text  Learner-facing option from candidate doc.
 * @param bool   $is_correct   Whether this letter is keyed correct.
 * @return string Formatted rationale line (e.g. "A. Correct. ...").
 */
function lcsw_format_rationale_line( $line, $letter, $option_text, $is_correct ) {
	$letter = strtolower( (string) $letter );
	if ( ! preg_match( '/^' . strtoupper( $letter ) . '\.\s+(.+)$/u', (string) $line, $m ) ) {
		return '';
	}

	$rest        = trim( $m[1] );
	$option_text = trim( preg_replace( '/\s+/u', ' ', (string) $option_text ) );
	$rest_norm   = trim( preg_replace( '/\s+/u', ' ', $rest ) );
	$rat         = '';

	if ( '' !== $option_text && 0 === stripos( $rest_norm, $option_text ) ) {
		$rat = trim( substr( $rest_norm, strlen( $option_text ) ), " \t." );
	} else {
		list( , $rat ) = lcsw_split_option_rationale( $rest_norm );
	}

	if ( '' === $rat ) {
		return '';
	}

	if ( preg_match( '/^(Best|Correct|Incorrect)\./i', $rat ) ) {
		$rat = preg_replace( '/^Best\./i', 'Correct.', $rat );
	} else {
		$rat = ( $is_correct ? 'Correct.' : 'Incorrect.' ) . ' ' . $rat;
	}

	return strtoupper( $letter ) . '. ' . $rat;
}

/**
 * @param string[]                                         $lines       Key DOCX lines.
 * @param array<int,array<string,string>>                  $candidate_q Candidate questions (0-indexed).
 * @param array<int,string>                                $quick_keys  Question number => correct letter.
 * @return array<int,string> Question number => explanation block.
 */
function lcsw_parse_detailed_rationales( array $lines, array $candidate_q, array $quick_keys ) {
	$out = array();
	$i   = 0;
	$n   = count( $lines );

	while ( $i < $n ) {
		if ( ! preg_match( '/^Question\s+(\d{1,3})\s+[—\-]/u', $lines[ $i ], $m ) ) {
			++$i;
			continue;
		}

		$qnum = (int) $m[1];
		++$i;
		if ( $i < $n && preg_match( '/^Source ID:/i', $lines[ $i ] ) ) {
			++$i;
		}
		if ( $i < $n && ! preg_match( '/^[A-D]\./', $lines[ $i ] ) ) {
			++$i; // stem repeat
		}

		$cand   = $candidate_q[ $qnum - 1 ] ?? array();
		$correct = strtolower( (string) ( $quick_keys[ $qnum ] ?? '' ) );
		$parts  = array();

		foreach ( array( 'a', 'b', 'c', 'd' ) as $letter ) {
			if ( $i >= $n || ! preg_match( '/^' . strtoupper( $letter ) . '\./', $lines[ $i ] ) ) {
				throw new RuntimeException( "Missing rationale option {$letter} for question {$qnum}" );
			}
			$formatted = lcsw_format_rationale_line(
				$lines[ $i ],
				$letter,
				(string) ( $cand[ 'option_' . $letter ] ?? '' ),
				$letter === $correct
			);
			if ( '' === $formatted ) {
				throw new RuntimeException( "Could not parse rationale for Q{$qnum} option " . strtoupper( $letter ) );
			}
			$parts[] = $formatted;
			++$i;
		}

		while ( $i < $n && preg_match( '/^Applied Knowledge:/i', $lines[ $i ] ) ) {
			++$i;
		}

		$out[ $qnum ] = implode( "\n\n", $parts );
	}

	if ( 122 !== count( $out ) ) {
		throw new RuntimeException( 'Expected 122 detailed rationale blocks; found ' . count( $out ) );
	}

	return $out;
}

/**
 * @param array<int,array<string,string>> $questions Questions.
 * @param string                          $form      a|b.
 * @return string
 */
function lcsw_export_form_seed( array $questions, $form ) {
	$form_label = strtoupper( (string) $form );
	$out        = "<?php\n";
	$out       .= "/**\n";
	$out       .= " * CTA LCSW ASWB Clinical - Form {$form_label} - 122-question comprehensive simulation.\n";
	$out       .= " * Built from approved Candidate + Controlled Key v2.1 DOCX (August 2026).\n";
	$out       .= " * Standard two-section scroll format (61 + 61); not case-locking.\n";
	$out       .= " */\n";
	$out       .= "if ( ! defined( 'ABSPATH' ) ) {\n";
	$out       .= "\texit;\n";
	$out       .= "}\n\n";
	$out       .= "return array(\n";

	foreach ( $questions as $q ) {
		$out .= "\tarray(\n";
		foreach ( array( 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation' ) as $key ) {
			$pad = in_array( $key, array( 'question_text', 'correct_option', 'explanation' ), true ) ? '  ' : '       ';
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

$issues = array();

foreach ( $sources as $form => $cfg ) {
	if ( '' !== $only && $only !== $form ) {
		continue;
	}

	echo "Building Form " . strtoupper( $form ) . "...\n";

	if ( ! is_readable( $cfg['candidate'] ) || ! is_readable( $cfg['key'] ) ) {
		fwrite( STDERR, "Missing source DOCX for Form {$form}\n" );
		exit( 1 );
	}

	$candidate_lines = lcsw_form_docx_lines( $cfg['candidate'] );
	$key_lines       = lcsw_form_docx_lines( $cfg['key'] );

	$candidate_q = lcsw_parse_candidate_questions( $candidate_lines );
	$quick_keys  = lcsw_parse_quick_key( $key_lines );
	$rationales  = lcsw_parse_detailed_rationales( $key_lines, $candidate_q, $quick_keys );

	$merged = array();
	for ( $n = 1; $n <= 122; ++$n ) {
		$row = $candidate_q[ $n - 1 ];
		$key = $quick_keys[ $n ] ?? '';
		if ( ! in_array( $key, array( 'a', 'b', 'c', 'd' ), true ) || '' === $row[ 'option_' . $key ] ) {
			$issues[] = "Form {$form} Q{$n}: invalid or empty keyed option {$key}";
		}
		$row['correct_option'] = $key;
		$row['explanation']    = $rationales[ $n ] ?? '';
		if ( '' === trim( $row['explanation'] ) ) {
			$issues[] = "Form {$form} Q{$n}: missing explanation";
		}
		$merged[] = $row;
	}

	echo '  Questions: ' . count( $merged ) . "\n";
	echo '  Q1 stem: ' . substr( $merged[0]['question_text'], 0, 72 ) . "...\n";
	echo '  Q1 key: ' . strtoupper( $merged[0]['correct_option'] ) . "\n";

	$php = lcsw_export_form_seed( $merged, $form );
	if ( $dry_run ) {
		echo "  Dry run — not writing {$cfg['output']}\n";
		continue;
	}

	file_put_contents( $cfg['output'], $php );
	echo '  Wrote ' . $cfg['output'] . "\n";
}

if ( ! empty( $issues ) ) {
	echo "\nParse issues (" . count( $issues ) . "):\n";
	foreach ( $issues as $issue ) {
		echo "  - {$issue}\n";
	}
	exit( 1 );
}

echo "\nDone.\n";
