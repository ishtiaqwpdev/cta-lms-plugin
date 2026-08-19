<?php
/**
 * CTA-CE-003 certificate wiring + approved course thumbnail sync.
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
	const THUMBNAIL_SEED_OPTION = 'cta_suicide_risk_thumbnail_1_0_267';

	const COMPLETION_STATEMENT = 'The participant completed all required instructional modules, passed the 25-question final examination with a score of at least 70%, submitted the course-specific evaluation, and completed the required attestation.';

	const BUNDLED_THUMBNAIL_FILENAME = 'CTA_Suicide_Risk_Course_Image.png';

	const APPROVED_IMAGE_ALT = 'Advanced Suicide Risk Assessment course image from Clinical Training and Supervision Academy';

	/**
	 * @return object|null
	 */
	public static function find_course() {
		return class_exists( 'CTA_Suicide_Risk_Module_Sync' )
			? CTA_Suicide_Risk_Module_Sync::find_course()
			: null;
	}

	/**
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function needs_repair( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return true;
		}

		$row = CTA_Database::get_course( $course_id );
		if ( ! $row ) {
			return true;
		}

		if ( empty( $row->has_ce_certificate ) ) {
			return true;
		}

		$meta = self::decode_syllabus_meta( $row );

		if ( self::COMPLETION_STATEMENT !== (string) ( $meta['certificate_completion_statement'] ?? '' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Self-heal certificate metadata for CTA-CE-003 (idempotent).
	 *
	 * @return array{ok:bool,course_id:int,message:string}
	 */
	public static function ensure() {
		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'message'   => 'suicide_risk_course_not_found',
			);
		}

		$course_id = (int) $course->id;
		if ( ! self::needs_repair( $course_id ) ) {
			return array(
				'ok'        => true,
				'course_id' => $course_id,
				'message'   => 'ok',
			);
		}

		return self::sync( true );
	}

	/**
	 * Ensure syllabus_meta certificate fields are present.
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

		update_option(
			self::SEED_OPTION,
			array(
				'at'        => current_time( 'mysql' ),
				'course_id' => $course_id,
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
	 * Attach the approved Suicide Risk course thumbnail (catalog / detail / dashboard).
	 *
	 * @param bool $force Re-run even if already applied at this seed key.
	 * @return array{ok:bool,course_id:int,thumbnail_url:string,message:string}
	 */
	public static function sync_thumbnail( $force = false ) {
		if ( ! $force && get_option( self::THUMBNAIL_SEED_OPTION ) ) {
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
				'message'       => 'suicide_risk_course_not_found',
			);
		}

		$course_id     = (int) $course->id;
		$thumbnail_url = self::resolve_approved_thumbnail_url();
		if ( '' === $thumbnail_url ) {
			return array(
				'ok'            => false,
				'course_id'     => $course_id,
				'thumbnail_url' => '',
				'message'       => 'thumbnail_asset_not_found',
			);
		}

		global $wpdb;

		self::ensure_approved_image_meta( $course_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'thumbnail_url' => $thumbnail_url ),
			array( 'id' => $course_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'ok'            => false,
				'course_id'     => $course_id,
				'thumbnail_url' => $thumbnail_url,
				'message'       => 'thumbnail_update_failed',
			);
		}

		update_option( self::THUMBNAIL_SEED_OPTION, 1, false );

		return array(
			'ok'            => true,
			'course_id'     => $course_id,
			'thumbnail_url' => $thumbnail_url,
			'message'       => 'synced',
		);
	}

	/**
	 * Resolve approved Suicide Risk artwork (Media Library, bundled PNG, or known uploads path).
	 *
	 * @return string
	 */
	public static function resolve_approved_thumbnail_url() {
		$filenames = array(
			'Suicide.png',
			self::BUNDLED_THUMBNAIL_FILENAME,
			'CTA_Suicide_Risk_Course_Image.jpg',
			'CTA_Suicide_Risk_Course_Image.jpeg',
		);

		foreach ( $filenames as $filename ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_wp_attached_file',
							'value'   => $filename,
							'compare' => 'LIKE',
						),
					),
				)
			);
			if ( ! empty( $query->posts[0] ) ) {
				$url = wp_get_attachment_url( (int) $query->posts[0] );
				wp_reset_postdata();
				if ( $url ) {
					return esc_url_raw( $url );
				}
			}
			wp_reset_postdata();
		}

		$by_slug = get_page_by_path( 'suicide', OBJECT, 'attachment' );
		if ( $by_slug && ! empty( $by_slug->ID ) ) {
			$url = wp_get_attachment_url( (int) $by_slug->ID );
			if ( $url ) {
				return esc_url_raw( $url );
			}
		}

		$bundled = CTA_PLUGIN_DIR . 'assets/course-images/' . self::BUNDLED_THUMBNAIL_FILENAME;
		if ( is_readable( $bundled ) ) {
			return esc_url_raw( CTA_PLUGIN_URL . 'assets/course-images/' . self::BUNDLED_THUMBNAIL_FILENAME );
		}

		return esc_url_raw( content_url( 'uploads/2026/07/Suicide.png' ) );
	}

	/**
	 * @param object|null $row Course row.
	 * @return array<string,mixed>
	 */
	private static function decode_syllabus_meta( $row ) {
		if ( ! $row || empty( $row->syllabus_meta ) ) {
			return array();
		}

		$decoded = json_decode( (string) $row->syllabus_meta, true );
		return is_array( $decoded ) ? $decoded : array();
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

		$meta = self::decode_syllabus_meta( $row );

		$meta['course_code']                      = self::COURSE_CODE;
		$meta['course_code_status']               = (string) ( $meta['course_code_status'] ?? 'provisional_pending_final_approval' );
		$meta['certificate_title']                = (string) ( $row->title ?? '' );
		$meta['certificate_completion_statement'] = self::COMPLETION_STATEMENT;
		$meta['instructional_method']             = (string) ( $meta['instructional_method'] ?? 'Asynchronous Distance Learning' );
		$meta['presenter']                        = (string) ( $meta['presenter'] ?? 'Candice Fuimaono, MS, LMFT' );
		$meta['provider']                         = (string) ( $meta['provider'] ?? 'Clinical Training and Supervision Academy' );
		$meta['publication_status']               = (string) ( $meta['publication_status'] ?? 'under_review_not_approved_for_publication' );
		$meta['development_draft']                = true;

		unset( $meta['thumbnail_is_placeholder'] );

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
	 * Clear placeholder flags and set final learner-facing alt text after artwork is applied.
	 *
	 * @param int $course_id Course ID.
	 */
	private static function ensure_approved_image_meta( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return;
		}

		$row = class_exists( 'CTA_Database' ) ? CTA_Database::get_course( $course_id ) : null;
		if ( ! $row ) {
			return;
		}

		$meta = self::decode_syllabus_meta( $row );
		unset( $meta['thumbnail_is_placeholder'] );
		$meta['image_alt'] = self::APPROVED_IMAGE_ALT;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'syllabus_meta' => wp_json_encode( $meta ) ),
			array( 'id' => $course_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}

}
