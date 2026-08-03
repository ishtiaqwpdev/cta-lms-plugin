<?php
/**
 * California Law & Ethics (CTA-CE-001) instructional module + Vimeo sync.
 *
 * Remaps existing modules by order_index (preserves IDs/progress), sets official
 * titles and Vimeo URLs, and ensures the Course Integration Capstone sits after
 * Module 6 and before the Final Examination.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Law_Ethics_Module_Sync
 */
if ( ! class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {

class CTA_Law_Ethics_Module_Sync {

	const COURSE_CODE = 'CTA-CE-001';
	const SEED_OPTION = 'cta_law_ethics_modules_1_0_121';

	/**
	 * Title aliases used to locate the Law & Ethics CE course.
	 *
	 * @return string[]
	 */
	public static function match_titles() {
		return array(
			'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
			'California Law & Ethics for Mental Health Professionals',
		);
	}

	/**
	 * Official module definitions (order 1–7). Capstone is required module 7.
	 *
	 * @return array<int,array{title:string,video_url:string,duration_mins:int,summary_points:string[]}>
	 */
	public static function get_module_definitions() {
		return array(
			1 => array(
				'title'          => 'Regulatory Frameworks',
				'video_url'      => 'https://vimeo.com/1214611219',
				'duration_mins'  => 50,
				'summary_points' => array(
					'California regulatory updates and Board of Behavioral Sciences requirements',
					'Scope of competence and ethical practice standards',
					'Common disciplinary concerns and risk factors',
				),
			),
			2 => array(
				'title'          => 'Informed Consent & Digital Boundaries',
				'video_url'      => 'https://vimeo.com/1214902641',
				'duration_mins'  => 50,
				'summary_points' => array(
					'Legal and ethical requirements for informed consent',
					'Digital practice boundaries and electronic communication',
					'Fee, privacy, telehealth, and professional-boundary disclosures',
				),
			),
			3 => array(
				'title'          => 'Advanced Confidentiality & Privilege',
				'video_url'      => 'https://vimeo.com/1214856706',
				'duration_mins'  => 50,
				'summary_points' => array(
					'Confidentiality and psychotherapist-patient privilege',
					'Mandatory and permissive disclosure exceptions',
					'Mandated reporting standards and thresholds',
				),
			),
			4 => array(
				'title'          => 'Working with Minors',
				'video_url'      => 'https://vimeo.com/1214861621',
				'duration_mins'  => 50,
				'summary_points' => array(
					'Minor-consent treatment regulations',
					'Parental rights and involvement considerations',
					'Confidentiality considerations when treating minors',
				),
			),
			5 => array(
				'title'          => 'Crisis Management & Tarasoff',
				'video_url'      => 'https://vimeo.com/1214876352',
				'duration_mins'  => 50,
				'summary_points' => array(
					'Tarasoff duty-to-protect requirements',
					'Crisis management and emergency-response procedures',
					'Documentation of risk assessment and protective actions',
				),
			),
			6 => array(
				'title'          => 'Documentation, Professional Practice & Licensure Protection',
				'video_url'      => 'https://vimeo.com/1215024927',
				'duration_mins'  => 50,
				'summary_points' => array(
					'Defensible clinical documentation standards',
					'Risk-management and licensure protection',
					'Professional wills and practice closure considerations',
				),
			),
			7 => array(
				'title'          => 'Course Integration Capstone',
				'video_url'      => 'https://vimeo.com/1214922839',
				'duration_mins'  => 20,
				'summary_points' => array(
					'Integrate legal and ethical decision-making across Modules 1–6',
					'Apply California Law & Ethics standards to complex clinical scenarios',
					'Prepare for the final examination with structured clinical reasoning',
				),
			),
		);
	}

	/**
	 * Find the Law & Ethics course by code, then title aliases.
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
	 * Legacy module titles → official order (pre–CTA-CE-001 video rollout).
	 *
	 * @return array<string,int> lowercase title => order
	 */
	private static function legacy_title_order_map() {
		return array(
			'california regulatory frameworks & bbs updates'          => 1,
			'scope of competence, ethical practice & informed consent' => 2,
			'confidentiality, privilege & mandated reporting'          => 3,
			'minor consent, tarasoff duties & crisis management'       => 4,
			'documentation & record-keeping standards'                 => 5,
			'risk management, professional wills & practice closure'   => 6,
			'required course integration capstone'                     => 7,
		);
	}

	/**
	 * Sync module titles, order, durations, and Vimeo URLs for CTA-CE-001.
	 *
	 * Updates existing modules by title / legacy alias / position (preserves IDs).
	 * Creates missing Capstone (or any missing slot). Never deletes modules or enrollments.
	 * Does not publish the course.
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
				'message'   => 'law_ethics_course_not_found',
				'modules'   => array(),
			);
		}

		$course_id = (int) $course->id;
		$defs      = self::get_module_definitions();
		$table     = $wpdb->prefix . 'cta_course_modules';
		$legacy    = self::legacy_title_order_map();

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

		// Pre-assign rows to slots via official title or legacy alias (lowest id wins).
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
			if ( ! $order && isset( $legacy[ $title_l ] ) ) {
				$order = (int) $legacy[ $title_l ];
			}
			if ( $order && ! isset( $slot_row[ $order ] ) ) {
				$slot_row[ $order ] = $row;
			}
		}

		$used_ids = array();
		foreach ( $slot_row as $row ) {
			$used_ids[ (int) $row->id ] = true;
		}

		// Fill remaining slots from unused rows in sorted order (positional fallback).
		$unused = array();
		foreach ( $sorted as $row ) {
			if ( ! isset( $used_ids[ (int) $row->id ] ) ) {
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
			$mins  = absint( $def['duration_mins'] ?? 50 );
			$desc  = class_exists( 'CTA_Syllabus_Sync' )
				? CTA_Syllabus_Sync::format_module_description( (array) ( $def['summary_points'] ?? array() ) )
				: self::format_description( (array) ( $def['summary_points'] ?? array() ) );

			if ( isset( $slot_row[ $order ] ) ) {
				$target = $slot_row[ $order ];
				$mid    = (int) $target->id;

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

		// Park any leftover duplicate rows after Capstone so they do not block exam unlock.
		// Never deleted (preserves progress history); pushed to order_index 900+.
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

		// Active instructional sequence = official 7 modules only.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'modules_count' => count( $defs ) ),
			array( 'id' => $course_id ),
			array( '%d' ),
			array( '%d' )
		);

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

	/**
	 * Attach the approved CTA-CE-001 course image to thumbnail_url.
	 *
	 * Prefer Media Library match; fall back to the bundled plugin asset so
	 * catalog, course detail, and dashboard cards share one proportional image.
	 *
	 * @param bool $force Re-run even if already applied at this seed key.
	 * @return array{ok:bool,course_id:int,thumbnail_url:string,message:string}
	 */
	public static function sync_thumbnail( $force = false ) {
		$seed_option = 'cta_law_ethics_thumbnail_1_0_124';

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
				'message'       => 'law_ethics_course_not_found',
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
	 * Resolve approved Law & Ethics course image URL (Media Library or bundled asset).
	 *
	 * @return string
	 */
	public static function resolve_approved_thumbnail_url() {
		$filenames = array(
			'CTA_California_Law_Ethics_Course_Image.jpg',
			'CTA_California_Law_Ethics_Course_Image.jpeg',
			'CTA_California_Law_Ethics_Course_Image.png',
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

		$bundled = CTA_PLUGIN_DIR . 'assets/course-images/CTA_California_Law_Ethics_Course_Image.jpg';
		if ( file_exists( $bundled ) ) {
			return esc_url_raw( CTA_PLUGIN_URL . 'assets/course-images/CTA_California_Law_Ethics_Course_Image.jpg' );
		}

		return '';
	}

	/**
	 * Format module description HTML from summary points.
	 *
	 * @param string[] $points Summary points.
	 * @return string
	 */
	private static function format_description( array $points ) {
		$items = array();
		foreach ( $points as $point ) {
			$point = sanitize_text_field( (string) $point );
			if ( '' !== $point ) {
				$items[] = '<li>' . esc_html( $point ) . '</li>';
			}
		}
		if ( empty( $items ) ) {
			return '';
		}
		return '<ul>' . implode( '', $items ) . '</ul>';
	}
}

}
