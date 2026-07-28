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
	 * Create the attestation table.
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
	}

	/**
	 * Standard attestation paragraph students must agree to.
	 *
	 * @return string
	 */
	public static function default_attestation_text() {
		return __(
			'I attest that I am the person whose name appears on this enrollment and that I personally completed this asynchronous distance-learning course. I did not receive unauthorized assistance, share login credentials, or have another person complete course activities, the evaluation, or the final exam on my behalf. I understand that false attestation may result in denial of continuing education credit and revocation of my certificate.',
			'cta-lms'
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
		return null !== self::get( $user_id, $course_id );
	}

	/**
	 * Resolve display name for a WordPress user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	private static function get_student_display_name( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		$name = trim( $user->display_name );
		if ( '' === $name ) {
			$name = trim( $user->first_name . ' ' . $user->last_name );
		}
		if ( '' === $name ) {
			$name = (string) $user->user_login;
		}

		return sanitize_text_field( $name );
	}

	/**
	 * Submit or update attestation for a user and course.
	 *
	 * The compliance statement is always the platform default (or an explicit
	 * override). Students must type their full legal name as an electronic
	 * signature — that typed name is what we persist as student_name.
	 *
	 * @param int    $user_id        WordPress user ID.
	 * @param int    $course_id      Course ID.
	 * @param string $text           Attestation statement (optional; defaults applied).
	 * @param string $signature_name Typed full legal name / electronic signature.
	 * @return true|WP_Error
	 */
	public static function submit( $user_id, $course_id, $text = '', $signature_name = '' ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$text      = sanitize_textarea_field( wp_unslash( (string) $text ) );
		$signature = sanitize_text_field( wp_unslash( (string) $signature_name ) );

		if ( ! $user_id || ! $course_id ) {
			return new WP_Error( 'cta_attestation_invalid', __( 'Invalid attestation request.', 'cta-lms' ) );
		}

		// Never block on missing statement text — always fall back to the CE standard wording.
		if ( '' === trim( $text ) ) {
			$text = self::default_attestation_text();
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error( 'cta_attestation_text', __( 'Attestation text is required.', 'cta-lms' ) );
		}

		if ( '' === trim( $signature ) || strlen( trim( $signature ) ) < 2 ) {
			return new WP_Error(
				'cta_attestation_signature',
				__( 'Please type your full legal name as your electronic signature to complete this attestation.', 'cta-lms' )
			);
		}

		$course = CTA_Database::get_course( $course_id );
		if ( ! $course ) {
			return new WP_Error( 'cta_attestation_course', __( 'Course not found.', 'cta-lms' ) );
		}

		$course_title = sanitize_text_field( (string) $course->title );
		$ip_address   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$user_agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 500 ) ) ) : '';
		$existing     = self::get( $user_id, $course_id );
		$table        = self::table_name();
		$now          = current_time( 'mysql' );

		$row = array(
			'user_id'          => $user_id,
			'course_id'        => $course_id,
			'course_title'     => $course_title,
			'student_name'     => $signature,
			'attestation_text' => $text,
			'ip_address'       => $ip_address,
			'user_agent'       => $user_agent,
			'attested_at'      => $now,
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

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
