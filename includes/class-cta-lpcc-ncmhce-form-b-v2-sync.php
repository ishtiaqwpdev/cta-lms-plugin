<?php
/**
 * LPCC NCMHCE Form B v2.0 learner-item sync (staging / non-public).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Form_B_V2_Sync' ) ) {

class CTA_Lpcc_Ncmhce_Form_B_V2_Sync {

	const SEED_OPTION  = 'cta_lpcc_ncmhce_form_b_v2_items_1_0_264';
	const QUIZ_TYPE    = 'form_b_v2';
	const FORM_TITLE   = '[STAGING] Form B v2.0 — 143-Question Comprehensive Simulation';
	const LEARNER_FILE = 'includes/quiz-seeds/lpcc-ncmhce-form-b-v2-items.php';
	const TARGET_COUNT = 143;
	const TIME_LIMIT   = 225;
	const SORT_ORDER   = 31;

	public static function is_staging_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}
		return self::QUIZ_TYPE === sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
	}

	public static function current_user_can_preview() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

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

	public static function find_quiz( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return null;
		}
		foreach ( (array) CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			if ( self::is_staging_quiz( $row ) ) {
				return $row;
			}
		}
		return null;
	}

	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'quiz_id'   => 0,
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
		$quiz_id = self::replace_staging_quiz( (int) $course->id, $questions );
		if ( ! $quiz_id ) {
			return array(
				'ok'        => false,
				'course_id' => (int) $course->id,
				'quiz_id'   => 0,
				'message'   => 'quiz_write_failed',
			);
		}
		update_option( self::SEED_OPTION, 1, false );
		return array(
			'ok'        => true,
			'course_id' => (int) $course->id,
			'quiz_id'   => $quiz_id,
			'message'   => 'synced',
		);
	}

	private static function replace_staging_quiz( $course_id, array $questions ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		$quiz_table = $wpdb->prefix . 'cta_quizzes';
		$existing   = self::find_quiz( $course_id );
		$fields     = array(
			'title'           => self::FORM_TITLE,
			'quiz_type'       => self::QUIZ_TYPE,
			'passing_score'   => 0,
			'time_limit_mins' => self::TIME_LIMIT,
			'max_attempts'    => 0,
			'status'          => 'draft',
			'sort_order'      => self::SORT_ORDER,
		);

		if ( $existing ) {
			$quiz_id = (int) $existing->id;
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
