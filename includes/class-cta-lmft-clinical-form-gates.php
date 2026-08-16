<?php
/**
 * Sequential Form A / Form B unlock gates for LMFT California Clinical.
 *
 * Per Implementation Guide v1.1 §5 Form Release Sequence:
 * 1) Core curriculum + workbook practice sequence complete → Form A unlocks
 * 2) Form A submitted → learner reviews key/rationales and completes remediation
 * 3) Form A/remediation stage complete → Form B unlocks
 *
 * Core curriculum complete = all instructional workbooks marked complete
 * (modules_complete). Remediation = Form A Remediation Workbook when present;
 * otherwise Form A post-submit rationale review completion (stamped on results view).
 *
 * Wired only to active (non-archived) form_a / form_b quiz types.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Form_Gates
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Form_Gates' ) ) {

class CTA_Lmft_Clinical_Form_Gates {

	/**
	 * @param object|null $course Course row.
	 * @return bool
	 */
	public static function applies_to_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {
			return CTA_Lmft_Clinical_Legacy_Forms_Archive::is_lmft_clinical_course( $course );
		}

		return 'lmft-california-clinical-exam-preparation' === (string) ( $course->slug ?? '' );
	}

	/**
	 * Whether a quiz row is an active (non-archived) Form A/B simulation.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_active_form_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( ! in_array( $type, array( 'form_a', 'form_b' ), true ) ) {
			return false;
		}

		if ( class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' )
			&& CTA_Lmft_Clinical_Legacy_Forms_Archive::is_archived_quiz( $quiz ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Core curriculum complete = all instructional workbooks marked complete.
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $course_id  Course ID.
	 * @param object|null $enrollment Optional enrollment.
	 * @return bool
	 */
	public static function core_curriculum_complete( $user_id, $course_id, $enrollment = null ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		if ( ! $enrollment && class_exists( 'CTA_Database' ) ) {
			$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		}

		if ( class_exists( 'CTA_CE_Completion' ) ) {
			return CTA_CE_Completion::modules_complete( $user_id, $course_id, $enrollment );
		}

		return class_exists( 'CTA_Certificates' )
			&& CTA_Certificates::user_completed_all_modules( $user_id, $course_id, $enrollment );
	}

	/**
	 * Form A submitted on the active (non-archived) form_a quiz.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function form_a_submitted( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id || ! class_exists( 'CTA_Course_Materials' ) ) {
			return false;
		}

		return CTA_Course_Materials::user_has_completed_quiz_type( $user_id, $course_id, 'form_a' );
	}

	/**
	 * Form A remediation stage complete.
	 *
	 * Prefer explicit remediation workbook completion when that resource exists.
	 * Otherwise require the post-submit Form A review stamp (set when results/rationales
	 * are opened after Form A submission).
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function form_a_remediation_complete( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id || ! class_exists( 'CTA_Course_Materials' ) ) {
			return false;
		}

		if ( ! self::form_a_submitted( $user_id, $course_id ) ) {
			return false;
		}

		if ( CTA_Course_Materials::course_has_form_a_remediation( $course_id ) ) {
			return CTA_Course_Materials::user_has_completed_form_a_remediation( $user_id, $course_id );
		}

		return CTA_Course_Materials::user_has_completed_form_a_remediation( $user_id, $course_id );
	}

	/**
	 * Stamp Form A remediation complete after post-submit review (no workbook required).
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool Whether meta was written/already present.
	 */
	public static function mark_form_a_review_remediation_complete( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id || ! class_exists( 'CTA_Course_Materials' ) ) {
			return false;
		}

		if ( ! self::form_a_submitted( $user_id, $course_id ) ) {
			return false;
		}

		if ( CTA_Course_Materials::user_has_completed_form_a_remediation( $user_id, $course_id ) ) {
			return true;
		}

		// When a dedicated remediation workbook exists, only that workbook's AJAX
		// completion path should stamp the meta (preserves LPCC/AMFTRB behavior).
		if ( CTA_Course_Materials::course_has_form_a_remediation( $course_id ) ) {
			return false;
		}

		update_user_meta(
			$user_id,
			CTA_Course_Materials::form_a_remediation_meta_key( $course_id ),
			current_time( 'mysql' )
		);

		return true;
	}

	/**
	 * Whether Form A may be started.
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $course_id  Course ID.
	 * @param object|null $enrollment Optional enrollment.
	 * @return bool
	 */
	public static function can_access_form_a( $user_id, $course_id, $enrollment = null ) {
		return self::core_curriculum_complete( $user_id, $course_id, $enrollment );
	}

	/**
	 * Whether Form B may be started.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function can_access_form_b( $user_id, $course_id ) {
		return self::form_a_submitted( $user_id, $course_id )
			&& self::form_a_remediation_complete( $user_id, $course_id );
	}

	/**
	 * Evaluate access for an active Form A/B quiz.
	 *
	 * @param object|null $quiz       Quiz row.
	 * @param object|null $course     Course row.
	 * @param int         $user_id    User ID.
	 * @param object|null $enrollment Optional enrollment.
	 * @return true|WP_Error
	 */
	public static function assert_quiz_accessible( $quiz, $course, $user_id, $enrollment = null ) {
		if ( ! self::applies_to_course( $course ) || ! self::is_active_form_quiz( $quiz ) ) {
			return true;
		}

		$user_id   = absint( $user_id );
		$course_id = absint( $course->id ?? 0 );
		$type      = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );

		if ( 'form_a' === $type ) {
			if ( self::can_access_form_a( $user_id, $course_id, $enrollment ) ) {
				return true;
			}
			return new WP_Error(
				'form_a_locked',
				self::form_a_lock_message()
			);
		}

		if ( 'form_b' === $type ) {
			if ( self::can_access_form_b( $user_id, $course_id ) ) {
				return true;
			}
			return new WP_Error(
				'form_b_locked',
				self::form_b_lock_message( $user_id, $course_id )
			);
		}

		return true;
	}

	/**
	 * Short button label while locked.
	 *
	 * @param string $quiz_type form_a|form_b.
	 * @return string
	 */
	public static function lock_button_label( $quiz_type ) {
		$quiz_type = sanitize_key( (string) $quiz_type );
		if ( 'form_b' === $quiz_type ) {
			return __( 'Complete Form A Remediation to Unlock', 'cta-lms' );
		}
		return __( 'Complete All Workbooks to Unlock', 'cta-lms' );
	}

	/**
	 * @return string
	 */
	public static function form_a_lock_message() {
		return __( 'Complete all program workbooks before starting Form A.', 'cta-lms' );
	}

	/**
	 * Stage-aware Form B lock message.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function form_b_lock_message( $user_id = 0, $course_id = 0 ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( $user_id && $course_id && ! self::form_a_submitted( $user_id, $course_id ) ) {
			return __( 'Submit Form A and complete its remediation review before starting Form B.', 'cta-lms' );
		}

		if ( $user_id && $course_id && ! self::form_a_remediation_complete( $user_id, $course_id ) ) {
			if ( class_exists( 'CTA_Course_Materials' ) && CTA_Course_Materials::course_has_form_a_remediation( $course_id ) ) {
				return __( 'Complete the Form A Remediation Workbook before starting Form B.', 'cta-lms' );
			}
			return __( 'Review Form A answers and rationales (remediation) before starting Form B.', 'cta-lms' );
		}

		return __( 'Complete Form A and its remediation review to unlock Form B.', 'cta-lms' );
	}

	/**
	 * Card-level lock payload for Exam Center.
	 *
	 * @param object|null $quiz       Quiz row.
	 * @param object|null $course     Course row.
	 * @param int         $user_id    User ID.
	 * @param object|null $enrollment Optional enrollment.
	 * @param bool        $has_active Whether an in-progress attempt exists.
	 * @return array{entry_locked:bool,lock_message:string,lock_button_label:string}
	 */
	public static function get_card_lock_state( $quiz, $course, $user_id, $enrollment = null, $has_active = false ) {
		$default = array(
			'entry_locked'       => false,
			'lock_message'       => '',
			'lock_button_label'  => '',
		);

		if ( $has_active ) {
			return $default;
		}

		$check = self::assert_quiz_accessible( $quiz, $course, $user_id, $enrollment );
		if ( ! is_wp_error( $check ) ) {
			return $default;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );

		return array(
			'entry_locked'      => true,
			'lock_message'      => $check->get_error_message(),
			'lock_button_label' => self::lock_button_label( $type ),
		);
	}
}

}
