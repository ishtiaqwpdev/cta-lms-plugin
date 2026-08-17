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
	 * Whether module is the standalone license-specific instructional module.
	 *
	 * @param object $module Module row.
	 * @return bool
	 */
	public static function is_license_module( $module ) {
		$title = is_object( $module ) ? (string) ( $module->title ?? '' ) : '';
		if ( '' === trim( $title ) ) {
			return false;
		}

		if ( preg_match( '/^\s*Start\s+Here\s*:/i', $title ) ) {
			return false;
		}

		return (bool) preg_match(
			'/Practice\s+Act|License[-\s]Specific\s+Module|AMFT\s+Professional\s+Identity|Professional\s+Identity\s*&\s*California\s+Examination\s+Distinctions/i',
			$title
		);
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

			if ( self::is_start_here_module( $module ) ) {
				$label = __( 'Start Here', 'cta-lms' );
			} elseif ( self::is_license_module( $module ) ) {
				$label = __( 'License Module', 'cta-lms' );
			} else {
				$label = sprintf(
					/* translators: %d: workbook number */
					__( 'Workbook %d', 'cta-lms' ),
					class_exists( 'CTA_Exam_Prep_Lessons' )
						? CTA_Exam_Prep_Lessons::workbook_number_from_module( $module )
						: max( 1, (int) $index - 1 )
				);
			}

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
				'is_license'   => self::is_license_module( $module ),
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
			return __( 'Program orientation and study-path guidance before the license-specific module.', 'cta-lms' );
		}

		if ( self::is_license_module( $module ) ) {
			return __( 'LMFT/AMFT license-specific foundations and the separate 25-question assessment.', 'cta-lms' );
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
		$is_license = self::is_license_module( $module );
		$module_id = (int) $module->id;

		foreach ( (array) $quiz_cards as $card ) {
			$quiz = $card['quiz'] ?? null;
			if ( ! $quiz || self::is_program_level_quiz( $quiz ) ) {
				continue;
			}

			$quiz_wb = self::workbook_number_from_quiz( $quiz );
			$type    = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );

			if ( $is_license ) {
				if ( 'license_25' === $type
					|| false !== stripos( (string) $quiz->title, 'Practice Act Module' )
					|| false !== stripos( (string) $quiz->title, 'License-Specific Module' ) ) {
					$matched[] = $card;
				}
				continue;
			}

			if ( $is_start ) {
				if ( in_array( $type, array( 'start_here', 'orientation' ), true )
					|| ( false !== stripos( (string) $quiz->title, 'start here' )
						&& false === stripos( (string) $quiz->title, 'license' ) ) ) {
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

	/**
	 * Practice Bank attempt status for one workbook quiz card.
	 *
	 * Independent of workbook module completion (Mark Workbook Complete).
	 *
	 * @param array|null $card Quiz card from dashboard (quiz/attempts/active/best/passed).
	 * @return string not_available|not_started|in_progress|completed
	 */
	public static function get_practice_bank_status( $card ) {
		if ( empty( $card ) || empty( $card['quiz'] ) ) {
			return 'not_available';
		}

		$active = $card['active'] ?? null;
		if ( $active && self::attempt_is_in_progress( $active ) ) {
			return 'in_progress';
		}

		$attempts = isset( $card['attempts'] ) ? (array) $card['attempts'] : array();
		foreach ( $attempts as $attempt ) {
			if ( self::attempt_is_submitted( $attempt ) && ! self::attempt_answers_are_empty( $attempt->answers ?? null ) ) {
				return 'completed';
			}
		}

		if ( ! empty( $card['best'] )
			&& self::attempt_is_submitted( $card['best'] )
			&& ! self::attempt_answers_are_empty( $card['best']->answers ?? null ) ) {
			return 'completed';
		}

		return 'not_started';
	}

	/**
	 * Aggregate Practice Bank status from workbook-scoped quiz cards.
	 *
	 * @param array $cards Workbook quiz cards.
	 * @return string not_available|not_started|in_progress|completed
	 */
	public static function get_practice_bank_status_from_cards( array $cards ) {
		if ( empty( $cards ) ) {
			return 'not_available';
		}

		$has_in_progress = false;
		$has_completed   = false;
		$has_quiz        = false;

		foreach ( $cards as $card ) {
			$status = self::get_practice_bank_status( $card );
			if ( 'not_available' === $status ) {
				continue;
			}
			$has_quiz = true;
			if ( 'completed' === $status ) {
				$has_completed = true;
			} elseif ( 'in_progress' === $status ) {
				$has_in_progress = true;
			}
		}

		if ( ! $has_quiz ) {
			return 'not_available';
		}
		if ( $has_completed ) {
			return 'completed';
		}
		if ( $has_in_progress ) {
			return 'in_progress';
		}

		return 'not_started';
	}

	/**
	 * Learner-facing Practice Bank status label.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function get_practice_bank_status_label( $status ) {
		switch ( sanitize_key( (string) $status ) ) {
			case 'completed':
				return __( 'Completed', 'cta-lms' );
			case 'in_progress':
				return __( 'In Progress', 'cta-lms' );
			case 'not_available':
			case 'not_started':
			default:
				return __( 'Not Started', 'cta-lms' );
		}
	}

	/**
	 * Whether an attempt row represents a submitted (finished) attempt.
	 *
	 * @param object|null $attempt Attempt row.
	 * @return bool
	 */
	public static function attempt_is_submitted( $attempt ) {
		if ( ! $attempt ) {
			return false;
		}

		$completed_at = isset( $attempt->completed_at ) ? trim( (string) $attempt->completed_at ) : '';
		return '' !== $completed_at
			&& '0000-00-00' !== $completed_at
			&& '0000-00-00 00:00:00' !== $completed_at;
	}

	/**
	 * Whether an attempt is still open (started, not submitted).
	 *
	 * @param object|null $attempt Attempt row.
	 * @return bool
	 */
	public static function attempt_is_in_progress( $attempt ) {
		return $attempt && ! self::attempt_is_submitted( $attempt );
	}

	/**
	 * Remove ghost Practice Bank “completions”: submitted workbook-bank attempts
	 * with no real answer payload (empty / null / empty JSON object/array).
	 *
	 * Does not touch workbook module completion (modules_completed) or Form A/B.
	 *
	 * @return array{deleted_attempts:int,cleared_preserved:int}
	 */
	public static function reset_ghost_practice_bank_completions() {
		global $wpdb;

		$deleted  = 0;
		$cleared  = 0;
		$attempts = $wpdb->prefix . 'cta_quiz_attempts';
		$quizzes  = $wpdb->prefix . 'cta_quizzes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT a.id, a.user_id, a.course_id, a.quiz_id, a.answers, a.completed_at, q.quiz_type
			FROM {$attempts} a
			INNER JOIN {$quizzes} q ON q.id = a.quiz_id
			WHERE q.quiz_type REGEXP '^wb[0-9]+_bank$'
			  AND a.completed_at IS NOT NULL
			  AND a.completed_at NOT IN ('', '0000-00-00', '0000-00-00 00:00:00')"
		);

		foreach ( (array) $rows as $row ) {
			if ( ! self::attempt_answers_are_empty( $row->answers ?? null ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ok = $wpdb->delete( $attempts, array( 'id' => (int) $row->id ), array( '%d' ) );
			if ( $ok ) {
				++$deleted;
			}
		}

		// Clear preserved printable bank flags that have no matching real submitted attempt.
		if ( class_exists( 'CTA_Course_Materials' ) ) {
			$meta_key_like = 'cta_exam_preserved_attempts_%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
					$meta_key_like
				)
			);

			foreach ( (array) $meta_rows as $meta ) {
				$raw = maybe_unserialize( $meta->meta_value );
				if ( ! is_array( $raw ) || empty( $raw ) ) {
					continue;
				}

				$course_id = 0;
				if ( preg_match( '/^cta_exam_preserved_attempts_(\d+)$/', (string) $meta->meta_key, $m ) ) {
					$course_id = (int) $m[1];
				}
				if ( ! $course_id ) {
					continue;
				}

				$changed = false;
				foreach ( array_keys( $raw ) as $type ) {
					$type = sanitize_text_field( (string) $type );
					if ( ! preg_match( '/^wb\d+_bank$/', $type ) ) {
						continue;
					}
					if ( self::user_has_real_completed_bank_attempt( (int) $meta->user_id, $course_id, $type ) ) {
						continue;
					}
					unset( $raw[ $type ] );
					$changed = true;
					++$cleared;
				}

				if ( $changed ) {
					if ( empty( $raw ) ) {
						delete_user_meta( (int) $meta->user_id, (string) $meta->meta_key );
					} else {
						update_user_meta( (int) $meta->user_id, (string) $meta->meta_key, $raw );
					}
				}
			}
		}

		return array(
			'deleted_attempts'  => $deleted,
			'cleared_preserved' => $cleared,
		);
	}

	/**
	 * @param mixed $answers Attempt answers column.
	 * @return bool
	 */
	public static function attempt_answers_are_empty( $answers ) {
		if ( null === $answers || '' === $answers ) {
			return true;
		}
		if ( is_array( $answers ) ) {
			return empty( $answers );
		}

		$decoded = json_decode( (string) $answers, true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $value ) {
				if ( '' !== trim( (string) $value ) && null !== $value ) {
					return false;
				}
			}
			return true;
		}

		return '' === trim( (string) $answers ) || '{}' === trim( (string) $answers ) || '[]' === trim( (string) $answers );
	}

	/**
	 * @param int    $user_id   User ID.
	 * @param int    $course_id Course ID.
	 * @param string $quiz_type wbN_bank.
	 * @return bool
	 */
	private static function user_has_real_completed_bank_attempt( $user_id, $course_id, $quiz_type ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$quiz_type = sanitize_text_field( (string) $quiz_type );
		if ( ! $user_id || ! $course_id || '' === $quiz_type ) {
			return false;
		}

		$attempts = $wpdb->prefix . 'cta_quiz_attempts';
		$quizzes  = $wpdb->prefix . 'cta_quizzes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.answers, a.completed_at
				FROM {$attempts} a
				INNER JOIN {$quizzes} q ON q.id = a.quiz_id
				WHERE a.user_id = %d AND a.course_id = %d AND q.quiz_type = %s
				  AND a.completed_at IS NOT NULL
				  AND a.completed_at NOT IN ('', '0000-00-00', '0000-00-00 00:00:00')",
				$user_id,
				$course_id,
				$quiz_type
			)
		);

		foreach ( (array) $rows as $row ) {
			if ( ! self::attempt_answers_are_empty( $row->answers ?? null ) ) {
				return true;
			}
		}

		return false;
	}
}

}
