<?php
/**
 * Build LPCC NCMHCE Form B v2.0 admin-only answer key from parsed sources.
 *
 * Form B controlled key uses a paragraph format (not Form A item tables).
 *
 * Usage: php scripts/_extract_lpcc_form_b_v2_key_paras.php
 *        php scripts/build-lpcc-ncmhce-form-b-v2-seeds.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root        = dirname( __DIR__ );
$cand_json   = $root . '/_tmp_lpcc_form_b_v2/parsed.json';
$key_paras   = $root . '/_tmp_lpcc_form_b_v2_key/paras.txt';
$key_xml     = $root . '/_tmp_lpcc_form_b_v2_key/unzip/word/document.xml';
$report_path = $root . '/_tmp_lpcc_form_b_v2_key/match_report.txt';

if ( ! is_readable( $cand_json ) || ! is_readable( $key_paras ) || ! is_readable( $key_xml ) ) {
	fwrite( STDERR, "Missing parsed candidate JSON or key extract.\n" );
	exit( 1 );
}

$candidate = json_decode( file_get_contents( $cand_json ), true );
$paras     = file( $key_paras, FILE_IGNORE_NEW_LINES );
if ( ! is_array( $candidate ) || ! is_array( $paras ) ) {
	fwrite( STDERR, "Invalid source files.\n" );
	exit( 1 );
}

if ( ! function_exists( 'fb_v2_norm' ) ) {
	function fb_v2_norm( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$map  = array(
			"\xE2\x80\x98" => "'",
			"\xE2\x80\x99" => "'",
			"\xE2\x80\x9C" => '"',
			"\xE2\x80\x9D" => '"',
			"\xC2\xA0"     => ' ',
			'—'            => '-',
			'–'            => '-',
		);
		$text = strtr( $text, $map );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( (string) $text );
	}
}

if ( ! function_exists( 'fb_v2_export' ) ) {
	function fb_v2_export( $value, $level = 0 ) {
		$pad  = str_repeat( "\t", $level );
		$pad1 = str_repeat( "\t", $level + 1 );
		if ( is_array( $value ) ) {
			if ( array() === $value ) {
				return 'array()';
			}
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$out     = "array(\n";
			foreach ( $value as $k => $v ) {
				$key = $is_list ? '' : var_export( (string) $k, true ) . ' => ';
				$out .= $pad1 . $key . fb_v2_export( $v, $level + 1 ) . ",\n";
			}
			return $out . $pad . ')';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		return var_export( (string) $value, true );
	}
}

$cand_items = array();
foreach ( (array) ( $candidate['cases'] ?? array() ) as $case ) {
	foreach ( (array) ( $case['sections'] ?? array() ) as $sec ) {
		foreach ( (array) ( $sec['questions'] ?? array() ) as $q ) {
			$cand_items[] = array(
				'number'        => (int) ( $q['number'] ?? 0 ),
				'question_text' => (string) ( $q['question_text'] ?? '' ),
				'options'       => (array) ( $q['options'] ?? array() ),
			);
		}
	}
}

if ( 143 !== count( $cand_items ) ) {
	fwrite( STDERR, 'Candidate flatten count=' . count( $cand_items ) . " (expected 143)\n" );
	exit( 1 );
}

$compact_answers = array();
$xml             = file_get_contents( $key_xml );
$xml             = preg_replace( '/w:rsid[A-Za-z]*="[^"]*"/', '', $xml );
$dom             = new DOMDocument();
$dom->loadXML( $xml );
$xpath           = new DOMXPath( $dom );
$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
$cell_text       = static function ( DOMXPath $xpath, DOMNode $cell ) {
	$texts = $xpath->query( './/w:t', $cell );
	$line  = '';
	foreach ( $texts as $t ) {
		$line .= $t->textContent;
	}
	return trim( preg_replace( '/\s+/u', ' ', $line ) );
};
$tables = $xpath->query( '//w:tbl' );
foreach ( $tables as $tbl ) {
	$rows = $xpath->query( './w:tr', $tbl );
	foreach ( $rows as $tr ) {
		$cells = $xpath->query( './w:tc', $tr );
		$vals  = array();
		foreach ( $cells as $tc ) {
			$vals[] = $cell_text( $xpath, $tc );
		}
		for ( $i = 0; $i + 1 < count( $vals ); $i += 2 ) {
			if ( preg_match( '/^\d+$/', (string) $vals[ $i ] ) && preg_match( '/^[A-D]$/i', (string) $vals[ $i + 1 ] ) ) {
				$compact_answers[ (int) $vals[ $i ] ] = strtolower( $vals[ $i + 1 ] );
			}
		}
	}
}

$key_items    = array();
$current      = null;
$current_case = 0;
$flush        = static function () use ( &$key_items, &$current ) {
	if ( $current ) {
		$key_items[ (int) $current['number'] ] = $current;
		$current = null;
	}
};

foreach ( $paras as $raw ) {
	$line = trim( (string) $raw );
	if ( '' === $line ) {
		continue;
	}
	if ( preg_match( '/^Case\s+(\d+):/i', $line, $m ) ) {
		$flush();
		$current_case = (int) $m[1];
		continue;
	}
	if ( preg_match( '/^(\d{1,3})\.\s+(.+)$/u', $line, $m ) && (int) $m[1] <= 143 ) {
		$flush();
		$current = array(
			'number'        => (int) $m[1],
			'case_number'   => $current_case,
			'question_text' => trim( $m[2] ),
			'correct_option'=> '',
			'item_status'   => '',
			'domain'        => '',
			'task'          => '',
			'why_best'      => '',
			'why_not'       => array(),
			'transfer'      => '',
		);
		continue;
	}
	if ( ! $current ) {
		continue;
	}
	if ( preg_match( '/^Correct Answer:\s*([A-D])\./i', $line, $m ) ) {
		$current['correct_option'] = strtolower( $m[1] );
		continue;
	}
	if ( preg_match( '/^Domain:\s*([A-Z]+)/i', $line, $m ) ) {
		$current['domain'] = strtoupper( $m[1] );
	}
	if ( preg_match( '/Task:\s*([A-Z0-9\-]+)/i', $line, $m ) ) {
		$current['task'] = strtoupper( $m[1] );
	}
	if ( preg_match( '/Blueprint role:\s*(scored-style|field-test-style)/i', $line, $m ) ) {
		$current['item_status'] = ( false !== stripos( $m[1], 'field' ) ) ? 'Field-test' : 'Scored';
	}
	if ( 0 === stripos( $line, 'Why it is best:' ) ) {
		$current['why_best'] = trim( substr( $line, strlen( 'Why it is best:' ) ) );
		continue;
	}
	if ( preg_match( '/^Why ([A-D]) is not best:\s*(.+)$/u', $line, $m ) ) {
		$current['why_not'][ strtoupper( $m[1] ) ] = trim( $m[2] );
		continue;
	}
	if ( 0 === stripos( $line, 'Clinical discrimination / transfer rule:' ) ) {
		$current['transfer'] = trim( substr( $line, strlen( 'Clinical discrimination / transfer rule:' ) ) );
		continue;
	}
}
$flush();

$problems    = array();
$scored_n    = 0;
$field_n     = 0;
$scored_dist = array( 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0 );
$domain_dist = array();
$admin_rows  = array();
$pass_mentions = array();
foreach ( $paras as $raw ) {
	if ( preg_match( '/pass(ing)?|cut.?score|70\s*%|threshold/i', (string) $raw ) ) {
		$pass_mentions[] = (string) $raw;
	}
}

if ( 143 !== count( $key_items ) ) {
	$problems[] = 'Key parsed questions=' . count( $key_items ) . ' expected 143';
}
if ( 143 !== count( $compact_answers ) ) {
	$problems[] = 'Compact answer grid count=' . count( $compact_answers ) . ' expected 143';
}

for ( $i = 0; $i < 143; $i++ ) {
	$num  = $i + 1;
	$cand = $cand_items[ $i ];
	$key  = isset( $key_items[ $num ] ) ? $key_items[ $num ] : null;
	$code = sprintf( 'CTA-LPCC-NCMHCE-FB-V2-%03d', $num );

	if ( ! $key ) {
		$problems[] = "Q{$num}: missing from answer key parse";
		continue;
	}
	if ( (int) $cand['number'] !== $num ) {
		$problems[] = "Q{$num}: candidate number is {$cand['number']}";
	}
	if ( fb_v2_norm( $cand['question_text'] ) !== fb_v2_norm( $key['question_text'] ) ) {
		$problems[] = "Q{$num}: stem mismatch";
	}

	$letter = (string) $key['correct_option'];
	if ( ! in_array( $letter, array( 'a', 'b', 'c', 'd' ), true ) ) {
		$problems[] = "Q{$num}: invalid correct letter";
	}
	if ( isset( $compact_answers[ $num ] ) && $compact_answers[ $num ] !== $letter ) {
		$problems[] = "Q{$num}: compact grid letter disagrees with Correct Answer line";
	}

	$status = (string) $key['item_status'];
	if ( 'Scored' === $status ) {
		++$scored_n;
		++$scored_dist[ $letter ];
		$dom = (string) $key['domain'];
		if ( ! isset( $domain_dist[ $dom ] ) ) {
			$domain_dist[ $dom ] = 0;
		}
		++$domain_dist[ $dom ];
	} elseif ( 'Field-test' === $status ) {
		++$field_n;
	} else {
		$problems[] = "Q{$num}: missing scored/field-test blueprint role";
	}

	if ( '' === trim( (string) $key['why_best'] ) ) {
		$problems[] = "Q{$num}: missing why_best";
	}
	if ( 3 !== count( $key['why_not'] ) ) {
		$problems[] = "Q{$num}: expected 3 why-not lines, got " . count( $key['why_not'] );
	}
	if ( '' === trim( (string) $key['transfer'] ) ) {
		$problems[] = "Q{$num}: missing transfer rule";
	}

	$expl_parts = array();
	if ( '' !== trim( (string) $key['why_best'] ) ) {
		$expl_parts[] = 'Why the keyed answer is best: ' . trim( $key['why_best'] );
	}
	foreach ( array( 'A', 'B', 'C', 'D' ) as $opt_letter ) {
		if ( isset( $key['why_not'][ $opt_letter ] ) ) {
			$expl_parts[] = 'Why ' . $opt_letter . ' is less appropriate: ' . $key['why_not'][ $opt_letter ];
		}
	}
	if ( '' !== trim( (string) $key['transfer'] ) ) {
		$expl_parts[] = 'Transfer rule: ' . trim( $key['transfer'] );
	}

	$admin_rows[ $code ] = array(
		'correct_option' => $letter,
		'item_status'    => $status,
		'domain'         => (string) $key['domain'],
		'task'           => (string) $key['task'],
		'source_status'  => 'EXACT/FROZEN SOURCE',
		'explanation'    => implode( "\n\n", $expl_parts ),
	);
}

if ( 100 !== $scored_n || 43 !== $field_n ) {
	$problems[] = "Scored/field-test counts {$scored_n}/{$field_n} (expected 100/43)";
}

$report   = array();
$report[] = 'LPCC NCMHCE Form B v2.0 answer-key match report';
$report[] = 'candidate_questions=' . count( $cand_items );
$report[] = 'key_questions=' . count( $key_items );
$report[] = 'compact_grid=' . count( $compact_answers );
$report[] = 'scored=' . $scored_n . ' field_test=' . $field_n;
$report[] = 'scored_letter_dist=' . json_encode( $scored_dist );
$report[] = 'scored_domain_dist=' . json_encode( $domain_dist );
$report[] = 'passing_percentage_in_source=' . ( $pass_mentions ? 'KEYWORD_HIT' : 'NOT_FOUND' );
$report[] = 'rationale_release_in_key_doc=AFTER_FULL_FORM_COMPLETION_ONLY';
$report[] = 'mismatch_count=' . count( $problems );
foreach ( $problems as $p ) {
	$report[] = 'MISMATCH: ' . $p;
}
file_put_contents( $report_path, implode( "\n", $report ) . "\n" );
echo implode( "\n", $report ) . "\n";

if ( $problems ) {
	fwrite( STDERR, "Refusing to write seeds until mismatches are zero.\n" );
	exit( 1 );
}

$admin_path = $root . '/includes/quiz-seeds/admin-only/lpcc-ncmhce-form-b-v2-answer-key.php';
$admin_php  = "<?php\n";
$admin_php .= "/**\n";
$admin_php .= " * ADMIN ONLY — LPCC NCMHCE Form B v2.0 secured answer keys (143 items).\n";
$admin_php .= " * Merged into runtime quiz rows by CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync only.\n";
$admin_php .= " * Never registered as a learner download or exposed via learner AJAX before full submission.\n";
$admin_php .= " *\n * @package CTA_LMS\n */\n";
$admin_php .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
$admin_php .= 'return ' . fb_v2_export( $admin_rows ) . ";\n";
file_put_contents( $admin_path, $admin_php );
echo "wrote {$admin_path}\n";
echo "OK\n";
