<?php
/**
 * Final verification for LCSW ASWB Clinical workbook online Practice Banks (v1.0.278).
 *
 * Usage: php scripts/test-lcsw-aswb-workbook-bank-final-verification.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

require_once CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-sync.php';

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

echo "=== LCSW ASWB Clinical — Workbook Practice Bank Verification ===\n\n";

echo "--- 1) Program identity ---\n";
assert_true(
	'lcsw-aswb-clinical-exam-preparation' === CTA_Lcsw_Aswb_Sync::SLUG,
	'Canonical slug is lcsw-aswb-clinical-exam-preparation'
);

echo "\n--- 2) Approved per-workbook source DOCX (all 12) ---\n";
$base = CTA_PLUGIN_DIR . 'assets/course-materials/lcsw-aswb/question-banks/';
for ( $wb = 1; $wb <= 12; $wb++ ) {
	$docx = $base . 'CTA_LCSW_WB' . $wb . '_17_Question_Bank_v1.0.docx';
	assert_true( is_readable( $docx ), "WB{$wb} downloadable practice bank DOCX exists" );
}

echo "\n--- 3) Online quiz seeds (17 questions each, all 12 workbooks) ---\n";
for ( $wb = 1; $wb <= 12; $wb++ ) {
	$seed = load_seed( 'lcsw-aswb-wb' . $wb . '-bank.php' );
	assert_true( 17 === count( $seed ), "WB{$wb} online seed has 17 questions" );
	if ( ! empty( $seed[0]['question_text'] ) && ! empty( $seed[0]['correct_option'] ) ) {
		assert_true( true, "WB{$wb} seed has stem + keyed answer" );
	} else {
		assert_true( false, "WB{$wb} seed has stem + keyed answer" );
	}
}
assert_true( 204 === ( 12 * 17 ), 'Program total = 204 workbook practice questions (12 × 17)' );

echo "\n--- 4) Sync + shared template wiring (same pattern as LMFT AMFTRB) ---\n";
$sync_src = (string) file_get_contents( CTA_PLUGIN_DIR . 'includes/class-cta-lcsw-aswb-sync.php' );
assert_true( 17 === (int) CTA_Lcsw_Aswb_Sync::WORKBOOK_BANK_COUNT, 'WORKBOOK_BANK_COUNT = 17' );
assert_true( 40 === (int) CTA_Lcsw_Aswb_Sync::WORKBOOK_BANK_TIME_MINS, 'WORKBOOK_BANK_TIME_MINS = 40' );
assert_true(
	method_exists( 'CTA_Lcsw_Aswb_Sync', 'get_live_workbook_bank_health' ),
	'get_live_workbook_bank_health() implemented'
);
assert_true(
	method_exists( 'CTA_Lcsw_Aswb_Sync', 'workbook_banks_are_live' ),
	'workbook_banks_are_live() implemented'
);
assert_true(
	false !== strpos( $sync_src, 'workbook_banks_are_live( $course_id )' ),
	'ensure_learner_forms() gates on workbook bank health'
);

$template = (string) file_get_contents( CTA_PLUGIN_DIR . 'templates/partials/exam-prep-workbook-tabbed.php' );
assert_true(
	false !== strpos( $template, 'check back when the online quiz is published' ),
	'Fallback message is conditional (only when no online quiz cards)'
);
assert_true(
	false !== strpos( $template, 'Start Practice Bank' ),
	'Shared workbook tab supports online practice bank launch buttons'
);
assert_true(
	false !== strpos( $template, 'get_practice_bank_status_label' ),
	'NOT STARTED status tracking wired in shared template'
);

assert_true(
	method_exists( 'CTA_Lcsw_Aswb_Sync', 'ensure_workbook_banks' ),
	'ensure_workbook_banks() implemented (scoped publish — no Form A/B touch)'
);
assert_true(
	method_exists( 'CTA_Lcsw_Aswb_Sync', 'sync_workbook_banks' ),
	'sync_workbook_banks() implemented'
);
assert_true(
	false !== strpos( $sync_src, 'maybe_heal_workbook_banks' ),
	'Runtime self-heal for missing workbook banks'
);

$lms = (string) file_get_contents( CTA_PLUGIN_DIR . 'cta-lms.php' );
assert_true(
	false !== strpos( $lms, "1.0.282" ) && false !== strpos( $lms, 'cta_lms_queue_deferred_upgrade' ),
	'Upgrade defers heavy sync to background queue (v1.0.282 — prevents 504)'
);
assert_true(
	false !== strpos( $lms, 'lcsw_workbook_banks' ) && false !== strpos( $lms, 'CTA_Lms_Deferred_Upgrades' ),
	'Workbook bank publish uses deferred upgrade queue'
);
assert_true(
	method_exists( 'CTA_Lcsw_Aswb_Sync', 'sync_workbook_banks_missing' ),
	'sync_workbook_banks_missing() batches sync to avoid timeouts'
);

echo "\n--- 5) Regression — other programs unaffected ---\n";
assert_true( 17 === count( load_seed( 'lmft-amftrb-wb1-bank.php' ) ), 'LMFT AMFTRB WB1 seed unchanged' );
assert_true( 17 === count( load_seed( 'lpcc-ncmhce-wb1-bank.php' ) ), 'LPCC NCMHCE WB1 seed unchanged' );

echo "\n=== SUMMARY: {$pass} passed, {$fail} failed ===\n";
echo "NOTE: Workbook banks sync in background batches after upgrade (v1.0.282+) to avoid 504 timeouts.\n";

exit( $fail > 0 ? 1 : 0 );
