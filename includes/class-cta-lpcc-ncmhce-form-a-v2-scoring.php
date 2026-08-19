<?php
/**
 * Scored vs field-test scoring for LPCC NCMHCE Form A v2.0.
 *
 * Form A v2.0 presents 143 items. Only the 100 admin-marked Scored items
 * count toward the learner-facing percentage. Field-test items are answered
 * but excluded from the score and never labeled in learner UI.
 *
 * The v2.0 Controlled Answer Key does not state a passing percentage.
 * Pass/fail is withheld until that cut score is confirmed from source.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lpcc_Ncmhce_Form_A_V2_Scoring
 */
if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Scoring' ) ) {

class CTA_Lpcc_Ncmhce_Form_A_V2_Scoring {

	const SCORED_ITEM_COUNT     = 100;
	const TOTAL_ITEM_COUNT      = 143;
	const FIELD_TEST_ITEM_COUNT = 43;
	const QUIZ_TYPE             = 'form_a_v2';

	/**
	 * Passing percent from the v2.0 Controlled Answer Key, or null if unspecified.
	 *
	 * @return int|null
	 */
	public static function source_passing_percent() {
		return null;
	}

	/**
	 * Whether this quiz uses scored-only displayed scoring.
	 *
	 * @param object|null $quiz   Quiz row.
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function uses_scored_field_test_scoring( $quiz, $course = null ) {
		if ( ! $quiz ) {
			return false;
		}

		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' )
			&& CTA_Lpcc_Ncmhce_Form_A_Sync::is_live_v2_quiz( $quiz ) ) {
			return true;
		}

		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync' )
			&& CTA_Lpcc_Ncmhce_Form_A_V2_Sync::is_staging_quiz( $quiz ) ) {
			return true;
		}

		return self::QUIZ_TYPE === sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
	}

	/**
	 * Whether learner UI should omit pass/fail because the source key has no cut score.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function withholds_pass_fail( $quiz ) {
		return self::uses_scored_field_test_scoring( $quiz ) && null === self::source_passing_percent();
	}

	/**
	 * Validate Scored / Field-test distribution.
	 *
	 * @param array $records Answer rows keyed by question_code.
	 * @return true|WP_Error
	 */
	public static function validate_scored_field_test_distribution( array $records ) {
		$scored = 0;
		$field  = 0;

		foreach ( $records as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = (string) ( $row['item_status'] ?? '' );
			if ( 'Scored' === $status ) {
				++$scored;
			} elseif ( 'Field-test' === $status ) {
				++$field;
			}
		}

		if ( self::SCORED_ITEM_COUNT !== $scored || self::FIELD_TEST_ITEM_COUNT !== $field ) {
			return new WP_Error(
				'invalid_scored_field_test_distribution',
				sprintf(
					'Expected %1$d Scored and %2$d Field-test items; found %3$d Scored and %4$d Field-test.',
					self::SCORED_ITEM_COUNT,
					self::FIELD_TEST_ITEM_COUNT,
					$scored,
					$field
				)
			);
		}

		return true;
	}

	/**
	 * Map scored question IDs for a quiz attempt.
	 *
	 * @param array       $questions Question rows from the database.
	 * @param object|null $quiz      Quiz row.
	 * @return array<int,true>
	 */
	public static function get_scored_question_ids( array $questions, $quiz ) {
		if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync' ) ) {
			return array();
		}

		if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' ) && ! class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync' ) ) {
			return array();
		}

		$records    = CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync::get_answer_records();
		$code_order = class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' )
			? CTA_Lpcc_Ncmhce_Form_A_Sync::get_question_code_order_map()
			: CTA_Lpcc_Ncmhce_Form_A_V2_Sync::get_question_code_order_map();
		$scored     = array();

		foreach ( $questions as $question ) {
			$order = isset( $question->order_index ) ? (int) $question->order_index : -1;
			$code  = isset( $code_order[ $order ] ) ? (string) $code_order[ $order ] : '';

			if ( '' === $code || empty( $records[ $code ] ) || ! is_array( $records[ $code ] ) ) {
				continue;
			}

			if ( 'Scored' === (string) ( $records[ $code ]['item_status'] ?? '' ) ) {
				$scored[ (int) $question->id ] = true;
			}
		}

		return $scored;
	}

	/**
	 * Calculate the learner-facing score from Scored items only.
	 *
	 * @param array       $questions Question rows.
	 * @param array       $sanitized Question ID => answer letter map.
	 * @param object|null $quiz      Quiz row.
	 * @return array{score:int,passed:bool,scored_correct:int,scored_total:int,pass_threshold_unspecified:bool}
	 */
	public static function calculate_display_score( array $questions, array $sanitized, $quiz ) {
		$scored_ids     = self::get_scored_question_ids( $questions, $quiz );
		$scored_correct = 0;
		$scored_total   = count( $scored_ids );

		foreach ( $questions as $question ) {
			$qid = (int) $question->id;
			if ( ! isset( $scored_ids[ $qid ] ) ) {
				continue;
			}

			$answer = isset( $sanitized[ $qid ] ) ? $sanitized[ $qid ] : '';
			if ( $answer && $answer === $question->correct_option ) {
				++$scored_correct;
			}
		}

		$score = $scored_total > 0 ? (int) round( ( $scored_correct / $scored_total ) * 100 ) : 0;
		$cut   = self::source_passing_percent();

		return array(
			'score'                       => $score,
			'passed'                      => ( null !== $cut && $score >= (int) $cut ),
			'scored_correct'              => $scored_correct,
			'scored_total'                => $scored_total,
			'pass_threshold_unspecified'  => ( null === $cut ),
		);
	}
}

}
