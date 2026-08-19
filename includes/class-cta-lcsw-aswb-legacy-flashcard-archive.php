<?php
/**
 * Archive legacy LCSW ASWB Clinical interactive flashcard deck (132 cards).
 *
 * Replaced by the 180-card blueprint-aligned Flashcard Study Center deck.
 * Preserves files under _archived/ — does not delete card data.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lcsw_Aswb_Legacy_Flashcard_Archive' ) ) {

class CTA_Lcsw_Aswb_Legacy_Flashcard_Archive {

	const ARCHIVE_OPTION         = 'cta_lcsw_aswb_legacy_flashcards_archived_1_0_271';
	const ARCHIVED_TITLE_PREFIX  = '[Archived] ';
	const ARCHIVED_RESOURCE_SORT = 910;

	const ACTIVE_LEGACY_JSON_REL   = 'assets/course-materials/lcsw-aswb/study-tools/flashcards.json';
	const ARCHIVED_LEGACY_JSON_REL = 'assets/course-materials/lcsw-aswb/study-tools/_archived/lcsw-aswb-legacy-flashcards-v1.0-132.json';
	const LEGACY_CARD_COUNT        = 132;
	const EXPECTED_STUDY_CENTER    = 180;

	/**
	 * @return string[]
	 */
	public static function legacy_resource_path_markers() {
		return array(
			'study-tools/cta_lcsw_clinical_exam_preparation_flashcard_collection_v1.0.docx',
			'clinical exam preparation flashcard collection',
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

		if ( class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {
			$course = CTA_Lcsw_Aswb_Sync::find_course();
			if ( $course && ! empty( $course->id ) ) {
				return (int) $course->id;
			}
		}

		return 0;
	}

	/**
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function is_lcsw_aswb_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		$slug = sanitize_title( (string) ( $course->slug ?? '' ) );
		return in_array(
			$slug,
			array(
				'lcsw-aswb-clinical-exam-preparation',
				'lcsw-california-clinical-exam-preparation',
			),
			true
		);
	}

	/**
	 * @return bool
	 */
	public static function study_center_deck_is_live() {
		if ( ! class_exists( 'CTA_Exam_Prep_Flashcard_Center' ) ) {
			return false;
		}

		return CTA_Exam_Prep_Flashcard_Center::study_center_deck_is_live( 'lcsw-aswb' );
	}

	/**
	 * @param int $course_id Optional course ID.
	 * @return bool
	 */
	public static function is_legacy_flashcards_archived( $course_id = 0 ) {
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
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function blocks_learner_legacy_deck( $course ) {
		if ( ! self::is_lcsw_aswb_course( $course ) ) {
			return false;
		}

		return self::is_legacy_flashcards_archived( (int) ( $course->id ?? 0 ) );
	}

	/**
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function is_archived_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		if ( self::title_is_archived( (string) ( $resource->title ?? '' ) ) ) {
			return true;
		}

		return self::matches_legacy_flashcard_resource( $resource );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function title_is_archived( $title ) {
		return 0 === stripos( trim( (string) $title ), self::ARCHIVED_TITLE_PREFIX );
	}

	/**
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function matches_legacy_flashcard_resource( $resource ) {
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
			if ( false !== strpos( $haystack, strtolower( (string) $marker ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $path Path fragment.
	 * @return bool
	 */
	public static function is_archived_legacy_json_path( $path ) {
		$norm = strtolower( str_replace( '\\', '/', (string) $path ) );
		if ( '' === $norm ) {
			return false;
		}

		return false !== strpos( $norm, 'lcsw-aswb-legacy-flashcards-v1.0-132.json' )
			|| ( false !== strpos( $norm, 'lcsw-aswb/study-tools/_archived/' ) && false !== strpos( $norm, 'flashcards' ) );
	}

	/**
	 * @param string $path Path fragment.
	 * @return bool
	 */
	public static function is_blocked_active_legacy_json_path( $path ) {
		if ( ! self::is_legacy_flashcards_archived() ) {
			return false;
		}

		$norm = strtolower( str_replace( '\\', '/', (string) $path ) );
		if ( '' === $norm ) {
			return false;
		}

		return false !== strpos( $norm, 'lcsw-aswb/study-tools/flashcards.json' );
	}

	/**
	 * Archive legacy deck when the approved Study Center JSON is live.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if archived.
	 * @return array{ok:bool,course_id:int,json_moved:bool,resource_ids:int[],card_count:int,message:string}
	 */
	public static function archive_legacy_flashcards( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );

		if ( ! $course_id ) {
			return array(
				'ok'           => false,
				'course_id'    => 0,
				'json_moved'   => false,
				'resource_ids' => array(),
				'card_count'   => 0,
				'message'      => 'course_not_found',
			);
		}

		if ( ! self::study_center_deck_is_live() ) {
			return array(
				'ok'           => false,
				'course_id'    => $course_id,
				'json_moved'   => false,
				'resource_ids' => array(),
				'card_count'   => 0,
				'message'      => 'study_center_not_live',
			);
		}

		if ( ! $force && self::is_legacy_flashcards_archived( $course_id ) ) {
			$record = get_option( self::ARCHIVE_OPTION, array() );
			return array(
				'ok'           => true,
				'course_id'    => $course_id,
				'json_moved'   => ! empty( $record['json_moved'] ),
				'resource_ids' => is_array( $record['resource_ids'] ?? null ) ? (array) $record['resource_ids'] : array(),
				'card_count'   => (int) ( $record['card_count'] ?? 0 ),
				'message'      => 'already_archived',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		$json_result = self::archive_legacy_json_file();
		$res_result  = self::archive_legacy_resources( $course_id );

		update_option(
			self::ARCHIVE_OPTION,
			array(
				'archived'      => true,
				'at'            => current_time( 'mysql' ),
				'course_id'     => $course_id,
				'json_moved'    => ! empty( $json_result['moved'] ),
				'card_count'    => (int) ( $json_result['card_count'] ?? 0 ),
				'resource_ids'  => $res_result['resource_ids'],
				'archived_json' => self::ARCHIVED_LEGACY_JSON_REL,
			),
			false
		);

		return array(
			'ok'           => ! empty( $json_result['moved'] ) || ! empty( $res_result['resource_ids'] ) || is_readable( self::archived_json_absolute_path() ),
			'course_id'    => $course_id,
			'json_moved'   => ! empty( $json_result['moved'] ),
			'resource_ids' => $res_result['resource_ids'],
			'card_count'   => (int) ( $json_result['card_count'] ?? 0 ),
			'message'      => 'archived',
		);
	}

	/**
	 * @return string
	 */
	public static function archived_json_absolute_path() {
		return CTA_PLUGIN_DIR . ltrim( self::ARCHIVED_LEGACY_JSON_REL, '/' );
	}

	/**
	 * @return string
	 */
	public static function active_legacy_json_absolute_path() {
		return CTA_PLUGIN_DIR . ltrim( self::ACTIVE_LEGACY_JSON_REL, '/' );
	}

	/**
	 * @return array{moved:bool,card_count:int}
	 */
	private static function archive_legacy_json_file() {
		$active   = self::active_legacy_json_absolute_path();
		$archived = self::archived_json_absolute_path();
		$count    = 0;

		if ( is_readable( $archived ) ) {
			$count = self::count_cards_in_json_file( $archived );
			if ( is_readable( $active ) ) {
				@unlink( $active ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			return array(
				'moved'      => true,
				'card_count' => $count,
			);
		}

		if ( ! is_readable( $active ) ) {
			return array(
				'moved'      => false,
				'card_count' => 0,
			);
		}

		$count = self::count_cards_in_json_file( $active );
		$dir   = dirname( $archived );
		if ( ! wp_mkdir_p( $dir ) ) {
			return array(
				'moved'      => false,
				'card_count' => $count,
			);
		}

		if ( ! @rename( $active, $archived ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( ! copy( $active, $archived ) ) {
				return array(
					'moved'      => false,
					'card_count' => $count,
				);
			}
			@unlink( $active ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		return array(
			'moved'      => true,
			'card_count' => $count,
		);
	}

	/**
	 * @param string $path JSON path.
	 * @return int
	 */
	private static function count_cards_in_json_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return 0;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['cards'] ) || ! is_array( $data['cards'] ) ) {
			return 0;
		}

		return count( $data['cards'] );
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
			if ( ! self::matches_legacy_flashcard_resource( $resource ) ) {
				continue;
			}

			$resource_id = (int) $resource->id;
			$title       = self::prefix_archived_title( (string) ( $resource->title ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'       => $title,
					'order_index' => $order,
				),
				array( 'id' => $resource_id ),
				array( '%s', '%d' ),
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
