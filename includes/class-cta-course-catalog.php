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
				'price'        => 89.99,
				'category'     => 'Law & Ethics',
			),
			array(
				'match_titles' => array(
					'Clinical and Ethical Excellence in Telehealth: The Essential California Framework',
					'Clinical and Ethical Excellence in Telehealth',
				),
				'title'        => 'Clinical and Ethical Excellence in Telehealth: The Essential California Framework',
				'ce_hours'     => 3.0,
				'price'        => 44.98,
				'category'     => 'Clinical Skills',
			),
			array(
				'match_titles' => array(
					'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
					'Advanced Suicide Risk Assessment',
				),
				'title'        => 'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
				'ce_hours'     => 6.0,
				'price'        => 90.00,
				'category'     => 'Law & Ethics',
			),
			array(
				'match_titles' => array(
					'Alcoholism & Other Chemical Substance Dependency: Assessment, Treatment, Recovery, & Clinical Practice',
					'Alcoholism & Other Chemical Substance Dependency',
				),
				'title'        => 'Alcoholism & Other Chemical Substance Dependency: Assessment, Treatment, Recovery, & Clinical Practice',
				'ce_hours'     => 15.0,
				'price'        => 225.00,
				'category'     => 'Law & Ethics',
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
				'price'        => 104.98,
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
				'ce_hours'     => 6.0,
				'price'        => 89.99,
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
				'title'                => 'California Law & Ethics Exam Preparation',
				'slug'                 => 'california-law-ethics-exam-preparation',
				'price'                => 199.00,
				'access_period_months' => 6,
				'category'             => 'Exam Preparation',
			),
			array(
				'title'                => 'LMFT California Clinical Exam Preparation',
				'slug'                 => 'lmft-california-clinical-exam-preparation',
				'price'                => 249.00,
				'access_period_months' => 6,
				'category'             => 'Exam Preparation',
			),
			array(
				'title'                => 'LCSW California Clinical Exam Preparation',
				'slug'                 => 'lcsw-california-clinical-exam-preparation',
				'price'                => 249.00,
				'access_period_months' => 6,
				'category'             => 'Exam Preparation',
			),
			array(
				'title'                => 'LPCC California Clinical Exam Preparation',
				'slug'                 => 'lpcc-california-clinical-exam-preparation',
				'price'                => 249.00,
				'access_period_months' => 6,
				'category'             => 'Exam Preparation',
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

		update_option( 'cta_approved_price_sync_1_0_86', wp_json_encode( $report ), false );

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
					$before = isset( $course->price ) ? (float) $course->price : 0.0;

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
						'id'           => (int) $course->id,
						'title'        => $title,
						'price'        => $price,
						'price_before' => $before,
						'price_after'  => $price,
						'category'     => $cat,
						'ce_hours'     => $ce,
					);
					$report['updated'][] = $row;
					if ( ! self::prices_equal( $before, $price ) ) {
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
					'status'               => 'published',
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

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$courses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE slug = %s OR title = %s ORDER BY id ASC",
					$slug,
					$title
				)
			);

			$data = array(
				'title'                => $title,
				'price'                => $price,
				'category'             => sanitize_text_field( (string) $entry['category'] ),
				'access_period_months' => absint( $entry['access_period_months'] ),
				'ce_hours'             => 0,
				'product_type'         => 'exam_prep',
				'awards_ce_hours'      => 0,
				'has_ce_certificate'   => 0,
			);

			if ( ! empty( $courses ) ) {
				$seen = array();
				foreach ( $courses as $course ) {
					$id = (int) $course->id;
					if ( isset( $seen[ $id ] ) ) {
						continue;
					}
					$seen[ $id ] = true;

					$before = isset( $course->price ) ? (float) $course->price : 0.0;

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$table,
						$data,
						array( 'id' => $id ),
						array( '%s', '%f', '%s', '%d', '%f', '%s', '%d', '%d' ),
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
