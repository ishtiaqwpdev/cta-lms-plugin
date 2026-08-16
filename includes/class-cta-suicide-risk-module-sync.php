<?php
/**
 * Advanced Suicide Risk Assessment (CTA-CE-003) instructional module + Vimeo sync.
 *
 * Six sequential video modules with manual completion + sequential unlock (CE pattern).
 * Does not publish the course or modify exam/evaluation/toolkit content.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Suicide_Risk_Module_Sync
 */
if ( ! class_exists( 'CTA_Suicide_Risk_Module_Sync' ) ) {

class CTA_Suicide_Risk_Module_Sync {

	const COURSE_CODE = 'CTA-CE-003';
	const SEED_OPTION = 'cta_suicide_risk_modules_1_0_210';

	/**
	 * Title aliases used to locate the Suicide Risk Assessment CE course.
	 *
	 * @return string[]
	 */
	public static function match_titles() {
		return array(
			'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
			'Advanced Suicide Risk Assessment',
		);
	}

	/**
	 * Official six-module sequence (order 1–6).
	 *
	 * @return array<int,array{title:string,video_url:string,duration_mins:int,summary_points:string[]}>
	 */
	public static function get_module_definitions() {
		return array(
			1 => array(
				'title'          => 'The Epidemiology and Phenomenology of Suicide',
				'video_url'      => 'https://vimeo.com/1216849426',
				'duration_mins'  => 60,
				'summary_points' => array(),
			),
			2 => array(
				'title'          => 'Standardized Assessment and Comprehensive Risk Formulation',
				'video_url'      => 'https://vimeo.com/1216893343',
				'duration_mins'  => 60,
				'summary_points' => array(),
			),
			3 => array(
				'title'          => 'Moving Beyond No-Harm Contracts to Collaborative Safety Planning',
				'video_url'      => 'https://vimeo.com/1216901724',
				'duration_mins'  => 60,
				'summary_points' => array(),
			),
			4 => array(
				'title'          => 'Involuntary Evaluation, Hospitalization, and Legal Thresholds',
				'video_url'      => 'https://vimeo.com/1216909079',
				'duration_mins'  => 60,
				'summary_points' => array(),
			),
			5 => array(
				'title'          => 'Clinical Documentation, Consultation, and Liability Management',
				'video_url'      => 'https://vimeo.com/1217091350',
				'duration_mins'  => 60,
				'summary_points' => array(),
			),
			6 => array(
				'title'          => 'Postvention, Clinician Wellness, and Professional Recovery',
				'video_url'      => 'https://vimeo.com/1217142210',
				'duration_mins'  => 60,
				'summary_points' => array(),
			),
		);
	}

	/**
	 * Expected Vimeo numeric IDs in module order (for validation).
	 *
	 * @return array<int,string>
	 */
	public static function get_expected_vimeo_ids() {
		$ids = array();
		foreach ( self::get_module_definitions() as $order => $def ) {
			$url = (string) ( $def['video_url'] ?? '' );
			if ( preg_match( '/(\d+)\s*$/', $url, $m ) ) {
				$ids[ (int) $order ] = $m[1];
			}
		}
		return $ids;
	}

	/**
	 * Find the Suicide Risk Assessment CE course by code, then title aliases.
	 *
	 * @return object|null
	 */
	public static function find_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

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
				$is_exam = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );
				if ( ! $is_exam ) {
					return $course;
				}
			}
		}

		return null;
	}

	/**
	 * Sync module titles, order, durations, and Vimeo URLs for CTA-CE-003.
	 *
	 * @param bool $force Re-run even if already applied at this seed key.
	 * @return array{ok:bool,course_id:int,updated:int,created:int,message:string,modules:array}
	 */
	public static function sync_modules( $force = false ) {
		global $wpdb;

		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'updated'   => 0,
				'created'   => 0,
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
				'created'   => 0,
				'message'   => 'suicide_risk_course_not_found',
				'modules'   => array(),
			);
		}

		$course_id = (int) $course->id;
		$defs      = self::get_module_definitions();
		$table     = $wpdb->prefix . 'cta_course_modules';

		$existing = class_exists( 'CTA_Database' )
			? CTA_Database::get_course_modules( $course_id, true )
			: array();

		$sorted = array_values( (array) $existing );
		usort(
			$sorted,
			static function ( $a, $b ) {
				$oa = (int) ( $a->order_index ?? 0 );
				$ob = (int) ( $b->order_index ?? 0 );
				if ( $oa === $ob ) {
					return (int) $a->id - (int) $b->id;
				}
				return $oa - $ob;
			}
		);

		$slot_row = array();
		foreach ( $sorted as $row ) {
			$title_l = strtolower( trim( (string) $row->title ) );
			$title_l = preg_replace( '/^\[archived\]\s*/', '', $title_l );
			$order   = 0;
			foreach ( $defs as $def_order => $def ) {
				if ( $title_l === strtolower( (string) $def['title'] ) ) {
					$order = (int) $def_order;
					break;
				}
			}
			if ( $order && ! isset( $slot_row[ $order ] ) ) {
				$slot_row[ $order ] = $row;
			}
		}

		$used_ids = array();
		foreach ( $slot_row as $row ) {
			$used_ids[ (int) $row->id ] = true;
		}

		$unused = array();
		foreach ( $sorted as $row ) {
			if ( ! isset( $used_ids[ (int) $row->id ] ) && (int) ( $row->order_index ?? 0 ) < 900 ) {
				$unused[] = $row;
			}
		}
		$unused_i = 0;
		foreach ( array_keys( $defs ) as $order ) {
			if ( isset( $slot_row[ $order ] ) ) {
				continue;
			}
			if ( isset( $unused[ $unused_i ] ) ) {
				$slot_row[ $order ] = $unused[ $unused_i ];
				$used_ids[ (int) $unused[ $unused_i ]->id ] = true;
				++$unused_i;
			}
		}

		$report  = array();
		$updated = 0;
		$created = 0;

		foreach ( $defs as $order => $def ) {
			$title = sanitize_text_field( (string) $def['title'] );
			$url   = esc_url_raw( (string) $def['video_url'] );
			$mins  = absint( $def['duration_mins'] ?? 60 );
			$desc  = '';

			if ( isset( $slot_row[ $order ] ) ) {
				$target = $slot_row[ $order ];
				$mid    = (int) $target->id;

				if ( '' === $desc && ! empty( $target->description ) ) {
					$desc = (string) $target->description;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ok = $wpdb->update(
					$table,
					array(
						'title'         => $title,
						'description'   => $desc,
						'duration_mins' => $mins,
						'order_index'   => (int) $order,
						'video_url'     => $url,
						'is_locked'     => 1,
					),
					array( 'id' => $mid ),
					array( '%s', '%s', '%d', '%d', '%s', '%d' ),
					array( '%d' )
				);

				if ( false !== $ok ) {
					++$updated;
				}

				$report[] = array(
					'id'        => $mid,
					'title'     => $title,
					'order'     => (int) $order,
					'video_url' => $url,
					'action'    => 'updated',
				);
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ok = $wpdb->insert(
				$table,
				array(
					'course_id'     => $course_id,
					'title'         => $title,
					'description'   => $desc,
					'duration_mins' => $mins,
					'order_index'   => (int) $order,
					'is_locked'     => 1,
					'video_url'     => $url,
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
			);

			if ( $ok ) {
				++$created;
				$report[] = array(
					'id'        => (int) $wpdb->insert_id,
					'title'     => $title,
					'order'     => (int) $order,
					'video_url' => $url,
					'action'    => 'created',
				);
			}
		}

		$park = 900;
		foreach ( $sorted as $row ) {
			$mid = (int) $row->id;
			if ( isset( $used_ids[ $mid ] ) ) {
				continue;
			}
			$raw_title = sanitize_text_field( (string) $row->title );
			$raw_title = preg_replace( '/^\[Archived\]\s*/i', '', $raw_title );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'order_index' => $park,
					'title'       => '[Archived] ' . $raw_title,
				),
				array( 'id' => $mid ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			++$park;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'modules_count' => count( $defs ) ),
			array( 'id' => $course_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( class_exists( 'CTA_Course_Catalog' ) ) {
			CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
		}

		update_option( self::SEED_OPTION, 1, false );

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'updated'   => $updated,
			'created'   => $created,
			'message'   => 'synced',
			'modules'   => $report,
		);
	}
}

}
