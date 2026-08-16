<?php
/**
 * Verify Study Schedules matching never binds Workbook N titles.
 *
 * Run: php scripts/test-exam-prep-study-schedules-match.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CTA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function __( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function sanitize_title( $title ) {
	$title = strtolower( (string) $title );
	$title = preg_replace( '/[^a-z0-9]+/', '-', $title );
	return trim( $title, '-' );
}

require_once CTA_PLUGIN_DIR . 'includes/class-cta-exam-prep-getting-started.php';

$passed = 0;
$failed = 0;

function assert_test( $condition, $label ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "PASS: {$label}\n";
		++$passed;
		return;
	}
	echo "FAIL: {$label}\n";
	++$failed;
}

$should_match = array(
	'Student Roadmap and 10-, 14-, and 18-Week Study Schedules',
	'Student Start-Here Roadmap and 10-, 14-, and 18-Week Study Schedules',
	'Start Here — Learner Roadmap, Schedules, and Progress Tools',
	'R9A — Study Schedules and Error Log',
	'CTA Study Schedules Toolkit',
	'10-Week / 14-Week / 18-Week Pacing Options',
);

$should_not_match = array(
	'Workbook 10 — Progress Evaluation, Research Literacy, Plan Revision, Termination, and Continuity (Student Workbook)',
	'Workbook 10: Case Management, Advocacy, Resources, and Collaboration',
	'Workbook 10 — Multicultural, Developmental, Career, Spiritual, Disability, and Context-Responsive Counseling (Student Workbook)',
	'Workbook 10 — Developmental, Cultural, and Contextual Interventions (Student Workbook)',
	'Workbook 1: Exam Strategy',
	'Workbook 14 — Something',
	'Start Here: Program Orientation',
	'Practice Examination A — 50-Question Assessment',
	'Flashcard Study Center',
);

foreach ( $should_match as $title ) {
	assert_test(
		CTA_Exam_Prep_Getting_Started::is_study_schedule_resource_title( $title ),
		'Matches schedule title: ' . $title
	);
}

foreach ( $should_not_match as $title ) {
	assert_test(
		! CTA_Exam_Prep_Getting_Started::is_study_schedule_resource_title( $title ),
		'Rejects non-schedule title: ' . $title
	);
}

// Simulate mixed resource order: Workbook 10 first, then real schedules.
$resources = array(
	(object) array(
		'id'    => 101,
		'title' => 'Workbook 10 — Progress Evaluation, Research Literacy, Plan Revision, Termination, and Continuity (Student Workbook)',
	),
	(object) array(
		'id'    => 202,
		'title' => 'Student Roadmap and 10-, 14-, and 18-Week Study Schedules',
	),
);

if ( ! class_exists( 'CTA_Course_Materials' ) ) {
	class CTA_Course_Materials {
		public static function get_serve_url( $id ) {
			return 'https://example.test/serve/' . (int) $id;
		}
	}
}

$ref = new ReflectionClass( 'CTA_Exam_Prep_Getting_Started' );
$method = $ref->getMethod( 'attach_resource_links' );
$method->setAccessible( true );
$config = array(
	'study_schedules' => array(
		'intro'          => 'Choose a schedule',
		'combined_url'   => '',
		'combined_title' => '',
	),
	'readiness'       => array(
		'summary' => '',
		'url'     => '',
		'title'   => '',
	),
);
$out = $method->invoke( null, $config, $resources );

assert_test(
	false === stripos( (string) ( $out['study_schedules']['combined_title'] ?? '' ), 'Workbook 10' ),
	'attach_resource_links prefers real schedule over Workbook 10'
);
assert_test(
	false !== stripos( (string) ( $out['study_schedules']['combined_title'] ?? '' ), 'Study Schedules' )
	|| false !== stripos( (string) ( $out['study_schedules']['combined_title'] ?? '' ), 'Roadmap' ),
	'attach_resource_links binds the roadmap/schedule document'
);
assert_test(
	'https://example.test/serve/202' === (string) ( $out['study_schedules']['combined_url'] ?? '' ),
	'Schedule URL points at schedule resource id, not workbook id'
);

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );
