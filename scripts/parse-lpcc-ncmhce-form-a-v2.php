<?php
/**
 * Parse LPCC NCMHCE Form A v2.0 candidate DOCX paragraphs into structured JSON.
 *
 * Usage: php scripts/parse-lpcc-ncmhce-form-a-v2.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root  = dirname( __DIR__ );
$paras = file( $root . '/_tmp_lpcc_form_a_v2/paras.txt', FILE_IGNORE_NEW_LINES );
if ( ! is_array( $paras ) ) {
	fwrite( STDERR, "Missing paras.txt\n" );
	exit( 1 );
}

$cases        = array();
$current_case = null;
$current_sec  = null;
$current_q    = null;
$mode         = 'preamble';
$preamble     = array();

/**
 * Flush current question into current section.
 */
function flush_q( &$current_sec, &$current_q ) {
	if ( ! $current_q ) {
		return;
	}
	$current_sec['questions'][] = $current_q;
	$current_q = null;
}

/**
 * Flush current section into current case.
 */
function flush_sec( &$current_case, &$current_sec, &$current_q ) {
	flush_q( $current_sec, $current_q );
	if ( $current_sec ) {
		$current_sec['stem'] = trim( implode( "\n\n", $current_sec['stem_paras'] ) );
		unset( $current_sec['stem_paras'] );
		$current_case['sections'][] = $current_sec;
	}
	$current_sec = null;
}

foreach ( $paras as $raw ) {
	$line = trim( (string) $raw );
	if ( '' === $line ) {
		continue;
	}

	if ( preg_match( '/^CASE\s+(\d+)\s+[—–-]\s+(.+)$/u', $line, $m ) ) {
		if ( $current_case ) {
			flush_sec( $current_case, $current_sec, $current_q );
			$cases[] = $current_case;
		}
		$current_case = array(
			'case_number' => (int) $m[1],
			'title'       => $line,
			'client'      => trim( $m[2] ),
			'sections'    => array(),
		);
		$current_sec = null;
		$current_q   = null;
		$mode        = 'case';
		continue;
	}

	if ( preg_match( '/^SECTION\s+(\d+)\s+[—–-]\s+(.+)$/u', $line, $m ) ) {
		if ( ! $current_case ) {
			fwrite( STDERR, "Section before case: {$line}\n" );
			exit( 1 );
		}
		flush_sec( $current_case, $current_sec, $current_q );
		$current_sec = array(
			'section_number' => (int) $m[1],
			'title'          => $line,
			'heading'        => trim( $m[2] ),
			'stem_paras'     => array(),
			'questions'      => array(),
		);
		$mode = 'stem';
		continue;
	}

	if ( preg_match( '/^(\d+)\.\s+(.+)$/u', $line, $m ) ) {
		if ( ! $current_sec ) {
			fwrite( STDERR, "Question before section: {$line}\n" );
			exit( 1 );
		}
		flush_q( $current_sec, $current_q );
		$current_q = array(
			'number'        => (int) $m[1],
			'question_text' => trim( $m[2] ),
			'options'       => array(),
		);
		$mode = 'question';
		continue;
	}

	if ( preg_match( '/^([A-D])\.\s+(.+)$/u', $line, $m ) ) {
		if ( ! $current_q ) {
			fwrite( STDERR, "Option before question: {$line}\n" );
			exit( 1 );
		}
		$key = strtolower( $m[1] );
		$current_q['options'][ $key ] = trim( $m[2] );
		continue;
	}

	if ( preg_match( '/^Answer:/u', $line ) ) {
		continue;
	}

	if ( preg_match( '/^End of Form A\b/u', $line )
		|| preg_match( '/^Stop here\./u', $line )
		|| preg_match( '/^After completion,/u', $line ) ) {
		$mode = 'preamble';
		continue;
	}

	if ( 'preamble' === $mode || ! $current_case ) {
		$preamble[] = $line;
		continue;
	}

	if ( 'stem' === $mode && $current_sec ) {
		$current_sec['stem_paras'][] = $line;
		continue;
	}

	if ( 'question' === $mode && $current_q ) {
		// Continuation of stem-like text after a question is unexpected; append to question.
		$current_q['question_text'] .= ' ' . $line;
		continue;
	}
}

if ( $current_case ) {
	flush_sec( $current_case, $current_sec, $current_q );
	$cases[] = $current_case;
}

$q_total     = 0;
$opt_max     = 0;
$stem_max    = 0;
$case_counts = array();
$problems    = array();

foreach ( $cases as $case ) {
	$cq = 0;
	$secs = isset( $case['sections'] ) ? count( $case['sections'] ) : 0;
	if ( 3 !== $secs ) {
		$problems[] = 'Case ' . $case['case_number'] . ' has ' . $secs . ' sections';
	}
	foreach ( $case['sections'] as $sec ) {
		$stem_max = max( $stem_max, strlen( (string) $sec['stem'] ) );
		foreach ( $sec['questions'] as $q ) {
			++$q_total;
			++$cq;
			if ( count( $q['options'] ) !== 4 ) {
				$problems[] = 'Q' . $q['number'] . ' has ' . count( $q['options'] ) . ' options';
			}
			foreach ( array( 'a', 'b', 'c', 'd' ) as $k ) {
				if ( empty( $q['options'][ $k ] ) ) {
					$problems[] = 'Q' . $q['number'] . ' missing option ' . strtoupper( $k );
				} else {
					$opt_max = max( $opt_max, strlen( $q['options'][ $k ] ) );
				}
			}
		}
	}
	$case_counts[ $case['case_number'] ] = $cq;
}

$summary = array(
	'preamble'         => $preamble,
	'case_count'       => count( $cases ),
	'question_count'   => $q_total,
	'questions_by_case'=> $case_counts,
	'max_option_len'   => $opt_max,
	'max_stem_len'     => $stem_max,
	'problems'         => $problems,
);

file_put_contents( $root . '/_tmp_lpcc_form_a_v2/parsed.json', json_encode( array( 'summary' => $summary, 'cases' => $cases ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

echo "cases=" . count( $cases ) . " questions={$q_total}\n";
echo "by_case=" . json_encode( $case_counts ) . "\n";
echo "max_option_len={$opt_max} max_stem_len={$stem_max}\n";
if ( $problems ) {
	echo "PROBLEMS:\n" . implode( "\n", $problems ) . "\n";
	exit( 1 );
}
echo "OK\n";
