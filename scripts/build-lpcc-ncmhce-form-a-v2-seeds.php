<?php
/**
 * Build LPCC NCMHCE Form A v2.0 learner items + admin-only answer key from parsed sources.
 *
 * CLI only. Never a learner route. Does not print correct letters.
 *
 * Usage: php scripts/build-lpcc-ncmhce-form-a-v2-seeds.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root       = dirname( __DIR__ );
$cand_json  = $root . '/_tmp_lpcc_form_a_v2/parsed.json';
$key_paras  = $root . '/_tmp_lpcc_form_a_v2_key/paras.txt';
$key_xml    = $root . '/_tmp_lpcc_form_a_v2_key/unzip/word/document.xml';
$report_path = $root . '/_tmp_lpcc_form_a_v2_key/match_report.txt';

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

/**
 * Normalize comparison text.
 *
 * @param string $text Raw.
 * @return string
 */
function fa_v2_norm( $text ) {
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

/**
 * PHP export with WordPress array() syntax.
 *
 * @param mixed $value Value.
 * @param int   $level Indent.
 * @return string
 */
function fa_v2_export( $value, $level = 0 ) {
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
			$out .= $pad1 . $key . fa_v2_export( $v, $level + 1 ) . ",\n";
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

// --- Flatten candidate questions in document order. ---
$cand_items = array();
foreach ( (array) ( $candidate['cases'] ?? array() ) as $case ) {
	$case_no = (int) ( $case['case_number'] ?? 0 );
	foreach ( (array) ( $case['sections'] ?? array() ) as $sec ) {
		$sec_no = (int) ( $sec['section_number'] ?? 0 );
		$stem   = (string) ( $sec['stem'] ?? '' );
		$first  = true;
		foreach ( (array) ( $sec['questions'] ?? array() ) as $q ) {
			$num = (int) ( $q['number'] ?? 0 );
			$cand_items[] = array(
				'case_number'    => $case_no,
				'case_title'     => (string) ( $case['title'] ?? '' ),
				'section_number' => $sec_no,
				'section_title'  => (string) ( $sec['title'] ?? '' ),
				'section_stem'   => $stem,
				'is_section_first' => $first,
				'number'         => $num,
				'question_text'  => (string) ( $q['question_text'] ?? '' ),
				'options'        => (array) ( $q['options'] ?? array() ),
			);
			$first = false;
		}
	}
}

if ( 143 !== count( $cand_items ) ) {
	fwrite( STDERR, 'Candidate flatten count=' . count( $cand_items ) . " (expected 143)\n" );
	exit( 1 );
}

// --- Parse key item tables (skip first 2 control tables and last summary table). ---
$xml = file_get_contents( $key_xml );
$xml = preg_replace( '/w:rsid[A-Za-z]*="[^"]*"/', '', $xml );
libxml_use_internal_errors( true );
$dom = new DOMDocument();
$dom->loadXML( $xml );
$xpath = new DOMXPath( $dom );
$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

$cell_text = static function ( DOMXPath $xpath, DOMNode $cell ) {
	$texts = $xpath->query( './/w:t', $cell );
	$line  = '';
	foreach ( $texts as $t ) {
		$line .= $t->textContent;
	}
	return trim( preg_replace( '/\s+/u', ' ', $line ) );
};

$tables     = $xpath->query( '//w:tbl' );
$table_meta = array();
$control_bits = array();
$ti = 0;
foreach ( $tables as $tbl ) {
	++$ti;
	$rows = $xpath->query( './w:tr', $tbl );
	$joined_rows = array();
	foreach ( $rows as $tr ) {
		$cells = $xpath->query( './w:tc', $tr );
		$vals  = array();
		foreach ( $cells as $tc ) {
			$vals[] = $cell_text( $xpath, $tc );
		}
		$joined_rows[] = implode( ' | ', $vals );
	}
	$blob = implode( ' ', $joined_rows );
	if ( 1 === $ti || 146 === $ti ) {
		$control_bits[] = $blob;
		continue;
	}
	if ( 2 === $ti ) {
		continue;
	}
	$key    = '';
	$status = '';
	$task   = '';
	$domain = '';
	if ( preg_match( '/Key:\s*([A-D])/i', $blob, $m ) ) {
		$key = strtolower( $m[1] );
	}
	if ( preg_match( '/Status:\s*(Scored|Field-test)/i', $blob, $m ) ) {
		$status = ( 'Scored' === $m[1] ) ? 'Scored' : 'Field-test';
	}
	if ( preg_match( '/Task:\s*([A-Z0-9\-]+)/i', $blob, $m ) ) {
		$task = strtoupper( $m[1] );
	}
	if ( preg_match( '/Domain:\s*([A-Z]+)/i', $blob, $m ) ) {
		$domain = strtoupper( $m[1] );
	}
	$table_meta[] = array(
		'correct_option' => $key,
		'item_status'    => $status,
		'task'           => $task,
		'domain'         => $domain,
	);
}

if ( 143 !== count( $table_meta ) ) {
	fwrite( STDERR, 'Item table count=' . count( $table_meta ) . " (expected 143)\n" );
	exit( 1 );
}

$pass_mentions = array();
foreach ( $control_bits as $blob ) {
	if ( preg_match( '/pass(ing)?|cut.?score|70\s*%|threshold/i', $blob ) ) {
		$pass_mentions[] = preg_replace( '/\b[A-D]{11,15}\b/', '[KEY-REDACTED]', $blob );
	}
}

// --- Parse key questions from paragraphs. ---
$key_items   = array();
$case_keys   = array();
$current_q   = null;
$current_case = 0;
$flush_q     = static function ( &$key_items, &$current_q ) {
	if ( $current_q ) {
		$key_items[ (int) $current_q['number'] ] = $current_q;
		$current_q = null;
	}
};

foreach ( $paras as $raw ) {
	$line = trim( (string) $raw );
	if ( '' === $line ) {
		continue;
	}

	if ( preg_match( '/^CASE\s+(\d+)\b/u', $line, $m ) ) {
		$flush_q( $key_items, $current_q );
		$current_case = (int) $m[1];
		continue;
	}

	if ( preg_match( '/Final key:\s*([A-D]{13})/i', $line, $m ) ) {
		$case_keys[ $current_case ] = strtoupper( $m[1] );
		continue;
	}

	if ( preg_match( '/^Q(\d+)\.\s+(.+)$/u', $line, $m ) ) {
		$flush_q( $key_items, $current_q );
		$current_q = array(
			'number'        => (int) $m[1],
			'case_number'   => $current_case,
			'question_text' => trim( $m[2] ),
			'options'       => array(),
			'why_best'      => '',
			'why_less'      => array(),
			'transfer'      => '',
		);
		continue;
	}

	if ( $current_q && preg_match( '/^([A-D])\.\s+(.+)$/u', $line, $m ) ) {
		$current_q['options'][ strtolower( $m[1] ) ] = trim( $m[2] );
		continue;
	}

	if ( $current_q && 0 === stripos( $line, 'Why the keyed answer is best:' ) ) {
		$current_q['why_best'] = trim( substr( $line, strlen( 'Why the keyed answer is best:' ) ) );
		continue;
	}

	if ( $current_q && preg_match( '/^Why ([A-D]) is less appropriate:\s*(.+)$/u', $line, $m ) ) {
		$current_q['why_less'][ strtoupper( $m[1] ) ] = trim( $m[2] );
		continue;
	}

	if ( $current_q && 0 === stripos( $line, 'Transfer rule:' ) ) {
		$current_q['transfer'] = trim( substr( $line, strlen( 'Transfer rule:' ) ) );
		continue;
	}

	if ( $current_q && preg_match( '/^SECTION\s+\d+/u', $line ) ) {
		$flush_q( $key_items, $current_q );
		continue;
	}
}
$flush_q( $key_items, $current_q );

$problems = array();
if ( 143 !== count( $key_items ) ) {
	$problems[] = 'Key parsed questions=' . count( $key_items ) . ' expected 143';
}

$scored_n     = 0;
$field_n      = 0;
$letter_dist  = array( 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0 );
$scored_dist  = array( 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0 );
$domain_dist  = array();
$learner_rows = array();
$admin_rows   = array();

for ( $i = 0; $i < 143; $i++ ) {
	$num  = $i + 1;
	$cand = $cand_items[ $i ];
	$key  = isset( $key_items[ $num ] ) ? $key_items[ $num ] : null;
	$meta = $table_meta[ $i ];
	$code = sprintf( 'CTA-LPCC-NCMHCE-FA-V2-%03d', $num );

	if ( ! $key ) {
		$problems[] = "Q{$num}: missing from answer key parse";
		continue;
	}

	if ( (int) $cand['number'] !== $num ) {
		$problems[] = "Q{$num}: candidate number is {$cand['number']}";
	}

	$stem_c = fa_v2_norm( $cand['question_text'] );
	$stem_k = fa_v2_norm( $key['question_text'] );
	if ( $stem_c !== $stem_k ) {
		$problems[] = "Q{$num}: stem mismatch (cand " . strlen( $stem_c ) . ' vs key ' . strlen( $stem_k ) . ')';
	}

	foreach ( array( 'a', 'b', 'c', 'd' ) as $opt ) {
		$oc = fa_v2_norm( $cand['options'][ $opt ] ?? '' );
		$ok = fa_v2_norm( $key['options'][ $opt ] ?? '' );
		if ( '' === $oc || '' === $ok ) {
			$problems[] = "Q{$num}: missing option " . strtoupper( $opt );
		} elseif ( $oc !== $ok ) {
			$problems[] = "Q{$num}: option " . strtoupper( $opt ) . ' mismatch';
		}
	}

	$table_letter = (string) $meta['correct_option'];
	$case_no      = (int) $cand['case_number'];
	$pos_in_case  = ( ( $num - 1 ) % 13 );
	$string_letter = '';
	if ( isset( $case_keys[ $case_no ] ) ) {
		$string_letter = strtolower( substr( $case_keys[ $case_no ], $pos_in_case, 1 ) );
	}

	$less_letters = array_keys( $key['why_less'] );
	$inferred     = array_values( array_diff( array( 'A', 'B', 'C', 'D' ), $less_letters ) );
	$inferred_l   = ( 1 === count( $inferred ) ) ? strtolower( $inferred[0] ) : '';

	if ( $table_letter !== $string_letter ) {
		$problems[] = "Q{$num}: table key disagrees with case Final key string";
	}
	if ( '' !== $inferred_l && $inferred_l !== $table_letter ) {
		$problems[] = "Q{$num}: inferred letter from 'less appropriate' lines disagrees with table key";
	}
	if ( ! in_array( $table_letter, array( 'a', 'b', 'c', 'd' ), true ) ) {
		$problems[] = "Q{$num}: invalid table correct letter";
	}

	$status = (string) $meta['item_status'];
	if ( 'Scored' === $status ) {
		++$scored_n;
		if ( isset( $scored_dist[ $table_letter ] ) ) {
			++$scored_dist[ $table_letter ];
		}
		$dom = (string) $meta['domain'];
		if ( ! isset( $domain_dist[ $dom ] ) ) {
			$domain_dist[ $dom ] = 0;
		}
		++$domain_dist[ $dom ];
	} elseif ( 'Field-test' === $status ) {
		++$field_n;
	} else {
		$problems[] = "Q{$num}: missing Scored/Field-test status";
	}

	if ( isset( $letter_dist[ $table_letter ] ) ) {
		++$letter_dist[ $table_letter ];
	}

	if ( '' === trim( (string) $key['why_best'] ) ) {
		$problems[] = "Q{$num}: missing 'Why the keyed answer is best'";
	}
	if ( 3 !== count( $key['why_less'] ) ) {
		$problems[] = "Q{$num}: expected 3 'less appropriate' lines, got " . count( $key['why_less'] );
	}
	if ( '' === trim( (string) $key['transfer'] ) ) {
		$problems[] = "Q{$num}: missing Transfer rule";
	}

	$expl_parts = array();
	if ( '' !== trim( (string) $key['why_best'] ) ) {
		$expl_parts[] = 'Why the keyed answer is best: ' . trim( $key['why_best'] );
	}
	foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
		if ( isset( $key['why_less'][ $letter ] ) ) {
			$expl_parts[] = 'Why ' . $letter . ' is less appropriate: ' . $key['why_less'][ $letter ];
		}
	}
	if ( '' !== trim( (string) $key['transfer'] ) ) {
		$expl_parts[] = 'Transfer rule: ' . trim( $key['transfer'] );
	}
	$explanation = implode( "\n\n", $expl_parts );

	$header = $cand['case_title'] . ' | ' . $cand['section_title'];
	if ( ! empty( $cand['is_section_first'] ) && '' !== trim( (string) $cand['section_stem'] ) ) {
		$qtext = $header . "\n\n" . trim( $cand['section_stem'] ) . "\n\n" . $cand['question_text'];
	} else {
		$qtext = $header . "\n\n" . $cand['question_text'];
	}

	$learner_rows[] = array(
		'question_code'  => $code,
		'question_text'  => $qtext,
		'option_a'       => (string) ( $cand['options']['a'] ?? '' ),
		'option_b'       => (string) ( $cand['options']['b'] ?? '' ),
		'option_c'       => (string) ( $cand['options']['c'] ?? '' ),
		'option_d'       => (string) ( $cand['options']['d'] ?? '' ),
		'correct_option' => 'x',
		'explanation'    => '',
	);

	$admin_rows[ $code ] = array(
		'correct_option' => $table_letter,
		'item_status'    => $status,
		'domain'         => (string) $meta['domain'],
		'task'           => (string) $meta['task'],
		'source_status'  => 'EXACT/FROZEN SOURCE',
		'explanation'    => $explanation,
	);
}

$control_joined = implode( ' ', $control_bits );
$expect_scored  = ( false !== strpos( $control_joined, '100 scored-style' ) );
$expect_field   = ( false !== strpos( $control_joined, '43 field-test-style' ) );
if ( 100 !== $scored_n || 43 !== $field_n ) {
	$problems[] = "Scored/field-test counts {$scored_n}/{$field_n} (expected 100/43)";
}
if ( array( 'a' => 25, 'b' => 25, 'c' => 25, 'd' => 25 ) !== $scored_dist ) {
	$problems[] = 'Scored letter distribution ' . json_encode( $scored_dist ) . ' (expected 25 each)';
}

$report   = array();
$report[] = 'LPCC NCMHCE Form A v2.0 answer-key match report';
$report[] = 'candidate_questions=' . count( $cand_items );
$report[] = 'key_questions=' . count( $key_items );
$report[] = 'item_tables=' . count( $table_meta );
$report[] = 'scored=' . $scored_n . ' field_test=' . $field_n;
$report[] = 'all_letter_dist=' . json_encode( $letter_dist );
$report[] = 'scored_letter_dist=' . json_encode( $scored_dist );
$report[] = 'scored_domain_dist=' . json_encode( $domain_dist );
$report[] = 'passing_percentage_in_source=' . ( $pass_mentions ? 'KEYWORD_HIT' : 'NOT_FOUND' );
$report[] = 'control_table_has_100_scored=' . ( $expect_scored ? 'yes' : 'no' );
$report[] = 'control_table_has_43_field_test=' . ( $expect_field ? 'yes' : 'no' );
$report[] = 'case_3_4_section_headings_in_key=SECTION_1_ONLY';
$report[] = 'rationale_release_in_key_doc=NOT_STATED';
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

$learner_path = $root . '/includes/quiz-seeds/lpcc-ncmhce-form-a-v2-items.php';
$admin_path   = $root . '/includes/quiz-seeds/admin-only/lpcc-ncmhce-form-a-v2-answer-key.php';

$learner_php  = "<?php\n";
$learner_php .= "/**\n";
$learner_php .= " * LPCC NCMHCE Form A v2.0 learner items (143). Staging / non-public until cutover.\n";
$learner_php .= " * Correct answers live in admin-only/lpcc-ncmhce-form-a-v2-answer-key.php.\n";
$learner_php .= " * Never expose this mapping via learner downloads.\n";
$learner_php .= " *\n * @package CTA_LMS\n */\n";
$learner_php .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
$learner_php .= 'return ' . fa_v2_export( $learner_rows ) . ";\n";

$admin_php  = "<?php\n";
$admin_php .= "/**\n";
$admin_php .= " * ADMIN ONLY — LPCC NCMHCE Form A v2.0 secured answer keys (143 items).\n";
$admin_php .= " * Merged into runtime quiz rows by CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync only.\n";
$admin_php .= " * Never registered as a learner download or exposed via learner AJAX before full submission.\n";
$admin_php .= " *\n * @package CTA_LMS\n */\n";
$admin_php .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
$admin_php .= 'return ' . fa_v2_export( $admin_rows ) . ";\n";

file_put_contents( $learner_path, $learner_php );
file_put_contents( $admin_path, $admin_php );

echo "wrote {$learner_path}\n";
echo "wrote {$admin_path}\n";
echo "OK\n";
