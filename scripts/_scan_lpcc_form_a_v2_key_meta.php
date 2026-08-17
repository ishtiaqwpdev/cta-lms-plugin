<?php
/**
 * Scan Form A v2.0 answer-key paras for admin metadata only.
 * Prints headings, pass-score language, timing language, and item counts.
 * Does not print correct letters or rationale bodies.
 *
 * Usage: php scripts/_scan_lpcc_form_a_v2_key_meta.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root  = dirname( __DIR__ );
$paras = file( $root . '/_tmp_lpcc_form_a_v2_key/paras.txt', FILE_IGNORE_NEW_LINES );
if ( ! is_array( $paras ) ) {
	fwrite( STDERR, "Missing key paras.txt\n" );
	exit( 1 );
}

$keyword_re = '/pass(ing)?|threshold|cut.?score|70\s*%|percent|rationale|review|after\s+submi|lock|section|break|timer|scoring|correct answer|answer key/i';

echo "=== PREAMBLE / KEYWORD LINES (first 80 nonempty) ===\n";
$shown = 0;
foreach ( $paras as $i => $raw ) {
	$line = trim( (string) $raw );
	if ( '' === $line ) {
		continue;
	}
	++$shown;
	if ( $shown <= 80 ) {
		echo sprintf( "%4d: %s\n", $i + 1, $line );
	}
}

echo "\n=== ALL LINES MATCHING PASS/RATIONALE/TIMING KEYWORDS ===\n";
foreach ( $paras as $i => $raw ) {
	$line = trim( (string) $raw );
	if ( '' === $line ) {
		continue;
	}
	if ( preg_match( $keyword_re, $line ) ) {
		// Skip per-item rationale bodies (usually long and start after Question N).
		if ( strlen( $line ) > 220 && ! preg_match( '/^(CASE|SECTION|Question|FORM|CONTROLLED|ADMIN|Scoring|Passing|Rationale release)/i', $line ) ) {
			echo sprintf( "%4d: [long keyword line, %d chars, starts: %s]\n", $i + 1, strlen( $line ), substr( $line, 0, 80 ) );
			continue;
		}
		echo sprintf( "%4d: %s\n", $i + 1, $line );
	}
}

echo "\n=== CASE / SECTION HEADINGS ===\n";
$cases    = array();
$sections = array();
foreach ( $paras as $i => $raw ) {
	$line = trim( (string) $raw );
	if ( preg_match( '/^CASE\s+(\d+)/u', $line, $m ) ) {
		$cases[ (int) $m[1] ] = $line;
		echo sprintf( "%4d: %s\n", $i + 1, $line );
	} elseif ( preg_match( '/^SECTION\s+(\d+)/u', $line, $m ) ) {
		$sections[] = $line;
		echo sprintf( "%4d: %s\n", $i + 1, $line );
	}
}

echo "\ncase_headings=" . count( $cases ) . " section_headings=" . count( $sections ) . "\n";

echo "\n=== QUESTION / ANSWER LINE COUNTS ===\n";
$q_lines     = 0;
$ans_lines   = 0;
$q_numbers   = array();
$ans_letters = array();
$q_pattern   = 0;
foreach ( $paras as $raw ) {
	$line = trim( (string) $raw );
	if ( preg_match( '/^(?:Question|Q)\s*(\d+)\b/i', $line, $m ) ) {
		++$q_lines;
		$q_numbers[] = (int) $m[1];
	} elseif ( preg_match( '/^(\d+)\.\s+/u', $line ) ) {
		++$q_pattern;
	}
	if ( preg_match( '/^(?:Correct\s+Answer|Answer)\s*[:\-–—]\s*([A-D])/i', $line, $m ) ) {
		++$ans_lines;
		$ans_letters[] = strtoupper( $m[1] );
	}
}
echo "Question-N lines={$q_lines}\n";
echo "Numbered N. lines={$q_pattern}\n";
echo "Correct Answer letter lines={$ans_lines}\n";
if ( $q_numbers ) {
	echo "question_n min=" . min( $q_numbers ) . " max=" . max( $q_numbers ) . " unique=" . count( array_unique( $q_numbers ) ) . "\n";
}

echo "\n=== SAMPLE STRUCTURAL LINES AROUND FIRST ITEM (redacted answers) ===\n";
$started = false;
$n       = 0;
foreach ( $paras as $i => $raw ) {
	$line = trim( (string) $raw );
	if ( ! $started && preg_match( '/^(CASE\s+1\b|Question\s+1\b|1\.\s+)/u', $line ) ) {
		$started = true;
	}
	if ( ! $started ) {
		continue;
	}
	++$n;
	$redacted = preg_replace( '/^(Correct\s+Answer|Answer)\s*[:\-–—]\s*[A-D].*$/i', '$1: [REDACTED]', $line );
	if ( strlen( $redacted ) > 160 ) {
		$redacted = substr( $redacted, 0, 160 ) . '…';
	}
	echo sprintf( "%4d: %s\n", $i + 1, $redacted );
	if ( $n >= 40 ) {
		break;
	}
}
