<?php
/**
 * Exam Preparation Program access checks and helpers.
 *
 * Exam prep products are non-CE: they grant timed access to instructional
 * content, workbooks, practice tests, and mock exams — never CE hours or
 * certificates. Expiration gates access only; progress and purchase history
 * are preserved.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Access
 */
if ( ! class_exists( 'CTA_Exam_Access' ) ) {

class CTA_Exam_Access {

	const PRODUCT_TYPE_CE        = 'ce';
	const PRODUCT_TYPE_EXAM_PREP = 'exam_prep';

	/**
	 * Whether a course row (or product_type string) is an exam prep program.
	 *
	 * @param object|string|null $course_or_type Course object or product_type value.
	 * @return bool
	 */
	public static function is_exam_prep( $course_or_type ) {
		if ( is_object( $course_or_type ) ) {
			$type = isset( $course_or_type->product_type ) ? (string) $course_or_type->product_type : self::PRODUCT_TYPE_CE;
		} else {
			$type = (string) $course_or_type;
		}

		return self::PRODUCT_TYPE_EXAM_PREP === $type;
	}

	/**
	 * Pure evaluator: whether access is currently active.
	 *
	 * @param bool        $has_record     Whether an exam_access row exists.
	 * @param string|null $expires_at     MySQL datetime or null (null = never expires).
	 * @param string|null $now_mysql      Current MySQL datetime for comparison.
	 * @return bool
	 */
	public static function evaluate_has_active_access( $has_record, $expires_at, $now_mysql ) {
		if ( ! $has_record ) {
			return false;
		}

		if ( null === $expires_at || '' === $expires_at ) {
			return true;
		}

		if ( null === $now_mysql || '' === $now_mysql ) {
			return false;
		}

		return strtotime( (string) $expires_at ) > strtotime( (string) $now_mysql );
	}

	/**
	 * Whether the learner currently has active access to an exam prep course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course / program ID.
	 * @return bool
	 */
	public static function has_active_access( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$record = self::get_access_record( $user_id, $course_id );

		if ( ! $record ) {
			return false;
		}

		$now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

		return self::evaluate_has_active_access(
			true,
			isset( $record->expires_at ) ? $record->expires_at : null,
			$now
		);
	}

	/**
	 * Fetch the exam access row for a user + course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_access_record( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return null;
		}

		$table = $wpdb->prefix . 'cta_exam_access';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d LIMIT 1",
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * All exam access records for a user (including expired).
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_user_access_records( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$table = $wpdb->prefix . 'cta_exam_access';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY purchased_at DESC, id DESC",
				$user_id
			)
		);

		return $rows ? $rows : array();
	}

	/**
	 * Grant or renew timed access after purchase.
	 *
	 * Preserves existing progress; only updates expiration window.
	 *
	 * @param int    $user_id             User ID.
	 * @param int    $course_id           Course ID.
	 * @param int    $access_period_months Months of access (default 6).
	 * @param string $purchased_at        Optional purchase datetime (mysql).
	 * @return object|null Access record.
	 */
	public static function grant_access( $user_id, $course_id, $access_period_months = 6, $purchased_at = '' ) {
		global $wpdb;

		$user_id               = absint( $user_id );
		$course_id             = absint( $course_id );
		$access_period_months  = max( 1, absint( $access_period_months ) );

		if ( ! $user_id || ! $course_id ) {
			return null;
		}

		$now = $purchased_at ? sanitize_text_field( $purchased_at ) : current_time( 'mysql' );
		$expires_at = self::compute_expires_at( $now, $access_period_months );
		$table      = $wpdb->prefix . 'cta_exam_access';
		$existing   = self::get_access_record( $user_id, $course_id );

		if ( $existing ) {
			// Extend from the later of current expiry or now (repurchase renews window).
			$base = $now;
			if ( ! empty( $existing->expires_at ) && strtotime( $existing->expires_at ) > strtotime( $now ) ) {
				$base = $existing->expires_at;
			}
			$expires_at = self::compute_expires_at( $base, $access_period_months );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'expires_at'          => $expires_at,
					'original_expires_at' => empty( $existing->original_expires_at ) ? $expires_at : $existing->original_expires_at,
					'updated_at'          => current_time( 'mysql' ),
				),
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return self::get_access_record( $user_id, $course_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'user_id'             => $user_id,
				'course_id'           => $course_id,
				'purchased_at'        => $now,
				'expires_at'          => $expires_at,
				'original_expires_at' => $expires_at,
				'created_at'          => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return self::get_access_record( $user_id, $course_id );
	}

	/**
	 * Manually extend access (admin action — no request/approval workflow).
	 *
	 * @param int    $user_id      Learner user ID.
	 * @param int    $course_id    Exam prep course ID.
	 * @param int    $extra_months Months to add from current expiry (or now if expired).
	 * @param int    $admin_id     Admin performing the extension.
	 * @param string $notes        Optional notes.
	 * @return object|WP_Error|null
	 */
	public static function extend_access( $user_id, $course_id, $extra_months, $admin_id, $notes = '' ) {
		global $wpdb;

		$user_id      = absint( $user_id );
		$course_id    = absint( $course_id );
		$extra_months = max( 1, absint( $extra_months ) );
		$admin_id     = absint( $admin_id );

		$record = self::get_access_record( $user_id, $course_id );

		if ( ! $record ) {
			return new WP_Error( 'cta_no_access', __( 'No exam access record found for this learner.', 'cta-lms' ) );
		}

		$now  = current_time( 'mysql' );
		$base = ( ! empty( $record->expires_at ) && strtotime( $record->expires_at ) > strtotime( $now ) )
			? $record->expires_at
			: $now;
		$expires_at = self::compute_expires_at( $base, $extra_months );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$wpdb->prefix . 'cta_exam_access',
			array(
				'expires_at'           => $expires_at,
				'extended_by_admin_id' => $admin_id,
				'extension_notes'      => sanitize_textarea_field( $notes ),
				'updated_at'           => $now,
			),
			array( 'id' => (int) $record->id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cta_extend_failed', __( 'Could not extend access.', 'cta-lms' ) );
		}

		return self::get_access_record( $user_id, $course_id );
	}

	/**
	 * Compute expires_at from a base datetime + months.
	 *
	 * @param string $base_mysql Base MySQL datetime.
	 * @param int    $months     Months to add.
	 * @return string
	 */
	public static function compute_expires_at( $base_mysql, $months ) {
		$months = max( 1, absint( $months ) );
		$tz     = function_exists( 'cta_lms_get_timezone' ) ? cta_lms_get_timezone() : new DateTimeZone( 'UTC' );

		try {
			$dt = new DateTimeImmutable( (string) $base_mysql, $tz );
		} catch ( Exception $e ) {
			$dt = new DateTimeImmutable( 'now', $tz );
		}

		return $dt->modify( '+' . $months . ' months' )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Whether a course awards CE hours / certificates (forced false for exam prep).
	 *
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function awards_ce( $course ) {
		if ( ! $course ) {
			return false;
		}

		if ( self::is_exam_prep( $course ) ) {
			return false;
		}

		if ( isset( $course->awards_ce_hours ) ) {
			return (int) $course->awards_ce_hours === 1;
		}

		return true;
	}

	/**
	 * Whether a course issues a CE certificate.
	 *
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function has_ce_certificate( $course ) {
		if ( ! $course ) {
			return false;
		}

		if ( self::is_exam_prep( $course ) ) {
			return false;
		}

		if ( isset( $course->has_ce_certificate ) ) {
			return (int) $course->has_ce_certificate === 1;
		}

		return true;
	}

	/**
	 * Filter course IDs, removing any exam-prep products (for CE bundles).
	 *
	 * @param array $course_ids Course IDs.
	 * @return array
	 */
	public static function filter_ce_only_course_ids( $course_ids ) {
		if ( ! is_array( $course_ids ) || empty( $course_ids ) ) {
			return array();
		}

		$filtered = array();

		foreach ( $course_ids as $id ) {
			$id     = absint( $id );
			$course = $id && class_exists( 'CTA_Database' ) ? CTA_Database::get_course( $id ) : null;

			if ( $course && ! self::is_exam_prep( $course ) ) {
				$filtered[] = $id;
			}
		}

		return array_values( array_unique( $filtered ) );
	}

	/**
	 * Default exam preparation program definitions for seeding.
	 *
	 * @return array
	 */
	public static function get_default_programs() {
		return array(
			array(
				'title'       => 'California Law & Ethics Exam Preparation',
				'slug'        => 'california-law-ethics-exam-preparation',
				'description' => '<p>Comprehensive preparation for the California Law &amp; Ethics exam. Includes online instructional content, printable workbooks, practice tests, and mock examinations with answer rationales. Access is valid for 6 months from purchase. This program does not award CE hours or a CE certificate.</p>',
				'price'       => 199.00,
				'category'    => 'Exam Preparation',
			),
			array(
				'title'       => 'CTA LMFT California Clinical Exam Preparation Program',
				'slug'        => 'lmft-california-clinical-exam-preparation',
				'description' => '<p>Complete self-paced preparation for the California LMFT Clinical Exam. Includes 12 workbooks, paired practice banks, two 150-question simulations with controlled rationales, flashcards, and study schedules. Exam Preparation Only — No CE Credit (classification pending final client confirmation). Recorded audio and video are not included at launch. Pricing and access period pending client confirmation.</p>',
				'price'       => 0.00,
				'category'    => 'Exam Preparation',
				'status'      => 'draft',
				'commercial_pending' => true,
			),
			array(
				'title'       => 'CTA LCSW ASWB Clinical Exam Preparation Program',
				'slug'        => 'lcsw-aswb-clinical-exam-preparation',
				'description' => '<p>Complete self-paced preparation for the ASWB Clinical Social Work Licensing Examination. Includes 12 social work–specific workbooks, paired practice banks, a 25-question mini-mock, two 122-question simulations with controlled rationales, flashcards, study schedules, and the August 2026 exam-day guide. Access is valid for 6 months from purchase. Exam Preparation Only — No CE Credit. Recorded audio and video are not included at launch.</p>',
				'price'       => 249.00,
				'category'    => 'Exam Preparation',
				'legacy_slug' => 'lcsw-california-clinical-exam-preparation',
			),
			array(
				'title'       => 'LPCC California Clinical Exam Preparation',
				'slug'        => 'lpcc-california-clinical-exam-preparation',
				'description' => '<p>Targeted preparation for the LPCC California Clinical Exam. Includes online instructional content, printable workbooks, practice tests, and mock examinations with answer rationales. Access is valid for 6 months from purchase. This program does not award CE hours or a CE certificate.</p>',
				'price'       => 249.00,
				'category'    => 'Exam Preparation',
			),
		);
	}

	/**
	 * Seed the four default exam prep programs if missing (by slug).
	 */
	public static function seed_default_programs() {
		global $wpdb;

		if ( ! class_exists( 'CTA_Database' ) ) {
			return;
		}

		CTA_Database::ensure_tables();

		$table = $wpdb->prefix . 'cta_courses';

		foreach ( self::get_default_programs() as $program ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
					$program['slug']
				)
			);

			// Migrate legacy slug (e.g. LCSW California → ASWB Clinical).
			if ( ! $existing_id && ! empty( $program['legacy_slug'] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$existing_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
						$program['legacy_slug']
					)
				);
			}

			if ( $existing_id ) {
				$update = array(
					'title'                => $program['title'],
					'slug'                 => $program['slug'],
					'price'                => (float) $program['price'],
					'category'             => $program['category'],
					'product_type'         => self::PRODUCT_TYPE_EXAM_PREP,
					'access_period_months' => 6,
					'ce_hours'             => 0,
					'awards_ce_hours'      => 0,
					'has_ce_certificate'   => 0,
				);
				$formats = array( '%s', '%s', '%f', '%s', '%s', '%d', '%f', '%d', '%d' );

				// Commercial terms unconfirmed: keep draft and do not treat catalog price as live publish.
				if ( ! empty( $program['commercial_pending'] ) ) {
					$update['status'] = 'draft';
					$formats[]        = '%s';
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					$update,
					array( 'id' => $existing_id ),
					$formats,
					array( '%d' )
				);
				continue;
			}

			$status = ! empty( $program['status'] ) ? sanitize_text_field( (string) $program['status'] ) : 'draft';
			if ( ! empty( $program['commercial_pending'] ) ) {
				$status = 'draft';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'title'                 => $program['title'],
					'slug'                  => $program['slug'],
					'description'           => $program['description'],
					'ce_hours'              => 0,
					'price'                 => $program['price'],
					'category'              => $program['category'],
					'learning_objectives'   => wp_json_encode( array() ),
					'modules_count'         => 0,
					'status'                => $status,
					'product_type'          => self::PRODUCT_TYPE_EXAM_PREP,
					'access_period_months'  => 6,
					'awards_ce_hours'       => 0,
					'has_ce_certificate'    => 0,
				),
				array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * Whether commercial terms (price / access / classification) are pending client confirmation.
	 *
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function commercial_terms_pending( $course ) {
		if ( ! $course ) {
			return false;
		}

		if ( empty( $course->syllabus_meta ) ) {
			return false;
		}

		$meta = json_decode( (string) $course->syllabus_meta, true );
		if ( ! is_array( $meta ) ) {
			return false;
		}

		return ! empty( $meta['commercial_pending'] )
			|| ( isset( $meta['pricing_status'] ) && 'pending_client_confirmation' === $meta['pricing_status'] );
	}
}

}
