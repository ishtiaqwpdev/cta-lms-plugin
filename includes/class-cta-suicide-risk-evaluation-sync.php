<?php
/**
 * Advanced Suicide Risk Assessment (CTA-CE-003) course evaluation sync.
 *
 * Replaces the generic CAMFT template with the approved evaluation structure,
 * inline completion attestation (Section 9), and syllabus-aligned objectives.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Suicide_Risk_Evaluation_Sync
 */
if ( ! class_exists( 'CTA_Suicide_Risk_Evaluation_Sync' ) ) {

class CTA_Suicide_Risk_Evaluation_Sync {

	const COURSE_CODE = 'CTA-CE-003';
	const SEED_OPTION = 'cta_suicide_risk_evaluation_1_0_214';

	/**
	 * Exact attestation statement from Section 9 (verbatim).
	 *
	 * @return string
	 */
	public static function attestation_statement() {
		return 'By submitting this evaluation, I attest that I personally completed all six required instructional modules in this asynchronous course and completed the final examination. I understand that the course-specific evaluation and this attestation are required before the CE certificate is issued.';
	}

	/**
	 * @return string[]
	 */
	public static function match_titles() {
		return class_exists( 'CTA_Suicide_Risk_Module_Sync' )
			? CTA_Suicide_Risk_Module_Sync::match_titles()
			: array(
				'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
				'Advanced Suicide Risk Assessment',
			);
	}

	/**
	 * Section 4 learning objectives — must match Chunk 1 syllabus learner outcomes exactly.
	 *
	 * @return string[]
	 */
	public static function evaluation_learning_objectives() {
		return array(
			'Identify at least five warning signs, risk factors, or protective factors associated with suicide risk and distinguish chronic suicidal ideation, acute suicidal intent, and non-suicidal self-injury.',
			'Apply the Columbia-Suicide Severity Rating Scale and the SAFE-T framework to a clinical case and classify information needed for a comprehensive suicide-risk formulation.',
			'Prepare a collaborative six-step safety plan that includes warning signs, coping strategies, social supports, professional resources, and lethal-means safety.',
			'Identify three clinical or legal factors relevant to a California danger-to-self determination and select an appropriate crisis-response or level-of-care action.',
			'Write a suicide-risk documentation note that includes at least four elements supporting clinical rationale, consultation, intervention, and follow-up.',
			'Design a postvention and clinician-support protocol that addresses continuity of care, consultation, countertransference, secondary traumatic stress, and professional wellness.',
		);
	}

	/**
	 * @return array[]
	 */
	public static function get_questions() {
		$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/suicide-risk-evaluation.php';
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * @return object|null
	 */
	public static function find_course() {
		if ( class_exists( 'CTA_Suicide_Risk_Module_Sync' ) ) {
			return CTA_Suicide_Risk_Module_Sync::find_course();
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
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_suicide_risk_course( $course_id ) {
		$course_id = absint( $course_id );
		if ( ! $course_id ) {
			return false;
		}

		$course = self::find_course();
		if ( $course && (int) $course->id === $course_id ) {
			return true;
		}

		if ( ! class_exists( 'CTA_Database' ) ) {
			return false;
		}

		$row = CTA_Database::get_course( $course_id );
		if ( ! $row || empty( $row->syllabus_meta ) ) {
			return false;
		}

		$meta = json_decode( (string) $row->syllabus_meta, true );
		return is_array( $meta ) && self::COURSE_CODE === (string) ( $meta['course_code'] ?? '' );
	}

	/**
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function evaluation_includes_attestation( $course_id ) {
		if ( ! self::is_suicide_risk_course( $course_id ) || ! class_exists( 'CTA_Evaluation_Questions' ) ) {
			return false;
		}

		$row = CTA_Evaluation_Questions::get_question_by_key( $course_id, 'sra_attest_complete' );
		return $row && 'active' === (string) $row->status;
	}

	/**
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
				'message'   => 'suicide_risk_course_not_found',
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

		self::sync_course_learning_objectives( $course_id );

		$likert        = CTA_Evaluation_Questions::default_rating_options();
		$allowed_keys  = array();
		$order         = 1;
		$upserted      = 0;

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

		if ( class_exists( 'CTA_Course_Catalog' ) ) {
			CTA_Course_Catalog::unpublish_all_ce_courses_pending_cepa();
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
