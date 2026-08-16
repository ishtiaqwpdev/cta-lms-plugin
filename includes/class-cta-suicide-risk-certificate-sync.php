<?php
/**
 * CTA-CE-003 certificate wiring + admin placeholder thumbnail sync.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Suicide_Risk_Certificate_Sync
 */
if ( ! class_exists( 'CTA_Suicide_Risk_Certificate_Sync' ) ) {

class CTA_Suicide_Risk_Certificate_Sync {

	const COURSE_CODE = 'CTA-CE-003';
	const SEED_OPTION = 'cta_suicide_risk_certificate_1_0_215';

	const COMPLETION_STATEMENT = 'The participant completed all required instructional modules, passed the 25-question final examination with a score of at least 70%, submitted the course-specific evaluation, and completed the required attestation.';

	const PLACEHOLDER_FILENAME = 'CTA_Suicide_Risk_Course_Image_ADMIN_PLACEHOLDER.svg';

	/**
	 * @return object|null
	 */
	public static function find_course() {
		return class_exists( 'CTA_Suicide_Risk_Module_Sync' )
			? CTA_Suicide_Risk_Module_Sync::find_course()
			: null;
	}

	/**
	 * Bundled admin-only placeholder image URL (clearly labeled, not client artwork).
	 *
	 * @return string
	 */
	public static function resolve_placeholder_thumbnail_url() {
		$path = CTA_PLUGIN_DIR . 'assets/course-images/' . self::PLACEHOLDER_FILENAME;
		if ( ! is_readable( $path ) ) {
			return '';
		}

		return CTA_PLUGIN_URL . 'assets/course-images/' . self::PLACEHOLDER_FILENAME;
	}

	/**
	 * Ensure syllabus_meta certificate fields + placeholder thumbnail are present.
	 *
	 * @param bool $force Re-run even if already seeded.
	 * @return array{ok:bool,course_id:int,message:string}
	 */
	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'message'   => 'already_seeded',
			);
		}

		if ( class_exists( 'CTA_Syllabus_Sync' ) ) {
			CTA_Syllabus_Sync::sync_all( false );
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'message'   => 'suicide_risk_course_not_found',
			);
		}

		$course_id = (int) $course->id;
		self::ensure_certificate_meta( $course_id );

		$thumb = self::sync_placeholder_thumbnail( $course_id );
		if ( ! $thumb['ok'] ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'message'   => $thumb['message'],
			);
		}

		if ( class_exists( 'CTA_Course_Catalog' ) ) {
			CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
		}

		update_option(
			self::SEED_OPTION,
			array(
				'at'            => current_time( 'mysql' ),
				'course_id'     => $course_id,
				'thumbnail_url' => $thumb['thumbnail_url'],
			),
			false
		);

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'message'   => 'synced',
		);
	}

	/**
	 * Merge certificate metadata into syllabus_meta without overwriting unrelated keys.
	 *
	 * @param int $course_id Course ID.
	 */
	private static function ensure_certificate_meta( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return;
		}

		$row = class_exists( 'CTA_Database' ) ? CTA_Database::get_course( $course_id ) : null;
		if ( ! $row ) {
			return;
		}

		$meta = array();
		if ( ! empty( $row->syllabus_meta ) ) {
			$decoded = json_decode( (string) $row->syllabus_meta, true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		$meta['course_code']                        = self::COURSE_CODE;
		$meta['course_code_status']                 = (string) ( $meta['course_code_status'] ?? 'provisional_pending_final_approval' );
		$meta['certificate_title']                  = (string) ( $row->title ?? '' );
		$meta['certificate_completion_statement']   = self::COMPLETION_STATEMENT;
		$meta['instructional_method']               = (string) ( $meta['instructional_method'] ?? 'Asynchronous Distance Learning' );
		$meta['presenter']                          = (string) ( $meta['presenter'] ?? 'Candice Fuimaono, MS, LMFT' );
		$meta['provider']                           = (string) ( $meta['provider'] ?? 'Clinical Training and Supervision Academy' );
		$meta['thumbnail_is_placeholder']           = true;
		$meta['publication_status']                 = (string) ( $meta['publication_status'] ?? 'under_review_not_approved_for_publication' );
		$meta['development_draft']                    = true;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array(
				'has_ce_certificate' => 1,
				'syllabus_meta'      => wp_json_encode( $meta ),
			),
			array( 'id' => $course_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $course_id Course ID.
	 * @return array{ok:bool,thumbnail_url:string,message:string}
	 */
	private static function sync_placeholder_thumbnail( $course_id ) {
		global $wpdb;

		$thumbnail_url = self::resolve_placeholder_thumbnail_url();
		if ( '' === $thumbnail_url ) {
			return array(
				'ok'            => false,
				'thumbnail_url' => '',
				'message'       => 'placeholder_asset_missing',
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'thumbnail_url' => $thumbnail_url ),
			array( 'id' => absint( $course_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'ok'            => false,
				'thumbnail_url' => $thumbnail_url,
				'message'       => 'thumbnail_update_failed',
			);
		}

		return array(
			'ok'            => true,
			'thumbnail_url' => $thumbnail_url,
			'message'       => 'synced',
		);
	}
}

}
