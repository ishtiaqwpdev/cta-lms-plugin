<?php
/**
 * LPCC NCMHCE Form A/B progressive case simulation player.
 *
 * Presents 11 three-section cases one section at a time with one-way locking,
 * a scheduled 15-minute break after Case 5, and a 225-minute item timer that
 * pauses during the break (per current NCMHCE / CTA approved spec).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Simulation' ) ) {

class CTA_Lpcc_Ncmhce_Simulation {

	const META_KEY              = '_ncmhce';
	const TIME_LIMIT_MINS       = 225;
	const BREAK_MINUTES         = 15;
	const BREAK_AFTER_CASE      = 5;
	const TOTAL_CASES           = 11;
	const SECTIONS_PER_CASE     = 3;
	const TOTAL_SECTIONS          = 33;
	const QUESTION_COUNT        = 143;

	/**
	 * @var array<string,array<int,array<string,mixed>>>|null
	 */
	private static $section_cache = null;

	/**
	 * Whether a quiz uses the NCMHCE progressive simulation player.
	 *
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_simulation_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		$type = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		if ( 'form_a' === $type && class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' ) ) {
			return CTA_Lpcc_Ncmhce_Form_A_Sync::is_live_v2_quiz( $quiz );
		}
		if ( 'form_b' === $type && class_exists( 'CTA_Lpcc_Ncmhce_Form_B_Sync' ) ) {
			return CTA_Lpcc_Ncmhce_Form_B_Sync::is_live_v2_quiz( $quiz );
		}

		return false;
	}

	/**
	 * Apply 225-minute timer to live Form A/B rows.
	 *
	 * @return array{ok:bool,course_id:int,updated:int,message:string}
	 */
	public static function sync_simulation_time_limits() {
		global $wpdb;

		if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'updated'   => 0,
				'message'   => 'program_sync_missing',
			);
		}

		$course = CTA_Lpcc_Ncmhce_Sync::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'updated'   => 0,
				'message'   => 'course_not_found',
			);
		}

		$course_id = (int) $course->id;
		$table     = $wpdb->prefix . 'cta_quizzes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET time_limit_mins = %d
				WHERE course_id = %d
					AND quiz_type IN ('form_a', 'form_b')
					AND (status IS NULL OR status <> 'archived')
					AND sort_order < 900",
				self::TIME_LIMIT_MINS,
				$course_id
			)
		);

		return array(
			'ok'        => false !== $updated,
			'course_id' => $course_id,
			'updated'   => false === $updated ? 0 : (int) $updated,
			'message'   => false === $updated ? 'update_failed' : 'synced',
		);
	}

	/**
	 * Approved NCMHCE question counts per case section (both Form A and Form B).
	 *
	 * @return array<int,array<int,int>>
	 */
	public static function get_questions_per_section_map() {
		return array(
			1  => array( 1 => 4, 2 => 5, 3 => 4 ),
			2  => array( 1 => 4, 2 => 4, 3 => 5 ),
			3  => array( 1 => 4, 2 => 4, 3 => 5 ),
			4  => array( 1 => 4, 2 => 4, 3 => 5 ),
			5  => array( 1 => 3, 2 => 5, 3 => 5 ),
			6  => array( 1 => 3, 2 => 5, 3 => 5 ),
			7  => array( 1 => 4, 2 => 4, 3 => 5 ),
			8  => array( 1 => 4, 2 => 4, 3 => 5 ),
			9  => array( 1 => 3, 2 => 5, 3 => 5 ),
			10 => array( 1 => 3, 2 => 5, 3 => 5 ),
			11 => array( 1 => 4, 2 => 5, 3 => 4 ),
		);
	}

	/**
	 * @param string $quiz_type form_a|form_b.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_section_blueprint( $quiz_type ) {
		$quiz_type = sanitize_key( $quiz_type );
		if ( null !== self::$section_cache && isset( self::$section_cache[ $quiz_type ] ) ) {
			return self::$section_cache[ $quiz_type ];
		}

		$rows    = self::load_seed_rows( $quiz_type );
		$layout  = self::get_questions_per_section_map();
		$sections = array();
		$order   = 0;
		$index   = 0;

		for ( $case = 1; $case <= self::TOTAL_CASES; $case++ ) {
			for ( $section = 1; $section <= self::SECTIONS_PER_CASE; $section++ ) {
				$count   = (int) ( $layout[ $case ][ $section ] ?? 0 );
				$indices = array();

				for ( $i = 0; $i < $count && $order < count( $rows ); $i++ ) {
					$indices[] = $order;
					++$order;
				}

				$first_row = isset( $indices[0] ) ? $rows[ $indices[0] ] : array();
				$parsed    = self::parse_case_section( is_array( $first_row ) ? $first_row : array(), $quiz_type );

				$sections[ $index ] = array(
					'index'            => $index,
					'case'             => $case,
					'section'          => $section,
					'title'            => $parsed['title'],
					'question_indices' => $indices,
				);
				++$index;
			}
		}

		if ( null === self::$section_cache ) {
			self::$section_cache = array();
		}
		self::$section_cache[ $quiz_type ] = $sections;

		return $sections;
	}

	/**
	 * @param string $quiz_type form_a|form_b.
	 * @return array<int,array<string,mixed>>
	 */
	private static function load_seed_rows( $quiz_type ) {
		if ( 'form_a' === $quiz_type && class_exists( 'CTA_Lpcc_Ncmhce_Form_A_Sync' ) ) {
			return CTA_Lpcc_Ncmhce_Form_A_Sync::get_questions();
		}
		if ( 'form_b' === $quiz_type && class_exists( 'CTA_Lpcc_Ncmhce_Form_B_Sync' ) ) {
			return CTA_Lpcc_Ncmhce_Form_B_Sync::get_questions();
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $row Seed row.
	 * @param string              $quiz_type form_a|form_b.
	 * @return array{case:int,section:int,title:string}
	 */
	public static function parse_case_section( array $row, $quiz_type ) {
		if ( 'form_b' === sanitize_key( $quiz_type ) ) {
			$case    = isset( $row['case_number'] ) ? (int) $row['case_number'] : 1;
			$section = isset( $row['section_number'] ) ? (int) $row['section_number'] : 1;
			$title   = self::extract_section_title( (string) ( $row['question_text'] ?? '' ), $case, $section );

			return array(
				'case'    => max( 1, $case ),
				'section' => max( 1, min( 3, $section ) ),
				'title'   => $title,
			);
		}

		$text = (string) ( $row['question_text'] ?? '' );
		$case = 1;
		$sec  = 1;

		if ( preg_match( '/CASE\s+(\d+)/iu', $text, $case_match ) ) {
			$case = (int) $case_match[1];
		}
		if ( preg_match( '/SECTION\s+(\d+)/iu', $text, $sec_match ) ) {
			$sec = (int) $sec_match[1];
		}

		return array(
			'case'    => max( 1, $case ),
			'section' => max( 1, min( 3, $sec ) ),
			'title'   => self::extract_section_title( $text, $case, $sec ),
		);
	}

	/**
	 * @param string $text Question text.
	 * @param int    $case Case number.
	 * @param int    $section Section number.
	 * @return string
	 */
	private static function extract_section_title( $text, $case, $section ) {
		$line = trim( (string) strtok( $text, "\n" ) );
		if ( '' !== $line ) {
			return $line;
		}

		return sprintf(
			/* translators: 1: case number, 2: section number */
			__( 'Case %1$d — Section %2$d', 'cta-lms' ),
			(int) $case,
			(int) $section
		);
	}

	/**
	 * Map ordered question rows to section indices (by order_index position).
	 *
	 * @param object      $quiz Quiz row.
	 * @param array       $questions Ordered question rows.
	 * @return array<int,int> question_id => section_index
	 */
	public static function map_questions_to_sections( $quiz, array $questions ) {
		$quiz_type  = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		$blueprint  = self::get_section_blueprint( $quiz_type );
		$index_map  = array();

		foreach ( $blueprint as $section ) {
			foreach ( (array) ( $section['question_indices'] ?? array() ) as $seed_index ) {
				if ( isset( $questions[ $seed_index ] ) ) {
					$index_map[ (int) $questions[ $seed_index ]->id ] = (int) $section['index'];
				}
			}
		}

		return $index_map;
	}

	/**
	 * @param array<string,mixed> $answers Decoded attempt answers.
	 * @return array<string,mixed>
	 */
	public static function get_meta_from_answers( array $answers ) {
		$meta = isset( $answers[ self::META_KEY ] ) && is_array( $answers[ self::META_KEY ] )
			? $answers[ self::META_KEY ]
			: array();

		return self::normalize_meta( $meta );
	}

	/**
	 * @param array<string,mixed> $meta Raw meta.
	 * @return array<string,mixed>
	 */
	public static function normalize_meta( array $meta ) {
		$section_index = isset( $meta['section_index'] ) ? (int) $meta['section_index'] : 0;
		$section_index = max( 0, min( self::TOTAL_SECTIONS - 1, $section_index ) );

		$break_state = isset( $meta['break_state'] ) ? sanitize_key( (string) $meta['break_state'] ) : 'none';
		if ( ! in_array( $break_state, array( 'none', 'pending', 'active', 'done' ), true ) ) {
			$break_state = 'none';
		}

		return array(
			'section_index'        => $section_index,
			'locked_through'       => isset( $meta['locked_through'] ) ? max( -1, (int) $meta['locked_through'] ) : max( -1, $section_index - 1 ),
			'break_state'          => $break_state,
			'break_started_at'     => isset( $meta['break_started_at'] ) ? sanitize_text_field( (string) $meta['break_started_at'] ) : '',
			'break_completed_at'   => isset( $meta['break_completed_at'] ) ? sanitize_text_field( (string) $meta['break_completed_at'] ) : '',
			'break_pause_seconds'  => isset( $meta['break_pause_seconds'] ) ? max( 0, (int) $meta['break_pause_seconds'] ) : 0,
		);
	}

	/**
	 * Default meta for a new attempt.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_meta() {
		return self::normalize_meta(
			array(
				'section_index'  => 0,
				'locked_through' => -1,
				'break_state'    => 'none',
			)
		);
	}

	/**
	 * Section index after Case 5 (last section of case 5).
	 *
	 * @return int
	 */
	public static function break_after_section_index() {
		return ( self::BREAK_AFTER_CASE * self::SECTIONS_PER_CASE ) - 1;
	}

	/**
	 * Whether the scheduled break applies after a section index.
	 *
	 * @param int $completed_section_index Section just completed.
	 * @return bool
	 */
	public static function break_follows_section( $completed_section_index ) {
		return (int) $completed_section_index === self::break_after_section_index();
	}

	/**
	 * Merge learner answers with validated simulation meta.
	 *
	 * @param array<string,mixed> $sanitized Sanitized question answers.
	 * @param array<string,mixed> $incoming_meta Incoming meta from client.
	 * @param object              $quiz Quiz row.
	 * @param array               $questions Question rows.
	 * @param array<string,mixed> $existing_answers Existing stored answers (including meta).
	 * @return array<string,mixed>
	 */
	public static function merge_attempt_answers( array $sanitized, array $incoming_meta, $quiz, array $questions, array $existing_answers = array() ) {
		$existing = self::normalize_meta( self::get_meta_from_answers( $existing_answers ) );
		$incoming = self::normalize_meta( $incoming_meta );
		$merged   = $existing;

		$old_sec = (int) $existing['section_index'];
		$new_sec = (int) $incoming['section_index'];

		if ( $new_sec < $old_sec ) {
			$new_sec = $old_sec;
		}
		if ( $new_sec > $old_sec + 1 ) {
			$new_sec = $old_sec + 1;
		}

		$break_gate = self::break_after_section_index();
		$advancing  = $new_sec > $old_sec;

		if ( $advancing && $old_sec === $break_gate && 'done' !== $existing['break_state'] ) {
			$new_sec                   = $old_sec;
			$merged['break_state']     = 'active';
			$merged['break_started_at'] = $incoming['break_started_at'] ?: current_time( 'mysql' );
			$merged['locked_through']  = max( (int) $merged['locked_through'], $break_gate );
		} elseif ( $advancing ) {
			$merged['section_index']  = $new_sec;
			$merged['locked_through'] = max( (int) $merged['locked_through'], $old_sec );
		}

		if ( 'done' === $incoming['break_state'] && in_array( (string) $existing['break_state'], array( 'active', 'pending' ), true ) ) {
			$completed_at = $incoming['break_completed_at'] ?: current_time( 'mysql' );
			$started_ts   = strtotime( (string) $merged['break_started_at'] );
			$completed_ts = strtotime( (string) $completed_at );
			$pause        = ( $started_ts > 0 && $completed_ts > 0 ) ? max( 0, $completed_ts - $started_ts ) : 0;
			$pause        = min( $pause, self::BREAK_MINUTES * 60 );

			$merged['break_state']         = 'done';
			$merged['break_completed_at']  = $completed_at;
			$merged['break_pause_seconds'] = (int) $existing['break_pause_seconds'] + $pause;
			$merged['section_index']       = $break_gate + 1;
			$merged['locked_through']      = max( (int) $merged['locked_through'], $break_gate );
		} elseif ( 'active' === $incoming['break_state'] && 'done' !== $existing['break_state'] && $old_sec === $break_gate ) {
			$merged['break_state']      = 'active';
			$merged['break_started_at'] = $incoming['break_started_at'] ?: current_time( 'mysql' );
			$merged['locked_through']   = max( (int) $merged['locked_through'], $break_gate );
		}

		$section_map = self::map_questions_to_sections( $quiz, $questions );
		$locked_max  = (int) $merged['locked_through'];

		foreach ( $existing_answers as $qid => $answer ) {
			if ( self::META_KEY === $qid ) {
				continue;
			}
			$qid = (int) $qid;
			if ( ! isset( $section_map[ $qid ] ) ) {
				continue;
			}
			if ( (int) $section_map[ $qid ] <= $locked_max && in_array( $answer, array( 'a', 'b', 'c', 'd' ), true ) ) {
				$sanitized[ $qid ] = $answer;
			}
		}

		foreach ( $sanitized as $qid => $answer ) {
			if ( self::META_KEY === $qid ) {
				continue;
			}
			$qid = (int) $qid;
			if ( ! isset( $section_map[ $qid ] ) ) {
				continue;
			}
			if ( (int) $section_map[ $qid ] <= $locked_max ) {
				unset( $sanitized[ $qid ] );
			}
		}

		// Re-apply preserved locked answers after stripping incoming edits.
		foreach ( $existing_answers as $qid => $answer ) {
			if ( self::META_KEY === $qid ) {
				continue;
			}
			$qid = (int) $qid;
			if ( ! isset( $section_map[ $qid ] ) ) {
				continue;
			}
			if ( (int) $section_map[ $qid ] <= $locked_max && in_array( $answer, array( 'a', 'b', 'c', 'd' ), true ) ) {
				$sanitized[ $qid ] = $answer;
			}
		}

		$sanitized[ self::META_KEY ] = self::normalize_meta( $merged );

		return $sanitized;
	}

	/**
	 * Strip simulation meta before scoring.
	 *
	 * @param array<string,mixed> $answers Answers map.
	 * @return array<string,mixed>
	 */
	public static function strip_meta_from_answers( array $answers ) {
		unset( $answers[ self::META_KEY ] );

		return $answers;
	}

	/**
	 * Adjust remaining seconds for break pauses.
	 *
	 * @param object|null $quiz Quiz row.
	 * @param object|null $attempt Attempt row.
	 * @param int         $base_remaining Base remaining seconds.
	 * @return int
	 */
	public static function adjust_seconds_remaining( $quiz, $attempt, $base_remaining ) {
		if ( ! self::is_simulation_quiz( $quiz ) || ! $attempt || $base_remaining <= 0 ) {
			return $base_remaining;
		}

		$decoded = array();
		if ( ! empty( $attempt->answers ) ) {
			$maybe = json_decode( (string) $attempt->answers, true );
			if ( is_array( $maybe ) ) {
				$decoded = $maybe;
			}
		}

		$meta = self::get_meta_from_answers( $decoded );
		$pause = (int) ( $meta['break_pause_seconds'] ?? 0 );

		if ( 'active' === ( $meta['break_state'] ?? '' ) && ! empty( $meta['break_started_at'] ) ) {
			$started = strtotime( (string) $meta['break_started_at'] );
			$now     = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
			if ( $started > 0 ) {
				$pause += max( 0, $now - $started );
			}
		}

		return max( 0, $base_remaining + $pause );
	}

	/**
	 * Render progressive simulation questions.
	 *
	 * @param object $quiz Quiz row.
	 * @param object $attempt Attempt row.
	 * @param array  $questions Question rows.
	 * @param bool   $review Review mode.
	 * @return string
	 */
	public static function render_questions( $quiz, $attempt, array $questions, $review = false ) {
		$quiz_type   = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		$blueprint   = self::get_section_blueprint( $quiz_type );
		$section_map = self::map_questions_to_sections( $quiz, $questions );

		$answers = array();
		if ( ! empty( $attempt->answers ) ) {
			$decoded = json_decode( (string) $attempt->answers, true );
			if ( is_array( $decoded ) ) {
				$answers = $decoded;
			}
		}

		$meta = self::get_meta_from_answers( $answers );

		ob_start();

		echo '<div class="cta-ncmhce-simulation" data-ncmhce-total-sections="' . esc_attr( (string) count( $blueprint ) ) . '">';

		foreach ( $blueprint as $section ) {
			$section_index = (int) $section['index'];
			$header        = sprintf(
				/* translators: 1: case number, 2: section number, 3: section title */
				__( 'Case %1$d — Section %2$d: %3$s', 'cta-lms' ),
				(int) $section['case'],
				(int) $section['section'],
				(string) $section['title']
			);

			echo '<section class="cta-ncmhce-section" data-ncmhce-section-index="' . esc_attr( (string) $section_index ) . '" data-ncmhce-case="' . esc_attr( (string) (int) $section['case'] ) . '" hidden>';
			echo '<header class="cta-ncmhce-section__header"><h2 class="cta-ncmhce-section__title">' . esc_html( $header ) . '</h2></header>';
			echo '<div class="cta-ncmhce-section__questions">';

			foreach ( (array) ( $section['question_indices'] ?? array() ) as $seed_index ) {
				if ( ! isset( $questions[ $seed_index ] ) ) {
					continue;
				}

				$question        = $questions[ $seed_index ];
				$question_number = (int) $seed_index + 1;
				$user_answer     = isset( $answers[ $question->id ] ) ? $answers[ $question->id ] : '';
				$is_locked       = $section_index <= (int) $meta['locked_through'] && ! $review;

				include CTA_PLUGIN_DIR . 'templates/partials/quiz-question.php';
			}

			echo '</div>';
			echo '</section>';
		}

		echo '<div class="cta-ncmhce-break" data-ncmhce-break-panel hidden>';
		echo '<div class="card cta-ncmhce-break__card">';
		echo '<h2 class="cta-ncmhce-break__title">' . esc_html__( 'Scheduled Break', 'cta-lms' ) . '</h2>';
		echo '<p class="cta-ncmhce-break__lead">' . esc_html__( 'You have completed Case 5. This is your 15-minute scheduled break.', 'cta-lms' ) . '</p>';
		echo '<p class="cta-ncmhce-break__note">' . esc_html__( 'The examination item timer is paused during this break. When you are ready, select Resume Examination to continue with Case 6.', 'cta-lms' ) . '</p>';
		echo '<p class="cta-ncmhce-break__timer" data-ncmhce-break-timer aria-live="polite">15:00</p>';
		echo '<button type="button" class="btn btn-primary" data-ncmhce-break-resume>' . esc_html__( 'Resume Examination', 'cta-lms' ) . '</button>';
		echo '</div>';
		echo '</div>';

		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Client payload for simulation bootstrap.
	 *
	 * @param object $quiz Quiz row.
	 * @param object|null $attempt Attempt row.
	 * @param array  $questions Question rows.
	 * @return array<string,mixed>
	 */
	public static function get_client_config( $quiz, $attempt, array $questions ) {
		$quiz_type   = sanitize_key( (string) ( $quiz->quiz_type ?? '' ) );
		$blueprint   = self::get_section_blueprint( $quiz_type );
		$sections    = array();

		foreach ( $blueprint as $section ) {
			$qids = array();
			foreach ( (array) ( $section['question_indices'] ?? array() ) as $seed_index ) {
				if ( isset( $questions[ $seed_index ] ) ) {
					$qids[] = (int) $questions[ $seed_index ]->id;
				}
			}
			$sections[] = array(
				'index'        => (int) $section['index'],
				'case'         => (int) $section['case'],
				'section'      => (int) $section['section'],
				'title'        => (string) $section['title'],
				'question_ids' => $qids,
			);
		}

		$meta = self::default_meta();
		if ( $attempt && ! empty( $attempt->answers ) ) {
			$decoded = json_decode( (string) $attempt->answers, true );
			if ( is_array( $decoded ) ) {
				$meta = self::get_meta_from_answers( $decoded );
			}
		}

		return array(
			'sections'                 => $sections,
			'meta'                     => $meta,
			'break_minutes'            => self::BREAK_MINUTES,
			'break_after_section'      => self::break_after_section_index(),
			'total_sections'           => count( $sections ),
			'question_count'           => count( $questions ),
		);
	}

	/**
	 * Approved exam instructions for the start card.
	 *
	 * @return string
	 */
	public static function get_exam_instructions() {
		return implode(
			"\n",
			array(
				__( 'This simulation follows the current NCMHCE format: 11 case studies in three sections each (143 questions total).', 'cta-lms' ),
				__( 'You may review and change answers within the current case section only. After you select Continue, prior sections lock and cannot be reopened.', 'cta-lms' ),
				__( 'Use 225 minutes for examination questions. A 15-minute scheduled break begins after Case 5; the item timer pauses during the break.', 'cta-lms' ),
				__( 'Select one best answer for every question. Answer rationales appear after you submit the full simulation.', 'cta-lms' ),
			)
		);
	}
}

}
