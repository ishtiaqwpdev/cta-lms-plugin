<?php
/**
 * Database setup and helper functions for CTA LMS.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Database
 */
if ( ! class_exists( 'CTA_Database' ) ) {

class CTA_Database {

	/**
	 * Create all plugin database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$table_courses = $wpdb->prefix . 'cta_courses';
		$sql_courses   = "CREATE TABLE $table_courses (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL,
  slug varchar(255) NOT NULL,
  description longtext,
  ce_hours decimal(4,1) NOT NULL DEFAULT 0.0,
  price decimal(10,2) NOT NULL DEFAULT 0.00,
  category varchar(100) DEFAULT NULL,
  learning_objectives longtext,
  syllabus_meta longtext,
  modules_count int(11) DEFAULT 0,
  status varchar(20) DEFAULT 'draft',
  thumbnail_url varchar(500) DEFAULT NULL,
  vimeo_id varchar(100) DEFAULT NULL,
  video_url varchar(500) DEFAULT NULL,
  product_type varchar(20) NOT NULL DEFAULT 'ce',
  access_period_months int(11) NOT NULL DEFAULT 6,
  awards_ce_hours tinyint(1) NOT NULL DEFAULT 1,
  has_ce_certificate tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY product_type (product_type)
) $charset_collate;";

		$table_modules = $wpdb->prefix . 'cta_course_modules';
		$sql_modules   = "CREATE TABLE $table_modules (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  course_id bigint(20) unsigned NOT NULL,
  title varchar(255) NOT NULL,
  description text,
  video_url varchar(500) DEFAULT NULL,
  duration_mins int(11) DEFAULT 0,
  order_index int(11) DEFAULT 0,
  is_locked tinyint(1) DEFAULT 1,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY course_id (course_id)
) $charset_collate;";

		$table_enrollments = $wpdb->prefix . 'cta_enrollments';
		$sql_enrollments   = "CREATE TABLE $table_enrollments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  status varchar(20) DEFAULT 'active',
  progress int(3) DEFAULT 0,
  modules_completed longtext,
  enrolled_at datetime DEFAULT CURRENT_TIMESTAMP,
  completed_at datetime DEFAULT NULL,
  expires_at datetime DEFAULT NULL,
  payment_id varchar(100) DEFAULT NULL,
  access_source varchar(20) DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY unique_enrollment (user_id,course_id),
  KEY user_id (user_id),
  KEY course_id (course_id)
) $charset_collate;";

		$table_bookings = $wpdb->prefix . 'cta_bookings';
		$sql_bookings   = "CREATE TABLE $table_bookings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  session_type varchar(20) NOT NULL,
  session_date date NOT NULL,
  session_time time NOT NULL,
  duration_mins int(11) DEFAULT 60,
  seats_total int(11) DEFAULT 8,
  seats_booked int(11) DEFAULT 0,
  status varchar(20) DEFAULT 'confirmed',
  stripe_sub_id varchar(100) DEFAULT NULL,
  notes text,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY session_date (session_date)
) $charset_collate;";

		$table_documents = $wpdb->prefix . 'cta_documents';
		$sql_documents   = "CREATE TABLE $table_documents (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  file_name varchar(255) NOT NULL,
  file_url varchar(500) NOT NULL,
  file_type varchar(100) DEFAULT NULL,
  file_size int(11) DEFAULT NULL,
  doc_category varchar(100) DEFAULT NULL,
  review_status varchar(20) DEFAULT 'pending',
  uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
  reviewed_at datetime DEFAULT NULL,
  reviewed_by bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id)
) $charset_collate;";

		$table_payments = $wpdb->prefix . 'cta_payments';
		$sql_payments   = "CREATE TABLE $table_payments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  stripe_payment_id varchar(100) DEFAULT NULL,
  stripe_customer_id varchar(100) DEFAULT NULL,
  amount decimal(10,2) NOT NULL,
  currency varchar(10) DEFAULT 'usd',
  payment_type varchar(20) DEFAULT NULL,
  product_type varchar(20) DEFAULT NULL,
  product_id bigint(20) unsigned DEFAULT NULL,
  plan_name varchar(255) DEFAULT NULL,
  plan_details longtext,
  status varchar(20) DEFAULT 'pending',
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY stripe_payment_id (stripe_payment_id),
  KEY user_id (user_id)
) $charset_collate;";

		$table_bundles = $wpdb->prefix . 'cta_bundles';
		$sql_bundles   = "CREATE TABLE $table_bundles (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL,
  slug varchar(255) NOT NULL,
  description text,
  plan_type varchar(20) NOT NULL DEFAULT 'bundle',
  price decimal(10,2) NOT NULL DEFAULT 0.00,
  billing_cycle varchar(20) DEFAULT 'one_time',
  included_courses longtext,
  stripe_price_id varchar(100) DEFAULT NULL,
  is_featured tinyint(1) DEFAULT 0,
  status varchar(20) DEFAULT 'active',
  sort_order int(11) DEFAULT 0,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) $charset_collate;";

		$table_certificates = $wpdb->prefix . 'cta_certificates';
		$sql_certificates   = "CREATE TABLE $table_certificates (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  enrollment_id bigint(20) unsigned NOT NULL,
  certificate_number varchar(50) NOT NULL,
  issued_at datetime DEFAULT CURRENT_TIMESTAMP,
  file_path varchar(500) DEFAULT NULL,
  file_url varchar(500) DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY certificate_number (certificate_number),
  KEY user_id (user_id),
  KEY enrollment_id (enrollment_id),
  KEY course_id (course_id)
) $charset_collate;";

		$table_quizzes = $wpdb->prefix . 'cta_quizzes';
		$sql_quizzes   = "CREATE TABLE $table_quizzes (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  course_id bigint(20) unsigned NOT NULL,
  title varchar(255) NOT NULL,
  quiz_type varchar(40) NOT NULL DEFAULT 'final',
  sort_order int(11) NOT NULL DEFAULT 0,
  passing_score int(11) DEFAULT 70,
  time_limit_mins int(11) DEFAULT 0,
  max_attempts int(11) DEFAULT 0,
  status varchar(20) DEFAULT 'active',
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY course_id (course_id),
  KEY course_sort (course_id, sort_order, id)
) $charset_collate;";

		$table_quiz_questions = $wpdb->prefix . 'cta_quiz_questions';
		$sql_quiz_questions   = "CREATE TABLE $table_quiz_questions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  quiz_id bigint(20) unsigned NOT NULL,
  question_text text NOT NULL,
  option_a varchar(500) NOT NULL,
  option_b varchar(500) NOT NULL,
  option_c varchar(500) NOT NULL,
  option_d varchar(500) NOT NULL,
  correct_option varchar(1) NOT NULL,
  explanation text,
  order_index int(11) DEFAULT 0,
  PRIMARY KEY  (id),
  KEY quiz_id (quiz_id)
) $charset_collate;";

		$table_quiz_attempts = $wpdb->prefix . 'cta_quiz_attempts';
		// No UNIQUE on (user_id, quiz_id[+attempt_number]): retakes must never be blocked by indexes.
		$sql_quiz_attempts   = "CREATE TABLE $table_quiz_attempts (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  quiz_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  answers longtext,
  score int(11) DEFAULT 0,
  passed tinyint(1) DEFAULT 0,
  attempt_number int(11) DEFAULT 1,
  started_at datetime DEFAULT CURRENT_TIMESTAMP,
  completed_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY user_quiz (user_id,quiz_id),
  KEY user_id (user_id),
  KEY quiz_id (quiz_id),
  KEY course_id (course_id)
) $charset_collate;";

		$table_evaluations = $wpdb->prefix . 'cta_evaluations';
		$sql_evaluations   = "CREATE TABLE $table_evaluations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  rating int(11) NOT NULL DEFAULT 0,
  content_quality int(11) NOT NULL DEFAULT 0,
  instructor_rating int(11) NOT NULL DEFAULT 0,
  would_recommend tinyint(1) NOT NULL DEFAULT 0,
  comments text,
  responses longtext,
  timezone varchar(100) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'completed',
  course_title varchar(255) NOT NULL DEFAULT '',
  student_name varchar(255) NOT NULL DEFAULT '',
  student_email varchar(255) NOT NULL DEFAULT '',
  submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY course_id (course_id),
  KEY user_course (user_id,course_id)
) $charset_collate;";

		$table_exam_access = $wpdb->prefix . 'cta_exam_access';
		$sql_exam_access   = "CREATE TABLE $table_exam_access (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  purchased_at datetime DEFAULT CURRENT_TIMESTAMP,
  expires_at datetime DEFAULT NULL,
  original_expires_at datetime DEFAULT NULL,
  extended_by_admin_id bigint(20) unsigned DEFAULT NULL,
  extension_notes text,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY user_course (user_id,course_id),
  KEY user_id (user_id),
  KEY course_id (course_id),
  KEY expires_at (expires_at)
) $charset_collate;";

		$table_resources = $wpdb->prefix . 'cta_downloadable_resources';
		$sql_resources   = "CREATE TABLE $table_resources (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  course_id bigint(20) unsigned NOT NULL,
  module_id bigint(20) unsigned NOT NULL DEFAULT 0,
  attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL,
  file_url varchar(500) NOT NULL,
  file_path varchar(500) DEFAULT NULL,
  file_type varchar(50) DEFAULT NULL,
  order_index int(11) DEFAULT 0,
  is_practice_test tinyint(1) NOT NULL DEFAULT 0,
  unlock_after_quiz_type varchar(40) NOT NULL DEFAULT '',
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY course_id (course_id),
  KEY module_id (module_id)
) $charset_collate;";

		dbDelta( $sql_courses );
		dbDelta( $sql_modules );
		dbDelta( $sql_enrollments );
		dbDelta( $sql_bookings );
		dbDelta( $sql_documents );
		dbDelta( $sql_payments );
		dbDelta( $sql_bundles );
		dbDelta( $sql_certificates );
		dbDelta( $sql_quizzes );
		dbDelta( $sql_quiz_questions );
		dbDelta( $sql_quiz_attempts );
		dbDelta( $sql_evaluations );
		dbDelta( $sql_exam_access );
		dbDelta( $sql_resources );

		self::maybe_add_exam_prep_columns();
		self::maybe_add_multi_quiz_support();
		self::maybe_ensure_quiz_attempt_schema();
		self::maybe_add_resource_columns();
		self::maybe_add_resource_unlock_column();
		self::maybe_add_syllabus_columns();
		self::maybe_add_evaluation_submission_columns();
		self::maybe_add_enrollment_access_source();

		if ( class_exists( 'CTA_Evaluation_Questions' ) ) {
			CTA_Evaluation_Questions::install();
		}

		if ( class_exists( 'CTA_Course_Attestation' ) ) {
			CTA_Course_Attestation::install();
		}
	}

	/**
	 * Ensure enrollment access_source column exists (CE purchase vs membership).
	 */
	public static function maybe_add_enrollment_access_source() {
		if ( class_exists( 'CTA_CE_Access' ) ) {
			CTA_CE_Access::maybe_install_schema();
			return;
		}

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
	}

	/**
	 * Ensure syllabus_meta column exists on cta_courses.
	 */
	public static function maybe_add_syllabus_columns() {
		global $wpdb;

		$table   = $wpdb->prefix . 'cta_courses';
		$columns = array(
			'syllabus_meta' => "ALTER TABLE {$table} ADD COLUMN syllabus_meta longtext",
		);

		foreach ( $columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
			if ( empty( $exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}
	}

	/**
	 * Ensure exam-prep columns exist on cta_courses (dbDelta-safe fallback).
	 *
	 * Uses SHOW COLUMNS rather than DROP/rebuild so existing CE data is preserved.
	 */
	public static function maybe_add_exam_prep_columns() {
		global $wpdb;

		$table   = $wpdb->prefix . 'cta_courses';
		$columns = array(
			'product_type'         => "ALTER TABLE {$table} ADD COLUMN product_type varchar(20) NOT NULL DEFAULT 'ce'",
			'access_period_months' => "ALTER TABLE {$table} ADD COLUMN access_period_months int(11) NOT NULL DEFAULT 6",
			'awards_ce_hours'      => "ALTER TABLE {$table} ADD COLUMN awards_ce_hours tinyint(1) NOT NULL DEFAULT 1",
			'has_ce_certificate'   => "ALTER TABLE {$table} ADD COLUMN has_ce_certificate tinyint(1) NOT NULL DEFAULT 1",
		);

		foreach ( $columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
			if ( empty( $exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}
	}

	/**
	 * Ensure downloadable resource columns exist (module + protected path).
	 */
	public static function maybe_add_resource_columns() {
		global $wpdb;

		$table   = $wpdb->prefix . 'cta_downloadable_resources';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return;
		}

		$columns = array(
			'module_id'     => "ALTER TABLE {$table} ADD COLUMN module_id bigint(20) unsigned NOT NULL DEFAULT 0",
			'attachment_id' => "ALTER TABLE {$table} ADD COLUMN attachment_id bigint(20) unsigned NOT NULL DEFAULT 0",
			'file_path'     => "ALTER TABLE {$table} ADD COLUMN file_path varchar(500) DEFAULT NULL",
		);

		foreach ( $columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$col_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
			if ( empty( $col_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}

		self::maybe_add_resource_unlock_column();
	}

	/**
	 * Per-student unlock gate: hide a download until the learner submits a quiz of this type.
	 *
	 * Used for Form A/B detailed-rationale files (unlock_after_quiz_type = form_a|form_b).
	 */
	public static function maybe_add_resource_unlock_column() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_downloadable_resources';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$col_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'unlock_after_quiz_type' ) );
		if ( empty( $col_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				"ALTER TABLE {$table} ADD COLUMN unlock_after_quiz_type varchar(40) NOT NULL DEFAULT '' AFTER is_practice_test"
			);
		}
	}

	/**
	 * Ensure evaluation submission columns exist on cta_evaluations.
	 */
	public static function maybe_add_evaluation_submission_columns() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_evaluations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists !== $table ) {
			return;
		}

		$columns = array(
			'status'        => "ALTER TABLE {$table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'completed'",
			'course_title'  => "ALTER TABLE {$table} ADD COLUMN course_title varchar(255) NOT NULL DEFAULT ''",
			'student_name'  => "ALTER TABLE {$table} ADD COLUMN student_name varchar(255) NOT NULL DEFAULT ''",
			'student_email' => "ALTER TABLE {$table} ADD COLUMN student_email varchar(255) NOT NULL DEFAULT ''",
		);

		foreach ( $columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$col_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
			if ( empty( $col_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}

		// Allow historical evaluation submissions (do not overwrite prior rows).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'user_course'" );
		if ( ! empty( $indexes ) ) {
			$is_unique = false;
			foreach ( $indexes as $index_row ) {
				if ( isset( $index_row->Non_unique ) && (int) $index_row->Non_unique === 0 ) {
					$is_unique = true;
					break;
				}
			}
			if ( $is_unique ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$table} DROP INDEX user_course" );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( "ALTER TABLE {$table} ADD KEY user_course (user_id, course_id)" );
			}
		}
	}

	/**
	 * Whether core plugin tables exist.
	 *
	 * @return bool
	 */
	public static function tables_ready() {
		global $wpdb;

		$courses_table = $wpdb->prefix . 'cta_courses';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $courses_table ) ) === $courses_table;
	}

	/**
	 * Create tables when missing (for example after duplicate-plugin cleanup).
	 */
	public static function ensure_tables() {
		if ( self::tables_ready() ) {
			return true;
		}

		self::create_tables();

		return self::tables_ready();
	}

	/**
	 * Fetch all active bundles ordered for display.
	 *
	 * @return array
	 */
	public static function get_all_bundles() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bundles';

		return $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE status = 'active'
			ORDER BY sort_order ASC, id ASC"
		);
	}

	/**
	 * Fetch a single active bundle by ID.
	 *
	 * @param int $id Bundle ID.
	 * @return object|null
	 */
	public static function get_bundle( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bundles';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND status = 'active'",
				$id
			)
		);
	}

	/**
	 * Seed default bundle plans when table is empty.
	 */
	public static function seed_bundles() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bundles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $count > 0 ) {
			return;
		}

		if ( class_exists( 'CTA_Bundle_Catalog' ) ) {
			CTA_Bundle_Catalog::sync_all();
			return;
		}

		// Fallback if catalog class is unavailable.
		$bundles = array(
			array(
				'name'             => 'First Renewal Bundle',
				'slug'             => 'first-renewal-bundle',
				'description'      => 'First Renewal Bundle — Child Abuse and HIV/AIDS courses (retail value $178).',
				'plan_type'        => 'bundle',
				'price'            => 139.00,
				'billing_cycle'    => 'one_time',
				'included_courses' => wp_json_encode( array() ),
				'is_featured'      => 0,
				'sort_order'       => 10,
			),
			array(
				'name'             => 'Clinical Excellence Annual All-Access Pass',
				'slug'             => 'clinical-excellence-annual-all-access',
				'description'      => 'Unlimited access to all asynchronous CE courses for a full year. Excludes live supervision and Exam Preparation programs.',
				'plan_type'        => 'annual',
				'price'            => 299.00,
				'billing_cycle'    => 'yearly',
				'included_courses' => wp_json_encode( array() ),
				'is_featured'      => 1,
				'sort_order'       => 120,
			),
			CTA_Supervision_Plans::get_all_access_bundle_seed(),
		);

		foreach ( $bundles as $bundle ) {
			$wpdb->insert(
				$table,
				$bundle,
				array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * Fetch the latest supervision-related payment for a user.
	 *
	 * Includes direct supervision subscriptions and Supervision + CE All-Access
	 * (hybrid) bundle purchases.
	 *
	 * @param int         $user_id WordPress user ID.
	 * @param string|null $status  Optional payment status filter.
	 * @return object|null
	 */
	public static function get_user_supervision_payment( $user_id, $status = null ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'cta_payments';
		$user_id = absint( $user_id );
		$status  = $status ? sanitize_text_field( $status ) : '';

		if ( ! $user_id ) {
			return null;
		}

		if ( $status ) {
			$direct = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE user_id = %d
					AND product_type = 'supervision'
					AND status = %s
					ORDER BY created_at DESC, id DESC
					LIMIT 1",
					$user_id,
					$status
				)
			);
		} else {
			$direct = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE user_id = %d
					AND product_type = 'supervision'
					ORDER BY created_at DESC, id DESC
					LIMIT 1",
					$user_id
				)
			);
		}

		if ( $direct ) {
			return $direct;
		}

		if ( $status ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE user_id = %d
					AND product_type = 'bundle'
					AND status = %s
					AND (
						plan_details LIKE %s
						OR plan_name LIKE %s
						OR plan_name LIKE %s
						OR plan_name LIKE %s
					)
					ORDER BY created_at DESC, id DESC
					LIMIT 1",
					$user_id,
					$status,
					'%"plan_slug":"hybrid"%',
					'%Hybrid%',
					'%All-Access Program%',
					'%Supervision + CE%'
				)
			);
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d
				AND product_type = 'bundle'
				AND (
					plan_details LIKE %s
					OR plan_name LIKE %s
					OR plan_name LIKE %s
					OR plan_name LIKE %s
				)
				ORDER BY created_at DESC, id DESC
				LIMIT 1",
				$user_id,
				'%"plan_slug":"hybrid"%',
				'%Hybrid%',
				'%All-Access Program%',
				'%Supervision + CE%'
			)
		);
	}

	/**
	 * Fetch a single course by ID.
	 *
	 * @param int $id Course ID.
	 * @return object|null
	 */
	public static function get_course( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Fetch a course by exact title, with a contains fallback.
	 *
	 * @param string $title Course title.
	 * @return object|null
	 */
	public static function get_course_by_title( $title ) {
		global $wpdb;

		$title = trim( (string) $title );
		if ( '' === $title ) {
			return null;
		}

		$table = $wpdb->prefix . 'cta_courses';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE title = %s LIMIT 1",
				$title
			)
		);

		if ( $row ) {
			return $row;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE title LIKE %s ORDER BY id ASC LIMIT 1",
				'%' . $wpdb->esc_like( $title ) . '%'
			)
		);
	}

	/**
	 * Fetch all courses, optionally filtered by status.
	 *
	 * @param string $status Course status (default: published).
	 * @return array
	 */
	public static function get_all_courses( $status = 'published' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC",
				$status
			)
		);
	}

	/**
	 * Fetch courses filtered by product type and optional status.
	 *
	 * @param string      $product_type ce|exam_prep.
	 * @param string|null $status       Optional status filter (null = all).
	 * @return array
	 */
	public static function get_courses_by_product_type( $product_type, $status = 'published' ) {
		global $wpdb;

		$table        = $wpdb->prefix . 'cta_courses';
		$product_type = sanitize_text_field( $product_type );

		if ( null === $status || '' === $status || 'all' === $status ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE product_type = %s ORDER BY created_at DESC",
					$product_type
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_type = %s AND status = %s ORDER BY created_at DESC",
				$product_type,
				sanitize_text_field( $status )
			)
		);
	}

	/**
	 * Downloadable resources for a course (workbooks, handouts, practice tests).
	 *
	 * @param int      $course_id           Course ID.
	 * @param bool     $practice_tests_only When true, only is_practice_test = 1.
	 * @param int|null $module_id           Optional module filter (0 = course-level only, null = all).
	 * @return array
	 */
	public static function get_downloadable_resources( $course_id, $practice_tests_only = false, $module_id = null ) {
		global $wpdb;

		$course_id = absint( $course_id );

		if ( ! $course_id ) {
			return array();
		}

		$table  = $wpdb->prefix . 'cta_downloadable_resources';
		$where  = array( 'course_id = %d' );
		$values = array( $course_id );

		if ( $practice_tests_only ) {
			$where[] = 'is_practice_test = 1';
		}

		if ( null !== $module_id ) {
			$where[]  = 'module_id = %d';
			$values[] = absint( $module_id );
		}

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY order_index ASC, id ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return $rows ? $rows : array();
	}

	/**
	 * Fetch a single downloadable resource by ID.
	 *
	 * @param int $resource_id Resource ID.
	 * @return object|null
	 */
	public static function get_downloadable_resource( $resource_id ) {
		global $wpdb;

		$resource_id = absint( $resource_id );

		if ( ! $resource_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_downloadable_resources WHERE id = %d",
				$resource_id
			)
		);
	}

	/**
	 * Fetch all enrollments for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array
	 */
	public static function get_user_enrollments( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_enrollments';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY enrolled_at DESC",
				$user_id
			)
		);
	}

	/**
	 * Fetch all bookings for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array
	 */
	public static function get_user_bookings( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bookings';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY session_date DESC, session_time DESC",
				$user_id
			)
		);
	}

	/**
	 * Check available seats for a session date and time.
	 *
	 * @param string $session_date Session date (Y-m-d).
	 * @param string $session_time Session time (H:i:s).
	 * @return int Available seats remaining.
	 */
	public static function get_available_seats( $session_date, $session_time ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_bookings';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT seats_total, seats_booked FROM {$table}
				WHERE session_date = %s AND session_time = %s AND status = 'confirmed'
				LIMIT 1",
				$session_date,
				$session_time
			)
		);

		if ( ! $row ) {
			return 8;
		}

		return max( 0, (int) $row->seats_total - (int) $row->seats_booked );
	}

	/**
	 * Fetch modules for a course ordered by curriculum sequence.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	/**
	 * Fetch modules for a course (ordered).
	 *
	 * Rows with order_index >= 900 are treated as archived duplicates and are
	 * excluded from the learner/admin curriculum sequence (exam unlock, locks).
	 *
	 * @param int  $course_id        Course ID.
	 * @param bool $include_archived When true, include archived rows.
	 * @return array
	 */
	public static function get_course_modules( $course_id, $include_archived = false ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_course_modules';

		if ( $include_archived ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE course_id = %d ORDER BY order_index ASC, id ASC",
					$course_id
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE course_id = %d AND order_index < 900 ORDER BY order_index ASC, id ASC",
				$course_id
			)
		);
	}

	/**
	 * Fetch a single enrollment for a user and course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_user_enrollment( $user_id, $course_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_enrollments';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Fetch a certificate by ID.
	 *
	 * @param int $certificate_id Certificate ID.
	 * @return object|null
	 */
	public static function get_certificate( $certificate_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_certificates';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$certificate_id
			)
		);
	}

	/**
	 * Fetch certificate for a user's enrollment.
	 *
	 * @param int $user_id       WordPress user ID.
	 * @param int $enrollment_id Enrollment ID.
	 * @return object|null
	 */
	public static function get_enrollment_certificate( $user_id, $enrollment_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_certificates';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND enrollment_id = %d",
				$user_id,
				$enrollment_id
			)
		);
	}

	/**
	 * Fetch certificate for a user and course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_user_course_certificate( $user_id, $course_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_certificates';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d ORDER BY issued_at DESC LIMIT 1",
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Fetch all certificates for a user (permanent — independent of enrollment/membership).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array
	 */
	public static function get_user_certificates( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$table = $wpdb->prefix . 'cta_certificates';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY issued_at DESC",
				$user_id
			)
		);
	}

	/**
	 * Allow multiple quizzes per course (Exam Prep Practice / Form A / Form B).
	 *
	 * Drops the legacy UNIQUE(course_id) constraint, adds quiz_type + sort_order,
	 * and backfills types for existing rows.
	 */
	public static function maybe_add_multi_quiz_support() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quizzes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		$columns = array(
			'quiz_type'  => "ALTER TABLE {$table} ADD COLUMN quiz_type varchar(40) NOT NULL DEFAULT 'final' AFTER title",
			'sort_order' => "ALTER TABLE {$table} ADD COLUMN sort_order int(11) NOT NULL DEFAULT 0 AFTER quiz_type",
		);

		foreach ( $columns as $column => $sql ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$col_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
			if ( empty( $col_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}
		}

		// Drop UNIQUE(course_id) so one Exam Prep program can own multiple assessments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
		$has_unique_course = false;
		$has_plain_course  = false;

		foreach ( (array) $indexes as $index ) {
			$name   = isset( $index['Key_name'] ) ? (string) $index['Key_name'] : '';
			$unique = isset( $index['Non_unique'] ) ? (int) $index['Non_unique'] : 1;
			$col    = isset( $index['Column_name'] ) ? (string) $index['Column_name'] : '';

			if ( 'course_id' === $name && 0 === $unique && 'course_id' === $col ) {
				$has_unique_course = true;
			}
			if ( 'course_id' === $name && 1 === $unique ) {
				$has_plain_course = true;
			}
			if ( 'course_sort' === $name ) {
				$has_plain_course = true;
			}
		}

		if ( $has_unique_course ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} DROP INDEX course_id" );
			$has_plain_course = false;
		}

		if ( ! $has_plain_course ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD KEY course_id (course_id)" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD KEY course_sort (course_id, sort_order, id)" );
		}

		// Backfill quiz_type for legacy single-quiz Exam Prep rows.
		$courses_table = $wpdb->prefix . 'cta_courses';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"UPDATE {$table} q
			INNER JOIN {$courses_table} c ON c.id = q.course_id
			SET q.quiz_type = 'practice', q.sort_order = 10
			WHERE c.product_type = 'exam_prep'
			AND (q.quiz_type = '' OR q.quiz_type IS NULL OR q.quiz_type = 'final')"
		);
	}

	/**
	 * Ensure attempt_number exists, zero-dates are cleaned, and NO unique key blocks Start/Retry.
	 */
	public static function maybe_ensure_quiz_attempt_schema() {
		$flag = 'cta_quiz_attempt_schema_v138';
		if ( '1' === (string) get_option( $flag, '' ) ) {
			// Still drop unique keys cheaply if a host re-added them; skip heavy renumber.
			self::maybe_fix_quiz_attempt_retake_index();
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'attempt_number' ) );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN attempt_number int(11) NOT NULL DEFAULT 1 AFTER passed" );
		}

		// Normalize legacy zero-dates so "active" detection works.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"UPDATE {$table}
			SET completed_at = NULL
			WHERE completed_at = '0000-00-00 00:00:00'
				OR completed_at = '0000-00-00'
				OR completed_at = ''"
		);

		self::maybe_renumber_duplicate_attempt_numbers();
		self::maybe_fix_quiz_attempt_retake_index();
		update_option( $flag, '1', false );
	}

	/**
	 * When attempt_number was backfilled as 1 for every row, renumber per user/quiz
	 * so UNIQUE(user_id, quiz_id, attempt_number) can be created and Start/Retry works.
	 */
	public static function maybe_renumber_duplicate_attempt_numbers() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dupes = $wpdb->get_results(
			"SELECT user_id, quiz_id
			FROM {$table}
			GROUP BY user_id, quiz_id, attempt_number
			HAVING COUNT(*) > 1
			LIMIT 200"
		);

		if ( empty( $dupes ) ) {
			return;
		}

		$seen = array();
		foreach ( $dupes as $pair ) {
			$user_id = absint( $pair->user_id );
			$quiz_id = absint( $pair->quiz_id );
			$key     = $user_id . ':' . $quiz_id;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE user_id = %d AND quiz_id = %d ORDER BY id ASC",
					$user_id,
					$quiz_id
				)
			);

			$n = 1;
			foreach ( (array) $rows as $row ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array( 'attempt_number' => $n ),
					array( 'id' => (int) $row->id ),
					array( '%d' ),
					array( '%d' )
				);
				++$n;
			}
		}
	}

	/**
	 * Drop every UNIQUE index on quiz attempts (except PRIMARY).
	 *
	 * Unique (user_id, quiz_id) or (user_id, quiz_id, attempt_number) both cause
	 * "Unable to start quiz" whenever a second row cannot be inserted. Retakes are
	 * enforced in application logic, not with a unique DB constraint.
	 */
	public static function maybe_fix_quiz_attempt_retake_index() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
		if ( ! is_array( $indexes ) ) {
			$indexes = array();
		}

		$by_name        = array();
		$has_user_quiz  = false;
		foreach ( $indexes as $row ) {
			$name = isset( $row['Key_name'] ) ? (string) $row['Key_name'] : '';
			if ( '' === $name || 'PRIMARY' === $name ) {
				continue;
			}
			if ( ! isset( $by_name[ $name ] ) ) {
				$by_name[ $name ] = array(
					'unique'  => ( isset( $row['Non_unique'] ) && 0 === (int) $row['Non_unique'] ),
					'columns' => array(),
				);
			}
			$seq = isset( $row['Seq_in_index'] ) ? (int) $row['Seq_in_index'] : 0;
			$col = isset( $row['Column_name'] ) ? (string) $row['Column_name'] : '';
			if ( $seq > 0 && '' !== $col ) {
				$by_name[ $name ]['columns'][ $seq ] = $col;
			}
		}

		foreach ( $by_name as $name => $meta ) {
			$cols = array_values( $meta['columns'] );
			if ( ! $meta['unique'] && array( 'user_id', 'quiz_id' ) === $cols ) {
				$has_user_quiz = true;
			}
			if ( $meta['unique'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$table} DROP INDEX `{$name}`" );
			}
		}

		if ( ! $has_user_quiz ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "ALTER TABLE {$table} ADD KEY user_quiz (user_id, quiz_id)" );
		}
	}

	/**
	 * Fetch a quiz by ID.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return object|null
	 */
	public static function get_quiz( $quiz_id ) {
		global $wpdb;

		$quiz_id = absint( $quiz_id );
		if ( ! $quiz_id ) {
			return null;
		}

		$table = $wpdb->prefix . 'cta_quizzes';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$quiz_id
			)
		);
	}

	/**
	 * Fetch all quizzes for a course (supports multiple Exam Prep assessments).
	 *
	 * @param int  $course_id   Course ID.
	 * @param bool $active_only Only active quizzes.
	 * @return array
	 */
	public static function get_quizzes_by_course( $course_id, $active_only = true ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return array();
		}

		$table = $wpdb->prefix . 'cta_quizzes';
		$sql   = "SELECT * FROM {$table} WHERE course_id = %d";
		$args  = array( $course_id );

		if ( $active_only ) {
			$sql .= " AND status = 'active'";
		}

		$sql .= ' ORDER BY sort_order ASC, id ASC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Resolve a course quiz, optionally by explicit quiz ID.
	 *
	 * @param int $course_id Course ID.
	 * @param int $quiz_id   Optional quiz ID (must belong to course).
	 * @return object|null
	 */
	public static function get_quiz_for_course( $course_id, $quiz_id = 0 ) {
		$course_id = absint( $course_id );
		$quiz_id   = absint( $quiz_id );

		if ( $quiz_id ) {
			$quiz = self::get_quiz( $quiz_id );
			if ( $quiz && (int) $quiz->course_id === $course_id ) {
				if ( 'active' === (string) $quiz->status ) {
					return $quiz;
				}

				if ( class_exists( 'CTA_Lpcc_Ncmhce_Form_A_V2_Sync' )
					&& CTA_Lpcc_Ncmhce_Form_A_V2_Sync::is_staging_quiz( $quiz ) ) {
					return CTA_Lpcc_Ncmhce_Form_A_V2_Sync::current_user_can_preview() ? $quiz : null;
				}
			}
		}

		return self::get_quiz_by_course( $course_id );
	}

	/**
	 * Fetch primary/active quiz for a course (first by sort_order).
	 *
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_quiz_by_course( $course_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quizzes';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE course_id = %d AND status = 'active'
				ORDER BY sort_order ASC, id ASC
				LIMIT 1",
				$course_id
			)
		);
	}

	/**
	 * Default Exam Prep assessment templates (Practice / Form A / Form B).
	 *
	 * @return array
	 */
	public static function get_exam_prep_assessment_templates() {
		return array(
			array(
				'quiz_type'  => 'practice',
				'title'      => __( 'Practice Assessment', 'cta-lms' ),
				'sort_order' => 10,
			),
			array(
				'quiz_type'  => 'form_a',
				'title'      => __( 'Form A — Comprehensive Simulation', 'cta-lms' ),
				'sort_order' => 20,
			),
			array(
				'quiz_type'  => 'form_b',
				'title'      => __( 'Form B — Comprehensive Simulation', 'cta-lms' ),
				'sort_order' => 30,
			),
		);
	}

	/**
	 * Fetch quiz questions ordered by index.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	public static function get_quiz_questions( $quiz_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_questions';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d ORDER BY order_index ASC, id ASC",
				$quiz_id
			)
		);
	}

	/**
	 * Fetch completed quiz attempts for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	public static function get_user_quiz_attempts( $user_id, $quiz_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_attempts';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d AND quiz_id = %d AND completed_at IS NOT NULL
				ORDER BY attempt_number DESC",
				$user_id,
				$quiz_id
			)
		);
	}

	/**
	 * Fetch in-progress quiz attempt.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $quiz_id Quiz ID.
	 * @return object|null
	 */
	public static function get_active_quiz_attempt( $user_id, $quiz_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_attempts';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d AND quiz_id = %d
					AND (
						completed_at IS NULL
						OR completed_at = '0000-00-00 00:00:00'
						OR completed_at = '0000-00-00'
					)
				ORDER BY id DESC LIMIT 1",
				$user_id,
				$quiz_id
			)
		);
	}

	/**
	 * Fetch course evaluation for a user.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_course_evaluation( $user_id, $course_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_evaluations';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d ORDER BY id DESC LIMIT 1",
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Fetch a single evaluation submission by ID.
	 *
	 * @param int $id Evaluation row ID.
	 * @return object|null
	 */
	public static function get_evaluation( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$table = $wpdb->prefix . 'cta_evaluations';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Build WHERE clause and values for evaluation list queries.
	 *
	 * @param array $args Query arguments.
	 * @return array { where: string[], values: array }
	 */
	private static function build_evaluation_query_parts( $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['course_id'] ) ) {
			$where[]  = 'course_id = %d';
			$values[] = absint( $args['course_id'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'submitted_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'submitted_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(course_title LIKE %s OR student_name LIKE %s OR student_email LIKE %s OR comments LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}

		return array(
			'where'  => $where,
			'values' => $values,
		);
	}

	/**
	 * Fetch evaluation submissions with optional filters.
	 *
	 * @param array $args {
	 *     Optional query arguments.
	 *
	 *     @type int    $course_id Filter by course ID.
	 *     @type int    $user_id   Filter by user ID.
	 *     @type string $date_from Submitted on or after (Y-m-d or datetime).
	 *     @type string $date_to   Submitted on or before (Y-m-d or datetime).
	 *     @type string $search    Search course title, student name/email, comments.
	 *     @type int    $limit     Max rows (default 50).
	 *     @type int    $offset    Offset (default 0).
	 * }
	 * @return array
	 */
	public static function get_evaluations( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_evaluations';
		$parts = self::build_evaluation_query_parts( $args );

		$limit  = isset( $args['limit'] ) ? max( 1, absint( $args['limit'] ) ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, absint( $args['offset'] ) ) : 0;

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $parts['where'] )
			. ' ORDER BY submitted_at DESC, id DESC LIMIT %d OFFSET %d';

		$values   = $parts['values'];
		$values[] = $limit;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Count evaluation submissions matching filters.
	 *
	 * @param array $args Same filters as get_evaluations() without limit/offset.
	 * @return int
	 */
	public static function count_evaluations( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_evaluations';
		$parts = self::build_evaluation_query_parts( $args );

		$sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode( ' AND ', $parts['where'] );

		if ( empty( $parts['values'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $sql );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $parts['values'] ) );
	}

	/**
	 * Get public certificate download URL from row.
	 *
	 * @param object $certificate Certificate row.
	 * @return string
	 */
	public static function get_certificate_url( $certificate ) {
		if ( ! empty( $certificate->file_url ) ) {
			return (string) $certificate->file_url;
		}

		if ( ! empty( $certificate->download_url ) ) {
			return (string) $certificate->download_url;
		}

		return '';
	}
}
}