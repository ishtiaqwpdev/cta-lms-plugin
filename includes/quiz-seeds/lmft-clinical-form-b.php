<?php
/**
 * CTA LMFT California Clinical — Comprehensive Simulation Form B (150 items).
 *
 * PROMPT 07: learner items 1–25 imported verbatim.
 * PROMPT 08: learner items 26–50 imported verbatim.
 * PROMPT 09: learner items 51–75 imported verbatim.
 * PROMPT 10: learner items 76–100 imported verbatim.
 * PROMPT 11: learner items 101–125 imported verbatim.
 * PROMPT 12: learner items 126–150 imported verbatim.
 *
 * Question order and A–D choice order are fixed (no randomization).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$chunks = array(
	__DIR__ . '/lmft-clinical-form-b-items-01-25.php',
	__DIR__ . '/lmft-clinical-form-b-items-26-50.php',
	__DIR__ . '/lmft-clinical-form-b-items-51-75.php',
	__DIR__ . '/lmft-clinical-form-b-items-76-100.php',
	__DIR__ . '/lmft-clinical-form-b-items-101-125.php',
	__DIR__ . '/lmft-clinical-form-b-items-126-150.php',
);

$questions = array();
foreach ( $chunks as $chunk ) {
	if ( ! is_readable( $chunk ) ) {
		continue;
	}
	$rows = include $chunk;
	if ( is_array( $rows ) ) {
		$questions = array_merge( $questions, $rows );
	}
}

for ( $num = count( $questions ) + 1; $num <= 150; $num++ ) {
	$questions[] = array(
		'question_code'  => sprintf( 'CTA-LMFT-CA-FB-%03d', $num ),
		'question_text'  => sprintf( '[Import pending — Comprehensive Simulation Form B item %d]', $num ),
		'option_a'       => 'Import pending',
		'option_b'       => 'Import pending',
		'option_c'       => 'Import pending',
		'option_d'       => 'Import pending',
		'correct_option' => 'x',
		'explanation'    => '',
	);
}

return $questions;
