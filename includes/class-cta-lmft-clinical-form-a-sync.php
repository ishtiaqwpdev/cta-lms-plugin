<?php
/**
 * Comprehensive Simulation Form A import/sync for LMFT California Clinical (course_id=10).
 *
 * PROMPT 01+: rebuilds the active form_a assessment after legacy forms are archived.
 * Preserves fixed question order and fixed A–D choice order (no randomization).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Form_A_Sync
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {

class CTA_Lmft_Clinical_Form_A_Sync {

	const SEED_OPTION            = 'cta_lmft_clinical_form_a_build_1_0_226';
	const TARGET_COURSE_ID       = 10;
	const QUIZ_TYPE                = 'form_a';
	const FORM_TITLE               = 'Comprehensive Simulation - Form A';
	const TARGET_QUESTION_COUNT    = 150;
	const IMPORTED_THROUGH         = 150;
	const PASSING_SCORE            = 70;
	const TIME_LIMIT_MINS          = 240;
	const SORT_ORDER               = 20;
	const PENDING_CORRECT_OPTION   = 'x';
	const SEED_FILE                = 'includes/quiz-seeds/lmft-clinical-form-a.php';

	/**
	 * @return object|null
	 */
	public static function find_course() {
		if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
			$course_id = CTA_Lmft_Clinical_Legacy_Forms_Archive::resolve_course_id( self::TARGET_COURSE_ID );
			if ( $course_id && class_exists( 'CTA_Database' ) ) {
				return CTA_Database::get_course( $course_id );
			}
		}

		return class_exists( 'CTA_Lmft_Clinical_Sync' )
			? CTA_Lmft_Clinical_Sync::find_course()
			: null;
	}

	/**
	 * @return array[]
	 */
	public static function get_questions() {
		$path = CTA_PLUGIN_DIR . self::SEED_FILE;
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * @param array $questions Question rows.
	 * @return int
	 */
	public static function count_imported_items( array $questions ) {
		$max = 0;
		foreach ( $questions as $index => $row ) {
			$text = (string) ( $row['question_text'] ?? '' );
			if ( 0 === strpos( $text, '[Import pending' ) ) {
				continue;
			}
			$max = max( $max, $index + 1 );
		}
		return $max;
	}

	/**
	 * @param array $questions Question rows.
	 * @return string
	 */
	public static function resolve_quiz_status( array $questions ) {
		$imported = self::count_imported_items( $questions );
		return ( self::TARGET_QUESTION_COUNT === count( $questions ) && $imported >= self::TARGET_QUESTION_COUNT )
			? 'active'
			: 'inactive';
	}

	/**
	 * @param bool $force Re-run even if seed option is set.
	 * @return array{ok:bool,course_id:int,quiz_id:int,questions:int,imported:int,status:string,message:string}
	 */
	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			$stored = get_option( self::SEED_OPTION, array() );
			return array(
				'ok'        => true,
				'course_id' => (int) ( $stored['course_id'] ?? 0 ),
				'quiz_id'   => (int) ( $stored['quiz_id'] ?? 0 ),
				'questions' => (int) ( $stored['questions'] ?? 0 ),
				'imported'  => (int) ( $stored['imported'] ?? 0 ),
				'status'    => (string) ( $stored['status'] ?? 'inactive' ),
				'message'   => 'already_seeded',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'questions' => 0,
				'imported'  => 0,
				'status'    => 'inactive',
				'message'   => 'course_not_found',
			);
		}

		$questions = self::get_questions();
		if ( self::TARGET_QUESTION_COUNT !== count( $questions ) ) {
			return array(
				'ok'        => false,
				'course_id' => (int) $course->id,
				'quiz_id'   => 0,
				'questions' => count( $questions ),
				'imported'  => self::count_imported_items( $questions ),
				'status'    => 'inactive',
				'message'   => 'invalid_question_bank_count',
			);
		}

		$imported = self::count_imported_items( $questions );
		if ( $imported < self::IMPORTED_THROUGH ) {
			return array(
				'ok'        => false,
				'course_id' => (int) $course->id,
				'quiz_id'   => 0,
				'questions' => count( $questions ),
				'imported'  => $imported,
				'status'    => 'inactive',
				'message'   => 'imported_items_below_minimum',
			);
		}

		$quiz_id = self::replace_form_quiz( (int) $course->id, $questions );
		if ( ! $quiz_id ) {
			return array(
				'ok'        => false,
				'course_id' => (int) $course->id,
				'quiz_id'   => 0,
				'questions' => count( $questions ),
				'imported'  => $imported,
				'status'    => 'inactive',
				'message'   => 'quiz_write_failed',
			);
		}

		$status = self::resolve_quiz_status( $questions );
		update_option(
			self::SEED_OPTION,
			array(
				'at'        => current_time( 'mysql' ),
				'course_id' => (int) $course->id,
				'quiz_id'   => $quiz_id,
				'questions' => count( $questions ),
				'imported'  => $imported,
				'status'    => $status,
				'title'     => self::FORM_TITLE,
			),
			false
		);

		return array(
			'ok'        => true,
			'course_id' => (int) $course->id,
			'quiz_id'   => $quiz_id,
			'questions' => count( $questions ),
			'imported'  => $imported,
			'status'    => $status,
			'message'   => 'synced',
		);
	}

	/**
	 * @param int   $course_id Course ID.
	 * @param array $questions Question rows in fixed order.
	 * @return int Quiz ID or 0.
	 */
	private static function replace_form_quiz( $course_id, array $questions ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		$quiz_table = $wpdb->prefix . 'cta_quizzes';
		$quiz       = null;

		if ( class_exists( 'CTA_Database' ) ) {
			foreach ( (array) CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
				$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
				if ( self::QUIZ_TYPE === $type && ! self::is_legacy_row( $row ) ) {
					$quiz = $row;
					break;
				}
			}
		}

		$status = self::resolve_quiz_status( $questions );

		if ( $quiz ) {
			$quiz_id = (int) $quiz->id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$quiz_table,
				array(
					'title'           => self::FORM_TITLE,
					'quiz_type'       => self::QUIZ_TYPE,
					'passing_score'   => self::PASSING_SCORE,
					'time_limit_mins' => self::TIME_LIMIT_MINS,
					'max_attempts'    => 0,
					'status'          => $status,
					'sort_order'      => self::SORT_ORDER,
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
					'title'           => self::FORM_TITLE,
					'quiz_type'       => self::QUIZ_TYPE,
					'sort_order'      => self::SORT_ORDER,
					'passing_score'   => self::PASSING_SCORE,
					'time_limit_mins' => self::TIME_LIMIT_MINS,
					'max_attempts'    => 0,
					'status'          => $status,
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

		self::maybe_widen_option_columns();

		$q_table = $wpdb->prefix . 'cta_quiz_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $q_table, array( 'quiz_id' => $quiz_id ), array( '%d' ) );

		$text = function_exists( 'cta_lms_sanitize_utf8_text' ) ? 'cta_lms_sanitize_utf8_text' : null;

		foreach ( $questions as $index => $question ) {
			$correct = isset( $question['correct_option'] ) ? strtolower( (string) $question['correct_option'] ) : self::PENDING_CORRECT_OPTION;
			if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd', 'x' ), true ) ) {
				$correct = self::PENDING_CORRECT_OPTION;
			}

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

		$columns = array(
			'option_a' => "ALTER TABLE {$table} MODIFY option_a TEXT NOT NULL",
			'option_b' => "ALTER TABLE {$table} MODIFY option_b TEXT NOT NULL",
			'option_c' => "ALTER TABLE {$table} MODIFY option_c TEXT NOT NULL",
			'option_d' => "ALTER TABLE {$table} MODIFY option_d TEXT NOT NULL",
		);

		foreach ( $columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
			if ( empty( $col ) ) {
				continue;
			}
			$type = strtolower( (string) ( $col[0]->Type ?? '' ) );
			if ( false !== strpos( $type, 'varchar' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query( $sql );
			}
		}
	}
}

}
