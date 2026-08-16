<?php
/**
 * Build lmft-clinical-form-a-items-26-50.php from embedded PROMPT 02 JSON.
 *
 * Usage: php scripts/build-lmft-clinical-form-a-items-26-50.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );
$json = file_get_contents( __DIR__ . '/_lmft-form-a-prompt-02.json' );
if ( false === $json ) {
	fwrite( STDERR, "Missing scripts/_lmft-form-a-prompt-02.json\n" );
	exit( 1 );
}

$data = json_decode( $json, true );
if ( ! is_array( $data ) || empty( $data['questions'] ) ) {
	fwrite( STDERR, "Invalid JSON payload\n" );
	exit( 1 );
}

$out = "<?php\n";
$out .= "/**\n";
$out .= " * LMFT California Clinical — Comprehensive Simulation Form A items 26–50 (PROMPT 02).\n";
$out .= " * Learner-facing stems and choices only. Answer keys deferred to admin merge.\n";
$out .= " *\n";
$out .= " * @package CTA_LMS\n";
$out .= " */\n";
$out .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
$out .= "return array(\n";

foreach ( $data['questions'] as $row ) {
	$num = (int) ( $row['q_num'] ?? 0 );
	$stem = (string) ( $row['stem'] ?? '' );
	$choices = isset( $row['choices'] ) && is_array( $row['choices'] ) ? $row['choices'] : array();

	$out .= "\tarray(\n";
	$out .= "\t\t'question_code'  => 'CTA-LMFT-CA-FA-" . str_pad( (string) $num, 3, '0', STR_PAD_LEFT ) . "',\n";
	$out .= "\t\t'question_text'  => " . var_export( $stem, true ) . ",\n";
	$out .= "\t\t'option_a'       => " . var_export( (string) ( $choices['A'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'option_b'       => " . var_export( (string) ( $choices['B'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'option_c'       => " . var_export( (string) ( $choices['C'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'option_d'       => " . var_export( (string) ( $choices['D'] ?? '' ), true ) . ",\n";
	$out .= "\t\t'correct_option' => 'x',\n";
	$out .= "\t\t'explanation'    => '',\n";
	$out .= "\t),\n";
}

$out .= ");\n";

$target = $root . '/includes/quiz-seeds/lmft-clinical-form-a-items-26-50.php';
file_put_contents( $target, $out );
echo "Wrote {$target}\n";
