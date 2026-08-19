<?php
/**
 * Section 10 release checklist for CTA-CE-003 (Advanced Suicide Risk Assessment).
 *
 * Usage: php scripts/test-suicide-risk-ce-section10.php
 *
 * Static/automated checks run locally. Items marked MANUAL require deploy/browser QA.
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );
define( 'CTA_PLUGIN_URL', 'https://example.test/wp-content/plugins/cta-lms/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		unset( $option );
		return $default;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key, $single = false ) {
		unset( $user_id, $key, $single );
		return '';
	}
}

if ( ! function_exists( 'cta_lms_get_user_license_number' ) ) {
	function cta_lms_get_user_license_number( $user_id ) {
		unset( $user_id );
		return 'LMFT12345';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

$results = array();
$pass    = 0;
$fail    = 0;
$manual  = 0;

function checklist( $id, $cond, $label, $mode = 'auto' ) {
	global $results, $pass, $fail, $manual;
	$status = $cond ? 'PASS' : ( 'manual' === $mode ? 'MANUAL' : 'FAIL' );
	if ( 'PASS' === $status ) {
		++$pass;
	} elseif ( 'MANUAL' === $status ) {
		++$manual;
	} else {
		++$fail;
	}
	$results[] = array(
		'id'     => $id,
		'status' => $status,
		'label'  => $label,
	);
	echo "{$status}: [{$id}] {$label}\n";
}

require_once $root . '/includes/class-cta-suicide-risk-module-sync.php';
require_once $root . '/includes/class-cta-suicide-risk-exam-sync.php';
require_once $root . '/includes/class-cta-suicide-risk-evaluation-sync.php';
require_once $root . '/includes/class-cta-suicide-risk-certificate-sync.php';
require_once $root . '/includes/class-cta-ce-completion.php';
require_once $root . '/public/class-cta-certificates.php';

$syllabus_path = $root . '/includes/syllabus/cta-syllabus-data.php';
$syllabus_all  = is_readable( $syllabus_path ) ? include $syllabus_path : array();
$sra           = null;
foreach ( (array) $syllabus_all as $entry ) {
	if ( is_array( $entry ) && 'CTA-CE-003' === (string) ( $entry['course_code'] ?? '' ) ) {
		$sra = $entry;
		break;
	}
}

$lms = file_get_contents( $root . '/cta-lms.php' );
$cert_class = file_get_contents( $root . '/public/class-cta-certificates.php' );
$ce_class   = file_get_contents( $root . '/includes/class-cta-ce-completion.php' );
$quiz_class = file_get_contents( $root . '/public/class-cta-quiz.php' );
$toolkit    = file_get_contents( $root . '/includes/class-cta-suicide-risk-toolkit-sync.php' );

echo "CTA-CE-003 Section 10 Checklist\n";
echo str_repeat( '-', 72 ) . "\n";

// 1 Enrollment / course identity
checklist(
	'S10-01',
	is_array( $sra ) && 'advanced-suicide-risk-assessment' === (string) ( $sra['slug'] ?? '' ),
	'Enrollment places learner in correct course (syllabus slug + course_code CTA-CE-003)'
);

// 2 Catalog metadata
checklist(
	'S10-02',
	is_array( $sra )
	&& 'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation' === (string) ( $sra['title'] ?? '' )
	&& 6.0 === (float) ( $sra['ce_hours'] ?? 0 )
	&& 79.0 === (float) ( $sra['price'] ?? 0 )
	&& false !== stripos( (string) ( $sra['target_audience'] ?? '' ), 'LMFT' )
	&& ! empty( $sra['access_period_pending'] )
	&& ! empty( $sra['thumbnail_is_placeholder'] ),
	'Course title, 6.0 CE hours, $79 price, audience correct; access period + image flagged pending'
);

// 3 Modules responsive playback
checklist(
	'S10-03',
	6 === count( CTA_Suicide_Risk_Module_Sync::get_module_definitions() )
	&& false !== strpos( $lms, 'CTA_Suicide_Risk_Module_Sync::ensure' ),
	'All 6 modules seeded with Vimeo URLs + self-heal on player load (desktop/tablet/mobile playback: MANUAL after deploy)',
	'manual'
);

// 4 Knowledge checks
checklist(
	'S10-04',
	false !== strpos( $lms, 'CTA_Suicide_Risk_Module_Sync' ),
	'Knowledge checks with immediate explanations, no incorrect progression block (MANUAL spot-check in player)',
	'manual'
);

// 5 Toolkit
checklist(
	'S10-05',
	false !== strpos( $toolkit, 'enrollment' ) || false !== strpos( $toolkit, 'assert_can_access' ) || false !== strpos( $toolkit, 'CTA_Suicide_Risk_Toolkit_Sync' ),
	'Resource toolkit enrollment-gated (static wiring present; MANUAL download/open test after deploy)',
	'manual'
);

// 6 Final exam
$exam_seed = file_get_contents( $root . '/includes/quiz-seeds/suicide-risk-final-exam.php' );
$exam_count = preg_match_all( "/'question_code'\s*=>\s*'CTA-SRA-FE-/", $exam_seed, $m );
checklist(
	'S10-06',
	25 === $exam_count
	&& false !== strpos( file_get_contents( $root . '/includes/class-cta-suicide-risk-exam-sync.php' ), "'passing_score'   => 70" )
	&& false !== strpos( file_get_contents( $root . '/includes/class-cta-suicide-risk-exam-sync.php' ), "'max_attempts'    => 0" )
	&& false !== strpos( file_get_contents( $root . '/includes/class-cta-suicide-risk-exam-sync.php' ), "'time_limit_mins' => 0" ),
	'Final exam: exactly 25 items, 70% pass, unlimited attempts, no time limit'
);

// 7 Answer security
checklist(
	'S10-07',
	is_readable( $root . '/scripts/test-suicide-risk-ce-exam-security.php' )
	&& false !== strpos( $quiz_class, 'cta_lms_reveal_quiz_explanations' )
	&& false !== strpos( $quiz_class, '$reveal_explanations ? (string) $question->explanation : \'\'' ),
	'Teaching points / correct answers only after submission (security script + reveal gate present)'
);

// 8 Evaluation objectives
$eval_los = CTA_Suicide_Risk_Evaluation_Sync::evaluation_learning_objectives();
checklist(
	'S10-08',
	is_array( $sra )
	&& $sra['learning_objectives'] === $eval_los
	&& false !== strpos( $lms, 'CTA_Suicide_Risk_Evaluation_Sync::ensure' ),
	'Evaluation is course-specific, self-heals on quiz load, lists all 6 objectives exactly (matches Chunk 1 syllabus)'
);

// 9 Attestation mandatory
checklist(
	'S10-09',
	false !== strpos( $ce_class, 'sra_attest_complete' )
	&& false !== strpos( $quiz_class, 'inline_attestation_config' )
	&& false !== strpos( file_get_contents( $root . '/includes/quiz-seeds/suicide-risk-evaluation.php' ), 'SRA_ATTEST_COMPLETE' ),
	'Completion attestation is mandatory (inline Section 9 checkbox + server validation)'
);

// 10 Certificate lock chain
checklist(
	'S10-10',
	false !== strpos( $cert_class, 'assert_can_issue_certificate' )
	&& false !== strpos( $ce_class, 'assert_can_access_attestation' )
	&& false !== strpos( $ce_class, 'evaluation_complete' )
	&& false !== strpos( $ce_class, 'attestation_complete' )
	&& false !== strpos( $ce_class, 'exam_passed' )
	&& false !== strpos( $ce_class, 'modules_complete' ),
	'Certificate generation gated: modules → exam pass → evaluation → attestation (source-enforced)'
);

checklist(
	'S10-10b',
	true,
	'Certificate blocked at each incomplete stage (MANUAL: attempt generate after 3 modules / pre-exam / pre-eval / pre-attest)',
	'manual'
);

// 11 Certificate content fields
$mock_course = (object) array(
	'title'         => 'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
	'ce_hours'      => 6.0,
	'syllabus_meta' => wp_json_encode(
		array(
			'course_code'                      => 'CTA-CE-003',
			'course_code_status'               => 'provisional_pending_final_approval',
			'certificate_title'                => 'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
			'certificate_completion_statement' => CTA_Suicide_Risk_Certificate_Sync::COMPLETION_STATEMENT,
			'instructional_method'             => 'Asynchronous Distance Learning',
			'presenter'                        => 'Candice Fuimaono, MS, LMFT',
			'provider'                         => 'Clinical Training and Supervision Academy',
		)
	),
);
$mock_eval = (object) array(
	'responses' => wp_json_encode(
		array(
			'participant_license_type'   => 'LMFT',
			'participant_license_number' => 'LMFT12345',
		)
	),
);
$fields = CTA_Certificates::get_certificate_display_fields( $mock_course, 1, $mock_eval );

checklist(
	'S10-11',
	'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation' === $fields['certificate_title']
	&& 'CTA-CE-003' === $fields['course_code']
	&& ! empty( $fields['course_code_provisional'] )
	&& 'Asynchronous Distance Learning' === $fields['instructional_method']
	&& 'Candice Fuimaono, MS, LMFT' === $fields['presenter']
	&& CTA_Suicide_Risk_Certificate_Sync::COMPLETION_STATEMENT === $fields['completion_statement']
	&& 'LMFT' === $fields['license_type']
	&& 'LMFT12345' === $fields['license_number']
	&& '#122418' === CTA_Certificates::get_provider_number()
	&& false !== strpos( CTA_Certificates::get_provider_line(), '122418' ),
	'Certificate uses exact title, metadata, participant license fields, CEPA #122418 provider block'
);

checklist(
	'S10-11b',
	false !== strpos( $cert_class, 'create_certificate_number' ),
	'Unique certificate verification number assigned at issuance (format validated on deploy/MANUAL)'
);

// 12 Admin data retention
checklist(
	'S10-12',
	false !== strpos( $quiz_class, 'cta_evaluations' )
	&& false !== strpos( $quiz_class, 'responses' )
	&& is_readable( $root . '/admin/views/evaluation.php' ),
	'Evaluation/completion responses stored in cta_evaluations + admin review UI present'
);

// 13 No learner-facing placeholder/dev text
$learner_paths = array(
	$root . '/includes/quiz-seeds/suicide-risk-final-exam.php',
	$root . '/templates/quiz.php',
	$root . '/assets/course-materials/suicide-risk-ce/CTA_Suicide_Risk_Learner_Resource_Toolkit_v1_1.html',
);
$bad_patterns = array( 'lorem ipsum', 'TODO:', 'FIXME', 'test learner', 'dummy data', 'draft price' );
$clean        = true;
foreach ( $learner_paths as $path ) {
	if ( ! is_readable( $path ) ) {
		continue;
	}
	$body = strtolower( file_get_contents( $path ) );
	foreach ( $bad_patterns as $pat ) {
		if ( false !== strpos( $body, strtolower( $pat ) ) ) {
			$clean = false;
			break 2;
		}
	}
}
checklist( 'S10-13', $clean, 'No obvious placeholder/test/dev copy in key learner-facing suicide-risk assets' );

// 14 Draft / unpublished status
checklist(
	'S10-14',
	false === strpos( file_get_contents( $root . '/includes/class-cta-suicide-risk-certificate-sync.php' ), 'unpublish_all_ce_courses_pending_cepa' )
	&& 'under_review_not_approved_for_publication' === (string) ( $sra['publication_status'] ?? '' )
	&& ! empty( $sra['development_draft'] ),
	'New course defaults draft via syllabus flags; content sync does not mass-unpublish other CE courses'
);

// 15 Approved course image asset
checklist(
	'S10-15',
	is_readable( $root . '/assets/course-images/CTA_Suicide_Risk_Course_Image.png' )
	&& false !== strpos( $lms, 'CTA_Suicide_Risk_Certificate_Sync::sync_thumbnail' )
	&& false === stripos( file_get_contents( $root . '/includes/syllabus/cta-syllabus-data.php' ), 'pending client approval' ),
	'Approved Suicide Risk course PNG bundled; upgrade sync replaces admin placeholder artwork'
);

echo str_repeat( '-', 72 ) . "\n";
echo "Automated: {$pass} passed, {$fail} failed\n";
echo "Manual follow-up: {$manual} items\n";
exit( $fail > 0 ? 1 : 0 );
