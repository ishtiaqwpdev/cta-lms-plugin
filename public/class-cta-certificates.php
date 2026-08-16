<?php
/**
 * CE certificate generation and retrieval.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Certificates
 */
if ( ! class_exists( 'CTA_Certificates' ) ) {

class CTA_Certificates {

	/**
	 * Generate certificate for a completed course.
	 *
	 * Certificates are issued only after:
	 * - all instructional modules are completed
	 * - final exam is passed
	 * - course evaluation is submitted
	 * - course-completion attestation is submitted
	 *
	 * @param int         $user_id   WordPress user ID.
	 * @param int         $course_id Course ID.
	 * @param object|null $evaluation Optional evaluation row (avoids a second lookup).
	 * @return object|null
	 */
	public static function generate( $user_id, $course_id, $evaluation = null ) {
		$existing = self::get_certificate( $user_id, $course_id );

		// Already issued: refresh HTML so license/logo stay current, keep certificate number.
		if ( $existing ) {
			self::refresh_file( $existing );
			return self::get_certificate( $user_id, $course_id );
		}

		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		$course     = CTA_Database::get_course( $course_id );

		if ( ! $enrollment || ! $course ) {
			return null;
		}

		// Exam Preparation Programs never issue CE certificates.
		if ( class_exists( 'CTA_Exam_Access' ) && ( CTA_Exam_Access::is_exam_prep( $course ) || ! CTA_Exam_Access::has_ce_certificate( $course ) ) ) {
			return null;
		}

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			$seq = CTA_CE_Completion::assert_can_issue_certificate( $user_id, $course_id );
			if ( is_wp_error( $seq ) ) {
				return null;
			}
		} else {
			if ( ! self::user_completed_all_modules( $user_id, $course_id, $enrollment ) ) {
				return null;
			}

			if ( ! self::user_passed_final_exam( $user_id, $course_id ) ) {
				return null;
			}

			if ( null === $evaluation ) {
				$evaluation = CTA_Database::get_course_evaluation( $user_id, $course_id );
			}

			if ( ! $evaluation ) {
				return null;
			}

			if ( class_exists( 'CTA_Course_Attestation' ) && ! CTA_Course_Attestation::has( $user_id, $course_id ) ) {
				return null;
			}
		}

		if ( null === $evaluation ) {
			$evaluation = CTA_Database::get_course_evaluation( $user_id, $course_id );
		}

		if ( ! $evaluation ) {
			return null;
		}

		global $wpdb;

		$issued_at = current_time( 'mysql' );

		$wpdb->update(
			$wpdb->prefix . 'cta_enrollments',
			array(
				'status'       => 'completed',
				'progress'     => 100,
				'completed_at' => $issued_at,
			),
			array( 'id' => (int) $enrollment->id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		$certificate_number = self::create_certificate_number();
		$paths              = self::build_file_paths( $user_id, $course_id, $certificate_number );

		if ( is_wp_error( $paths ) ) {
			return null;
		}

		$html = self::build_html(
			$user_id,
			$course,
			$certificate_number,
			$issued_at,
			$evaluation
		);

		if ( '' === $html ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $paths['file_path'], $html ) ) {
			return null;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'cta_certificates',
			array(
				'user_id'            => $user_id,
				'course_id'          => (int) $course_id,
				'enrollment_id'      => (int) $enrollment->id,
				'certificate_number' => $certificate_number,
				'issued_at'          => $issued_at,
				'file_path'          => $paths['file_path'],
				'file_url'           => $paths['file_url'],
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return null;
		}

		$certificate = (object) array(
			'id'                 => (int) $wpdb->insert_id,
			'user_id'            => $user_id,
			'course_id'          => (int) $course_id,
			'enrollment_id'      => (int) $enrollment->id,
			'certificate_number' => $certificate_number,
			'issued_at'          => $issued_at,
			'file_path'          => $paths['file_path'],
			'file_url'           => $paths['file_url'],
		);

		CTA_Emails::send(
			'certificate_ready',
			$user_id,
			array(
				'course'      => $course,
				'certificate' => $certificate,
			)
		);

		return $certificate;
	}

	/**
	 * Whether the learner completed every instructional module for the course.
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $course_id  Course ID.
	 * @param object|null $enrollment Optional enrollment row.
	 * @return bool
	 */
	public static function user_completed_all_modules( $user_id, $course_id, $enrollment = null ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		if ( null === $enrollment ) {
			$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		}

		if ( ! $enrollment ) {
			return false;
		}

		$modules = CTA_Database::get_course_modules( $course_id );
		if ( empty( $modules ) ) {
			return (int) $enrollment->progress >= 100;
		}

		$completed = array();
		if ( ! empty( $enrollment->modules_completed ) ) {
			$decoded = json_decode( (string) $enrollment->modules_completed, true );
			if ( is_array( $decoded ) ) {
				$completed = array_map( 'absint', $decoded );
			}
		}

		foreach ( $modules as $module ) {
			if ( ! in_array( (int) $module->id, $completed, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the learner has a passing final exam attempt.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function user_passed_final_exam( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$quiz = CTA_Database::get_quiz_by_course( $course_id );
		if ( ! $quiz ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$passed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$wpdb->prefix}cta_quiz_attempts
				WHERE user_id = %d AND quiz_id = %d AND passed = 1 AND completed_at IS NOT NULL",
				$user_id,
				(int) $quiz->id
			)
		);

		return $passed > 0;
	}

	/**
	 * Rebuild a certificate HTML file with the student's current license + logo.
	 *
	 * Fixes stale "N/A" license lines when the student (or admin) updates
	 * Account Settings after the certificate was first issued.
	 *
	 * @param object $certificate Certificate DB row.
	 * @return bool True when the file was rewritten.
	 */
	public static function refresh_file( $certificate ) {
		if ( ! $certificate || empty( $certificate->id ) ) {
			return false;
		}

		$user_id   = absint( $certificate->user_id );
		$course_id = absint( $certificate->course_id );
		$course    = CTA_Database::get_course( $course_id );

		if ( ! $user_id || ! $course ) {
			return false;
		}

		if ( ! self::is_ce_certificate_course( $course ) ) {
			return false;
		}

		$evaluation = CTA_Database::get_course_evaluation( $user_id, $course_id );
		$issued_at  = ! empty( $certificate->issued_at ) ? (string) $certificate->issued_at : current_time( 'mysql' );
		$number     = (string) $certificate->certificate_number;

		$html = self::build_html( $user_id, $course, $number, $issued_at, $evaluation );

		if ( '' === $html ) {
			return false;
		}

		$file_path = ! empty( $certificate->file_path ) ? (string) $certificate->file_path : '';

		if ( '' === $file_path || ! file_exists( dirname( $file_path ) ) ) {
			$paths = self::build_file_paths( $user_id, $course_id, $number );
			if ( is_wp_error( $paths ) ) {
				return false;
			}
			$file_path = $paths['file_path'];

			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'cta_certificates',
				array(
					'file_path' => $paths['file_path'],
					'file_url'  => $paths['file_url'],
				),
				array( 'id' => (int) $certificate->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $file_path, $html );
	}

	/**
	 * Refresh every certificate file for a user (after license edits).
	 *
	 * @param int $user_id User ID.
	 * @return int Number of files refreshed.
	 */
	public static function refresh_user_certificates( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cta_certificates WHERE user_id = %d",
				$user_id
			)
		);

		$count = 0;
		foreach ( (array) $rows as $row ) {
			if ( self::refresh_file( $row ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Rebuild every stored certificate HTML file (timezone / template updates).
	 *
	 * @param int $limit Max rows to refresh (0 = all).
	 * @return int Number of files refreshed.
	 */
	public static function refresh_all_certificates( $limit = 0 ) {
		global $wpdb;

		$limit = absint( $limit );
		$sql   = "SELECT * FROM {$wpdb->prefix}cta_certificates ORDER BY id ASC";
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $sql );

		$count = 0;
		foreach ( (array) $rows as $row ) {
			if ( self::refresh_file( $row ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Format the certificate issue / completion datetime for print and email.
	 *
	 * Uses the stored issue instant only (never "now"). Always displays in
	 * America/Los_Angeles (PST/PDT) via cta_lms_format_certificate_issued_at() —
	 * never the learner browser zone, PHP date.timezone, or WP General timezone
	 * (those produced PKT / Asia/Karachi labels on certificates).
	 *
	 * @param string      $issued_at   MySQL datetime from the certificate row.
	 * @param object|null $evaluation  Evaluation row (optional; submitted_at fallback).
	 * @return string
	 */
	public static function format_issue_date( $issued_at, $evaluation = null ) {
		$issue_source = trim( (string) $issued_at );
		if (
			( '' === $issue_source || '0000-00-00 00:00:00' === $issue_source )
			&& $evaluation
			&& ! empty( $evaluation->submitted_at )
		) {
			$issue_source = (string) $evaluation->submitted_at;
		}

		if ( '' === $issue_source || '0000-00-00 00:00:00' === $issue_source ) {
			return '';
		}

		if ( function_exists( 'cta_lms_format_certificate_issued_at' ) ) {
			return cta_lms_format_certificate_issued_at( $issue_source );
		}

		// Fallback if helpers failed to load — still force Los Angeles.
		try {
			$la     = new DateTimeZone( 'America/Los_Angeles' );
			$parsed = function_exists( 'cta_lms_parse_datetime' )
				? cta_lms_parse_datetime( $issue_source )
				: new DateTimeImmutable( $issue_source, $la );
			if ( ! $parsed ) {
				$parsed = new DateTimeImmutable( 'now', $la );
			}
			$dt   = $parsed->setTimezone( $la );
			$abbr = ( '1' === $dt->format( 'I' ) ) ? 'PDT' : 'PST';
			return $dt->format( 'F j, Y' ) . ' at ' . $dt->format( 'g:i A' ) . ' ' . $abbr;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Decode syllabus_meta JSON from a course row.
	 *
	 * @param object|null $course Course row.
	 * @return array<string,mixed>
	 */
	public static function decode_course_syllabus_meta( $course ) {
		if ( ! $course || empty( $course->syllabus_meta ) ) {
			return array();
		}

		$decoded = json_decode( (string) $course->syllabus_meta, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Resolve learner license type from evaluation responses or user meta.
	 *
	 * @param int         $user_id    User ID.
	 * @param object|null $evaluation Evaluation row.
	 * @return string
	 */
	public static function resolve_license_type( $user_id, $evaluation = null ) {
		$user_id = absint( $user_id );
		$keys    = array( 'participant_license_type', 'camft_participant_license_type' );

		if ( $evaluation && ! empty( $evaluation->responses ) ) {
			$responses = json_decode( (string) $evaluation->responses, true );
			if ( is_array( $responses ) ) {
				foreach ( $keys as $key ) {
					if ( ! empty( $responses[ $key ] ) && is_string( $responses[ $key ] ) ) {
						return sanitize_text_field( $responses[ $key ] );
					}
				}
			}
		}

		if ( $user_id ) {
			$stored = (string) get_user_meta( $user_id, 'cta_license_type', true );
			if ( '' !== trim( $stored ) ) {
				return sanitize_text_field( $stored );
			}
		}

		return '';
	}

	/**
	 * Build certificate display fields from course metadata and evaluation data.
	 *
	 * @param object      $course     Course row.
	 * @param int         $user_id    User ID.
	 * @param object|null $evaluation Evaluation row.
	 * @return array<string,string|bool>
	 */
	public static function get_certificate_display_fields( $course, $user_id, $evaluation = null ) {
		$meta = self::decode_course_syllabus_meta( $course );

		$course_title = (string) ( $course->title ?? '' );
		$cert_title   = trim( (string) ( $meta['certificate_title'] ?? '' ) );
		if ( '' === $cert_title ) {
			$cert_title = $course_title;
		}

		$course_code = trim( (string) ( $meta['course_code'] ?? '' ) );
		$code_status = trim( (string) ( $meta['course_code_status'] ?? '' ) );
		$provisional = '' !== $code_status && false === stripos( $code_status, 'approved' );

		$license_type   = self::resolve_license_type( $user_id, $evaluation );
		$license_number = cta_lms_get_user_license_number( $user_id );
		if ( $evaluation && ! empty( $evaluation->responses ) ) {
			$responses = json_decode( (string) $evaluation->responses, true );
			if ( is_array( $responses ) ) {
				foreach ( array( 'participant_license_number', 'camft_participant_license_number' ) as $key ) {
					if ( ! empty( $responses[ $key ] ) && is_string( $responses[ $key ] ) ) {
						$license_number = sanitize_text_field( $responses[ $key ] );
						break;
					}
				}
			}
		}

		$license_display = '';
		if ( '' !== $license_type && '' !== $license_number ) {
			$license_display = $license_type . ' — ' . $license_number;
		} elseif ( '' !== $license_type ) {
			$license_display = $license_type;
		} elseif ( '' !== $license_number ) {
			$license_display = $license_number;
		}

		return array(
			'course_title'          => $course_title,
			'certificate_title'     => $cert_title,
			'course_code'           => $course_code,
			'course_code_provisional' => $provisional,
			'course_code_status'    => $code_status,
			'instructional_method'  => trim( (string) ( $meta['instructional_method'] ?? '' ) ),
			'presenter'               => trim( (string) ( $meta['presenter'] ?? '' ) ),
			'provider'                => trim( (string) ( $meta['provider'] ?? '' ) ),
			'completion_statement'    => trim( (string) ( $meta['certificate_completion_statement'] ?? '' ) ),
			'license_type'          => $license_type,
			'license_number'        => $license_number,
			'license_display'       => $license_display,
		);
	}

	/**
	 * Build certificate HTML from the template with live user/course data.
	 *
	 * @param int         $user_id             User ID.
	 * @param object      $course              Course row.
	 * @param string      $certificate_number  Certificate number.
	 * @param string      $issued_at           MySQL datetime.
	 * @param object|null $evaluation          Evaluation row (optional).
	 * @param array       $args                Optional flags: auto_print (bool), download_url (string), for_pdf (bool).
	 * @return string
	 */
	public static function build_html( $user_id, $course, $certificate_number, $issued_at, $evaluation = null, $args = array() ) {
		if ( ! self::is_ce_certificate_course( $course ) ) {
			return '';
		}

		$user = get_userdata( $user_id );

		// Always print the real certificate issue instant from storage.
		$completion_date = self::format_issue_date( $issued_at, $evaluation );
		$display         = self::get_certificate_display_fields( $course, $user_id, $evaluation );
		$course_title    = (string) ( $display['certificate_title'] ?? $course->title );
		$ce_hours        = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
		if ( '' === $ce_hours ) {
			$ce_hours = '0';
		}

		$student_name = function_exists( 'cta_lms_get_user_legal_name' )
			? cta_lms_get_user_legal_name( $user_id )
			: ( $user ? $user->display_name : '' );
		if ( '' === $student_name && $evaluation && ! empty( $evaluation->student_name ) ) {
			$student_name = (string) $evaluation->student_name;
		}
		if ( '' === $student_name ) {
			$student_name = __( 'Student', 'cta-lms' );
		}

		$license_number  = ! empty( $display['license_number'] )
			? (string) $display['license_number']
			: cta_lms_get_user_license_number( $user_id );
		$course_code             = (string) ( $display['course_code'] ?? '' );
		$course_code_provisional = ! empty( $display['course_code_provisional'] );
		$course_code_status      = (string) ( $display['course_code_status'] ?? '' );
		$instructional_method    = (string) ( $display['instructional_method'] ?? '' );
		$presenter               = (string) ( $display['presenter'] ?? '' );
		$completion_statement    = (string) ( $display['completion_statement'] ?? '' );
		$license_type            = (string) ( $display['license_type'] ?? '' );
		$provider_name    = self::get_provider_name();
		$provider_number  = self::get_provider_number();
		$provider_line    = self::get_provider_line();
		$provider_address       = self::get_provider_address();
		$provider_address_lines = self::get_provider_address_lines();
		$cepa_stamp_url         = self::get_cepa_stamp_data_uri();

		$header_text = (string) get_option( 'cta_certificate_header_text', '' );
		if ( '' === $header_text ) {
			$header_text = __( 'Certificate of Completion', 'cta-lms' );
		}

		$footer_text = (string) get_option( 'cta_certificate_footer_text', '' );
		if ( '' === $footer_text ) {
			$footer_text = 'clinicaltrainingacademy.com';
		}

		$signature_name = (string) get_option( 'cta_certificate_signature_name', '' );
		if ( '' === $signature_name ) {
			$signature_name = (string) get_option( 'cta_admin_name', '' );
		}
		if ( '' === $signature_name ) {
			$signature_name = 'Candice Fuimaono, MS, LMFT';
		}

		$organization_name   = $provider_name;
		$administrator_title = __( 'Program Administrator', 'cta-lms' );
		$logo_url            = self::get_logo_data_uri();
		if ( '' === $logo_url ) {
			$logo_url = cta_lms_get_logo_url();
		}
		$signature_url = self::get_signature_data_uri();
		$auto_print   = ! empty( $args['auto_print'] );
		$download_url = ! empty( $args['download_url'] ) ? (string) $args['download_url'] : '';
		$for_pdf      = ! empty( $args['for_pdf'] );

		ob_start();
		// PDF uses a Dompdf-safe twin of the same visual design; on-screen print HTML stays unchanged.
		if ( $for_pdf ) {
			include CTA_PLUGIN_DIR . 'templates/certificate-pdf.php';
		} else {
			include CTA_PLUGIN_DIR . 'templates/certificate.php';
		}
		return (string) ob_get_clean();
	}

	/**
	 * Whether a course belongs to the CE certificate workflow.
	 *
	 * This guard is intentionally repeated at render/download time so a legacy
	 * certificate row can never apply CEPA branding to an Exam Prep program.
	 *
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function is_ce_certificate_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		if ( class_exists( 'CTA_Exam_Access' ) ) {
			return ! CTA_Exam_Access::is_exam_prep( $course )
				&& CTA_Exam_Access::has_ce_certificate( $course );
		}

		return ! isset( $course->has_ce_certificate ) || ! empty( $course->has_ce_certificate );
	}

	/**
	 * Official CAMFT CEPA provider name for CE certificates.
	 *
	 * @return string
	 */
	public static function get_provider_name() {
		return 'Clinical Training & Supervision Academy';
	}

	/**
	 * Official CAMFT CEPA provider number.
	 *
	 * @return string
	 */
	public static function get_provider_number() {
		return '#122418';
	}

	/**
	 * Default CE provider mailing address (street + city; org name is shown separately).
	 *
	 * @return string
	 */
	public static function get_default_provider_address() {
		return "6296 Magnolia Ave #1077\nRiverside, CA 92506";
	}

	/**
	 * Configured provider mailing address for CE certificates.
	 *
	 * Falls back to the approved Riverside business address when unset.
	 *
	 * @return string
	 */
	public static function get_provider_address() {
		$address = trim( (string) get_option( 'cta_certificate_provider_address', '' ) );
		if ( '' === $address ) {
			$address = self::get_default_provider_address();
		}

		return $address;
	}

	/**
	 * Provider mailing address as printable lines (no duplicate org name).
	 *
	 * @return array<int,string>
	 */
	public static function get_provider_address_lines() {
		$lines = preg_split( '/\r\n|\r|\n/', self::get_provider_address() );
		$lines = array_values(
			array_filter(
				array_map(
					static function ( $line ) {
						return trim( (string) $line );
					},
					(array) $lines
				)
			)
		);

		if ( empty( $lines ) ) {
			return array();
		}

		$provider_variants = array(
			self::get_provider_name(),
			'Clinical Training and Supervision Academy',
			'Clinical Training & Supervision Academy',
		);

		foreach ( $provider_variants as $variant ) {
			if ( 0 === strcasecmp( (string) $lines[0], $variant ) ) {
				array_shift( $lines );
				break;
			}
		}

		return $lines;
	}

	/**
	 * Master CE certificate provider line (CAMFT-approved wording).
	 *
	 * @return string
	 */
	public static function get_provider_line() {
		return 'CAMFT-Approved Continuing Education Provider ' . self::get_provider_number();
	}

	/**
	 * Official CAMFT CEPA approval stamp as a data URI.
	 *
	 * The bundled source image is embedded byte-for-byte; templates constrain
	 * only its rendered box while preserving the intrinsic aspect ratio.
	 *
	 * @return string
	 */
	public static function get_cepa_stamp_data_uri() {
		return self::path_to_data_uri( CTA_PLUGIN_DIR . 'assets/img/CEPA_R_Stamp.png' );
	}

	/**
	 * Absolute paths checked for the bundled administrator signature image.
	 *
	 * @return array<int,string>
	 */
	public static function get_bundled_signature_paths() {
		$base = CTA_PLUGIN_DIR . 'assets/img/';
		return array(
			$base . 'certificate-signature.png',
			$base . 'certificate-signature.jpg',
			$base . 'certificate-signature.jpeg',
			$base . 'certificate-signature.webp',
		);
	}

	/**
	 * Electronic signature image as a data URI for print/PDF embedding.
	 *
	 * Prefers the bundled plugin asset, then Media Library / settings URL.
	 *
	 * @return string data: URI or empty string.
	 */
	public static function get_signature_data_uri() {
		foreach ( self::get_bundled_signature_paths() as $path ) {
			$uri = self::path_to_data_uri( $path );
			if ( '' !== $uri ) {
				return $uri;
			}
		}

		$url = (string) get_option( 'cta_certificate_signature_image_url', '' );
		$url = esc_url_raw( trim( $url ) );
		if ( '' === $url ) {
			return '';
		}

		$path = '';

		if ( 0 === strpos( $url, CTA_PLUGIN_URL ) ) {
			$relative  = substr( $url, strlen( CTA_PLUGIN_URL ) );
			$candidate = CTA_PLUGIN_DIR . ltrim( $relative, '/\\' );
			if ( file_exists( $candidate ) ) {
				$path = $candidate;
			}
		}

		if ( '' === $path ) {
			$upload = wp_upload_dir();
			if ( empty( $upload['error'] ) && 0 === strpos( $url, $upload['baseurl'] ) ) {
				$relative  = substr( $url, strlen( $upload['baseurl'] ) );
				$candidate = trailingslashit( $upload['basedir'] ) . ltrim( $relative, '/\\' );
				if ( file_exists( $candidate ) ) {
					$path = $candidate;
				}
			}
		}

		if ( '' === $path && function_exists( 'attachment_url_to_postid' ) ) {
			$att_id = absint( attachment_url_to_postid( $url ) );
			if ( $att_id ) {
				$attached = get_attached_file( $att_id );
				if ( $attached && file_exists( $attached ) ) {
					$path = $attached;
				}
			}
		}

		if ( '' !== $path ) {
			return self::path_to_data_uri( $path );
		}

		// Remote fallback for Dompdf (same pattern as logo).
		if ( preg_match( '#^https?://#i', $url ) && function_exists( 'wp_remote_get' ) ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 15,
					'redirection' => 3,
				)
			);
			if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$bytes = (string) wp_remote_retrieve_body( $response );
				if ( '' !== $bytes ) {
					$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
					$type         = 'image/png';
					if ( preg_match( '#^(image/[a-z0-9.+-]+)#i', $content_type, $m ) ) {
						$type = strtolower( $m[1] );
					} elseif ( preg_match( '/\.jpe?g(\?|$)/i', $url ) ) {
						$type = 'image/jpeg';
					} elseif ( preg_match( '/\.webp(\?|$)/i', $url ) ) {
						$type = 'image/webp';
					}
					return 'data:' . $type . ';base64,' . base64_encode( $bytes );
				}
			}
		}

		return '';
	}

	/**
	 * Convert a local image path to a data URI.
	 *
	 * @param string $path Absolute filesystem path.
	 * @return string
	 */
	private static function path_to_data_uri( $path ) {
		$path = (string) $path;
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}

		$mime = function_exists( 'wp_check_filetype' ) ? wp_check_filetype( $path ) : array();
		$type = ! empty( $mime['type'] ) ? $mime['type'] : 'image/png';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			return '';
		}

		return 'data:' . $type . ';base64,' . base64_encode( $bytes );
	}

	/**
	 * Embed the CTA logo as a data URI so print/PDF output never depends on a remote URL.
	 *
	 * @return string data: URI or empty string.
	 */
	public static function get_logo_data_uri() {
		$url = cta_lms_get_logo_url();
		if ( '' === $url ) {
			return '';
		}

		$path = '';

		// Local plugin asset.
		if ( 0 === strpos( $url, CTA_PLUGIN_URL ) ) {
			$relative = substr( $url, strlen( CTA_PLUGIN_URL ) );
			$candidate = CTA_PLUGIN_DIR . ltrim( $relative, '/\\' );
			if ( file_exists( $candidate ) ) {
				$path = $candidate;
			}
		}

		// Uploads / Media Library.
		if ( '' === $path ) {
			$upload = wp_upload_dir();
			if ( empty( $upload['error'] ) && 0 === strpos( $url, $upload['baseurl'] ) ) {
				$relative = substr( $url, strlen( $upload['baseurl'] ) );
				$candidate = trailingslashit( $upload['basedir'] ) . ltrim( $relative, '/\\' );
				if ( file_exists( $candidate ) ) {
					$path = $candidate;
				}
			}
		}

		// Attachment ID from known URL.
		if ( '' === $path && function_exists( 'attachment_url_to_postid' ) ) {
			$att_id = absint( attachment_url_to_postid( $url ) );
			if ( $att_id ) {
				$attached = get_attached_file( $att_id );
				if ( $attached && file_exists( $attached ) ) {
					$path = $attached;
				}
			}
		}

		if ( '' === $path || ! is_readable( $path ) ) {
			// Last resort: fetch a remote/custom logo so Dompdf can embed without isRemoteEnabled.
			if ( preg_match( '#^https?://#i', $url ) && function_exists( 'wp_remote_get' ) ) {
				$response = wp_remote_get(
					$url,
					array(
						'timeout'     => 15,
						'redirection' => 3,
					)
				);
				if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
					$bytes = (string) wp_remote_retrieve_body( $response );
					if ( '' !== $bytes ) {
						$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
						$type         = 'image/png';
						if ( preg_match( '#^(image/[a-z0-9.+-]+)#i', $content_type, $m ) ) {
							$type = strtolower( $m[1] );
						} elseif ( preg_match( '/\.svg(\?|$)/i', $url ) ) {
							$type = 'image/svg+xml';
						} elseif ( preg_match( '/\.jpe?g(\?|$)/i', $url ) ) {
							$type = 'image/jpeg';
						} elseif ( preg_match( '/\.webp(\?|$)/i', $url ) ) {
							$type = 'image/webp';
						}

						if ( 0 === strpos( $type, 'image/svg' ) && function_exists( 'imagecreatetruecolor' ) ) {
							$raster = self::rasterize_svg_logo( $bytes, 440, 104 );
							if ( is_string( $raster ) && '' !== $raster ) {
								return 'data:image/png;base64,' . base64_encode( $raster );
							}
						}

						return 'data:' . $type . ';base64,' . base64_encode( $bytes );
					}
				}
			}

			return '';
		}

		$mime = wp_check_filetype( $path );
		$type = ! empty( $mime['type'] ) ? $mime['type'] : 'image/png';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			return '';
		}

		// Prefer PNG/JPEG for Dompdf; if Imagick is present, rasterize SVG logos.
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'svg', 'svgz' ), true ) && class_exists( 'Imagick' ) ) {
			$raster = self::rasterize_svg_logo( $bytes, 440, 104 );
			if ( is_string( $raster ) && '' !== $raster ) {
				return 'data:image/png;base64,' . base64_encode( $raster );
			}
		}

		return 'data:' . $type . ';base64,' . base64_encode( $bytes );
	}

	/**
	 * Rasterize an SVG logo to PNG bytes (Imagick) for more reliable Dompdf embedding.
	 *
	 * @param string $svg_bytes Raw SVG.
	 * @param int    $width     Target width.
	 * @param int    $height    Target height.
	 * @return string|null PNG binary or null.
	 */
	private static function rasterize_svg_logo( $svg_bytes, $width = 440, $height = 104 ) {
		try {
			$img = new Imagick();
			$img->setBackgroundColor( new ImagickPixel( 'white' ) );
			$img->readImageBlob( $svg_bytes );
			$img->setImageFormat( 'png' );
			$img->resizeImage( (int) $width, (int) $height, Imagick::FILTER_LANCZOS, 1, true );
			$png = $img->getImageBlob();
			$img->clear();
			$img->destroy();
			return is_string( $png ) ? $png : null;
		} catch ( Exception $e ) {
			return null;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	/**
	 * Gated print / Save-as-PDF URL for a certificate.
	 *
	 * Returns a raw URL (not HTML-escaped). Do not pass through esc_html / wp_nonce_url
	 * before using in JS window.open() or JSON responses — those expect literal & separators.
	 *
	 * @param int  $certificate_id Certificate ID.
	 * @param bool $auto_print     Whether to open the print dialog automatically.
	 * @return string
	 */
	public static function get_print_url( $certificate_id, $auto_print = true ) {
		$extra = array();
		if ( $auto_print ) {
			$extra['print'] = 1;
		}
		return self::get_action_url( $certificate_id, $extra );
	}

	/**
	 * Gated file-download URL for a certificate.
	 *
	 * @param int $certificate_id Certificate ID.
	 * @return string
	 */
	public static function get_download_url( $certificate_id ) {
		return self::get_action_url( $certificate_id, array( 'download' => 1 ) );
	}

	/**
	 * Build a nonce-protected frontend URL for certificate actions.
	 *
	 * Uses the site front door (not /wp-admin/) so Associates and other
	 * learner roles are not blocked by the admin-dashboard gate.
	 *
	 * @param int   $certificate_id Certificate ID.
	 * @param array $extra          Extra query args (print, download).
	 * @return string
	 */
	private static function get_action_url( $certificate_id, $extra = array() ) {
		$certificate_id = absint( $certificate_id );
		if ( ! $certificate_id ) {
			return '';
		}

		$args = array_merge(
			array(
				'cta_certificate' => $certificate_id,
				'_wpnonce'        => wp_create_nonce( 'cta_print_certificate_' . $certificate_id ),
			),
			is_array( $extra ) ? $extra : array()
		);

		if ( empty( $args['print'] ) ) {
			unset( $args['print'] );
		}
		if ( empty( $args['download'] ) ) {
			unset( $args['download'] );
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Catch frontend certificate print/download requests early on init.
	 *
	 * Query shape: /?cta_certificate={id}&_wpnonce=...&print=1|download=1
	 */
	public static function maybe_handle_frontend_request() {
		if ( empty( $_GET['cta_certificate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		self::handle_print_request();
	}

	/**
	 * Stream a print-ready certificate (landscape, single page) for Print → Save as PDF,
	 * or force-download a real PDF file when ?download=1.
	 *
	 * Access control: the logged-in user must own the certificate (or be an admin).
	 * No manage_options requirement for the owner.
	 */
	public static function handle_print_request() {
		$certificate_id = absint( wp_unslash( $_GET['cta_certificate'] ?? $_GET['certificate_id'] ?? 0 ) );

		if (
			! $certificate_id
			|| ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cta_print_certificate_' . $certificate_id )
		) {
			wp_die( esc_html__( 'Invalid certificate request.', 'cta-lms' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		$certificate = CTA_Database::get_certificate( $certificate_id );
		$user_id     = get_current_user_id();

		// Ownership check — learners may only open their own certificate.
		$is_owner = $certificate && (int) $certificate->user_id === (int) $user_id;
		$is_admin = current_user_can( 'manage_options' );

		if ( ! $certificate || ( ! $is_owner && ! $is_admin ) ) {
			wp_die( esc_html__( 'Certificate not found.', 'cta-lms' ), 404 );
		}

		self::refresh_file( $certificate );
		$certificate = CTA_Database::get_certificate( $certificate_id );
		$course      = CTA_Database::get_course( (int) $certificate->course_id );
		$evaluation  = CTA_Database::get_course_evaluation( (int) $certificate->user_id, (int) $certificate->course_id );

		if ( ! $course ) {
			wp_die( esc_html__( 'Course not found.', 'cta-lms' ), 404 );
		}

		if ( ! self::is_ce_certificate_course( $course ) ) {
			wp_die( esc_html__( 'CE certificates are not available for Exam Preparation programs.', 'cta-lms' ), 404 );
		}

		$is_download = ! empty( $_GET['download'] );
		$number      = (string) $certificate->certificate_number;

		// Learner download: real PDF named by verification number (e.g. CTA-2026-151748.pdf).
		if ( $is_download ) {
			$pdf = self::build_pdf(
				(int) $certificate->user_id,
				$course,
				$number,
				(string) $certificate->issued_at,
				$evaluation
			);

			if ( is_wp_error( $pdf ) ) {
				wp_die( esc_html( $pdf->get_error_message() ), 500 );
			}

			$filename = sanitize_file_name( $number ) . '.pdf';
			if ( ! preg_match( '/\.pdf$/i', $filename ) ) {
				$filename .= '.pdf';
			}

			nocache_headers();
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . (string) strlen( $pdf ) );
			header( 'X-Content-Type-Options: nosniff' );
			echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF
			exit;
		}

		// Inline/print view remains the existing HTML certificate (unchanged design).
		$html = self::build_html(
			(int) $certificate->user_id,
			$course,
			$number,
			(string) $certificate->issued_at,
			$evaluation,
			array(
				'auto_print'   => ! empty( $_GET['print'] ),
				'download_url' => self::get_download_url( $certificate_id ),
			)
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header(
			sprintf(
				'Content-Disposition: inline; filename="%s"',
				sanitize_file_name( $number ) . '.html'
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Whether Dompdf is available for server-side PDF generation.
	 *
	 * @return bool
	 */
	public static function can_generate_pdf() {
		return class_exists( '\Dompdf\Dompdf' );
	}

	/**
	 * Build PDF binary from the certificate design (landscape, one page).
	 *
	 * Uses Dompdf with the existing certificate visual language (colors, logo,
	 * typography, borders). Requires vendor/dompdf via Composer.
	 *
	 * @param int         $user_id            User ID.
	 * @param object      $course             Course row.
	 * @param string      $certificate_number Certificate number.
	 * @param string      $issued_at          MySQL datetime.
	 * @param object|null $evaluation         Evaluation row (optional).
	 * @return string|WP_Error PDF binary or error.
	 */
	public static function build_pdf( $user_id, $course, $certificate_number, $issued_at, $evaluation = null ) {
		if ( ! self::is_ce_certificate_course( $course ) ) {
			return new WP_Error(
				'cta_certificate_scope',
				__( 'CE certificates are not available for Exam Preparation programs.', 'cta-lms' )
			);
		}

		if ( ! self::can_generate_pdf() ) {
			return new WP_Error(
				'cta_pdf_unavailable',
				__( 'PDF generation is not available. Please run composer install (dompdf) on the server.', 'cta-lms' )
			);
		}

		$html = self::build_html(
			$user_id,
			$course,
			$certificate_number,
			$issued_at,
			$evaluation,
			array(
				'for_pdf' => true,
			)
		);

		if ( '' === $html ) {
			return new WP_Error( 'cta_pdf_empty', __( 'Unable to build certificate PDF.', 'cta-lms' ) );
		}

		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false );
			$options->set( 'isHtml5ParserEnabled', true );
			$options->set( 'isFontSubsettingEnabled', true );
			$options->set( 'defaultFont', 'DejaVu Serif' );
			$options->setChroot( array( CTA_PLUGIN_DIR ) );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'letter', 'landscape' );
			$dompdf->render();

			$binary = $dompdf->output();
			if ( ! is_string( $binary ) || '' === $binary ) {
				return new WP_Error( 'cta_pdf_render', __( 'PDF rendering failed.', 'cta-lms' ) );
			}

			// Basic magic-byte check so we never send HTML labeled as PDF.
			if ( 0 !== strpos( $binary, '%PDF' ) ) {
				return new WP_Error( 'cta_pdf_invalid', __( 'Generated file was not a valid PDF.', 'cta-lms' ) );
			}

			return $binary;
		} catch ( Exception $e ) {
			return new WP_Error(
				'cta_pdf_exception',
				sprintf(
					/* translators: %s: exception message */
					__( 'PDF generation error: %s', 'cta-lms' ),
					$e->getMessage()
				)
			);
		} catch ( Throwable $e ) {
			return new WP_Error(
				'cta_pdf_exception',
				sprintf(
					/* translators: %s: exception message */
					__( 'PDF generation error: %s', 'cta-lms' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Resolve upload paths for a new certificate file.
	 *
	 * @param int    $user_id             User ID.
	 * @param int    $course_id           Course ID.
	 * @param string $certificate_number  Certificate number.
	 * @return array|WP_Error { file_path, file_url }
	 */
	private static function build_file_paths( $user_id, $course_id, $certificate_number ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'cta_upload', $upload_dir['error'] );
		}

		$subdir   = (string) get_option( 'cta_certificate_upload_dir', 'cta-certificates' );
		$subdir   = $subdir ? sanitize_file_name( $subdir ) : 'cta-certificates';
		$cert_dir = trailingslashit( $upload_dir['basedir'] ) . $subdir;

		if ( ! wp_mkdir_p( $cert_dir ) ) {
			return new WP_Error( 'cta_mkdir', __( 'Could not create certificate directory.', 'cta-lms' ) );
		}

		$filename = sanitize_file_name(
			sprintf(
				'certificate-%d-%d-%s.html',
				$user_id,
				$course_id,
				$certificate_number
			)
		);

		return array(
			'file_path' => trailingslashit( $cert_dir ) . $filename,
			'file_url'  => trailingslashit( $upload_dir['baseurl'] ) . $subdir . '/' . $filename,
		);
	}

	/**
	 * Fetch certificate for user and course.
	 *
	 * @param int $user_id   WordPress user ID.
	 * @param int $course_id Course ID.
	 * @return object|null
	 */
	public static function get_certificate( $user_id, $course_id ) {
		return CTA_Database::get_user_course_certificate( $user_id, $course_id );
	}

	/**
	 * Generate unique certificate number.
	 *
	 * @return string
	 */
	private static function create_certificate_number() {
		global $wpdb;

		$year = wp_date( 'Y' );

		do {
			$number = sprintf( 'CTA-%s-%06d', $year, wp_rand( 100000, 999999 ) );
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}cta_certificates WHERE certificate_number = %s",
					$number
				)
			);
		} while ( $exists );

		return $number;
	}
}
}
