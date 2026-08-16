<?php
/**
 * Validate LMFT Clinical Form A/B Core/Calibration scoring logic.
 *
 * Usage: php scripts/test-lmft-clinical-comprehensive-scoring.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'CTA_PLUGIN_DIR', $root . '/' );

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		public $code = '';

		/** @var string */
		public $message = '';

		/**
		 * @param string $code Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
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

require_once $root . '/includes/class-cta-lmft-clinical-comprehensive-scoring.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-a-answer-sync.php';
require_once $root . '/includes/class-cta-lmft-clinical-form-b-answer-sync.php';
require_once $root . '/public/class-cta-quiz.php';

assert_true(
	125 === CTA_Lmft_Clinical_Comprehensive_Scoring::SCORED_ITEM_COUNT,
	'Scored item count is 125'
);
assert_true(
	150 === CTA_Lmft_Clinical_Comprehensive_Scoring::TOTAL_ITEM_COUNT,
	'Total item count is 150'
);

foreach ( array( 'A' => 'CTA_Lmft_Clinical_Form_A_Answer_Sync', 'B' => 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) as $label => $sync_class ) {
	$records = $sync_class::get_answer_records();
	$valid   = $sync_class::validate_answer_key( $records );
	assert_true( true === $valid, "Form {$label} answer key validates with 125 Core / 25 Calibration" );

	$distribution = CTA_Lmft_Clinical_Comprehensive_Scoring::validate_core_calibration_distribution( $records );
	assert_true( true === $distribution, "Form {$label} admin key has exactly 125 Core and 25 Calibration" );
}

$course = (object) array(
	'slug' => 'lmft-california-clinical-exam-preparation',
);

$form_a_quiz = (object) array(
	'quiz_type' => 'form_a',
	'title'     => 'Comprehensive Simulation - Form A',
	'passing_score' => 70,
);

$form_b_quiz = (object) array(
	'quiz_type' => 'form_b',
	'title'     => 'Comprehensive Simulation - Form B',
	'passing_score' => 70,
);

assert_true(
	CTA_Lmft_Clinical_Comprehensive_Scoring::uses_core_calibration_scoring( $form_a_quiz, $course ),
	'Form A uses Core-only displayed scoring'
);
assert_true(
	CTA_Lmft_Clinical_Comprehensive_Scoring::uses_core_calibration_scoring( $form_b_quiz, $course ),
	'Form B uses Core-only displayed scoring'
);

/**
 * Build mock DB question rows for scoring simulation.
 *
 * @param string $sync_class Answer sync class name.
 * @return array<object>
 */
function build_mock_questions_for_scoring( $sync_class ) {
	$records    = $sync_class::get_answer_records();
	$code_order = $sync_class::get_question_code_order_map();
	$questions  = array();

	foreach ( $code_order as $order => $code ) {
		$correct = strtolower( (string) ( $records[ $code ]['correct_option'] ?? 'a' ) );
		$questions[] = (object) array(
			'id'             => $order + 1,
			'order_index'    => (int) $order,
			'correct_option' => $correct,
		);
	}

	return $questions;
}

/**
 * Build answer map from scored/core flags.
 *
 * @param array<object>     $questions Question rows.
 * @param object            $quiz      Quiz row.
 * @param callable          $answer_for Callable( bool $is_scored, object $question ): string
 * @return array<int,string>
 */
function build_answer_map( array $questions, $quiz, callable $answer_for ) {
	$scored_ids = CTA_Lmft_Clinical_Comprehensive_Scoring::get_scored_question_ids( $questions, $quiz );
	$answers    = array();

	foreach ( $questions as $question ) {
		$qid       = (int) $question->id;
		$is_scored = ! empty( $scored_ids[ $qid ] );
		$answers[ $qid ] = $answer_for( $is_scored, $question );
	}

	return $answers;
}

/**
 * Return a wrong but valid option letter.
 *
 * @param string $correct Correct option.
 * @return string
 */
function wrong_option( $correct ) {
	$options = array( 'a', 'b', 'c', 'd' );
	foreach ( $options as $option ) {
		if ( $option !== $correct ) {
			return $option;
		}
	}

	return 'a';
}

foreach (
	array(
		'A' => array( 'CTA_Lmft_Clinical_Form_A_Answer_Sync', $form_a_quiz ),
		'B' => array( 'CTA_Lmft_Clinical_Form_B_Answer_Sync', $form_b_quiz ),
	) as $label => $pair
) {
	list( $sync_class, $quiz ) = $pair;
	$questions = build_mock_questions_for_scoring( $sync_class );
	assert_true(
		150 === count( $questions ),
		"Form {$label} mock question set has 150 items"
	);
	assert_true(
		125 === count( CTA_Lmft_Clinical_Comprehensive_Scoring::get_scored_question_ids( $questions, $quiz ) ),
		"Form {$label} maps exactly 125 scored Core question IDs"
	);

	$all_core_correct = build_answer_map(
		$questions,
		$quiz,
		static function ( $is_scored, $question ) {
			return $is_scored
				? (string) $question->correct_option
				: wrong_option( (string) $question->correct_option );
		}
	);
	$result = CTA_Lmft_Clinical_Comprehensive_Scoring::calculate_display_score(
		$questions,
		$all_core_correct,
		$quiz,
		70
	);
	assert_true( 100 === (int) $result['score'], "Form {$label} all-Core-correct yields 100% displayed score" );
	assert_true( 125 === (int) $result['core_correct'], "Form {$label} counts 125 Core correct answers" );

	$half_core = build_answer_map(
		$questions,
		$quiz,
		static function ( $is_scored, $question ) {
			if ( ! $is_scored ) {
				return wrong_option( (string) $question->correct_option );
			}

			return ( (int) $question->order_index % 2 === 0 )
				? (string) $question->correct_option
				: wrong_option( (string) $question->correct_option );
		}
	);
	$result_half = CTA_Lmft_Clinical_Comprehensive_Scoring::calculate_display_score(
		$questions,
		$half_core,
		$quiz,
		70
	);
	$expected_half = (int) round( ( $result_half['core_correct'] / 125 ) * 100 );
	assert_true(
		$expected_half === (int) $result_half['score'],
		"Form {$label} displayed score equals Core correct / 125 x 100"
	);

	$calibration_only_change = $all_core_correct;
	$calibration_only_change_alt = build_answer_map(
		$questions,
		$quiz,
		static function ( $is_scored, $question ) {
			if ( $is_scored ) {
				return (string) $question->correct_option;
			}

			return (string) $question->correct_option;
		}
	);
	$result_base = CTA_Lmft_Clinical_Comprehensive_Scoring::calculate_display_score(
		$questions,
		$all_core_correct,
		$quiz,
		70
	);
	$result_alt = CTA_Lmft_Clinical_Comprehensive_Scoring::calculate_display_score(
		$questions,
		$calibration_only_change_alt,
		$quiz,
		70
	);
	assert_true(
		(int) $result_base['score'] === (int) $result_alt['score'],
		"Form {$label} changing Calibration-only answers does not change displayed score"
	);
}

$quiz_src = file_get_contents( $root . '/public/class-cta-quiz.php' );
$main_js  = file_get_contents( $root . '/assets/js/main.js' );
$lms      = file_get_contents( $root . '/cta-lms.php' );

assert_true(
	false !== strpos( $quiz_src, 'calculate_display_score' ),
	'Quiz submit uses Core-only scoring helper'
);
assert_true(
	false === strpos( $main_js, 'calibration' ) && false === strpos( $main_js, 'Calibration' ),
	'Learner quiz JS does not reference calibration labels'
);
assert_true(
	false !== strpos( $lms, 'class-cta-lmft-clinical-comprehensive-scoring.php' ),
	'Scoring class is bootstrapped'
);
assert_true(
	false !== strpos( $lms, '1.0.247' ),
	'Version bump 1.0.247'
);

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
