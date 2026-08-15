<?php
/**
 * Strict CE course completion sequence gates.
 *
 * Required order:
 * Modules (incl. Capstone) → Final Exam (pass) → Evaluation → Attestation → Certificate
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_CE_Completion
 */
if ( ! class_exists( 'CTA_CE_Completion' ) ) {

class CTA_CE_Completion {

	/**
	 * Whether every active instructional module (including Capstone) is complete.
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $course_id  Course ID.
	 * @param object|null $enrollment Optional enrollment row.
	 * @return bool
	 */
	public static function modules_complete( $user_id, $course_id, $enrollment = null ) {
		if ( class_exists( 'CTA_Certificates' ) ) {
			return CTA_Certificates::user_completed_all_modules( $user_id, $course_id, $enrollment );
		}
		return false;
	}

	/**
	 * Whether the learner has a passing final-exam attempt.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function exam_passed( $user_id, $course_id ) {
		if ( class_exists( 'CTA_Certificates' ) ) {
			return CTA_Certificates::user_passed_final_exam( $user_id, $course_id );
		}
		return false;
	}

	/**
	 * Whether a course evaluation has been submitted.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function evaluation_complete( $user_id, $course_id ) {
		if ( ! class_exists( 'CTA_Database' ) ) {
			return false;
		}
		return (bool) CTA_Database::get_course_evaluation( $user_id, $course_id );
	}

	/**
	 * Whether course-completion attestation is on file (typed name required).
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function attestation_complete( $user_id, $course_id ) {
		if ( ! class_exists( 'CTA_Course_Attestation' ) ) {
			return true;
		}
		return CTA_Course_Attestation::has( $user_id, $course_id );
	}

	/**
	 * Can the learner open / start the final examination?
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error
	 */
	public static function assert_can_access_exam( $user_id, $course_id ) {
		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );

		if ( ! $user_id || ! $course_id ) {
			return new WP_Error( 'cta_seq_invalid', __( 'Invalid course access request.', 'cta-lms' ) );
		}

		$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		if ( ! $enrollment ) {
			return new WP_Error( 'cta_seq_enroll', __( 'You are not enrolled in this course.', 'cta-lms' ) );
		}

		if ( ! self::modules_complete( $user_id, $course_id, $enrollment ) ) {
			$course = class_exists( 'CTA_Database' ) ? CTA_Database::get_course( $course_id ) : null;
			$is_exam = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );
			return new WP_Error(
				'cta_seq_modules',
				$is_exam
					? __( 'Complete all program workbooks before starting this assessment.', 'cta-lms' )
					: __( 'Complete all instructional modules, including the Course Integration Capstone, before starting the final examination.', 'cta-lms' )
			);
		}

		return true;
	}

	/**
	 * Can the learner submit / view the course evaluation?
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error
	 */
	public static function assert_can_access_evaluation( $user_id, $course_id ) {
		$course = class_exists( 'CTA_Database' ) ? CTA_Database::get_course( absint( $course_id ) ) : null;
		if ( class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course ) ) {
			return new WP_Error(
				'cta_seq_exam_prep',
				__( 'Exam Preparation Programs do not use CE course evaluations.', 'cta-lms' )
			);
		}

		$exam = self::assert_can_access_exam( $user_id, $course_id );
		if ( is_wp_error( $exam ) ) {
			return $exam;
		}

		if ( ! self::exam_passed( $user_id, $course_id ) ) {
			return new WP_Error(
				'cta_seq_exam',
				__( 'You must pass the final examination before submitting the course evaluation.', 'cta-lms' )
			);
		}

		return true;
	}

	/**
	 * Can the learner submit a standalone attestation (non-inline courses)?
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error
	 */
	public static function assert_can_access_attestation( $user_id, $course_id ) {
		$eval = self::assert_can_access_evaluation( $user_id, $course_id );
		if ( is_wp_error( $eval ) ) {
			return $eval;
		}

		if ( ! self::evaluation_complete( $user_id, $course_id ) ) {
			return new WP_Error(
				'cta_seq_evaluation',
				__( 'Submit the course evaluation before the completion attestation.', 'cta-lms' )
			);
		}

		return true;
	}

	/**
	 * Can a CE certificate be issued?
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error
	 */
	public static function assert_can_issue_certificate( $user_id, $course_id ) {
		$attest = self::assert_can_access_attestation( $user_id, $course_id );
		if ( is_wp_error( $attest ) ) {
			return $attest;
		}

		if ( ! self::attestation_complete( $user_id, $course_id ) ) {
			return new WP_Error(
				'cta_seq_attestation',
				__( 'Complete the course-completion attestation before a CE certificate can be issued.', 'cta-lms' )
			);
		}

		return true;
	}

	/**
	 * Keep enrollment.progress aligned with actual module completion (Capstone included).
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $course_id  Course ID.
	 * @param object|null $enrollment Optional enrollment row.
	 * @return int Updated progress percentage.
	 */
	public static function sync_progress( $user_id, $course_id, $enrollment = null ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		if ( ! $user_id || ! $course_id ) {
			return 0;
		}

		if ( null === $enrollment ) {
			$enrollment = CTA_Database::get_user_enrollment( $user_id, $course_id );
		}
		if ( ! $enrollment ) {
			return 0;
		}

		$modules = CTA_Database::get_course_modules( $course_id );
		$total   = count( $modules );
		if ( $total < 1 ) {
			return (int) $enrollment->progress;
		}

		$completed = array();
		if ( ! empty( $enrollment->modules_completed ) ) {
			$decoded = json_decode( (string) $enrollment->modules_completed, true );
			if ( is_array( $decoded ) ) {
				$completed = array_map( 'absint', $decoded );
			}
		}

		$done = 0;
		foreach ( $modules as $module ) {
			if ( in_array( (int) $module->id, $completed, true ) ) {
				++$done;
			}
		}

		$progress = (int) round( ( $done / $total ) * 100 );

		if ( (int) $enrollment->progress !== $progress ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'cta_enrollments',
				array( 'progress' => $progress ),
				array( 'id' => (int) $enrollment->id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return $progress;
	}
}

}
