<?php
/**
 * ADMIN ONLY — LMFT California Clinical Form A secured answer keys (150 items).
 *
 * PROMPT 13: answer key + rationales 1–25 imported verbatim.
 * PROMPT 14: answer key + rationales 26–50 imported verbatim.
 * PROMPT 15: answer key + rationales 51–75 imported verbatim.
 * PROMPT 16: answer key + rationales 76–100 imported verbatim.
 * PROMPT 17: answer key + rationales 101–125 imported verbatim.
 * PROMPT 18: answer key + rationales 126–150 imported verbatim (complete set).
 *
 * Merged into runtime quiz rows by CTA_Lmft_Clinical_Form_A_Answer_Sync only.
 * Never registered as a learner download or exposed via learner AJAX.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$chunks = array(
	__DIR__ . '/lmft-clinical-form-a-answer-key-01-25.php',
	__DIR__ . '/lmft-clinical-form-a-answer-key-26-50.php',
	__DIR__ . '/lmft-clinical-form-a-answer-key-51-75.php',
	__DIR__ . '/lmft-clinical-form-a-answer-key-76-100.php',
	__DIR__ . '/lmft-clinical-form-a-answer-key-101-125.php',
	__DIR__ . '/lmft-clinical-form-a-answer-key-126-150.php',
);

$records = array();
foreach ( $chunks as $chunk ) {
	if ( ! is_readable( $chunk ) ) {
		continue;
	}
	$rows = include $chunk;
	if ( is_array( $rows ) ) {
		$records = array_merge( $records, $rows );
	}
}

return $records;
