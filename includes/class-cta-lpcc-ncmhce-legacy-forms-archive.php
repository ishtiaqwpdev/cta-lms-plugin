<?php
/**
 * Archive legacy LPCC NCMHCE Form A/B assessments before v2.0 go-live.
 *
 * Marks existing form_a / form_b quizzes and v1.0 printable simulations as
 * archived so learners cannot reach them, while preserving DB rows and history.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Legacy_Forms_Archive' ) ) {

class CTA_Lpcc_Ncmhce_Legacy_Forms_Archive {

	const ARCHIVE_OPTION        = 'cta_lpcc_ncmhce_legacy_forms_archived_1_0_265';
	const CUTOVER_OPTION        = 'cta_lpcc_ncmhce_v2_forms_live_1_0_265';
	const ARCHIVED_STATUS       = 'archived';
	const ARCHIVED_TITLE_PREFIX = '[Archived] ';
	const ARCHIVED_SORT_FORM_A  = 920;
	const ARCHIVED_SORT_FORM_B  = 930;
	const ARCHIVED_RESOURCE_SORT = 900;

	const V2_FORM_A_Q1_NEEDLE = 'CASE 1 — MAYA R.';
	const V2_FORM_B_Q1_NEEDLE = 'CASE 1: Lena M.';

	const LEGACY_FORM_A_Q1_NEEDLE = 'What should the counselor do FIRST in response to the information in this section';
	const LEGACY_FORM_B_Q1_NEEDLE = 'What should the counselor do FIRST based on the information available';

	/**
	 * @return string[]
	 */
	public static function legacy_quiz_types() {
		return array( 'form_a', 'form_b', 'legacy_form_a', 'legacy_form_b', 'form_a_v2', 'form_b_v2' );
	}

	/**
	 * @return array<string,string>
	 */
	public static function legacy_quiz_type_map() {
		return array(
			'form_a' => 'legacy_form_a',
			'form_b' => 'legacy_form_b',
		);
	}

	/**
	 * @return string[]
	 */
	public static function legacy_resource_path_markers() {
		return array(
			'simulations/CTA_LPCC_Comprehensive_Simulation_Form_A_143_Question_Candidate_Exam_v1.0.docx',
			'simulations/CTA_LPCC_Comprehensive_Simulation_Form_B_143_Question_Candidate_Exam_v1.0.docx',
			'simulations/CTA_LPCC_Comprehensive_Simulation_Form_A_143_Question_Answer_Rationales_v1.0.docx',
			'simulations/CTA_LPCC_Comprehensive_Simulation_Form_B_143_Question_Answer_Rationales_v1.0.docx',
		);
	}

	/**
	 * @param int $course_id Optional course ID.
	 * @return int
	 */
	public static function resolve_course_id( $course_id = 0 ) {
		$course_id = absint( $course_id );
		if ( $course_id > 0 ) {
			return $course_id;
		}

		if ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
			$course = CTA_Lpcc_Ncmhce_Sync::find_course();
			if ( $course && ! empty( $course->id ) ) {
				return (int) $course->id;
			}
		}

		return 0;
	}

	/**
	 * @param object $course Course row.
	 * @return bool
	 */
	public static function is_ncmhce_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		$slug = strtolower( (string) ( $course->slug ?? '' ) );
		if ( in_array( $slug, array( 'lpcc-ncmhce-exam-preparation', 'lpcc-california-clinical-exam-preparation' ), true ) ) {
			return true;
		}

		$title = strtolower( (string) ( $course->title ?? '' ) );
		return false !== strpos( $title, 'lpcc' ) && false !== strpos( $title, 'ncmhce' );
	}

	/**
	 * @param int $course_id Optional course ID.
	 * @return bool
	 */
	public static function is_legacy_forms_archived( $course_id = 0 ) {
		$record = get_option( self::ARCHIVE_OPTION, array() );
		if ( ! is_array( $record ) || empty( $record['archived'] ) ) {
			return false;
		}

		$stored_id = absint( $record['course_id'] ?? 0 );
		$course_id = self::resolve_course_id( $course_id );

		if ( $course_id && $stored_id && $course_id !== $stored_id ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int $course_id Optional course ID.
	 * @return bool
	 */
	public static function is_v2_cutover_complete( $course_id = 0 ) {
		$record = get_option( self::CUTOVER_OPTION, array() );
		if ( ! is_array( $record ) || empty( $record['live'] ) ) {
			return false;
		}

		$stored_id = absint( $record['course_id'] ?? 0 );
		$course_id = self::resolve_course_id( $course_id );

		if ( $course_id && $stored_id && $course_id !== $stored_id ) {
			return false;
		}

		return true;
	}

	/**
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_archived_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( in_array( $type, array( 'legacy_form_a', 'legacy_form_b', 'form_a_v2', 'form_b_v2' ), true ) ) {
			return true;
		}

		if ( self::ARCHIVED_STATUS === (string) ( $quiz->status ?? '' ) ) {
			return true;
		}

		return self::title_is_archived( (string) ( $quiz->title ?? '' ) );
	}

	/**
	 * Whether learners may open this quiz row (active v2.0 live forms only).
	 *
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_learner_accessible_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		if ( self::is_archived_quiz( $quiz ) ) {
			return false;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( ! in_array( $type, array( 'form_a', 'form_b' ), true ) ) {
			return true;
		}

		return 'active' === (string) ( $quiz->status ?? '' );
	}

	/**
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function is_archived_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		return self::title_is_archived( (string) ( $resource->title ?? '' ) );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function title_is_archived( $title ) {
		return 0 === stripos( trim( (string) $title ), self::ARCHIVED_TITLE_PREFIX );
	}

	/**
	 * @param object $resource Resource row.
	 * @return bool
	 */
	public static function matches_legacy_form_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		$haystack = strtolower(
			str_replace(
				'\\',
				'/',
				(string) ( $resource->file_path ?? '' ) . ' ' .
				(string) ( $resource->file_url ?? '' ) . ' ' .
				(string) ( $resource->title ?? '' )
			)
		);

		if ( '' === trim( $haystack ) ) {
			return false;
		}

		foreach ( self::legacy_resource_path_markers() as $marker ) {
			if ( false !== strpos( $haystack, strtolower( $marker ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Archive only legacy (non-v2) active Form A/B rows.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if archive option is set.
	 * @return array{ok:bool,course_id:int,form_a:int,form_b:int,resources:int,message:string}
	 */
	public static function archive_legacy_forms( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );

		if ( ! $course_id ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'form_a'    => 0,
				'form_b'    => 0,
				'resources' => 0,
				'message'   => 'course_not_found',
			);
		}

		if ( ! $force && self::is_legacy_forms_archived( $course_id ) ) {
			$stored = get_option( self::ARCHIVE_OPTION, array() );
			return array(
				'ok'        => true,
				'course_id' => $course_id,
				'form_a'    => (int) ( $stored['form_a_quiz_id'] ?? 0 ),
				'form_b'    => (int) ( $stored['form_b_quiz_id'] ?? 0 ),
				'resources' => count( self::get_archived_resource_ids( $course_id ) ),
				'message'   => 'already_archived',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		$quiz_result = self::archive_legacy_quizzes( $course_id );
		$res_result  = self::archive_legacy_resources( $course_id );

		update_option(
			self::ARCHIVE_OPTION,
			array(
				'archived'       => true,
				'at'             => current_time( 'mysql' ),
				'course_id'      => $course_id,
				'form_a_quiz_id' => (int) ( $quiz_result['form_a'] ?? 0 ),
				'form_b_quiz_id' => (int) ( $quiz_result['form_b'] ?? 0 ),
				'resource_ids'   => $res_result['resource_ids'],
			),
			false
		);

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'form_a'    => (int) ( $quiz_result['form_a'] ?? 0 ),
			'form_b'    => (int) ( $quiz_result['form_b'] ?? 0 ),
			'resources' => count( $res_result['resource_ids'] ),
			'message'   => 'archived',
		);
	}

	/**
	 * Archive draft staging form_a_v2 / form_b_v2 rows after live promotion.
	 *
	 * @param int $course_id Course ID.
	 * @return array{form_a_v2:int,form_b_v2:int}
	 */
	public static function archive_staging_v2_quizzes( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$out       = array(
			'form_a_v2' => 0,
			'form_b_v2' => 0,
		);

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return $out;
		}

		$table = $wpdb->prefix . 'cta_quizzes';
		$sorts = array(
			'form_a_v2' => 921,
			'form_b_v2' => 931,
		);

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( ! isset( $sorts[ $type ] ) ) {
				continue;
			}

			$quiz_id = (int) $row->id;
			$title   = self::prefix_archived_title( (string) ( $row->title ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'      => $title,
					'status'     => self::ARCHIVED_STATUS,
					'sort_order' => (int) $sorts[ $type ],
				),
				array( 'id' => $quiz_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);

			$out[ $type ] = $quiz_id;
		}

		return $out;
	}

	/**
	 * Atomic v2.0 cutover: archive legacy, publish live v2 forms, merge keys, archive staging.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if cutover option is set.
	 * @return array{ok:bool,course_id:int,message:string,details:array}
	 */
	public static function perform_v2_cutover( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );

		if ( ! $course_id ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'message'   => 'course_not_found',
				'details'   => array(),
			);
		}

		if ( ! $force && self::is_v2_cutover_complete( $course_id ) ) {
			return array(
				'ok'        => true,
				'course_id' => $course_id,
				'message'   => 'already_live',
				'details'   => (array) get_option( self::CUTOVER_OPTION, array() ),
			);
		}

		$details = array();

		$details['archive'] = self::archive_legacy_forms( $course_id, true );

		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' ) ) {
			$details['form_a'] = CTA_Lpcc_Ncmhce_Form_A_Sync::sync( true );
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_Sync' ) ) {
			$details['form_b'] = CTA_Lpcc_Ncmhce_Form_B_Sync::sync( true );
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync' ) ) {
			$details['form_a_answers'] = CTA_Lpcc_Ncmhce_Form_A_V2_Answer_Sync::sync_answer_keys( true );
		}
		if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync' ) ) {
			$details['form_b_answers'] = CTA_Lpcc_Ncmhce_Form_B_V2_Answer_Sync::sync_answer_keys( true );
		}

		$details['staging_archived'] = self::archive_staging_v2_quizzes( $course_id );

		$ok = ! empty( $details['form_a']['ok'] ) && ! empty( $details['form_b']['ok'] )
			&& ! empty( $details['form_a_answers']['ok'] ) && ! empty( $details['form_b_answers']['ok'] );

		if ( $ok ) {
			update_option(
				self::CUTOVER_OPTION,
				array(
					'live'          => true,
					'at'            => current_time( 'mysql' ),
					'course_id'     => $course_id,
					'form_a_quiz_id' => (int) ( $details['form_a']['quiz_id'] ?? 0 ),
					'form_b_quiz_id' => (int) ( $details['form_b']['quiz_id'] ?? 0 ),
				),
				false
			);
		}

		return array(
			'ok'        => $ok,
			'course_id' => $course_id,
			'message'   => $ok ? 'live' : 'cutover_incomplete',
			'details'   => $details,
		);
	}

	/**
	 * @param int $quiz_id Quiz ID.
	 * @return string
	 */
	public static function get_quiz_q1_text( $quiz_id ) {
		global $wpdb;

		$quiz_id = absint( $quiz_id );
		if ( ! $quiz_id ) {
			return '';
		}

		$table = $wpdb->prefix . 'cta_quiz_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$text = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT question_text FROM {$table} WHERE quiz_id = %d ORDER BY order_index ASC, id ASC LIMIT 1",
				$quiz_id
			)
		);

		return is_string( $text ) ? $text : '';
	}

	/**
	 * @param int $course_id Course ID.
	 * @return array{form_a:int,form_b:int}
	 */
	private static function archive_legacy_quizzes( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$out       = array(
			'form_a' => 0,
			'form_b' => 0,
		);

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return $out;
		}

		$table    = $wpdb->prefix . 'cta_quizzes';
		$sorts    = array(
			'form_a'        => self::ARCHIVED_SORT_FORM_A,
			'form_b'        => self::ARCHIVED_SORT_FORM_B,
			'legacy_form_a' => self::ARCHIVED_SORT_FORM_A,
			'legacy_form_b' => self::ARCHIVED_SORT_FORM_B,
		);
		$type_map = self::legacy_quiz_type_map();

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( ! in_array( $type, array( 'form_a', 'form_b' ), true ) ) {
				continue;
			}

			if ( 'active' !== (string) ( $row->status ?? '' ) ) {
				continue;
			}

			$q1 = self::get_quiz_q1_text( (int) $row->id );
			if ( false !== strpos( $q1, self::V2_FORM_A_Q1_NEEDLE ) || false !== strpos( $q1, self::V2_FORM_B_Q1_NEEDLE ) ) {
				continue;
			}

			$legacy_type = $type_map[ $type ] ?? $type;
			$quiz_id     = (int) $row->id;
			$title       = self::prefix_archived_title( (string) ( $row->title ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'      => $title,
					'quiz_type'  => $legacy_type,
					'status'     => self::ARCHIVED_STATUS,
					'sort_order' => (int) ( $sorts[ $legacy_type ] ?? self::ARCHIVED_SORT_FORM_A ),
				),
				array( 'id' => $quiz_id ),
				array( '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);

			$out[ $type ] = $quiz_id;
		}

		return $out;
	}

	/**
	 * @param int $course_id Course ID.
	 * @return array{resource_ids:int[]}
	 */
	private static function archive_legacy_resources( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$ids       = array();

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return array( 'resource_ids' => $ids );
		}

		$table = $wpdb->prefix . 'cta_downloadable_resources';
		$order = self::ARCHIVED_RESOURCE_SORT;

		foreach ( (array) CTA_Database::get_downloadable_resources( $course_id ) as $resource ) {
			if ( ! self::matches_legacy_form_resource( $resource ) ) {
				continue;
			}

			$resource_id = (int) $resource->id;
			$title       = self::prefix_archived_title( (string) ( $resource->title ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'                  => $title,
					'order_index'            => $order,
					'unlock_after_quiz_type' => '',
				),
				array( 'id' => $resource_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			$ids[] = $resource_id;
			++$order;
		}

		return array( 'resource_ids' => $ids );
	}

	/**
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	public static function get_archived_resource_ids( $course_id = 0 ) {
		$course_id = self::resolve_course_id( $course_id );
		$ids       = array();

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return $ids;
		}

		foreach ( (array) CTA_Database::get_downloadable_resources( $course_id ) as $resource ) {
			if ( self::is_archived_resource( $resource ) || self::matches_legacy_form_resource( $resource ) ) {
				$ids[] = (int) $resource->id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	private static function prefix_archived_title( $title ) {
		$title = trim( (string) $title );
		if ( self::title_is_archived( $title ) ) {
			return $title;
		}

		return self::ARCHIVED_TITLE_PREFIX . $title;
	}
}

}
