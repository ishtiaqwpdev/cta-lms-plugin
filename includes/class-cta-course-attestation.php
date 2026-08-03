<?php
/**
 * Course attestation records for async distance learning compliance.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Course_Attestation
 */
if ( ! class_exists( 'CTA_Course_Attestation' ) ) {

class CTA_Course_Attestation {

	const TABLE = 'cta_course_attestations';

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
	 * Create / migrate the attestation table.
	 */
	public static function install() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  course_title varchar(255) NOT NULL DEFAULT '',
  student_name varchar(255) NOT NULL DEFAULT '',
  signature_date date DEFAULT NULL,
  attestation_text longtext NOT NULL,
  ip_address varchar(45) NOT NULL DEFAULT '',
  user_agent varchar(500) NOT NULL DEFAULT '',
  attested_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY user_course (user_id, course_id),
  KEY course_id (course_id),
  KEY user_id (user_id)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::maybe_add_signature_date_column();
	}

	/**
	 * Ensure signature_date exists on older installs.
	 */
	public static function maybe_add_signature_date_column() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'signature_date' ) );
		if ( empty( $exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN signature_date date DEFAULT NULL AFTER student_name" );
		}
	}

	/**
	 * Mandatory completion attestation statement (exact CE wording).
	 *
	 * Inserts the course title into the required statement. When no title is
	 * provided, uses the Telehealth course title from the approved syllabus.
	 *
	 * @param string $course_title Course title for the statement.
	 * @return string
	 */
	public static function default_attestation_text( $course_title = '' ) {
		$course_title = trim( (string) $course_title );

		// CTA-CE-001 uses the Course Evaluation v1.0 Section 9 statement (exact).
		if ( class_exists( 'CTA_Law_Ethics_Evaluation_Sync' ) ) {
			$is_law_ethics = false;
			if ( '' !== $course_title ) {
				foreach ( CTA_Law_Ethics_Evaluation_Sync::match_titles() as $title ) {
					if ( 0 === strcasecmp( $course_title, $title ) ) {
						$is_law_ethics = true;
						break;
					}
				}
			}
			if ( $is_law_ethics ) {
				return CTA_Law_Ethics_Evaluation_Sync::attestation_statement();
			}
		}

		if ( '' === $course_title ) {
			$course_title = 'Clinical and Ethical Excellence in Telehealth: The Essential California Framework';
		}

		return sprintf(
			/* translators: %s: course title */
			__(
				"I attest that I personally completed all required instructional modules for '%s,' completed the final examination without unauthorized assistance, and am submitting this evaluation based on my own participation. I understand that optional worksheets, reflections, and toolkit exercises are self-directed practice and are not separate certificate requirements. I understand that the CE certificate will be issued only after all required course-completion steps are satisfied.",
				'cta-lms'
			),
			$course_title
		);
	}

	/**
	 * Fetch attestation for a user and course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table_name() . ' WHERE user_id = %d AND course_id = %d LIMIT 1',
				$user_id,
				$course_id
			)
		);
	}

	/**
	 * Whether the user has attested for a course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function has( $user_id, $course_id ) {
		$row = self::get( $user_id, $course_id );
		if ( ! $row ) {
			return false;
		}
		// Certificate gate: must have a typed electronic signature on file.
		return '' !== trim( (string) $row->student_name );
	}

	/**
	 * Submit or update attestation for a user and course.
	 *
	 * Requires a typed name (electronic signature). Optional signature_date is
	 * stored when provided; attested_at is always the server timestamp.
	 *
	 * @param int    $user_id         WordPress user ID.
	 * @param int    $course_id       Course ID.
	 * @param string $text            Attestation statement (optional; defaults applied).
	 * @param string $signature_name  Typed full legal name / electronic signature.
	 * @param string $signature_date  Y-m-d date string (optional).
	 * @return true|WP_Error
	 */
	public static function submit( $user_id, $course_id, $text = '', $signature_name = '', $signature_date = '' ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$text      = sanitize_textarea_field( wp_unslash( (string) $text ) );
		$signature = sanitize_text_field( wp_unslash( (string) $signature_name ) );
		$sig_date  = sanitize_text_field( wp_unslash( (string) $signature_date ) );

		if ( ! $user_id || ! $course_id ) {
			return new WP_Error( 'cta_attestation_invalid', __( 'Invalid attestation request.', 'cta-lms' ) );
		}

		$course = CTA_Database::get_course( $course_id );
		if ( ! $course ) {
			return new WP_Error( 'cta_attestation_course', __( 'Course not found.', 'cta-lms' ) );
		}

		$course_title = sanitize_text_field( (string) $course->title );

		// Always persist the current mandatory statement for this course.
		if ( '' === trim( $text ) ) {
			$text = self::default_attestation_text( $course_title );
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error( 'cta_attestation_text', __( 'Attestation text is required.', 'cta-lms' ) );
		}

		if ( '' === trim( $signature ) || strlen( trim( $signature ) ) < 2 ) {
			return new WP_Error(
				'cta_attestation_signature',
				__( 'Please complete the Typed Name field to electronically sign this attestation.', 'cta-lms' )
			);
		}

		// Normalize / validate signature date (Y-m-d). Default to site "today".
		if ( '' === $sig_date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sig_date ) ) {
			$sig_date = current_time( 'Y-m-d' );
		} else {
			$parts = array_map( 'intval', explode( '-', $sig_date ) );
			if ( count( $parts ) !== 3 || ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
				return new WP_Error(
					'cta_attestation_date',
					__( 'Please enter a valid date for this attestation.', 'cta-lms' )
				);
			}
		}

		self::maybe_add_signature_date_column();

		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 500 ) ) ) : '';
		$existing   = self::get( $user_id, $course_id );
		$table      = self::table_name();
		$now        = current_time( 'mysql' );

		$row = array(
			'user_id'          => $user_id,
			'course_id'        => $course_id,
			'course_title'     => $course_title,
			'student_name'     => $signature,
			'signature_date'   => $sig_date,
			'attestation_text' => $text,
			'ip_address'       => $ip_address,
			'user_agent'       => $user_agent,
			'attested_at'      => $now,
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->update(
				$table,
				$row,
				array( 'id' => (int) $existing->id ),
				$formats,
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ok = $wpdb->insert( $table, $row, $formats );
		}

		if ( false === $ok ) {
			return new WP_Error( 'cta_attestation_save', __( 'Could not save attestation.', 'cta-lms' ) );
		}

		return true;
	}
}

}
