<?php
/**
 * Exam Prep workbook list + per-workbook resource resolution.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Prep_Workbooks
 */
if ( ! class_exists( 'CTA_Exam_Prep_Workbooks' ) ) {

class CTA_Exam_Prep_Workbooks {

	/**
	 * Quiz types that belong in the Exam Center, not on workbook pages.
	 *
	 * @return string[]
	 */
	public static function program_level_quiz_types() {
		return array(
			'form_a',
			'form_b',
			'practice_a',
			'practice_b',
			'comprehensive_final',
			'checkpoint_1',
			'checkpoint_2',
			'checkpoint_3',
		);
	}

	/**
	 * Whether a quiz is program-wide (Exam Center) vs workbook-scoped.
	 *
	 * @param object $quiz Quiz row.
	 * @return bool
	 */
	public static function is_program_level_quiz( $quiz ) {
		if ( ! $quiz ) {
			return true;
		}

		$type = isset( $quiz->quiz_type ) ? sanitize_key( (string) $quiz->quiz_type ) : '';

		if ( in_array( $type, self::program_level_quiz_types(), true ) ) {
			return true;
		}

		$title = strtolower( (string) ( $quiz->title ?? '' ) );

		if ( false !== strpos( $title, 'form a' )
			|| false !== strpos( $title, 'form b' )
			|| false !== strpos( $title, 'comprehensive simulation' )
			|| false !== strpos( $title, 'comprehensive final' )
			|| false !== strpos( $title, 'practice exam a' )
			|| false !== strpos( $title, 'practice exam b' )
			|| false !== strpos( $title, 'checkpoint' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Workbook number matched from quiz_type or title.
	 *
	 * @param object $quiz Quiz row.
	 * @return int
	 */
	public static function workbook_number_from_quiz( $quiz ) {
		if ( ! $quiz ) {
			return 0;
		}

		$type = (string) ( $quiz->quiz_type ?? '' );
		if ( preg_match( '/^wb(\d+)_/i', $type, $m ) ) {
			return absint( $m[1] );
		}

		$title = (string) ( $quiz->title ?? '' );
		if ( preg_match( '/Workbook\s+(\d+)/i', $title, $m ) ) {
			return absint( $m[1] );
		}

		return 0;
	}

	/**
	 * Whether module is Start Here / orientation (not numbered workbook).
	 *
	 * @param object $module Module row.
	 * @return bool
	 */
	public static function is_start_here_module( $module ) {
		$title = is_object( $module ) ? (string) ( $module->title ?? '' ) : '';
		return (bool) preg_match( '/^\s*Start\s+Here\s*:/i', $title );
	}

	/**
	 * Build workbook list rows for overview grid.
	 *
	 * @param object $course        Course row.
	 * @param array  $modules       Module rows.
	 * @param array  $completed_ids Completed module IDs.
	 * @param string $player_base   Player base URL.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_workbook_list_items( $course, $modules, $completed_ids, $player_base ) {
		$items = array();

		foreach ( (array) $modules as $index => $module ) {
			$module_id   = (int) $module->id;
			$is_complete = in_array( $module_id, (array) $completed_ids, true );
			$url         = add_query_arg(
				array(
					'course_id' => (int) $course->id,
					'module_id' => $module_id,
				),
				$player_base
			);

			$label = self::is_start_here_module( $module )
				? __( 'Start Here', 'cta-lms' )
				: sprintf(
					/* translators: %d: workbook number */
					__( 'Workbook %d', 'cta-lms' ),
					class_exists( 'CTA_Exam_Prep_Lessons' )
						? CTA_Exam_Prep_Lessons::workbook_number_from_module( $module )
						: ( (int) $index + 1 )
				);

			$items[] = array(
				'module'       => $module,
				'module_id'    => $module_id,
				'index'        => (int) $index,
				'label'        => $label,
				'title'        => (string) $module->title,
				'description'  => self::module_description( $module ),
				'is_complete'  => $is_complete,
				'url'          => $url,
				'is_start_here'=> self::is_start_here_module( $module ),
				'workbook_num' => class_exists( 'CTA_Exam_Prep_Lessons' )
					? CTA_Exam_Prep_Lessons::workbook_number_from_module( $module )
					: 0,
			);
		}

		return $items;
	}

	/**
	 * Short description for list cards.
	 *
	 * @param object $module Module row.
	 * @return string
	 */
	public static function module_description( $module ) {
		if ( ! empty( $module->description ) ) {
			return wp_trim_words( wp_strip_all_tags( (string) $module->description ), 22, '…' );
		}

		if ( self::is_start_here_module( $module ) ) {
			return __( 'Program orientation and license-specific guidance before Workbook 1.', 'cta-lms' );
		}

		return __( 'Read online, download the printable workbook, and complete the paired practice bank.', 'cta-lms' );
	}

	/**
	 * Online quizzes tied to a single workbook module.
	 *
	 * @param object $course     Course row.
	 * @param object $module     Module row.
	 * @param array  $quiz_cards Pre-built quiz cards from dashboard (optional).
	 * @param int    $user_id    User ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_workbook_quiz_cards( $course, $module, $quiz_cards, $user_id ) {
		$matched   = array();
		$wb_num    = class_exists( 'CTA_Exam_Prep_Lessons' )
			? CTA_Exam_Prep_Lessons::workbook_number_from_module( $module )
			: 0;
		$is_start  = self::is_start_here_module( $module );
		$module_id = (int) $module->id;

		foreach ( (array) $quiz_cards as $card ) {
			$quiz = $card['quiz'] ?? null;
			if ( ! $quiz || self::is_program_level_quiz( $quiz ) ) {
				continue;
			}

			$quiz_wb = self::workbook_number_from_quiz( $quiz );
			$type    = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );

			if ( $is_start ) {
				if ( in_array( $type, array( 'license', 'start_here', 'orientation' ), true )
					|| false !== stripos( (string) $quiz->title, 'license' )
					|| false !== stripos( (string) $quiz->title, 'start here' ) ) {
					$matched[] = $card;
				}
				continue;
			}

			if ( $wb_num > 0 && $quiz_wb === $wb_num ) {
				$matched[] = $card;
			}
		}

		// Law & Ethics chapter tests may not use wb_num in type — match title prefix.
		if ( ! $is_start && $wb_num > 0 && empty( $matched ) ) {
			foreach ( (array) $quiz_cards as $card ) {
				$quiz = $card['quiz'] ?? null;
				if ( ! $quiz || self::is_program_level_quiz( $quiz ) ) {
					continue;
				}
				if ( preg_match( '/Workbook\s+' . $wb_num . '\b/i', (string) $quiz->title ) ) {
					$matched[] = $card;
				}
			}
		}

		return $matched;
	}

	/**
	 * Downloadable practice bank resource for a workbook module.
	 *
	 * @param array  $resources Resource rows.
	 * @param object $module    Module row.
	 * @return object|null
	 */
	public static function find_practice_bank_resource( array $resources, $module ) {
		if ( empty( $resources ) || ! $module ) {
			return null;
		}

		$module_id = (int) $module->id;
		$wb_num    = class_exists( 'CTA_Exam_Prep_Lessons' )
			? CTA_Exam_Prep_Lessons::workbook_number_from_module( $module )
			: 0;

		foreach ( $resources as $resource ) {
			if ( empty( $resource->is_practice_test ) ) {
				continue;
			}

			$title = (string) ( $resource->title ?? '' );
			$file  = (string) ( $resource->file_name ?? '' );

			// Skip simulations / forms — Exam Center content.
			if ( false !== stripos( $title, 'Form A' )
				|| false !== stripos( $title, 'Form B' )
				|| false !== stripos( $title, 'Comprehensive' )
				|| false !== stripos( $title, 'Checkpoint' )
				|| false !== stripos( $title, 'Practice Exam' ) ) {
				continue;
			}

			if ( $module_id && ! empty( $resource->module_id ) && absint( $resource->module_id ) === $module_id ) {
				return $resource;
			}

			if ( $wb_num > 0 && preg_match( '/Workbook\s+' . $wb_num . '\b|\bWB\s*' . $wb_num . '\b/i', $title . ' ' . $file ) ) {
				return $resource;
			}
		}

		return null;
	}

	/**
	 * Player URL for workbooks list view.
	 *
	 * @param int    $course_id   Course ID.
	 * @param string $player_base Player page URL.
	 * @return string
	 */
	public static function get_workbooks_list_url( $course_id, $player_base ) {
		return add_query_arg(
			array(
				'course_id' => absint( $course_id ),
				'view'      => 'workbooks',
			),
			$player_base
		);
	}
}

}
