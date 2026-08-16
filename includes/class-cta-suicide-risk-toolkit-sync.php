<?php
/**
 * Advanced Suicide Risk Assessment (CTA-CE-003) learner resource toolkit sync.
 *
 * Registers the enrollment-gated HTML toolkit in protected storage.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Suicide_Risk_Toolkit_Sync
 */
if ( ! class_exists( 'CTA_Suicide_Risk_Toolkit_Sync' ) ) {

class CTA_Suicide_Risk_Toolkit_Sync {

	const COURSE_CODE = 'CTA-CE-003';
	const SEED_OPTION = 'cta_suicide_risk_toolkit_1_0_211';
	const RESOURCE_KEY = 'suicide_risk_learner_resource_toolkit_v1_1';
	const TOOLKIT_TITLE = 'Learner Resource Toolkit — Clinician-Facing Resource Toolkit';

	/**
	 * @return string[]
	 */
	public static function match_titles() {
		return array(
			'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
			'Advanced Suicide Risk Assessment',
		);
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
	 * Attach or refresh the protected learner toolkit download.
	 *
	 * @param bool $force Re-run even if seeded.
	 * @return array{ok:bool,course_id:int,resource_id:int,message:string}
	 */
	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'          => true,
				'course_id'   => 0,
				'resource_id' => 0,
				'message'     => 'already_seeded',
			);
		}

		if ( ! class_exists( 'CTA_Course_Materials' ) ) {
			return array(
				'ok'          => false,
				'course_id'   => 0,
				'resource_id' => 0,
				'message'     => 'materials_class_missing',
			);
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'          => false,
				'course_id'   => 0,
				'resource_id' => 0,
				'message'     => 'suicide_risk_course_not_found',
			);
		}

		$source = CTA_PLUGIN_DIR . 'assets/course-materials/suicide-risk-ce/CTA_Suicide_Risk_Learner_Resource_Toolkit_v1_1.html';
		if ( ! is_readable( $source ) ) {
			return array(
				'ok'          => false,
				'course_id'   => (int) $course->id,
				'resource_id' => 0,
				'message'     => 'toolkit_source_missing',
			);
		}

		$course_id   = (int) $course->id;
		$resource_id = self::find_toolkit_resource_id( $course_id );

		if ( ! $resource_id ) {
			CTA_Course_Materials::ensure_bundled_resources();
			$resource_id = self::find_toolkit_resource_id( $course_id );
		}

		if ( $resource_id ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'cta_downloadable_resources',
				array(
					'title'     => self::TOOLKIT_TITLE,
					'module_id' => 0,
				),
				array( 'id' => $resource_id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
		}

		update_option(
			self::SEED_OPTION,
			array(
				'at'          => current_time( 'mysql' ),
				'course_id'   => $course_id,
				'resource_id' => (int) $resource_id,
			),
			false
		);

		return array(
			'ok'          => (bool) $resource_id,
			'course_id'   => $course_id,
			'resource_id' => (int) $resource_id,
			'message'     => $resource_id ? 'synced' : 'resource_not_registered',
		);
	}

	/**
	 * @param int $course_id Course ID.
	 * @return int
	 */
	private static function find_toolkit_resource_id( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		$table = $wpdb->prefix . 'cta_downloadable_resources';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title, file_path FROM {$table} WHERE course_id = %d ORDER BY id ASC",
				$course_id
			)
		);

		foreach ( (array) $rows as $row ) {
			$path  = strtolower( (string) ( $row->file_path ?? '' ) );
			$title = (string) ( $row->title ?? '' );
			if ( false !== strpos( $path, 'suicide_risk_learner_resource_toolkit' )
				|| false !== strpos( $path, 'cta_suicide_risk_learner_resource_toolkit' )
				|| self::TOOLKIT_TITLE === $title ) {
				return (int) $row->id;
			}
		}

		return 0;
	}
}

}
