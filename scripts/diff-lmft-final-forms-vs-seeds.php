<?php
/**
 * Diff August 14 Final Form A/B DOCX (+ admin key) against current PHP seeds.
 *
 * Usage: php scripts/diff-lmft-final-forms-vs-seeds.php
 *
 * @package CTA_LMS
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
$base = $root . '/_packages/CTA_LMFT_CA_Clinical_Final_Simulations_Aug14';

/**
 * @param string $path DOCX path.
 * @return string[]
 */
function docx_lines( $path ) {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		throw new RuntimeException( 'Cannot open: ' . $path );
	}
	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();
	if ( false === $xml || '' === $xml ) {
		return array();
	}
	$dom = new DOMDocument();
	$dom->loadXML( $xml );
	$xp = new DOMXPath( $dom );
	$xp->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
	$out = array();
	foreach ( $xp->query( '//w:p' ) as $p ) {
		$t = '';
		foreach ( $xp->query( './/w:t', $p ) as $n ) {
			$t .= $n->textContent;
		}
		$t = trim( preg_replace( '/\s+/u', ' ', $t ) );
		if ( '' !== $t ) {
			$out[] = $t;
		}
	}
	return $out;
}

/**
 * @param string $text Text.
 * @return string
 */
function norm_text( $text ) {
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = str_replace( array( "\xC2\xA0", '’', '‘', '“', '”', '–', '—' ), array( ' ', "'", "'", '"', '"', '-', '-' ), $text );
	$text = preg_replace( '/\s+/u', ' ', $text );
	return trim( (string) $text );
}

/**
 * @param string[] $lines DOCX paragraph lines.
 * @return array<int,array{n:int,stem:string,A:string,B:string,C:string,D:string}>
 */
function parse_learner_form( array $lines ) {
	$qs  = array();
	$cur = null;
	$in  = false;
	foreach ( $lines as $line ) {
		if ( preg_match( '/^FORM [AB].*BEGIN/u', $line ) ) {
			$in = true;
			continue;
		}
		if ( preg_match( '/^Answer Sheet$/u', $line ) || preg_match( '/^END OF FORM/u', $line ) ) {
			break;
		}
		if ( ! $in ) {
			continue;
		}
		if ( preg_match( '/^(\d{1,3})\.\s+(.+)$/u', $line, $m ) ) {
			if ( $cur ) {
				$qs[ (int) $cur['n'] ] = $cur;
			}
			$cur = array(
				'n'    => (int) $m[1],
				'stem' => $m[2],
				'A'    => '',
				'B'    => '',
				'C'    => '',
				'D'    => '',
			);
			continue;
		}
		if ( ! $cur ) {
			continue;
		}
		if ( preg_match( '/^([A-D])\.\s*(.*)$/u', $line, $m ) ) {
			$cur[ $m[1] ] = $m[2];
		}
	}
	if ( $cur ) {
		$qs[ (int) $cur['n'] ] = $cur;
	}
	ksort( $qs );
	return $qs;
}

/**
 * Parse admin key blocks.
 *
 * @param string[] $lines Lines.
 * @param string   $form  A|B.
 * @return array<int,array<string,mixed>>
 */
function parse_admin_key( array $lines, $form ) {
	$records = array();
	$cur     = null;
	// Bullet between fields is U+2022 (•).
	$header  = '/^Form ' . preg_quote( $form, '/' ) . '\s*\x{2022}\s*Q0*(\d{1,3})\s*\x{2022}\s*(.+)$/u';

	foreach ( $lines as $line ) {
		if ( 'A' === $form && preg_match( '/^Form B\b/u', $line ) ) {
			break;
		}
		if ( preg_match( $header, $line, $m ) ) {
			if ( $cur ) {
				$records[ (int) $cur['q_num'] ] = $cur;
			}
			$meta    = $m[2];
			$parts   = preg_split( '/\s*\|\s*/u', $meta );
			$item_id = trim( (string) ( $parts[0] ?? '' ) );
			$status  = '';
			$key     = '';
			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( in_array( $part, array( 'Core', 'Calibration' ), true ) ) {
					$status = $part;
				}
				if ( preg_match( '/^Key\s+([A-D])$/u', $part, $km ) ) {
					$key = $km[1];
				}
			}
			$cur = array(
				'q_num'                   => (int) $m[1],
				'item_id'                 => $item_id,
				'core_calibration_status' => $status,
				'correct_answer'          => $key,
				'stem'                    => '',
				'A'                       => '',
				'B'                       => '',
				'C'                       => '',
				'D'                       => '',
				'rat_A'                   => '',
				'rat_B'                   => '',
				'rat_C'                   => '',
				'rat_D'                   => '',
			);
			continue;
		}
		if ( ! $cur ) {
			continue;
		}
		if ( preg_match( '/^Stem:\s*(.+)$/u', $line, $m ) ) {
			$cur['stem'] = $m[1];
			continue;
		}
		if ( preg_match( '/^([A-D])\.\s*(.+)$/u', $line, $m ) && '' === $cur[ $m[1] ] ) {
			$cur[ $m[1] ] = $m[2];
			continue;
		}
		if ( preg_match( '/^CONTROLLED KEY:\s*([A-D])/u', $line, $m ) ) {
			$cur['correct_answer'] = $m[1];
			continue;
		}
		if ( preg_match( '/^([A-D])\s*\x{2014}\s*Why (?:best|not best):\s*(.+)$/u', $line, $m )
			|| preg_match( '/^([A-D])\s*[—\-]\s*Why (?:best|not best):\s*(.+)$/u', $line, $m ) ) {
			$cur[ 'rat_' . $m[1] ] = $m[2];
			continue;
		}
	}
	if ( $cur ) {
		$records[ (int) $cur['q_num'] ] = $cur;
	}
	ksort( $records );
	return $records;
}

$fail = 0;

foreach ( array( 'A', 'B' ) as $form ) {
	$lower = strtolower( $form );
	$docx  = $base . '/CTA_LMFT_CA_Clinical_Exam_Prep_Final_Form_' . $form . '_v1.0.docx';
	$seed  = include $root . '/includes/quiz-seeds/lmft-clinical-form-' . $lower . '.php';
	$qs    = parse_learner_form( docx_lines( $docx ) );

	echo "=== Form {$form} learner ===\n";
	echo 'DOCX questions: ' . count( $qs ) . "\n";
	echo 'Seed questions: ' . count( $seed ) . "\n";
	echo 'Q1 DOCX: ' . substr( $qs[1]['stem'] ?? '', 0, 140 ) . "\n";
	echo 'Q1 seed: ' . substr( $seed[0]['question_text'] ?? '', 0, 140 ) . "\n";

	$mismatches = array();
	for ( $i = 1; $i <= 150; $i++ ) {
		if ( empty( $qs[ $i ] ) || empty( $seed[ $i - 1 ] ) ) {
			$mismatches[] = "Q{$i}: missing";
			continue;
		}
		$d = $qs[ $i ];
		$s = $seed[ $i - 1 ];
		$map = array(
			'stem' => array( $d['stem'], $s['question_text'] ),
			'A'    => array( $d['A'], $s['option_a'] ),
			'B'    => array( $d['B'], $s['option_b'] ),
			'C'    => array( $d['C'], $s['option_c'] ),
			'D'    => array( $d['D'], $s['option_d'] ),
		);
		foreach ( $map as $field => $pair ) {
			if ( norm_text( $pair[0] ) !== norm_text( $pair[1] ) ) {
				$mismatches[] = "Q{$i}.{$field}";
			}
		}
	}
	echo 'Field mismatches: ' . count( $mismatches ) . "\n";
	foreach ( array_slice( $mismatches, 0, 20 ) as $m ) {
		echo "  - {$m}\n";
	}
	if ( count( $mismatches ) > 0 ) {
		$fail = 1;
	}
	echo "\n";
}

$admin_lines = docx_lines( $base . '/CTA_LMFT_CA_Clinical_Exam_Prep_Final_Admin_Key_Rationales_v1.0.docx' );
foreach ( array( 'A', 'B' ) as $form ) {
	$lower   = strtolower( $form );
	$admin_parsed = parse_admin_key( $admin_lines, $form );
	$seed_path    = $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-' . $lower . '-answer-key.php';
	$seed         = ( static function ( $path ) {
		return include $path;
	} )( $seed_path );

	echo "=== Form {$form} admin key ===\n";
	echo 'DOCX records: ' . count( $admin_parsed ) . "\n";
	echo 'Seed records: ' . count( $seed ) . "\n";
	echo 'DOCX key sample: ' . implode( ',', array_slice( array_keys( $admin_parsed ), 0, 5 ) ) . "\n";
	echo 'Seed key sample: ' . implode( ',', array_slice( array_keys( $seed ), 0, 3 ) ) . "\n";
	if ( ! empty( $admin_parsed ) ) {
		$first = reset( $admin_parsed );
		echo 'First record q_num=' . ( $first['q_num'] ?? '?' ) . ' key=' . ( $first['correct_answer'] ?? '?' ) . ' status=' . ( $first['core_calibration_status'] ?? '?' ) . "\n";
	}

	$core = 0;
	$cal  = 0;
	foreach ( $admin_parsed as $r ) {
		if ( 'Core' === ( $r['core_calibration_status'] ?? '' ) ) {
			++$core;
		} elseif ( 'Calibration' === ( $r['core_calibration_status'] ?? '' ) ) {
			++$cal;
		}
	}
	echo "DOCX Core={$core} Calibration={$cal}\n";

	$mismatches = array();
	$code_prefix = ( 'A' === $form ) ? 'CTA-LMFT-CA-FA-' : 'CTA-LMFT-CA-FB-';
	for ( $i = 1; $i <= 150; $i++ ) {
		$code = sprintf( '%s%03d', $code_prefix, $i );
		$d    = $admin_parsed[ $i ] ?? null;
		$s    = $seed[ $code ] ?? null;
		if ( ! $d || ! $s ) {
			$mismatches[] = "Q{$i}: missing d=" . ( $d ? 'y' : 'n' ) . ' s=' . ( $s ? 'y' : 'n' );
			continue;
		}
		if ( strtoupper( (string) $d['correct_answer'] ) !== strtoupper( (string) ( $s['correct_option'] ?? '' ) ) ) {
			$mismatches[] = "Q{$i}:key {$d['correct_answer']}!=" . ( $s['correct_option'] ?? '' );
		}
		if ( (string) $d['core_calibration_status'] !== (string) ( $s['core_calibration_status'] ?? '' ) ) {
			$mismatches[] = "Q{$i}:status";
		}
		if ( (string) $d['item_id'] !== (string) ( $s['item_id'] ?? '' ) ) {
			$mismatches[] = "Q{$i}:item_id";
		}
		foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
			$seed_rat = (string) ( $s['rationales'][ $letter ] ?? '' );
			$docx_rat = (string) ( $d[ 'rat_' . $letter ] ?? '' );
			if ( norm_text( $docx_rat ) !== norm_text( $seed_rat ) ) {
				$mismatches[] = "Q{$i}:rat_{$letter}";
			}
		}
	}
	echo 'Admin mismatches: ' . count( $mismatches ) . "\n";
	foreach ( array_slice( $mismatches, 0, 25 ) as $m ) {
		echo "  - {$m}\n";
	}
	if ( count( $mismatches ) > 0 ) {
		$fail = 1;
	}
	echo "\n";
}

exit( $fail );
