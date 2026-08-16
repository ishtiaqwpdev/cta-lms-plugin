<?php
/**
 * Core/Calibration scoring for LMFT California Clinical comprehensive simulations.
 *
 * Form A and Form B present 150 items. Only the 125 admin-marked Core items
 * count toward the learner-facing score. Calibration items are answered but
 * excluded from score calculation and never labeled in learner UI.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Comprehensive_Scoring
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Comprehensive_Scoring' ) ) {

class CTA_Lmft_Clinical_Comprehensive_Scoring {

	const SCORED_ITEM_COUNT = 125;
	const TOTAL_ITEM_COUNT  = 150;

	/**
	 * Whether this quiz uses Core-only displayed scoring.
	 *
	 * @param object|null $quiz   Quiz row.
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function uses_core_calibration_scoring( $quiz, $course ) {
		if ( ! $quiz || ! $course ) {
			return false;
		}

		$quiz_type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( ! in_array( $quiz_type, array( 'form_a', 'form_b' ), true ) ) {
			return false;
		}

		if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' )
			&& CTA_Lmft_Clinical_Legacy_Forms_Archive::title_is_archived( (string) ( $quiz->title ?? '' ) ) ) {
			return false;
		}

		return class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' )
			? CTA_Lmft_Clinical_Legacy_Forms_Archive::is_lmft_clinical_course( $course )
			: ( 'lmft-california-clinical-exam-preparation' === (string) ( $course->slug ?? '' ) );
	}

	/**
	 * Validate admin answer-key Core/Calibration distribution.
	 *
	 * @param array $records Answer rows keyed by question_code.
	 * @return true|WP_Error
	 */
	public static function validate_core_calibration_distribution( array $records ) {
		$core = 0;
		$cal  = 0;

		foreach ( $records as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status = (string) ( $row['core_calibration_status'] ?? '' );
			if ( 'Core' === $status ) {
				++$core;
			} elseif ( 'Calibration' === $status ) {
				++$cal;
			}
		}

		if ( self::SCORED_ITEM_COUNT !== $core || ( self::TOTAL_ITEM_COUNT - self::SCORED_ITEM_COUNT ) !== $cal ) {
			return new WP_Error(
				'invalid_core_calibration_distribution',
				sprintf(
					'Expected %1$d Core and %2$d Calibration items; found %3$d Core and %4$d Calibration.',
					self::SCORED_ITEM_COUNT,
					self::TOTAL_ITEM_COUNT - self::SCORED_ITEM_COUNT,
					$core,
					$cal
				)
			);
		}

		return true;
	}

	/**
	 * Resolve the secured answer-sync helper for a comprehensive simulation quiz.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return string|null Class name.
	 */
	public static function get_answer_sync_class( $quiz ) {
		$quiz_type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );

		if ( 'form_a' === $quiz_type && class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
			return 'CTA_Lmft_Clinical_Form_A_Answer_Sync';
		}

		if ( 'form_b' === $quiz_type && class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
			return 'CTA_Lmft_Clinical_Form_B_Answer_Sync';
		}

		return null;
	}

	/**
	 * Map scored (Core) question IDs for a quiz attempt.
	 *
	 * @param array       $questions Question rows from the database.
	 * @param object|null $quiz      Quiz row.
	 * @return array<int,true> Question IDs keyed for isset() lookups.
	 */
	public static function get_scored_question_ids( array $questions, $quiz ) {
		$sync_class = self::get_answer_sync_class( $quiz );
		if ( ! $sync_class || ! method_exists( $sync_class, 'get_answer_records' ) || ! method_exists( $sync_class, 'get_question_code_order_map' ) ) {
			return array();
		}

		$records    = $sync_class::get_answer_records();
		$code_order = $sync_class::get_question_code_order_map();
		$scored     = array();

		foreach ( $questions as $question ) {
			$order = isset( $question->order_index ) ? (int) $question->order_index : -1;
			$code  = isset( $code_order[ $order ] ) ? (string) $code_order[ $order ] : '';

			if ( '' === $code || empty( $records[ $code ] ) || ! is_array( $records[ $code ] ) ) {
				continue;
			}

			if ( 'Core' === (string) ( $records[ $code ]['core_calibration_status'] ?? '' ) ) {
				$scored[ (int) $question->id ] = true;
			}
		}

		return $scored;
	}

	/**
	 * Calculate the learner-facing score from Core items only.
	 *
	 * @param array       $questions       Question rows.
	 * @param array       $sanitized       Question ID => answer letter map.
	 * @param object|null $quiz            Quiz row.
	 * @param int         $passing_score   Passing percentage threshold.
	 * @return array{score:int,passed:bool,core_correct:int,core_total:int}
	 */
	public static function calculate_display_score( array $questions, array $sanitized, $quiz, $passing_score ) {
		$scored_ids   = self::get_scored_question_ids( $questions, $quiz );
		$core_correct = 0;
		$core_total   = count( $scored_ids );

		foreach ( $questions as $question ) {
			$qid = (int) $question->id;
			if ( empty( $scored_ids[ $qid ] ) ) {
				continue;
			}

			$answer = isset( $sanitized[ $qid ] ) ? (string) $sanitized[ $qid ] : '';
			if ( '' !== $answer && $answer === (string) $question->correct_option ) {
				++$core_correct;
			}
		}

		$denominator = self::SCORED_ITEM_COUNT;
		$score       = $denominator > 0 ? (int) round( ( $core_correct / $denominator ) * 100 ) : 0;
		$passed      = $score >= max( 0, (int) $passing_score );

		return array(
			'score'        => $score,
			'passed'       => $passed,
			'core_correct' => $core_correct,
			'core_total'   => $core_total,
		);
	}
}

}
