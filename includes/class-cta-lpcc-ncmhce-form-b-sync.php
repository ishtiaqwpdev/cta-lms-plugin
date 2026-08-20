<?php
/**
 * LPCC NCMHCE Form B v2.0 live assessment sync (post-cutover).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_B_Sync' ) ) {

class CTA_Lpcc_Ncmhce_Form_B_Sync {

	const SEED_OPTION   = 'cta_lpcc_ncmhce_form_b_v2_live_1_0_265';
	const QUIZ_TYPE     = 'form_b';
	const FORM_TITLE    = 'Form B — 143-Question Comprehensive Simulation (Candidate Exam)';
	const LEARNER_FILE  = 'includes/quiz-seeds/lpcc-ncmhce-form-b-v2-items.php';
	const TARGET_COUNT  = 143;
	const TIME_LIMIT    = 225;
	const SORT_ORDER    = 30;
	const PASSING_SCORE = 70;

	public static function get_questions() {
		$path = CTA_PLUGIN_DIR . self::LEARNER_FILE;
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$rows = include $path;
		return is_array( $rows ) ? $rows : array();
	}

	public static function get_question_code_order_map() {
		$map = array();
		foreach ( self::get_questions() as $index => $row ) {
			$code = isset( $row['question_code'] ) ? trim( (string) $row['question_code'] ) : '';
			if ( '' !== $code ) {
				$map[ (int) $index ] = $code;
			}
		}
		return $map;
	}

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

	public static function is_live_v2_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}
		if ( self::QUIZ_TYPE !== sanitize_key( (string) ( $quiz->quiz_type ?? '' ) ) ) {
			return false;
		}
		if ( self::is_legacy_row( $quiz ) ) {
			return false;
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Simulation' )
			&& ! CTA_Lpcc_Ncmhce_Simulation::is_ncmhce_course_quiz( $quiz ) ) {
			return false;
		}
		return 'active' === (string) ( $quiz->status ?? '' );
	}

	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			$stored = get_option( self::SEED_OPTION, array() );
			return array(
				'ok'        => true,
				'course_id' => (int) ( is_array( $stored ) ? ( $stored['course_id'] ?? 0 ) : 0 ),
				'quiz_id'   => (int) ( is_array( $stored ) ? ( $stored['quiz_id'] ?? 0 ) : 0 ),
				'message'   => 'already_seeded',
			);
		}
		if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'message'   => 'program_sync_missing',
			);
		}
		$course = CTA_Lpcc_Ncmhce_Sync::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'message'   => 'course_not_found',
			);
		}
		$questions = self::get_questions();
		if ( self::TARGET_COUNT !== count( $questions ) ) {
			return array(
				'ok'        => false,
				'course_id' => (int) $course->id,
				'quiz_id'   => 0,
				'message'   => 'invalid_question_count',
			);
		}
		$quiz_id = self::replace_form_quiz( (int) $course->id, $questions );
		if ( ! $quiz_id ) {
			return array(
				'ok'        => false,
				'course_id' => (int) $course->id,
				'quiz_id'   => 0,
				'message'   => 'quiz_write_failed',
			);
		}
		update_option(
			self::SEED_OPTION,
			array(
				'at'        => current_time( 'mysql' ),
				'course_id' => (int) $course->id,
				'quiz_id'   => $quiz_id,
			),
			false
		);
		return array(
			'ok'        => true,
			'course_id' => (int) $course->id,
			'quiz_id'   => $quiz_id,
			'message'   => 'synced',
		);
	}

	private static function replace_form_quiz( $course_id, array $questions ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		$quiz_table  = $wpdb->prefix . 'cta_quizzes';
		$quiz        = null;
		$existing_id = self::find_form_quiz_id( $course_id );
		if ( $existing_id && class_exists( 'CTA_Database' ) ) {
			$quiz = CTA_Database::get_quiz( $existing_id );
		}

		$fields = array(
			'title'           => self::FORM_TITLE,
			'quiz_type'       => self::QUIZ_TYPE,
			'passing_score'   => self::PASSING_SCORE,
			'time_limit_mins' => self::TIME_LIMIT,
			'max_attempts'    => 0,
			'status'          => 'active',
			'sort_order'      => self::SORT_ORDER,
		);

		if ( $quiz ) {
			$quiz_id = (int) $quiz->id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$quiz_table,
				$fields,
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
					'title'           => $fields['title'],
					'quiz_type'       => $fields['quiz_type'],
					'sort_order'      => $fields['sort_order'],
					'passing_score'   => $fields['passing_score'],
					'time_limit_mins' => $fields['time_limit_mins'],
					'max_attempts'    => $fields['max_attempts'],
					'status'          => $fields['status'],
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
			$qt = (string) ( $question['question_text'] ?? '' );
			$oa = (string) ( $question['option_a'] ?? '' );
			$ob = (string) ( $question['option_b'] ?? '' );
			$oc = (string) ( $question['option_c'] ?? '' );
			$od = (string) ( $question['option_d'] ?? '' );
			if ( $text ) {
				$qt = $text( $qt );
				$oa = $text( $oa );
				$ob = $text( $ob );
				$oc = $text( $oc );
				$od = $text( $od );
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
					'correct_option' => 'x',
					'explanation'    => '',
					'order_index'    => (int) $index,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);
		}
		return $quiz_id;
	}

	private static function is_legacy_row( $row ) {
		if ( ! $row ) {
			return false;
		}
		$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
		if ( in_array( $type, array( 'legacy_form_a', 'legacy_form_b' ), true ) ) {
			return true;
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Legacy_Forms_Archive' ) ) {
			return CTA_Lpcc_Ncmhce_Legacy_Forms_Archive::is_archived_quiz( $row );
		}
		return 'archived' === (string) ( $row->status ?? '' );
	}

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
