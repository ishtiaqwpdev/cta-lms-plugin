<?php
/**
 * Build lmft-clinical-form-a-answer-key-26-50.php from embedded PROMPT 14 JSON.
 *
 * Usage: php scripts/build-lmft-clinical-form-a-answer-key-26-50.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );
$json = file_get_contents( __DIR__ . '/_lmft-form-a-admin-prompt-14.json' );
if ( false === $json ) {
	fwrite( STDERR, "Missing scripts/_lmft-form-a-admin-prompt-14.json\n" );
	exit( 1 );
}

$data = json_decode( $json, true );
if ( ! is_array( $data ) || empty( $data['records'] ) ) {
	fwrite( STDERR, "Invalid JSON payload\n" );
	exit( 1 );
}

$out = "<?php\n";
$out .= "/**\n";
$out .= " * ADMIN ONLY — LMFT California Clinical Form A answer key items 26–50 (PROMPT 14).\n";
$out .= " * Secured answer + rationale mapping. Never exposed to learners.\n";
$out .= " *\n";
$out .= " * @package CTA_LMS\n";
$out .= " */\n";
$out .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
$out .= "return array(\n";

foreach ( $data['records'] as $row ) {
	$num = (int) ( $row['q_num'] ?? 0 );
	$code = 'CTA-LMFT-CA-FA-' . str_pad( (string) $num, 3, '0', STR_PAD_LEFT );
	$correct = strtolower( (string) ( $row['correct_answer'] ?? '' ) );
	$rationales = isset( $row['rationales'] ) && is_array( $row['rationales'] ) ? $row['rationales'] : array();

	$out .= "\t'{$code}' => array(\n";
	$out .= "\t\t'correct_option'            => " . var_export( $correct, true ) . ",\n";
	$out .= "\t\t'item_id'                   => " . var_export( (string) ( $row['item_id'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'area'                      => " . var_export( (string) ( $row['area'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'core_calibration_status'   => " . var_export( (string) ( $row['core_calibration_status'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'source_status'             => " . var_export( (string) ( $row['source_status'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'rationales'                => array(\n";
	foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
		$out .= "\t\t\t'{$letter}' => " . var_export( (string) ( $rationales[ $letter ] ?? '' ), true ) . ",\n";
	}
	$out .= "\t\t),\n";
	$out .= "\t),\n";
}

$out .= ");\n";

$target = $root . '/includes/quiz-seeds/admin-only/lmft-clinical-form-a-answer-key-26-50.php';
file_put_contents( $target, $out );
echo "Wrote {$target}\n";
