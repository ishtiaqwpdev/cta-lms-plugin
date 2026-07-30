<?php
/**
 * Telehealth (CTA-CE-002) final exam + evaluation seed (course-scoped only).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Telehealth_Exam_Sync
 */
if ( ! class_exists( 'CTA_Telehealth_Exam_Sync' ) ) {

class CTA_Telehealth_Exam_Sync {

	const COURSE_CODE = 'CTA-CE-002';
	const QUIZ_TITLE  = 'Final Examination';
	const SEED_OPTION = 'cta_telehealth_final_exam_seeded_1_0_108';

	/**
	 * Title aliases used to locate the Telehealth CE course.
	 *
	 * @return string[]
	 */
	public static function match_titles() {
		return array(
			'Clinical and Ethical Excellence in Telehealth: The Essential California Framework',
			'Clinical and Ethical Excellence in Telehealth',
		);
	}

	/**
	 * Load the official 25-question bank (exact CTA wording).
	 *
	 * @return array[]
	 */
	public static function get_questions() {
		$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/telehealth-final-exam.php';
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * Find the Telehealth course by code, then title aliases.
	 *
	 * @return object|null
	 */
	public static function find_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		// Prefer course_code in syllabus_meta JSON when present.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );

		foreach ( (array) $rows as $row ) {
			$meta = array();
			if ( ! empty( $row->syllabus_meta ) ) {
				$decoded = json_decode( (string) $row->syllabus_meta, true );
				if ( is_array( $decoded ) ) {
					$meta = $decoded;
				}
			}
			$code = isset( $meta['course_code'] ) ? (string) $meta['course_code'] : '';
			if ( self::COURSE_CODE === $code ) {
				return $row;
			}
		}

		if ( ! class_exists( 'CTA_Database' ) ) {
			return null;
		}

		foreach ( self::match_titles() as $title ) {
			$course = CTA_Database::get_course_by_title( $title );
			if ( $course ) {
				return $course;
			}
		}

		return null;
	}

	/**
	 * Seed/replace Telehealth final exam and refresh course evaluation only.
	 *
	 * Does not modify price, CE hours, or any other course.
	 *
	 * @param bool $force Re-run even if already seeded at this version.
	 * @return array{ok:bool,course_id:int,quiz_id:int,questions:int,message:string}
	 */
	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'         => true,
				'course_id'  => 0,
				'quiz_id'    => 0,
				'questions'  => 0,
				'message'    => 'already_seeded',
			);
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'         => false,
				'course_id'  => 0,
				'quiz_id'    => 0,
				'questions'  => 0,
				'message'    => 'telehealth_course_not_found',
			);
		}

		$course_id = (int) $course->id;
		$questions = self::get_questions();

		if ( 25 !== count( $questions ) ) {
			return array(
				'ok'         => false,
				'course_id'  => $course_id,
				'quiz_id'    => 0,
				'questions'  => count( $questions ),
				'message'    => 'invalid_question_bank_count',
			);
		}

		$quiz_id = self::replace_final_exam( $course_id, $questions );
		if ( ! $quiz_id ) {
			return array(
				'ok'         => false,
				'course_id'  => $course_id,
				'quiz_id'    => 0,
				'questions'  => 0,
				'message'    => 'quiz_write_failed',
			);
		}

		// Evaluation: LO ratings from syllabus + CAMFT sections for this course only.
		if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
			CTA_Evaluation_Questions::sync_learning_objective_questions( $course_id );
			CTA_Evaluation_Questions::copy_camft_templates_to_course( $course_id );
		}

		update_option( self::SEED_OPTION, array(
			'at'         => current_time( 'mysql' ),
			'course_id'  => $course_id,
			'quiz_id'    => $quiz_id,
			'questions'  => 25,
			'passing'    => 70,
		), false );

		return array(
			'ok'         => true,
			'course_id'  => $course_id,
			'quiz_id'    => $quiz_id,
			'questions'  => 25,
			'message'    => 'synced',
		);
	}

	/**
	 * Create or update the final quiz and replace all questions.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $questions Question bank rows.
	 * @return int Quiz ID or 0.
	 */
	private static function replace_final_exam( $course_id, array $questions ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		// Prefer an existing active final quiz; otherwise first active quiz.
		$quiz = null;
		if ( class_exists( 'CTA_Database' ) ) {
			$all = CTA_Database::get_quizzes_by_course( $course_id, false );
			foreach ( (array) $all as $row ) {
				$type = isset( $row->quiz_type ) ? (string) $row->quiz_type : 'final';
				if ( 'final' === $type || '' === $type ) {
					$quiz = $row;
					break;
				}
			}
			if ( ! $quiz && ! empty( $all[0] ) ) {
				$quiz = $all[0];
			}
		}

		$quiz_table = $wpdb->prefix . 'cta_quizzes';

		if ( $quiz ) {
			$quiz_id = (int) $quiz->id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$quiz_table,
				array(
					'title'           => self::QUIZ_TITLE,
					'quiz_type'       => 'final',
					'passing_score'   => 70,
					'time_limit_mins' => 0,
					'max_attempts'    => 0,
					'status'          => 'active',
					'sort_order'      => isset( $quiz->sort_order ) ? (int) $quiz->sort_order : 0,
				),
				array( 'id' => $quiz_id ),
				array( '%s', '%s', '%d', '%d', '%d', '%s', '%d' ),
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$quiz_table,
				array(
					'course_id'       => $course_id,
					'title'           => self::QUIZ_TITLE,
					'quiz_type'       => 'final',
					'sort_order'      => 0,
					'passing_score'   => 70,
					'time_limit_mins' => 0,
					'max_attempts'    => 0,
					'status'          => 'active',
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
			);
			if ( ! $inserted ) {
				return 0;
			}
			$quiz_id = (int) $wpdb->insert_id;
		}

		if ( ! $quiz_id ) {
			return 0;
		}

		$q_table = $wpdb->prefix . 'cta_quiz_questions';

		// Ensure option columns can hold full official wording.
		self::maybe_widen_option_columns();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $q_table, array( 'quiz_id' => $quiz_id ), array( '%d' ) );

		foreach ( $questions as $index => $question ) {
			$correct = isset( $question['correct_option'] ) ? strtolower( (string) $question['correct_option'] ) : 'a';
			$correct = in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ? $correct : 'a';

			$text = function_exists( 'cta_lms_sanitize_utf8_text' )
				? 'cta_lms_sanitize_utf8_text'
				: null;

			$qt = (string) ( $question['question_text'] ?? '' );
			$oa = (string) ( $question['option_a'] ?? '' );
			$ob = (string) ( $question['option_b'] ?? '' );
			$oc = (string) ( $question['option_c'] ?? '' );
			$od = (string) ( $question['option_d'] ?? '' );
			$ex = (string) ( $question['explanation'] ?? '' );

			if ( $text ) {
				$qt = $text( $qt );
				$oa = $text( $oa );
				$ob = $text( $ob );
				$oc = $text( $oc );
				$od = $text( $od );
				$ex = $text( $ex );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$q_table,
				array(
					'quiz_id'        => $quiz_id,
					'question_text'  => $qt,
					'option_a'       => $oa,
					'option_b'       => $ob,
					'option_c'       => $oc,
					'option_d'       => $od,
					'correct_option' => $correct,
					'explanation'    => $ex,
					'order_index'    => (int) $index,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);
		}

		return $quiz_id;
	}

	/**
	 * Widen option_* columns so long official stems are not truncated.
	 */
	private static function maybe_widen_option_columns() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		foreach ( array( 'option_a', 'option_b', 'option_c', 'option_d' ) as $col ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ), ARRAY_A );
			if ( empty( $row['Type'] ) ) {
				continue;
			}
			$type = strtolower( (string) $row['Type'] );
			if ( false !== strpos( $type, 'text' ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} MODIFY {$col} text NOT NULL" );
		}
	}
}

}
