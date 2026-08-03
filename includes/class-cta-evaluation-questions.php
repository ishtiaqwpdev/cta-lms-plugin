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
			'rating'     => __( 'Rating Scale (1–5 + N/A)', 'cta-lms' ),
			'radio'      => __( 'Radio Button', 'cta-lms' ),
			'checkbox'   => __( 'Checkbox', 'cta-lms' ),
			'short_text' => __( 'Short Text', 'cta-lms' ),
			'paragraph'  => __( 'Paragraph', 'cta-lms' ),
			'dropdown'   => __( 'Dropdown', 'cta-lms' ),
			'info'       => __( 'Information (display only)', 'cta-lms' ),
		);
	}

	/**
	 * Default Likert options for rating questions (incl. N/A).
	 *
	 * @return array
	 */
	public static function default_rating_options() {
		return array(
			'1'  => __( '1 — Strongly Disagree', 'cta-lms' ),
			'2'  => __( '2 — Disagree', 'cta-lms' ),
			'3'  => __( '3 — Neutral', 'cta-lms' ),
			'4'  => __( '4 — Agree', 'cta-lms' ),
			'5'  => __( '5 — Strongly Agree', 'cta-lms' ),
			'na' => __( 'N/A — Not Applicable', 'cta-lms' ),
		);
	}

	/**
	 * Rating options for learning-objective evaluation questions.
	 *
	 * Same Agree/Disagree + N/A scale as all other rating items.
	 *
	 * @return array
	 */
	public static function default_objective_rating_options() {
		return self::default_rating_options();
	}

	/**
	 * License/registration type choices for the evaluation participant section.
	 *
	 * @return array value => label
	 */
	public static function license_type_options() {
		$types = function_exists( 'cta_lms_get_license_types' )
			? cta_lms_get_license_types()
			: array( 'LMFT', 'LCSW', 'LPCC', 'LEP', 'AMFT', 'ASW', 'APCC' );

		$options = array();
		foreach ( (array) $types as $type ) {
			$type = (string) $type;
			if ( '' !== $type ) {
				$options[ $type ] = $type;
			}
		}
		$options['Other'] = __( 'Other', 'cta-lms' );
		return $options;
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
			// Library exists but may be a partial older seed — fill any missing keys.
			self::ensure_camft_library_complete();
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
	 * Upsert the full standard evaluation template into the shared library (course_id = 0).
	 *
	 * Also deactivates obsolete library questions that are no longer in the template.
	 *
	 * @return int Number of newly inserted library questions.
	 */
	public static function ensure_camft_library_complete() {
		$inserted     = 0;
		$allowed_keys = array();

		foreach ( self::get_camft_template_questions() as $index => $tpl ) {
			$allowed_keys[] = $tpl['id'];
			$existing       = self::get_question_by_key( 0, $tpl['id'] );
			$data           = array(
				'course_id'       => 0,
				'question_key'    => $tpl['id'],
				'section_label'   => $tpl['section'],
				'label'           => $tpl['label'],
				'question_type'   => self::normalize_type( $tpl['type'] ),
				'options'         => isset( $tpl['options'] ) ? $tpl['options'] : array(),
				'is_required'     => ! empty( $tpl['required'] ) ? 1 : 0,
				'summary_field'   => isset( $tpl['summary'] ) ? $tpl['summary'] : '',
				'order_index'     => 100 + (int) $index,
				'source_type'     => 'camft',
				'objective_index' => -1,
				'status'          => 'active',
			);

			// Keep participant fields at the top of the shared library.
			if ( 0 === strpos( (string) $tpl['id'], 'participant_' ) ) {
				$data['order_index'] = (int) $index;
			}

			if ( $existing ) {
				self::update_question( (int) $existing->id, $data );
			} else {
				self::insert_question( $data );
				++$inserted;
			}
		}

		self::deactivate_obsolete_camft_questions( 0, $allowed_keys );

		return $inserted;
	}

	/**
	 * Deactivate CAMFT/standard questions whose keys are no longer in the template.
	 *
	 * @param int   $course_id    Course ID (0 = shared library).
	 * @param array $allowed_keys Bare template question keys (without camft_ prefix).
	 * @return int Number deactivated.
	 */
	public static function deactivate_obsolete_camft_questions( $course_id, $allowed_keys = null ) {
		$course_id = absint( $course_id );
		if ( null === $allowed_keys ) {
			$allowed_keys = array();
			foreach ( self::get_camft_template_questions() as $tpl ) {
				$allowed_keys[] = $tpl['id'];
			}
		}
		$allowed_keys = array_map( 'strval', (array) $allowed_keys );
		$deactivated  = 0;

		foreach ( self::get_questions( 'active', $course_id ) as $row ) {
			$source = isset( $row->source_type ) ? (string) $row->source_type : '';
			if ( 'camft' !== $source && 0 !== $course_id ) {
				// On courses, only prune camft-sourced rows; library is all template.
				continue;
			}
			if ( 0 === $course_id && 'learning_objective' === $source ) {
				continue;
			}

			$key      = (string) $row->question_key;
			$bare_key = ( 0 === strpos( $key, 'camft_' ) ) ? substr( $key, 6 ) : $key;

			if ( in_array( $bare_key, $allowed_keys, true ) || in_array( $key, $allowed_keys, true ) ) {
				continue;
			}

			// Leave custom (non-camft) course questions alone.
			if ( 0 !== $course_id && 'camft' !== $source ) {
				continue;
			}

			self::update_question(
				(int) $row->id,
				array(
					'status' => 'inactive',
				)
			);
			++$deactivated;
		}

		return $deactivated;
	}

	/**
	 * Standard CE evaluation template (seed course_id = 0 library).
	 *
	 * Section A (learning objectives) is synced per-course from course LO text.
	 * Participant info + Sections B–E live here.
	 *
	 * @return array
	 */
	public static function get_camft_template_questions() {
		$likert        = self::default_rating_options();
		$license_types = self::license_type_options();

		$section_participant = __( 'Participant Information', 'cta-lms' );
		$section_b           = __( 'Section B — Course Content and Learning Experience', 'cta-lms' );
		$section_c           = __( 'Section C — Instructor/Presenter', 'cta-lms' );
		$section_d           = __( 'Section D — Technology and Administration', 'cta-lms' );
		$section_e           = __( 'Section E — Qualitative Feedback', 'cta-lms' );

		return array(
			// Participant Information (required).
			array(
				'id'       => 'participant_cert_name',
				'section'  => $section_participant,
				'label'    => __( 'Name for Certificate', 'cta-lms' ),
				'type'     => 'short_text',
				'required' => true,
				'summary'  => '',
			),
			array(
				'id'       => 'participant_email',
				'section'  => $section_participant,
				'label'    => __( 'Email Address', 'cta-lms' ),
				'type'     => 'short_text',
				'required' => true,
				'summary'  => '',
			),
			array(
				'id'       => 'participant_license_type',
				'section'  => $section_participant,
				'label'    => __( 'License/Registration Type', 'cta-lms' ),
				'type'     => 'dropdown',
				'required' => true,
				'options'  => $license_types,
				'summary'  => '',
			),
			array(
				'id'       => 'participant_license_number',
				'section'  => $section_participant,
				'label'    => __( 'License/Registration Number', 'cta-lms' ),
				'type'     => 'short_text',
				'required' => true,
				'summary'  => '',
			),

			// Section B — Course Content and Learning Experience.
			array(
				'id'       => 'course_appropriateness',
				'section'  => $section_b,
				'label'    => __( 'The course was appropriate to my education, experience, and licensure or registration level.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => 'rating',
			),
			array(
				'id'       => 'relevance_to_practice',
				'section'  => $section_b,
				'label'    => __( 'The course content was relevant to my professional practice.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'presentation_active_learning',
				'section'  => $section_b,
				'label'    => __( 'The presentation and active-learning activities supported my learning.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'materials_suitable',
				'section'  => $section_b,
				'label'    => __( 'The instructional materials and downloadable resources were suitable and useful.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => 'content_quality',
			),
			array(
				'id'       => 'currency_accuracy',
				'section'  => $section_b,
				'label'    => __( 'The information presented was current and accurate.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'apply_telehealth_concepts',
				'section'  => $section_b,
				'label'    => __( 'The course strengthened my ability to apply telehealth-related clinical, legal, ethical, and risk-management concepts.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),

			// Section C — Instructor/Presenter.
			array(
				'id'       => 'instructor_knowledge',
				'section'  => $section_c,
				'label'    => __( 'The instructor demonstrated knowledge of the subject matter.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => 'instructor_rating',
			),
			array(
				'id'       => 'instructor_clarity',
				'section'  => $section_c,
				'label'    => __( 'The instructor communicated the material clearly.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'instructor_delivery',
				'section'  => $section_c,
				'label'    => __( "The instructor's delivery was professional, practical, and approachable.", 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'instructor_responsiveness',
				'section'  => $section_c,
				'label'    => __( 'The instructor was responsive to participant needs or questions, when applicable.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),

			// Section D — Technology and Administration.
			array(
				'id'       => 'admin_instructions_clear',
				'section'  => $section_d,
				'label'    => __( 'Course administration and instructions were clear.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'technology_support',
				'section'  => $section_d,
				'label'    => __( 'Technology support was adequate and timely, when needed.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'technology_contribution',
				'section'  => $section_d,
				'label'    => __( 'The course technology contributed positively to learning.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'technology_usability',
				'section'  => $section_d,
				'label'    => __( 'The course platform was user-friendly.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),
			array(
				'id'       => 'media_functioned',
				'section'  => $section_d,
				'label'    => __( 'Videos, documents, quizzes, and downloads functioned as expected.', 'cta-lms' ),
				'type'     => 'rating',
				'required' => true,
				'options'  => $likert,
				'summary'  => '',
			),

			// Section E — Qualitative Feedback (optional).
			array(
				'id'       => 'feedback_most_valuable',
				'section'  => $section_e,
				'label'    => __( 'What was the most valuable part of this course?', 'cta-lms' ),
				'type'     => 'paragraph',
				'required' => false,
				'summary'  => '',
			),
			array(
				'id'       => 'feedback_could_improve',
				'section'  => $section_e,
				'label'    => __( 'What could be improved?', 'cta-lms' ),
				'type'     => 'paragraph',
				'required' => false,
				'summary'  => '',
			),
			array(
				'id'       => 'feedback_apply_practice',
				'section'  => $section_e,
				'label'    => __( 'How do you expect to apply what you learned in professional practice?', 'cta-lms' ),
				'type'     => 'paragraph',
				'required' => false,
				'summary'  => '',
			),
			array(
				'id'       => 'feedback_topics_wanted',
				'section'  => $section_e,
				'label'    => __( 'What additional telehealth or related topics would you like CTA to offer?', 'cta-lms' ),
				'type'     => 'paragraph',
				'required' => false,
				'summary'  => '',
			),
			array(
				'id'       => 'comments',
				'section'  => $section_e,
				'label'    => __( 'Additional comments', 'cta-lms' ),
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
	 * Ensure a course has evaluation questions (LO sync + full CAMFT set).
	 *
	 * Always fills any missing CAMFT / standard questions so older partial
	 * course forms pick up newly required CAMFT areas.
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

		// CTA-CE-001 uses the official 9-section evaluation (not the shared A–E template).
		if ( class_exists( 'CTA_Law_Ethics_Evaluation_Sync' )
			&& CTA_Law_Ethics_Evaluation_Sync::is_law_ethics_course( $course_id ) ) {
			CTA_Law_Ethics_Evaluation_Sync::sync( false );
			return count( self::get_questions( 'all', $course_id ) );
		}

		self::sync_learning_objective_questions( $course_id );
		self::copy_camft_templates_to_course( $course_id );

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
		$section     = __( 'Section A — Learning Objectives', 'cta-lms' );

		foreach ( $objectives as $index => $objective ) {
			$key           = 'lo_' . $index;
			$active_keys[] = $key;
			// Prompt stem is shown as the section intro in the student form.
			$label = $objective;

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
				'order_index'     => 10 + (int) $index,
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
	 * Copy / upsert shared standard templates (course_id = 0) into a course.
	 *
	 * Updates existing CAMFT items to match the current template labels/options,
	 * adds missing ones, and deactivates obsolete template keys.
	 *
	 * @param int $course_id Course ID.
	 * @return int Number of questions newly copied.
	 */
	public static function copy_camft_templates_to_course( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return 0;
		}

		self::ensure_camft_library_complete();

		$templates = self::get_questions( 'active', 0 );
		if ( empty( $templates ) ) {
			return 0;
		}

		$allowed_keys = array();
		foreach ( self::get_camft_template_questions() as $tpl ) {
			$allowed_keys[] = $tpl['id'];
		}

		$lo_count   = count( self::get_questions_by_source( $course_id, 'learning_objective' ) );
		$order_base = max( 100, $lo_count + 20 );
		$copied     = 0;

		foreach ( $templates as $template ) {
			$bare_key = (string) $template->question_key;
			if ( 0 === strpos( $bare_key, 'camft_' ) ) {
				$bare_key = substr( $bare_key, 6 );
			}
			$new_key = ( 0 === strpos( (string) $template->question_key, 'camft_' ) )
				? (string) $template->question_key
				: 'camft_' . $bare_key;

			$options = array();
			if ( ! empty( $template->options_json ) ) {
				$decoded = json_decode( (string) $template->options_json, true );
				if ( is_array( $decoded ) ) {
					$options = $decoded;
				}
			}

			$is_participant = ( 0 === strpos( $bare_key, 'participant_' ) );
			$order_index    = $is_participant
				? (int) $template->order_index
				: $order_base + (int) $template->order_index;

			$data = array(
				'course_id'       => $course_id,
				'question_key'    => $new_key,
				'section_label'   => $template->section_label,
				'label'           => $template->label,
				'question_type'   => self::normalize_type( $template->question_type ),
				'options'         => $options,
				'is_required'     => (int) $template->is_required,
				'summary_field'   => $template->summary_field,
				'order_index'     => $order_index,
				'source_type'     => 'camft',
				'objective_index' => -1,
				'status'          => 'active',
			);

			$existing = self::get_question_by_key( $course_id, $new_key );
			if ( ! $existing && $new_key !== $bare_key ) {
				$existing = self::get_question_by_key( $course_id, $bare_key );
			}

			if ( $existing ) {
				// Keep the stored question_key stable if it was the legacy bare key.
				$data['question_key'] = (string) $existing->question_key;
				self::update_question( (int) $existing->id, $data );
			} else {
				self::insert_question( $data );
				++$copied;
			}
		}

		self::deactivate_obsolete_camft_questions( $course_id, $allowed_keys );

		return $copied;
	}

	/**
	 * Push the full CAMFT / standard evaluation set onto every CE course.
	 *
	 * Skips Exam Preparation Programs (no CE certificate / evaluation flow).
	 *
	 * @return array{courses:int,copied:int}
	 */
	public static function sync_camft_to_all_ce_courses() {
		self::install();
		self::ensure_camft_library_complete();

		$courses = array();
		if ( method_exists( 'CTA_Database', 'get_courses_by_product_type' ) ) {
			$courses = CTA_Database::get_courses_by_product_type( 'ce', 'all' );
			if ( empty( $courses ) ) {
				$courses = CTA_Database::get_courses_by_product_type( 'ce', 'published' );
			}
		}

		$touched = 0;
		$copied  = 0;

		foreach ( (array) $courses as $course ) {
			if ( ! $course || empty( $course->id ) ) {
				continue;
			}

			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
				continue;
			}

			$before = count( self::get_questions( 'all', (int) $course->id ) );
			$added  = self::copy_camft_templates_to_course( (int) $course->id );
			self::sync_learning_objective_questions( (int) $course->id );
			$after = count( self::get_questions( 'all', (int) $course->id ) );

			if ( $added > 0 || $after !== $before ) {
				++$touched;
			}
			$copied += (int) $added;
		}

		return array(
			'courses' => $touched,
			'copied'  => $copied,
		);
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
