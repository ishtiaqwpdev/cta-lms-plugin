<?php
/**
 * Sync client syllabus content into existing CTA courses/modules.
 *
 * Updates by title match only — never creates duplicate courses.
 * Preserves enrollments, pricing, videos, quizzes, certificates, and resources.
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

			$report['courses_updated'][] = array(
				'id'              => $result['course_id'],
				'title'           => $result['title'],
				'modules_updated' => $result['modules_updated'],
				'modules_created' => $result['modules_created'],
				'development_draft' => ! empty( $syllabus['development_draft'] ),
			);
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
	 * Sync one syllabus into its matching course.
	 *
	 * @param array $syllabus Syllabus definition.
	 * @return array
	 */
	private static function sync_one( array $syllabus ) {
		global $wpdb;

		$course = self::find_course( $syllabus );
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
			'educational_goals'       => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $syllabus['educational_goals'] ?? array() ) ) ) ),
			'completion_requirements' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $syllabus['completion_requirements'] ?? array() ) ) ) ),
			'references'              => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $syllabus['references'] ?? array() ) ) ) ),
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
			? wp_kses_post( '<p>' . esc_html( (string) $syllabus['description'] ) . '</p>' )
			: (string) $course->description;

		$data = array(
			'title'               => sanitize_text_field( (string) ( $syllabus['title'] ?? $course->title ) ),
			'description'         => $description,
			'learning_objectives' => wp_json_encode( $objectives ),
			'ce_hours'            => $intended_ce,
			'syllabus_meta'       => wp_json_encode( $meta ),
		);

		if ( ! empty( $syllabus['category'] ) ) {
			$data['category'] = sanitize_text_field( (string) $syllabus['category'] );
		}

		$formats = array();
		foreach ( array_keys( $data ) as $key ) {
			$formats[] = ( 'ce_hours' === $key ) ? '%f' : '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, $data, array( 'id' => $course_id ), $formats, array( '%d' ) );

		$mod_result = self::sync_modules( $course_id, (array) ( $syllabus['modules'] ?? array() ) );

		return array(
			'course_id'       => $course_id,
			'title'           => $data['title'],
			'modules_updated' => $mod_result['updated'],
			'modules_created' => $mod_result['created'],
		);
	}

	/**
	 * Upsert modules by order_index. Never deletes existing modules.
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
		$by_index  = array();

		foreach ( $existing as $row ) {
			$by_index[ (int) $row->order_index ] = $row;
		}

		// If order_index values are sparse/zero-heavy, also map by sequence position.
		$by_position = array_values( $existing );

		$updated = 0;
		$created = 0;

		foreach ( array_values( $modules ) as $i => $mod ) {
			$title   = sanitize_text_field( (string) ( $mod['title'] ?? '' ) );
			$mins    = absint( $mod['duration_mins'] ?? 60 );
			$points  = isset( $mod['summary_points'] ) ? (array) $mod['summary_points'] : array();
			$desc    = self::format_module_description(
				array_map( 'sanitize_text_field', $points )
			);
			$order   = $i + 1;

			if ( '' === $title ) {
				continue;
			}

			$target = null;
			if ( isset( $by_index[ $order ] ) ) {
				$target = $by_index[ $order ];
			} elseif ( isset( $by_index[ $i ] ) ) {
				$target = $by_index[ $i ];
			} elseif ( isset( $by_position[ $i ] ) ) {
				$target = $by_position[ $i ];
			}

			if ( $target ) {
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
