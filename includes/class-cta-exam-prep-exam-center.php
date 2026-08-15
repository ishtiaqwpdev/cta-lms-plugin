<?php
/**
 * Exam Prep Practice Exams (Exam Center) — program-level simulation listing.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Prep_Exam_Center
 */
if ( ! class_exists( 'CTA_Exam_Prep_Exam_Center' ) ) {

class CTA_Exam_Prep_Exam_Center {

	/**
	 * Build enriched exam rows for the Practice Exams section.
	 *
	 * @param object|null           $course    Course row.
	 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
	 * @return array<string,mixed>
	 */
	public static function get_center_data_for_course( $course, $dashboard ) {
		if ( ! $course || ! $dashboard ) {
			return self::empty_center_data();
		}

		$course_id = (int) $course->id;
		$user_id   = get_current_user_id();

		$resources = class_exists( 'CTA_Database' )
			? CTA_Database::get_downloadable_resources( $course_id )
			: array();

		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}

		$all_cards       = self::build_exam_cards( $course_id, $user_id, $dashboard, $resources );
		$enrollment      = class_exists( 'CTA_Database' )
			? CTA_Database::get_user_enrollment( $user_id, $course_id )
			: null;
		$modules_complete = class_exists( 'CTA_CE_Completion' )
			? CTA_CE_Completion::modules_complete( $user_id, $course_id, $enrollment )
			: ( $enrollment && (int) $enrollment->progress >= 100 );

		foreach ( $all_cards as &$card ) {
			$has_active_attempt  = ! empty( $card['has_active_attempt'] );
			$card['entry_locked'] = ! $modules_complete && ! $has_active_attempt;
			$card['lock_message'] = $card['entry_locked']
				? __( 'Complete all program workbooks before starting this assessment.', 'cta-lms' )
				: '';
		}
		unset( $card );

		$simulations     = array();
		$cumulative_banks = array();

		foreach ( $all_cards as $card ) {
			$category = (string) ( $card['category'] ?? 'full_simulation' );
			if ( 'cumulative_bank' === $category ) {
				$cumulative_banks[] = $card;
			} else {
				$simulations[] = $card;
			}
		}

		$attempted_count = 0;
		$passed_count    = 0;

		foreach ( $all_cards as $exam ) {
			if ( ! empty( $exam['attempt_count'] ) ) {
				++$attempted_count;
			}
			if ( ! empty( $exam['passed'] ) ) {
				++$passed_count;
			}
		}

		$data = array(
			'exams'              => $all_cards,
			'simulations'        => $simulations,
			'cumulative_banks'   => $cumulative_banks,
			'exam_count'         => count( $all_cards ),
			'simulation_count'   => count( $simulations ),
			'cumulative_count'   => count( $cumulative_banks ),
			'attempted_count'    => $attempted_count,
			'passed_count'       => $passed_count,
			'has_exams'          => ! empty( $all_cards ),
			'has_simulations'    => ! empty( $simulations ),
			'has_cumulative'     => ! empty( $cumulative_banks ),
			'exams_url'          => $dashboard->get_player_view_url( $course_id, 'exams' ),
		);

		/**
		 * Filter Practice Exams (Exam Center) payload for a course.
		 *
		 * @param array<string,mixed>   $data      Center data.
		 * @param object              $course    Course row.
		 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
		 */
		return apply_filters( 'cta_exam_prep_exam_center_data', $data, $course, $dashboard );
	}

	/**
	 * Default empty center payload.
	 *
	 * @return array<string,mixed>
	 */
	public static function empty_center_data() {
		return array(
			'exams'              => array(),
			'simulations'        => array(),
			'cumulative_banks'   => array(),
			'exam_count'         => 0,
			'simulation_count'   => 0,
			'cumulative_count'   => 0,
			'attempted_count'    => 0,
			'passed_count'       => 0,
			'has_exams'          => false,
			'has_simulations'    => false,
			'has_cumulative'     => false,
			'exams_url'          => '',
		);
	}

	/**
	 * Build exam card rows for program-level quizzes.
	 *
	 * @param int                   $course_id Course ID.
	 * @param int                   $user_id   User ID.
	 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
	 * @param array                 $resources Downloadable resources.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_exam_cards( $course_id, $user_id, $dashboard, array $resources ) {
		$cards = array();

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, true ) as $qrow ) {
			if ( empty( CTA_Database::get_quiz_questions( (int) $qrow->id ) ) ) {
				continue;
			}

			if ( class_exists( 'CTA_Exam_Prep_Workbooks' ) && ! CTA_Exam_Prep_Workbooks::is_program_level_quiz( $qrow ) ) {
				continue;
			}

			$category = class_exists( 'CTA_Exam_Prep_Workbooks' )
				? CTA_Exam_Prep_Workbooks::get_assessment_category( $qrow )
				: 'full_simulation';

			if ( 'workbook_bank' === $category ) {
				continue;
			}

			$quiz_id   = (int) $qrow->id;
			$attempts  = CTA_Database::get_user_quiz_attempts( $user_id, $quiz_id );
			$active    = CTA_Database::get_active_quiz_attempt( $user_id, $quiz_id );
			$best      = self::get_best_attempt( $attempts );
			$latest    = ! empty( $attempts ) ? $attempts[0] : null;
			$q_count   = count( CTA_Database::get_quiz_questions( $quiz_id ) );
			$quiz_url  = $dashboard->get_quiz_url( $course_id, $quiz_id );
			$has_tried = ! empty( $attempts );

			$cards[] = array(
				'quiz'             => $qrow,
				'quiz_id'          => $quiz_id,
				'title'            => (string) $qrow->title,
				'question_count'   => $q_count,
				'category'         => $category,
				'category_label'   => class_exists( 'CTA_Exam_Prep_Workbooks' )
					? CTA_Exam_Prep_Workbooks::get_assessment_category_label( $category, $qrow )
					: '',
				'type_label'       => self::get_exam_type_label( $qrow ),
				'url'              => $quiz_url,
				'review_url'       => $quiz_url,
				'attempts'         => self::normalize_attempts( $attempts ),
				'attempt_count'    => count( $attempts ),
				'best'             => $best,
				'latest'           => $latest,
				'best_score'       => $best ? (int) $best->score : null,
				'latest_score'     => $latest ? (int) $latest->score : null,
				'passed'           => $best && (int) $best->passed,
				'has_attempts'     => $has_tried,
				'has_active_attempt' => (bool) $active,
				'review_materials' => $has_tried
					? self::get_review_materials( $course_id, $user_id, $qrow, $resources )
					: array(),
				'sort_weight'      => self::get_quiz_sort_weight( $qrow ),
			);
		}

		usort(
			$cards,
			static function ( $a, $b ) {
				$wa = (int) ( $a['sort_weight'] ?? 100 );
				$wb = (int) ( $b['sort_weight'] ?? 100 );
				if ( $wa === $wb ) {
					return strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
				}
				return $wa <=> $wb;
			}
		);

		return $cards;
	}

	/**
	 * Human-readable exam format label.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return string
	 */
	public static function get_exam_type_label( $quiz ) {
		if ( ! $quiz ) {
			return __( 'Practice Assessment', 'cta-lms' );
		}

		$type  = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		$title = strtolower( (string) ( $quiz->title ?? '' ) );

		if ( in_array( $type, array( 'comprehensive_final' ), true )
			|| false !== strpos( $title, 'comprehensive final' )
			|| false !== strpos( $title, 'final readiness' ) ) {
			return __( 'Final Readiness Simulation', 'cta-lms' );
		}

		if ( preg_match( '/^checkpoint_[123]$/', $type )
			|| false !== strpos( $title, 'checkpoint' ) ) {
			return __( 'Checkpoint Assessment', 'cta-lms' );
		}

		if ( in_array( $type, array( 'form_a', 'form_b', 'practice_a', 'practice_b' ), true )
			|| false !== strpos( $title, 'form a' )
			|| false !== strpos( $title, 'form b' )
			|| false !== strpos( $title, 'practice exam' )
			|| false !== strpos( $title, 'comprehensive simulation' ) ) {
			return __( 'Full-Length Simulation', 'cta-lms' );
		}

		return __( 'Practice Assessment', 'cta-lms' );
	}

	/**
	 * Sort weight for consistent exam ordering across programs.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return int
	 */
	private static function get_quiz_sort_weight( $quiz ) {
		if ( ! $quiz ) {
			return 999;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		$map  = array(
			'form_a'              => 10,
			'practice_a'          => 11,
			'checkpoint_1'        => 20,
			'checkpoint_2'        => 30,
			'form_b'              => 40,
			'practice_b'          => 41,
			'checkpoint_3'        => 50,
			'comprehensive_final' => 60,
		);

		if ( isset( $map[ $type ] ) ) {
			return (int) $map[ $type ];
		}

		return 100 + (int) ( $quiz->sort_order ?? 0 );
	}

	/**
	 * Highest-scoring completed attempt.
	 *
	 * @param array $attempts Attempt rows.
	 * @return object|null
	 */
	private static function get_best_attempt( array $attempts ) {
		$best = null;

		foreach ( $attempts as $att ) {
			if ( null === $best || (int) $att->score > (int) $best->score ) {
				$best = $att;
			}
		}

		return $best;
	}

	/**
	 * Normalize attempt rows for template display.
	 *
	 * @param array $attempts Attempt rows (newest first).
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_attempts( array $attempts ) {
		$rows = array();

		foreach ( $attempts as $att ) {
			$rows[] = array(
				'id'             => (int) $att->id,
				'attempt_number' => (int) ( $att->attempt_number ?? 0 ),
				'score'          => (int) $att->score,
				'passed'         => (bool) (int) $att->passed,
				'completed_at'   => (string) ( $att->completed_at ?? '' ),
				'completed_label'=> self::format_attempt_date( (string) ( $att->completed_at ?? '' ) ),
			);
		}

		return $rows;
	}

	/**
	 * Format attempt completion timestamp for display.
	 *
	 * @param string $mysql_datetime MySQL datetime.
	 * @return string
	 */
	private static function format_attempt_date( $mysql_datetime ) {
		if ( '' === trim( (string) $mysql_datetime ) ) {
			return '';
		}

		$ts = strtotime( $mysql_datetime );
		if ( ! $ts ) {
			return '';
		}

		return date_i18n( get_option( 'date_format' ), $ts );
	}

	/**
	 * Match post-exam review downloads to a program-level quiz.
	 *
	 * @param int         $course_id Course ID.
	 * @param int         $user_id   User ID.
	 * @param object      $quiz      Quiz row.
	 * @param array       $resources Resource rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_review_materials( $course_id, $user_id, $quiz, array $resources ) {
		$materials = array();
		$seen      = array();

		foreach ( $resources as $resource ) {
			if ( ! self::resource_matches_quiz( $resource, $quiz ) ) {
				continue;
			}

			$kind = self::classify_review_resource( $resource );
			if ( '' === $kind ) {
				continue;
			}

			$resource_id = (int) $resource->id;
			if ( isset( $seen[ $kind ] ) ) {
				continue;
			}

			$accessible = class_exists( 'CTA_Course_Materials' )
				? CTA_Course_Materials::user_can_access( $user_id, $resource )
				: false;

			$lock_message = '';
			if ( ! $accessible && class_exists( 'CTA_Course_Materials' ) ) {
				$lock_message = CTA_Course_Materials::get_unlock_lock_message( $user_id, $resource );
				if ( '' === $lock_message ) {
					$lock_message = __( 'Complete this exam to unlock review materials.', 'cta-lms' );
				}
			}

			$url = ( $accessible && class_exists( 'CTA_Course_Materials' ) )
				? CTA_Course_Materials::get_serve_url( $resource_id )
				: '';

			$materials[] = array(
				'key'          => $kind,
				'label'        => self::get_review_material_label( $kind, $resource ),
				'url'          => $url,
				'accessible'   => $accessible && '' !== $url,
				'locked'       => ! $accessible,
				'lock_message' => $lock_message,
				'resource_id'  => $resource_id,
			);

			$seen[ $kind ] = true;
		}

		$order = array( 'answer_key', 'remediation' );
		usort(
			$materials,
			static function ( $a, $b ) use ( $order ) {
				$ia = array_search( (string) ( $a['key'] ?? '' ), $order, true );
				$ib = array_search( (string) ( $b['key'] ?? '' ), $order, true );
				$ia = false === $ia ? 99 : $ia;
				$ib = false === $ib ? 99 : $ib;
				return $ia <=> $ib;
			}
		);

		return $materials;
	}

	/**
	 * Display label for a review material link.
	 *
	 * @param string $kind     Material kind key.
	 * @param object $resource Resource row.
	 * @return string
	 */
	private static function get_review_material_label( $kind, $resource ) {
		if ( 'remediation' === $kind ) {
			return __( 'Performance & Remediation Workbook', 'cta-lms' );
		}

		$title = (string) ( $resource->title ?? '' );
		if ( false !== stripos( $title, 'Answer Key' ) && false !== stripos( $title, 'Rationale' ) ) {
			return __( 'Answer Key & Detailed Rationales', 'cta-lms' );
		}
		if ( false !== stripos( $title, 'Rationale' ) ) {
			return __( 'Detailed Rationales', 'cta-lms' );
		}

		return __( 'Controlled Answer Key', 'cta-lms' );
	}

	/**
	 * Classify a resource as answer-key or remediation review material.
	 *
	 * @param object|null $resource Resource row.
	 * @return string Kind key or empty string.
	 */
	private static function classify_review_resource( $resource ) {
		if ( ! $resource ) {
			return '';
		}

		$hay = (string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' );

		if ( false !== stripos( $hay, 'Remediation' )
			|| false !== stripos( $hay, 'Performance Analysis' )
			|| false !== stripos( $hay, 'Remediation Plan' )
			|| false !== stripos( $hay, 'Remediation Guide' )
			|| false !== stripos( $hay, 'Remediation Workbook' )
			|| false !== stripos( $hay, 'Remediation Worksheet' ) ) {
			return 'remediation';
		}

		if ( class_exists( 'CTA_Course_Materials' ) && CTA_Course_Materials::is_protected_rationale_resource( $resource ) ) {
			return 'answer_key';
		}

		return '';
	}

	/**
	 * Whether a downloadable resource belongs to a given program-level quiz.
	 *
	 * @param object|null $resource Resource row.
	 * @param object|null $quiz     Quiz row.
	 * @return bool
	 */
	private static function resource_matches_quiz( $resource, $quiz ) {
		if ( ! $resource || ! $quiz ) {
			return false;
		}

		if ( ! empty( $resource->is_practice_test ) ) {
			return false;
		}

		if ( ! empty( $resource->module_id ) ) {
			return false;
		}

		$unlock    = sanitize_key( (string) ( $resource->unlock_after_quiz_type ?? '' ) );
		$quiz_type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );

		if ( '' !== $unlock && '' !== $quiz_type && $unlock === $quiz_type ) {
			return true;
		}

		$haystack = strtolower(
			(string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' )
		);

		foreach ( self::get_quiz_match_patterns( $quiz ) as $pattern ) {
			if ( false !== strpos( $haystack, strtolower( (string) $pattern ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Title/path match tokens for associating review files with a quiz.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return string[]
	 */
	private static function get_quiz_match_patterns( $quiz ) {
		if ( ! $quiz ) {
			return array();
		}

		$type     = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		$patterns = array();

		switch ( $type ) {
			case 'form_a':
				$patterns = array( 'form a', 'form_a', 'simulation_form_a' );
				break;
			case 'form_b':
				$patterns = array( 'form b', 'form_b', 'simulation_form_b' );
				break;
			case 'practice_a':
				$patterns = array( 'practice examination a', 'practice exam a', 'practice_a', 'practice-a' );
				break;
			case 'practice_b':
				$patterns = array( 'practice examination b', 'practice exam b', 'practice_b', 'practice-b' );
				break;
			case 'comprehensive_final':
				$patterns = array( 'comprehensive final', 'comprehensive_final', 'comprehensive-final' );
				break;
			case 'checkpoint_1':
				$patterns = array( 'checkpoint 1', 'checkpoint_1', 'checkpoint-1' );
				break;
			case 'checkpoint_2':
				$patterns = array( 'checkpoint 2', 'checkpoint_2', 'checkpoint-2' );
				break;
			case 'checkpoint_3':
				$patterns = array( 'checkpoint 3', 'checkpoint_3', 'checkpoint-3' );
				break;
		}

		$title = strtolower( (string) ( $quiz->title ?? '' ) );
		if ( preg_match( '/form\s+a\b/i', (string) $quiz->title ) ) {
			$patterns[] = 'form a';
		}
		if ( preg_match( '/form\s+b\b/i', (string) $quiz->title ) ) {
			$patterns[] = 'form b';
		}
		if ( false !== strpos( $title, 'checkpoint' ) && preg_match( '/checkpoint\s*(\d+)/i', (string) $quiz->title, $m ) ) {
			$patterns[] = 'checkpoint ' . (int) $m[1];
		}
		if ( false !== strpos( $title, 'comprehensive final' ) ) {
			$patterns[] = 'comprehensive final';
		}

		return array_values( array_unique( array_filter( $patterns ) ) );
	}
}

}
