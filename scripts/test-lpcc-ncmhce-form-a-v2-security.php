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

$legacy_quiz = (object) array( 'quiz_type' => 'form_a' );
assert_true(
	! CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::uses_scored_field_test_scoring( $legacy_quiz ),
	'Live Form A quiz_type is not scored by the v2.0 scorer'
);

assert_true(
	false !== strpos( $ncmhce_sync, "'quiz_type' => 'form_a'" )
		&& false !== strpos( $ncmhce_sync, 'lpcc-ncmhce-form-a.php' ),
	'Program sync still points live Form A at the legacy seed'
);
assert_not_contains( $ncmhce_sync, 'form_a_v2', 'Program sync does not replace live Form A with v2.0' );
assert_true(
	false !== strpos( file_get_contents( $root . '/includes/class-cta-lpcc-ncmhce-form-a-v2-sync.php' ), "'status'          => 'draft'" ),
	'Form A v2.0 quiz is written as draft (non-public)'
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
	false !== strpos( $dashboard, 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync::is_staging_quiz' ),
	'Dashboard skips the staging Form A v2 quiz card'
);
assert_not_contains(
	file_get_contents( $root . '/includes/class-cta-exam-prep-workbooks.php' ),
	'form_a_v2',
	'Exam Center program-level types do not include form_a_v2'
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

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
