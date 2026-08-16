<?php
/**
 * Archive legacy LMFT California Clinical Form A/B assessments (PROMPT 00).
 *
 * Marks existing form_a / form_b quizzes and printable simulations as archived
 * so learners cannot reach them, while preserving all DB rows and attempt history.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Legacy_Forms_Archive
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {

class CTA_Lmft_Clinical_Legacy_Forms_Archive {

	const ARCHIVE_OPTION         = 'cta_lmft_clinical_legacy_forms_archived_1_0_220';
	const TARGET_COURSE_ID       = 10;
	const ARCHIVED_STATUS        = 'archived';
	const ARCHIVED_TITLE_PREFIX  = '[Archived] ';
	const ARCHIVED_SORT_FORM_A   = 920;
	const ARCHIVED_SORT_FORM_B   = 930;
	const ARCHIVED_RESOURCE_SORT = 900;

	/**
	 * Quiz types covered by this archive pass.
	 *
	 * @return string[]
	 */
	public static function legacy_quiz_types() {
		return array( 'form_a', 'form_b', 'legacy_form_a', 'legacy_form_b' );
	}

	/**
	 * Map active quiz types to archived legacy quiz_type values.
	 *
	 * @return array<string,string>
	 */
	public static function legacy_quiz_type_map() {
		return array(
			'form_a' => 'legacy_form_a',
			'form_b' => 'legacy_form_b',
		);
	}

	/**
	 * Relative simulation file markers for legacy Form A/B learner materials.
	 *
	 * @return string[]
	 */
	public static function legacy_resource_path_markers() {
		return array(
			'simulations/cta_lmft_comprehensive_simulation_form_a_150_question_exam_v1.0.docx',
			'simulations/cta_lmft_comprehensive_simulation_form_b_150_question_exam_v1.0.docx',
			'simulations/cta_lmft_comprehensive_simulation_form_a_answer_key_and_detailed_rationales_v1.0.docx',
			'simulations/cta_lmft_comprehensive_simulation_form_b_answer_key_and_detailed_rationales_v1.0.docx',
		);
	}

	/**
	 * Resolve the LMFT Clinical course ID (prefers course_id=10 when valid).
	 *
	 * @param int $course_id Optional explicit course ID.
	 * @return int
	 */
	public static function resolve_course_id( $course_id = 0 ) {
		$course_id = absint( $course_id );
		if ( $course_id > 0 ) {
			return $course_id;
		}

		if ( self::TARGET_COURSE_ID > 0 && class_exists( 'CTA_Database' ) ) {
			$row = CTA_Database::get_course( self::TARGET_COURSE_ID );
			if ( $row && self::is_lmft_clinical_course( $row ) ) {
				return (int) self::TARGET_COURSE_ID;
			}
		}

		if ( class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
			$course = CTA_Lmft_Clinical_Sync::find_course();
			if ( $course ) {
				return (int) $course->id;
			}
		}

		return 0;
	}

	/**
	 * @param object $course Course row.
	 * @return bool
	 */
	public static function is_lmft_clinical_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		$slug  = strtolower( (string) ( $course->slug ?? '' ) );
		$title = strtolower( (string) ( $course->title ?? '' ) );

		if ( 'lmft-california-clinical-exam-preparation' === $slug ) {
			return true;
		}

		return false !== strpos( $title, 'lmft california clinical' );
	}

	/**
	 * Whether legacy Form A/B have already been archived for this program.
	 *
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
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_archived_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		if ( self::ARCHIVED_STATUS === (string) ( $quiz->status ?? '' ) ) {
			return true;
		}

		return self::title_is_archived( (string) ( $quiz->title ?? '' ) );
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
	 * @param string $path Path or title haystack.
	 * @return bool
	 */
	public static function resource_path_is_legacy_form( $path ) {
		return self::matches_legacy_form_resource(
			(object) array(
				'file_path' => (string) $path,
				'file_url'  => '',
				'title'     => '',
			)
		);
	}

	/**
	 * Archive legacy Form A/B quizzes and related printable materials.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if archive option is already set.
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
			$ids = self::get_archived_quiz_ids( $course_id );
			return array(
				'ok'        => true,
				'course_id' => $course_id,
				'form_a'    => (int) ( $ids['form_a'] ?? 0 ),
				'form_b'    => (int) ( $ids['form_b'] ?? 0 ),
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
				'archived'      => true,
				'at'            => current_time( 'mysql' ),
				'course_id'     => $course_id,
				'form_a_quiz_id' => (int) ( $quiz_result['form_a'] ?? 0 ),
				'form_b_quiz_id' => (int) ( $quiz_result['form_b'] ?? 0 ),
				'resource_ids'  => $res_result['resource_ids'],
			),
			false
		);

		return array(
			'ok'        => ! empty( $quiz_result['form_a'] ) || ! empty( $quiz_result['form_b'] ) || ! empty( $res_result['resource_ids'] ),
			'course_id' => $course_id,
			'form_a'    => (int) ( $quiz_result['form_a'] ?? 0 ),
			'form_b'    => (int) ( $quiz_result['form_b'] ?? 0 ),
			'resources' => count( $res_result['resource_ids'] ),
			'message'   => 'archived',
		);
	}

	/**
	 * @param int $course_id Course ID.
	 * @return array{form_a:int,form_b:int}
	 */
	public static function get_archived_quiz_ids( $course_id = 0 ) {
		$course_id = self::resolve_course_id( $course_id );
		$out       = array(
			'form_a' => 0,
			'form_b' => 0,
		);

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return $out;
		}

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( 'legacy_form_a' === $type || 'form_a' === $type ) {
				$out['form_a'] = (int) $row->id;
			}
			if ( 'legacy_form_b' === $type || 'form_b' === $type ) {
				$out['form_b'] = (int) $row->id;
			}
		}

		return $out;
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
			if ( false !== strpos( $haystack, $marker ) ) {
				return true;
			}
		}

		if ( false === strpos( $haystack, 'comprehensive_simulation' ) && false === strpos( $haystack, 'simulation_form_' ) ) {
			return false;
		}

		return ( false !== strpos( $haystack, 'form_a' ) || false !== strpos( $haystack, 'form a' ) )
			|| ( false !== strpos( $haystack, 'form_b' ) || false !== strpos( $haystack, 'form b' ) );
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

		$table = $wpdb->prefix . 'cta_quizzes';
		$sorts = array(
			'form_a'        => self::ARCHIVED_SORT_FORM_A,
			'form_b'        => self::ARCHIVED_SORT_FORM_B,
			'legacy_form_a' => self::ARCHIVED_SORT_FORM_A,
			'legacy_form_b' => self::ARCHIVED_SORT_FORM_B,
		);
		$type_map = self::legacy_quiz_type_map();

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( ! in_array( $type, array( 'form_a', 'form_b', 'legacy_form_a', 'legacy_form_b' ), true ) ) {
				continue;
			}

			$source_type = in_array( $type, array( 'legacy_form_a', 'legacy_form_b' ), true )
				? str_replace( 'legacy_', '', $type )
				: $type;
			$legacy_type = $type_map[ $source_type ] ?? $type;

			$quiz_id = (int) $row->id;
			$title   = self::prefix_archived_title( (string) ( $row->title ?? '' ) );

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

			$out[ $source_type ] = $quiz_id;
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
