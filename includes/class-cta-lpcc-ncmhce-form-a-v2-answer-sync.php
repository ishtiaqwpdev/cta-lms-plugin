<?php
/**
 * LPCC NCMHCE Form A v2.0 secured answer-key merge.
 *
 * Merges admin-only correct_option + explanation into the staging form_a_v2
 * quiz rows. The source DOCX is never registered as a learner download.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync
 */
if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync' ) ) {

class CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync {

	const ANSWER_SEED_OPTION    = 'cta_lpcc_ncmhce_form_a_v2_answers_1_0_263';
	const ADMIN_KEY_FILE        = 'includes/quiz-seeds/admin-only/lpcc-ncmhce-form-a-v2-answer-key.php';
	const TARGET_QUESTION_COUNT = 143;

	/**
	 * Absolute path to the admin-only secured answer key.
	 *
	 * @return string
	 */
	public static function get_answer_key_path() {
		return CTA_PLUGIN_DIR . self::ADMIN_KEY_FILE;
	}

	/**
	 * Load secured answer rows keyed by question_code.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_answer_records() {
		$path = self::get_answer_key_path();
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$rows = include $path;
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Validate secured answer key completeness.
	 *
	 * @param array $records Answer rows keyed by question_code.
	 * @return true|WP_Error
	 */
	public static function validate_answer_key( array $records ) {
		if ( self::TARGET_QUESTION_COUNT !== count( $records ) ) {
			return new WP_Error(
				'invalid_answer_key_count',
				sprintf( 'Expected %d secured Form A v2.0 answer rows.', self::TARGET_QUESTION_COUNT )
			);
		}

		$scored = 0;
		$field  = 0;

		for ( $num = 1; $num <= self::TARGET_QUESTION_COUNT; $num++ ) {
			$code = sprintf( 'CTA-LPCC-NCMHCE-FA-V2-%03d', $num );
			if ( empty( $records[ $code ] ) || ! is_array( $records[ $code ] ) ) {
				return new WP_Error( 'missing_answer_key', 'Missing secured answer for ' . $code );
			}

			$row     = $records[ $code ];
			$correct = strtolower( (string) ( $row['correct_option'] ?? '' ) );
			if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
				return new WP_Error( 'invalid_correct_option', $code . ' has invalid correct_option.' );
			}

			if ( '' === trim( (string) ( $row['explanation'] ?? '' ) ) ) {
				return new WP_Error( 'missing_rationale', $code . ' missing explanation.' );
			}

			$status = (string) ( $row['item_status'] ?? '' );
			if ( 'Scored' === $status ) {
				++$scored;
			} elseif ( 'Field-test' === $status ) {
				++$field;
			} else {
				return new WP_Error( 'invalid_item_status', $code . ' has invalid item_status.' );
			}
		}

		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Scoring' ) ) {
			$dist = CTA_Lpcc_Ncmhce_Form_A_V2_Scoring::validate_scored_field_test_distribution( $records );
			if ( true !== $dist ) {
				return $dist;
			}
		} elseif ( 100 !== $scored || 43 !== $field ) {
			return new WP_Error( 'invalid_scored_distribution', 'Expected 100 scored and 43 field-test items.' );
		}

		return true;
	}

	/**
	 * Merge correct answers and rationales into the staging quiz rows.
	 *
	 * @param bool $force Re-run even if already applied.
	 * @return array{ok:bool,course_id:int,quiz_id:int,updated:int,message:string}
	 */
	public static function sync_answer_keys( $force = false ) {
		if ( ! $force && get_option( self::ANSWER_SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'quiz_id'   => 0,
				'updated'   => 0,
				'message'   => 'already_seeded',
			);
		}

		if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync' ) ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'updated'   => 0,
				'message'   => 'form_a_v2_sync_missing',
			);
		}

		if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'updated'   => 0,
				'message'   => 'program_sync_missing',
			);
		}

		$course = CTA_Lpcc_Ncmhce_Sync::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'updated'   => 0,
				'message'   => 'course_not_found',
			);
		}

		$course_id = (int) $course->id;
		$quiz      = CTA_Lpcc_Ncmhce_Form_A_V2_Sync::find_quiz( $course_id );
		$quiz_id   = $quiz ? (int) $quiz->id : 0;
		$records   = self::get_answer_records();
		$valid     = self::validate_answer_key( $records );

		if ( is_wp_error( $valid ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => 0,
				'message'   => $valid->get_error_code(),
			);
		}

		if ( ! $quiz_id || ! class_exists( 'CTA_Database' ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => 0,
				'updated'   => 0,
				'message'   => 'form_a_v2_quiz_not_found',
			);
		}

		$db_questions = CTA_Database::get_quiz_questions( $quiz_id );
		if ( self::TARGET_QUESTION_COUNT !== count( $db_questions ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => 0,
				'message'   => 'learner_form_a_v2_not_loaded',
			);
		}

		$code_order = CTA_Lpcc_Ncmhce_Form_A_V2_Sync::get_question_code_order_map();
		if ( self::TARGET_QUESTION_COUNT !== count( $code_order ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => 0,
				'message'   => 'learner_code_map_invalid',
			);
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'cta_quiz_questions';
		$updated = 0;
		$text    = function_exists( 'cta_lms_sanitize_utf8_text' ) ? 'cta_lms_sanitize_utf8_text' : null;

		foreach ( $db_questions as $question ) {
			$order = isset( $question->order_index ) ? (int) $question->order_index : -1;
			$code  = isset( $code_order[ $order ] ) ? (string) $code_order[ $order ] : '';

			if ( '' === $code || empty( $records[ $code ] ) ) {
				continue;
			}

			$correct = strtolower( (string) ( $records[ $code ]['correct_option'] ?? '' ) );
			if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
				continue;
			}

			$explanation = (string) ( $records[ $code ]['explanation'] ?? '' );
			if ( $text ) {
				$explanation = $text( $explanation );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update(
				$table,
				array(
					'correct_option' => $correct,
					'explanation'    => $explanation,
				),
				array(
					'id'      => (int) $question->id,
					'quiz_id' => $quiz_id,
				),
				array( '%s', '%s' ),
				array( '%d', '%d' )
			);

			if ( false !== $ok ) {
				++$updated;
			}
		}

		if ( self::TARGET_QUESTION_COUNT !== $updated ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => $updated,
				'message'   => 'partial_answer_apply',
			);
		}

		update_option( self::ANSWER_SEED_OPTION, 1, false );

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'quiz_id'   => $quiz_id,
			'updated'   => $updated,
			'message'   => 'synced',
		);
	}
}

}
