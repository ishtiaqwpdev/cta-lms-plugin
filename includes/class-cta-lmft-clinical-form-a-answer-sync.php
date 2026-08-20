<?php
/**
 * Comprehensive Simulation Form A secured answer-key sync (LMFT California Clinical).
 *
 * PROMPT 13+: merges admin-only correct answers into the active form_a quiz for
 * server-side scoring. Full rationales and core_calibration_status remain in
 * admin-only seed files and are never exposed to learners.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Form_A_Answer_Sync
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {

class CTA_Lmft_Clinical_Form_A_Answer_Sync {

	const ANSWER_SEED_OPTION   = 'cta_lmft_clinical_form_a_answers_final_aug14_1_0_256';
	const ADMIN_KEY_FILE       = 'includes/quiz-seeds/admin-only/lmft-clinical-form-a-answer-key.php';
	const QUIZ_TYPE            = 'form_a';
	const FORM_TITLE           = 'Form A — Comprehensive Simulation';
	const TARGET_QUESTION_COUNT = 150;
	const IMPORTED_THROUGH     = 150;

	/**
	 * Absolute path to the admin-only secured answer key aggregator.
	 *
	 * @return string
	 */
	public static function get_answer_key_path() {
		return CTA_PLUGIN_DIR . self::ADMIN_KEY_FILE;
	}

	/**
	 * Load secured answer rows keyed by official question_code.
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
	 * Map order_index => question_code from the learner Form A seed.
	 *
	 * @return array<int,string>
	 */
	public static function get_question_code_order_map() {
		if ( ! class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
			return array();
		}

		$map = array();
		foreach ( CTA_Lmft_Clinical_Form_A_Sync::get_questions() as $index => $row ) {
			$code = isset( $row['question_code'] ) ? trim( (string) $row['question_code'] ) : '';
			if ( '' !== $code ) {
				$map[ (int) $index ] = $code;
			}
		}

		return $map;
	}

	/**
	 * Whether learner-facing routes should suppress inline answer/rationale reveal.
	 *
	 * @param object|null $quiz   Quiz row.
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function should_suppress_learner_answer_reveal( $quiz, $course ) {
		if ( class_exists( 'CTA_Lmft_Clinical_Comprehensive_Review' ) ) {
			return CTA_Lmft_Clinical_Comprehensive_Review::should_suppress_learner_answer_reveal( $quiz, $course );
		}

		if ( ! $quiz || ! $course ) {
			return false;
		}

		if ( 'form_a' !== sanitize_key( (string) ( $quiz->quiz_type ?? '' ) ) ) {
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
	 * Find the active rebuilt Form A quiz ID for the LMFT Clinical course.
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function find_form_quiz_id( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return 0;
		}

		foreach ( (array) CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( self::QUIZ_TYPE !== $type || self::is_legacy_row( $row ) ) {
				continue;
			}

			return (int) $row->id;
		}

		return 0;
	}

	/**
	 * @param object $row Quiz row.
	 * @return bool
	 */
	private static function is_legacy_row( $row ) {
		if ( ! $row ) {
			return false;
		}

		$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
		if ( in_array( $type, array( 'legacy_form_a', 'legacy_form_b' ), true ) ) {
			return true;
		}

		return 'archived' === (string) ( $row->status ?? '' )
			&& class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' )
			&& CTA_Lmft_Clinical_Legacy_Forms_Archive::title_is_archived( (string) ( $row->title ?? '' ) );
	}

	/**
	 * Validate secured answer key completeness for imported range.
	 *
	 * @param array $records Answer rows keyed by question_code.
	 * @return true|WP_Error
	 */
	public static function validate_answer_key( array $records ) {
		if ( self::IMPORTED_THROUGH !== count( $records ) ) {
			return new WP_Error(
				'invalid_answer_key_count',
				sprintf( 'Expected %d secured Form A answer rows.', self::IMPORTED_THROUGH )
			);
		}

		for ( $num = 1; $num <= self::IMPORTED_THROUGH; $num++ ) {
			$code = sprintf( 'CTA-LMFT-CA-FA-%03d', $num );
			if ( empty( $records[ $code ] ) || ! is_array( $records[ $code ] ) ) {
				return new WP_Error( 'missing_answer_key', 'Missing secured answer for ' . $code );
			}

			$row = $records[ $code ];
			$correct = strtolower( (string) ( $row['correct_option'] ?? '' ) );
			if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
				return new WP_Error( 'invalid_correct_option', $code . ' has invalid correct_option.' );
			}

			$status = (string) ( $row['core_calibration_status'] ?? '' );
			if ( ! in_array( $status, array( 'Core', 'Calibration' ), true ) ) {
				return new WP_Error( 'invalid_core_calibration_status', $code . ' has invalid core_calibration_status.' );
			}

			$rationales = isset( $row['rationales'] ) && is_array( $row['rationales'] ) ? $row['rationales'] : array();
			foreach ( array( 'A', 'B', 'C', 'D' ) as $letter ) {
				if ( '' === trim( (string) ( $rationales[ $letter ] ?? '' ) ) ) {
					return new WP_Error( 'missing_rationale', $code . ' missing rationale for choice ' . $letter );
				}
			}
		}

		$distribution = class_exists( 'CTA_Lmft_Clinical_Comprehensive_Scoring' )
			? CTA_Lmft_Clinical_Comprehensive_Scoring::validate_core_calibration_distribution( $records )
			: true;
		if ( true !== $distribution ) {
			return $distribution;
		}

		return true;
	}

	/**
	 * Apply secured correct answers to existing learner Form A rows.
	 *
	 * Updates correct_option in the database only. Rationales and calibration
	 * metadata remain in admin-only seed files.
	 *
	 * @param bool $force Re-run even if already applied at this version.
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

		if ( ! class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'updated'   => 0,
				'message'   => 'form_a_sync_missing',
			);
		}

		$course = CTA_Lmft_Clinical_Form_A_Sync::find_course();
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
		$quiz_id   = self::find_form_quiz_id( $course_id );
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
				'message'   => 'form_a_quiz_not_found',
			);
		}

		$db_questions = CTA_Database::get_quiz_questions( $quiz_id );
		if ( self::TARGET_QUESTION_COUNT !== count( $db_questions ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => 0,
				'message'   => 'learner_form_a_not_loaded',
			);
		}

		$code_order = self::get_question_code_order_map();
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

		foreach ( $db_questions as $question ) {
			$order = isset( $question->order_index ) ? (int) $question->order_index : -1;
			$code  = isset( $code_order[ $order ] ) ? (string) $code_order[ $order ] : '';
			$num   = $order + 1;

			if ( $num > self::IMPORTED_THROUGH || '' === $code || empty( $records[ $code ] ) ) {
				continue;
			}

			$correct = strtolower( (string) ( $records[ $code ]['correct_option'] ?? '' ) );
			if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update(
				$table,
				array(
					'correct_option' => $correct,
					'explanation'    => '',
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

		if ( self::IMPORTED_THROUGH !== $updated ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => $updated,
				'message'   => 'partial_answer_apply',
			);
		}

		update_option(
			self::ANSWER_SEED_OPTION,
			array(
				'at'        => current_time( 'mysql' ),
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => $updated,
				'range'     => '1-150',
				'title'     => self::FORM_TITLE,
			),
			false
		);

		$seed = get_option( CTA_Lmft_Clinical_Form_A_Sync::SEED_OPTION );
		if ( is_array( $seed ) ) {
			$seed['answers_through'] = self::IMPORTED_THROUGH;
			update_option( CTA_Lmft_Clinical_Form_A_Sync::SEED_OPTION, $seed, false );
		}

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
