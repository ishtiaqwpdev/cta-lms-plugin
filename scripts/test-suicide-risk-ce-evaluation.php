<?php
/**
 * Validation for CTA-CE-003 course evaluation + inline attestation (Chunk 6).
 *
 * Usage: php scripts/test-suicide-risk-ce-evaluation.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

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

require_once $root . '/includes/class-cta-suicide-risk-evaluation-sync.php';

$syllabus_path = $root . '/includes/syllabus/cta-syllabus-data.php';
$syllabus      = is_readable( $syllabus_path ) ? include $syllabus_path : array();
$sra_syllabus  = null;
foreach ( (array) $syllabus as $entry ) {
	if ( is_array( $entry ) && 'CTA-CE-003' === (string) ( $entry['course_code'] ?? '' ) ) {
		$sra_syllabus = $entry;
		break;
	}
}

assert_true( is_array( $sra_syllabus ), 'Chunk 1 syllabus entry for CTA-CE-003 found' );

$syllabus_los = isset( $sra_syllabus['learning_objectives'] ) && is_array( $sra_syllabus['learning_objectives'] )
	? $sra_syllabus['learning_objectives']
	: array();
$eval_los     = CTA_Suicide_Risk_Evaluation_Sync::evaluation_learning_objectives();

assert_true( 6 === count( $eval_los ), 'Evaluation defines 6 learning objectives' );
assert_true( $syllabus_los === $eval_los, 'Section 4 objectives match Chunk 1 learner outcomes exactly (word for word)' );

$questions = CTA_Suicide_Risk_Evaluation_Sync::get_questions();
assert_true( ! empty( $questions ), 'Evaluation seed loads' );

$by_id = array();
foreach ( $questions as $row ) {
	$key = sanitize_key( (string) ( $row['id'] ?? '' ) );
	if ( '' !== $key ) {
		$by_id[ $key ] = $row;
	}
}

$required_keys = array(
	'participant_cert_name',
	'participant_email',
	'participant_license_type',
	'participant_license_number',
	'participant_state_jurisdiction',
	'participant_completion_date',
	'sra_eval_obj01',
	'sra_eval_obj02',
	'sra_eval_obj03',
	'sra_eval_obj04',
	'sra_eval_obj05',
	'sra_eval_obj06',
	'sra_eval_level',
	'sra_eval_relevance',
	'sra_eval_presentation',
	'sra_eval_materials',
	'sra_eval_currency',
	'sra_eval_inst_know',
	'sra_eval_inst_clear',
	'sra_eval_inst_resp',
	'sra_eval_admin',
	'sra_eval_tech_support',
	'sra_eval_tech_learn',
	'sra_eval_tech_use',
	'sra_eval_strengths',
	'sra_eval_improve',
	'sra_eval_future',
	'sra_attest_complete',
	'sra_attest_signature',
	'sra_attest_date',
);

foreach ( $required_keys as $key ) {
	assert_true( isset( $by_id[ $key ] ), "Field ID present: {$key}" );
}

for ( $i = 0; $i < 6; $i++ ) {
	$obj_key = sprintf( 'sra_eval_obj%02d', $i + 1 );
	assert_true(
		isset( $by_id[ $obj_key ] ) && (string) $by_id[ $obj_key ]['label'] === (string) $eval_los[ $i ],
		"Objective {$obj_key} label matches syllabus LO " . ( $i + 1 )
	);
	$opts = isset( $by_id[ $obj_key ]['options'] ) && is_array( $by_id[ $obj_key ]['options'] )
		? $by_id[ $obj_key ]['options']
		: array();
	assert_true( ! isset( $opts['na'] ), "{$obj_key} does not allow N/A" );
}

$section5_no_na = array(
	'sra_eval_level',
	'sra_eval_relevance',
	'sra_eval_presentation',
	'sra_eval_materials',
	'sra_eval_currency',
	'sra_eval_organization',
	'sra_eval_pacing',
	'sra_eval_downloads',
);
foreach ( $section5_no_na as $key ) {
	$opts = isset( $by_id[ $key ]['options'] ) && is_array( $by_id[ $key ]['options'] )
		? $by_id[ $key ]['options']
		: array();
	assert_true( isset( $by_id[ $key ] ) && ! isset( $opts['na'] ), "Section 5 {$key} does not allow N/A" );
}

assert_true(
	isset( $by_id['sra_eval_inst_resp']['options']['na'] ),
	'Section 6 instructor responsiveness allows N/A'
);

$section7_with_na = array(
	'sra_eval_admin',
	'sra_eval_tech_support',
	'sra_eval_tech_learn',
	'sra_eval_tech_use',
	'sra_eval_media',
	'sra_eval_facilities',
);
foreach ( $section7_with_na as $key ) {
	$opts = isset( $by_id[ $key ]['options'] ) && is_array( $by_id[ $key ]['options'] )
		? $by_id[ $key ]['options']
		: array();
	assert_true( isset( $opts['na'] ), "Section 7 {$key} allows N/A" );
}

$attest_text = CTA_Suicide_Risk_Evaluation_Sync::attestation_statement();
assert_true(
	false !== strpos(
		$attest_text,
		'I personally completed all six required instructional modules in this asynchronous course and completed the final examination'
	),
	'Attestation statement matches approved verbatim wording'
);

assert_true(
	'sra_attest_complete' === sanitize_key( 'SRA_ATTEST_COMPLETE' ),
	'SRA_ATTEST_COMPLETE maps to sanitized question key sra_attest_complete'
);

$lms          = file_get_contents( $root . '/cta-lms.php' );
$ce           = file_get_contents( $root . '/includes/class-cta-ce-completion.php' );
$eval_q       = file_get_contents( $root . '/includes/class-cta-evaluation-questions.php' );
$quiz_tpl     = file_get_contents( $root . '/templates/quiz.php' );
$quiz_class   = file_get_contents( $root . '/public/class-cta-quiz.php' );

assert_true( false !== strpos( $lms, '1.0.219' ), 'Version bump 1.0.219' );
assert_true( method_exists( 'CTA_Suicide_Risk_Evaluation_Sync', 'ensure' ), 'Evaluation sync exposes ensure() self-heal' );
assert_true( false !== strpos( $lms, 'CTA_Suicide_Risk_Evaluation_Sync::ensure' ), 'Upgrade hook heals evaluation' );
assert_true( false !== strpos( $lms, 'CTA_Suicide_Risk_Evaluation_Sync::sync' ), 'Upgrade hook seeds evaluation' );
assert_true( false !== strpos( $eval_q, 'CTA_Suicide_Risk_Evaluation_Sync::ensure' ), 'Evaluation ensure routes CTA-CE-003 to dedicated sync' );
assert_true( false !== strpos( $quiz_class, 'CTA_Suicide_Risk_Evaluation_Sync::ensure' ), 'Quiz page self-heals evaluation after exam pass' );
assert_true( false !== strpos( $ce, 'inline_attestation_config' ), 'CE completion exposes inline attestation config' );
assert_true( false !== strpos( $ce, 'sra_attest_complete' ), 'SRA attestation keys registered in completion helper' );
assert_true( false !== strpos( $quiz_tpl, 'CTA_CE_Completion::evaluation_includes_inline_attestation' ), 'Quiz template uses inline attestation helper' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
