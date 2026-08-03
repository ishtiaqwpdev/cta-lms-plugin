<?php
/**
 * California Law & Ethics (CTA-CE-001) CAMFT 9-section evaluation sync.
 *
 * Replaces the course evaluation with the official v1.0 structure and keeps
 * the completion attestation inside the same evaluation flow (Section 9).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Law_Ethics_Evaluation_Sync
 */
if ( ! class_exists( 'CTA_Law_Ethics_Evaluation_Sync' ) ) {

class CTA_Law_Ethics_Evaluation_Sync {

	const COURSE_CODE = 'CTA-CE-001';
	const SEED_OPTION = 'cta_law_ethics_evaluation_1_0_122';

	/**
	 * Exact attestation statement from Course Evaluation v1.0 Section 9.
	 *
	 * @return string
	 */
	public static function attestation_statement() {
		return 'I attest that I personally completed the required instructional modules and embedded learning activities for this 6.0-hour asynchronous course. I understand that the CE certificate is issued only after all course requirements, including the final examination, course evaluation, and this attestation, are completed.';
	}

	/**
	 * Title aliases for the Law & Ethics CE course.
	 *
	 * @return string[]
	 */
	public static function match_titles() {
		return array(
			'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
			'California Law & Ethics for Mental Health Professionals',
		);
	}

	/**
	 * Official evaluation learning objectives (Section 3).
	 *
	 * @return string[]
	 */
	public static function evaluation_learning_objectives() {
		return array(
			'Identify four California legal, regulatory, or ethical sources that govern mental health practice and distinguish scope of practice from scope of competence.',
			'Prepare an informed-consent checklist that includes required fee, privacy, telehealth, digital-communication, and professional-boundary elements.',
			'Distinguish confidentiality, psychotherapist-patient privilege, and lawful disclosure and select an appropriate response to a subpoena or request for records.',
			'Apply California minor-consent, parental-involvement, custody, and mandated-reporting standards to a clinical case example.',
			'Apply California duty-to-protect, crisis-intervention, and documentation standards to a case involving suicide risk or a serious threat of violence.',
			'Prepare a record-management and practice-continuity plan that addresses client access, retention, security, practice closure, professional wills, and licensure or business-risk concerns.',
		);
	}

	/**
	 * Load the official evaluation question bank.
	 *
	 * @return array[]
	 */
	public static function get_questions() {
		$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/law-ethics-evaluation.php';
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * Find the Law & Ethics CE course.
	 *
	 * @return object|null
	 */
	public static function find_course() {
		if ( class_exists( 'CTA_Law_Ethics_Module_Sync' ) ) {
			return CTA_Law_Ethics_Module_Sync::find_course();
		}

		if ( ! class_exists( 'CTA_Database' ) ) {
			return null;
		}

		foreach ( self::match_titles() as $title ) {
			$course = CTA_Database::get_course_by_title( $title );
			if ( $course && ( ! class_exists( 'CTA_Exam_Access' ) || ! CTA_Exam_Access::is_exam_prep( $course ) ) ) {
				return $course;
			}
		}

		return null;
	}

	/**
	 * Whether a course ID is CTA-CE-001 Law & Ethics.
	 *
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_law_ethics_course( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return false;
		}
		$course = self::find_course();
		return $course && (int) $course->id === $course_id;
	}

	/**
	 * Whether this course's evaluation includes Section 9 attestation fields.
	 *
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function evaluation_includes_attestation( $course_id ) {
		if ( ! self::is_law_ethics_course( $course_id ) || ! class_exists( 'CTA_Evaluation_Questions' ) ) {
			return false;
		}
		$row = CTA_Evaluation_Questions::get_question_by_key( $course_id, 'completion_attestation_agree' );
		return $row && 'active' === (string) $row->status;
	}

	/**
	 * Seed / replace the Law & Ethics evaluation with the official 9-section form.
	 *
	 * @param bool $force Re-run even if already seeded.
	 * @return array{ok:bool,course_id:int,questions:int,message:string}
	 */
	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'questions' => 0,
				'message'   => 'already_seeded',
			);
		}

		if ( ! class_exists( 'CTA_Evaluation_Questions' ) ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'questions' => 0,
				'message'   => 'evaluation_class_missing',
			);
		}

		CTA_Evaluation_Questions::install();

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'questions' => 0,
				'message'   => 'law_ethics_course_not_found',
			);
		}

		$course_id = (int) $course->id;
		$questions = self::get_questions();
		if ( empty( $questions ) ) {
			return array(
				'ok'        => false,
				'course_id' => $course_id,
				'questions' => 0,
				'message'   => 'question_bank_missing',
			);
		}

		// Align stored learning objectives with evaluation Section 3.
		self::sync_course_learning_objectives( $course_id );

		$likert      = CTA_Evaluation_Questions::default_rating_options();
		$allowed_keys = array();
		$order        = 1;
		$upserted     = 0;

		foreach ( $questions as $tpl ) {
			$key = sanitize_key( (string) ( $tpl['id'] ?? '' ) );
			if ( '' === $key ) {
				continue;
			}
			$allowed_keys[] = $key;

			$type    = CTA_Evaluation_Questions::normalize_type( $tpl['type'] ?? 'rating' );
			$options = isset( $tpl['options'] ) && is_array( $tpl['options'] ) ? $tpl['options'] : array();
			if ( 'rating' === $type && empty( $options ) ) {
				$options = $likert;
			}

			$source = isset( $tpl['source_type'] ) ? sanitize_key( (string) $tpl['source_type'] ) : 'custom';
			if ( ! in_array( $source, array( 'custom', 'learning_objective', 'camft' ), true ) ) {
				$source = 'custom';
			}

			$data = array(
				'course_id'       => $course_id,
				'question_key'    => $key,
				'section_label'   => sanitize_text_field( (string) ( $tpl['section'] ?? '' ) ),
				'label'           => (string) ( $tpl['label'] ?? '' ),
				'question_type'   => $type,
				'options'         => $options,
				'is_required'     => ! empty( $tpl['required'] ) ? 1 : 0,
				'summary_field'   => sanitize_key( (string) ( $tpl['summary'] ?? '' ) ),
				'order_index'     => $order++,
				'source_type'     => $source,
				'objective_index' => isset( $tpl['objective_index'] ) ? (int) $tpl['objective_index'] : -1,
				'status'          => 'active',
			);

			// Persist display value for info fields inside options_json.
			if ( 'info' === $type && ! empty( $tpl['value'] ) ) {
				$data['options'] = array(
					'display' => (string) $tpl['value'],
				);
			}

			$existing = CTA_Evaluation_Questions::get_question_by_key( $course_id, $key );
			if ( $existing ) {
				CTA_Evaluation_Questions::update_question( (int) $existing->id, $data );
			} else {
				CTA_Evaluation_Questions::insert_question( $data );
			}
			++$upserted;
		}

		// Deactivate any leftover questions not in the official bank (old A–E / telehealth items).
		foreach ( CTA_Evaluation_Questions::get_questions( 'active', $course_id ) as $row ) {
			$key = (string) $row->question_key;
			if ( in_array( $key, $allowed_keys, true ) ) {
				continue;
			}
			CTA_Evaluation_Questions::update_question(
				(int) $row->id,
				array( 'status' => 'inactive' )
			);
		}

		update_option( self::SEED_OPTION, 1, false );

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'questions' => $upserted,
			'message'   => 'synced',
		);
	}

	/**
	 * Write evaluation Section 3 objectives onto the course row.
	 *
	 * @param int $course_id Course ID.
	 */
	private static function sync_course_learning_objectives( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array(
				'learning_objectives' => wp_json_encode( self::evaluation_learning_objectives() ),
			),
			array( 'id' => $course_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}

}
