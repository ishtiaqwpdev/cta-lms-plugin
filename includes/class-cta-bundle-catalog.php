<?php
/**
 * Canonical Membership / Bundle / Pathway catalog (Master Pricing Catalog v3.5).
 *
 * Upserts names, prices, and CE course inclusions by title match. Never deletes
 * payment history — obsolete SKUs are deactivated.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Bundle_Catalog
 */
if ( ! class_exists( 'CTA_Bundle_Catalog' ) ) {

class CTA_Bundle_Catalog {

	/**
	 * Master Pricing Catalog v3.5 — renewal bundles, pathways, memberships.
	 *
	 * `course_numbers` map to CE catalog indices (1-based) in CTA_Course_Catalog::get_ce_catalog().
	 * Numbers above 8 are reserved for future CE courses and resolve only when present.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_catalog() {
		return array(
			array(
				'name'             => 'First Renewal Bundle',
				'slug'             => 'first-renewal-bundle',
				'legacy_slugs'     => array( 'first-renewal-starter' ),
				'description'      => 'First Renewal Bundle — Child Abuse Assessment & Mandated Reporting and HIV/AIDS and Mental Health (retail value $178).',
				'plan_type'        => 'bundle',
				'price'            => 139.00,
				'retail_value'     => 178.00,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 5, 6 ),
				'is_featured'      => 0,
				'sort_order'       => 10,
			),
			array(
				'name'             => 'Clinical Foundations Bundle',
				'slug'             => 'clinical-foundations-bundle',
				'legacy_slugs'     => array(),
				'description'      => 'Clinical Foundations Bundle — California Law & Ethics, Telehealth, and Advanced Suicide Risk Assessment (retail value $203).',
				'plan_type'        => 'bundle',
				'price'            => 179.00,
				'retail_value'     => 203.00,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 1, 2, 3 ),
				'is_featured'      => 0,
				'sort_order'       => 20,
			),
			array(
				'name'             => 'Behavioral Health Specialty Bundle',
				'slug'             => 'behavioral-health-specialty-bundle',
				'legacy_slugs'     => array(),
				'description'      => 'Behavioral Health Specialty Bundle — Alcoholism & Other Chemical Substance Dependency, HIV/AIDS and Mental Health, and Human Sexuality & Clinical Practice (retail value $337).',
				'plan_type'        => 'bundle',
				'price'            => 299.00,
				'retail_value'     => 337.00,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 4, 6, 7 ),
				'is_featured'      => 0,
				'sort_order'       => 30,
			),
			array(
				'name'             => 'California Renewal Essentials Bundle',
				'slug'             => 'california-renewal-essentials-bundle',
				'legacy_slugs'     => array(),
				'description'      => 'California Renewal Essentials Bundle — 38 CE hours total (Courses 1, 2, 3, 5, 6, 23, 24, 25). Available listed CE courses are included now; remaining courses attach when published.',
				'plan_type'        => 'bundle',
				'price'            => 279.00,
				'ce_hours_total'   => 38,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 1, 2, 3, 5, 6, 24, 25, 23 ),
				'is_featured'      => 0,
				'sort_order'       => 40,
			),
			array(
				'name'             => 'First Renewal Compliance Bundle',
				'slug'             => 'first-renewal-compliance-bundle',
				'legacy_slugs'     => array(),
				'description'      => 'First Renewal Compliance Bundle — 44 CE hours total (Courses 1–6).',
				'plan_type'        => 'bundle',
				'price'            => 349.00,
				'ce_hours_total'   => 44,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 1, 2, 3, 4, 5, 6 ),
				'is_featured'      => 0,
				'sort_order'       => 50,
			),
			array(
				'name'             => 'Risk Management & Clinical Protection Bundle',
				'slug'             => 'risk-management-clinical-protection-bundle',
				'legacy_slugs'     => array( 'crisis-risk-bundle' ),
				'description'      => 'Risk Management & Clinical Protection Bundle — 46 CE hours total (Courses 1, 2, 3, 5, 8, 24, 25, 27). Available listed CE courses are included now; remaining courses attach when published.',
				'plan_type'        => 'bundle',
				'price'            => 299.00,
				'ce_hours_total'   => 46,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 1, 2, 3, 5, 8, 24, 25, 27 ),
				'is_featured'      => 0,
				'sort_order'       => 60,
			),
			array(
				'name'             => 'Neurodivergent Child Therapist Pathway',
				'slug'             => 'neurodivergent-child-therapist-pathway',
				'legacy_slugs'     => array(),
				'description'      => 'Specialty Learning Pathway — 36 CE hours total (Courses 9, 10, 11, 12, 13, 14, 16, 17, 18, 2, 15). Available listed CE courses are included now; remaining courses attach when published.',
				'plan_type'        => 'bundle',
				'price'            => 399.00,
				'ce_hours_total'   => 36,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 9, 10, 11, 12, 13, 14, 16, 17, 18, 2, 15 ),
				'is_featured'      => 0,
				'sort_order'       => 70,
			),
			array(
				'name'             => 'Child & Adolescent Treatment Specialist Pathway',
				'slug'             => 'child-adolescent-treatment-specialist-pathway',
				'legacy_slugs'     => array(),
				'description'      => 'Specialty Learning Pathway — 37 CE hours total (Courses 14, 15, 16, 17, 18, 5, 2, 9, 10, 12). Available listed CE courses are included now; remaining courses attach when published.',
				'plan_type'        => 'bundle',
				'price'            => 399.00,
				'ce_hours_total'   => 37,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 14, 15, 16, 17, 18, 5, 2, 9, 10, 12 ),
				'is_featured'      => 0,
				'sort_order'       => 80,
			),
			array(
				'name'             => 'Clinical Supervision Leadership Pathway',
				'slug'             => 'clinical-supervision-leadership-pathway',
				'legacy_slugs'     => array(),
				'description'      => 'Specialty Learning Pathway — 45 CE hours total (Courses 8, 19, 20, 21, 22, 1, 3, 2). Available listed CE courses are included now; remaining courses attach when published.',
				'plan_type'        => 'bundle',
				'price'            => 449.00,
				'ce_hours_total'   => 45,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 8, 19, 20, 21, 22, 1, 3, 2 ),
				'is_featured'      => 0,
				'sort_order'       => 90,
			),
			array(
				'name'             => 'Addiction & Recovery Specialist Pathway',
				'slug'             => 'addiction-recovery-specialist-pathway',
				'legacy_slugs'     => array(),
				'description'      => 'Specialty Learning Pathway — 38 CE hours total (Courses 4, 3, 6, 7).',
				'plan_type'        => 'bundle',
				'price'            => 399.00,
				'ce_hours_total'   => 38,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 4, 3, 6, 7 ),
				'is_featured'      => 0,
				'sort_order'       => 100,
			),
			array(
				'name'             => 'Modern Clinical Practice Pathway',
				'slug'             => 'modern-clinical-practice-pathway',
				'legacy_slugs'     => array(),
				'description'      => 'Specialty Learning Pathway — 41 CE hours total (Courses 23, 24, 25, 26, 27, 6, 7, 3, 2). Available listed CE courses are included now; remaining courses attach when published.',
				'plan_type'        => 'bundle',
				'price'            => 399.00,
				'ce_hours_total'   => 41,
				'billing_cycle'    => 'one_time',
				'course_numbers'   => array( 23, 24, 25, 26, 27, 6, 7, 3, 2 ),
				'is_featured'      => 0,
				'sort_order'       => 110,
			),
			array(
				'name'             => 'Clinical Excellence Annual All-Access Pass',
				'slug'             => 'clinical-excellence-annual-all-access',
				'legacy_slugs'     => array( 'annual-all-access' ),
				'description'      => 'Unlimited access to all asynchronous CE courses for one year. New CE courses added during an active membership are included. Excludes live supervision services and Exam Preparation programs.',
				'plan_type'        => 'annual',
				'price'            => 299.00,
				'billing_cycle'    => 'yearly',
				'course_numbers'   => array(),
				'is_featured'      => 1,
				'sort_order'       => 120,
			),
			array(
				'name'             => 'Supervision + CE All-Access Program',
				'slug'             => 'supervision-ce-all-access',
				'legacy_slugs'     => array( 'supervision-ce-hybrid' ),
				'description'      => 'Monthly group supervision, one individual supervision session per month, and full CE course library access.',
				'plan_type'        => 'subscription',
				'price'            => 350.00,
				'billing_cycle'    => 'monthly',
				'course_numbers'   => array(),
				'is_featured'      => 0,
				'sort_order'       => 130,
			),
		);
	}

	/**
	 * Obsolete SKUs not in Catalog v3.5 — deactivate (do not delete).
	 *
	 * @return array<int,array{slug:string,name:string}>
	 */
	public static function get_obsolete_bundles() {
		return array(
			array(
				'slug' => 'clinical-focus-bundle',
				'name' => 'Clinical Focus CE Bundle',
			),
			// crisis-risk-bundle is remapped via legacy_slugs on Risk Management bundle.
		);
	}

	/**
	 * Sync DB bundles to Catalog v3.5. Idempotent.
	 *
	 * @return array Report.
	 */
	public static function sync_all() {
		global $wpdb;

		CTA_Database::ensure_tables();

		$table  = $wpdb->prefix . 'cta_bundles';
		$report = array(
			'updated'    => array(),
			'created'    => array(),
			'deactivated'=> array(),
			'missing_courses' => array(),
			'synced_at'  => gmdate( 'c' ),
		);

		foreach ( self::get_catalog() as $entry ) {
			$ids     = self::resolve_course_ids( $entry );
			$missing = isset( $ids['missing'] ) ? $ids['missing'] : array();
			$course_ids = isset( $ids['ids'] ) ? $ids['ids'] : array();

			if ( ! empty( $missing ) ) {
				$report['missing_courses'][] = array(
					'bundle'  => $entry['name'],
					'numbers' => $missing,
				);
			}

			// Prefer Supervision Plans canonical price for All-Access subscription.
			$price = (float) $entry['price'];
			$name  = (string) $entry['name'];
			$desc  = (string) $entry['description'];
			$slug  = sanitize_title( (string) $entry['slug'] );

			if ( 'supervision-ce-all-access' === $slug && class_exists( 'CTA_Supervision_Plans' ) ) {
				$price = (float) CTA_Supervision_Plans::get_price( CTA_Supervision_Plans::HYBRID_SLUG );
				$name  = CTA_Supervision_Plans::get_name( CTA_Supervision_Plans::HYBRID_SLUG );
				$plan  = CTA_Supervision_Plans::get_plan( CTA_Supervision_Plans::HYBRID_SLUG );
				if ( ! empty( $plan['description'] ) ) {
					$desc = (string) $plan['description'];
				}
			}

			$row = array(
				'name'             => $name,
				'slug'             => $slug,
				'description'      => $desc,
				'plan_type'        => sanitize_text_field( (string) $entry['plan_type'] ),
				'price'            => $price,
				'billing_cycle'    => sanitize_text_field( (string) $entry['billing_cycle'] ),
				'included_courses' => wp_json_encode( array_values( array_map( 'absint', $course_ids ) ) ),
				'is_featured'      => ! empty( $entry['is_featured'] ) ? 1 : 0,
				'status'           => 'active',
				'sort_order'       => absint( $entry['sort_order'] ),
			);

			$existing_id = self::find_bundle_id( $slug, isset( $entry['legacy_slugs'] ) ? (array) $entry['legacy_slugs'] : array(), $name );

			if ( $existing_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					$row,
					array( 'id' => $existing_id ),
					array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%d' ),
					array( '%d' )
				);
				$report['updated'][] = array(
					'id'    => $existing_id,
					'name'  => $name,
					'slug'  => $slug,
					'price' => $price,
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$ok = $wpdb->insert(
					$table,
					$row,
					array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%d' )
				);
				if ( $ok ) {
					$report['created'][] = array(
						'id'    => (int) $wpdb->insert_id,
						'name'  => $name,
						'slug'  => $slug,
						'price' => $price,
					);
				}
			}
		}

		foreach ( self::get_obsolete_bundles() as $obs ) {
			$slug = sanitize_title( (string) $obs['slug'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE slug = %s OR name = %s",
					$slug,
					(string) $obs['name']
				)
			);
			foreach ( (array) $ids as $id ) {
				$id = absint( $id );
				if ( ! $id ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array( 'status' => 'inactive' ),
					array( 'id' => $id ),
					array( '%s' ),
					array( '%d' )
				);
				$report['deactivated'][] = array(
					'id'   => $id,
					'name' => (string) $obs['name'],
					'slug' => $slug,
				);
			}
		}

		if ( class_exists( 'CTA_Supervision_Plans' ) ) {
			CTA_Supervision_Plans::sync_all_access_bundle();
		}

		self::clear_front_caches();

		update_option( 'cta_bundle_catalog_sync_v35', wp_json_encode( $report ), false );

		return $report;
	}

	/**
	 * Resolve CE course DB IDs for catalog course numbers (1-based CE catalog index).
	 *
	 * @param array $entry Bundle entry.
	 * @return array{ids:int[],missing:int[]}
	 */
	public static function resolve_course_ids( array $entry ) {
		$numbers = isset( $entry['course_numbers'] ) ? (array) $entry['course_numbers'] : array();
		$ce      = class_exists( 'CTA_Course_Catalog' ) ? CTA_Course_Catalog::get_ce_catalog() : array();
		$ids     = array();
		$missing = array();
		$seen    = array();

		foreach ( $numbers as $num ) {
			$num = absint( $num );
			if ( $num < 1 ) {
				continue;
			}

			$index = $num - 1;
			if ( ! isset( $ce[ $index ] ) ) {
				$missing[] = $num;
				continue;
			}

			$course = CTA_Course_Catalog::find_ce_course( $ce[ $index ] );
			if ( ! $course || empty( $course->id ) ) {
				$missing[] = $num;
				continue;
			}

			$id = (int) $course->id;
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$ids[]       = $id;
		}

		return array(
			'ids'     => $ids,
			'missing' => array_values( array_unique( $missing ) ),
		);
	}

	/**
	 * Find existing bundle ID by slug / legacy slug / name.
	 *
	 * @param string   $slug         Canonical slug.
	 * @param string[] $legacy_slugs Legacy slugs.
	 * @param string   $name         Display name.
	 * @return int
	 */
	private static function find_bundle_id( $slug, array $legacy_slugs, $name ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bundles';
		$slugs = array_values( array_unique( array_filter( array_merge( array( $slug ), $legacy_slugs ) ) ) );

		foreach ( $slugs as $try ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$id = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s ORDER BY id ASC LIMIT 1", sanitize_title( $try ) )
			);
			if ( $id ) {
				return $id;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s ORDER BY id ASC LIMIT 1", $name )
		);

		return $id;
	}

	/**
	 * Clear common page caches after bundle pricing updates.
	 */
	public static function clear_front_caches() {
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_all' ) ) {
			LiteSpeed_Cache_API::purge_all();
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		delete_transient( 'cta_membership_bundles_cache' );
	}
}
}
