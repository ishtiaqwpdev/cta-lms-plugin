<?php
/**
 * CE course access rules (individual purchase vs membership).
 *
 * Rules:
 * 1. Individually purchased CE courses → permanent access (no expiration).
 * 2. Membership/subscription CE access → only while membership is active;
 *    revoked on cancel/expiry unless the learner also purchased that course.
 * 3. Certificates are always permanent (never gated by membership/enrollment).
 *
 * Exam Prep uses CTA_Exam_Access — do not use this class for exam_prep products.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_CE_Access
 */
if ( ! class_exists( 'CTA_CE_Access' ) ) {

class CTA_CE_Access {

	const SOURCE_PURCHASE    = 'purchase';
	const SOURCE_MEMBERSHIP  = 'membership';
	const STATUS_REVOKED     = 'revoked';

	/**
	 * Whether the course is a standard CE product (not Exam Prep).
	 *
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function is_ce_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the learner currently has content access to a CE course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function has_active_access( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return false;
		}

		$course = CTA_Database::get_course( $course_id );
		if ( ! self::is_ce_course( $course ) ) {
			return false;
		}

		// Individual purchase always wins (Rule 1 / Rule 2 exception).
		if ( self::user_has_individual_purchase( $user_id, $course_id ) ) {
			$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
			return (bool) $enrollment && in_array( (string) $enrollment->status, array( 'active', 'completed' ), true );
		}

		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		if ( ! $enrollment ) {
			return false;
		}

		$status = (string) $enrollment->status;
		if ( self::STATUS_REVOKED === $status ) {
			return false;
		}

		if ( ! in_array( $status, array( 'active', 'completed' ), true ) ) {
			return false;
		}

		$source = self::resolve_access_source( $enrollment );

		if ( self::SOURCE_PURCHASE === $source ) {
			return true;
		}

		// Membership-sourced access.
		if ( ! empty( $enrollment->expires_at ) ) {
			return strtotime( (string) $enrollment->expires_at ) > strtotime( current_time( 'mysql' ) );
		}

		// Subscription membership with no hard expiry date: require live membership.
		return self::user_has_active_membership( $user_id );
	}

	/**
	 * Resolve access_source for an enrollment row (with legacy fallback).
	 *
	 * @param object $enrollment Enrollment row.
	 * @return string purchase|membership
	 */
	public static function resolve_access_source( $enrollment ) {
		if ( ! empty( $enrollment->access_source ) ) {
			$source = sanitize_key( (string) $enrollment->access_source );
			if ( in_array( $source, array( self::SOURCE_PURCHASE, self::SOURCE_MEMBERSHIP ), true ) ) {
				return $source;
			}
		}

		$user_id   = isset( $enrollment->user_id ) ? (int) $enrollment->user_id : 0;
		$course_id = isset( $enrollment->course_id ) ? (int) $enrollment->course_id : 0;

		if ( $user_id && $course_id && self::user_has_individual_purchase( $user_id, $course_id ) ) {
			return self::SOURCE_PURCHASE;
		}

		if ( $user_id && self::enrollment_payment_looks_like_membership( $enrollment ) ) {
			return self::SOURCE_MEMBERSHIP;
		}

		// Legacy rows without evidence of membership → treat as permanent purchase.
		return self::SOURCE_PURCHASE;
	}

	/**
	 * Whether the user has a completed individual course payment for this course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function user_has_individual_purchase( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d
				AND product_type = 'course'
				AND product_id = %d
				AND status = 'completed'
				LIMIT 1",
				$user_id,
				$course_id
			)
		);

		if ( $found ) {
			return true;
		}

		$enrollment = class_exists( 'CTA_Database' )
			? CTA_Database::get_user_enrollment( $user_id, $course_id )
			: null;

		return $enrollment && self::SOURCE_PURCHASE === sanitize_key( (string) ( $enrollment->access_source ?? '' ) );
	}

	/**
	 * Whether the user currently has an active CE membership/subscription.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_active_membership( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$hybrid = (int) get_user_meta( $user_id, 'cta_hybrid_plan_active', true );
		$sub_id = (string) get_user_meta( $user_id, 'cta_bundle_subscription_id', true );

		if ( $hybrid > 0 || '' !== $sub_id ) {
			if ( '' !== $sub_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$status = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT status FROM {$wpdb->prefix}cta_payments
						WHERE user_id = %d AND stripe_payment_id = %s
						ORDER BY id DESC LIMIT 1",
						$user_id,
						$sub_id
					)
				);
				if ( $status && ! in_array( (string) $status, array( 'refunded', 'cancelled' ), true ) ) {
					return true;
				}
				// Subscription id present but payment cancelled/refunded.
				if ( $status ) {
					return false;
				}
			}

			if ( $hybrid > 0 ) {
				return true;
			}
		}

		// Annual membership: still within enrollment expires_at window.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_annual = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT e.id FROM {$wpdb->prefix}cta_enrollments e
				WHERE e.user_id = %d
				AND e.access_source = %s
				AND e.status IN ('active','completed')
				AND e.expires_at IS NOT NULL
				AND e.expires_at != ''
				AND e.expires_at > %s
				LIMIT 1",
				$user_id,
				self::SOURCE_MEMBERSHIP,
				current_time( 'mysql' )
			)
		);

		return (bool) $active_annual;
	}

	/**
	 * Guess membership source from payment_id linkage (legacy backfill).
	 *
	 * @param object $enrollment Enrollment row.
	 * @return bool
	 */
	private static function enrollment_payment_looks_like_membership( $enrollment ) {
		global $wpdb;

		$payment_id = isset( $enrollment->payment_id ) ? (string) $enrollment->payment_id : '';
		$user_id    = isset( $enrollment->user_id ) ? (int) $enrollment->user_id : 0;

		if ( '' === $payment_id || ! $user_id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT product_type, product_id, plan_details FROM {$wpdb->prefix}cta_payments
				WHERE user_id = %d AND stripe_payment_id = %s
				LIMIT 1",
				$user_id,
				$payment_id
			)
		);

		if ( ! $payment || 'bundle' !== $payment->product_type ) {
			return false;
		}

		$bundle = class_exists( 'CTA_Database' )
			? CTA_Database::get_bundle( (int) $payment->product_id )
			: null;

		if ( $bundle && in_array( (string) $bundle->plan_type, array( 'annual', 'subscription' ), true ) ) {
			return true;
		}

		$details = json_decode( (string) $payment->plan_details, true );
		if ( is_array( $details ) ) {
			$plan = isset( $details['billing'] ) ? (string) $details['billing'] : '';
			if ( in_array( $plan, array( 'yearly', 'annual', 'monthly', 'subscription' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Revoke membership-sourced CE access for a user (keep purchase + certificates).
	 *
	 * Sets expires_at to now and marks non-completed membership enrollments revoked
	 * only when the learner does not also have an individual purchase.
	 *
	 * @param int $user_id User ID.
	 * @return int Number of enrollments updated.
	 */
	public static function revoke_membership_access( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		update_user_meta( $user_id, 'cta_hybrid_plan_active', 0 );

		$table = $wpdb->prefix . 'cta_enrollments';
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d",
				$user_id
			)
		);

		$updated = 0;

		foreach ( (array) $rows as $row ) {
			$course = class_exists( 'CTA_Database' ) ? CTA_Database::get_course( (int) $row->course_id ) : null;
			if ( ! self::is_ce_course( $course ) ) {
				continue;
			}

			if ( self::user_has_individual_purchase( $user_id, (int) $row->course_id ) ) {
				// Rule 2 exception / Rule 1: keep permanent.
				if ( self::SOURCE_PURCHASE !== sanitize_key( (string) ( $row->access_source ?? '' ) ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$table,
						array(
							'access_source' => self::SOURCE_PURCHASE,
							'expires_at'    => null,
						),
						array( 'id' => (int) $row->id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
					++$updated;
				}
				continue;
			}

			$source = self::resolve_access_source( $row );
			if ( self::SOURCE_MEMBERSHIP !== $source ) {
				continue;
			}

			$data    = array(
				'access_source' => self::SOURCE_MEMBERSHIP,
				'expires_at'    => $now,
			);
			$formats = array( '%s', '%s' );

			// Keep completed status so history remains; revoke active mid-progress.
			if ( 'active' === $row->status ) {
				$data['status'] = self::STATUS_REVOKED;
				$formats[]      = '%s';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				$data,
				array( 'id' => (int) $row->id ),
				$formats,
				array( '%d' )
			);

			if ( false !== $result ) {
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Ensure enrollment access_source column exists and backfill legacy rows.
	 */
	public static function maybe_install_schema() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_enrollments';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'access_source' ) );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN access_source varchar(20) DEFAULT NULL AFTER payment_id" );
		}

		self::backfill_enrollment_sources();
	}

	/**
	 * Backfill access_source for enrollments that pre-date this feature.
	 */
	public static function backfill_enrollment_sources() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_enrollments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE access_source IS NULL OR access_source = ''"
		);

		foreach ( (array) $rows as $row ) {
			$source = self::resolve_access_source( $row );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'access_source' => $source ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Build enrollment args for a membership/bundle grant.
	 *
	 * @param object $bundle  Bundle row.
	 * @param string $billing Billing hint from checkout metadata.
	 * @return array{access_source:string,expires_at:?string}
	 */
	public static function enrollment_args_for_bundle( $bundle, $billing = '' ) {
		$plan    = $bundle ? (string) $bundle->plan_type : 'bundle';
		$billing = sanitize_text_field( (string) $billing );

		$is_membership = in_array( $plan, array( 'annual', 'subscription' ), true )
			|| in_array( $billing, array( 'yearly', 'annual', 'monthly', 'subscription' ), true );

		if ( ! $is_membership ) {
			// Fixed one-time course bundles = permanent purchase of included courses.
			return array(
				'access_source' => self::SOURCE_PURCHASE,
				'expires_at'    => null,
			);
		}

		$expires_at = null;
		if ( 'annual' === $plan || in_array( $billing, array( 'yearly', 'annual' ), true ) ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' +12 months' ) );
			// Prefer site-local time via DateTime if available.
			try {
				$tz   = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
				$dt   = new DateTime( current_time( 'mysql' ), $tz );
				$dt->modify( '+12 months' );
				$expires_at = $dt->format( 'Y-m-d H:i:s' );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Keep gmdate fallback.
			}
		}

		return array(
			'access_source' => self::SOURCE_MEMBERSHIP,
			'expires_at'    => $expires_at,
		);
	}
}

}
