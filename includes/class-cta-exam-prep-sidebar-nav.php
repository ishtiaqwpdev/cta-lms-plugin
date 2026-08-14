<?php
/**
 * Exam Prep Course Home — nested sidebar navigation (data builder).
 *
 * Builds a reusable course → sections → sub-items tree for hover / accordion nav.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Prep_Sidebar_Nav
 */
if ( ! class_exists( 'CTA_Exam_Prep_Sidebar_Nav' ) ) {

class CTA_Exam_Prep_Sidebar_Nav {

	/**
	 * Build full sidebar navigation tree for an exam-prep course.
	 *
	 * @param object                   $course        Course row.
	 * @param array                    $modules       Module rows.
	 * @param array                    $completed_ids Completed module IDs.
	 * @param CTA_Student_Dashboard|null $dashboard   Dashboard instance.
	 * @param array                    $context       Current page context (view, module_id, etc.).
	 * @return array<string,mixed>
	 */
	public static function build( $course, $modules, $completed_ids, $dashboard, array $context = array() ) {
		if ( ! $course || ! $dashboard ) {
			return array();
		}

		$course_id   = (int) $course->id;
		$player_base = $dashboard->get_player_page_url();
		$view        = sanitize_key( (string) ( $context['view'] ?? '' ) );
		$module_id   = absint( $context['module_id'] ?? 0 );

		$resources = CTA_Database::get_downloadable_resources( $course_id );
		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$resources = CTA_Course_Materials::filter_student_visible_resources( $resources );
		}

		$getting_started = class_exists( 'CTA_Exam_Prep_Getting_Started' )
			? CTA_Exam_Prep_Getting_Started::get_config_for_course( $course, $resources )
			: array();

		$workbook_items = class_exists( 'CTA_Exam_Prep_Workbooks' )
			? CTA_Exam_Prep_Workbooks::get_workbook_list_items( $course, $modules, $completed_ids, $player_base )
			: array();

		$quiz_cards = self::build_program_quiz_cards( $course_id, $dashboard );

		$display_title = function_exists( 'cta_lms_get_course_display_title' )
			? cta_lms_get_course_display_title( $course )
			: (string) $course->title;

		$active_section = self::resolve_active_section( $view, $module_id );
		$active_child   = self::resolve_active_child( $active_section, $module_id, $context );

		$sections = array();

		$sections[] = self::section(
			'home',
			__( 'Course Home', 'cta-lms' ),
			$dashboard->get_player_home_url( $course_id ),
			array(),
			'home' === $active_section,
			'',
			'home'
		);

		if ( ! empty( $workbook_items ) ) {
			$children = array();
			foreach ( $workbook_items as $item ) {
				$child_key = 'module-' . (int) $item['module_id'];
				$children[] = array(
					'key'         => $child_key,
					'label'       => (string) $item['label'],
					'title'       => (string) $item['title'],
					'url'         => (string) $item['url'],
					'is_active'   => 'workbooks' === $active_section && $active_child === $child_key,
					'is_complete' => ! empty( $item['is_complete'] ),
				);
			}

			$sections[] = self::section(
				'workbooks',
				__( 'Workbooks', 'cta-lms' ),
				$dashboard->get_player_workbooks_url( $course_id ),
				$children,
				'workbooks' === $active_section,
				$active_child,
				'workbooks'
			);
		}

		$show_flashcard_center = class_exists( 'CTA_Exam_Access' ) && CTA_Exam_Access::is_exam_prep( $course );

		if ( $show_flashcard_center ) {
			$sections[] = self::section(
				'flashcards',
				__( 'Flashcard Study Center', 'cta-lms' ),
				$dashboard->get_player_view_url( $course_id, 'flashcards' ),
				array(),
				'flashcards' === $active_section,
				'',
				'flashcards'
			);
		}

		if ( ! empty( $quiz_cards ) ) {
			$children = array();
			foreach ( $quiz_cards as $card ) {
				$quiz      = $card['quiz'];
				$quiz_id   = (int) $quiz->id;
				$child_key = 'quiz-' . $quiz_id;
				$children[] = array(
					'key'       => $child_key,
					'label'     => (string) $quiz->title,
					'title'     => (string) $quiz->title,
					'url'       => (string) $card['url'],
					'is_active' => 'exams' === $active_section && $active_child === $child_key,
					'passed'    => ! empty( $card['passed'] ),
				);
			}

			$sections[] = self::section(
				'exams',
				__( 'Practice Exams', 'cta-lms' ),
				$dashboard->get_player_view_url( $course_id, 'exams' ),
				$children,
				'exams' === $active_section,
				$active_child,
				'exams'
			);
		}

		$study_resources = self::build_study_resource_children( $resources, $getting_started, $context );
		if ( ! empty( $study_resources ) ) {
			$sections[] = self::section(
				'resources',
				__( 'Study Resources', 'cta-lms' ),
				$dashboard->get_player_view_url( $course_id, 'resources' ),
				$study_resources,
				'resources' === $active_section,
				$active_child,
				'resources'
			);
		}

		$download_children = self::build_download_children( $resources, $modules );
		if ( ! empty( $download_children ) ) {
			$sections[] = self::section(
				'downloads',
				__( 'Downloads', 'cta-lms' ),
				$dashboard->get_player_view_url( $course_id, 'downloads' ),
				$download_children,
				'downloads' === $active_section,
				$active_child,
				'downloads'
			);
		}

		$audio_children = self::build_audio_children( $resources );
		if ( ! empty( $audio_children ) ) {
			$sections[] = self::section(
				'audio',
				__( 'Audio Review', 'cta-lms' ),
				$dashboard->get_player_view_url( $course_id, 'audio' ),
				$audio_children,
				'audio' === $active_section,
				$active_child,
				'audio'
			);
		}

		$sections[] = self::section(
			'progress',
			__( 'Progress / Readiness', 'cta-lms' ),
			$dashboard->get_player_view_url( $course_id, 'progress' ),
			self::build_progress_children( $getting_started, $resources, $context ),
			'progress' === $active_section,
			$active_child,
			'progress'
		);

		/**
		 * Filter exam-prep sidebar section tree before render.
		 *
		 * @param array  $sections Sections with keys, labels, urls, children.
		 * @param object $course   Course row.
		 * @param array  $context  Active page context.
		 */
		$sections = apply_filters( 'cta_exam_prep_sidebar_nav_sections', $sections, $course, $context );

		$enrolled_courses = self::get_enrolled_exam_prep_courses( $dashboard, $course_id );

		return array(
			'course'           => array(
				'id'    => $course_id,
				'title' => $display_title,
				'url'   => $dashboard->get_player_home_url( $course_id ),
			),
			'my_courses_url'   => $dashboard->get_dashboard_url(),
			'enrolled_courses' => $enrolled_courses,
			'sections'         => $sections,
			'active_section'   => $active_section,
			'active_child'     => $active_child,
		);
	}

	/**
	 * Normalize a section row.
	 *
	 * @param string $key          Section key.
	 * @param string $label        Display label.
	 * @param string $url          Section URL.
	 * @param array  $children     Child items.
	 * @param bool   $is_active    Whether section is active.
	 * @param string $active_child Active child key within section.
	 * @param string $icon         Optional icon slug.
	 * @return array<string,mixed>
	 */
	private static function section( $key, $label, $url, array $children, $is_active, $active_child, $icon = '' ) {
		return array(
			'key'          => (string) $key,
			'label'        => (string) $label,
			'url'          => (string) $url,
			'icon'         => (string) $icon,
			'children'     => $children,
			'has_children' => ! empty( $children ),
			'is_active'    => (bool) $is_active,
			'active_child' => (string) $active_child,
		);
	}

	/**
	 * Resolve active top-level section from URL context.
	 *
	 * @param string $view      View query arg.
	 * @param int    $module_id Module ID query arg.
	 * @return string
	 */
	public static function resolve_active_section( $view, $module_id ) {
		if ( $module_id > 0 ) {
			return 'workbooks';
		}

		$known = array( 'home', 'workbooks', 'flashcards', 'exams', 'resources', 'downloads', 'audio', 'progress' );

		if ( in_array( $view, $known, true ) ) {
			return $view;
		}

		return 'home';
	}

	/**
	 * Resolve active child key.
	 *
	 * @param string $section   Active section.
	 * @param int    $module_id Module ID.
	 * @param array  $context   Page context.
	 * @return string
	 */
	private static function resolve_active_child( $section, $module_id, array $context ) {
		if ( 'workbooks' === $section && $module_id > 0 ) {
			return 'module-' . $module_id;
		}

		$resource_id = absint( $context['resource_id'] ?? 0 );
		if ( $resource_id > 0 && in_array( $section, array( 'resources', 'downloads', 'audio' ), true ) ) {
			return 'resource-' . $resource_id;
		}

		$quiz_id = absint( $context['quiz_id'] ?? 0 );
		if ( 'exams' === $section && $quiz_id > 0 ) {
			return 'quiz-' . $quiz_id;
		}

		return '';
	}

	/**
	 * Program-level practice exam quiz cards.
	 *
	 * @param int                   $course_id Course ID.
	 * @param CTA_Student_Dashboard $dashboard Dashboard instance.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_program_quiz_cards( $course_id, $dashboard ) {
		$cards = array();
		$user_id = get_current_user_id();

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, true ) as $qrow ) {
			if ( empty( CTA_Database::get_quiz_questions( (int) $qrow->id ) ) ) {
				continue;
			}

			if ( class_exists( 'CTA_Exam_Prep_Workbooks' ) && ! CTA_Exam_Prep_Workbooks::is_program_level_quiz( $qrow ) ) {
				continue;
			}

			$attempts = CTA_Database::get_user_quiz_attempts( $user_id, (int) $qrow->id );
			$best     = null;
			foreach ( $attempts as $att ) {
				if ( null === $best || (int) $att->score > (int) $best->score ) {
					$best = $att;
				}
			}

			$cards[] = array(
				'quiz'   => $qrow,
				'url'    => $dashboard->get_quiz_url( $course_id, (int) $qrow->id ),
				'passed' => $best && (int) $best->passed,
			);
		}

		return $cards;
	}

	/**
	 * Study resource child links (course-level guides, schedules, toolkits).
	 *
	 * @param array $resources       Resource rows.
	 * @param array $getting_started Getting started config.
	 * @param array $context         Page context.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_study_resource_children( array $resources, array $getting_started, array $context ) {
		$children = array();
		$seen     = array();

		$schedules = isset( $getting_started['study_schedules'] ) ? (array) $getting_started['study_schedules'] : array();
		if ( ! empty( $schedules['combined_url'] ) ) {
			$key = 'schedule-combined';
			$children[] = array(
				'key'       => $key,
				'label'     => ! empty( $schedules['combined_title'] )
					? (string) $schedules['combined_title']
					: __( 'Study Schedules', 'cta-lms' ),
				'title'     => ! empty( $schedules['combined_title'] )
					? (string) $schedules['combined_title']
					: __( 'Study Schedules', 'cta-lms' ),
				'url'       => (string) $schedules['combined_url'],
				'is_active' => self::child_is_active( 'resources', $key, $context ),
				'external'  => true,
			);
			$seen[ $key ] = true;
		}

		foreach ( $resources as $resource ) {
			if ( self::is_audio_resource( $resource ) || self::is_workbook_download( $resource ) ) {
				continue;
			}

			$module_id = isset( $resource->module_id ) ? absint( $resource->module_id ) : 0;
			if ( $module_id > 0 ) {
				continue;
			}

			if ( ! empty( $resource->is_practice_test ) ) {
				continue;
			}

			$resource_id = (int) $resource->id;
			$key         = 'resource-' . $resource_id;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$url = class_exists( 'CTA_Course_Materials' )
				? CTA_Course_Materials::get_serve_url( $resource_id )
				: '';

			if ( ! $url ) {
				continue;
			}

			$children[] = array(
				'key'       => $key,
				'label'     => (string) $resource->title,
				'title'     => (string) $resource->title,
				'url'       => $url,
				'is_active' => self::child_is_active( 'resources', $key, $context ),
				'external'  => true,
			);
			$seen[ $key ] = true;
		}

		return $children;
	}

	/**
	 * Downloadable workbook / practice bank child links.
	 *
	 * @param array $resources Resource rows.
	 * @param array $modules   Module rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_download_children( array $resources, array $modules ) {
		$children = array();
		$seen     = array();

		foreach ( (array) $modules as $module ) {
			if ( class_exists( 'CTA_Exam_Prep_Lessons' ) ) {
				$wb_resource = CTA_Exam_Prep_Lessons::find_workbook_resource( $resources, $module );
				if ( $wb_resource ) {
					$key = 'resource-' . (int) $wb_resource->id;
					if ( ! isset( $seen[ $key ] ) ) {
						$children[] = self::resource_child( $wb_resource, 'downloads', $key );
						$seen[ $key ] = true;
					}
				}
			}

			if ( class_exists( 'CTA_Exam_Prep_Workbooks' ) ) {
				$bank = CTA_Exam_Prep_Workbooks::find_practice_bank_resource( $resources, $module );
				if ( $bank ) {
					$key = 'resource-' . (int) $bank->id;
					if ( ! isset( $seen[ $key ] ) ) {
						$children[] = self::resource_child( $bank, 'downloads', $key );
						$seen[ $key ] = true;
					}
				}
			}
		}

		return $children;
	}

	/**
	 * Audio review child links.
	 *
	 * @param array $resources Resource rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_audio_children( array $resources ) {
		$children = array();

		foreach ( $resources as $resource ) {
			if ( ! self::is_audio_resource( $resource ) ) {
				continue;
			}

			$key = 'resource-' . (int) $resource->id;
			$children[] = self::resource_child( $resource, 'audio', $key );
		}

		usort(
			$children,
			static function ( $a, $b ) {
				return strnatcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		return $children;
	}

	/**
	 * Progress / readiness nested items.
	 *
	 * @param array $getting_started Getting started config.
	 * @param array $resources       Resource rows.
	 * @param array $context         Page context.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_progress_children( array $getting_started, array $resources, array $context ) {
		$children = array();
		$readiness = isset( $getting_started['readiness'] ) ? (array) $getting_started['readiness'] : array();

		if ( ! empty( $readiness['url'] ) ) {
			$key = 'readiness-tool';
			$children[] = array(
				'key'       => $key,
				'label'     => ! empty( $readiness['title'] )
					? (string) $readiness['title']
					: __( 'Readiness Self-Assessment', 'cta-lms' ),
				'title'     => ! empty( $readiness['title'] )
					? (string) $readiness['title']
					: __( 'Readiness Self-Assessment', 'cta-lms' ),
				'url'       => (string) $readiness['url'],
				'is_active' => self::child_is_active( 'progress', $key, $context ),
				'external'  => true,
			);
		}

		foreach ( $resources as $resource ) {
			$title = strtolower( (string) ( $resource->title ?? '' ) );
			if ( false === strpos( $title, 'readiness' ) && false === strpos( $title, 'progress tracker' ) ) {
				continue;
			}

			$key = 'resource-' . (int) $resource->id;
			$children[] = self::resource_child( $resource, 'progress', $key, $context );
		}

		return $children;
	}

	/**
	 * Build a resource child nav item.
	 *
	 * @param object      $resource Resource row.
	 * @param string      $section  Section key for active check.
	 * @param string      $key      Child key.
	 * @param array|null  $context  Optional context.
	 * @return array<string,mixed>
	 */
	private static function resource_child( $resource, $section, $key, $context = null ) {
		$url = class_exists( 'CTA_Course_Materials' )
			? CTA_Course_Materials::get_serve_url( (int) $resource->id )
			: '';

		return array(
			'key'       => $key,
			'label'     => (string) $resource->title,
			'title'     => (string) $resource->title,
			'url'       => $url,
			'is_active' => is_array( $context ) ? self::child_is_active( $section, $key, $context ) : false,
			'external'  => true,
		);
	}

	/**
	 * Whether a child item is active.
	 *
	 * @param string $section Section key.
	 * @param string $key     Child key.
	 * @param array  $context Page context.
	 * @return bool
	 */
	private static function child_is_active( $section, $key, array $context ) {
		$active_section = self::resolve_active_section(
			sanitize_key( (string) ( $context['view'] ?? '' ) ),
			absint( $context['module_id'] ?? 0 )
		);

		if ( $active_section !== $section ) {
			return false;
		}

		$active_child = self::resolve_active_child( $active_section, absint( $context['module_id'] ?? 0 ), $context );

		return $active_child === $key;
	}

	/**
	 * Whether resource is an audio track.
	 *
	 * @param object $resource Resource row.
	 * @return bool
	 */
	private static function is_audio_resource( $resource ) {
		$bits = strtolower(
			(string) ( $resource->file_path ?? '' ) . ' ' .
			(string) ( $resource->file_url ?? '' ) . ' ' .
			(string) ( $resource->file_name ?? '' ) . ' ' .
			(string) ( $resource->title ?? '' )
		);

		return (bool) preg_match( '/\.mp3\b|\/audio\/|audio.review|audio review|audio track/i', $bits );
	}

	/**
	 * Whether resource is a printable workbook file.
	 *
	 * @param object $resource Resource row.
	 * @return bool
	 */
	private static function is_workbook_download( $resource ) {
		if ( ! empty( $resource->is_practice_test ) ) {
			return false;
		}

		$title = (string) ( $resource->title ?? '' );
		return (bool) preg_match( '/workbook\s+\d+|student workbook|printable workbook/i', $title );
	}

	/**
	 * Enrolled exam-prep courses for My Courses flyout (excluding current).
	 *
	 * @param CTA_Student_Dashboard $dashboard        Dashboard instance.
	 * @param int                   $current_course_id Current course ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_enrolled_exam_prep_courses( $dashboard, $current_course_id ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$items = array();
		foreach ( CTA_Database::get_user_enrollments( $user_id ) as $enrollment ) {
			$course = CTA_Database::get_course( (int) $enrollment->course_id );
			if ( ! $course || ! class_exists( 'CTA_Exam_Access' ) || ! CTA_Exam_Access::is_exam_prep( $course ) ) {
				continue;
			}

			if ( ! CTA_Exam_Access::has_active_access( $user_id, (int) $course->id ) ) {
				continue;
			}

			$course_id = (int) $course->id;
			$items[]   = array(
				'id'         => $course_id,
				'title'      => function_exists( 'cta_lms_get_course_display_title' )
					? cta_lms_get_course_display_title( $course )
					: (string) $course->title,
				'url'        => $dashboard->get_player_home_url( $course_id ),
				'is_current' => $course_id === (int) $current_course_id,
			);
		}

		return $items;
	}
}

}
