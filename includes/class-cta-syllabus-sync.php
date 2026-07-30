<?php
	/**
	 * Sync client syllabus content into CTA courses/modules.
	 *
	 * Matches existing courses by title; creates a course only when no match exists.
	 * Never deletes courses/modules. Preserves enrollments, pricing, videos,
	 * quizzes, certificates, and resources on existing rows.
	 *
	 * @package CTA_LMS
	 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Syllabus_Sync
 */
if ( ! class_exists( 'CTA_Syllabus_Sync' ) ) {

class CTA_Syllabus_Sync {

	const OPTION_BACKUP   = 'cta_syllabus_backup_1_0_75';
	const OPTION_SYNCED   = 'cta_syllabus_synced_1_0_75';
	const DATA_FILE       = 'syllabus/cta-syllabus-data.php';

	/**
	 * Load syllabus definitions.
	 *
	 * @return array
	 */
	public static function get_definitions() {
		$path = CTA_PLUGIN_DIR . 'includes/' . self::DATA_FILE;
		if ( ! file_exists( $path ) ) {
			return array();
		}

		$data = include $path;
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Decode syllabus_meta JSON from a course row.
	 *
	 * @param object|null $course Course row.
	 * @return array
	 */
	public static function get_meta( $course ) {
		if ( ! $course || empty( $course->syllabus_meta ) ) {
			return array();
		}

		$decoded = json_decode( (string) $course->syllabus_meta, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Format course description HTML from a string or paragraph array.
	 *
	 * @param mixed $description String, newline-separated text, or paragraph list.
	 * @return string
	 */
	public static function format_course_description_html( $description ) {
		$paras = array();

		if ( is_array( $description ) ) {
			foreach ( $description as $part ) {
				$part = trim( wp_strip_all_tags( (string) $part ) );
				if ( '' !== $part ) {
					$paras[] = $part;
				}
			}
		} else {
			$text = trim( (string) $description );
			if ( '' !== $text ) {
				$chunks = preg_split( '/\n\s*\n/', $text );
				if ( ! is_array( $chunks ) || count( $chunks ) < 2 ) {
					$paras[] = trim( wp_strip_all_tags( $text ) );
				} else {
					foreach ( $chunks as $chunk ) {
						$chunk = trim( wp_strip_all_tags( (string) $chunk ) );
						if ( '' !== $chunk ) {
							$paras[] = $chunk;
						}
					}
				}
			}
		}

		$html = '';
		foreach ( $paras as $para ) {
			$html .= '<p>' . esc_html( $para ) . '</p>';
		}

		return wp_kses_post( $html );
	}

	/**
	 * Format module summary points for the description field.
	 *
	 * @param array $points Bullet points.
	 * @return string
	 */
	public static function format_module_description( array $points ) {
		$lines = array( 'Summary of Main Points:' );
		foreach ( $points as $point ) {
			$point = trim( (string) $point );
			if ( '' === $point ) {
				continue;
			}
			$lines[] = '• ' . $point;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Render plain-text module description as HTML list (escaped).
	 *
	 * @param string $description Stored description.
	 * @return string
	 */
	public static function render_module_description_html( $description ) {
		$description = trim( (string) $description );
		if ( '' === $description ) {
			return '';
		}

		$lines = preg_split( '/\r\n|\r|\n/', $description );
		$bullets = array();
		$intro   = '';

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^[•\-\*]\s*(.+)$/u', $line, $m ) ) {
				$bullets[] = $m[1];
			} elseif ( 0 === stripos( $line, 'Summary of Main Points' ) ) {
				$intro = $line;
			} else {
				$bullets[] = $line;
			}
		}

		$html = '';
		if ( $intro ) {
			$html .= '<p class="course-module-list__summary-label"><strong>' . esc_html( rtrim( $intro, ':' ) ) . '</strong></p>';
		}
		if ( ! empty( $bullets ) ) {
			$html .= '<ul class="course-module-list__summary">';
			foreach ( $bullets as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}
			$html .= '</ul>';
		} elseif ( $description ) {
			$html .= '<p class="course-module-list__desc">' . esc_html( $description ) . '</p>';
		}

		return $html;
	}

	/**
	 * Run one-time syllabus sync (idempotent via option flag; re-runnable by deleting option).
	 *
	 * @param bool $force Force re-sync even if already marked complete.
	 * @return array Report: courses updated, modules created/updated, missing, backup_key.
	 */
	public static function sync_all( $force = false ) {
		if ( ! $force && get_option( self::OPTION_SYNCED ) ) {
			return array(
				'skipped' => true,
				'reason'  => 'already_synced',
			);
		}

		if ( ! class_exists( 'CTA_Database' ) ) {
			return array( 'error' => 'database_unavailable' );
		}

		CTA_Database::maybe_add_syllabus_columns();

		$definitions = self::get_definitions();
		if ( empty( $definitions ) ) {
			return array( 'error' => 'no_definitions' );
		}

		$backup = self::backup_matching_courses( $definitions );
		update_option( self::OPTION_BACKUP, $backup, false );

		$report = array(
			'courses_updated'   => array(),
			'courses_created'   => array(),
			'courses_missing'   => array(),
			'modules_updated'   => 0,
			'modules_created'   => 0,
			'backup_option'     => self::OPTION_BACKUP,
			'notes'             => array(),
		);

		foreach ( $definitions as $syllabus ) {
			$result = self::sync_one( $syllabus );
			if ( empty( $result['course_id'] ) ) {
				$report['courses_missing'][] = $syllabus['title'] ?? 'Unknown';
				continue;
			}

			$entry = array(
				'id'                => $result['course_id'],
				'title'             => $result['title'],
				'modules_updated'   => $result['modules_updated'],
				'modules_created'   => $result['modules_created'],
				'development_draft' => ! empty( $syllabus['development_draft'] ),
				'created'           => ! empty( $result['created'] ),
			);

			if ( ! empty( $result['created'] ) ) {
				$report['courses_created'][] = $entry;
			} else {
				$report['courses_updated'][] = $entry;
			}

			$report['modules_updated'] += (int) $result['modules_updated'];
			$report['modules_created'] += (int) $result['modules_created'];

			if ( ! empty( $syllabus['development_draft'] ) ) {
				$report['notes'][] = 'Alcoholism syllabus marked development_draft (internal only): 15 modules seeded with summary points; full instructional content still required before treating CE award as final.';
			}
		}

		update_option( self::OPTION_SYNCED, wp_json_encode( $report ), false );

		return $report;
	}

	/**
	 * Snapshot matching courses/modules before mutation.
	 *
	 * @param array $definitions Syllabus defs.
	 * @return array
	 */
	private static function backup_matching_courses( array $definitions ) {
		global $wpdb;

		$snapshot = array(
			'time'    => gmdate( 'c' ),
			'courses' => array(),
		);

		$table = $wpdb->prefix . 'cta_courses';

		foreach ( $definitions as $syllabus ) {
			$course = self::find_course( $syllabus );
			if ( ! $course ) {
				continue;
			}

			$modules = CTA_Database::get_course_modules( (int) $course->id );
			$snapshot['courses'][] = array(
				'id'                  => (int) $course->id,
				'title'               => (string) $course->title,
				'description'         => (string) $course->description,
				'ce_hours'            => (float) $course->ce_hours,
				'category'            => (string) ( $course->category ?? '' ),
				'learning_objectives' => (string) ( $course->learning_objectives ?? '' ),
				'syllabus_meta'       => (string) ( $course->syllabus_meta ?? '' ),
				'modules'             => array_map(
					static function ( $m ) {
						return array(
							'id'            => (int) $m->id,
							'title'         => (string) $m->title,
							'description'   => (string) $m->description,
							'duration_mins' => (int) $m->duration_mins,
							'order_index'   => (int) $m->order_index,
							'video_url'     => (string) ( $m->video_url ?? '' ),
						);
					},
					$modules
				),
			);
		}

		return $snapshot;
	}

	/**
	 * Find an existing course by match_titles (never creates a new course).
	 *
	 * @param array $syllabus Syllabus def.
	 * @return object|null
	 */
	private static function find_course( array $syllabus ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';
		$matches = isset( $syllabus['match_titles'] ) ? (array) $syllabus['match_titles'] : array();
		if ( empty( $matches ) && ! empty( $syllabus['title'] ) ) {
			$matches = array( $syllabus['title'] );
		}

		foreach ( $matches as $needle ) {
			$needle = trim( (string) $needle );
			if ( '' === $needle ) {
				continue;
			}

			// Exact title first.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$course = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE title = %s AND (product_type = 'ce' OR product_type = '' OR product_type IS NULL) ORDER BY id ASC LIMIT 1",
					$needle
				)
			);
			if ( $course ) {
				return $course;
			}

			// Partial / LIKE match.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$course = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE title LIKE %s
					AND (product_type = 'ce' OR product_type = '' OR product_type IS NULL)
					ORDER BY id ASC
					LIMIT 1",
					'%' . $wpdb->esc_like( $needle ) . '%'
				)
			);
			if ( $course ) {
				return $course;
			}
		}

		return null;
	}

	/**
	 * Build a unique course slug from a title.
	 *
	 * @param string $title Course title.
	 * @return string
	 */
	/**
	 * Build a unique course slug from a preferred base (title or explicit slug).
	 *
	 * @param string   $preferred Preferred slug or title.
	 * @param int|null $exclude_id Course ID to exclude from uniqueness check.
	 * @return string
	 */
	private static function unique_slug( $preferred, $exclude_id = null ) {
		global $wpdb;

		$base = sanitize_title( (string) $preferred );
		if ( '' === $base ) {
			$base = 'cta-course';
		}

		$table = $wpdb->prefix . 'cta_courses';
		$slug  = $base;
		$i     = 2;
		$exclude_id = absint( $exclude_id );

		while ( true ) {
			if ( $exclude_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1",
						$slug,
						$exclude_id
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
						$slug
					)
				);
			}

			if ( ! $exists ) {
				return $slug;
			}

			$slug = $base . '-' . $i;
			++$i;

			if ( $i > 200 ) {
				return $base . '-' . wp_generate_password( 6, false, false );
			}
		}
	}

	/**
	 * Sanitize a string list for syllabus_meta.
	 *
	 * @param array $items Raw items.
	 * @return array
	 */
	private static function sanitize_string_list( $items ) {
		$out = array();
		foreach ( (array) $items as $item ) {
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_values( $out );
	}

	/**
	 * Create a new CE course from a syllabus definition (only when no match exists).
	 *
	 * Does not touch enrollments/payments. Uses syllabus price when provided.
	 *
	 * @param array $syllabus Syllabus definition.
	 * @return object|null Course row.
	 */
	private static function create_course( array $syllabus ) {
		global $wpdb;

		$title = sanitize_text_field( (string) ( $syllabus['title'] ?? '' ) );
		if ( '' === $title ) {
			return null;
		}

		// Final guard against race / duplicate title.
		$existing = self::find_course( $syllabus );
		if ( $existing ) {
			return $existing;
		}

		$table = $wpdb->prefix . 'cta_courses';
		$slug_source = ! empty( $syllabus['slug'] ) ? (string) $syllabus['slug'] : $title;
		$slug  = self::unique_slug( $slug_source );
		$ce    = isset( $syllabus['ce_hours'] ) ? (float) $syllabus['ce_hours'] : 0.0;

		$description = ! empty( $syllabus['description'] )
			? self::format_course_description_html( $syllabus['description'] )
			: '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert(
			$table,
			array(
				'title'                => $title,
				'slug'                 => $slug,
				'description'          => $description,
				'ce_hours'             => $ce,
				'price'                => isset( $syllabus['price'] ) ? (float) $syllabus['price'] : 0.00,
				'category'             => sanitize_text_field( (string) ( $syllabus['category'] ?? '' ) ),
				'learning_objectives'  => '[]',
				'syllabus_meta'        => '',
				'modules_count'        => 0,
				'status'               => 'published',
				'product_type'         => 'ce',
				// Default only for brand-new rows; existing courses keep their owner-set access period.
				'access_period_months' => 6,
				'awards_ce_hours'      => 1,
				'has_ce_certificate'   => 1,
			),
			array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d' )
		);

		if ( ! $inserted ) {
			return null;
		}

		return CTA_Database::get_course( (int) $wpdb->insert_id );
	}

	/**
	 * Sync one syllabus into its matching course (create only if missing).
	 *
	 * @param array $syllabus Syllabus definition.
	 * @return array
	 */
	private static function sync_one( array $syllabus ) {
		global $wpdb;

		$created = false;
		$course  = self::find_course( $syllabus );

		if ( ! $course ) {
			$course = self::create_course( $syllabus );
			$created = (bool) $course;
		}

		if ( ! $course ) {
			return array( 'course_id' => 0 );
		}

		$course_id = (int) $course->id;
		$table     = $wpdb->prefix . 'cta_courses';

		$meta = array(
			'course_level'            => sanitize_text_field( (string) ( $syllabus['course_level'] ?? '' ) ),
			'target_audience'         => sanitize_text_field( (string) ( $syllabus['target_audience'] ?? '' ) ),
			'instructional_method'    => sanitize_text_field( (string) ( $syllabus['instructional_method'] ?? '' ) ),
			'presenter'               => sanitize_text_field( (string) ( $syllabus['presenter'] ?? '' ) ),
			'course_code'             => sanitize_text_field( (string) ( $syllabus['course_code'] ?? '' ) ),
			'course_classification'   => sanitize_text_field( (string) ( $syllabus['course_classification'] ?? '' ) ),
			'short_description'       => sanitize_textarea_field( (string) ( $syllabus['short_description'] ?? '' ) ),
			'educational_notice'      => sanitize_textarea_field( (string) ( $syllabus['educational_notice'] ?? '' ) ),
			'seo_title'               => sanitize_text_field( (string) ( $syllabus['seo_title'] ?? '' ) ),
			'meta_description'        => sanitize_text_field( (string) ( $syllabus['meta_description'] ?? '' ) ),
			'image_alt'               => sanitize_text_field( (string) ( $syllabus['image_alt'] ?? '' ) ),
			'educational_goals'       => self::sanitize_string_list( $syllabus['educational_goals'] ?? array() ),
			'key_topics'              => self::sanitize_string_list( $syllabus['key_topics'] ?? array() ),
			'completion_requirements' => self::sanitize_string_list( $syllabus['completion_requirements'] ?? array() ),
			'references'              => self::sanitize_string_list( $syllabus['references'] ?? array() ),
			'attestation_required'    => true,
			'development_draft'       => ! empty( $syllabus['development_draft'] ),
			'syllabus_synced_at'      => gmdate( 'c' ),
		);

		$objectives = array();
		foreach ( (array) ( $syllabus['learning_objectives'] ?? array() ) as $objective ) {
			$objective = sanitize_text_field( (string) $objective );
			if ( '' !== $objective ) {
				$objectives[] = $objective;
			}
		}

		$intended_ce               = isset( $syllabus['ce_hours'] ) ? (float) $syllabus['ce_hours'] : (float) $course->ce_hours;
		$meta['intended_ce_hours'] = $intended_ce;

		$description = ! empty( $syllabus['description'] )
			? self::format_course_description_html( $syllabus['description'] )
			: (string) $course->description;

		$data = array(
			'title'               => sanitize_text_field( (string) ( $syllabus['title'] ?? $course->title ) ),
			'description'         => $description,
			'learning_objectives' => wp_json_encode( $objectives ),
			'ce_hours'            => $intended_ce,
			'syllabus_meta'       => wp_json_encode( $meta ),
		);

		// Recommended public slug — never touches access_period_months or thumbnail_url.
		if ( ! empty( $syllabus['slug'] ) ) {
			$data['slug'] = self::unique_slug( (string) $syllabus['slug'], $course_id );
		}

		if ( ! empty( $syllabus['category'] ) ) {
			$data['category'] = sanitize_text_field( (string) $syllabus['category'] );
		}

		// Apply catalog price when provided. Never overwrite an existing price with 0.
		if ( isset( $syllabus['price'] ) && is_numeric( $syllabus['price'] ) ) {
			$syllabus_price = (float) $syllabus['price'];
			$existing_price = isset( $course->price ) ? (float) $course->price : 0.0;
			if ( $syllabus_price > 0 || $existing_price <= 0 ) {
				$data['price'] = $syllabus_price;
			}
		}

		$formats = array();
		foreach ( array_keys( $data ) as $key ) {
			if ( 'ce_hours' === $key || 'price' === $key ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, $data, array( 'id' => $course_id ), $formats, array( '%d' ) );

		$mod_result = self::sync_modules( $course_id, (array) ( $syllabus['modules'] ?? array() ) );

		// Keep course-specific evaluation LO + CAMFT questions aligned after syllabus upsert.
		if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
			CTA_Evaluation_Questions::sync_learning_objective_questions( $course_id );
			CTA_Evaluation_Questions::copy_camft_templates_to_course( $course_id );
		}

		return array(
			'course_id'       => $course_id,
			'title'           => $data['title'],
			'modules_updated' => $mod_result['updated'],
			'modules_created' => $mod_result['created'],
			'created'         => $created,
		);
	}

	/**
	 * Upsert modules by exact title. Never deletes existing modules.
	 *
	 * Missing syllabus titles are created (so a newly approved Module 1 is
	 * inserted instead of overwriting an existing module by position).
	 * Matched titles keep video_url / is_locked and receive updated copy/order.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $modules   Syllabus modules.
	 * @return array{updated:int,created:int}
	 */
	private static function sync_modules( $course_id, array $modules ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$table     = $wpdb->prefix . 'cta_course_modules';
		$existing  = CTA_Database::get_course_modules( $course_id );
		$by_title  = array();

		foreach ( $existing as $row ) {
			$title_key = strtolower( trim( (string) $row->title ) );
			if ( '' !== $title_key && ! isset( $by_title[ $title_key ] ) ) {
				$by_title[ $title_key ] = $row;
			}
		}

		$used_ids = array();
		$updated  = 0;
		$created  = 0;

		foreach ( array_values( $modules ) as $i => $mod ) {
			$title  = sanitize_text_field( (string) ( $mod['title'] ?? '' ) );
			$mins   = absint( $mod['duration_mins'] ?? 60 );
			$points = isset( $mod['summary_points'] ) ? (array) $mod['summary_points'] : array();
			$desc   = self::format_module_description(
				array_map( 'sanitize_text_field', $points )
			);
			$order  = $i + 1;

			if ( '' === $title ) {
				continue;
			}

			$title_key = strtolower( trim( $title ) );
			$target    = null;

			// Exact title match only — never remap by position when inserting a new module.
			if ( isset( $by_title[ $title_key ] ) && ! isset( $used_ids[ (int) $by_title[ $title_key ]->id ] ) ) {
				$target = $by_title[ $title_key ];
			}

			if ( $target ) {
				$used_ids[ (int) $target->id ] = true;

				// Preserve video_url and is_locked; update title/description/duration/order.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array(
						'title'         => $title,
						'description'   => $desc,
						'duration_mins' => $mins,
						'order_index'   => $order,
					),
					array( 'id' => (int) $target->id ),
					array( '%s', '%s', '%d', '%d' ),
					array( '%d' )
				);
				++$updated;
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert(
					$table,
					array(
						'course_id'     => $course_id,
						'title'         => $title,
						'description'   => $desc,
						'duration_mins' => $mins,
						'order_index'   => $order,
						'is_locked'     => 1,
						'video_url'     => '',
					),
					array( '%d', '%s', '%s', '%d', '%d', '%d', '%s' )
				);
				++$created;
			}
		}

		$module_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE course_id = %d",
				$course_id
			)
		);

		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'modules_count' => $module_count ),
			array( 'id' => $course_id ),
			array( '%d' ),
			array( '%d' )
		);

		return array(
			'updated' => $updated,
			'created' => $created,
		);
	}
}
}
