<?php
/**
 * Security audit for LPCC NCMHCE Form A v2.0 staging build.
 *
 * Validates admin-key isolation, learner-seed placeholders, scored/field-test
 * mapping, and that live Form A is not replaced.
 *
 * Usage: php scripts/test-lpcc-ncmhce-form-a-v2-security.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code    = '';
		public $message = '';

		public function __construct( $code = '', $message = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		public function get_error_code() {
			return $this->code;
		}
	}
}

$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
	} else {
		++$fail;
		echo "FAIL: {$msg}\n";
	}
}

function assert_not_contains( $haystack, $needle, $msg ) {
	assert_true( false === strpos( (string) $haystack, (string) $needle ), $msg );
}

require_once $root . '/includes/class-cta-lpcc-ncmhce-form-a-v2-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-a-v2-scoring.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-a-v2-answer-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-legacy-forms-archive.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-a-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-b-sync.php';

$quiz_php     = file_get_contents( $root . '/public/class-cta-quiz.php' );
$quiz_partial = file_get_contents( $root . '/templates/partials/quiz-question.php' );
$materials    = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
$lms          = file_get_contents( $root . '/cta-lms.php' );
$ncmhce_sync  = file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-sync.php' );
$exam_center  = file_get_contents( $root . '/includes/class-cta-exam-prep-exam-center.php' );
$dashboard    = file_get_contents( $root . '/public/class-cta-student-dashboard.php' );
$htaccess     = file_get_contents( $root . '/includes/quiz-seeds/admin-only/.htaccess' );
$learner_src  = file_get_contents( $root . '/includes/quiz-seeds/lpcc-ncmhce-form-a-v2-items.php' );
$admin_src    = file_get_contents( $root . '/includes/quiz-seeds/admin-only/lpcc-ncmhce-form-a-v2-answer-key.php' );
$legacy_src   = file_get_contents( $root . '/includes/quiz-seeds/lpcc-ncmhce-form-a.php' );

$learner = include $root . '/includes/quiz-seeds/lpcc-ncmhce-form-a-v2-items.php';
$admin   = include $root . '/includes/quiz-seeds/admin-only/lpcc-ncmhce-form-a-v2-answer-key.php';
$legacy  = include $root . '/includes/quiz-seeds/lpcc-ncmhce-form-a.php';

echo "=== LPCC NCMHCE Form A v2.0 Security Check ===\n\n";

assert_true( is_array( $learner ) && 143 === count( $learner ), 'Learner seed has 143 items' );
assert_true( is_array( $admin ) && 143 === count( $admin ), 'Admin key has 143 items' );
assert_true( is_array( $legacy ) && 143 === count( $legacy ), 'Legacy live Form A seed still has 143 items' );

assert_true(
	false !== strpos( CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync::get_answer_key_path(), 'admin-only' ),
	'Form A v2 admin key path is under admin-only/'
);
assert_true( false !== strpos( $htaccess, 'Deny from all' ), 'admin-only .htaccess denies all direct access' );
assert_true( false !== strpos( $admin_src, 'ADMIN ONLY' ), 'Admin key file marked ADMIN ONLY' );

assert_not_contains( $learner_src, "'correct_option' => 'a'", 'Learner seed has no correct_option a' );
assert_not_contains( $learner_src, "'correct_option' => 'b'", 'Learner seed has no correct_option b' );
assert_not_contains( $learner_src, "'correct_option' => 'c'", 'Learner seed has no correct_option c' );
assert_not_contains( $learner_src, "'correct_option' => 'd'", 'Learner seed has no correct_option d' );
assert_not_contains( $learner_src, 'Why the keyed answer is best', 'Learner seed has no rationale bodies' );
assert_not_contains( $learner_src, 'item_status', 'Learner seed has no item_status' );
assert_not_contains( $learner_src, 'source_status', 'Learner seed has no source_status' );

$placeholder_ok = true;
foreach ( $learner as $row ) {
	if ( 'x' !== ( $row['correct_option'] ?? '' ) || '' !== (string) ( $row['explanation'] ?? '' ) ) {
		$placeholder_ok = false;
		break;
	}
}
assert_true( $placeholder_ok, 'Every learner row uses correct_option x and empty explanation' );

$valid = CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync::validate_answer_key( $admin );
assert_true( true === $valid, 'Admin answer key validates (143 mapped, 100 scored / 43 field-test)' );

$codes = CTA_Lpcc_Ncmhce_Form_A_V2_Sync::get_question_code_order_map();
assert_true( 143 === count( $codes ), 'Learner code map has 143 question_codes' );
$codes_match = true;
foreach ( $codes as $index => $code ) {
	if ( empty( $admin[ $code ] ) ) {
		$codes_match = false;
		break;
	}
}
assert_true( $codes_match, 'Every learner question_code has an admin key row' );

assert_true(
	null === CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::source_passing_percent(),
	'Passing percent is unspecified in the v2.0 source key (not assumed 70)'
);
assert_true(
	100 === CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::SCORED_ITEM_COUNT
		&& 43 === CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::FIELD_TEST_ITEM_COUNT,
	'Scored/field-test counts match the control record (100 / 43)'
);

$fake_quiz = (object) array( 'quiz_type' => 'form_a_v2' );
assert_true(
	CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::withholds_pass_fail( $fake_quiz ),
	'Form A v2 withholds pass/fail until a source cut score is confirmed'
);

$legacy_quiz = (object) array( 'quiz_type' => 'form_a', 'status' => 'archived' );
assert_true(
	! CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::uses_scored_field_test_scoring( $legacy_quiz ),
	'Archived legacy Form A is not scored by the v2.0 scorer'
);

$live_v2_quiz = (object) array( 'quiz_type' => 'form_a', 'status' => 'active' );
assert_true(
	class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' )
		&& CTA_Lpcc_Ncmhce_Form_A_Sync::is_live_v2_quiz( $live_v2_quiz ),
	'Active live form_a uses v2.0 scoring after cutover'
);

assert_true(
	false !== strpos( $ncmhce_sync, 'is_v2_cutover_complete' ),
	'Program sync skips legacy re-seed after v2 cutover'
);
assert_true(
	false !== strpos( $lms, 'perform_v2_cutover' ),
	'Atomic v2 cutover runs on plugin upgrade 1.0.265'
);
assert_true(
	false !== strpos( file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-form-a-v2-sync.php' ), "'status'          => 'draft'" ),
	'Staging form_a_v2 sync remains draft-only (pre-cutover loader)'
);

assert_not_contains( $quiz_partial, 'correct_option', 'Quiz question partial never references correct_option' );
assert_not_contains( $quiz_partial, 'explanation', 'Quiz question partial never references explanation' );
assert_not_contains( $quiz_partial, 'question_code', 'Quiz question partial never renders question_code' );
assert_true(
	false !== strpos( $quiz_php, "'html'               => \$this->render_quiz_questions" ),
	'Start/resume AJAX returns HTML only (no answer map JSON)'
);
assert_true(
	false !== strpos( $quiz_php, 'cta_lms_reveal_quiz_explanations' ),
	'Explanations are gated behind the post-submit reveal filter'
);

assert_true(
	false !== strpos( $materials, 'lpcc-ncmhce-form-a-v2-answer-key.php' ),
	'Form A v2 answer key filename blocked in is_admin_restricted_source_path'
);
assert_true(
	false !== strpos( $materials, 'controlled_answer_key_rationales' ),
	'Controlled answer-key DOCX filename marker is blocked from downloads'
);
assert_not_contains( $materials, 'get_answer_records', 'Materials serve handler does not expose answer records API' );

$docx_hits = array();
$materials_dir = $root . '/assets/course-materials';
if ( is_dir( $materials_dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $materials_dir ) );
	foreach ( $it as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$name = strtolower( $file->getFilename() );
		$path = strtolower( str_replace( '\\', '/', $file->getPathname() ) );
		if ( false !== strpos( $path, '/lpcc-ncmhce/' )
			&& ( false !== strpos( $name, 'form_a_v2' ) || false !== strpos( $name, 'form_a_v2.0_controlled' ) )
			&& false !== strpos( $name, 'controlled' ) ) {
			$docx_hits[] = $file->getPathname();
		}
	}
}
assert_true( empty( $docx_hits ), 'Answer-key DOCX is not copied into learner materials' );

assert_true(
	false !== strpos( $dashboard, 'CTA_Lpcc_Ncmhce_Form_V2_Scoring_Bridge::is_staging_quiz' ),
	'Dashboard skips staging Form A/B v2 quiz cards'
);
assert_not_contains(
	file_get_contents( $root . '/includes/class-cta-exam-prep-workbooks.php' ),
	'form_a_v2',
	'Exam Center program-level types do not include form_a_v2'
);
assert_not_contains(
	file_get_contents( $root . '/includes/class-cta-exam-prep-workbooks.php' ),
	'form_b_v2',
	'Exam Center program-level types do not include form_b_v2'
);

assert_true(
	false !== strpos( $lms, 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync::sync' )
		&& false !== strpos( $lms, 'CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync::sync_answer_keys' ),
	'Upgrade loads staging items then merges secured answers'
);

$q1 = $learner[0]['question_text'] ?? '';
assert_true(
	false !== strpos( $q1, 'Which follow-up inquiry would MOST directly clarify' ),
	'Q1 candidate stem is present on the first learner item'
);
assert_true(
	'c' === strtolower( (string) ( $admin['CTA-LPCC-NCMHCE-FA-V2-001']['correct_option'] ?? '' ) ),
	'Q1 admin key is mapped (letter present and valid)'
);

$perfect_questions = array();
$perfect_answers   = array();
foreach ( $codes as $index => $code ) {
	$id = $index + 1;
	$perfect_questions[] = (object) array(
		'id'             => $id,
		'order_index'    => $index,
		'correct_option' => $admin[ $code ]['correct_option'],
	);
	$perfect_answers[ $id ] = $admin[ $code ]['correct_option'];
}
$perfect = CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::calculate_display_score(
	$perfect_questions,
	$perfect_answers,
	$fake_quiz
);
assert_true( 100 === (int) $perfect['score'], 'All-correct fixture scores 100% of scored items' );
assert_true( 100 === (int) $perfect['scored_correct'], 'All-correct fixture has 100 scored items correct' );
assert_true( empty( $perfect['passed'] ), 'All-correct fixture still withholds pass/fail' );

$zero_answers = array();
foreach ( $codes as $index => $code ) {
	$id     = $index + 1;
	$status = (string) ( $admin[ $code ]['item_status'] ?? '' );
	$right  = (string) $admin[ $code ]['correct_option'];
	$zero_answers[ $id ] = ( 'Field-test' === $status ) ? $right : ( ( 'a' === $right ) ? 'b' : 'a' );
}
$zero = CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::calculate_display_score( $perfect_questions, $zero_answers, $fake_quiz );
assert_true( 0 === (int) $zero['score'] && 0 === (int) $zero['scored_correct'], 'Field-test-only correct answers yield 0% scored' );

echo "\n=== LPCC NCMHCE Form B v2.0 Security Check ===\n\n";

require_once $root . '/includes/class-cta-lpcc-ncmhce-form-b-v2-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-b-v2-scoring.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-b-v2-answer-sync.php';
require_once $root . '/includes/class-cta-lpcc-ncmhce-form-v2-scoring-bridge.php';

$learner_b_src = file_get_contents( $root . '/includes/quiz-seeds/lpcc-ncmhce-form-b-v2-items.php' );
$admin_b_src   = file_get_contents( $root . '/includes/quiz-seeds/admin-only/lpcc-ncmhce-form-b-v2-answer-key.php' );
$legacy_b_src  = file_get_contents( $root . '/includes/quiz-seeds/lpcc-ncmhce-form-b.php' );
$learner_b     = include $root . '/includes/quiz-seeds/lpcc-ncmhce-form-b-v2-items.php';
$admin_b       = include $root . '/includes/quiz-seeds/admin-only/lpcc-ncmhce-form-b-v2-answer-key.php';
$legacy_b      = include $root . '/includes/quiz-seeds/lpcc-ncmhce-form-b.php';

assert_true( is_array( $learner_b ) && 143 === count( $learner_b ), 'Form B learner seed has 143 items' );
assert_true( is_array( $admin_b ) && 143 === count( $admin_b ), 'Form B admin key has 143 items' );
assert_true( is_array( $legacy_b ) && 143 === count( $legacy_b ), 'Legacy live Form B seed still has 143 items' );
assert_true(
	false !== strpos( CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync::get_answer_key_path(), 'admin-only' ),
	'Form B v2 admin key path is under admin-only/'
);
assert_true( false !== strpos( $admin_b_src, 'ADMIN ONLY' ), 'Form B admin key file marked ADMIN ONLY' );
assert_not_contains( $learner_b_src, "'correct_option' => 'a'", 'Form B learner seed has no correct_option a' );
assert_not_contains( $learner_b_src, 'Why the keyed answer is best', 'Form B learner seed has no rationale bodies' );

$valid_b = CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync::validate_answer_key( $admin_b );
assert_true( true === $valid_b, 'Form B admin answer key validates (143 mapped, 100 scored / 43 field-test)' );

$codes_b = CTA_Lpcc_Ncmhce_Form_B_V2_Sync::get_question_code_order_map();
assert_true( 143 === count( $codes_b ), 'Form B learner code map has 143 question_codes' );
$codes_b_match = true;
foreach ( $codes_b as $code ) {
	if ( empty( $admin_b[ $code ] ) ) {
		$codes_b_match = false;
		break;
	}
}
assert_true( $codes_b_match, 'Every Form B learner question_code has an admin key row' );
assert_true(
	null === CTA_Lpcc_Ncmhce_Form_B_V2_Scoring::source_passing_percent(),
	'Form B passing percent is unspecified in the v2.0 source key (not assumed 70)'
);
assert_true(
	false !== strpos( $materials, 'lpcc-ncmhce-form-b-v2-answer-key.php' ),
	'Form B v2 answer key filename blocked in is_admin_restricted_source_path'
);
assert_true(
	false !== strpos( $lms, 'CTA_Lpcc_Ncmhce_Form_B_V2_Sync::sync' )
		&& false !== strpos( $lms, 'CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync::sync_answer_keys' ),
	'Upgrade loads Form B staging items then merges secured answers'
);
assert_not_contains( $ncmhce_sync, 'form_b_v2', 'Program sync does not replace live Form B with v2.0' );
assert_true(
	false !== strpos( file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-form-b-v2-sync.php' ), "'status'          => 'draft'" ),
	'Form B v2.0 quiz is written as draft (non-public)'
);
assert_true(
	false !== strpos( $quiz_php, 'CTA_Lpcc_Ncmhce_Form_V2_Scoring_Bridge' ),
	'Quiz handler uses shared LPCC v2 scoring bridge'
);

$fake_quiz_b = (object) array( 'quiz_type' => 'form_b_v2' );
assert_true(
	CTA_Lpcc_Ncmhce_Form_V2_Scoring_Bridge::withholds_pass_fail( $fake_quiz_b ),
	'Form B v2 withholds pass/fail until a source cut score is confirmed'
);
assert_true(
	'c' === strtolower( (string) ( $admin_b['CTA-LPCC-NCMHCE-FB-V2-001']['correct_option'] ?? '' ) ),
	'Form B Q1 admin key is mapped (letter present and valid)'
);

$perfect_b_questions = array();
$perfect_b_answers   = array();
foreach ( $codes_b as $index => $code ) {
	$id = $index + 1;
	$perfect_b_questions[] = (object) array(
		'id'             => $id,
		'order_index'    => $index,
		'correct_option' => $admin_b[ $code ]['correct_option'],
	);
	$perfect_b_answers[ $id ] = $admin_b[ $code ]['correct_option'];
}
$perfect_b = CTA_Lpcc_Ncmhce_Form_B_V2_Scoring::calculate_display_score(
	$perfect_b_questions,
	$perfect_b_answers,
	$fake_quiz_b
);
assert_true( 100 === (int) $perfect_b['score'], 'Form B all-correct fixture scores 100% of scored items' );
assert_true( empty( $perfect_b['passed'] ), 'Form B all-correct fixture still withholds pass/fail' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
