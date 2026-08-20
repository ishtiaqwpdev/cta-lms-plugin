<?php
/**
 * Final verification for LMFT California Clinical Form A (150 Q, 240 min).
 *
 * Usage: php scripts/test-lmft-clinical-form-a-final-verification.php
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
if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
	define( 'CTA_PLUGIN_DIR', $root . '/' );
}

$pass = 0;
$fail = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
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

/**
 * Extract plain text from a DOCX file.
 *
 * @param string $path Absolute path.
 * @return string
 */
function cta_extract_docx_text( $path ) {
	if ( ! is_readable( $path ) || ! class_exists( 'ZipArchive' ) ) {
		return '';
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return '';
	}

	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();

	if ( ! is_string( $xml ) || '' === $xml ) {
		return '';
	}

	$text = preg_replace( '/<w:tab[^>]*\/>/', "\t", $xml );
	$text = preg_replace( '/<w:br[^>]*\/>/', "\n", (string) $text );
	$text = strip_tags( (string) $text );
	$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/[ \t]+/u', ' ', (string) $text );
	$text = preg_replace( '/\n{3,}/u', "\n\n", (string) $text );

	return trim( (string) $text );
}

require_once $root . '/includes/class-cta-lmft-clinical-legacy-forms-archive.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php';

echo "=== LMFT California Clinical Form A Final Verification ===\n\n";

$form_a_docx = $root . '/New folder/CTA_LMFT_CA_Clinical_Exam_Prep_Final_Form_A_FINAL.docx';
$key_docx    = $root . '/New folder/CTA_LMFT_CA_Clinical_Exam_Prep_Final_Admin_Key_Rationales_FINAL.docx';

assert_true( is_readable( $form_a_docx ), 'Form A FINAL DOCX present in New folder/' );
assert_true( is_readable( $key_docx ), 'Admin Key FINAL DOCX present in New folder/' );

$questions = CTA_Lmft_Clinical_Form_A_Sync::get_questions();
$answers   = CTA_Lmft_Clinical_Form_A_Answer_Sync::get_answer_records();

assert_true( 150 === count( $questions ), 'Seed bank defines exactly 150 questions' );
assert_true( 150 === CTA_Lmft_Clinical_Form_A_Sync::count_imported_items( $questions ), 'All 150 learner items imported (no placeholders)' );
assert_true( 150 === count( $answers ), 'Admin answer key defines 150 records' );
assert_true( true === CTA_Lmft_Clinical_Form_A_Answer_Sync::validate_answer_key( $answers ), 'Admin answer key validates' );
assert_true( 240 === CTA_Lmft_Clinical_Form_A_Sync::TIME_LIMIT_MINS, 'Time limit is 240 minutes' );
assert_true(
	'Form A — Comprehensive Simulation' === CTA_Lmft_Clinical_Form_A_Sync::FORM_TITLE,
	'Form A title matches approved specification'
);
assert_true(
	'active' === CTA_Lmft_Clinical_Form_A_Sync::resolve_quiz_status( $questions ),
	'Form A resolves to active status once fully imported'
);

$q1_seed = (string) ( $questions[0]['question_text'] ?? '' );
assert_true(
	false !== stripos( $q1_seed, CTA_Lmft_Clinical_Legacy_Forms_Archive::FINAL_FORM_A_Q1_NEEDLE ),
	'Seed Q1 matches August 14 Final fingerprint'
);

$form_a_text = cta_extract_docx_text( $form_a_docx );
$key_text    = cta_extract_docx_text( $key_docx );

$parse_issues = array();
if ( '' === $form_a_text ) {
	$parse_issues[] = 'Could not extract text from Form A FINAL DOCX';
} elseif ( false === stripos( $form_a_text, CTA_Lmft_Clinical_Legacy_Forms_Archive::FINAL_FORM_A_Q1_NEEDLE ) ) {
	$parse_issues[] = 'Form A FINAL DOCX Q1 fingerprint not found in extracted text';
}

if ( '' === $key_text ) {
	$parse_issues[] = 'Could not extract text from Admin Key FINAL DOCX';
} elseif ( false === stripos( $key_text, 'Gender dysphoria' )
	&& false === stripos( $key_text, 'Form A' ) ) {
	$parse_issues[] = 'Admin Key FINAL DOCX missing expected Form A key content (Q1 rationale markers)';
}

assert_true( empty( $parse_issues ), 'FINAL DOCX sources align with seed fingerprints' );
if ( ! empty( $parse_issues ) ) {
	foreach ( $parse_issues as $issue ) {
		echo "  NOTE: {$issue}\n";
	}
}

$lms     = file_get_contents( $root . '/cta-lms.php' );
$legacy  = file_get_contents( $root . '/includes/class-cta-lmft-clinical-legacy-forms-archive.php' );
$admin   = file_get_contents( $root . '/admin/class-cta-admin.php' );

assert_true( false !== strpos( $lms, "1.0.279" ), 'Plugin version bumped to 1.0.279' );
assert_true(
	false !== strpos( $lms, "version_compare( \$installed, '1.0.279', '<' )" ),
	'Upgrade hook 1.0.279 forces Form A sync + archived duplicate purge'
);
assert_true(
	1 === preg_match(
		'/version_compare\( \$installed, \'1\.0\.279\', \'<\' \)[\s\S]*?CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys\( true \)/',
		$lms
	),
	'Upgrade 1.0.279 syncs Form A only (questions + answer keys)'
);
assert_true(
	0 === preg_match(
		'/version_compare\( \$installed, \'1\.0\.279\', \'<\' \)[\s\S]*?CTA_Lmft_Clinical_Form_B_Sync::sync/',
		$lms
	),
	'Upgrade 1.0.279 does not re-sync Form B'
);
assert_true(
	false !== strpos( $legacy, 'purge_archived_duplicate_form_quizzes' ),
	'Legacy archive can purge archived duplicate quiz rows'
);
assert_true(
	false !== strpos( $legacy, 'filter_admin_assessment_quizzes' ),
	'Legacy archive filters archived rows from admin dropdown'
);
assert_true(
	false !== strpos( $admin, 'filter_admin_assessment_quizzes' ),
	'Admin course edit uses archived quiz filter for LMFT Clinical'
);

echo "\n--- Storage summary ---\n";
echo "Learner questions: includes/quiz-seeds/lmft-clinical-form-a.php (+ items 01-25 … 126-150)\n";
echo "Admin answer key:  includes/quiz-seeds/admin-only/lmft-clinical-form-a-answer-key.php\n";
echo "DB tables:         wp_cta_quizzes (quiz_type=form_a) + wp_cta_quiz_questions\n";
echo "Imported count:    150 questions, 150 answer-key records\n";
echo "Parse issues:      " . ( empty( $parse_issues ) ? 'none' : implode( '; ', $parse_issues ) ) . "\n";

echo "\n=== Summary ===\nPASS: {$pass}\nFAIL: {$fail}\n";
exit( $fail > 0 ? 1 : 0 );
