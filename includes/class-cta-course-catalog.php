<?php
/**
 * Canonical CE / Exam Prep catalog pricing & category restore.
 *
 * Values are client-provided and must not be guessed. Used by upgrades and
 * admin "Sync Syllabus" / restore flows. Never drops tables or enrollments.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Course_Catalog
 */
if ( ! class_exists( 'CTA_Course_Catalog' ) ) {

class CTA_Course_Catalog {

	/**
	 * Exact CE course commercial data (client-provided).
	 *
	 * @return array
	 */
	public static function get_ce_catalog() {
		return array(
			array(
				'match_titles' => array(
					'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
					'California Law & Ethics for Mental Health Professionals',
				),
				'title'        => 'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
				'ce_hours'     => 6.0,
				'price'        => 79.00,
				'category'     => 'Law & Ethics',
			),
			array(
				'match_titles' => array(
					'Clinical and Ethical Excellence in Telehealth: The Essential California Framework',
					'Clinical and Ethical Excellence in Telehealth',
				),
				'title'        => 'Clinical and Ethical Excellence in Telehealth: The Essential California Framework',
				'ce_hours'     => 3.0,
				'price'        => 45.00,
				'category'     => 'Clinical Skills',
			),
			array(
				'match_titles' => array(
					'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
					'Advanced Suicide Risk Assessment',
				),
				'title'        => 'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
				'ce_hours'     => 6.0,
				'price'        => 79.00,
				'category'     => 'Clinical Skills',
			),
			array(
				'match_titles' => array(
					'Alcoholism & Other Chemical Substance Dependency: Assessment, Treatment, Recovery, & Clinical Practice',
					'Alcoholism & Other Chemical Substance Dependency',
				),
				'title'        => 'Alcoholism & Other Chemical Substance Dependency: Assessment, Treatment, Recovery, & Clinical Practice',
				'ce_hours'     => 15.0,
				'price'        => 149.00,
				'category'     => 'Alcoholism & Other Chemical Substance Dependency',
			),
			array(
				'match_titles' => array( 'Child Abuse Assessment & Mandated Reporting' ),
				'title'        => 'Child Abuse Assessment & Mandated Reporting',
				'ce_hours'     => 7.0,
				'price'        => 89.00,
				'category'     => 'Law & Ethics',
			),
			array(
				'match_titles' => array(
					'HIV/AIDS and Mental Health: Clinical Implications, Stigma, and Ethical Practice',
					'HIV/AIDS and Mental Health',
				),
				'title'        => 'HIV/AIDS and Mental Health: Clinical Implications, Stigma, and Ethical Practice',
				'ce_hours'     => 7.0,
				'price'        => 89.00,
				'category'     => 'Clinical Skills',
			),
			array(
				'match_titles' => array(
					'Human Sexuality & Clinical Practice: Biological, Psychological, and Cultural Perspectives',
					'Human Sexuality & Clinical Practice',
				),
				'title'        => 'Human Sexuality & Clinical Practice: Biological, Psychological, and Cultural Perspectives',
				'ce_hours'     => 10.0,
				'price'        => 99.00,
				'category'     => 'Clinical Skills',
			),
			array(
				'match_titles' => array(
					'The Fundamentals of Clinical Supervision: Legal Frameworks and Developmental Models',
					'The Fundamentals of Clinical Supervision',
					'Fundamentals of Clinical Supervision',
				),
				'title'        => 'The Fundamentals of Clinical Supervision: Legal Frameworks and Developmental Models',
				// Master Pricing Catalog v3.5 Course 8 = 15 CE; commercial price $169 (client).
				'ce_hours'     => 15.0,
				'price'        => 169.00,
				'category'     => 'Supervision',
			),
		);
	}

	/**
	 * Exact Exam Preparation commercial data (client-provided).
	 *
	 * @return array
	 */
	public static function get_exam_prep_catalog() {
		return array(
			array(
				'title'                  => 'CTA LMFT California Law & Ethics Exam Preparation Program',
				'slug'                   => 'california-law-ethics-exam-preparation',
				'price'                  => 199.00,
				'access_period_months'   => 6,
				'category'               => 'Exam Preparation',
				'public_title'           => 'LMFT California Law & Ethics Exam Preparation',
				'launch_pending_testing' => true,
				'match_titles'           => array(
					'CTA LMFT California Law & Ethics Exam Preparation Program',
					'LMFT California Law & Ethics Exam Preparation',
					'California Law & Ethics Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LCSW California Law & Ethics Exam Preparation Program',
				'slug'                   => 'lcsw-california-law-ethics-exam-preparation',
				'price'                  => 199.00,
				'access_period_months'   => 6,
				'category'               => 'Exam Preparation',
				'public_title'           => 'LCSW California Law & Ethics Exam Preparation',
				'launch_pending_testing' => true,
				'match_titles'           => array(
					'CTA LCSW California Law & Ethics Exam Preparation Program',
					'LCSW California Law & Ethics Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LPCC California Law & Ethics Exam Preparation Program',
				'slug'                   => 'lpcc-california-law-ethics-exam-preparation',
				'price'                  => 199.00,
				'access_period_months'   => 6,
				'category'               => 'Exam Preparation',
				'public_title'           => 'LPCC California Law & Ethics Exam Preparation',
				'launch_pending_testing' => true,
				'match_titles'           => array(
					'CTA LPCC California Law & Ethics Exam Preparation Program',
					'LPCC California Law & Ethics Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LMFT AMFTRB National Exam Preparation Program',
				'slug'                   => 'lmft-amftrb-national-exam-preparation',
				'price'                  => 329.00,
				'access_period_months'   => 6,
				'category'               => 'Exam Preparation',
				'public_title'           => 'LMFT AMFTRB National Exam Preparation',
				'launch_pending_testing' => true,
				'match_titles'           => array(
					'CTA LMFT AMFTRB National Exam Preparation Program',
					'LMFT AMFTRB National Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LMFT California Clinical Exam Preparation Program',
				'slug'                   => 'lmft-california-clinical-exam-preparation',
				// Commercial terms pending client confirmation — do not publish assumed LCSW-matching price.
				'price'                  => 0.00,
				'access_period_months'   => 6,
				'category'               => 'Exam Preparation',
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
				'price'                  => 249.00,
				'access_period_months'   => 6,
				'category'               => 'Exam Preparation',
				'launch_pending_testing' => true,
				'public_title'           => 'LCSW ASWB Clinical Exam Preparation',
				// Migrate legacy California Clinical label → public ASWB title/slug.
				'match_slugs'            => array(
					'lcsw-aswb-clinical-exam-preparation',
					'lcsw-california-clinical-exam-preparation',
				),
				'match_titles'           => array(
					'CTA LCSW ASWB Clinical Exam Preparation Program',
					'LCSW ASWB Clinical Exam Preparation',
					'LCSW California Clinical Exam Preparation',
				),
			),
			array(
				'title'                  => 'CTA LPCC NCMHCE Exam Preparation Program',
				'slug'                   => 'lpcc-ncmhce-exam-preparation',
				'price'                  => 249.00,
				'access_period_months'   => 6,
				'category'               => 'Exam Preparation',
				'launch_pending_testing' => true,
				'public_title'           => 'LPCC NCMHCE Exam Preparation',
				'match_slugs'            => array(
					'lpcc-ncmhce-exam-preparation',
					'lpcc-california-clinical-exam-preparation',
				),
				'match_titles'           => array(
					'CTA LPCC NCMHCE Exam Preparation Program',
					'LPCC NCMHCE Exam Preparation',
					'LPCC California Clinical Exam Preparation',
				),
			),
		);
	}

	/**
	 * Find a CE course by match titles (exact then LIKE).
	 *
	 * @param array $entry Catalog entry.
	 * @return object|null
	 */
	public static function find_ce_course( array $entry ) {
		$courses = self::find_all_ce_courses( $entry );
		return ! empty( $courses ) ? $courses[0] : null;
	}

	/**
	 * Find all CE courses matching catalog titles (handles duplicates).
	 *
	 * @param array $entry Catalog entry.
	 * @return array
	 */
	public static function find_all_ce_courses( array $entry ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'cta_courses';
		$matches = isset( $entry['match_titles'] ) ? (array) $entry['match_titles'] : array();
		if ( empty( $matches ) && ! empty( $entry['title'] ) ) {
			$matches = array( $entry['title'] );
		}

		$found = array();
		$seen  = array();

		foreach ( $matches as $needle ) {
			$needle = trim( (string) $needle );
			if ( '' === $needle ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE title = %s
					AND (product_type = 'ce' OR product_type = '' OR product_type IS NULL)
					ORDER BY id ASC",
					$needle
				)
			);

			foreach ( (array) $rows as $course ) {
				$id = (int) $course->id;
				if ( ! isset( $seen[ $id ] ) ) {
					$seen[ $id ] = true;
					$found[]     = $course;
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE title LIKE %s
					AND (product_type = 'ce' OR product_type = '' OR product_type IS NULL)
					ORDER BY id ASC",
					'%' . $wpdb->esc_like( $needle ) . '%'
				)
			);

			foreach ( (array) $rows as $course ) {
				$id = (int) $course->id;
				if ( ! isset( $seen[ $id ] ) ) {
					$seen[ $id ] = true;
					$found[]     = $course;
				}
			}
		}

		return $found;
	}

	/**
	 * Compare two money amounts at cent precision.
	 *
	 * @param float $a First amount.
	 * @param float $b Second amount.
	 * @return bool
	 */
	public static function prices_equal( $a, $b ) {
		return (int) round( (float) $a * 100 ) === (int) round( (float) $b * 100 );
	}

	/**
	 * Price-only sync against the approved CE + Exam Prep catalog.
	 *
	 * Does not change titles, categories, or hours. Updates every matching
	 * duplicate row. Returns a before/after report for admin review.
	 *
	 * @return array
	 */
	public static function sync_approved_prices() {
		global $wpdb;

		CTA_Database::ensure_tables();
		CTA_Database::maybe_add_exam_prep_columns();

		$table  = $wpdb->prefix . 'cta_courses';
		$report = array(
			'corrected' => array(),
			'unchanged' => array(),
			'missing'   => array(),
			'synced_at' => gmdate( 'c' ),
		);

		foreach ( self::get_ce_catalog() as $entry ) {
			$approved = (float) $entry['price'];
			$label    = sanitize_text_field( (string) ( $entry['title'] ?? '' ) );
			$courses  = self::find_all_ce_courses( $entry );

			if ( empty( $courses ) ) {
				$report['missing'][] = array(
					'title'           => $label,
					'approved_price'  => $approved,
					'product_type'    => 'ce',
				);
				continue;
			}

			foreach ( $courses as $course ) {
				$before = isset( $course->price ) ? (float) $course->price : 0.0;
				$row    = array(
					'id'              => (int) $course->id,
					'title'           => (string) $course->title,
					'catalog_title'   => $label,
					'product_type'    => 'ce',
					'price_before'    => $before,
					'price_after'     => $approved,
					'approved_price'  => $approved,
				);

				if ( self::prices_equal( $before, $approved ) ) {
					$report['unchanged'][] = $row;
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array( 'price' => $approved ),
					array( 'id' => (int) $course->id ),
					array( '%f' ),
					array( '%d' )
				);

				$report['corrected'][] = $row;
			}
		}

		if ( class_exists( 'CTA_Exam_Access' ) ) {
			CTA_Exam_Access::seed_default_programs();
		}

		foreach ( self::get_exam_prep_catalog() as $entry ) {
			$approved = (float) $entry['price'];
			$slug     = sanitize_title( (string) $entry['slug'] );
			$title    = sanitize_text_field( (string) $entry['title'] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$courses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE slug = %s OR title = %s OR title LIKE %s
					ORDER BY id ASC",
					$slug,
					$title,
					'%' . $wpdb->esc_like( $title ) . '%'
				)
			);

			// Prefer exact exam_prep matches; fall back to any title/slug hit.
			$filtered = array();
			foreach ( (array) $courses as $course ) {
				$type = isset( $course->product_type ) ? (string) $course->product_type : '';
				if ( 'exam_prep' === $type || $slug === (string) $course->slug || $title === (string) $course->title ) {
					$filtered[] = $course;
				}
			}
			if ( empty( $filtered ) ) {
				$filtered = (array) $courses;
			}

			if ( empty( $filtered ) ) {
				$report['missing'][] = array(
					'title'          => $title,
					'approved_price' => $approved,
					'product_type'   => 'exam_prep',
				);
				continue;
			}

			$seen = array();
			foreach ( $filtered as $course ) {
				$id = (int) $course->id;
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;

				$before = isset( $course->price ) ? (float) $course->price : 0.0;
				$row    = array(
					'id'             => $id,
					'title'          => (string) $course->title,
					'catalog_title'  => $title,
					'product_type'   => 'exam_prep',
					'price_before'   => $before,
					'price_after'    => $approved,
					'approved_price' => $approved,
				);

				if ( self::prices_equal( $before, $approved ) ) {
					$report['unchanged'][] = $row;
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array(
						'price'        => $approved,
						'product_type' => 'exam_prep',
					),
					array( 'id' => $id ),
					array( '%f', '%s' ),
					array( '%d' )
				);

				$report['corrected'][] = $row;
			}
		}

		update_option( 'cta_approved_price_sync_1_0_95', wp_json_encode( $report ), false );

		return $report;
	}

	/**
	 * Restore CE price / category / ce_hours from the canonical catalog.
	 *
	 * Never deletes rows. Creates a published stub only when a listed CE course
	 * is completely missing (price/category/hours filled; modules left empty).
	 *
	 * @return array Report.
	 */
	public static function restore_ce_pricing() {
		global $wpdb;

		$table  = $wpdb->prefix . 'cta_courses';
		$report = array(
			'updated'   => array(),
			'created'   => array(),
			'missing'   => array(),
			'corrected' => array(),
		);

		foreach ( self::get_ce_catalog() as $entry ) {
			$courses = self::find_all_ce_courses( $entry );
			$title   = sanitize_text_field( (string) $entry['title'] );
			$price   = (float) $entry['price'];
			$ce      = (float) $entry['ce_hours'];
			$cat     = sanitize_text_field( (string) $entry['category'] );

			if ( ! empty( $courses ) ) {
				foreach ( $courses as $course ) {
					$before    = isset( $course->price ) ? (float) $course->price : 0.0;
					$before_ce = isset( $course->ce_hours ) ? (float) $course->ce_hours : 0.0;

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$table,
						array(
							'price'    => $price,
							'category' => $cat,
							'ce_hours' => $ce,
							'title'    => $title,
						),
						array( 'id' => (int) $course->id ),
						array( '%f', '%s', '%f', '%s' ),
						array( '%d' )
					);

					$row = array(
						'id'              => (int) $course->id,
						'title'           => $title,
						'price'           => $price,
						'price_before'    => $before,
						'price_after'     => $price,
						'ce_hours_before' => $before_ce,
						'ce_hours_after'  => $ce,
						'category'        => $cat,
						'ce_hours'        => $ce,
					);
					$report['updated'][] = $row;
					if ( ! self::prices_equal( $before, $price ) || abs( $before_ce - $ce ) > 0.01 ) {
						$report['corrected'][] = $row;
					}
				}
				continue;
			}

			$slug = sanitize_title( $title );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$slug_exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug )
			);
			if ( $slug_exists ) {
				$slug .= '-ce';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$table,
				array(
					'title'                => $title,
					'slug'                 => $slug,
					'description'          => '',
					'ce_hours'             => $ce,
					'price'                => $price,
					'category'             => $cat,
					'learning_objectives'  => '[]',
					'modules_count'        => 0,
					'status'               => 'draft',
					'product_type'         => 'ce',
					'access_period_months' => 6,
					'awards_ce_hours'      => 1,
					'has_ce_certificate'   => 1,
				),
				array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d' )
			);

			if ( $inserted ) {
				$report['created'][] = array(
					'id'       => (int) $wpdb->insert_id,
					'title'    => $title,
					'price'    => $price,
					'category' => $cat,
					'ce_hours' => $ce,
				);
			} else {
				$report['missing'][] = $title;
			}
		}

		return $report;
	}

	/**
	 * Self-heal CE commercial fields when the canonical catalog fingerprint drifts.
	 *
	 * @param bool $force Force restore even when fingerprint matches.
	 * @return array|null Report or null when skipped.
	 */
	public static function maybe_restore_ce_pricing( $force = false ) {
		$fingerprint = '';
		if ( function_exists( 'cta_ce_price_catalog_fingerprint' ) ) {
			$fingerprint = (string) cta_ce_price_catalog_fingerprint();
		} else {
			$parts = array();
			foreach ( self::get_ce_catalog() as $entry ) {
				$parts[] = (string) ( $entry['title'] ?? '' )
					. ':' . number_format( (float) ( $entry['price'] ?? 0 ), 2, '.', '' )
					. ':' . number_format( (float) ( $entry['ce_hours'] ?? 0 ), 2, '.', '' );
			}
			$fingerprint = md5( implode( '|', $parts ) );
		}

		if ( ! $force && $fingerprint && get_option( 'cta_ce_price_catalog_fp', '' ) === $fingerprint ) {
			return null;
		}

		if ( get_transient( 'cta_ce_price_sync_lock' ) ) {
			return null;
		}
		set_transient( 'cta_ce_price_sync_lock', 1, 60 );

		$report = self::restore_ce_pricing();

		if ( $fingerprint ) {
			update_option( 'cta_ce_price_catalog_fp', $fingerprint, false );
		}

		if ( class_exists( 'CTA_Bundle_Catalog' ) && method_exists( 'CTA_Bundle_Catalog', 'clear_front_caches' ) ) {
			CTA_Bundle_Catalog::clear_front_caches();
		}

		delete_transient( 'cta_ce_price_sync_lock' );

		return $report;
	}

	/**
	 * Restore Exam Prep price / category / access / non-CE flags.
	 *
	 * Updates existing by slug or title; seeds missing via CTA_Exam_Access when available.
	 *
	 * @return array Report.
	 */
	public static function restore_exam_prep_pricing() {
		global $wpdb;

		$table  = $wpdb->prefix . 'cta_courses';
		$report = array(
			'updated'   => array(),
			'created'   => array(),
			'missing'   => array(),
			'corrected' => array(),
		);

		if ( class_exists( 'CTA_Exam_Access' ) ) {
			// Ensure rows exist first (insert-only when missing).
			CTA_Exam_Access::seed_default_programs();
		}

		foreach ( self::get_exam_prep_catalog() as $entry ) {
			$slug  = sanitize_title( (string) $entry['slug'] );
			$title = sanitize_text_field( (string) $entry['title'] );
			$price = (float) $entry['price'];

			$slugs  = ! empty( $entry['match_slugs'] ) ? array_map( 'sanitize_title', (array) $entry['match_slugs'] ) : array( $slug );
			$titles = ! empty( $entry['match_titles'] ) ? array_map( 'sanitize_text_field', (array) $entry['match_titles'] ) : array( $title );
			$slugs  = array_values( array_unique( array_filter( $slugs ) ) );
			$titles = array_values( array_unique( array_filter( $titles ) ) );

			$courses = array();
			$seen    = array();
			foreach ( $slugs as $match_slug ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s ORDER BY id ASC", $match_slug )
				);
				foreach ( (array) $rows as $row ) {
					$id = (int) $row->id;
					if ( ! isset( $seen[ $id ] ) ) {
						$seen[ $id ] = true;
						$courses[]   = $row;
					}
				}
			}
			foreach ( $titles as $match_title ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$table} WHERE title = %s ORDER BY id ASC", $match_title )
				);
				foreach ( (array) $rows as $row ) {
					$id = (int) $row->id;
					if ( ! isset( $seen[ $id ] ) ) {
						$seen[ $id ] = true;
						$courses[]   = $row;
					}
				}
			}

			$data = array(
				'title'                => $title,
				'slug'                 => $slug,
				'price'                => $price,
				'category'             => sanitize_text_field( (string) $entry['category'] ),
				'access_period_months' => absint( $entry['access_period_months'] ),
				'ce_hours'             => 0,
				'product_type'         => 'exam_prep',
				'awards_ce_hours'      => 0,
				'has_ce_certificate'   => 0,
			);
			$formats = array( '%s', '%s', '%f', '%s', '%d', '%f', '%s', '%d', '%d' );

			$force_draft = false;
			// Pending commercial confirmation: keep draft; do not publish a live price.
			if ( ! empty( $entry['commercial_pending'] ) ) {
				$data['price'] = 0.0;
				$force_draft   = true;
			}
			// Launch testing incomplete: keep draft (configured price preserved for go-live).
			if ( ! empty( $entry['launch_pending_testing'] ) ) {
				$force_draft = true;
			}
			if ( $force_draft ) {
				$data['status'] = 'draft';
				$formats[]      = '%s';
			}

			if ( ! empty( $courses ) ) {
				$seen = array();
				foreach ( $courses as $course ) {
					$id = (int) $course->id;
					if ( isset( $seen[ $id ] ) ) {
						continue;
					}
					$seen[ $id ] = true;

					$before      = isset( $course->price ) ? (float) $course->price : 0.0;
					$row_data    = $data;
					$row_formats = $formats;

					if ( ! empty( $entry['public_title'] )
						|| ! empty( $entry['content_pending'] )
						|| ! empty( $entry['launch_pending_testing'] )
						|| ! empty( $entry['commercial_pending'] ) ) {
						$meta = array();
						if ( ! empty( $course->syllabus_meta ) ) {
							$decoded = json_decode( (string) $course->syllabus_meta, true );
							$meta    = is_array( $decoded ) ? $decoded : array();
						}
						$meta['course_classification'] = 'Exam Preparation Only — No CE Credit';
						if ( ! empty( $entry['public_title'] ) ) {
							$meta['public_title'] = sanitize_text_field( (string) $entry['public_title'] );
						}
						if ( ! empty( $entry['content_pending'] ) || ! empty( $entry['launch_pending_testing'] ) ) {
							$meta['development_draft'] = true;
						}
						if ( ! empty( $entry['launch_pending_testing'] ) ) {
							$meta['launch_pending_testing'] = true;
							$meta['launch_status']          = 'draft_pending_testing';
						}
						if ( ! empty( $entry['content_pending'] ) ) {
							$meta['content_pending'] = true;
						}
						if ( ! empty( $entry['commercial_pending'] ) ) {
							$meta['commercial_pending'] = true;
							$meta['pricing_status']    = 'pending_client_confirmation';
						}
						$row_data['syllabus_meta'] = wp_json_encode( $meta );
						$row_formats[]             = '%s';
					}

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$table,
						$row_data,
						array( 'id' => $id ),
						$row_formats,
						array( '%d' )
					);

					$row = array(
						'id'           => $id,
						'title'        => $title,
						'price'        => $price,
						'price_before' => $before,
						'price_after'  => $price,
					);
					$report['updated'][] = $row;
					if ( ! self::prices_equal( $before, $price ) ) {
						$report['corrected'][] = $row;
					}
				}
			} else {
				$report['missing'][] = $title;
			}
		}

		return $report;
	}

	/**
	 * Audit modules + quiz presence for catalog CE courses.
	 *
	 * @return array
	 */
	public static function audit_ce_content() {
		global $wpdb;

		$modules_table = $wpdb->prefix . 'cta_course_modules';
		$quizzes_table = $wpdb->prefix . 'cta_quizzes';
		$report        = array();

		foreach ( self::get_ce_catalog() as $entry ) {
			$course = self::find_ce_course( $entry );
			$row    = array(
				'title'           => $entry['title'],
				'found'           => false,
				'course_id'       => 0,
				'price'           => null,
				'category'        => null,
				'ce_hours'        => null,
				'modules_count'   => 0,
				'has_quiz'        => false,
				'quiz_questions'  => 0,
				'status'          => '',
			);

			if ( ! $course ) {
				$report[] = $row;
				continue;
			}

			$course_id = (int) $course->id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$modules_count = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$modules_table} WHERE course_id = %d", $course_id )
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$quiz = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM {$quizzes_table} WHERE course_id = %d AND status = 'active' LIMIT 1",
					$course_id
				)
			);

			$quiz_questions = 0;
			if ( $quiz ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$quiz_questions = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}cta_quiz_questions WHERE quiz_id = %d",
						(int) $quiz->id
					)
				);
			}

			$row['found']          = true;
			$row['course_id']      = $course_id;
			$row['price']          = (float) $course->price;
			$row['category']       = (string) ( $course->category ?? '' );
			$row['ce_hours']       = (float) $course->ce_hours;
			$row['modules_count']  = $modules_count;
			$row['has_quiz']       = (bool) $quiz;
			$row['quiz_questions'] = $quiz_questions;
			$row['status']         = (string) ( $course->status ?? '' );
			$report[]              = $row;
		}

		return $report;
	}

	/**
	 * Force every CE course to draft pending CAMFT CEPA provider approval.
	 *
	 * Does not touch Exam Preparation programs. Idempotent.
	 *
	 * @return array{updated:array<int,array{id:int,title:string,previous:string}>,already_draft:array<int,array{id:int,title:string}>,exam_prep_untouched:int}
	 */
	public static function unpublish_all_ce_courses_pending_cepa() {
		global $wpdb;

		$table  = $wpdb->prefix . 'cta_courses';
		$report = array(
			'updated'             => array(),
			'already_draft'       => array(),
			'exam_prep_untouched' => 0,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT id, title, status, product_type FROM {$table} ORDER BY id ASC" );

		foreach ( (array) $rows as $row ) {
			$is_exam = class_exists( 'CTA_Exam_Access' )
				? CTA_Exam_Access::is_exam_prep( $row )
				: ( 'exam_prep' === (string) $row->product_type );

			if ( $is_exam ) {
				++$report['exam_prep_untouched'];
				continue;
			}

			// Treat blank/legacy product_type as CE.
			$title = (string) $row->title;
			$status = (string) $row->status;

			if ( 'draft' === $status ) {
				$report['already_draft'][] = array(
					'id'    => (int) $row->id,
					'title' => $title,
				);
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update(
				$table,
				array( 'status' => 'draft' ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( false !== $ok ) {
				$report['updated'][] = array(
					'id'       => (int) $row->id,
					'title'    => $title,
					'previous' => $status,
				);
			}
		}

		update_option( 'cta_ce_courses_forced_draft_cepa', wp_json_encode( $report ), false );
		update_option( 'cta_ce_publish_confirm_required', 1, false );

		return $report;
	}

	/**
	 * Force every Exam Preparation program to Draft / unpublished.
	 *
	 * Release gate: Exam Prep must remain unavailable for public purchase until
	 * (1) final learner testing is completed and verified, AND
	 * (2) written approval from CTA (the client) has been received.
	 * Applies to all current and future exam_prep rows. Idempotent.
	 *
	 * @return array{updated:array<int,array{id:int,title:string,previous:string}>,already_draft:array<int,array{id:int,title:string}>,ce_untouched:int}
	 */
	public static function unpublish_all_exam_prep_pending_launch() {
		global $wpdb;

		$table  = $wpdb->prefix . 'cta_courses';
		$report = array(
			'updated'       => array(),
			'already_draft' => array(),
			'ce_untouched'  => 0,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT id, title, status, product_type, syllabus_meta FROM {$table} ORDER BY id ASC" );

		foreach ( (array) $rows as $row ) {
			$is_exam = class_exists( 'CTA_Exam_Access' )
				? CTA_Exam_Access::is_exam_prep( $row )
				: ( 'exam_prep' === (string) $row->product_type );

			if ( ! $is_exam ) {
				++$report['ce_untouched'];
				continue;
			}

			$title  = (string) $row->title;
			$status = (string) $row->status;
			$meta   = array();
			if ( ! empty( $row->syllabus_meta ) ) {
				$decoded = json_decode( (string) $row->syllabus_meta, true );
				$meta    = is_array( $decoded ) ? $decoded : array();
			}
			$meta['course_classification']  = 'Exam Preparation Only — No CE Credit';
			$meta['launch_pending_testing'] = true;
			$meta['launch_status']          = 'draft_pending_testing';
			$meta['development_draft']      = true;

			$data    = array(
				'status'        => 'draft',
				'syllabus_meta' => wp_json_encode( $meta ),
			);
			$formats = array( '%s', '%s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update(
				$table,
				$data,
				array( 'id' => (int) $row->id ),
				$formats,
				array( '%d' )
			);

			if ( false !== $ok ) {
				if ( 'draft' === $status ) {
					$report['already_draft'][] = array(
						'id'    => (int) $row->id,
						'title' => $title,
					);
				} else {
					$report['updated'][] = array(
						'id'       => (int) $row->id,
						'title'    => $title,
						'previous' => $status,
					);
				}
			}
		}

		update_option( 'cta_exam_prep_forced_draft_launch_gate', wp_json_encode( $report ), false );
		update_option( 'cta_exam_prep_publish_confirm_required', 1, false );

		return $report;
	}

	/**
	 * Run full commercial restore + return combined report.
	 *
	 * @return array
	 */
	public static function restore_all() {
		CTA_Database::ensure_tables();
		CTA_Database::maybe_add_exam_prep_columns();

		$price = self::sync_approved_prices();
		$ce    = self::restore_ce_pricing();
		$exam  = self::restore_exam_prep_pricing();
		$audit = self::audit_ce_content();

		$report = array(
			'price_sync' => $price,
			'ce'         => $ce,
			'exam_prep'  => $exam,
			'audit'      => $audit,
			'restored_at'=> gmdate( 'c' ),
		);

		update_option( 'cta_course_catalog_restore_1_0_78', wp_json_encode( $report ), false );

		return $report;
	}
}
}
