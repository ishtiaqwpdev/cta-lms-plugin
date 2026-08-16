<?php
/**
 * Advanced Suicide Risk Assessment (CTA-CE-003) final examination — learner questions only.
 *
 * Chunk 4: loads question stems and answer choices without correct keys or rationales.
 * Answer keys and rationales are applied separately in a security-gated step (Chunk 5).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Suicide_Risk_Exam_Sync
 */
if ( ! class_exists( 'CTA_Suicide_Risk_Exam_Sync' ) ) {

class CTA_Suicide_Risk_Exam_Sync {

	const COURSE_CODE = 'CTA-CE-003';
	const QUIZ_TITLE  = 'Final Examination';
	const SEED_OPTION = 'cta_suicide_risk_final_exam_learner_1_0_212';
	const ANSWER_SEED_OPTION = 'cta_suicide_risk_final_exam_answers_1_0_213';

	const CRISIS_RESOURCE_NOTE = 'Crisis-resource note: This examination is not a substitute for emergency action. In the United States, 988 is available by call, text, or chat for crisis support; call 911 or use local emergency services when immediate physical danger requires an emergency response.';

	const COPYRIGHT_NOTICE = 'Copyright © 2026 Clinical Training and Supervision Academy. Enrolled learners may use this examination for their own course completion. Sharing, resale, public posting, copying for commercial use, and distribution of controlled answers or rationales are prohibited.';

	/** Placeholder until Chunk 5 applies secured answer keys (NOT a-b-c-d). */
	const PENDING_CORRECT_OPTION = 'x';

	/**
	 * Absolute path to the admin-only secured answer key (never a learner download).
	 *
	 * @return string
	 */
	public static function get_answer_key_path() {
		return CTA_PLUGIN_DIR . 'includes/quiz-seeds/admin-only/suicide-risk-final-exam-answer-key.php';
	}

	/**
	 * Load secured answer key rows keyed by official question_code.
	 *
	 * @return array<string,array{correct_option:string,teaching_point:string}>
	 */
	public static function get_answer_keys() {
		$path = self::get_answer_key_path();
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$rows = include $path;
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Whether this CE final should reveal concise teaching points after submission.
	 *
	 * @param object|null $course Course row.
	 * @param object|null $quiz   Quiz row.
	 * @return bool
	 */
	public static function course_should_reveal_teaching_points( $course, $quiz ) {
		if ( ! $course || ! $quiz ) {
			return false;
		}

		$type = isset( $quiz->quiz_type ) ? (string) $quiz->quiz_type : 'final';
		if ( 'final' !== $type && '' !== $type ) {
			return false;
		}

		$meta = array();
		if ( ! empty( $course->syllabus_meta ) ) {
			$decoded = json_decode( (string) $course->syllabus_meta, true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		return self::COURSE_CODE === (string) ( $meta['course_code'] ?? '' );
	}

	/**
	 * Map order_index => question_code from the learner seed (fixed sequential order).
	 *
	 * @return array<int,string>
	 */
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

	/**
	 * @return string[]
	 */
	public static function match_titles() {
		return class_exists( 'CTA_Suicide_Risk_Module_Sync' )
			? CTA_Suicide_Risk_Module_Sync::match_titles()
			: array(
				'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
				'Advanced Suicide Risk Assessment',
			);
	}

	/**
	 * Verbatim exam instructions for the learner start screen.
	 *
	 * @return string
	 */
	public static function get_exam_instructions() {
		return self::CRISIS_RESOURCE_NOTE . "\n\n" . self::COPYRIGHT_NOTICE;
	}

	/**
	 * Resolve instructions when the course is CTA-CE-003.
	 *
	 * @param object|null $course Course row.
	 * @return string
	 */
	public static function get_exam_instructions_for_course( $course ) {
		if ( ! $course ) {
			return '';
		}

		$code = '';
		if ( ! empty( $course->syllabus_meta ) ) {
			$meta = json_decode( (string) $course->syllabus_meta, true );
			if ( is_array( $meta ) && ! empty( $meta['course_code'] ) ) {
				$code = (string) $meta['course_code'];
			}
		}

		return self::COURSE_CODE === $code ? self::get_exam_instructions() : '';
	}

	/**
	 * Load learner-facing question bank (no correct answers or rationales).
	 *
	 * @return array[]
	 */
	public static function get_questions() {
		$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/suicide-risk-final-exam.php';
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * @return object|null
	 */
	public static function find_course() {
		if ( class_exists( 'CTA_Suicide_Risk_Module_Sync' ) ) {
			return CTA_Suicide_Risk_Module_Sync::find_course();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'cta_courses';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
		foreach ( (array) $rows as $row ) {
			$meta = json_decode( (string) ( $row->syllabus_meta ?? '' ), true );
			if ( is_array( $meta ) && self::COURSE_CODE === (string) ( $meta['course_code'] ?? '' ) ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Seed/replace the learner final exam (questions + choices only).
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
				'message'    => 'suicide_risk_course_not_found',
			);
		}

		$course_id = (int) $course->id;
		$questions = self::get_questions();
		$valid     = self::validate_question_bank( $questions );

		if ( is_wp_error( $valid ) ) {
			return array(
				'ok'         => false,
				'course_id'  => $course_id,
				'quiz_id'    => 0,
				'questions'  => count( $questions ),
				'message'    => $valid->get_error_code(),
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

		if ( class_exists( 'CTA_Course_Catalog' ) ) {
			CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
		}

		update_option(
			self::SEED_OPTION,
			array(
				'at'          => current_time( 'mysql' ),
				'course_id'   => $course_id,
				'quiz_id'     => $quiz_id,
				'questions'   => 25,
				'passing'     => 70,
				'answers'     => 'pending_chunk_5',
			),
			false
		);

		return array(
			'ok'         => true,
			'course_id'  => $course_id,
			'quiz_id'    => $quiz_id,
			'questions'  => 25,
			'message'    => 'synced',
		);
	}

	/**
	 * Validate learner bank count, IDs, and absence of answer keys in the seed file.
	 *
	 * @param array $questions Question rows.
	 * @return true|WP_Error
	 */
	public static function validate_question_bank( array $questions ) {
		if ( 25 !== count( $questions ) ) {
			return new WP_Error( 'invalid_question_bank_count', 'Expected 25 questions.' );
		}

		$codes = array();
		for ( $i = 1; $i <= 25; $i++ ) {
			$expected = sprintf( 'CTA-SRA-FE-%03d', $i );
			$codes[]  = $expected;
		}

		$seen = array();
		foreach ( $questions as $index => $question ) {
			$code = isset( $question['question_code'] ) ? trim( (string) $question['question_code'] ) : '';
			if ( '' === $code ) {
				return new WP_Error( 'missing_question_code', 'Question at index ' . $index . ' is missing question_code.' );
			}
			if ( isset( $seen[ $code ] ) ) {
				return new WP_Error( 'duplicate_question_code', 'Duplicate question_code: ' . $code );
			}
			$seen[ $code ] = true;

			if ( ! in_array( $code, $codes, true ) ) {
				return new WP_Error( 'unexpected_question_code', 'Unexpected question_code: ' . $code );
			}

			foreach ( array( 'question_text', 'option_a', 'option_b', 'option_c', 'option_d' ) as $field ) {
				if ( empty( $question[ $field ] ) ) {
					return new WP_Error( 'missing_question_field', $code . ' missing ' . $field );
				}
			}

			if ( ! empty( $question['correct_option'] ) ) {
				return new WP_Error( 'learner_seed_has_correct_option', $code . ' must not include correct_option in Chunk 4.' );
			}
			if ( ! empty( $question['explanation'] ) ) {
				return new WP_Error( 'learner_seed_has_explanation', $code . ' must not include explanation in Chunk 4.' );
			}
		}

		foreach ( $codes as $expected ) {
			if ( empty( $seen[ $expected ] ) ) {
				return new WP_Error( 'missing_question_code', 'Missing expected question_code: ' . $expected );
			}
		}

		return true;
	}

	/**
	 * Create or update the final quiz and replace all learner questions.
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

		$quiz_row = array(
			'title'           => self::QUIZ_TITLE,
			'quiz_type'       => 'final',
			'passing_score'   => 70,
			'time_limit_mins' => 0,
			'max_attempts'    => 0,
			'status'          => 'active',
		);

		if ( $quiz ) {
			$quiz_id = (int) $quiz->id;
			$quiz_row['sort_order'] = isset( $quiz->sort_order ) ? (int) $quiz->sort_order : 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$quiz_table,
				$quiz_row,
				array( 'id' => $quiz_id ),
				array( '%s', '%s', '%d', '%d', '%d', '%s', '%d' ),
				array( '%d' )
			);
		} else {
			$quiz_row['course_id']  = $course_id;
			$quiz_row['sort_order'] = 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$quiz_table,
				$quiz_row,
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

		$sanitize = function_exists( 'cta_lms_sanitize_utf8_text' ) ? 'cta_lms_sanitize_utf8_text' : null;

		foreach ( $questions as $index => $question ) {
			$qt = (string) ( $question['question_text'] ?? '' );
			$oa = (string) ( $question['option_a'] ?? '' );
			$ob = (string) ( $question['option_b'] ?? '' );
			$oc = (string) ( $question['option_c'] ?? '' );
			$od = (string) ( $question['option_d'] ?? '' );

			if ( $sanitize ) {
				$qt = $sanitize( $qt );
				$oa = $sanitize( $oa );
				$ob = $sanitize( $ob );
				$oc = $sanitize( $oc );
				$od = $sanitize( $od );
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
					'correct_option' => self::PENDING_CORRECT_OPTION,
					'explanation'    => '',
					'order_index'    => (int) $index,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);
		}

		return $quiz_id;
	}

	/**
	 * Apply secured answer keys + teaching points to the existing learner exam rows.
	 *
	 * Updates correct_option and explanation in the database only — never registers
	 * a downloadable answer-key file for learners.
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

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'quiz_id'   => 0,
				'updated'   => 0,
				'message'   => 'suicide_risk_course_not_found',
			);
		}

		$course_id  = (int) $course->id;
		$quiz_id    = self::find_final_quiz_id( $course_id );
		$answer_map = self::get_answer_keys();
		$valid      = self::validate_answer_key( $answer_map );

		if ( is_wp_error( $valid ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => (int) $quiz_id,
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
				'message'   => 'final_quiz_not_found',
			);
		}

		$db_questions = CTA_Database::get_quiz_questions( $quiz_id );
		if ( 25 !== count( $db_questions ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => 0,
				'message'   => 'learner_exam_not_loaded',
			);
		}

		$code_order = self::get_question_code_order_map();
		if ( 25 !== count( $code_order ) ) {
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
		$sanitize = function_exists( 'cta_lms_sanitize_utf8_text' ) ? 'cta_lms_sanitize_utf8_text' : null;

		foreach ( $db_questions as $question ) {
			$order = isset( $question->order_index ) ? (int) $question->order_index : -1;
			$code  = isset( $code_order[ $order ] ) ? (string) $code_order[ $order ] : '';
			if ( '' === $code || empty( $answer_map[ $code ] ) ) {
				continue;
			}

			$key     = $answer_map[ $code ];
			$correct = strtolower( (string) ( $key['correct_option'] ?? '' ) );
			$correct = in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ? $correct : '';
			$point   = (string) ( $key['teaching_point'] ?? '' );

			if ( '' === $correct || '' === $point ) {
				continue;
			}

			if ( $sanitize ) {
				$point = $sanitize( $point );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update(
				$table,
				array(
					'correct_option' => $correct,
					'explanation'    => $point,
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

		if ( 25 !== $updated ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => $updated,
				'message'   => 'partial_answer_apply',
			);
		}

		if ( class_exists( 'CTA_Course_Catalog' ) ) {
			CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
		}

		update_option(
			self::ANSWER_SEED_OPTION,
			array(
				'at'        => current_time( 'mysql' ),
				'course_id' => $course_id,
				'quiz_id'   => $quiz_id,
				'updated'   => $updated,
			),
			false
		);

		$seed = get_option( self::SEED_OPTION );
		if ( is_array( $seed ) ) {
			$seed['answers'] = 'applied';
			update_option( self::SEED_OPTION, $seed, false );
		}

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'quiz_id'   => $quiz_id,
			'updated'   => $updated,
			'message'   => 'synced',
		);
	}

	/**
	 * Validate secured answer key completeness.
	 *
	 * @param array $answer_map Answer rows keyed by question_code.
	 * @return true|WP_Error
	 */
	public static function validate_answer_key( array $answer_map ) {
		if ( 25 !== count( $answer_map ) ) {
			return new WP_Error( 'invalid_answer_key_count', 'Expected 25 secured answer rows.' );
		}

		for ( $i = 1; $i <= 25; $i++ ) {
			$code = sprintf( 'CTA-SRA-FE-%03d', $i );
			if ( empty( $answer_map[ $code ] ) || ! is_array( $answer_map[ $code ] ) ) {
				return new WP_Error( 'missing_answer_key', 'Missing secured answer for ' . $code );
			}

			$correct = strtolower( (string) ( $answer_map[ $code ]['correct_option'] ?? '' ) );
			if ( ! in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ) {
				return new WP_Error( 'invalid_correct_option', $code . ' has invalid correct_option.' );
			}

			if ( '' === trim( (string) ( $answer_map[ $code ]['teaching_point'] ?? '' ) ) ) {
				return new WP_Error( 'missing_teaching_point', $code . ' missing teaching_point.' );
			}
		}

		return true;
	}

	/**
	 * Expected consolidated answer letters Q1–Q25 for validation/scoring tests.
	 *
	 * @return array<int,string> 1-based question number => a|b|c|d
	 */
	public static function get_expected_answer_letters() {
		return array(
			1  => 'c',
			2  => 'a',
			3  => 'd',
			4  => 'b',
			5  => 'd',
			6  => 'c',
			7  => 'a',
			8  => 'b',
			9  => 'c',
			10 => 'd',
			11 => 'b',
			12 => 'a',
			13 => 'd',
			14 => 'c',
			15 => 'b',
			16 => 'a',
			17 => 'd',
			18 => 'c',
			19 => 'a',
			20 => 'b',
			21 => 'd',
			22 => 'c',
			23 => 'b',
			24 => 'd',
			25 => 'a',
		);
	}

	/**
	 * @param int $course_id Course ID.
	 * @return int
	 */
	private static function find_final_quiz_id( $course_id ) {
		if ( ! class_exists( 'CTA_Database' ) ) {
			return 0;
		}

		$course_id = absint( $course_id );
		$all       = CTA_Database::get_quizzes_by_course( $course_id, false );
		foreach ( (array) $all as $row ) {
			$type = isset( $row->quiz_type ) ? (string) $row->quiz_type : 'final';
			if ( 'final' === $type || '' === $type ) {
				return (int) $row->id;
			}
		}

		return ! empty( $all[0]->id ) ? (int) $all[0]->id : 0;
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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ), ARRAY_A );
			if ( empty( $row['Type'] ) ) {
				continue;
			}
			$type = strtolower( (string) $row['Type'] );
			if ( false !== strpos( $type, 'text' ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} MODIFY {$col} text NOT NULL" );
		}
	}
}

}
