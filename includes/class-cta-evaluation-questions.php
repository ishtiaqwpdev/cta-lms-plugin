<?php
/**
 * Admin-configurable CE course evaluation questions (per-course + shared templates).
 *
 * Question definitions live in cta_evaluation_questions. course_id = 0 holds shared
 * CAMFT template library rows; each course gets its own copied/synced questions.
 * Student submissions remain in cta_evaluations.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Evaluation_Questions
 */
if ( ! class_exists( 'CTA_Evaluation_Questions' ) ) {

class CTA_Evaluation_Questions {

	const TABLE = 'cta_evaluation_questions';

	/**
	 * Supported question types for the admin UI / student form.
	 *
	 * @return array type => label
	 */
	public static function get_types() {
		return array(
			'rating'     => __( 'Rating Scale (1–5)', 'cta-lms' ),
			'radio'      => __( 'Radio Button', 'cta-lms' ),
			'checkbox'   => __( 'Checkbox', 'cta-lms' ),
			'short_text' => __( 'Short Text', 'cta-lms' ),
			'paragraph'  => __( 'Paragraph', 'cta-lms' ),
			'dropdown'   => __( 'Dropdown', 'cta-lms' ),
		);
	}

	/**
	 * Default Likert options for rating questions.
	 *
	 * @return array
	 */
	public static function default_rating_options() {
		return array(
			'1' => __( '1 — Strongly Disagree', 'cta-lms' ),
			'2' => __( '2 — Disagree', 'cta-lms' ),
			'3' => __( '3 — Neutral', 'cta-lms' ),
			'4' => __( '4 — Agree', 'cta-lms' ),
			'5' => __( '5 — Strongly Agree', 'cta-lms' ),
		);
	}

	/**
	 * Rating options for learning-objective evaluation questions.
	 *
	 * @return array
	 */
	public static function default_objective_rating_options() {
		return array(
			'5' => __( 'Excellent', 'cta-lms' ),
			'4' => __( 'Very Good', 'cta-lms' ),
			'3' => __( 'Good', 'cta-lms' ),
			'2' => __( 'Fair', 'cta-lms' ),
			'1' => __( 'Poor', 'cta-lms' ),
		);
	}

	/**
	 * Table name with prefix.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create / migrate the questions table and seed shared templates when empty.
	 */
	public static function install() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  course_id bigint(20) unsigned NOT NULL DEFAULT 0,
  question_key varchar(100) NOT NULL,
  section_label varchar(255) NOT NULL DEFAULT '',
  label text NOT NULL,
  question_type varchar(40) NOT NULL DEFAULT 'rating',
  options_json longtext,
  is_required tinyint(1) NOT NULL DEFAULT 1,
  summary_field varchar(50) NOT NULL DEFAULT '',
  order_index int(11) NOT NULL DEFAULT 0,
  source_type varchar(40) NOT NULL DEFAULT 'custom',
  objective_index int(11) NOT NULL DEFAULT -1,
  status varchar(20) NOT NULL DEFAULT 'active',
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY course_question (course_id, question_key),
  KEY status_order (status, order_index),
  KEY course_status (course_id, status, order_index)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::maybe_migrate();
		self::seed_defaults_if_empty();
	}

	/**
	 * Migrate legacy schema (columns + composite unique key).
	 */
	public static function maybe_migrate() {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		$columns = array(
			'course_id'       => "ALTER TABLE {$table} ADD COLUMN course_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER id",
			'source_type'     => "ALTER TABLE {$table} ADD COLUMN source_type varchar(40) NOT NULL DEFAULT 'custom' AFTER order_index",
			'objective_index' => "ALTER TABLE {$table} ADD COLUMN objective_index int(11) NOT NULL DEFAULT -1 AFTER source_type",
		);

		foreach ( $columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
			if ( empty( $exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
		$has_old = false;
		$has_new = false;

		foreach ( (array) $indexes as $index ) {
			if ( 'question_key' === $index['Key_name'] && '0' === (string) $index['Seq_in_index'] ) {
				$has_old = true;
			}
			if ( 'course_question' === $index['Key_name'] ) {
				$has_new = true;
			}
		}

		if ( $has_old ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} DROP INDEX question_key" );
		}

		if ( ! $has_new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY course_question (course_id, question_key)" );
		}

		// Legacy rows without course_id scope belong to the shared template library.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "UPDATE {$table} SET course_id = 0 WHERE course_id IS NULL OR course_id = 0" );
	}

	/**
	 * Seed CAMFT template questions at course_id = 0 when none exist there.
	 */
	public static function seed_defaults_if_empty() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE course_id = %d",
				0
			)
		);

		if ( $count > 0 ) {
			return;
		}

		$defaults = self::get_camft_template_questions();
		$order    = 100;

		foreach ( $defaults as $row ) {
			self::insert_question(
				array(
					'course_id'       => 0,
					'question_key'    => $row['id'],
					'section_label'   => $row['section'],
					'label'           => $row['label'],
					'question_type'   => self::normalize_type( $row['type'] ),
					'options'         => isset( $row['options'] ) ? $row['options'] : array(),
					'is_required'     => ! empty( $row['required'] ) ? 1 : 0,
					'summary_field'   => isset( $row['summary'] ) ? $row['summary'] : '',
					'order_index'     => $order++,
					'source_type'     => 'camft',
					'objective_index' => -1,
					'status'          => 'active',
				)
			);
		}
	}

	/**
	 * CAMFT-style evaluation template definitions (seed course_id = 0 library).
	 *
	 * @return array
	 */
	public static function get_camft_template_questions() {
		$likert = self::default_rating_options();

		return array(
			array(
				'id'       => 'content_quality',
				'section'  => __( 'Course Content', 'cta-lms' ),
				'label'    => __( 'How would you rate the overall quality of the course content?', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => 'content_quality',
			),
			array(
				'id'       => 'materials_clarity',
				'section'  => __( 'Course Content', 'cta-lms' ),
				'label'    => __( 'How clear and well organized were the instructional materials?', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => 'rating',
			),
			array(
				'id'       => 'instructor_clarity',
				'section'  => __( 'Instruction', 'cta-lms' ),
				'label'    => __( 'How would you rate the clarity of the instructor / presentation?', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => 'instructor_rating',
			),
			array(
				'id'       => 'would_recommend',
				'section'  => __( 'Overall', 'cta-lms' ),
				'label'    => __( 'Would you recommend this course to a colleague?', 'cta-lms' ),
				'type'     => 'radio',
				'required' => true,
				'options'  => array(
					'yes' => __( 'Yes', 'cta-lms' ),
					'no'  => __( 'No', 'cta-lms' ),
				),
				'summary'  => 'would_recommend',
			),
			array(
				'id'       => 'comments',
				'section'  => __( 'Additional Feedback', 'cta-lms' ),
				'label'    => __( 'Additional comments or suggestions (optional)', 'cta-lms' ),
				'type'     => 'paragraph',
				'required' => false,
				'summary'  => 'comments',
			),
		);
	}

	/**
	 * Backward-compatible alias for CAMFT template definitions.
	 *
	 * @return array
	 */
	public static function get_builtin_defaults() {
		return self::get_camft_template_questions();
	}

	/**
	 * Normalize legacy type names to current admin types.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	public static function normalize_type( $type ) {
		$type = sanitize_key( (string) $type );

		$legacy_map = array(
			'likert'          => 'rating',
			'multiple_choice' => 'radio',
			'textarea'        => 'paragraph',
			'yes_no'          => 'radio',
		);

		if ( isset( $legacy_map[ $type ] ) ) {
			return $legacy_map[ $type ];
		}

		if ( ! isset( self::get_types()[ $type ] ) ) {
			return 'rating';
		}

		return $type;
	}

	/**
	 * Fetch questions for the student form (active only, per course).
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_form_questions( $course_id = 0 ) {
		$course_id = absint( $course_id );
		self::ensure_course_evaluation( $course_id );

		$rows = self::get_questions( 'active', $course_id );

		if ( empty( $rows ) ) {
			return self::rows_to_form_questions( self::get_camft_template_questions() );
		}

		return self::rows_to_form_questions( $rows );
	}

	/**
	 * Ensure a course has evaluation questions (LO sync + CAMFT copy).
	 *
	 * @param int $course_id Course ID.
	 * @return int Total question count for the course after sync.
	 */
	public static function ensure_course_evaluation( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			self::seed_defaults_if_empty();
			return count( self::get_questions( 'all', 0 ) );
		}

		$existing = self::get_questions( 'all', $course_id );
		if ( empty( $existing ) ) {
			self::sync_learning_objective_questions( $course_id );
			self::copy_camft_templates_to_course( $course_id );
		}

		return count( self::get_questions( 'all', $course_id ) );
	}

	/**
	 * Sync learning-objective rating questions for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return int Number of LO questions synced.
	 */
	public static function sync_learning_objective_questions( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		$course = CTA_Database::get_course( $course_id );
		if ( ! $course ) {
			return 0;
		}

		$objectives = array();
		if ( ! empty( $course->learning_objectives ) ) {
			$decoded = json_decode( (string) $course->learning_objectives, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $objective ) {
					$objective = trim( (string) $objective );
					if ( '' !== $objective ) {
						$objectives[] = $objective;
					}
				}
			}
		}

		$existing_lo = self::get_questions_by_source( $course_id, 'learning_objective' );
		$active_keys = array();
		$synced      = 0;
		$lo_options  = self::default_objective_rating_options();
		$section     = __( 'Learning Objectives', 'cta-lms' );

		foreach ( $objectives as $index => $objective ) {
			$key           = 'lo_' . $index;
			$active_keys[] = $key;
			$label         = sprintf(
				/* translators: %s: learning objective text */
				__( "How well did this course help you achieve this learning objective?\n\n%s", 'cta-lms' ),
				$objective
			);

			$existing_row = self::get_question_by_key( $course_id, $key );
			$data         = array(
				'course_id'       => $course_id,
				'question_key'    => $key,
				'section_label'   => $section,
				'label'           => $label,
				'question_type'   => 'rating',
				'options'         => $lo_options,
				'is_required'     => 1,
				'summary_field'   => '',
				'order_index'     => $index,
				'source_type'     => 'learning_objective',
				'objective_index' => (int) $index,
				'status'          => 'active',
			);

			if ( $existing_row ) {
				self::update_question( (int) $existing_row->id, $data );
			} else {
				self::insert_question( $data );
			}
			++$synced;
		}

		foreach ( $existing_lo as $row ) {
			$idx = isset( $row->objective_index ) ? (int) $row->objective_index : -1;
			$key = (string) $row->question_key;
			if ( $idx >= 0 && ! in_array( $key, $active_keys, true ) ) {
				self::update_question(
					(int) $row->id,
					array(
						'status' => 'inactive',
					)
				);
			}
		}

		return $synced;
	}

	/**
	 * Copy shared CAMFT templates (course_id = 0) into a course.
	 *
	 * @param int $course_id Course ID.
	 * @return int Number of questions copied.
	 */
	public static function copy_camft_templates_to_course( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		$existing_camft = self::get_questions_by_source( $course_id, 'camft' );
		if ( ! empty( $existing_camft ) ) {
			return 0;
		}

		self::seed_defaults_if_empty();

		$templates = self::get_questions( 'active', 0 );
		if ( empty( $templates ) ) {
			foreach ( self::get_camft_template_questions() as $tpl ) {
				self::insert_question(
					array(
						'course_id'     => 0,
						'question_key'  => $tpl['id'],
						'section_label' => $tpl['section'],
						'label'         => $tpl['label'],
						'question_type' => self::normalize_type( $tpl['type'] ),
						'options'       => isset( $tpl['options'] ) ? $tpl['options'] : array(),
						'is_required'   => ! empty( $tpl['required'] ) ? 1 : 0,
						'summary_field' => isset( $tpl['summary'] ) ? $tpl['summary'] : '',
						'order_index'   => 100,
						'source_type'   => 'camft',
						'status'        => 'active',
					)
				);
			}
			$templates = self::get_questions( 'active', 0 );
		}

		$lo_count   = count( self::get_questions_by_source( $course_id, 'learning_objective' ) );
		$order_base = max( 100, $lo_count + 10 );
		$copied     = 0;

		foreach ( $templates as $template ) {
			if ( isset( $template->source_type ) && 'camft' !== $template->source_type && 0 !== (int) $template->course_id ) {
				continue;
			}

			$new_key = 'camft_' . $template->question_key;
			if ( self::get_question_by_key( $course_id, $new_key ) ) {
				continue;
			}

			$options = array();
			if ( ! empty( $template->options_json ) ) {
				$decoded = json_decode( (string) $template->options_json, true );
				if ( is_array( $decoded ) ) {
					$options = $decoded;
				}
			}

			self::insert_question(
				array(
					'course_id'       => $course_id,
					'question_key'    => $new_key,
					'section_label'   => $template->section_label,
					'label'           => $template->label,
					'question_type'   => self::normalize_type( $template->question_type ),
					'options'         => $options,
					'is_required'     => (int) $template->is_required,
					'summary_field'   => $template->summary_field,
					'order_index'     => $order_base + (int) $template->order_index,
					'source_type'     => 'camft',
					'objective_index' => -1,
					'status'          => 'active',
				)
			);
			++$copied;
		}

		return $copied;
	}

	/**
	 * Fetch question rows for admin (all statuses or filtered).
	 *
	 * @param string   $status    active|inactive|draft|all.
	 * @param int|null $course_id Optional course filter.
	 * @return array
	 */
	public static function get_questions( $status = 'all', $course_id = null ) {
		global $wpdb;

		$table  = self::table_name();
		$where  = array();
		$values = array();

		if ( null !== $course_id ) {
			$where[]  = 'course_id = %d';
			$values[] = absint( $course_id );
		}

		if ( 'all' !== $status ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $status );
		}

		$sql = "SELECT * FROM {$table}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY order_index ASC, id ASC';

		if ( empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (array) $wpdb->get_results( $sql );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Get questions by source_type for a course.
	 *
	 * @param int    $course_id   Course ID.
	 * @param string $source_type Source type slug.
	 * @return array
	 */
	public static function get_questions_by_source( $course_id, $source_type ) {
		global $wpdb;

		$course_id   = absint( $course_id );
		$source_type = sanitize_key( $source_type );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE course_id = %d AND source_type = %s ORDER BY order_index ASC, id ASC',
				$course_id,
				$source_type
			)
		);
	}

	/**
	 * Get one question by course + key.
	 *
	 * @param int    $course_id    Course ID.
	 * @param string $question_key Question key.
	 * @return object|null
	 */
	public static function get_question_by_key( $course_id, $question_key ) {
		global $wpdb;

		$course_id    = absint( $course_id );
		$question_key = sanitize_key( $question_key );
		if ( '' === $question_key ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE course_id = %d AND question_key = %s LIMIT 1',
				$course_id,
				$question_key
			)
		);
	}

	/**
	 * Get one question by ID.
	 *
	 * @param int $id Question ID.
	 * @return object|null
	 */
	public static function get_question( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE id = %d',
				$id
			)
		);
	}

	/**
	 * Insert a question.
	 *
	 * @param array $data Question fields.
	 * @return int|WP_Error Insert ID or error.
	 */
	public static function insert_question( $data ) {
		global $wpdb;

		$prepared = self::prepare_row_data( $data, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( self::table_name(), $prepared['data'], $prepared['formats'] );

		if ( ! $ok ) {
			return new WP_Error( 'cta_eval_insert', __( 'Could not save evaluation question.', 'cta-lms' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a question.
	 *
	 * @param int   $id   Question ID.
	 * @param array $data Fields.
	 * @return true|WP_Error
	 */
	public static function update_question( $id, $data ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id || ! self::get_question( $id ) ) {
			return new WP_Error( 'cta_eval_missing', __( 'Question not found.', 'cta-lms' ) );
		}

		$prepared = self::prepare_row_data( $data, false );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->update(
			self::table_name(),
			$prepared['data'],
			array( 'id' => $id ),
			$prepared['formats'],
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'cta_eval_update', __( 'Could not update evaluation question.', 'cta-lms' ) );
		}

		return true;
	}

	/**
	 * Delete a question definition (does not affect past submissions).
	 *
	 * @param int $id Question ID.
	 * @return true|WP_Error
	 */
	public static function delete_question( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'cta_eval_missing', __( 'Question not found.', 'cta-lms' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );

		if ( ! $deleted ) {
			return new WP_Error( 'cta_eval_delete', __( 'Could not delete evaluation question.', 'cta-lms' ) );
		}

		return true;
	}

	/**
	 * Reorder questions by ID list, optionally scoped to a course.
	 *
	 * @param array    $ordered_ids Ordered question IDs.
	 * @param int|null $course_id   Optional course scope.
	 */
	public static function reorder( $ordered_ids, $course_id = null ) {
		global $wpdb;

		$table = self::table_name();
		foreach ( array_values( (array) $ordered_ids ) as $index => $qid ) {
			$qid = absint( $qid );
			if ( ! $qid ) {
				continue;
			}

			if ( null !== $course_id ) {
				$row = self::get_question( $qid );
				if ( ! $row || (int) $row->course_id !== absint( $course_id ) ) {
					continue;
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'order_index' => (int) $index ),
				array( 'id' => $qid ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Convert DB rows (or template arrays) into the form question shape.
	 *
	 * @param array $rows DB objects or template arrays.
	 * @return array
	 */
	public static function rows_to_form_questions( $rows ) {
		$out = array();

		foreach ( (array) $rows as $row ) {
			if ( is_array( $row ) ) {
				$type    = self::normalize_type( $row['type'] ?? 'rating' );
				$options = isset( $row['options'] ) && is_array( $row['options'] ) ? $row['options'] : array();
				$out[]   = array(
					'id'          => (string) ( $row['id'] ?? '' ),
					'db_id'       => isset( $row['db_id'] ) ? absint( $row['db_id'] ) : 0,
					'course_id'   => isset( $row['course_id'] ) ? absint( $row['course_id'] ) : 0,
					'source_type' => isset( $row['source_type'] ) ? sanitize_key( $row['source_type'] ) : 'custom',
					'section'     => (string) ( $row['section'] ?? '' ),
					'label'       => (string) ( $row['label'] ?? '' ),
					'type'        => $type,
					'required'    => ! empty( $row['required'] ),
					'options'     => $options ? $options : ( 'rating' === $type ? self::default_rating_options() : array() ),
					'summary'     => (string) ( $row['summary'] ?? '' ),
				);
				continue;
			}

			$type    = self::normalize_type( $row->question_type ?? 'rating' );
			$options = array();
			if ( ! empty( $row->options_json ) ) {
				$decoded = json_decode( (string) $row->options_json, true );
				if ( is_array( $decoded ) ) {
					$options = $decoded;
				}
			}
			if ( 'rating' === $type && empty( $options ) ) {
				if ( isset( $row->source_type ) && 'learning_objective' === $row->source_type ) {
					$options = self::default_objective_rating_options();
				} else {
					$options = self::default_rating_options();
				}
			}

			$out[] = array(
				'id'          => (string) $row->question_key,
				'db_id'       => isset( $row->id ) ? absint( $row->id ) : 0,
				'course_id'   => isset( $row->course_id ) ? absint( $row->course_id ) : 0,
				'source_type' => isset( $row->source_type ) ? sanitize_key( $row->source_type ) : 'custom',
				'section'     => (string) $row->section_label,
				'label'       => (string) $row->label,
				'type'        => $type,
				'required'    => (int) $row->is_required === 1,
				'options'     => $options,
				'summary'     => (string) $row->summary_field,
			);
		}

		return $out;
	}

	/**
	 * Prepare insert/update payload.
	 *
	 * @param array $data   Raw data.
	 * @param bool  $is_new Whether inserting.
	 * @return array|WP_Error { data, formats }
	 */
	private static function prepare_row_data( $data, $is_new ) {
		$label = isset( $data['label'] ) ? sanitize_textarea_field( wp_unslash( $data['label'] ) ) : '';
		if ( '' === trim( $label ) ) {
			return new WP_Error( 'cta_eval_label', __( 'Question label is required.', 'cta-lms' ) );
		}

		$type = self::normalize_type( $data['question_type'] ?? ( $data['type'] ?? 'rating' ) );
		$key  = sanitize_key( $data['question_key'] ?? '' );

		if ( '' === $key ) {
			$key = sanitize_key( substr( md5( $label . microtime( true ) ), 0, 12 ) );
			if ( '' === $key ) {
				$key = 'q_' . wp_generate_password( 8, false, false );
			}
		}

		$options = array();
		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			foreach ( $data['options'] as $opt_key => $opt_label ) {
				$opt_key = sanitize_key( (string) $opt_key );
				if ( '' === $opt_key ) {
					continue;
				}
				$options[ $opt_key ] = sanitize_text_field( (string) $opt_label );
			}
		} elseif ( ! empty( $data['options_text'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $data['options_text'] );
			$i     = 1;
			foreach ( (array) $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				if ( false !== strpos( $line, '|' ) ) {
					list( $opt_key, $opt_label ) = array_map( 'trim', explode( '|', $line, 2 ) );
					$opt_key = sanitize_key( $opt_key );
					if ( $opt_key ) {
						$options[ $opt_key ] = sanitize_text_field( $opt_label );
					}
				} else {
					$options[ (string) $i ] = sanitize_text_field( $line );
					++$i;
				}
			}
		}

		if ( 'rating' === $type && empty( $options ) ) {
			$source = sanitize_key( $data['source_type'] ?? '' );
			$options = ( 'learning_objective' === $source )
				? self::default_objective_rating_options()
				: self::default_rating_options();
		}

		if ( 'checkbox' === $type && count( $options ) < 1 ) {
			return new WP_Error(
				'cta_eval_options',
				__( 'Checkbox questions need at least one option (one per line, or value|Label).', 'cta-lms' )
			);
		}

		if ( in_array( $type, array( 'radio', 'dropdown' ), true ) && count( $options ) < 2 ) {
			return new WP_Error(
				'cta_eval_options',
				__( 'Radio and dropdown questions need at least two options (one per line, or value|Label).', 'cta-lms' )
			);
		}

		$status = sanitize_key( $data['status'] ?? 'active' );
		if ( ! in_array( $status, array( 'active', 'inactive', 'draft' ), true ) ) {
			$status = 'active';
		}

		$summary = sanitize_key( $data['summary_field'] ?? ( $data['summary'] ?? '' ) );
		$allowed_summary = array( '', 'rating', 'content_quality', 'instructor_rating', 'would_recommend', 'comments' );
		if ( ! in_array( $summary, $allowed_summary, true ) ) {
			$summary = '';
		}

		$course_id = isset( $data['course_id'] ) ? absint( $data['course_id'] ) : 0;
		$source_type = sanitize_key( $data['source_type'] ?? 'custom' );
		if ( ! in_array( $source_type, array( 'custom', 'learning_objective', 'camft' ), true ) ) {
			$source_type = 'custom';
		}
		$objective_index = isset( $data['objective_index'] ) ? (int) $data['objective_index'] : -1;

		$row = array(
			'course_id'       => $course_id,
			'question_key'    => $key,
			'section_label'   => sanitize_text_field( $data['section_label'] ?? ( $data['section'] ?? '' ) ),
			'label'           => $label,
			'question_type'   => $type,
			'options_json'    => wp_json_encode( $options ),
			'is_required'     => ! empty( $data['is_required'] ) || ! empty( $data['required'] ) ? 1 : 0,
			'summary_field'   => $summary,
			'order_index'     => isset( $data['order_index'] ) ? absint( $data['order_index'] ) : 0,
			'source_type'     => $source_type,
			'objective_index' => $objective_index,
			'status'          => $status,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s' );

		if ( ! $is_new ) {
			unset( $row['question_key'] );
			array_splice( $formats, 1, 1 );
		}

		return array(
			'data'    => $row,
			'formats' => $formats,
		);
	}
}

}
