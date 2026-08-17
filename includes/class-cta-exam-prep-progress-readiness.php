<?php
/**
 * Exam Prep Progress / Readiness dashboard data provider.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Exam_Prep_Progress_Readiness' ) ) {

class CTA_Exam_Prep_Progress_Readiness {

	/**
	 * Build reusable learner progress data for an exam-prep course.
	 *
	 * @param object|null           $course        Course row.
	 * @param array                 $modules       Course module rows.
	 * @param array                 $completed_ids Completed module IDs.
	 * @param CTA_Student_Dashboard $dashboard     Dashboard instance.
	 * @return array<string,mixed>
	 */
	public static function get_dashboard_data( $course, array $modules, array $completed_ids, $dashboard ) {
		if ( ! $course || ! $dashboard ) {
			return self::empty_data();
		}

		$course_id       = (int) $course->id;
		$user_id         = get_current_user_id();
		$total_modules   = count( $modules );
		$completed_count = count(
			array_filter(
				$modules,
				static function ( $module ) use ( $completed_ids ) {
					return in_array( (int) $module->id, $completed_ids, true );
				}
			)
		);
		$module_percent  = $total_modules > 0
			? (int) round( ( $completed_count / $total_modules ) * 100 )
			: 0;

		$exam_data = class_exists( 'CTA_Exam_Prep_Exam_Center' )
			? CTA_Exam_Prep_Exam_Center::get_center_data_for_course( $course, $dashboard )
			: array();

		$workbook_banks = self::get_workbook_bank_progress( $course_id, $user_id, $dashboard );
		$resources      = self::get_visible_resources( $course_id );
		$readiness      = self::get_resource_cards( $resources, 'readiness' );
		$remediation    = self::get_resource_cards( $resources, 'remediation' );
		$flashcards     = self::get_flashcard_progress_config( $course, $dashboard, $user_id );
		$guidance       = self::build_guidance( $exam_data, $remediation, $dashboard, $course_id );

		$data = array(
			'overview'       => array(
				'percent'         => $module_percent,
				'completed_count' => $completed_count,
				'total_count'     => $total_modules,
			),
			'flashcards'     => $flashcards,
			'exams'          => array_values( (array) ( $exam_data['exams'] ?? array() ) ),
			'exam_summary'   => array(
				'total_count'     => (int) ( $exam_data['exam_count'] ?? 0 ),
				'attempted_count' => (int) ( $exam_data['attempted_count'] ?? 0 ),
				'passed_count'    => (int) ( $exam_data['passed_count'] ?? 0 ),
				'url'             => (string) ( $exam_data['exams_url'] ?? $dashboard->get_player_view_url( $course_id, 'exams' ) ),
			),
			'workbook_banks' => $workbook_banks,
			'readiness'      => $readiness,
			'remediation'    => $remediation,
			'guidance'       => $guidance,
			'has_readiness'  => ! empty( $readiness ),
			'has_remediation'=> ! empty( $remediation ),
		);

		return apply_filters( 'cta_exam_prep_progress_readiness_data', $data, $course, $dashboard );
	}

	/**
	 * Empty dashboard payload.
	 *
	 * @return array<string,mixed>
	 */
	public static function empty_data() {
		return array(
			'overview'        => array(
				'percent'         => 0,
				'completed_count' => 0,
				'total_count'     => 0,
			),
			'flashcards'      => array(),
			'exams'           => array(),
			'exam_summary'    => array(),
			'workbook_banks'  => array(),
			'readiness'       => array(),
			'remediation'     => array(),
			'guidance'        => array(),
			'has_readiness'   => false,
			'has_remediation' => false,
		);
	}

	/**
	 * Workbook-scoped knowledge-check / practice-bank progress.
	 *
	 * @param int                   $course_id Course ID.
	 * @param int                   $user_id   User ID.
	 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_workbook_bank_progress( $course_id, $user_id, $dashboard ) {
		$rows = array();

		foreach ( (array) CTA_Database::get_quizzes_by_course( $course_id, true ) as $quiz ) {
			if ( ! class_exists( 'CTA_Exam_Prep_Workbooks' ) || ! CTA_Exam_Prep_Workbooks::is_workbook_quiz( $quiz ) ) {
				continue;
			}

			$quiz_id   = (int) $quiz->id;
			$questions = CTA_Database::get_quiz_questions( $quiz_id );
			if ( empty( $questions ) ) {
				continue;
			}

			$attempts = CTA_Database::get_user_quiz_attempts( $user_id, $quiz_id );
			$active   = CTA_Database::get_active_quiz_attempt( $user_id, $quiz_id );
			$best_score = null;
			$passed     = false;

			foreach ( $attempts as $attempt ) {
				if ( ! CTA_Exam_Prep_Workbooks::attempt_is_submitted( $attempt ) ) {
					continue;
				}
				if ( CTA_Exam_Prep_Workbooks::attempt_answers_are_empty( $attempt->answers ?? null ) ) {
					continue;
				}
				$score = (int) $attempt->score;
				if ( null === $best_score || $score > $best_score ) {
					$best_score = $score;
				}
				$passed = $passed || ! empty( $attempt->passed );
			}

			$card = array(
				'quiz'     => $quiz,
				'attempts' => $attempts,
				'active'   => $active,
				'best'     => null,
				'passed'   => $passed,
			);
			foreach ( $attempts as $attempt ) {
				if ( CTA_Exam_Prep_Workbooks::attempt_is_submitted( $attempt )
					&& ! CTA_Exam_Prep_Workbooks::attempt_answers_are_empty( $attempt->answers ?? null ) ) {
					if ( null === $card['best'] || (int) $attempt->score > (int) $card['best']->score ) {
						$card['best'] = $attempt;
					}
				}
			}

			$status = CTA_Exam_Prep_Workbooks::get_practice_bank_status( $card );
			$workbook_number = CTA_Exam_Prep_Workbooks::workbook_number_from_quiz( $quiz );
			$rows[] = array(
				'quiz_id'         => $quiz_id,
				'title'           => (string) $quiz->title,
				'label'           => CTA_Exam_Prep_Workbooks::get_assessment_category_label( 'workbook_bank', $quiz ),
				'workbook_number' => $workbook_number,
				'attempt_count'   => count(
					array_filter(
						$attempts,
						static function ( $attempt ) {
							return CTA_Exam_Prep_Workbooks::attempt_is_submitted( $attempt )
								&& ! CTA_Exam_Prep_Workbooks::attempt_answers_are_empty( $attempt->answers ?? null );
						}
					)
				),
				'best_score'      => $best_score,
				'passed'          => $passed,
				'status'          => $status,
				'status_label'    => CTA_Exam_Prep_Workbooks::get_practice_bank_status_label( $status ),
				'url'             => $dashboard->get_quiz_url( $course_id, $quiz_id ),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$wa = (int) ( $a['workbook_number'] ?? 999 );
				$wb = (int) ( $b['workbook_number'] ?? 999 );
				if ( $wa === $wb ) {
					return strnatcasecmp( (string) $a['title'], (string) $b['title'] );
				}
				return $wa <=> $wb;
			}
		);

		return $rows;
	}

	/**
	 * Client-side flashcard progress configuration.
	 *
	 * Progress is intentionally read in JavaScript because Flashcard Study Center
	 * currently stores self-assessment state in localStorage.
	 *
	 * @param object                $course    Course row.
	 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
	 * @param int                   $user_id   User ID.
	 * @return array<string,mixed>
	 */
	private static function get_flashcard_progress_config( $course, $dashboard, $user_id ) {
		$course_id = (int) $course->id;
		$deck      = class_exists( 'CTA_Exam_Prep_Flashcard_Center' )
			? CTA_Exam_Prep_Flashcard_Center::get_deck_for_course( $course )
			: array();

		return array(
			'total_count' => (int) ( $deck['count'] ?? 0 ),
			'has_content' => ! empty( $deck['has_content'] ),
			'storage_key' => 'cta_fsc_' . $course_id . '_' . (int) $user_id,
			'url'         => $dashboard->get_player_view_url( $course_id, 'flashcards' ),
		);
	}

	/**
	 * Student-visible course resources.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	private static function get_visible_resources( $course_id ) {
		$resources = (array) CTA_Database::get_downloadable_resources( $course_id );

		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}

		return $resources;
	}

	/**
	 * Build deduplicated readiness or remediation cards.
	 *
	 * @param array  $resources Resource rows.
	 * @param string $kind      readiness|remediation.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_resource_cards( array $resources, $kind ) {
		$cards = array();
		$seen  = array();

		foreach ( $resources as $resource ) {
			if ( ! self::resource_matches_kind( $resource, $kind ) ) {
				continue;
			}

			$resource_id = (int) ( $resource->id ?? 0 );
			$url         = class_exists( 'CTA_Course_Materials' )
				? CTA_Course_Materials::get_serve_url( $resource_id )
				: '';
			if ( $resource_id <= 0 || '' === $url ) {
				continue;
			}

			$title      = trim( (string) ( $resource->title ?? '' ) );
			$dedupe_key = self::normalize_resource_title( $title );
			if ( '' === $dedupe_key ) {
				$dedupe_key = md5( $url );
			}
			if ( isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}

			$seen[ $dedupe_key ] = true;
			$delivery            = self::get_resource_delivery( $resource );
			$cards[]             = array(
				'resource_id'   => $resource_id,
				'title'         => $title,
				'description'   => self::get_resource_description( $resource, $kind ),
				'url'           => $url,
				'mode'          => $delivery['mode'],
				'action_label'  => $delivery['action_label'],
				'format_label'  => $delivery['format_label'],
			);
		}

		usort(
			$cards,
			static function ( $a, $b ) {
				return strnatcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return $cards;
	}

	/**
	 * Match readiness/remediation resources and keep the sets distinct.
	 *
	 * @param object|null $resource Resource row.
	 * @param string      $kind     readiness|remediation.
	 * @return bool
	 */
	private static function resource_matches_kind( $resource, $kind ) {
		if ( ! $resource || ! empty( $resource->module_id ) || ! empty( $resource->is_practice_test ) ) {
			return false;
		}

		$haystack = strtolower(
			(string) ( $resource->title ?? '' ) . ' ' .
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' )
		);

		if ( 'remediation' === $kind ) {
			return (bool) preg_match( '/remediation|performance analysis|error repair|correction guide|weak area/i', $haystack );
		}

		if ( preg_match( '/remediation|performance analysis|error repair/i', $haystack ) ) {
			return false;
		}

		return (bool) preg_match( '/readiness|self.assessment|progress tracker|master study map|readiness checklist/i', $haystack );
	}

	/**
	 * Normalize titles so repeated DB rows (including R9A variants) display once.
	 *
	 * @param string $title Resource title.
	 * @return string
	 */
	private static function normalize_resource_title( $title ) {
		$title = strtolower( wp_strip_all_tags( (string) $title ) );
		$title = preg_replace( '/^\s*[a-z]\d+[a-z]?\s*[-–—:]\s*/i', '', $title );
		$title = preg_replace( '/\bv\d+(?:\.\d+)*\b/i', '', $title );
		$title = preg_replace( '/\b(?:revised|final|formatted)\b/i', '', $title );
		$title = preg_replace( '/[^a-z0-9]+/', ' ', $title );
		return trim( (string) $title );
	}

	/**
	 * Resource delivery behavior.
	 *
	 * @param object $resource Resource row.
	 * @return array{mode:string,action_label:string,format_label:string}
	 */
	private static function get_resource_delivery( $resource ) {
		$bits = (string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' ) . ' ' .
			(string) ( $resource->file_name ?? '' ) . ' ' .
			(string) ( $resource->title ?? '' );
		$ext  = preg_match( '/\.(pdf|docx?|xlsx?)\b/i', $bits, $match )
			? strtolower( (string) $match[1] )
			: '';

		if ( 'pdf' === $ext ) {
			return array(
				'mode'         => 'view',
				'action_label' => __( 'View PDF', 'cta-lms' ),
				'format_label' => __( 'PDF', 'cta-lms' ),
			);
		}

		return array(
			'mode'         => 'download',
			'action_label' => __( 'Download', 'cta-lms' ),
			'format_label' => '' !== $ext ? strtoupper( $ext ) : __( 'File', 'cta-lms' ),
		);
	}

	/**
	 * One-line resource description.
	 *
	 * @param object $resource Resource row.
	 * @param string $kind     readiness|remediation.
	 * @return string
	 */
	private static function get_resource_description( $resource, $kind ) {
		$haystack = strtolower( (string) ( $resource->title ?? '' ) . ' ' . (string) ( $resource->file_path ?? '' ) );

		if ( preg_match( '/self.assessment/i', $haystack ) ) {
			return __( 'Gauge your current exam preparedness and identify the next study priority.', 'cta-lms' );
		}
		if ( preg_match( '/progress tracker/i', $haystack ) ) {
			return __( 'Track workbook completion, assessment scores, and study milestones.', 'cta-lms' );
		}
		if ( preg_match( '/master study map|readiness checklist/i', $haystack ) ) {
			return __( 'Use the program-wide study map and final readiness checklist before test day.', 'cta-lms' );
		}
		if ( 'remediation' === $kind ) {
			return __( 'Review missed content, document weak areas, and build a focused remediation plan.', 'cta-lms' );
		}

		return __( 'Readiness tool for checking progress and preparing your next study step.', 'cta-lms' );
	}

	/**
	 * Build truthful next-step guidance from available aggregate attempt data.
	 *
	 * Domain-level results are not generated because the attempt schema stores
	 * answers and aggregate scores but no question-to-domain mapping.
	 *
	 * @param array                 $exam_data   Exam Center payload.
	 * @param array                 $remediation Remediation resource cards.
	 * @param CTA_Student_Dashboard $dashboard   Dashboard instance.
	 * @param int                   $course_id   Course ID.
	 * @return array<int,array<string,string>>
	 */
	private static function build_guidance( array $exam_data, array $remediation, $dashboard, $course_id ) {
		$exams     = (array) ( $exam_data['exams'] ?? array() );
		$attempted = array_filter(
			$exams,
			static function ( $exam ) {
				return ! empty( $exam['has_attempts'] );
			}
		);
		$not_passed = array_filter(
			$attempted,
			static function ( $exam ) {
				return empty( $exam['passed'] );
			}
		);

		if ( empty( $attempted ) ) {
			return array(
				array(
					'tone'  => 'info',
					'title' => __( 'Establish a baseline', 'cta-lms' ),
					'text'  => __( 'Complete a practice exam when you are ready. Your result will appear here and help focus your next review cycle.', 'cta-lms' ),
					'label' => __( 'Open Practice Exams', 'cta-lms' ),
					'url'   => $dashboard->get_player_view_url( $course_id, 'exams' ),
				),
			);
		}

		if ( ! empty( $not_passed ) ) {
			$first_resource = ! empty( $remediation[0] ) ? $remediation[0] : null;
			return array(
				array(
					'tone'  => 'warning',
					'title' => __( 'Focus your next review cycle', 'cta-lms' ),
					'text'  => __( 'At least one attempted assessment is below its passing threshold. Review related workbooks and flashcards, then use the remediation guide before retaking it.', 'cta-lms' ),
					'label' => $first_resource ? (string) $first_resource['action_label'] : __( 'Review Flashcards', 'cta-lms' ),
					'url'   => $first_resource ? (string) $first_resource['url'] : $dashboard->get_player_view_url( $course_id, 'flashcards' ),
				),
			);
		}

		return array(
			array(
				'tone'  => 'success',
				'title' => __( 'Keep your readiness current', 'cta-lms' ),
				'text'  => __( 'Your attempted assessments meet their passing thresholds. Continue reviewing flagged flashcards and complete any remaining program work.', 'cta-lms' ),
				'label' => __( 'Review Flashcards', 'cta-lms' ),
				'url'   => $dashboard->get_player_view_url( $course_id, 'flashcards' ),
			),
		);
	}
}

}
