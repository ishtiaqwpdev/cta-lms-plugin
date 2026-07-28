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
		if ( class_exists( 'CTA_Exam_Access' ) && ! CTA_Exam_Access::has_ce_certificate( $course ) ) {
			return null;
		}

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

		$student_timezone = ! empty( $evaluation->timezone ) ? (string) $evaluation->timezone : null;

		CTA_Emails::send(
			'certificate_ready',
			$user_id,
			array(
				'course'      => $course,
				'certificate' => $certificate,
				'timezone'    => $student_timezone,
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
	 * Format the certificate issue / completion datetime for print and email.
	 *
	 * Uses the stored issue instant only (never "now"). Prefers the learner's
	 * evaluation timezone when available so the printed day matches when they earned it.
	 *
	 * @param string      $issued_at   MySQL datetime from the certificate row.
	 * @param object|null $evaluation  Evaluation row (optional; timezone / submitted_at).
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

		$display_tz = cta_lms_get_timezone();
		if ( $evaluation && ! empty( $evaluation->timezone ) ) {
			try {
				$display_tz = new DateTimeZone( (string) $evaluation->timezone );
			} catch ( Exception $e ) {
				$display_tz = cta_lms_get_timezone();
			}
		}

		return cta_lms_format_local_date(
			$issue_source,
			'F j, Y \a\t g:i A T',
			$display_tz
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
	 * @param array       $args                Optional flags: auto_print (bool), download_url (string).
	 * @return string
	 */
	public static function build_html( $user_id, $course, $certificate_number, $issued_at, $evaluation = null, $args = array() ) {
		$user = get_userdata( $user_id );

		// Always print the real certificate issue instant from storage.
		$completion_date = self::format_issue_date( $issued_at, $evaluation );
		$course_title    = (string) $course->title;
		$ce_hours        = rtrim( rtrim( number_format( (float) $course->ce_hours, 1, '.', '' ), '0' ), '.' );
		if ( '' === $ce_hours ) {
			$ce_hours = '0';
		}

		$student_name = function_exists( 'cta_lms_get_user_legal_name' )
			? cta_lms_get_user_legal_name( $user_id )
			: ( $user ? $user->display_name : '' );
		if ( '' === $student_name ) {
			$student_name = __( 'Student', 'cta-lms' );
		}

		$license_number  = cta_lms_get_user_license_number( $user_id );
		$provider_number = (string) get_option( 'cta_camft_provider_number', '' );
		if ( '' === $provider_number ) {
			$provider_number = (string) get_option( 'cta_cepa_provider_number', '' );
		}

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

		$organization_name   = __( 'Clinical Training and Supervision Academy', 'cta-lms' );
		$administrator_title = __( 'Program Administrator', 'cta-lms' );
		$logo_url            = self::get_logo_data_uri();
		if ( '' === $logo_url ) {
			$logo_url = cta_lms_get_logo_url();
		}
		$auto_print   = ! empty( $args['auto_print'] );
		$download_url = ! empty( $args['download_url'] ) ? (string) $args['download_url'] : '';

		ob_start();
		include CTA_PLUGIN_DIR . 'templates/certificate.php';
		return (string) ob_get_clean();
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
			return '';
		}

		$mime = wp_check_filetype( $path );
		$type = ! empty( $mime['type'] ) ? $mime['type'] : 'image/png';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			return '';
		}

		return 'data:' . $type . ';base64,' . base64_encode( $bytes );
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
	 * Build a nonce-protected admin-post URL for certificate actions.
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
				'action'         => 'cta_print_certificate',
				'certificate_id' => $certificate_id,
				'_wpnonce'       => wp_create_nonce( 'cta_print_certificate_' . $certificate_id ),
			),
			is_array( $extra ) ? $extra : array()
		);

		if ( empty( $args['print'] ) ) {
			unset( $args['print'] );
		}
		if ( empty( $args['download'] ) ) {
			unset( $args['download'] );
		}

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Stream a print-ready certificate (landscape, single page) for Print → Save as PDF,
	 * or force-download the certificate HTML file when ?download=1.
	 */
	public static function handle_print_request() {
		$certificate_id = absint( wp_unslash( $_GET['certificate_id'] ?? 0 ) );

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

		if ( ! $certificate || ( (int) $certificate->user_id !== $user_id && ! current_user_can( 'manage_options' ) ) ) {
			wp_die( esc_html__( 'Certificate not found.', 'cta-lms' ), 404 );
		}

		self::refresh_file( $certificate );
		$certificate = CTA_Database::get_certificate( $certificate_id );
		$course      = CTA_Database::get_course( (int) $certificate->course_id );
		$evaluation  = CTA_Database::get_course_evaluation( (int) $certificate->user_id, (int) $certificate->course_id );

		if ( ! $course ) {
			wp_die( esc_html__( 'Course not found.', 'cta-lms' ), 404 );
		}

		$is_download = ! empty( $_GET['download'] );
		$filename    = sanitize_file_name( (string) $certificate->certificate_number ) . '.html';

		$html = self::build_html(
			(int) $certificate->user_id,
			$course,
			(string) $certificate->certificate_number,
			(string) $certificate->issued_at,
			$evaluation,
			array(
				'auto_print'   => ! $is_download && ! empty( $_GET['print'] ),
				'download_url' => self::get_download_url( $certificate_id ),
			)
		);

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header(
			sprintf(
				'Content-Disposition: %s; filename="%s"',
				$is_download ? 'attachment' : 'inline',
				$filename
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
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
