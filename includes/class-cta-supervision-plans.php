<?php
/**
 * Canonical supervision subscription plan names and prices.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Supervision_Plans
 */
if ( ! class_exists( 'CTA_Supervision_Plans' ) ) {

class CTA_Supervision_Plans {

	const GROUP_SLUG  = 'group';
	const HYBRID_SLUG = 'hybrid';

	const GROUP_PRICE  = 260.0;
	const HYBRID_PRICE = 350.0;

	const ALL_ACCESS_BUNDLE_SLUG = 'supervision-ce-all-access';
	const LEGACY_HYBRID_BUNDLE_SLUG = 'supervision-ce-hybrid';

	/**
	 * Plan catalog keyed by internal slug.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_plans() {
		return array(
			self::GROUP_SLUG  => array(
				'slug'        => self::GROUP_SLUG,
				'name'        => __( 'Group Supervision', 'cta-lms' ),
				'price'       => self::get_group_price(),
				'description' => __( 'Monthly group supervision subscription', 'cta-lms' ),
				'billing'     => 'monthly',
			),
			self::HYBRID_SLUG => array(
				'slug'        => self::HYBRID_SLUG,
				'name'        => __( 'Supervision + CE All-Access Program', 'cta-lms' ),
				'price'       => self::get_all_access_price(),
				'description' => __( 'Group supervision sessions plus full CE course library access.', 'cta-lms' ),
				'billing'     => 'monthly',
				'bundle_slug' => self::ALL_ACCESS_BUNDLE_SLUG,
			),
		);
	}

	/**
	 * Normalize a plan slug (accepts legacy labels).
	 *
	 * @param string $plan Raw plan slug or name.
	 * @return string group|hybrid
	 */
	public static function normalize_slug( $plan ) {
		$plan = strtolower( trim( (string) $plan ) );

		if ( in_array( $plan, array( self::HYBRID_SLUG, 'all_access', 'all-access', 'supervision-ce-all-access', 'supervision-ce-hybrid' ), true ) ) {
			return self::HYBRID_SLUG;
		}

		if ( false !== strpos( $plan, 'hybrid' ) || false !== strpos( $plan, 'all-access' ) || false !== strpos( $plan, 'all access' ) ) {
			return self::HYBRID_SLUG;
		}

		return self::GROUP_SLUG;
	}

	/**
	 * Get one plan definition.
	 *
	 * @param string $plan Plan slug.
	 * @return array<string,mixed>
	 */
	public static function get_plan( $plan ) {
		$slug  = self::normalize_slug( $plan );
		$plans = self::get_plans();

		return $plans[ $slug ];
	}

	/**
	 * Display name for a plan.
	 *
	 * @param string $plan Plan slug.
	 * @return string
	 */
	public static function get_name( $plan ) {
		$definition = self::get_plan( $plan );
		return (string) $definition['name'];
	}

	/**
	 * Monthly price for a plan.
	 *
	 * @param string $plan Plan slug.
	 * @return float
	 */
	public static function get_price( $plan ) {
		$definition = self::get_plan( $plan );
		return (float) $definition['price'];
	}

	/**
	 * Formatted monthly price label, e.g. "$350/month".
	 *
	 * @param string $plan Plan slug.
	 * @return string
	 */
	public static function get_price_label( $plan ) {
		$price = self::get_price( $plan );

		return '$' . number_format( $price, 0 ) . __( '/month', 'cta-lms' );
	}

	/**
	 * Group Supervision monthly price (option-overridable).
	 *
	 * @return float
	 */
	public static function get_group_price() {
		$price = (float) get_option( 'cta_supervision_monthly_price', self::GROUP_PRICE );
		return $price > 0 ? $price : self::GROUP_PRICE;
	}

	/**
	 * Supervision + CE All-Access monthly price (option-overridable).
	 *
	 * @return float
	 */
	public static function get_all_access_price() {
		$price = (float) get_option( 'cta_supervision_all_access_price', self::HYBRID_PRICE );
		return $price > 0 ? $price : self::HYBRID_PRICE;
	}

	/**
	 * Canonical All-Access bundle definition used for seed/sync.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_all_access_bundle_seed() {
		$plan = self::get_plan( self::HYBRID_SLUG );

		return array(
			'name'             => $plan['name'],
			'slug'             => self::ALL_ACCESS_BUNDLE_SLUG,
			'description'      => $plan['description'],
			'plan_type'        => 'subscription',
			'price'            => (float) $plan['price'],
			'billing_cycle'    => 'monthly',
			'included_courses' => wp_json_encode( array() ),
			'is_featured'      => 0,
			'sort_order'       => 5,
		);
	}

	/**
	 * Resolve the associate's supervision plan slug from all available signals.
	 *
	 * Prevents mismatches where legacy meta still says "Hybrid" while the slug
	 * (or price) still points at Group Supervision ($260).
	 *
	 * @param int $user_id User ID.
	 * @return string group|hybrid
	 */
	public static function resolve_user_plan_slug( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return self::GROUP_SLUG;
		}

		// Explicit All-Access entitlement (bundle purchase).
		if ( get_user_meta( $user_id, 'cta_hybrid_plan_active', true ) ) {
			return self::HYBRID_SLUG;
		}

		// Prefer completed payment record — amount/slug are authoritative over stale names.
		if ( class_exists( 'CTA_Database' ) ) {
			$payment = CTA_Database::get_user_supervision_payment( $user_id, 'completed' );
			if ( $payment ) {
				$details = array();
				if ( ! empty( $payment->plan_details ) ) {
					$decoded = json_decode( (string) $payment->plan_details, true );
					if ( is_array( $decoded ) ) {
						$details = $decoded;
					}
				}

				$amount = isset( $payment->amount ) ? (float) $payment->amount : 0.0;
				if ( $amount > 0 ) {
					$all_access = self::get_all_access_price();
					$group      = self::get_group_price();
					if ( abs( $amount - $all_access ) < 0.01 ) {
						return self::HYBRID_SLUG;
					}
					if ( abs( $amount - $group ) < 0.01 ) {
						return self::GROUP_SLUG;
					}
				}

				if ( ! empty( $details['plan_slug'] ) ) {
					return self::normalize_slug( (string) $details['plan_slug'] );
				}

				if ( $amount > 0 ) {
					$all_access = self::get_all_access_price();
					$group      = self::get_group_price();
					if ( $amount >= ( ( $group + $all_access ) / 2 ) ) {
						return self::HYBRID_SLUG;
					}
					return self::GROUP_SLUG;
				}

				if ( ! empty( $payment->plan_name ) && self::name_indicates_all_access( (string) $payment->plan_name ) ) {
					return self::HYBRID_SLUG;
				}
			}
		}

		$name = (string) get_user_meta( $user_id, 'cta_supervision_plan_name', true );
		if ( self::name_indicates_all_access( $name ) ) {
			return self::HYBRID_SLUG;
		}

		$slug = (string) get_user_meta( $user_id, 'cta_supervision_plan', true );
		if ( '' !== $slug ) {
			return self::normalize_slug( $slug );
		}

		return self::GROUP_SLUG;
	}

	/**
	 * Canonical display name for a user's supervision plan.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function resolve_user_plan_name( $user_id ) {
		return self::get_name( self::resolve_user_plan_slug( $user_id ) );
	}

	/**
	 * Canonical price label for a user's supervision plan.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function resolve_user_price_label( $user_id ) {
		return self::get_price_label( self::resolve_user_plan_slug( $user_id ) );
	}

	/**
	 * Whether a stored plan name refers to the All-Access / legacy Hybrid plan.
	 *
	 * @param string $name Plan name.
	 * @return bool
	 */
	public static function name_indicates_all_access( $name ) {
		$name = (string) $name;

		if ( '' === $name ) {
			return false;
		}

		return (bool) (
			false !== stripos( $name, 'Hybrid' )
			|| false !== stripos( $name, 'All-Access Program' )
			|| false !== stripos( $name, 'All Access Program' )
			|| false !== stripos( $name, 'Supervision + CE' )
		);
	}

	/**
	 * Canonicalize a stored display name to the approved plan title.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function canonicalize_name( $name ) {
		if ( self::name_indicates_all_access( $name ) ) {
			return self::get_name( self::HYBRID_SLUG );
		}

		$name = trim( (string) $name );
		if ( '' === $name || false !== stripos( $name, 'Group' ) ) {
			return self::get_name( self::GROUP_SLUG );
		}

		return $name;
	}

	/**
	 * Sync All-Access bundle row name/price/slug in the database.
	 */
	public static function sync_all_access_bundle() {
		global $wpdb;

		$table = self::table_name_bundles();
		$seed  = self::get_all_access_bundle_seed();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug IN (%s, %s) OR name LIKE %s OR name LIKE %s OR name LIKE %s ORDER BY id ASC LIMIT 1",
				self::ALL_ACCESS_BUNDLE_SLUG,
				self::LEGACY_HYBRID_BUNDLE_SLUG,
				'%Hybrid%',
				'%All-Access Program%',
				'%Supervision + CE%'
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				$seed,
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);
			return;
		}

		// Catalog already has other bundles but is missing All-Access — insert it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			$wpdb->insert(
				$table,
				$seed,
				array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Bundles table name.
	 *
	 * @return string
	 */
	private static function table_name_bundles() {
		global $wpdb;
		return $wpdb->prefix . 'cta_bundles';
	}

	/**
	 * Normalize stored payment / user meta plan names after rename.
	 * Also repairs mismatched plan slug vs name/amount pairs.
	 */
	public static function migrate_legacy_names() {
		global $wpdb;

		$canonical_all_access = self::get_name( self::HYBRID_SLUG );
		$canonical_group      = self::get_name( self::GROUP_SLUG );
		$group_price          = self::GROUP_PRICE;
		$all_access_price     = self::HYBRID_PRICE;

		// Rename legacy Hybrid labels on payment rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}cta_payments
				SET plan_name = %s
				WHERE plan_name LIKE %s
				   OR plan_name LIKE %s
				   OR plan_name LIKE %s",
				$canonical_all_access,
				'%Hybrid%',
				'%All-Access Program%',
				'%Supervision + CE%'
			)
		);

		// Re-align payment plan_name with amount when name/price disagree.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}cta_payments
				SET plan_name = %s
				WHERE ABS(amount - %f) < 0.01
				  AND (
					product_type = 'supervision'
					OR plan_name LIKE %s
					OR plan_name LIKE %s
				  )",
				$canonical_group,
				$group_price,
				'%Supervision%',
				'%Hybrid%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}cta_payments
				SET plan_name = %s
				WHERE ABS(amount - %f) < 0.01
				  AND (
					product_type IN ('supervision', 'bundle')
					OR plan_name LIKE %s
					OR plan_name LIKE %s
				  )",
				$canonical_all_access,
				$all_access_price,
				'%Supervision%',
				'%Hybrid%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->usermeta}
				SET meta_value = %s
				WHERE meta_key = 'cta_supervision_plan_name'
				AND (
					meta_value LIKE %s
					OR meta_value LIKE %s
					OR meta_value LIKE %s
				)",
				$canonical_all_access,
				'%Hybrid%',
				'%All-Access Program%',
				'%Supervision + CE%'
			)
		);

		// Repair users whose stored slug does not match All-Access signals.
		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT user_id FROM {$wpdb->usermeta}
			WHERE meta_key IN ('cta_supervision_plan', 'cta_supervision_plan_name', 'cta_hybrid_plan_active')"
		);

		foreach ( (array) $user_ids as $user_id ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				continue;
			}

			$resolved = self::resolve_user_plan_slug( $user_id );
			update_user_meta( $user_id, 'cta_supervision_plan', $resolved );
			update_user_meta( $user_id, 'cta_supervision_plan_name', self::get_name( $resolved ) );
		}

		update_option( 'cta_supervision_product_name', $canonical_group );
		update_option( 'cta_supervision_product_description', self::get_plan( self::GROUP_SLUG )['description'] );
		update_option( 'cta_supervision_monthly_price', self::GROUP_PRICE );
		update_option( 'cta_supervision_all_access_price', self::HYBRID_PRICE );
	}
}
}
