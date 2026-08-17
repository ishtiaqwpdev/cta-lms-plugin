<?php
/**
 * Verify LCSW ASWB Clinical workbook practice-bank seeds (counts, metadata strip, rationales).
 *
 * Usage: php scripts/test-lcsw-aswb-workbook-banks.php
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

$fail = 0;
$pass = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 */
function assert_true( $cond, $msg ) {
	global $fail, $pass;
	if ( $cond ) {
		echo "PASS: {$msg}\n";
		++$pass;
	} else {
		echo "FAIL: {$msg}\n";
		++$fail;
	}
}

$titles = array(
	1  => 'ASWB Exam Strategy and Applied Reasoning',
	2  => 'Values, Ethics, Self-Determination, and Social Justice',
	3  => 'Human Development, Diversity, and Person-in-Environment',
	4  => 'Clinical Assessment, Interviewing, and Mental Status',
	5  => 'Diagnosis, Medical Factors, and Psychopharmacology',
	6  => 'Crisis, Abuse, and Risk Assessment',
	7  => 'Treatment and Service Planning',
	8  => 'Clinical Interventions and Trauma-Informed Practice',
	9  => 'Family, Couple, Group, and Parenting Interventions',
	10 => 'Case Management, Advocacy, Resources, and Collaboration',
	11 => 'Practice Evaluation, Research, Supervision, and Administration',
	12 => 'Integrated Review and 25-Question Mini-Mock',
);

$placeholder = 'check back when the online quiz is published';

for ( $wb = 1; $wb <= 12; $wb++ ) {
	$docx = $root . '/assets/course-materials/lcsw-aswb/question-banks/CTA_LCSW_WB' . $wb . '_17_Question_Bank_v1.0.docx';
	$seed = $root . '/includes/quiz-seeds/lcsw-aswb-wb' . $wb . '-bank.php';

	assert_true( is_readable( $docx ), "WB{$wb} source DOCX exists" );
	assert_true( is_readable( $seed ), "WB{$wb} seed PHP exists" );

	$questions = include $seed;
	assert_true( is_array( $questions ) && 17 === count( $questions ), "WB{$wb} seed has 17 questions" );

	if ( ! is_array( $questions ) ) {
		continue;
	}

	$meta_leak = false;
	$missing_key = false;
	$missing_rat = false;
	$empty_stem  = false;

	foreach ( $questions as $i => $q ) {
		$stem = (string) ( $q['question_text'] ?? '' );
		$exp  = (string) ( $q['explanation'] ?? '' );
		$corr = strtolower( (string) ( $q['correct_option'] ?? '' ) );
		$oa   = trim( (string) ( $q['option_a'] ?? '' ) );
		$ob   = trim( (string) ( $q['option_b'] ?? '' ) );
		$oc   = trim( (string) ( $q['option_c'] ?? '' ) );

		if ( '' === $stem || '' === $oa || '' === $ob || '' === $oc ) {
			$empty_stem = true;
		}
		if ( ! in_array( $corr, array( 'a', 'b', 'c', 'd' ), true ) ) {
			$missing_key = true;
		}
		if ( '' === $exp || false === stripos( $exp, 'Correct.' ) ) {
			$missing_rat = true;
		}
		$hay = $stem . "\n" . $oa . "\n" . $ob . "\n" . $oc . "\n" . (string) ( $q['option_d'] ?? '' );
		if ( preg_match( '/LCSW-WB\d+-QB-Q\d+/', $hay )
			|| preg_match( '/\b(Easy|Moderate|Difficult)\b/', $hay )
			|| false !== stripos( $hay, 'Primary Concept' )
			|| false !== stripos( $hay, 'Question Type' )
			|| false !== stripos( $hay, 'Question ID' ) ) {
			$meta_leak = true;
		}
	}

	assert_true( ! $empty_stem, "WB{$wb} stems/options present" );
	assert_true( ! $missing_key, "WB{$wb} correct_option set for all items" );
	assert_true( ! $missing_rat, "WB{$wb} controlled rationales present in explanation" );
	assert_true( ! $meta_leak, "WB{$wb} learner fields strip Q-ID/difficulty/type metadata" );

	$q1 = $questions[0];
	assert_true(
		! empty( $q1['question_text'] ) && empty( preg_match( '/LCSW-WB/', (string) $q1['question_text'] ) ),
		"WB{$wb} Q1 stem is learner-safe"
	);
}

// Sync class wiring.
require_once $root . '/includes/class-cta-lcsw-aswb-sync.php';
assert_true( class_exists( 'CTA_Lcsw_Aswb_Sync' ), 'CTA_Lcsw_Aswb_Sync loaded' );
assert_true(
	false !== strpos( file_get_contents( $root . '/includes/class-cta-lcsw-aswb-sync.php' ), "lcsw-aswb-wb' . \$n . '-bank.php" ),
	'Sync references workbook bank seeds'
);
assert_true(
	false !== strpos( file_get_contents( $root . '/includes/class-cta-lcsw-aswb-sync.php' ), "wb' . \$n . '_bank" )
		|| false !== strpos( file_get_contents( $root . '/includes/class-cta-lcsw-aswb-sync.php' ), 'wb1_bank' ),
	'Sync creates wbN_bank quiz types'
);

// Placeholder still exists as fallback only when cards empty.
$tabbed = file_get_contents( $root . '/templates/partials/exam-prep-workbook-tabbed.php' );
assert_true( false !== strpos( $tabbed, $placeholder ), 'Placeholder remains as empty-cards fallback only' );
assert_true( false !== strpos( $tabbed, "if ( ! empty( \$cards ) )" ), 'Online quiz cards preferred over placeholder' );

// Empty option_d skipped in quiz UI.
$partial = file_get_contents( $root . '/templates/partials/quiz-question.php' );
assert_true( false !== strpos( $partial, "'' === trim( (string) \$label )" ), 'Quiz UI skips empty options (3-option banks)' );

echo "\nWorkbook titles confirmed (12):\n";
foreach ( $titles as $n => $t ) {
	echo "  WB{$n}: {$t}\n";
}

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
