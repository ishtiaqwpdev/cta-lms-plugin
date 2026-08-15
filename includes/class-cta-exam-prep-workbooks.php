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
		return array_merge(
			self::full_simulation_quiz_types(),
			self::cumulative_quiz_types()
		);
	}

	/**
	 * Full-length program simulations (Exam Center — primary section).
	 *
	 * @return string[]
	 */
	public static function full_simulation_quiz_types() {
		return array(
			'form_a',
			'form_b',
			'practice_a',
			'practice_b',
			'comprehensive_final',
		);
	}

	/**
	 * Cumulative / multi-workbook practice banks (Exam Center — secondary section).
	 *
	 * @return string[]
	 */
	public static function cumulative_quiz_types() {
		return array(
			'checkpoint_1',
			'checkpoint_2',
			'checkpoint_3',
		);
	}

	/**
	 * Assessment category slug for a quiz row.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return string workbook_bank|cumulative_bank|full_simulation|other
	 */
	public static function get_assessment_category( $quiz ) {
		if ( ! $quiz ) {
			return 'other';
		}

		if ( self::is_workbook_quiz( $quiz ) ) {
			return 'workbook_bank';
		}

		if ( self::is_cumulative_quiz( $quiz ) ) {
			return 'cumulative_bank';
		}

		if ( self::is_full_simulation_quiz( $quiz ) ) {
			return 'full_simulation';
		}

		return 'other';
	}

	/**
	 * Whether a quiz is a workbook-scoped practice bank (not program-wide).
	 *
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_workbook_quiz( $quiz ) {
		if ( ! $quiz || self::is_cumulative_quiz( $quiz ) || self::is_full_simulation_quiz( $quiz ) ) {
			return false;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( preg_match( '/^wb\d+_bank$/', $type ) ) {
			return true;
		}

		return self::workbook_number_from_quiz( $quiz ) > 0;
	}

	/**
	 * Whether a quiz is a cumulative checkpoint / topic bank.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_cumulative_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( in_array( $type, self::cumulative_quiz_types(), true ) ) {
			return true;
		}

		$title = strtolower( (string) ( $quiz->title ?? '' ) );
		return false !== strpos( $title, 'checkpoint' );
	}

	/**
	 * Whether a quiz is a full-length program simulation.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_full_simulation_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( in_array( $type, self::full_simulation_quiz_types(), true ) ) {
			return true;
		}

		$title = strtolower( (string) ( $quiz->title ?? '' ) );

		return false !== strpos( $title, 'form a' )
			|| false !== strpos( $title, 'form b' )
			|| false !== strpos( $title, 'comprehensive simulation' )
			|| false !== strpos( $title, 'comprehensive final' )
			|| false !== strpos( $title, 'practice exam a' )
			|| false !== strpos( $title, 'practice exam b' );
	}

	/**
	 * Short category label for UI tags (workbook toolbar, exam cards, etc.).
	 *
	 * @param string      $category Category slug from get_assessment_category().
	 * @param object|null $quiz     Optional quiz for workbook number context.
	 * @return string
	 */
	public static function get_assessment_category_label( $category, $quiz = null ) {
		switch ( sanitize_key( (string) $category ) ) {
			case 'workbook_bank':
				$wb = $quiz ? self::workbook_number_from_quiz( $quiz ) : 0;
				if ( $wb > 0 ) {
					return sprintf(
						/* translators: %d: workbook number */
						__( 'Workbook %d Practice Bank', 'cta-lms' ),
						$wb
					);
				}
				return __( 'Workbook Practice Bank', 'cta-lms' );
			case 'cumulative_bank':
				return __( 'Cumulative Practice Bank', 'cta-lms' );
			case 'full_simulation':
				return __( 'Full Simulation', 'cta-lms' );
			default:
				return __( 'Practice Assessment', 'cta-lms' );
		}
	}

	/**
	 * Primary toolbar button label for a workbook practice bank.
	 *
	 * @param object|null $module Module row.
	 * @param object|null $quiz   Optional linked quiz row.
	 * @return string
	 */
	public static function get_workbook_practice_bank_button_label( $module = null, $quiz = null ) {
		$wb_num = 0;
		if ( $module && class_exists( 'CTA_Exam_Prep_Lessons' ) ) {
			$wb_num = CTA_Exam_Prep_Lessons::workbook_number_from_module( $module );
		}
		if ( $wb_num <= 0 && $quiz ) {
			$wb_num = self::workbook_number_from_quiz( $quiz );
		}

		if ( $wb_num > 0 ) {
			return sprintf(
				/* translators: %d: workbook number */
				__( 'Workbook %d Practice Bank', 'cta-lms' ),
				$wb_num
			);
		}

		return __( 'Practice Bank', 'cta-lms' );
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

		if ( self::is_workbook_quiz( $quiz ) ) {
			return false;
		}

		return self::is_cumulative_quiz( $quiz ) || self::is_full_simulation_quiz( $quiz );
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

	/**
	 * Resolve the primary Practice Bank action for a workbook toolbar button.
	 *
	 * Prefers in-app quiz URL, then in-page knowledge/practice tab, then DOCX download.
	 *
	 * @param array       $workbook_quiz_cards      Workbook-scoped quiz cards.
	 * @param array       $workbook_tabs            Built in-page section tabs.
	 * @param string      $workbook_page_url        Current workbook player URL.
	 * @param string      $bank_download_url        Optional DOCX practice bank download URL.
	 * @param object|null $practice_bank_resource   Practice bank resource row.
	 * @param object|null $module                   Workbook module row (for consistent labels).
	 * @return array<string,mixed>|null
	 */
	public static function resolve_practice_bank_action( $workbook_quiz_cards, $workbook_tabs, $workbook_page_url, $bank_download_url = '', $practice_bank_resource = null, $module = null ) {
		$fallback_label = self::get_workbook_practice_bank_button_label( $module );
		if ( $practice_bank_resource && ! empty( $practice_bank_resource->title ) && ! $module ) {
			$fallback_label = (string) $practice_bank_resource->title;
		}
		$docx_url = (string) $bank_download_url;

		foreach ( (array) $workbook_quiz_cards as $card ) {
			if ( ! empty( $card['locked'] ) ) {
				return array(
					'mode'            => 'locked',
					'url'             => '',
					'label'           => __( 'Complete Workbooks to Unlock', 'cta-lms' ),
					'category'        => 'workbook_bank',
					'category_label'  => self::get_assessment_category_label( 'workbook_bank', $card['quiz'] ?? null ),
					'docx_url'        => '',
					'lock_message'    => (string) ( $card['lock_msg'] ?? '' ),
				);
			}

			$url = (string) ( $card['url'] ?? '' );
			if ( '' === $url || '#' === $url ) {
				continue;
			}

			$quiz  = $card['quiz'] ?? null;
			$label = self::get_workbook_practice_bank_button_label( $module, $quiz );

			return array(
				'mode'            => 'quiz',
				'url'             => $url,
				'label'           => $label,
				'category'        => 'workbook_bank',
				'category_label'  => self::get_assessment_category_label( 'workbook_bank', $quiz ),
				'docx_url'        => $docx_url,
			);
		}

		$tab_priority = array( 'practice', 'knowledge' );
		foreach ( $tab_priority as $tab_key ) {
			foreach ( (array) $workbook_tabs as $tab ) {
				if ( (string) ( $tab['key'] ?? '' ) !== $tab_key ) {
					continue;
				}

				$label = (string) ( $tab['label'] ?? $fallback_label );
				if ( 'practice' === $tab_key ) {
					$label = self::get_workbook_practice_bank_button_label( $module );
				}
				if ( 'practice' === $tab_key && ! empty( $tab['quiz_cards'] ) ) {
					foreach ( (array) $tab['quiz_cards'] as $card ) {
						$url = (string) ( $card['url'] ?? '' );
						if ( '' !== $url && '#' !== $url ) {
							$quiz = $card['quiz'] ?? null;
							return array(
								'mode'           => 'quiz',
								'url'            => $url,
								'label'          => self::get_workbook_practice_bank_button_label( $module, $quiz ),
								'category'       => 'workbook_bank',
								'category_label' => self::get_assessment_category_label( 'workbook_bank', $quiz ),
								'docx_url'       => $docx_url,
							);
						}
					}
				}

				return array(
					'mode'           => 'tab',
					'url'            => add_query_arg( 'wb_section', $tab_key, (string) $workbook_page_url ),
					'label'          => $label,
					'category'       => 'workbook_bank',
					'category_label' => self::get_assessment_category_label( 'workbook_bank' ),
					'docx_url'       => $docx_url,
					'tab_key'        => $tab_key,
				);
			}
		}

		if ( '' !== $docx_url ) {
			return array(
				'mode'           => 'download',
				'url'            => $docx_url,
				'label'          => $fallback_label,
				'category'       => 'workbook_bank',
				'category_label' => self::get_assessment_category_label( 'workbook_bank' ),
				'docx_url'       => '',
			);
		}

		return null;
	}
}

}
