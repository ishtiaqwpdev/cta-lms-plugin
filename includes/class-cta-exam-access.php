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
				'title'                  => 'CTA LMFT California Law & Ethics Exam Preparation Program',
				'slug'                   => 'california-law-ethics-exam-preparation',
				'description'            => '<p>Prepare for the California LMFT Law and Ethics Examination with a self-paced, six-month program designed for AMFTs and other eligible LMFT applicants. The program combines nine California law and ethics workbooks, a required LMFT Practice Act and professional-identity module, original assessments, detailed post-attempt rationales, cumulative practice examinations, flashcards, and targeted remediation tools.</p>',
				'price'                  => 199.00,
				'category'               => 'Exam Preparation',
				'status'                 => 'draft',
				'launch_pending_testing' => true,
				'public_title'           => 'LMFT California Law & Ethics Exam Preparation',
				'course_classification'  => 'Exam Preparation Only — No CE Credit',
				'target_audience'        => 'AMFTs and other eligible California LMFT Law and Ethics Examination candidates',
				'match_titles'           => array(
					'CTA LMFT California Law & Ethics Exam Preparation Program',
					'LMFT California Law & Ethics Exam Preparation',
					'California Law & Ethics Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LCSW California Law & Ethics Exam Preparation Program',
				'slug'                   => 'lcsw-california-law-ethics-exam-preparation',
				'description'            => '<p>LCSW California Law &amp; Ethics Exam Preparation is a separate, profession-specific study program for California ASWs and other eligible LCSW applicants. The program combines current California legal requirements, social-work ethical standards, LCSW/ASW professional identity, applied decision-making, and original examination practice.</p><p>The completed program includes a required LCSW/ASW license-specific module, nine learner workbooks covering 45 law-and-ethics chapters, answer-hidden assessments, detailed option-by-option rationales, two distinct 50-question practice examinations, one 100-question comprehensive final examination, performance and remediation workbooks, an interactive and printable flashcard system, and six high-yield printable study toolkits.</p><p>Learners may access and download approved study materials from the beginning of enrollment and study offline. The recommended order is guidance only; the program does not use continuing-education completion locks, a CE evaluation, or a certificate. Access is valid for six months from purchase. Exam Preparation Only — No CE Credit.</p>',
				'price'                  => 199.00,
				'category'               => 'Exam Preparation',
				'status'                 => 'draft',
				'launch_pending_testing' => true,
				'public_title'           => 'LCSW California Law & Ethics Exam Preparation',
				'course_code'            => 'CTA-EP-002',
				'target_audience'        => 'California ASWs and other eligible LCSW applicants',
				'learning_objectives'    => array(
					'Identify LCSW and ASW professional identity, statutory scope, competence, and role boundaries under California law.',
					'Apply ASW registration, supervision, and employment-related disclosure requirements to real practice settings.',
					'Distinguish confidentiality, privilege, authorization, and lawful disclosure in response to subpoenas or court requests.',
					'Recognize child, elder, dependent-adult, and danger/crisis reporting and safety obligations.',
					'Apply telehealth, technology, privacy, and jurisdiction standards to California social-work practice.',
					'Identify professional boundaries, multiple-relationship risks, and documentation/record-retention requirements.',
				),
				'match_titles'           => array(
					'CTA LCSW California Law & Ethics Exam Preparation Program',
					'LCSW California Law & Ethics Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LPCC California Law & Ethics Exam Preparation Program',
				'slug'                   => 'lpcc-california-law-ethics-exam-preparation',
				'description'            => '<p>LPCC California Law &amp; Ethics Exam Preparation is a separate, profession-specific study program for California APCCs and other eligible LPCC applicants. The program combines current California legal requirements, counseling ethical standards, LPCC/APCC professional identity, applied decision-making, and original examination practice.</p><p>The completed program includes a required LPCC/APCC license-specific module, nine learner workbooks covering 45 law-and-ethics chapters, answer-hidden assessments, detailed option-by-option rationales, two distinct 50-question practice examinations, one 100-question comprehensive final examination, performance and remediation tools, an interactive and printable 807-card flashcard system, and six high-yield printable study toolkits.</p><p>Learners may access and download approved study materials from the beginning of enrollment and study offline. The recommended order is guidance only; the program does not use continuing-education completion locks, a CE evaluation, or a certificate. Access is valid for six months from purchase. Exam Preparation Only — No CE Credit. This program does not award CE hours or a CE certificate.</p>',
				'price'                  => 199.00,
				'category'               => 'Exam Preparation',
				'status'                 => 'draft',
				'launch_pending_testing' => true,
				'public_title'           => 'LPCC California Law & Ethics Exam Preparation',
				'course_code'            => 'CTA-EP-003',
				'target_audience'        => 'California APCCs and other eligible LPCC applicants',
				'learning_objectives'    => array(
					'Identify LPCC and APCC professional identity, statutory scope, competence, and assessment boundaries under California law.',
					'Apply APCC registration, supervision, and disclosure requirements to real practice settings.',
					'Distinguish confidentiality, privilege, authorization, and lawful disclosure in response to subpoenas or court requests.',
					'Recognize child, elder, dependent-adult, and danger/crisis reporting and safety obligations.',
					'Apply telehealth, technology, privacy, and jurisdiction standards to California practice.',
					'Identify professional boundaries, multiple-relationship risks, and documentation/record-keeping requirements.',
				),
				'match_titles'           => array(
					'CTA LPCC California Law & Ethics Exam Preparation Program',
					'LPCC California Law & Ethics Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LMFT AMFTRB National Exam Preparation Program',
				'slug'                   => 'lmft-amftrb-national-exam-preparation',
				'description'            => '<p>Complete self-paced preparation for the AMFTRB National MFT examination. Includes 12 workbooks, 12 practice banks, three cumulative checkpoints, Form A / Form B 180-question simulations with controlled rationales, remediation tools, and 12 recorded audio-review tracks (combined runtime 1:15:26.811) with an authoritative transcript. Access is valid for 6 months from purchase. Exam Preparation Program | No CE Credit. This program does not award CE hours or a CE certificate. CTA is not affiliated with or endorsed by AMFTRB.</p>',
				'price'                  => 329.00,
				'category'               => 'Exam Preparation',
				'status'                 => 'draft',
				'launch_pending_testing' => true,
				'public_title'           => 'LMFT AMFTRB National Exam Preparation',
				'course_classification'  => 'Exam Preparation Program | No CE Credit',
				'match_titles'           => array(
					'CTA LMFT AMFTRB National Exam Preparation Program',
					'LMFT AMFTRB National Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LMFT California Clinical Exam Preparation Program',
				'slug'                   => 'lmft-california-clinical-exam-preparation',
				'description'            => '<p>Complete self-paced preparation for the California LMFT Clinical Exam. Includes 12 workbooks, paired practice banks, two 150-question simulations with controlled rationales, flashcards, and study schedules. Exam Preparation Only — No CE Credit (classification pending final client confirmation). Recorded audio and video are not included at launch. Pricing and access period pending client confirmation.</p>',
				'price'                  => 0.00,
				'category'               => 'Exam Preparation',
				'status'                 => 'draft',
				'commercial_pending'     => true,
				'launch_pending_testing' => true,
				'public_title'           => 'LMFT California Clinical Exam Preparation',
				'match_titles'           => array(
					'CTA LMFT California Clinical Exam Preparation Program',
					'LMFT California Clinical Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LCSW ASWB Clinical Exam Preparation Program',
				'slug'                   => 'lcsw-aswb-clinical-exam-preparation',
				'description'            => '<p>Complete self-paced preparation for the ASWB Clinical Social Work Licensing Examination. Includes 12 social work–specific workbooks, paired practice banks, a 25-question mini-mock, two 122-question simulations with controlled rationales, flashcards, study schedules, and the August 2026 exam-day guide. Access is valid for 6 months from purchase. Exam Preparation Only — No CE Credit. Recorded audio and video are not included at launch.</p>',
				'price'                  => 249.00,
				'category'               => 'Exam Preparation',
				'status'                 => 'draft',
				'launch_pending_testing' => true,
				'legacy_slug'            => 'lcsw-california-clinical-exam-preparation',
				'public_title'           => 'LCSW ASWB Clinical Exam Preparation',
				'match_titles'           => array(
					'CTA LCSW ASWB Clinical Exam Preparation Program',
					'LCSW ASWB Clinical Exam Preparation',
					'LCSW California Clinical Exam Preparation',
					'CTA LCSW California Clinical Exam Preparation Program',
				),
			),
			array(
				'title'                  => 'CTA LPCC NCMHCE Exam Preparation Program',
				'slug'                   => 'lpcc-ncmhce-exam-preparation',
				'description'            => ( class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) && CTA_Lpcc_Ncmhce_Sync::audio_public_advertising_approved() )
					? '<p>Complete self-paced preparation for the NCMHCE for LPCC candidates. Includes 12 workbooks, paired practice banks, three cumulative checkpoints, Form A and Form B simulations (143 questions each) with answer rationales, a Form A remediation workbook, eight audio-review tracks (combined runtime 48 minutes 49 seconds), flashcards, quick references, and study schedules. All learner materials are available from enrollment. Access is valid for 6 months from purchase. Exam Preparation Only — No CE Credit.</p>'
					: '<p>Complete self-paced preparation for the NCMHCE for LPCC candidates. Includes 12 workbooks, paired practice banks, three cumulative checkpoints, Form A and Form B simulations (143 questions each) with answer rationales, a Form A remediation workbook, flashcards, quick references, and study schedules. All learner materials are available from enrollment. Access is valid for 6 months from purchase. Exam Preparation Only — No CE Credit.</p>',
				'price'                  => 249.00,
				'category'               => 'Exam Preparation',
				'status'                 => 'draft',
				'launch_pending_testing' => true,
				'legacy_slug'            => 'lpcc-california-clinical-exam-preparation',
				'public_title'           => 'LPCC NCMHCE Exam Preparation',
				'match_titles'           => array(
					'CTA LPCC NCMHCE Exam Preparation Program',
					'LPCC NCMHCE Exam Preparation',
					'LPCC California Clinical Exam Preparation',
				),
			),
		);
	}

	/**
	 * Seed default exam prep programs if missing (by slug / legacy slug / match titles).
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

			// Match renamed programs by prior formal / public titles.
			if ( ! $existing_id && ! empty( $program['match_titles'] ) ) {
				foreach ( (array) $program['match_titles'] as $match_title ) {
					$match_title = sanitize_text_field( (string) $match_title );
					if ( '' === $match_title ) {
						continue;
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$existing_id = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT id FROM {$table} WHERE title = %s AND product_type = %s LIMIT 1",
							$match_title,
							self::PRODUCT_TYPE_EXAM_PREP
						)
					);
					if ( $existing_id ) {
						break;
					}
				}
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

				if ( ! empty( $program['description'] ) ) {
					$update['description'] = $program['description'];
					$formats[]             = '%s';
				}

				$meta_json = self::merge_public_title_meta( $existing_id, $program );
				if ( null !== $meta_json ) {
					$update['syllabus_meta'] = $meta_json;
					$formats[]               = '%s';
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
			if ( ! empty( $program['commercial_pending'] ) || ! empty( $program['launch_pending_testing'] ) ) {
				$status = 'draft';
			}

			$insert = array(
				'title'                => $program['title'],
				'slug'                 => $program['slug'],
				'description'          => $program['description'],
				'ce_hours'             => 0,
				'price'                => $program['price'],
				'category'             => $program['category'],
				'learning_objectives'  => wp_json_encode( array() ),
				'modules_count'        => 0,
				'status'               => $status,
				'product_type'         => self::PRODUCT_TYPE_EXAM_PREP,
				'access_period_months' => 6,
				'awards_ce_hours'      => 0,
				'has_ce_certificate'   => 0,
			);
			$insert_formats = array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d' );

			if ( ! empty( $program['public_title'] )
				|| ! empty( $program['content_pending'] )
				|| ! empty( $program['launch_pending_testing'] )
				|| ! empty( $program['commercial_pending'] ) ) {
				$insert['syllabus_meta'] = wp_json_encode( self::program_syllabus_meta_defaults( $program ) );
				$insert_formats[]        = '%s';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, $insert, $insert_formats );
		}
	}

	/**
	 * Default syllabus_meta fields for an exam prep program definition.
	 *
	 * @param array $program Program definition.
	 * @return array
	 */
	private static function program_syllabus_meta_defaults( array $program ) {
		$meta = array(
			'course_classification' => 'Exam Preparation Only — No CE Credit',
		);

		if ( ! empty( $program['course_classification'] ) ) {
			$meta['course_classification'] = sanitize_text_field( (string) $program['course_classification'] );
		}

		if ( ! empty( $program['public_title'] ) ) {
			$meta['public_title'] = sanitize_text_field( (string) $program['public_title'] );
		}

		if ( ! empty( $program['content_pending'] ) || ! empty( $program['launch_pending_testing'] ) ) {
			$meta['development_draft'] = true;
		}

		if ( ! empty( $program['launch_pending_testing'] ) ) {
			$meta['launch_pending_testing'] = true;
			$meta['launch_status']          = 'draft_pending_testing';
		}

		if ( ! empty( $program['content_pending'] ) ) {
			$meta['content_pending'] = true;
		}

		if ( ! empty( $program['commercial_pending'] ) ) {
			$meta['commercial_pending'] = true;
			$meta['pricing_status']    = 'pending_client_confirmation';
		}

		return $meta;
	}

	/**
	 * Merge public_title / non-CE classification into an existing course syllabus_meta JSON blob.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $program   Program definition.
	 * @return string|null Encoded JSON, or null when unchanged / unavailable.
	 */
	private static function merge_public_title_meta( $course_id, array $program ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return null;
		}

		if ( empty( $program['public_title'] )
			&& empty( $program['content_pending'] )
			&& empty( $program['launch_pending_testing'] )
			&& empty( $program['commercial_pending'] ) ) {
			return null;
		}

		$table = $wpdb->prefix . 'cta_courses';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT status, syllabus_meta FROM {$table} WHERE id = %d LIMIT 1", $course_id )
		);
		if ( ! $row ) {
			return null;
		}

		$raw = (string) ( $row->syllabus_meta ?? '' );
		$meta = array();
		if ( '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			$meta    = is_array( $decoded ) ? $decoded : array();
		}

		if ( 'published' === (string) ( $row->status ?? '' ) && class_exists( 'CTA_Course_Catalog' ) ) {
			$meta = CTA_Course_Catalog::apply_exam_prep_launch_meta( $meta );
			if ( ! empty( $program['public_title'] ) ) {
				$meta['public_title'] = sanitize_text_field( (string) $program['public_title'] );
			}
			if ( ! empty( $program['course_classification'] ) ) {
				$meta['course_classification'] = sanitize_text_field( (string) $program['course_classification'] );
			} elseif ( empty( $meta['course_classification'] ) ) {
				$meta['course_classification'] = 'Exam Preparation Only — No CE Credit';
			}
			return wp_json_encode( $meta );
		}

		$defaults = self::program_syllabus_meta_defaults( $program );
		$changed  = false;
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $meta ) || $meta[ $key ] !== $value ) {
				$meta[ $key ] = $value;
				$changed      = true;
			}
		}

		if ( ! $changed ) {
			return null;
		}

		return wp_json_encode( $meta );
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

		if ( 'published' === (string) ( $course->status ?? '' ) && (float) ( $course->price ?? 0 ) > 0 ) {
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

	/**
	 * Whether public launch is blocked until the student testing checklist is verified.
	 *
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function launch_pending_testing( $course ) {
		if ( ! $course ) {
			return false;
		}

		if ( 'published' === (string) ( $course->status ?? '' ) ) {
			return false;
		}

		if ( empty( $course->syllabus_meta ) ) {
			return false;
		}

		$meta = json_decode( (string) $course->syllabus_meta, true );
		if ( ! is_array( $meta ) ) {
			return false;
		}

		return ! empty( $meta['launch_pending_testing'] )
			|| ( isset( $meta['launch_status'] ) && 'draft_pending_testing' === $meta['launch_status'] );
	}

	/**
	 * Whether this Exam Prep program enforces assessment/rationale download gates.
	 *
	 * CTA Exam-Preparation Access Standard (Access Correction Notice v1.0):
	 * Exam Prep never uses CE-style progression, submission, score, remediation,
	 * or rationale-hide locks. All learner-facing content is open from enrollment.
	 * CE courses remain the only products that use required progression locks.
	 *
	 * Kept for backward-compatible call sites. AMFTRB still uses preserved-attempt
	 * recording for printable candidate banks; all other Exam Prep programs gate
	 * protected rationales on online quiz submission only.
	 *
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function uses_assessment_gates( $course ) {
		if ( ! is_object( $course ) ) {
			return false;
		}

		$slug = isset( $course->slug ) ? (string) $course->slug : '';

		return 'lmft-amftrb-national-exam-preparation' === $slug;
	}

	/**
	 * Clear unlock_after_quiz_type on all Exam Prep downloadable resources.
	 * Idempotent migration helper for Access Correction Notice v1.0.
	 *
	 * @return int Rows updated.
	 */
	public static function clear_all_exam_prep_material_unlock_gates() {
		global $wpdb;

		$courses = $wpdb->prefix . 'cta_courses';
		$res     = $wpdb->prefix . 'cta_downloadable_resources';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query(
			"UPDATE {$res} r
			INNER JOIN {$courses} c ON c.id = r.course_id
			SET r.unlock_after_quiz_type = ''
			WHERE c.product_type = 'exam_prep'
			  AND r.unlock_after_quiz_type <> ''"
		);
	}
}

}
