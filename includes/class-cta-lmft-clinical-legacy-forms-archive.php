<?php
/**
 * Archive legacy LMFT California Clinical Form A/B assessments (PROMPT 00).
 *
 * Marks existing form_a / form_b quizzes and printable simulations as archived
 * so learners cannot reach them, while preserving all DB rows and attempt history.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Legacy_Forms_Archive
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Legacy_Forms_Archive' ) ) {

class CTA_Lmft_Clinical_Legacy_Forms_Archive {

	const ARCHIVE_OPTION         = 'cta_lmft_clinical_legacy_forms_archived_1_0_256';
	const FINAL_ENSURE_OPTION    = 'cta_lmft_clinical_final_forms_ensured_1_0_256';
	const TARGET_COURSE_ID       = 10;
	const ARCHIVED_STATUS        = 'archived';
	const ARCHIVED_TITLE_PREFIX  = '[Archived] ';
	const ARCHIVED_SORT_FORM_A   = 920;
	const ARCHIVED_SORT_FORM_B   = 930;
	const ARCHIVED_RESOURCE_SORT = 900;

	/**
	 * Final August 14 Form A Q1 fingerprint (must remain learner-facing).
	 */
	const FINAL_FORM_A_Q1_NEEDLE = 'A transgender man reports for ten months a marked incongruence';

	/**
	 * Final August 14 Form B Q1 fingerprint (must remain learner-facing).
	 */
	const FINAL_FORM_B_Q1_NEEDLE = 'A service wants an ongoing therapy and psychoeducation group for adults recently diagnosed';

	/**
	 * July 2026 legacy Form A Q1 fingerprint (must never remain learner-accessible).
	 */
	const LEGACY_JULY_FORM_A_Q1_NEEDLE = 'escalating partner violence';

	/**
	 * Quiz types covered by this archive pass.
	 *
	 * @return string[]
	 */
	public static function legacy_quiz_types() {
		return array( 'form_a', 'form_b', 'legacy_form_a', 'legacy_form_b' );
	}

	/**
	 * Map active quiz types to archived legacy quiz_type values.
	 *
	 * @return array<string,string>
	 */
	public static function legacy_quiz_type_map() {
		return array(
			'form_a' => 'legacy_form_a',
			'form_b' => 'legacy_form_b',
		);
	}

	/**
	 * Relative simulation file markers for legacy Form A/B learner materials.
	 *
	 * @return string[]
	 */
	public static function legacy_resource_path_markers() {
		return array(
			'simulations/cta_lmft_comprehensive_simulation_form_a_150_question_exam_v1.0.docx',
			'simulations/cta_lmft_comprehensive_simulation_form_b_150_question_exam_v1.0.docx',
			'simulations/cta_lmft_comprehensive_simulation_form_a_answer_key_and_detailed_rationales_v1.0.docx',
			'simulations/cta_lmft_comprehensive_simulation_form_b_answer_key_and_detailed_rationales_v1.0.docx',
			'simulations/_archived/cta_lmft_comprehensive_simulation_form_a_150_question_exam_v1.0.docx',
			'simulations/_archived/cta_lmft_comprehensive_simulation_form_b_150_question_exam_v1.0.docx',
			'simulations/_archived/cta_lmft_comprehensive_simulation_form_a_answer_key_and_detailed_rationales_v1.0.docx',
			'simulations/_archived/cta_lmft_comprehensive_simulation_form_b_answer_key_and_detailed_rationales_v1.0.docx',
		);
	}

	/**
	 * Resolve the LMFT Clinical course ID (prefers course_id=10 when valid).
	 *
	 * @param int $course_id Optional explicit course ID.
	 * @return int
	 */
	public static function resolve_course_id( $course_id = 0 ) {
		$course_id = absint( $course_id );
		if ( $course_id > 0 ) {
			return $course_id;
		}

		if ( self::TARGET_COURSE_ID > 0 && class_exists( 'CTA_Database' ) ) {
			$row = CTA_Database::get_course( self::TARGET_COURSE_ID );
			if ( $row && self::is_lmft_clinical_course( $row ) ) {
				return (int) self::TARGET_COURSE_ID;
			}
		}

		if ( class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {
			$course = CTA_Lmft_Clinical_Sync::find_course();
			if ( $course ) {
				return (int) $course->id;
			}
		}

		return 0;
	}

	/**
	 * @param object $course Course row.
	 * @return bool
	 */
	public static function is_lmft_clinical_course( $course ) {
		if ( ! $course ) {
			return false;
		}

		$slug  = strtolower( (string) ( $course->slug ?? '' ) );
		$title = strtolower( (string) ( $course->title ?? '' ) );

		if ( 'lmft-california-clinical-exam-preparation' === $slug ) {
			return true;
		}

		return false !== strpos( $title, 'lmft california clinical' );
	}

	/**
	 * Whether legacy Form A/B have already been archived for this program.
	 *
	 * @param int $course_id Optional course ID.
	 * @return bool
	 */
	public static function is_legacy_forms_archived( $course_id = 0 ) {
		$record = get_option( self::ARCHIVE_OPTION, array() );
		if ( ! is_array( $record ) || empty( $record['archived'] ) ) {
			return false;
		}

		$stored_id = absint( $record['course_id'] ?? 0 );
		$course_id = self::resolve_course_id( $course_id );

		if ( $course_id && $stored_id && $course_id !== $stored_id ) {
			return false;
		}

		return true;
	}

	/**
	 * @param object|null $quiz Quiz row.
	 * @return bool
	 */
	public static function is_archived_quiz( $quiz ) {
		if ( ! $quiz ) {
			return false;
		}

		if ( self::ARCHIVED_STATUS === (string) ( $quiz->status ?? '' ) ) {
			return true;
		}

		return self::title_is_archived( (string) ( $quiz->title ?? '' ) );
	}

	/**
	 * @param object|null $resource Resource row.
	 * @return bool
	 */
	public static function is_archived_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		return self::title_is_archived( (string) ( $resource->title ?? '' ) );
	}

	/**
	 * @param string $title Title.
	 * @return bool
	 */
	public static function title_is_archived( $title ) {
		return 0 === stripos( trim( (string) $title ), self::ARCHIVED_TITLE_PREFIX );
	}

	/**
	 * @param string $path Path or title haystack.
	 * @return bool
	 */
	public static function resource_path_is_legacy_form( $path ) {
		return self::matches_legacy_form_resource(
			(object) array(
				'file_path' => (string) $path,
				'file_url'  => '',
				'title'     => '',
			)
		);
	}

	/**
	 * Archive any active Form A/B quiz whose Q1 is not the August 14 Final fingerprint.
	 *
	 * This catches July partner-violence Form A (and any other non-final bank) still
	 * sitting in the live form_a / form_b slots so Final sync can recreate them.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if ensure option is set.
	 * @return array{ok:bool,course_id:int,archived:int,message:string}
	 */
	public static function archive_non_final_active_forms( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );

		if ( ! $course_id ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'archived'  => 0,
				'message'   => 'course_not_found',
			);
		}

		if ( ! $force && get_option( self::FINAL_ENSURE_OPTION ) ) {
			$stored = get_option( self::FINAL_ENSURE_OPTION, array() );
			return array(
				'ok'        => true,
				'course_id' => $course_id,
				'archived'  => (int) ( $stored['archived'] ?? 0 ),
				'message'   => 'already_ensured',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'cta_quizzes';
		$archived = 0;
		$type_map = self::legacy_quiz_type_map();
		$sorts    = array(
			'legacy_form_a' => self::ARCHIVED_SORT_FORM_A,
			'legacy_form_b' => self::ARCHIVED_SORT_FORM_B,
		);

		foreach ( (array) CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( ! in_array( $type, array( 'form_a', 'form_b' ), true ) ) {
				continue;
			}
			if ( self::is_archived_quiz( $row ) ) {
				continue;
			}

			$q1 = self::get_quiz_question_one_text( (int) $row->id );
			if ( self::quiz_matches_final_form( $type, $q1 ) ) {
				continue;
			}

			$legacy_type = $type_map[ $type ] ?? ( 'form_a' === $type ? 'legacy_form_a' : 'legacy_form_b' );
			$title       = self::prefix_archived_title( (string) ( $row->title ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'      => $title,
					'quiz_type'  => $legacy_type,
					'status'     => self::ARCHIVED_STATUS,
					'sort_order' => (int) ( $sorts[ $legacy_type ] ?? self::ARCHIVED_SORT_FORM_A ),
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);
			++$archived;
		}

		// Always re-archive July printable resources if still present under learner titles.
		$res = self::archive_legacy_resources( $course_id );

		update_option(
			self::FINAL_ENSURE_OPTION,
			array(
				'at'           => current_time( 'mysql' ),
				'course_id'    => $course_id,
				'archived'     => $archived,
				'resource_ids' => $res['resource_ids'] ?? array(),
			),
			false
		);

		// Mark legacy printable archive complete so materials sync never re-publishes July files.
		update_option(
			self::ARCHIVE_OPTION,
			array(
				'archived'       => true,
				'at'             => current_time( 'mysql' ),
				'course_id'      => $course_id,
				'form_a_quiz_id' => 0,
				'form_b_quiz_id' => 0,
				'resource_ids'   => $res['resource_ids'] ?? array(),
				'source'         => 'final_aug14_ensure',
			),
			false
		);

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'archived'  => $archived,
			'message'   => 'ensured',
		);
	}

	/**
	 * @param string $quiz_type form_a|form_b.
	 * @param string $q1        Question 1 text.
	 * @return bool
	 */
	public static function quiz_matches_final_form( $quiz_type, $q1 ) {
		$q1 = (string) $q1;
		if ( 'form_a' === $quiz_type ) {
			return false !== stripos( $q1, self::FINAL_FORM_A_Q1_NEEDLE );
		}
		if ( 'form_b' === $quiz_type ) {
			return false !== stripos( $q1, self::FINAL_FORM_B_Q1_NEEDLE );
		}
		return false;
	}

	/**
	 * @param int $quiz_id Quiz ID.
	 * @return string
	 */
	public static function get_quiz_question_one_text( $quiz_id ) {
		global $wpdb;

		$quiz_id = absint( $quiz_id );
		if ( ! $quiz_id ) {
			return '';
		}

		$table = $wpdb->prefix . 'cta_quiz_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$text = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT question_text FROM {$table} WHERE quiz_id = %d ORDER BY order_index ASC, id ASC LIMIT 1",
				$quiz_id
			)
		);

		return is_string( $text ) ? $text : '';
	}

	/**
	 * Locate the learner-facing Final Form A or Form B quiz row.
	 *
	 * @param int    $course_id  Course ID.
	 * @param string $quiz_type  form_a|form_b.
	 * @return object|null
	 */
	public static function get_active_final_form_quiz( $course_id, $quiz_type ) {
		$course_id = self::resolve_course_id( $course_id );
		$quiz_type = sanitize_key( (string) $quiz_type );

		if ( ! $course_id || ! in_array( $quiz_type, array( 'form_a', 'form_b' ), true ) || ! class_exists( 'CTA_Database' ) ) {
			return null;
		}

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			if ( sanitize_key( (string) ( $row->quiz_type ?? '' ) ) !== $quiz_type ) {
				continue;
			}
			if ( self::is_archived_quiz( $row ) ) {
				continue;
			}
			if ( 'active' !== (string) ( $row->status ?? '' ) ) {
				continue;
			}

			$quiz_id = (int) $row->id;
			$expected_count = class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' )
				? (int) CTA_Lmft_Clinical_Form_A_Sync::TARGET_QUESTION_COUNT
				: 150;
			if ( $expected_count !== count( CTA_Database::get_quiz_questions( $quiz_id ) ) ) {
				continue;
			}

			$timer = (int) ( $row->time_limit_mins ?? 0 );
			if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
				$expected = (int) CTA_Lmft_Clinical_Form_A_Sync::TIME_LIMIT_MINS;
			} else {
				$expected = 240;
			}
			if ( $timer !== $expected ) {
				continue;
			}

			$q1 = self::get_quiz_question_one_text( $quiz_id );
			if ( ! self::quiz_matches_final_form( $quiz_type, $q1 ) ) {
				continue;
			}

			return $row;
		}

		return null;
	}

	/**
	 * Whether the August 14 Final form is live for learners (active, 150 Q, 240 min, correct Q1).
	 *
	 * @param int    $course_id Course ID.
	 * @param string $quiz_type form_a|form_b.
	 * @return bool
	 */
	public static function is_live_final_form( $course_id, $quiz_type ) {
		return null !== self::get_active_final_form_quiz( $course_id, $quiz_type );
	}

	/**
	 * Restore missing/inactive Final Form A/B after legacy archive or stale seed flags.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-sync even when both forms appear healthy.
	 * @return array{ok:bool,course_id:int,form_a_synced:bool,form_b_synced:bool,message:string}
	 */
	public static function ensure_learner_final_forms( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );

		if ( ! $course_id ) {
			return array(
				'ok'            => false,
				'course_id'     => 0,
				'form_a_synced' => false,
				'form_b_synced' => false,
				'message'       => 'course_not_found',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		$form_a_synced = false;
		$form_b_synced = false;

		if ( $force || ! self::is_live_final_form( $course_id, 'form_a' ) ) {
			if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Sync::sync( true );
				$form_a_synced = true;
			}
			if ( class_exists( 'CTA_Lmft_Clinical_Form_A_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_A_Answer_Sync::sync_answer_keys( true );
			}
		}

		if ( $force || ! self::is_live_final_form( $course_id, 'form_b' ) ) {
			if ( class_exists( 'CTA_Lmft_Clinical_Form_B_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Sync::sync( true );
				$form_b_synced = true;
			}
			if ( class_exists( 'CTA_Lmft_Clinical_Form_B_Answer_Sync' ) ) {
				CTA_Lmft_Clinical_Form_B_Answer_Sync::sync_answer_keys( true );
			}
		}

		$form_a_ok = self::is_live_final_form( $course_id, 'form_a' );
		$form_b_ok = self::is_live_final_form( $course_id, 'form_b' );

		return array(
			'ok'            => $form_a_ok && $form_b_ok,
			'course_id'     => $course_id,
			'form_a_synced' => $form_a_synced,
			'form_b_synced' => $form_b_synced,
			'message'       => $form_a_ok && $form_b_ok ? 'final_forms_live' : 'final_forms_incomplete',
		);
	}

	/**
	 * Remove archived duplicate Form A/B quiz rows when no learner attempts exist.
	 *
	 * Preserves rows that still have attempt history so admins can decide retention.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if purge option is set.
	 * @return array{ok:bool,course_id:int,deleted:int,deleted_ids:int[],blocked:array<int,array{quiz_id:int,title:string,attempt_count:int}>,message:string}
	 */
	public static function purge_archived_duplicate_form_quizzes( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );
		$option    = 'cta_lmft_clinical_archived_form_quizzes_purged_1_0_279';

		if ( ! $course_id ) {
			return array(
				'ok'         => false,
				'course_id'  => 0,
				'deleted'    => 0,
				'deleted_ids' => array(),
				'blocked'    => array(),
				'message'    => 'course_not_found',
			);
		}

		if ( ! $force && get_option( $option ) ) {
			$stored = get_option( $option, array() );
			return array(
				'ok'          => true,
				'course_id'   => $course_id,
				'deleted'     => (int) ( $stored['deleted'] ?? 0 ),
				'deleted_ids' => is_array( $stored['deleted_ids'] ?? null ) ? $stored['deleted_ids'] : array(),
				'blocked'     => is_array( $stored['blocked'] ?? null ) ? $stored['blocked'] : array(),
				'message'     => 'already_purged',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		global $wpdb;

		$keep_ids = array();
		foreach ( array( 'form_a', 'form_b' ) as $type ) {
			$row = self::get_active_final_form_quiz( $course_id, $type );
			if ( $row ) {
				$keep_ids[] = (int) $row->id;
			}
		}

		$deleted     = 0;
		$deleted_ids = array();
		$blocked     = array();

		foreach ( (array) CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$quiz_id = (int) $row->id;
			if ( ! $quiz_id || in_array( $quiz_id, $keep_ids, true ) ) {
				continue;
			}

			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( ! self::is_archived_quiz( $row )
				&& ! in_array( $type, array( 'legacy_form_a', 'legacy_form_b' ), true ) ) {
				continue;
			}

			$attempt_count = self::count_quiz_attempts( $quiz_id );
			if ( $attempt_count > 0 ) {
				$blocked[] = array(
					'quiz_id'       => $quiz_id,
					'title'         => (string) ( $row->title ?? '' ),
					'attempt_count' => $attempt_count,
				);
				continue;
			}

			$q_table = $wpdb->prefix . 'cta_quiz_questions';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $q_table, array( 'quiz_id' => $quiz_id ), array( '%d' ) );

			$quiz_table = $wpdb->prefix . 'cta_quizzes';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $quiz_table, array( 'id' => $quiz_id ), array( '%d' ) );

			$deleted_ids[] = $quiz_id;
			++$deleted;
		}

		update_option(
			$option,
			array(
				'at'          => current_time( 'mysql' ),
				'course_id'   => $course_id,
				'deleted'     => $deleted,
				'deleted_ids' => $deleted_ids,
				'blocked'     => $blocked,
			),
			false
		);

		return array(
			'ok'          => true,
			'course_id'   => $course_id,
			'deleted'     => $deleted,
			'deleted_ids' => $deleted_ids,
			'blocked'     => $blocked,
			'message'     => $deleted > 0 ? 'purged' : ( ! empty( $blocked ) ? 'blocked_by_attempts' : 'nothing_to_purge' ),
		);
	}

	/**
	 * Count all quiz attempts tied to a quiz row.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int
	 */
	public static function count_quiz_attempts( $quiz_id ) {
		global $wpdb;

		$quiz_id = absint( $quiz_id );
		if ( ! $quiz_id ) {
			return 0;
		}

		$table = $wpdb->prefix . 'cta_quiz_attempts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM {$table} WHERE quiz_id = %d",
				$quiz_id
			)
		);
	}

	/**
	 * Filter admin assessment dropdown rows to hide archived legacy duplicates.
	 *
	 * @param array  $quizzes   Quiz rows.
	 * @param object $course    Course row.
	 * @return array
	 */
	public static function filter_admin_assessment_quizzes( array $quizzes, $course ) {
		if ( ! self::is_lmft_clinical_course( $course ) ) {
			return $quizzes;
		}

		$filtered = array();
		foreach ( $quizzes as $row ) {
			if ( self::is_archived_quiz( $row ) ) {
				continue;
			}
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( in_array( $type, array( 'legacy_form_a', 'legacy_form_b' ), true ) ) {
				continue;
			}
			$filtered[] = $row;
		}

		return $filtered;
	}

	/**
	 * Archive legacy Form A/B quizzes and related printable materials.
	 *
	 * @param int  $course_id Optional course ID.
	 * @param bool $force     Re-run even if archive option is already set.
	 * @return array{ok:bool,course_id:int,form_a:int,form_b:int,resources:int,message:string}
	 */
	public static function archive_legacy_forms( $course_id = 0, $force = false ) {
		$course_id = self::resolve_course_id( $course_id );

		if ( ! $course_id ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'form_a'    => 0,
				'form_b'    => 0,
				'resources' => 0,
				'message'   => 'course_not_found',
			);
		}

		if ( ! $force && self::is_legacy_forms_archived( $course_id ) ) {
			$ids = self::get_archived_quiz_ids( $course_id );
			return array(
				'ok'        => true,
				'course_id' => $course_id,
				'form_a'    => (int) ( $ids['form_a'] ?? 0 ),
				'form_b'    => (int) ( $ids['form_b'] ?? 0 ),
				'resources' => count( self::get_archived_resource_ids( $course_id ) ),
				'message'   => 'already_archived',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		$quiz_result = self::archive_legacy_quizzes( $course_id );
		$res_result  = self::archive_legacy_resources( $course_id );

		update_option(
			self::ARCHIVE_OPTION,
			array(
				'archived'      => true,
				'at'            => current_time( 'mysql' ),
				'course_id'     => $course_id,
				'form_a_quiz_id' => (int) ( $quiz_result['form_a'] ?? 0 ),
				'form_b_quiz_id' => (int) ( $quiz_result['form_b'] ?? 0 ),
				'resource_ids'  => $res_result['resource_ids'],
			),
			false
		);

		return array(
			'ok'        => ! empty( $quiz_result['form_a'] ) || ! empty( $quiz_result['form_b'] ) || ! empty( $res_result['resource_ids'] ),
			'course_id' => $course_id,
			'form_a'    => (int) ( $quiz_result['form_a'] ?? 0 ),
			'form_b'    => (int) ( $quiz_result['form_b'] ?? 0 ),
			'resources' => count( $res_result['resource_ids'] ),
			'message'   => 'archived',
		);
	}

	/**
	 * @param int $course_id Course ID.
	 * @return array{form_a:int,form_b:int}
	 */
	public static function get_archived_quiz_ids( $course_id = 0 ) {
		$course_id = self::resolve_course_id( $course_id );
		$out       = array(
			'form_a' => 0,
			'form_b' => 0,
		);

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return $out;
		}

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( 'legacy_form_a' === $type || 'form_a' === $type ) {
				$out['form_a'] = (int) $row->id;
			}
			if ( 'legacy_form_b' === $type || 'form_b' === $type ) {
				$out['form_b'] = (int) $row->id;
			}
		}

		return $out;
	}

	/**
	 * @param int $course_id Course ID.
	 * @return int[]
	 */
	public static function get_archived_resource_ids( $course_id = 0 ) {
		$course_id = self::resolve_course_id( $course_id );
		$ids       = array();

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return $ids;
		}

		foreach ( (array) CTA_Database::get_downloadable_resources( $course_id ) as $resource ) {
			if ( self::is_archived_resource( $resource ) || self::matches_legacy_form_resource( $resource ) ) {
				$ids[] = (int) $resource->id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param object $resource Resource row.
	 * @return bool
	 */
	public static function matches_legacy_form_resource( $resource ) {
		if ( ! $resource ) {
			return false;
		}

		$haystack = strtolower(
			str_replace(
				'\\',
				'/',
				(string) ( $resource->file_path ?? '' ) . ' ' .
				(string) ( $resource->file_url ?? '' ) . ' ' .
				(string) ( $resource->title ?? '' )
			)
		);

		if ( '' === trim( $haystack ) ) {
			return false;
		}

		foreach ( self::legacy_resource_path_markers() as $marker ) {
			if ( false !== strpos( $haystack, $marker ) ) {
				return true;
			}
		}

		if ( false === strpos( $haystack, 'comprehensive_simulation' ) && false === strpos( $haystack, 'simulation_form_' ) ) {
			return false;
		}

		return ( false !== strpos( $haystack, 'form_a' ) || false !== strpos( $haystack, 'form a' ) )
			|| ( false !== strpos( $haystack, 'form_b' ) || false !== strpos( $haystack, 'form b' ) );
	}

	/**
	 * @param int $course_id Course ID.
	 * @return array{form_a:int,form_b:int}
	 */
	private static function archive_legacy_quizzes( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$out       = array(
			'form_a' => 0,
			'form_b' => 0,
		);

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return $out;
		}

		$table = $wpdb->prefix . 'cta_quizzes';
		$sorts = array(
			'form_a'        => self::ARCHIVED_SORT_FORM_A,
			'form_b'        => self::ARCHIVED_SORT_FORM_B,
			'legacy_form_a' => self::ARCHIVED_SORT_FORM_A,
			'legacy_form_b' => self::ARCHIVED_SORT_FORM_B,
		);
		$type_map = self::legacy_quiz_type_map();

		foreach ( CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
			$type = sanitize_key( (string) ( $row->quiz_type ?? '' ) );
			if ( ! in_array( $type, array( 'form_a', 'form_b', 'legacy_form_a', 'legacy_form_b' ), true ) ) {
				continue;
			}

			$source_type = in_array( $type, array( 'legacy_form_a', 'legacy_form_b' ), true )
				? str_replace( 'legacy_', '', $type )
				: $type;
			$legacy_type = $type_map[ $source_type ] ?? $type;

			$quiz_id = (int) $row->id;
			$title   = self::prefix_archived_title( (string) ( $row->title ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'      => $title,
					'quiz_type'  => $legacy_type,
					'status'     => self::ARCHIVED_STATUS,
					'sort_order' => (int) ( $sorts[ $legacy_type ] ?? self::ARCHIVED_SORT_FORM_A ),
				),
				array( 'id' => $quiz_id ),
				array( '%s', '%s', '%s', '%d' ),
				array( '%d' )
			);

			$out[ $source_type ] = $quiz_id;
		}

		return $out;
	}

	/**
	 * @param int $course_id Course ID.
	 * @return array{resource_ids:int[]}
	 */
	private static function archive_legacy_resources( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$ids       = array();

		if ( ! $course_id || ! class_exists( 'CTA_Database' ) ) {
			return array( 'resource_ids' => $ids );
		}

		$table = $wpdb->prefix . 'cta_downloadable_resources';
		$order = self::ARCHIVED_RESOURCE_SORT;

		foreach ( (array) CTA_Database::get_downloadable_resources( $course_id ) as $resource ) {
			if ( ! self::matches_legacy_form_resource( $resource ) ) {
				continue;
			}

			$resource_id = (int) $resource->id;
			$title       = self::prefix_archived_title( (string) ( $resource->title ?? '' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'                  => $title,
					'order_index'            => $order,
					'unlock_after_quiz_type' => '',
				),
				array( 'id' => $resource_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			$ids[] = $resource_id;
			++$order;
		}

		return array( 'resource_ids' => $ids );
	}

	/**
	 * @param string $title Title.
	 * @return string
	 */
	private static function prefix_archived_title( $title ) {
		$title = trim( (string) $title );
		if ( self::title_is_archived( $title ) ) {
			return $title;
		}

		return self::ARCHIVED_TITLE_PREFIX . $title;
	}
}

}
