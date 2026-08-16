<?php
/**
 * Course downloadable materials: storage, access checks, and gated serving.
 *
 * Files are copied into a protected uploads subdirectory so unenrolled users
 * cannot download them via a direct Media Library URL. Learners always fetch
 * materials through the gated serve endpoint after enrollment is verified.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Course_Materials
 */
if ( ! class_exists( 'CTA_Course_Materials' ) ) {

class CTA_Course_Materials {

	const UPLOAD_SUBDIR = 'cta-course-materials';

	/** @var int Max upload size in bytes (25MB — fits exam-prep audio MP3s). */
	const MAX_UPLOAD_BYTES = 26214400;

	/** @var array Allowed MIME types keyed by extension. */
	const ALLOWED_MIMES = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'mp3'  => 'audio/mpeg',
	);

	/**
	 * Allowed extensions for course materials (PDF / DOC / DOCX / MP3).
	 *
	 * @return string[]
	 */
	public static function allowed_extensions() {
		return array( 'pdf', 'doc', 'docx', 'mp3' );
	}

	/**
	 * Validate an attachment for use as a course material.
	 *
	 * @param int $attachment_id Media Library attachment ID.
	 * @return true|WP_Error
	 */
	public static function validate_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return new WP_Error(
				'cta_resource_missing',
				__( 'Please select or upload a file for this material.', 'cta-lms' )
			);
		}

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error(
				'cta_resource_missing',
				__( 'Attachment file not found.', 'cta-lms' )
			);
		}

		$size = filesize( $path );
		if ( false !== $size && $size > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error(
				'cta_resource_too_large',
				__( 'File exceeds the 25MB size limit. Please upload a smaller PDF, DOC, DOCX, or MP3 file.', 'cta-lms' )
			);
		}

		$checked = wp_check_filetype_and_ext( $path, basename( $path ), self::ALLOWED_MIMES );
		$ext     = ! empty( $checked['ext'] ) ? strtolower( (string) $checked['ext'] ) : '';

		if ( '' === $ext || ! isset( self::ALLOWED_MIMES[ $ext ] ) ) {
			// Fallback: basename extension when filetype helpers are inconclusive (common for DOC).
			$fallback = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $fallback, self::allowed_extensions(), true ) ) {
				return new WP_Error(
					'cta_resource_invalid_type',
					__( 'Only PDF, DOC, DOCX, and MP3 files are allowed for course materials.', 'cta-lms' )
				);
			}
		}

		return true;
	}

	/**
	 * Whether a user may access a resource.
	 *
	 * @param int         $user_id  User ID.
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function user_can_access( $user_id, $resource ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! $resource || empty( $resource->course_id ) ) {
			return false;
		}

		// Never expose admin/source/control package files on student surfaces.
		$path_bits = trim(
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' ) . ' ' .
			(string) ( $resource->title ?? '' )
		);
		if ( self::is_admin_restricted_source_path( $path_bits ) ) {
			return false;
		}

		if ( self::is_archived_resource( $resource ) ) {
			return false;
		}

		$course_id  = (int) $resource->course_id;
		$enrollment = class_exists( 'CTA_Database' )
			? CTA_Database::get_user_enrollment( $user_id, $course_id )
			: null;

		if ( ! $enrollment || ! in_array( $enrollment->status, array( 'active', 'completed' ), true ) ) {
			return false;
		}

		$course = CTA_Database::get_course( $course_id );

		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			if ( ! CTA_Exam_Access::has_active_access( $user_id, $course_id ) ) {
				return false;
			}
			// Exam Prep: workbooks, schedules, and candidate exams stay open from enrollment.
			// Protected answer keys / rationales unlock only after the matching assessment is submitted.
			if ( self::resource_requires_quiz_unlock( $resource ) ) {
				$unlock_type = self::get_resource_unlock_gate_type( $resource );
				return self::user_meets_unlock_gate( $user_id, $course_id, $unlock_type );
			}
			return true;
		} elseif ( class_exists( 'CTA_CE_Access' ) && CTA_CE_Access::is_ce_course( $course ) ) {
			if ( ! CTA_CE_Access::has_active_access( $user_id, $course_id ) ) {
				return false;
			}
		}

		// CE (and non-exam-prep) per-resource unlock gates.
		$unlock_type = isset( $resource->unlock_after_quiz_type )
			? sanitize_text_field( (string) $resource->unlock_after_quiz_type )
			: '';

		if ( '' !== $unlock_type ) {
			return self::user_meets_unlock_gate( $user_id, $course_id, $unlock_type );
		}

		return true;
	}

	/**
	 * Whether the learner satisfies a downloadable-resource unlock gate.
	 *
	 * Used for CE courses (and any non–Exam Prep product). Exam Prep skips these
	 * gates in user_can_access() so enrolled learners get materials immediately.
	 *
	 * Supported gates:
	 * - modules_complete — all instructional modules finished
	 * - form_b_ready — always true (legacy key; Exam Prep no longer uses it for locking)
	 * - form_a_remediation — Form A remediation workbook marked complete
	 * - form_a / form_b / wbN_bank / checkpoint_N — matching quiz type submitted
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $course_id   Course ID.
	 * @param string $unlock_type Gate key.
	 * @return bool
	 */
	public static function user_meets_unlock_gate( $user_id, $course_id, $unlock_type ) {
		$user_id     = absint( $user_id );
		$course_id   = absint( $course_id );
		$unlock_type = sanitize_text_field( (string) $unlock_type );

		if ( ! $user_id || ! $course_id || '' === $unlock_type ) {
			return false;
		}

		if ( 'modules_complete' === $unlock_type ) {
			$enrollment = class_exists( 'CTA_Database' )
				? CTA_Database::get_user_enrollment( $user_id, $course_id )
				: null;
			if ( class_exists( 'CTA_CE_Completion' ) ) {
				return CTA_CE_Completion::modules_complete( $user_id, $course_id, $enrollment );
			}
			return class_exists( 'CTA_Certificates' )
				&& CTA_Certificates::user_completed_all_modules( $user_id, $course_id, $enrollment );
		}

		// Form B candidate materials: never require Form A remediation (advisory only).
		if ( 'form_b_ready' === $unlock_type ) {
			return true;
		}

		if ( 'form_a_remediation' === $unlock_type ) {
			return self::user_has_completed_form_a_remediation( $user_id, $course_id );
		}

		// AMFTRB: preserved printable attempts also satisfy protected rationale gates.
		$course_for_gate = class_exists( 'CTA_Database' ) ? CTA_Database::get_course( $course_id ) : null;
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course_for_gate ) ) {
			if ( self::user_has_preserved_attempt( $user_id, $course_id, $unlock_type ) ) {
				return true;
			}
		}

		return self::user_has_completed_quiz_type( $user_id, $course_id, $unlock_type );
	}

	/**
	 * Infer unlock gate for Form A/B candidate-exam downloads from title/path.
	 *
	 * Answer keys / rationales are never inferred here (they must stay on form_a/form_b submit gates).
	 *
	 * @param object|null $resource Resource row.
	 * @return string Gate key or empty string.
	 */
	public static function infer_exam_form_download_gate( $resource ) {
		if ( ! $resource ) {
			return '';
		}

		$haystack = trim(
			(string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' )
		);

		if ( '' === $haystack ) {
			return '';
		}

		// Never auto-gate answer keys / rationales / remediation workbooks this way.
		if ( false !== stripos( $haystack, 'Answer' )
			|| false !== stripos( $haystack, 'Rationale' )
			|| false !== stripos( $haystack, 'Remediation' ) ) {
			return '';
		}

		$looks_like_form = ( false !== stripos( $haystack, 'Form_A' )
			|| false !== stripos( $haystack, 'Form A' )
			|| false !== stripos( $haystack, 'Form_B' )
			|| false !== stripos( $haystack, 'Form B' ) );
		$looks_like_sim  = ( false !== stripos( $haystack, 'Simulation' )
			|| false !== stripos( $haystack, 'Candidate_Exam' )
			|| false !== stripos( $haystack, 'Candidate Exam' )
			|| false !== stripos( $haystack, '_Exam_v' )
			|| ! empty( $resource->is_practice_test ) );

		if ( ! $looks_like_form || ! $looks_like_sim ) {
			return '';
		}

		if ( false !== stripos( $haystack, 'Form_B' ) || false !== stripos( $haystack, 'Form B' ) ) {
			return 'form_b_ready';
		}

		if ( false !== stripos( $haystack, 'Form_A' ) || false !== stripos( $haystack, 'Form A' ) ) {
			return 'modules_complete';
		}

		return '';
	}

	/**
	 * User-meta key for Form A remediation completion on a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function form_a_remediation_meta_key( $course_id ) {
		return 'cta_form_a_remediation_complete_' . absint( $course_id );
	}

	/**
	 * Whether a downloadable resource is the Form A Remediation Workbook.
	 *
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function is_form_a_remediation_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		$title = (string) ( $resource->title ?? '' );
		$path  = (string) ( $resource->file_path ?? '' );

		if ( false !== stripos( $title, 'Remediation' ) && false !== stripos( $title, 'Form A' ) ) {
			return true;
		}

		if ( false !== stripos( $path, 'Form_A_Remediation' )
			|| false !== stripos( $path, 'Form_A_Required_Remediation' )
			|| ( false !== stripos( $path, 'Simulation_Form_A' ) && false !== stripos( $path, 'Remediation' ) )
			|| false !== stripos( $path, 'Remediation_Workbook' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether this course attaches a Form A Remediation Workbook.
	 *
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function course_has_form_a_remediation( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return false;
		}

		foreach ( (array) CTA_Database::get_downloadable_resources( $course_id ) as $resource ) {
			if ( self::is_form_a_remediation_resource( $resource ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this student has marked Form A Remediation complete for the course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function user_has_completed_form_a_remediation( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		$val = get_user_meta( $user_id, self::form_a_remediation_meta_key( $course_id ), true );
		return is_string( $val ) && '' !== $val;
	}

	/**
	 * Mark Form A Remediation complete for this student/course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error
	 */
	public static function mark_form_a_remediation_complete( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return new WP_Error( 'invalid', __( 'Invalid request.', 'cta-lms' ) );
		}

		if ( ! self::course_has_form_a_remediation( $course_id ) ) {
			return new WP_Error( 'no_remediation', __( 'This program does not include a Form A Remediation Workbook.', 'cta-lms' ) );
		}

		$has_form_a = self::user_has_preserved_attempt( $user_id, $course_id, 'form_a' )
			|| ( class_exists( 'CTA_Lcsw_Aswb_Sync' )
				? CTA_Lcsw_Aswb_Sync::user_has_completed_quiz_type( $user_id, $course_id, 'form_a' )
				: self::user_has_completed_quiz_type( $user_id, $course_id, 'form_a' ) );

		if ( ! $has_form_a ) {
			return new WP_Error(
				'form_a_required',
				__( 'Submit Comprehensive Simulation Form A (or record your preserved Form A attempt) before completing the Form A Remediation Workbook.', 'cta-lms' )
			);
		}

		update_user_meta( $user_id, self::form_a_remediation_meta_key( $course_id ), current_time( 'mysql' ) );
		return true;
	}

	/**
	 * User-meta key storing preserved printable assessment attempts for a course.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function preserved_attempts_meta_key( $course_id ) {
		return 'cta_exam_preserved_attempts_' . absint( $course_id );
	}

	/**
	 * Whether the learner has a preserved first attempt for an unlock/quiz type.
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $course_id   Course ID.
	 * @param string $unlock_type Gate key (wb1_bank, checkpoint_1, form_a, …).
	 * @return bool
	 */
	public static function user_has_preserved_attempt( $user_id, $course_id, $unlock_type ) {
		$user_id     = absint( $user_id );
		$course_id   = absint( $course_id );
		$unlock_type = sanitize_text_field( (string) $unlock_type );

		if ( ! $user_id || ! $course_id || '' === $unlock_type ) {
			return false;
		}

		$raw = get_user_meta( $user_id, self::preserved_attempts_meta_key( $course_id ), true );
		if ( ! is_array( $raw ) ) {
			return false;
		}

		return ! empty( $raw[ $unlock_type ] );
	}

	/**
	 * Infer the assessment unlock type that a candidate practice resource preserves.
	 *
	 * @param object|null $resource Resource row.
	 * @return string Unlock type or empty.
	 */
	public static function infer_preserved_attempt_type( $resource ) {
		if ( ! $resource ) {
			return '';
		}

		$title = (string) ( $resource->title ?? '' );
		$path  = (string) ( $resource->file_path ?? '' ) . ' ' . (string) ( $resource->file_url ?? '' );
		$hay   = $title . ' ' . $path;

		if ( preg_match( '/Workbook\s+(\d+)/i', $title, $m )
			&& ( false !== stripos( $hay, 'Candidate Bank' ) || false !== stripos( $hay, 'Candidate_Bank' ) ) ) {
			return 'wb' . (int) $m[1] . '_bank';
		}

		if ( preg_match( '/Checkpoint\s+(\d+)/i', $title, $m )
			&& ( false !== stripos( $hay, 'Candidate' ) || ! empty( $resource->is_practice_test ) ) ) {
			return 'checkpoint_' . (int) $m[1];
		}

		if ( ( false !== stripos( $hay, 'Form A' ) || false !== stripos( $hay, 'Form_A' ) )
			&& ( false !== stripos( $hay, 'Candidate' ) || ! empty( $resource->is_practice_test ) )
			&& false === stripos( $hay, 'Rationale' )
			&& false === stripos( $hay, 'Remediation' )
			&& false === stripos( $hay, 'Answer Key' ) ) {
			return 'form_a';
		}

		if ( ( false !== stripos( $hay, 'Form B' ) || false !== stripos( $hay, 'Form_B' ) )
			&& ( false !== stripos( $hay, 'Candidate' ) || ! empty( $resource->is_practice_test ) )
			&& false === stripos( $hay, 'Rationale' )
			&& false === stripos( $hay, 'Remediation' )
			&& false === stripos( $hay, 'Answer Key' ) ) {
			return 'form_b';
		}

		return '';
	}

	/**
	 * Record a preserved first attempt for a printable candidate assessment.
	 *
	 * @param int    $user_id     User ID.
	 * @param int    $course_id   Course ID.
	 * @param string $unlock_type Gate key.
	 * @return true|WP_Error
	 */
	public static function mark_preserved_attempt( $user_id, $course_id, $unlock_type ) {
		$user_id     = absint( $user_id );
		$course_id   = absint( $course_id );
		$unlock_type = sanitize_text_field( (string) $unlock_type );

		if ( ! $user_id || ! $course_id || '' === $unlock_type ) {
			return new WP_Error( 'invalid', __( 'Invalid request.', 'cta-lms' ) );
		}

		if ( ! preg_match( '/^(wb\d+_bank|checkpoint_[123]|form_a|form_b)$/', $unlock_type ) ) {
			return new WP_Error( 'invalid_type', __( 'Unknown assessment type.', 'cta-lms' ) );
		}

		$key = self::preserved_attempts_meta_key( $course_id );
		$raw = get_user_meta( $user_id, $key, true );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		if ( empty( $raw[ $unlock_type ] ) ) {
			$raw[ $unlock_type ] = current_time( 'mysql' );
			update_user_meta( $user_id, $key, $raw );
		}

		return true;
	}

	/**
	 * Whether a resource is a protected answer-key / rationale file (gated release).
	 *
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function is_protected_rationale_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		$hay = (string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' );

		if ( false !== stripos( $hay, '/rationales/' ) || false !== stripos( $hay, '\\rationales\\' ) ) {
			return true;
		}

		if ( false !== stripos( $hay, 'Answer Key' )
			|| false !== stripos( $hay, 'Answer_Key' )
			|| false !== stripos( $hay, 'Detailed Rationales' )
			|| false !== stripos( $hay, 'Controlled Answer' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether a resource is gated until a matching assessment attempt is submitted.
	 *
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function resource_requires_quiz_unlock( $resource ) {
		return '' !== self::get_resource_unlock_gate_type( $resource );
	}

	/**
	 * Resolve the quiz gate key for a protected answer-key / rationale resource.
	 *
	 * Uses stored unlock_after_quiz_type when present; otherwise infers from title/path.
	 *
	 * @param object|null $resource Resource row.
	 * @return string Gate key or empty string.
	 */
	public static function get_resource_unlock_gate_type( $resource ) {
		return self::infer_protected_rationale_unlock_type( $resource );
	}

	/**
	 * Infer unlock_after_quiz_type for protected answer keys and rationales.
	 *
	 * @param object|null $resource Resource row.
	 * @return string Gate key or empty string.
	 */
	public static function infer_protected_rationale_unlock_type( $resource ) {
		if ( ! $resource || ! self::is_protected_rationale_resource( $resource ) ) {
			return '';
		}

		$stored = isset( $resource->unlock_after_quiz_type )
			? sanitize_text_field( (string) $resource->unlock_after_quiz_type )
			: '';
		if ( '' !== $stored ) {
			return $stored;
		}

		$title = (string) ( $resource->title ?? '' );
		$hay   = $title . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' );

		if ( false !== stripos( $hay, 'Comprehensive Final' ) ) {
			return 'comprehensive_final';
		}

		if ( preg_match( '/Workbook\s+(\d+)/i', $title, $m )
			&& ( false !== stripos( $hay, 'Answer Key' )
				|| false !== stripos( $hay, 'Answer_Key' )
				|| false !== stripos( $hay, 'Rationale' ) ) ) {
			return 'wb' . (int) $m[1] . '_bank';
		}

		if ( preg_match( '/Checkpoint\s+(\d+)/i', $title, $m )
			&& false !== stripos( $hay, 'Rationale' ) ) {
			return 'checkpoint_' . (int) $m[1];
		}

		if ( ( false !== stripos( $hay, 'Form B' ) || false !== stripos( $hay, 'Form_B' ) )
			&& ( false !== stripos( $hay, 'Answer Key' )
				|| false !== stripos( $hay, 'Answer_Key' )
				|| false !== stripos( $hay, 'Rationale' )
				|| false !== stripos( $hay, 'Controlled Answer' ) ) ) {
			return 'form_b';
		}

		if ( ( false !== stripos( $hay, 'Form A' ) || false !== stripos( $hay, 'Form_A' ) )
			&& ( false !== stripos( $hay, 'Answer Key' )
				|| false !== stripos( $hay, 'Answer_Key' )
				|| false !== stripos( $hay, 'Rationale' )
				|| false !== stripos( $hay, 'Controlled Answer' ) ) ) {
			return 'form_a';
		}

		return '';
	}

	/**
	 * Re-apply unlock_after_quiz_type on Exam Prep protected rationale rows.
	 *
	 * @return int Rows updated.
	 */
	public static function restore_exam_prep_protected_rationale_gates() {
		global $wpdb;

		$courses = $wpdb->prefix . 'cta_courses';
		$res     = $wpdb->prefix . 'cta_downloadable_resources';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT r.* FROM {$res} r
			INNER JOIN {$courses} c ON c.id = r.course_id
			WHERE c.product_type = 'exam_prep'"
		);

		$updated = 0;
		foreach ( (array) $rows as $row ) {
			$unlock = self::infer_protected_rationale_unlock_type_from_content(
				(string) ( $row->title ?? '' ),
				(string) ( $row->file_path ?? '' ) . ' ' . (string) ( $row->file_url ?? '' )
			);
			$current = isset( $row->unlock_after_quiz_type )
				? sanitize_text_field( (string) $row->unlock_after_quiz_type )
				: '';
			if ( $unlock === $current ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$res,
				array( 'unlock_after_quiz_type' => $unlock ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);
			++$updated;
		}

		return $updated;
	}

	/**
	 * Infer a protected rationale gate from title/path only (ignores stored DB value).
	 *
	 * @param string $title Resource title.
	 * @param string $path  File path / URL haystack.
	 * @return string Gate key or empty string.
	 */
	private static function infer_protected_rationale_unlock_type_from_content( $title, $path ) {
		return self::infer_protected_rationale_unlock_type(
			(object) array(
				'title'                  => $title,
				'file_path'              => $path,
				'file_url'               => '',
				'unlock_after_quiz_type' => '',
			)
		);
	}

	/**
	 * Form B access check for Exam Prep programs.
	 *
	 * Form B is open independently of Form A (and of Form A Remediation). Enrollment /
	 * timed access are enforced by callers; this helper no longer applies a Form A sequence.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error
	 */
	public static function assert_form_b_accessible( $user_id, $course_id ) {
		unset( $user_id, $course_id );
		return true;
	}

	/**
	 * Whether the course has an online quiz row for the given quiz_type.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $quiz_type Quiz type slug.
	 * @return bool
	 */
	public static function course_has_quiz_type( $course_id, $quiz_type ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$quiz_type = sanitize_text_field( (string) $quiz_type );

		if ( ! $course_id || '' === $quiz_type ) {
			return false;
		}

		// Non-quiz gate keys are not online quiz types.
		if ( in_array( $quiz_type, array( 'modules_complete', 'form_b_ready', 'form_a_remediation' ), true ) ) {
			return true;
		}

		$quizzes = $wpdb->prefix . 'cta_quizzes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$quizzes} WHERE course_id = %d AND quiz_type = %s LIMIT 1",
				$course_id,
				$quiz_type
			)
		);

		return $count > 0;
	}

	/**
	 * Whether the learner has a completed attempt for a course quiz of the given type.
	 *
	 * @param int    $user_id   User ID.
	 * @param int    $course_id Course ID.
	 * @param string $quiz_type Quiz type (form_a, form_b, …).
	 * @return bool
	 */
	public static function user_has_completed_quiz_type( $user_id, $course_id, $quiz_type ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$quiz_type = sanitize_text_field( (string) $quiz_type );

		if ( ! $user_id || ! $course_id || '' === $quiz_type ) {
			return false;
		}

		$quizzes = $wpdb->prefix . 'cta_quizzes';
		$attempts = $wpdb->prefix . 'cta_quiz_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(a.id) FROM {$attempts} a
				INNER JOIN {$quizzes} q ON q.id = a.quiz_id
				WHERE a.user_id = %d
					AND a.course_id = %d
					AND q.course_id = %d
					AND q.quiz_type = %s
					AND a.completed_at IS NOT NULL
				LIMIT 1",
				$user_id,
				$course_id,
				$course_id,
				$quiz_type
			)
		);

		return $count > 0;
	}

	/**
	 * Human-readable lock reason for a gated resource (empty if unlocked by quiz submit).
	 *
	 * @param int         $user_id  User ID.
	 * @param object|null $resource Resource row.
	 * @return string
	 */
	public static function get_unlock_lock_message( $user_id, $resource ) {
		if ( ! $resource ) {
			return '';
		}

		$course = ( ! empty( $resource->course_id ) && class_exists( 'CTA_Database' ) )
			? CTA_Database::get_course( (int) $resource->course_id )
			: null;

		$type = self::get_resource_unlock_gate_type( $resource );

		if ( '' === $type ) {
			return '';
		}

		$course_id = isset( $resource->course_id ) ? (int) $resource->course_id : 0;

		if ( self::user_meets_unlock_gate( $user_id, $course_id, $type ) ) {
			return '';
		}

		if ( 'modules_complete' === $type ) {
			return __( 'Available after you complete all program modules.', 'cta-lms' );
		}
		if ( 'form_b_ready' === $type ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return __( 'Available after you complete the Form A Remediation Workbook.', 'cta-lms' );
			}
			return '';
		}
		if ( 'form_a_remediation' === $type ) {
			return __( 'Available after you complete the Form A Remediation Workbook.', 'cta-lms' );
		}
		if ( 'form_a' === $type ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return __( 'Complete Form A (or record your preserved Form A attempt) to unlock the Answer Key and Rationales.', 'cta-lms' );
			}
			return __( 'Complete Form A to unlock the Answer Key and Rationales.', 'cta-lms' );
		}
		if ( 'form_b' === $type ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return __( 'Complete Form B (or record your preserved Form B attempt) to unlock the Answer Key and Rationales.', 'cta-lms' );
			}
			return __( 'Complete Form B to unlock the Answer Key and Rationales.', 'cta-lms' );
		}
		if ( 'comprehensive_final' === $type ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return __( 'Complete the Comprehensive Final (or record your preserved attempt) to unlock the Answer Key and Rationales.', 'cta-lms' );
			}
			return __( 'Complete the Comprehensive Final to unlock the Answer Key and Rationales.', 'cta-lms' );
		}
		if ( 'checkpoint_1' === $type ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return __( 'Complete Checkpoint 1 (or record your preserved attempt) to unlock the Answer Rationales.', 'cta-lms' );
			}
			return __( 'Complete Checkpoint 1 to unlock the Answer Rationales.', 'cta-lms' );
		}
		if ( 'checkpoint_2' === $type ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return __( 'Complete Checkpoint 2 (or record your preserved attempt) to unlock the Answer Rationales.', 'cta-lms' );
			}
			return __( 'Complete Checkpoint 2 to unlock the Answer Rationales.', 'cta-lms' );
		}
		if ( 'checkpoint_3' === $type ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return __( 'Complete Checkpoint 3 (or record your preserved attempt) to unlock the Answer Rationales.', 'cta-lms' );
			}
			return __( 'Complete Checkpoint 3 to unlock the Answer Rationales.', 'cta-lms' );
		}
		if ( preg_match( '/^wb(\d+)_bank$/', $type, $m ) ) {
			if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::uses_assessment_gates( $course ) ) {
				return sprintf(
					/* translators: %d: workbook number */
					__( 'Complete Workbook %1$d Practice Bank (or record your preserved attempt) to unlock the Answer Key and Rationales.', 'cta-lms' ),
					(int) $m[1]
				);
			}
			return sprintf(
				/* translators: %d: workbook number */
				__( 'Complete Workbook %1$d Practice Bank to unlock the Answer Key and Rationales.', 'cta-lms' ),
				(int) $m[1]
			);
		}

		return __( 'Available after you submit the related assessment.', 'cta-lms' );
	}

	/**
	 * Public (gated) download URL for a resource — never exposes the raw file path.
	 *
	 * @param int $resource_id Resource ID.
	 * @return string
	 */
	public static function get_serve_url( $resource_id ) {
		$resource_id = absint( $resource_id );

		if ( ! $resource_id ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'      => 'cta_serve_resource',
					'resource_id' => $resource_id,
				),
				admin_url( 'admin-post.php' )
			),
			'cta_serve_resource_' . $resource_id
		);
	}

	/**
	 * Public gated URL that explicitly downloads a resource as an attachment.
	 *
	 * Use this only for download-focused UI. Other sections should continue to
	 * use get_serve_url() so browser-viewable files can open inline.
	 *
	 * @param int $resource_id Resource ID.
	 * @return string
	 */
	public static function get_download_url( $resource_id ) {
		$resource_id = absint( $resource_id );

		if ( ! $resource_id ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'       => 'cta_serve_resource',
					'resource_id'  => $resource_id,
					'cta_download' => 1,
				),
				admin_url( 'admin-post.php' )
			),
			'cta_serve_resource_' . $resource_id
		);
	}

	/**
	 * Absolute path to the protected materials root (creates dir + deny rules).
	 *
	 * @return string|WP_Error
	 */
	public static function get_protected_root() {
		$upload = wp_upload_dir();

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'cta_upload', $upload['error'] );
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'cta_mkdir', __( 'Could not create materials directory.', 'cta-lms' ) );
		}

		self::ensure_deny_rules( $dir );

		return $dir;
	}

	/**
	 * Write .htaccess / index.php deny rules into the materials directory.
	 *
	 * @param string $dir Absolute directory path.
	 */
	public static function ensure_deny_rules( $dir ) {
		$dir = trailingslashit( $dir );

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$htaccess = $dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents(
				$htaccess,
				"Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
			);
		}

		$webconfig = $dir . 'web.config';
		if ( ! file_exists( $webconfig ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents(
				$webconfig,
				"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
				. "<configuration>\n"
				. "  <system.webServer>\n"
				. "    <security>\n"
				. "      <authorization>\n"
				. "        <deny users=\"*\" />\n"
				. "      </authorization>\n"
				. "    </security>\n"
				. "  </system.webServer>\n"
				. "</configuration>\n"
			);
		}

		$index = $dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Deny HTTP access to release packages (90_Admin_Restricted, archives, etc.).
	 *
	 * Source packages live under `_packages/` for admin/build use only and must never
	 * be student-visible — matching LCSW / LMFT / LPCC release rules.
	 */
	public static function ensure_package_tree_deny_rules() {
		if ( ! defined( 'CTA_PLUGIN_DIR' ) ) {
			return;
		}

		$packages = CTA_PLUGIN_DIR . '_packages';
		if ( is_dir( $packages ) ) {
			self::ensure_deny_rules( $packages );
		}
	}

	/**
	 * Whether a filesystem path points at admin/source/control content that must stay off student surfaces.
	 *
	 * @param string $path Absolute or relative path.
	 * @return bool
	 */
	public static function is_admin_restricted_source_path( $path ) {
		$norm = strtolower( str_replace( '\\', '/', (string) $path ) );
		if ( '' === $norm ) {
			return false;
		}

		$markers = array(
			'/90_admin_restricted/',
			'90_admin_restricted/',
			'/99_archive_superseded',
			'99_archive_superseded_do_not_publish',
			'/08_unrecorded_production',
			'unrecorded_do_not_publish',
			'_do_not_publish/',
			'/assessment_synchronization/',
			'/blueprints_and_crosswalks/',
			'/blueprint_and_qc_reports/',
			'/administrative_item_banks/',
			'/production_standards_and_crosswalks/',
			'/program_audit_and_source_control/',
			'/official_source_reference/',
			'/question_bank_and_simulation_controls/',
			'administrative_master_item_bank',
			// AMFTRB / David handoff package trees — never learner-facing.
			'/03_internal_controls/',
			'03_internal_controls/',
			'/assessment_and_program_blueprints/',
			'assessment_and_program_blueprints/',
			'/audio_production/',
			'audio_production/',
			'/program_architecture_and_audits/',
			'program_architecture_and_audits/',
			'/protected_inventory/',
			'protected_inventory/',
			'/workbook_blueprints/',
			'workbook_blueprints/',
			'/02_protected_rationales/',
			'02_protected_rationales/',
			// LPCC audio supplement admin references — never learner-facing.
			'/00_admin/',
			'00_admin/',
			'/admin-only/',
			'admin-only/',
			'/quiz-seeds/admin-only/',
			'quiz-seeds/admin-only/',
			'suicide-risk-final-exam-answer-key.php',
			'lmft-clinical-form-a-answer-key.php',
			'lmft-clinical-form-a-answer-key-',
			'lmft-clinical-form-b-answer-key.php',
			'lmft-clinical-form-b-answer-key-',
			'/chapter-tests-admin/',
			'chapter-tests-admin/',
			'lms_admin_only',
			'recording_guide',
			'completion_record',
			'audio_review_supplement_recording',
			'audio_production_completion',
		);

		foreach ( $markers as $marker ) {
			if ( false !== strpos( $norm, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Copy a Media Library attachment into the protected materials folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $course_id     Course ID.
	 * @return array|WP_Error { relative_path, file_url_placeholder, file_type, absolute_path }
	 */
	public static function import_attachment_to_protected( $attachment_id, $course_id ) {
		$attachment_id = absint( $attachment_id );
		$course_id     = absint( $course_id );

		$valid = self::validate_attachment( $attachment_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$source = get_attached_file( $attachment_id );

		if ( ! $source || ! file_exists( $source ) ) {
			return new WP_Error( 'cta_missing_file', __( 'Attachment file not found.', 'cta-lms' ) );
		}

		$root = self::get_protected_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$course_dir = trailingslashit( $root ) . $course_id;
		if ( ! wp_mkdir_p( $course_dir ) ) {
			return new WP_Error( 'cta_mkdir', __( 'Could not create course materials folder.', 'cta-lms' ) );
		}
		self::ensure_deny_rules( $course_dir );

		$filename = wp_unique_filename( $course_dir, basename( $source ) );
		$dest     = trailingslashit( $course_dir ) . $filename;

		if ( ! copy( $source, $dest ) ) {
			return new WP_Error( 'cta_copy', __( 'Could not copy file into protected storage.', 'cta-lms' ) );
		}

		$relative = self::UPLOAD_SUBDIR . '/' . $course_id . '/' . $filename;
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		return array(
			'relative_path' => $relative,
			'absolute_path' => $dest,
			'file_type'     => $ext ? $ext : 'file',
			// Placeholder — learners never receive a direct URL.
			'file_url'      => 'cta-protected://' . $relative,
		);
	}

	/**
	 * Bundled course materials shipped inside the plugin (source → course match).
	 *
	 * Files live under assets/course-materials/ and are served only through the
	 * enrollment-gated download endpoint (never as a public plugin URL).
	 *
	 * @return array[]
	 */
	public static function get_bundled_materials() {
		return array(
			array(
				'course_match_titles' => array(
					'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
					'California Law & Ethics for Mental Health Professionals',
				),
				'course_code'         => 'CTA-CE-001',
				'source_file'         => 'assets/course-materials/CTA_CE_001_California_Law_Ethics_Final_Syllabus_v2_1.pdf',
				'alt_source_files'    => array(
					'assets/course-materials/Final_Syllabus_v2_1.pdf',
					'assets/course-materials/CTA_CE_001_Final_Syllabus_v2_1.pdf',
				),
				'title'               => 'Final Syllabus v2.1',
				'resource_key'        => 'cta_ce_001_final_syllabus_v2_1',
				'is_syllabus'         => true,
			),
			array(
				'course_match_titles' => array(
					'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
					'California Law & Ethics for Mental Health Professionals',
				),
				'course_code'         => 'CTA-CE-001',
				'source_file'         => 'assets/course-materials/CTA_California_Law_Ethics_Practice_Protection_Toolkit_v1_0.pdf',
				'alt_source_files'    => array(
					'assets/course-materials/CTA_California_Law_Ethics_Practice_Protection_Toolkit_v1.0.pdf',
				),
				'title'               => 'California Law & Ethics Practice Protection Toolkit (Resource Workbook)',
				'resource_key'        => 'cta_ce_001_practice_protection_toolkit_v1',
				// Course-level only: supplementary workbook covering all topic areas (not module-gated).
				'module_id'           => 0,
			),
			array(
				'course_match_titles' => array(
					'Clinical and Ethical Excellence in Telehealth: The Essential California Framework',
					'Clinical and Ethical Excellence in Telehealth',
				),
				'source_file'         => 'assets/course-materials/CTA_Telehealth_Clinical_Resource_Toolkit_v2_0.docx',
				// Prefer PDF if present (matches other course handouts); fall back to DOCX.
				'alt_source_files'    => array(
					'assets/course-materials/CTA_Telehealth_Clinical_Resource_Toolkit_v2_0.pdf',
				),
				'title'               => 'CTA Telehealth Clinical Resource Toolkit (v2.0)',
				'resource_key'        => 'telehealth_clinical_resource_toolkit_v2',
			),
			array(
				'course_match_titles' => array(
					'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
					'Advanced Suicide Risk Assessment',
				),
				'course_code'         => 'CTA-CE-003',
				'source_file'         => 'assets/course-materials/suicide-risk-ce/CTA_Suicide_Risk_Learner_Resource_Toolkit_v1_1.html',
				'title'               => 'Learner Resource Toolkit — Clinician-Facing Resource Toolkit',
				'resource_key'        => 'suicide_risk_learner_resource_toolkit_v1_1',
				'module_id'           => 0,
			),
		);
	}

	/**
	 * Find the downloadable syllabus resource among course materials (if any).
	 *
	 * Prefers titles that look like a syllabus PDF (e.g. "Final Syllabus v2.1").
	 *
	 * @param array $resources Downloadable resource rows.
	 * @return object|null
	 */
	public static function find_syllabus_resource( array $resources ) {
		$preferred = null;
		foreach ( $resources as $resource ) {
			$title = isset( $resource->title ) ? (string) $resource->title : '';
			if ( '' === $title ) {
				continue;
			}
			if ( preg_match( '/\bfinal\s+syllabus\b/i', $title ) || preg_match( '/\bsyllabus\b.*\bv\s*2\.?1\b/i', $title ) ) {
				return $resource;
			}
			if ( ! $preferred && preg_match( '/\bsyllabus\b/i', $title ) ) {
				$preferred = $resource;
			}
		}
		return $preferred;
	}

	/**
	 * Ensure bundled materials are registered as downloadable resources.
	 *
	 * Idempotent: skips when the resource title (or known alias) already exists
	 * for the course, or when the source file is not present in the plugin package.
	 *
	 * @return array{attached:int,skipped:int,missing:array}
	 */
	public static function ensure_bundled_resources() {
		global $wpdb;

		$attached = 0;
		$skipped  = 0;
		$missing  = array();

		foreach ( self::get_bundled_materials() as $bundle ) {
			$source = self::resolve_bundled_source_path( $bundle );
			if ( '' === $source ) {
				$missing[] = (string) ( $bundle['source_file'] ?? '' );
				++$skipped;
				continue;
			}

			$course = self::find_course_for_bundle( $bundle );
			if ( ! $course ) {
				++$skipped;
				continue;
			}

			$course_id = (int) $course->id;
			$title     = sanitize_text_field( (string) ( $bundle['title'] ?? basename( $source ) ) );

			if ( self::course_has_bundled_resource( $course_id, $bundle, $title ) ) {
				++$skipped;
				continue;
			}

			$imported = self::import_local_file_to_protected( $source, $course_id );
			if ( is_wp_error( $imported ) ) {
				$missing[] = basename( $source ) . ' (' . $imported->get_error_message() . ')';
				++$skipped;
				continue;
			}

			$max_order = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(MAX(order_index), -1) FROM {$wpdb->prefix}cta_downloadable_resources WHERE course_id = %d",
					$course_id
				)
			);

			$module_id = isset( $bundle['module_id'] ) ? absint( $bundle['module_id'] ) : 0;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ok = $wpdb->insert(
				$wpdb->prefix . 'cta_downloadable_resources',
				array(
					'course_id'        => $course_id,
					'module_id'        => $module_id,
					'attachment_id'    => 0,
					'title'            => $title,
					'file_url'         => $imported['file_url'],
					'file_path'        => $imported['relative_path'],
					'file_type'        => $imported['file_type'],
					'order_index'      => $max_order + 1,
					'is_practice_test' => 0,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
			);

			if ( $ok ) {
				++$attached;
			} else {
				++$skipped;
			}
		}

		return array(
			'attached' => $attached,
			'skipped'  => $skipped,
			'missing'  => $missing,
		);
	}

	/**
	 * Whether a course already has this bundled resource (by title or alias).
	 *
	 * @param int    $course_id Course ID.
	 * @param array  $bundle    Bundle definition.
	 * @param string $title     Canonical title.
	 * @return bool
	 */
	private static function course_has_bundled_resource( $course_id, array $bundle, $title ) {
		global $wpdb;

		$aliases = array( $title );
		if ( ! empty( $bundle['title_aliases'] ) && is_array( $bundle['title_aliases'] ) ) {
			foreach ( $bundle['title_aliases'] as $alias ) {
				$alias = sanitize_text_field( (string) $alias );
				if ( '' !== $alias ) {
					$aliases[] = $alias;
				}
			}
		}

		// Recognize common informal names for the Practice Protection Toolkit.
		if ( ! empty( $bundle['resource_key'] ) && 'cta_ce_001_practice_protection_toolkit_v1' === $bundle['resource_key'] ) {
			$aliases[] = 'California Law & Ethics Practice Protection Toolkit v1.0';
			$aliases[] = 'Practice Protection Toolkit';
			$aliases[] = 'California Law & Ethics Practice Protection Toolkit';
		}

		$aliases = array_values( array_unique( $aliases ) );
		if ( empty( $aliases ) ) {
			return false;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $aliases ), '%s' ) );
		$params       = array_merge( array( (int) $course_id ), $aliases );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_downloadable_resources
				WHERE course_id = %d AND title IN ($placeholders)
				LIMIT 1",
				$params
			)
		);

		return $existing_id > 0;
	}

	/**
	 * Resolve the first existing bundled source path for a material definition.
	 *
	 * @param array $bundle Bundle definition.
	 * @return string Absolute path or empty.
	 */
	private static function resolve_bundled_source_path( array $bundle ) {
		$candidates = array();
		if ( ! empty( $bundle['alt_source_files'] ) && is_array( $bundle['alt_source_files'] ) ) {
			foreach ( $bundle['alt_source_files'] as $rel ) {
				$candidates[] = (string) $rel;
			}
		}
		if ( ! empty( $bundle['source_file'] ) ) {
			$candidates[] = (string) $bundle['source_file'];
		}

		foreach ( $candidates as $relative ) {
			$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
			$path     = CTA_PLUGIN_DIR . $relative;
			if ( is_readable( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * Find a CE course matching bundled course_code or title aliases.
	 *
	 * @param array $bundle Bundle definition.
	 * @return object|null
	 */
	private static function find_course_for_bundle( array $bundle ) {
		$code = isset( $bundle['course_code'] ) ? trim( (string) $bundle['course_code'] ) : '';
		if ( '' !== $code && 'CTA-CE-001' === $code && class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {
			$course = CTA_Law_Ethics_Module_Sync::find_course();
			if ( $course ) {
				return $course;
			}
		}

		if ( '' !== $code && class_exists( 'CTA_Database' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'cta_courses';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
			foreach ( (array) $rows as $row ) {
				if ( empty( $row->syllabus_meta ) ) {
					continue;
				}
				$decoded = json_decode( (string) $row->syllabus_meta, true );
				if ( is_array( $decoded ) && isset( $decoded['course_code'] ) && $code === (string) $decoded['course_code'] ) {
					return $row;
				}
			}
		}

		$titles = isset( $bundle['course_match_titles'] ) ? (array) $bundle['course_match_titles'] : array();
		foreach ( $titles as $title ) {
			$title = trim( (string) $title );
			if ( '' === $title || ! class_exists( 'CTA_Database' ) ) {
				continue;
			}
			$course = CTA_Database::get_course_by_title( $title );
			if ( $course ) {
				return $course;
			}
		}
		return null;
	}

	/**
	 * Copy a local filesystem file into protected course materials storage.
	 *
	 * @param string $source_path Absolute source path.
	 * @param int    $course_id   Course ID.
	 * @return array|WP_Error
	 */
	public static function import_local_file_to_protected( $source_path, $course_id ) {
		$course_id   = absint( $course_id );
		$source_path = (string) $source_path;

		if ( ! $course_id || ! is_readable( $source_path ) ) {
			return new WP_Error( 'cta_missing_file', __( 'Source material file not found.', 'cta-lms' ) );
		}

		if ( self::is_admin_restricted_source_path( $source_path ) ) {
			return new WP_Error(
				'cta_admin_restricted',
				__( 'Admin-restricted source and control documents cannot be published to students.', 'cta-lms' )
			);
		}

		$size = filesize( $source_path );
		if ( false !== $size && $size > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error(
				'cta_resource_too_large',
				__( 'File exceeds the 25MB size limit. Please upload a smaller PDF, DOC, DOCX, or MP3 file.', 'cta-lms' )
			);
		}

		$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::allowed_extensions(), true ) ) {
			return new WP_Error(
				'cta_resource_invalid_type',
				__( 'Only PDF, DOC, DOCX, and MP3 files are allowed for course materials.', 'cta-lms' )
			);
		}

		$root = self::get_protected_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$course_dir = trailingslashit( $root ) . $course_id;
		if ( ! wp_mkdir_p( $course_dir ) ) {
			return new WP_Error( 'cta_mkdir', __( 'Could not create course materials folder.', 'cta-lms' ) );
		}
		self::ensure_deny_rules( $course_dir );

		$filename = wp_unique_filename( $course_dir, basename( $source_path ) );
		$dest     = trailingslashit( $course_dir ) . $filename;

		if ( ! copy( $source_path, $dest ) ) {
			return new WP_Error( 'cta_copy', __( 'Could not copy file into protected storage.', 'cta-lms' ) );
		}

		$relative = self::UPLOAD_SUBDIR . '/' . $course_id . '/' . $filename;

		return array(
			'relative_path' => $relative,
			'absolute_path' => $dest,
			'file_type'     => $ext,
			'file_url'      => 'cta-protected://' . $relative,
		);
	}

	/**
	 * Resolve an absolute filesystem path for a resource, if local.
	 *
	 * @param object $resource Resource row.
	 * @return string Empty when not a local protected/attachment file.
	 */
	public static function resolve_local_path( $resource ) {
		if ( ! $resource ) {
			return '';
		}

		if ( ! empty( $resource->file_path ) ) {
			$upload = wp_upload_dir();
			if ( empty( $upload['error'] ) ) {
				$path = trailingslashit( $upload['basedir'] ) . ltrim( (string) $resource->file_path, '/\\' );
				if ( file_exists( $path ) ) {
					return $path;
				}
			}
		}

		if ( ! empty( $resource->attachment_id ) ) {
			$path = get_attached_file( (int) $resource->attachment_id );
			if ( $path && file_exists( $path ) ) {
				return $path;
			}
		}

		if ( ! empty( $resource->file_url ) && 0 !== strpos( (string) $resource->file_url, 'cta-protected://' ) ) {
			$upload = wp_upload_dir();
			$url    = (string) $resource->file_url;
			if ( empty( $upload['error'] ) && 0 === strpos( $url, $upload['baseurl'] ) ) {
				$relative = substr( $url, strlen( $upload['baseurl'] ) );
				$path     = trailingslashit( $upload['basedir'] ) . ltrim( $relative, '/\\' );
				if ( file_exists( $path ) ) {
					return $path;
				}
			}
		}

		return '';
	}

	/**
	 * Stream a resource to the browser after access checks (admin-post handler).
	 */
	public static function handle_serve_request() {
		$resource_id = absint( wp_unslash( $_GET['resource_id'] ?? 0 ) );
		$download    = ! empty( $_GET['cta_download'] );

		if ( ! $resource_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cta_serve_resource_' . $resource_id ) ) {
			wp_die( esc_html__( 'Invalid download request.', 'cta-lms' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		$resource = CTA_Database::get_downloadable_resource( $resource_id );

		if ( ! $resource ) {
			wp_die( esc_html__( 'File not found.', 'cta-lms' ), 404 );
		}

		$local_probe = self::resolve_local_path( $resource );
		$path_bits   = trim(
			(string) ( $local_probe ? $local_probe : '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->title ?? '' )
		);
		if ( self::is_admin_restricted_source_path( $path_bits ) ) {
			wp_die( esc_html__( 'This file is not available.', 'cta-lms' ), 403 );
		}

		$user_id = get_current_user_id();
		if ( ! self::user_can_access( $user_id, $resource ) ) {
			$gate_msg = self::get_unlock_lock_message( $user_id, $resource );
			if ( $gate_msg ) {
				wp_die( esc_html( $gate_msg ), 403 );
			}
			wp_die( esc_html__( 'You must be enrolled in this course to download materials.', 'cta-lms' ), 403 );
		}

		$local = self::resolve_local_path( $resource );

		if ( $local ) {
			$filename = sanitize_file_name( basename( $local ) );
			$mime     = wp_check_filetype( $filename );
			$type     = ! empty( $mime['type'] ) ? $mime['type'] : 'application/octet-stream';
			$size     = (int) filesize( $local );

			nocache_headers();
			header( 'Content-Type: ' . $type );
			header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . $filename . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Accept-Ranges: bytes' );

			$start = 0;
			$end   = max( 0, $size - 1 );
			$code  = 200;

			$range_header = isset( $_SERVER['HTTP_RANGE'] ) ? (string) wp_unslash( $_SERVER['HTTP_RANGE'] ) : '';
			if ( '' !== $range_header && preg_match( '/bytes=(\d*)-(\d*)/', $range_header, $m ) ) {
				if ( '' !== $m[1] ) {
					$start = (int) $m[1];
				}
				if ( '' !== $m[2] ) {
					$end = (int) $m[2];
				}
				if ( $end >= $size ) {
					$end = $size - 1;
				}
				if ( $start > $end || $start >= $size || $size <= 0 ) {
					status_header( 416 );
					header( 'Content-Range: bytes */' . $size );
					exit;
				}
				$code = 206;
				header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
			}

			$length = ( $size > 0 ) ? ( $end - $start + 1 ) : 0;
			status_header( $code );
			header( 'Content-Length: ' . (string) $length );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$fp = fopen( $local, 'rb' );
			if ( false === $fp ) {
				wp_die( esc_html__( 'File is unavailable.', 'cta-lms' ), 404 );
			}
			if ( $start > 0 ) {
				fseek( $fp, $start );
			}
			$left = $length;
			while ( $left > 0 && ! feof( $fp ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
				$chunk = fread( $fp, min( 8192, $left ) );
				if ( false === $chunk || '' === $chunk ) {
					break;
				}
				echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary stream
				$left -= strlen( $chunk );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $fp );
			exit;
		}

		// Legacy external URL fallback (still gated by enrollment above).
		$url = (string) ( $resource->file_url ?? '' );
		if ( $url && 0 !== strpos( $url, 'cta-protected://' ) ) {
			if ( $download ) {
				$tmp = wp_tempnam( $url );
				if ( ! $tmp ) {
					wp_die( esc_html__( 'Unable to prepare this download.', 'cta-lms' ), 500 );
				}

				$response = wp_safe_remote_get(
					$url,
					array(
						'timeout'     => 60,
						'redirection' => 3,
						'stream'      => true,
						'filename'    => $tmp,
					)
				);

				if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_readable( $tmp ) ) {
					wp_delete_file( $tmp );
					wp_die( esc_html__( 'The external file is unavailable.', 'cta-lms' ), 404 );
				}

				$url_path = (string) wp_parse_url( $url, PHP_URL_PATH );
				$filename = sanitize_file_name( basename( $url_path ) );
				if ( '' === $filename ) {
					$filename = 'cta-resource-' . $resource_id;
				}
				$type = wp_remote_retrieve_header( $response, 'content-type' );
				$type = $type ? sanitize_text_field( (string) $type ) : 'application/octet-stream';
				$size = (int) filesize( $tmp );

				nocache_headers();
				header( 'Content-Type: ' . $type );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'Content-Length: ' . (string) $size );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
				readfile( $tmp );
				wp_delete_file( $tmp );
				exit;
			}

			wp_safe_redirect( esc_url_raw( $url ) );
			exit;
		}

		wp_die( esc_html__( 'File is unavailable.', 'cta-lms' ), 404 );
	}

	/**
	 * Strip admin/source/control package files from a student-facing resource list.
	 *
	 * @param array $resources Resource rows.
	 * @return array
	 */
	public static function filter_student_visible_resources( $resources ) {
		$out = array();
		foreach ( (array) $resources as $resource ) {
			$path_bits = trim(
				(string) ( $resource->file_path ?? '' ) . ' ' .
				(string) ( $resource->file_url ?? '' ) . ' ' .
				(string) ( $resource->title ?? '' )
			);
			if ( self::is_admin_restricted_source_path( $path_bits ) ) {
				continue;
			}
			if ( self::is_archived_resource( $resource ) ) {
				continue;
			}
			$out[] = $resource;
		}
		return $out;
	}

	/**
	 * Whether a downloadable resource has been flagged as archived for learners.
	 *
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function is_archived_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' )
			&& CTA_Lmft_Clinical_Legacy_Forms_Archive::is_archived_resource( $resource ) ) {
			return true;
		}

		$title = trim( (string) ( $resource->title ?? '' ) );
		return 0 === stripos( $title, '[archived]' );
	}

	/**
	 * Group resources for display (course-level vs per-module).
	 *
	 * @param array $resources Resource rows.
	 * @param array $modules   Optional module rows.
	 * @return array{course:array,modules:array}
	 */
	public static function group_for_display( $resources, $modules = array() ) {
		$module_titles = array();
		foreach ( (array) $modules as $module ) {
			$module_titles[ (int) $module->id ] = $module->title;
		}

		$grouped = array(
			'course'  => array(),
			'modules' => array(),
		);

		foreach ( self::filter_student_visible_resources( $resources ) as $resource ) {
			$module_id = isset( $resource->module_id ) ? absint( $resource->module_id ) : 0;
			if ( $module_id > 0 ) {
				if ( ! isset( $grouped['modules'][ $module_id ] ) ) {
					$grouped['modules'][ $module_id ] = array(
						'title'     => isset( $module_titles[ $module_id ] ) ? $module_titles[ $module_id ] : __( 'Module', 'cta-lms' ),
						'resources' => array(),
					);
				}
				$grouped['modules'][ $module_id ]['resources'][] = $resource;
			} else {
				$grouped['course'][] = $resource;
			}
		}

		return $grouped;
	}
}

}
