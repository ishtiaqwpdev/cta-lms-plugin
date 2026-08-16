<?php
/**
 * Per-form review unlock for LMFT California Clinical comprehensive simulations.
 *
 * Form A review (inline answers + rationales + downloads) unlocks only after
 * Form A is submitted. Form B review stays locked until Form B is submitted.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Comprehensive_Review
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Comprehensive_Review' ) ) {

class CTA_Lmft_Clinical_Comprehensive_Review {

	/**
	 * Whether this quiz belongs to LMFT Clinical comprehensive review rules.
	 *
	 * @param object|null $quiz   Quiz row.
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function applies_to_quiz( $quiz, $course ) {
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
	 * Quiz type gate key for the matching form (form_a or form_b).
	 *
	 * @param object|null $quiz Quiz row.
	 * @return string
	 */
	public static function get_quiz_gate_type( $quiz ) {
		return sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
	}

	/**
	 * Whether the learner has submitted this specific form at least once.
	 *
	 * @param object|null $quiz    Quiz row.
	 * @param object|null $course  Course row.
	 * @param int         $user_id User ID (defaults to current user).
	 * @return bool
	 */
	public static function learner_has_unlocked_review( $quiz, $course, $user_id = 0 ) {
		if ( ! self::applies_to_quiz( $quiz, $course ) ) {
			return false;
		}

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id || ! class_exists( 'CTA_Course_Materials' ) ) {
			return false;
		}

		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}

		$gate = self::get_quiz_gate_type( $quiz );
		if ( '' === $gate ) {
			return false;
		}

		return CTA_Course_Materials::user_has_completed_quiz_type(
			$user_id,
			(int) $course->id,
			$gate
		);
	}

	/**
	 * Whether inline answers/rationales should stay hidden for this learner.
	 *
	 * @param object|null $quiz    Quiz row.
	 * @param object|null $course  Course row.
	 * @param int         $user_id User ID (defaults to current user).
	 * @return bool
	 */
	public static function should_suppress_learner_answer_reveal( $quiz, $course, $user_id = 0 ) {
		if ( ! self::applies_to_quiz( $quiz, $course ) ) {
			return false;
		}

		return ! self::learner_has_unlocked_review( $quiz, $course, $user_id );
	}

	/**
	 * Admin answer-key row for a quiz question order index.
	 *
	 * @param object|null $quiz        Quiz row.
	 * @param int         $order_index Zero-based order index.
	 * @return array<string,mixed>|null
	 */
	public static function get_answer_record_for_order( $quiz, $order_index ) {
		if ( ! class_exists( 'CTA_Lmft_Clinical_Comprehensive_Scoring' ) ) {
			return null;
		}

		$sync_class = CTA_Lmft_Clinical_Comprehensive_Scoring::get_answer_sync_class( $quiz );
		if ( ! $sync_class || ! method_exists( $sync_class, 'get_answer_records' ) || ! method_exists( $sync_class, 'get_question_code_order_map' ) ) {
			return null;
		}

		$order_index = (int) $order_index;
		$code_order  = $sync_class::get_question_code_order_map();
		$code        = isset( $code_order[ $order_index ] ) ? (string) $code_order[ $order_index ] : '';
		if ( '' === $code ) {
			return null;
		}

		$records = $sync_class::get_answer_records();
		return ( ! empty( $records[ $code ] ) && is_array( $records[ $code ] ) )
			? $records[ $code ]
			: null;
	}

	/**
	 * Rationale text for a specific answer choice on a question.
	 *
	 * @param object|null $quiz          Quiz row.
	 * @param int         $order_index   Zero-based order index.
	 * @param string      $choice_letter a|b|c|d.
	 * @return string
	 */
	public static function get_rationale_for_choice( $quiz, $order_index, $choice_letter ) {
		$record = self::get_answer_record_for_order( $quiz, $order_index );
		if ( ! $record ) {
			return '';
		}

		$letter     = strtoupper( substr( sanitize_key( (string) $choice_letter ), 0, 1 ) );
		$rationales = isset( $record['rationales'] ) && is_array( $record['rationales'] ) ? $record['rationales'] : array();

		return (string) ( $rationales[ $letter ] ?? '' );
	}

	/**
	 * Build learner-facing explanation text for a submitted question.
	 *
	 * Uses the secured rationale for the item's correct answer letter.
	 *
	 * @param object|null $quiz    Quiz row.
	 * @param object      $question Question row.
	 * @return string
	 */
	public static function get_learner_explanation_for_question( $quiz, $question ) {
		$correct = strtolower( (string) ( $question->correct_option ?? '' ) );
		if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
			return '';
		}

		$order = isset( $question->order_index ) ? (int) $question->order_index : -1;
		return self::get_rationale_for_choice( $quiz, $order, $correct );
	}

	/**
	 * Whether a downloadable resource belongs to Form A review only.
	 *
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function resource_is_form_a_review( $resource ) {
		if ( ! $resource || ! class_exists( 'CTA_Course_Materials' ) ) {
			return false;
		}

		return 'form_a' === CTA_Course_Materials::infer_protected_rationale_unlock_type( $resource );
	}

	/**
	 * Whether a downloadable resource belongs to Form B review only.
	 *
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function resource_is_form_b_review( $resource ) {
		if ( ! $resource || ! class_exists( 'CTA_Course_Materials' ) ) {
			return false;
		}

		return 'form_b' === CTA_Course_Materials::infer_protected_rationale_unlock_type( $resource );
	}
}

}
