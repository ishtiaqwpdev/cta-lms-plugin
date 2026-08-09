<?php
/**
 * California Law & Ethics (CTA-CE-001) final examination seed (course-scoped only).
 *
 * Loads the official 25-question final exam on staging. Keeps the CE course in
 * Draft pending CAMFT CEPA approval — never publishes as part of sync.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Law_Ethics_Exam_Sync
 */
if ( ! class_exists( 'CTA_Law_Ethics_Exam_Sync' ) ) {

class CTA_Law_Ethics_Exam_Sync {

	const COURSE_CODE = 'CTA-CE-001';
	const QUIZ_TITLE  = 'Final Examination';
	const SEED_OPTION = 'cta_law_ethics_final_exam_seeded_1_0_185';

	/**
	 * Title aliases used to locate the Law & Ethics CE course.
	 *
	 * @return string[]
	 */
	public static function match_titles() {
		return class_exists( 'CTA_Law_Ethics_Module_Sync' )
			? CTA_Law_Ethics_Module_Sync::match_titles()
			: array(
				'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
				'California Law & Ethics for Mental Health Professionals',
			);
	}

	/**
	 * Load the official 25-question bank (exact CTA wording).
	 *
	 * @return array[]
	 */
	public static function get_questions() {
		$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/law-ethics-final-exam.php';
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * Find the Law & Ethics CE course by code, then title aliases.
	 *
	 * @return object|null
	 */
	public static function find_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

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
	 * Seed/replace Law & Ethics final exam. Does not publish the CE course.
	 *
	 * @param bool $force Re-run even if already seeded at this version.
	 * @return array{ok:bool,course_id:int,quiz_id:int,questions:int,message:string}
	 */
	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'quiz_id'   => 0,
				'questions' => 0,
				'message'   => 'already_seeded',
			);
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'questions' => 0,
				'message'   => 'law_ethics_course_not_found',
			);
		}

		$course_id = (int) $course->id;
		$questions = self::get_questions();

		if ( 25 !== count( $questions ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => 0,
				'questions' => count( $questions ),
				'message'   => 'invalid_question_bank_count',
			);
		}

		$quiz_id = self::replace_final_exam( $course_id, $questions );
		if ( ! $quiz_id ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => 0,
				'questions' => 0,
				'message'   => 'quiz_write_failed',
			);
		}

		// CAMFT CEPA hold: never leave CTA-CE-001 published from this sync path.
		if ( class_exists( 'CTA_Course_Catalog' ) ) {
			CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
		}

		update_option(
			self::SEED_OPTION,
			array(
				'at'        => current_time( 'mysql' ),
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'questions' => 25,
				'passing'   => 70,
			),
			false
		);

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'quiz_id'   => $quiz_id,
			'questions' => 25,
			'message'   => 'synced',
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
		self::maybe_widen_option_columns();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $q_table, array( 'quiz_id' => $quiz_id ), array( '%d' ) );

		foreach ( $questions as $index => $question ) {
			$correct = isset( $question['correct_option'] ) ? strtolower( (string) $question['correct_option'] ) : 'a';
			$correct = in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ? $correct : 'a';

			$sanitize = function_exists( 'cta_lms_sanitize_utf8_text' )
				? 'cta_lms_sanitize_utf8_text'
				: null;

			$qt = (string) ( $question['question_text'] ?? '' );
			$oa = (string) ( $question['option_a'] ?? '' );
			$ob = (string) ( $question['option_b'] ?? '' );
			$oc = (string) ( $question['option_c'] ?? '' );
			$od = (string) ( $question['option_d'] ?? '' );
			$ex = (string) ( $question['explanation'] ?? '' );

			if ( $sanitize ) {
				$qt = $sanitize( $qt );
				$oa = $sanitize( $oa );
				$ob = $sanitize( $ob );
				$oc = $sanitize( $oc );
				$od = $sanitize( $od );
				$ex = $sanitize( $ex );
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
