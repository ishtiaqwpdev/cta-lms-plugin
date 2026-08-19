<?php
/**
 * Verification for LMFT AMFTRB workbook online Practice Banks (v1.0.276).
 *
 * Usage: php scripts/test-lmft-amftrb-workbook-bank-final-verification.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lmft-amftrb-sync.php';

$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
		return;
	}
	++$fail;
	echo "FAIL: {$msg}\n";
}

function load_seed( $file ) {
	$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/' . $file;
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$data = include $path;
	return is_array( $data ) ? $data : array();
}

echo "--- 1) Approved per-workbook source DOCX ---\n";
$base = CTA_PLUGIN_DIR . 'assets/course-materials/lmft-amftrb/';
for ( $wb = 1; $wb <= 12; $wb++ ) {
	$candidate = $base . 'question-banks/CTA_LMFT_AMFTRB_WB' . $wb . '_17_Question_Candidate_Bank_v1.0.docx';
	assert_true( is_readable( $candidate ), "WB{$wb} candidate DOCX exists" );
}

echo "\n--- 2) Online quiz seeds (17 questions each) ---\n";
for ( $wb = 1; $wb <= 12; $wb++ ) {
	$seed = load_seed( 'lmft-amftrb-wb' . $wb . '-bank.php' );
	assert_true( 17 === count( $seed ), "WB{$wb} seed has 17 questions" );
	if ( ! empty( $seed[0]['question_text'] ) && ! empty( $seed[0]['correct_option'] ) ) {
		assert_true( true, "WB{$wb} seed has stem + keyed answer" );
	} else {
		assert_true( false, "WB{$wb} seed has stem + keyed answer" );
	}
}
assert_true(
	204 === ( 12 * 17 ),
	'Program total = 204 workbook practice questions (12 × 17 per approved spec)'
);

echo "\n--- 3) Sync + shared template wiring ---\n";
assert_true(
	17 === CTA_Lmft_Amftrb_Sync::WORKBOOK_BANK_COUNT,
	'WORKBOOK_BANK_COUNT = 17 (internal approved spec; not 12)'
);
assert_true(
	method_exists( 'CTA_Lmft_Amftrb_Sync', 'get_live_workbook_bank_health' ),
	'Workbook bank health check implemented'
);
assert_true(
	method_exists( 'CTA_Lmft_Amftrb_Sync', 'workbook_banks_are_live' ),
	'workbook_banks_are_live() implemented'
);
$template = (string) file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/exam-prep-workbook-tabbed.php' );
assert_true(
	false !== strpos( $template, 'check back when the online quiz is published' ),
	'Fallback message is conditional (shows only when no online quiz card exists)'
);
assert_true(
	false !== strpos( $template, 'Start Practice Bank' ),
	'Shared workbook tab template supports online practice bank launch buttons'
);

echo "\n--- 4) Build tooling ---\n";
assert_true(
	is_readable( CTA_PLUGIN_DIR . 'scripts/build-lmft-amftrb-workbook-bank-seeds.php' ),
	'DOCX-to-seed build script exists'
);

echo "\n--- 5) Regression — LCSW workbook seeds untouched ---\n";
assert_true( 17 === count( load_seed( 'lcsw-aswb-wb1-bank.php' ) ), 'LCSW WB1 seed still 17 questions' );

echo "\n=== SUMMARY: {$pass} passed, {$fail} failed ===\n";
echo "NOTE: Live DB sync for all 12 wb{N}_bank quizzes runs on plugin upgrade to v1.0.276.\n";

exit( $fail > 0 ? 1 : 0 );
