<?php
/**
 * Archive legacy LMFT AMFTRB interactive flashcard deck (120 cards).
 *
 * Replaced by the approved 180-card Current-Exam Flashcard Study Center deck.
 * The legacy deck used workbook import tags as unique domains (120 domain chips).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lmft_Amftrb_Legacy_Flashcard_Archive' ) ) {

class CTA_Lmft_Amftrb_Legacy_Flashcard_Archive {

	const ARCHIVE_OPTION         = 'cta_lmft_amftrb_legacy_flashcards_archived_1_0_274';
	const ARCHIVED_TITLE_PREFIX  = '[Archived] ';
	const ARCHIVED_RESOURCE_SORT = 910;

	const ACTIVE_LEGACY_JSON_REL   = 'assets/course-materials/lmft-amftrb/study-tools/flashcards.json';
	const ARCHIVED_LEGACY_JSON_REL = 'assets/course-materials/lmft-amftrb/study-tools/_archived/lmft-amftrb-legacy-flashcards-v1.0-120.json';
	const LEGACY_CARD_COUNT        = 120;
	const EXPECTED_STUDY_CENTER    = 180;

	/**
	 * @return string[]
	 */
	public static function legacy_resource_path_markers() {
		return array(
			'study-tools/cta_lmft_amftrb_wb1-12_120_card_flashcard_study_collection_v1.0.docx',
			'120_card_flashcard_study_collection',
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

		global $wpdb;
		$table = $wpdb->prefix . 'cta_courses';
		$slug  = 'lmft-amftrb-national-exam-preparation';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
				$slug
			)
		);

		return $row && ! empty( $row->id ) ? (int) $row->id : 0;
	}

	/**
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function is_amftrb_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		$slug = sanitize_title( (string) ( $course->slug ?? '' ) );
		return 'lmft-amftrb-national-exam-preparation' === $slug;
	}

	/**
	 * @return bool
	 */
	public static function study_center_deck_is_live() {
		if ( ! class_exists( 'CTA_Exam_Prep_Flashcard_Center' ) ) {
			return false;
		}

		return CTA_Exam_Prep_Flashcard_Center::study_center_deck_is_live( 'lmft-amftrb' );
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
		if ( ! self::is_amftrb_course( $course ) ) {
			return false;
		}

		if ( self::is_legacy_flashcards_archived( (int) ( $course->id ?? 0 ) ) ) {
			return true;
		}

		// Repo/deploy cutover: active JSON removed and preserved under _archived/.
		if ( ! is_readable( self::active_legacy_json_absolute_path() )
			&& is_readable( self::archived_json_absolute_path() ) ) {
			return true;
		}

		return false;
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
	 * Archive legacy deck. Runs when Study Center is live OR legacy must be blocked.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if archived.
	 * @return array{ok:bool,course_id:int,json_moved:bool,resource_ids:int[],card_count:int,message:string}
	 */
	public static function archive_legacy_flashcards( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );

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
		$res_result  = $course_id ? self::archive_legacy_resources( $course_id ) : array( 'resource_ids' => array() );

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
			'ok'           => ! empty( $json_result['moved'] ) || is_readable( self::archived_json_absolute_path() ),
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
			$title       = trim( (string) ( $resource->title ?? '' ) );
			if ( 0 !== stripos( $title, self::ARCHIVED_TITLE_PREFIX ) ) {
				$title = self::ARCHIVED_TITLE_PREFIX . $title;
			}

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
}

}
