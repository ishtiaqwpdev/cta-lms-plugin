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
	 * Attach the approved Telehealth course thumbnail (laptop / video-call branded art).
	 *
	 * Uses Media Library attachment "Telehealth.png" when present; otherwise the
	 * known uploads path. Does not change access period or other course fields.
	 *
	 * @param bool $force Re-run even if already applied at this seed key.
	 * @return array{ok:bool,course_id:int,thumbnail_url:string,message:string}
	 */
	public static function sync_thumbnail( $force = false ) {
		$seed_option = 'cta_telehealth_thumbnail_1_0_111';

		if ( ! $force && get_option( $seed_option ) ) {
			return array(
				'ok'            => true,
				'course_id'     => 0,
				'thumbnail_url' => '',
				'message'       => 'already_seeded',
			);
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'            => false,
				'course_id'     => 0,
				'thumbnail_url' => '',
				'message'       => 'telehealth_course_not_found',
			);
		}

		$thumbnail_url = self::resolve_approved_thumbnail_url();
		if ( '' === $thumbnail_url ) {
			return array(
				'ok'            => false,
				'course_id'     => (int) $course->id,
				'thumbnail_url' => '',
				'message'       => 'thumbnail_asset_not_found',
			);
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'thumbnail_url' => $thumbnail_url ),
			array( 'id' => (int) $course->id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'ok'            => false,
				'course_id'     => (int) $course->id,
				'thumbnail_url' => $thumbnail_url,
				'message'       => 'update_failed',
			);
		}

		update_option( $seed_option, 1, false );

		return array(
			'ok'            => true,
			'course_id'     => (int) $course->id,
			'thumbnail_url' => $thumbnail_url,
			'message'       => 'synced',
		);
	}

	/**
	 * Resolve the approved Telehealth.png URL from Media Library or uploads path.
	 *
	 * @return string
	 */
	public static function resolve_approved_thumbnail_url() {
		$attachment_id = 0;

		// Prefer the known Media Library slug/filename from the Jul 2026 upload.
		$by_slug = get_page_by_path( 'telehealth', OBJECT, 'attachment' );
		if ( $by_slug && ! empty( $by_slug->ID ) ) {
			$attachment_id = (int) $by_slug->ID;
		}

		if ( ! $attachment_id ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_wp_attached_file',
							'value'   => 'Telehealth.png',
							'compare' => 'LIKE',
						),
					),
				)
			);
			if ( ! empty( $query->posts[0] ) ) {
				$attachment_id = (int) $query->posts[0];
			}
			wp_reset_postdata();
		}

		if ( $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				return esc_url_raw( $url );
			}
		}

		// Stable fallback matching the approved Media Library asset path.
		return esc_url_raw( content_url( 'uploads/2026/07/Telehealth.png' ) );
	}

	/**
	 * Module order => Vimeo video URL for CTA-CE-002 instructional videos.
	 *
	 * Stored in cta_course_modules.video_url (same field used by CE course player).
	 *
	 * @return array<int,string> 1-based order_index => https://vimeo.com/{id}
	 */
	public static function get_module_video_map() {
		return array(
			1 => 'https://vimeo.com/1213776719', // Module 1 – Legal Foundations.
			2 => 'https://vimeo.com/1213835801', // Module 2 – Intake & Identity.
			3 => 'https://vimeo.com/1214204058', // Module 3 – Security & Privacy.
		);
	}

	/**
	 * Title keywords used as a fallback when order_index does not match 1–3.
	 *
	 * @return array<int,string[]>
	 */
	private static function get_module_title_hints() {
		return array(
			1 => array( 'legal foundations', 'jurisdictional' ),
			2 => array( 'intake', 'identity', 'location verification' ),
			3 => array( 'hipaa', 'security', 'privacy', 'professional boundaries' ),
		);
	}

	/**
	 * Attach official Telehealth module Vimeo videos without changing titles/order.
	 *
	 * @param bool $force Re-run even if already applied at this seed key.
	 * @return array{ok:bool,course_id:int,updated:int,message:string,modules:array}
	 */
	public static function sync_module_videos( $force = false ) {
		$seed_option = 'cta_telehealth_module_videos_1_0_110';

		if ( ! $force && get_option( $seed_option ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'updated'   => 0,
				'message'   => 'already_seeded',
				'modules'   => array(),
			);
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'updated'   => 0,
				'message'   => 'telehealth_course_not_found',
				'modules'   => array(),
			);
		}

		$course_id = (int) $course->id;
		$modules   = class_exists( 'CTA_Database' )
			? CTA_Database::get_course_modules( $course_id )
			: array();

		if ( empty( $modules ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'updated'   => 0,
				'message'   => 'no_modules',
				'modules'   => array(),
			);
		}

		$video_map = self::get_module_video_map();
		$hints     = self::get_module_title_hints();
		$assigned  = array();
		$report    = array();
		$updated   = 0;

		// Prefer stable order_index 1–3.
		foreach ( $modules as $module ) {
			$order = (int) ( $module->order_index ?? 0 );
			if ( ! isset( $video_map[ $order ] ) || isset( $assigned[ $order ] ) ) {
				continue;
			}
			if ( self::apply_module_video( (int) $module->id, $video_map[ $order ] ) ) {
				++$updated;
			}
			$assigned[ $order ] = true;
			$report[]           = array(
				'id'        => (int) $module->id,
				'title'     => (string) $module->title,
				'order'     => $order,
				'video_url' => $video_map[ $order ],
			);
		}

		// Fallback: match by title keywords for any unassigned slot.
		foreach ( $video_map as $order => $url ) {
			if ( isset( $assigned[ $order ] ) ) {
				continue;
			}
			foreach ( $modules as $module ) {
				$mid = (int) $module->id;
				if ( in_array( $mid, array_column( $report, 'id' ), true ) ) {
					continue;
				}
				$title_l = strtolower( (string) $module->title );
				$matched = false;
				foreach ( (array) ( $hints[ $order ] ?? array() ) as $hint ) {
					if ( '' !== $hint && false !== strpos( $title_l, $hint ) ) {
						$matched = true;
						break;
					}
				}
				if ( ! $matched ) {
					continue;
				}
				if ( self::apply_module_video( $mid, $url ) ) {
					++$updated;
				}
				$assigned[ $order ] = true;
				$report[]           = array(
					'id'        => $mid,
					'title'     => (string) $module->title,
					'order'     => $order,
					'video_url' => $url,
				);
				break;
			}
		}

		if ( count( $assigned ) < count( $video_map ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'updated'   => $updated,
				'message'   => 'partial_module_match',
				'modules'   => $report,
			);
		}

		update_option( $seed_option, 1, false );

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'updated'   => $updated,
			'message'   => 'synced',
			'modules'   => $report,
		);
	}

	/**
	 * Write a module video_url.
	 *
	 * @param int    $module_id Module ID.
	 * @param string $video_url Canonical Vimeo URL.
	 * @return bool
	 */
	private static function apply_module_video( $module_id, $video_url ) {
		global $wpdb;

		$module_id = absint( $module_id );
		$video_url = esc_url_raw( (string) $video_url );

		if ( ! $module_id || '' === $video_url ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->prefix . 'cta_course_modules',
			array( 'video_url' => $video_url ),
			array( 'id' => $module_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
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
