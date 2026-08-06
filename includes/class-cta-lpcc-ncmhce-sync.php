<?php
/**
 * CTA LPCC NCMHCE Exam Preparation — program, modules, materials, checkpoints, and Form A/B sync.
 *
 * Student materials are an allowlist under assets/course-materials/lpcc-ncmhce/.
 * Package trees 08_Unrecorded_*, 90_Admin_Restricted (Assessment_Synchronization,
 * Blueprints_and_Crosswalks, Official_Source_Reference, Program_Audit_*), and
 * 99_Archive_* are never published to students.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lpcc_Ncmhce_Sync
 */
if ( ! class_exists( 'CTA_Lpcc_Ncmhce_Sync' ) ) {

class CTA_Lpcc_Ncmhce_Sync {

	const SEED_OPTION   = 'cta_lpcc_ncmhce_seeded_1_0_155';
	const SLUG          = 'lpcc-ncmhce-exam-preparation';
	const TITLE         = 'CTA LPCC NCMHCE Exam Preparation Program';
	const PUBLIC_TITLE  = 'LPCC NCMHCE Exam Preparation';
	const LEGACY_SLUG   = 'lpcc-california-clinical-exam-preparation';
	const LEGACY_TITLE  = 'LPCC California Clinical Exam Preparation';
	const PRICE         = 249.00;
	const ACCESS_MONTHS = 6;
	const MATERIALS_REL = 'assets/course-materials/lpcc-ncmhce/';
	const AUDIO_TRACK_COUNT = 8;
	/** Authoritative combined runtime for the 8 learner-facing audio tracks. */
	const COMBINED_AUDIO_RUNTIME = '48 minutes 49 seconds';
	/**
	 * Public LMS/website may advertise the 8 audio-review tracks only after Prompt 11 testing PASS.
	 * Evidence: docs/LPCC_Audio_Playback_Verification.md (desktop + mobile all PASS).
	 */
	const AUDIO_PUBLIC_ADVERTISING_APPROVED = true;

	/**
	 * Find the LPCC NCMHCE course by new/legacy slug or title.
	 *
	 * @return object|null
	 */
	public static function find_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		$candidates = array(
			array( 'slug', self::SLUG ),
			array( 'slug', self::LEGACY_SLUG ),
			array( 'title', self::TITLE ),
			array( 'title', self::LEGACY_TITLE ),
		);

		foreach ( $candidates as $pair ) {
			$column = $pair[0];
			$value  = $pair[1];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$column} = %s ORDER BY id ASC LIMIT 1",
					$value
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Create or update the exam_prep program.
	 *
	 * Remains draft until the full student testing checklist is verified (do not publish early).
	 *
	 * @return int Course ID or 0.
	 */
	public static function ensure_program() {
		global $wpdb;

		$table  = $wpdb->prefix . 'cta_courses';
		$course = self::find_course();

		$description = self::get_program_description_html();
		$objectives  = wp_json_encode( self::get_learning_objectives() );
		$meta        = wp_json_encode( self::get_syllabus_meta() );

		$fields = array(
			'title'                => self::TITLE,
			'slug'                 => self::SLUG,
			'description'          => $description,
			'ce_hours'             => 0,
			'price'                => (float) self::PRICE,
			'category'             => 'Exam Preparation',
			'learning_objectives'  => $objectives,
			'syllabus_meta'        => $meta,
			'status'               => 'draft',
			'product_type'         => 'exam_prep',
			'access_period_months' => (int) self::ACCESS_MONTHS,
			'awards_ce_hours'      => 0,
			'has_ce_certificate'   => 0,
		);

		$formats = array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' );

		if ( $course ) {
			$course_id = (int) $course->id;

			// Migrate legacy slug → new when updating an existing row.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				$fields,
				array( 'id' => $course_id ),
				$formats,
				array( '%d' )
			);

			return $course_id;
		}

		$fields['modules_count'] = 0;
		$formats[]               = '%d';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( $table, $fields, $formats );

		if ( ! $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Upsert the 12 workbook modules (order_index 0–11). Match existing by "Workbook N:" prefix.
	 *
	 * @param int $course_id Course ID.
	 * @return array{created:int,updated:int,modules:array}
	 */
	public static function sync_modules( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$created   = 0;
		$updated   = 0;
		$report    = array();

		if ( ! $course_id ) {
			return array(
				'created' => 0,
				'updated' => 0,
				'modules' => array(),
			);
		}

		$table    = $wpdb->prefix . 'cta_course_modules';
		$defs     = self::get_module_definitions();
		$existing = class_exists( 'CTA_Database' )
			? CTA_Database::get_course_modules( $course_id, true )
			: array();

		$by_prefix = array();
		foreach ( (array) $existing as $row ) {
			$title = (string) ( $row->title ?? '' );
			if ( preg_match( '/^Workbook\s+(\d+)\s*:/i', $title, $m ) ) {
				$n = (int) $m[1];
				if ( $n >= 1 && $n <= 12 && ! isset( $by_prefix[ $n ] ) ) {
					$by_prefix[ $n ] = $row;
				}
			}
		}

		$desc = 'Complete the student workbook and paired 17-question practice bank for this unit.';

		foreach ( $defs as $index => $def ) {
			$wb_num    = $index + 1;
			$title     = sanitize_text_field( (string) $def['title'] );
			$order     = (int) $index;
			$module_id = 0;

			if ( isset( $by_prefix[ $wb_num ] ) ) {
				$module_id = (int) $by_prefix[ $wb_num ]->id;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array(
						'title'       => $title,
						'description' => $desc,
						'video_url'   => '',
						'order_index' => $order,
						'is_locked'   => 0,
					),
					array( 'id' => $module_id ),
					array( '%s', '%s', '%s', '%d', '%d' ),
					array( '%d' )
				);
				++$updated;
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$ok = $wpdb->insert(
					$table,
					array(
						'course_id'     => $course_id,
						'title'         => $title,
						'description'   => $desc,
						'video_url'     => '',
						'duration_mins' => 0,
						'order_index'   => $order,
						'is_locked'     => 0,
					),
					array( '%d', '%s', '%s', '%s', '%d', '%d', '%d' )
				);
				if ( $ok ) {
					$module_id = (int) $wpdb->insert_id;
					++$created;
				}
			}

			$report[] = array(
				'id'    => $module_id,
				'title' => $title,
				'order' => $order,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'modules_count' => count( $defs ) ),
			array( 'id' => $course_id ),
			array( '%d' ),
			array( '%d' )
		);

		return array(
			'created' => $created,
			'updated' => $updated,
			'modules' => $report,
		);
	}

	/**
	 * Attach all student materials (workbooks, banks, checkpoints, simulations, study tools).
	 * Idempotent by friendly title. Practice-bank, checkpoint, and Form answer keys gated via unlock_after_quiz_type.
	 *
	 * @param int $course_id Course ID.
	 * @return array{attached:int,updated:int,skipped:int,missing:array}
	 */
	public static function sync_materials( $course_id ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$attached  = 0;
		$updated   = 0;
		$skipped   = 0;
		$missing   = array();

		if ( ! $course_id ) {
			return compact( 'attached', 'updated', 'skipped', 'missing' );
		}

		self::ensure_resource_unlock_column();

		if ( ! class_exists( 'CTA_Course_Materials' ) ) {
			return array(
				'attached' => 0,
				'updated'  => 0,
				'skipped'  => 0,
				'missing'  => array( 'CTA_Course_Materials missing' ),
			);
		}

		$modules     = class_exists( 'CTA_Database' )
			? CTA_Database::get_course_modules( $course_id )
			: array();
		$module_by_n = array();
		foreach ( (array) $modules as $mod ) {
			if ( preg_match( '/^Workbook\s+(\d+)\s*:/i', (string) $mod->title, $m ) ) {
				$module_by_n[ (int) $m[1] ] = (int) $mod->id;
			}
		}

		$order_index = 0;
		foreach ( self::get_material_map() as $item ) {
			$title       = sanitize_text_field( (string) $item['title'] );
			$rel         = ltrim( str_replace( '\\', '/', (string) $item['file'] ), '/' );
			$source      = CTA_PLUGIN_DIR . self::MATERIALS_REL . $rel;
			$module_id   = 0;
			$is_practice = ! empty( $item['is_practice_test'] ) ? 1 : 0;
			// Access Correction Notice: never persist material unlock gates for Exam Prep.
			$unlock      = '';

			// Never attach 90_Admin_Restricted / DO_NOT_PUBLISH / archive package trees.
			if ( class_exists( 'CTA_Course_Materials' )
				&& CTA_Course_Materials::is_admin_restricted_source_path( $source . ' ' . $rel ) ) {
				$missing[] = $rel . ' (admin_restricted)';
				++$skipped;
				++$order_index;
				continue;
			}

			if ( ! empty( $item['workbook_num'] ) ) {
				$wn        = (int) $item['workbook_num'];
				$module_id = isset( $module_by_n[ $wn ] ) ? (int) $module_by_n[ $wn ] : 0;
			}

			if ( ! is_readable( $source ) ) {
				$missing[] = $rel;
				++$skipped;
				++$order_index;
				continue;
			}

			$existing_id = self::find_resource_id( $course_id, $title, $rel );

			if ( $existing_id ) {
				$update = array(
					'title'                  => $title,
					'module_id'              => $module_id,
					'order_index'            => $order_index,
					'is_practice_test'       => $is_practice,
					'unlock_after_quiz_type' => $unlock,
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->prefix . 'cta_downloadable_resources',
					$update,
					array( 'id' => $existing_id ),
					array( '%s', '%d', '%d', '%d', '%s' ),
					array( '%d' )
				);
				++$updated;
				++$order_index;
				continue;
			}

			$imported = CTA_Course_Materials::import_local_file_to_protected( $source, $course_id );
			if ( is_wp_error( $imported ) ) {
				$missing[] = $rel . ' (' . $imported->get_error_message() . ')';
				++$skipped;
				++$order_index;
				continue;
			}

			$row = array(
				'course_id'              => $course_id,
				'module_id'              => $module_id,
				'attachment_id'          => 0,
				'title'                  => $title,
				'file_url'               => $imported['file_url'],
				'file_path'              => $imported['relative_path'],
				'file_type'              => $imported['file_type'],
				'order_index'            => $order_index,
				'is_practice_test'       => $is_practice,
				'unlock_after_quiz_type' => $unlock,
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ok = $wpdb->insert(
				$wpdb->prefix . 'cta_downloadable_resources',
				$row,
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
			);

			if ( $ok ) {
				++$attached;
			} else {
				++$skipped;
				$missing[] = $rel . ' (insert failed)';
			}

			++$order_index;
		}

		return compact( 'attached', 'updated', 'skipped', 'missing' );
	}

	/**
	 * Ensure workbook banks, checkpoint 1–3, and Form A/B quizzes with expected counts.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function sync_assessments( $course_id ) {
		$course_id = absint( $course_id );

		$empty = array(
			'ok'             => false,
			'checkpoint_1'   => 0,
			'checkpoint_2'   => 0,
			'checkpoint_3'   => 0,
			'form_a'         => 0,
			'form_b'         => 0,
			'questions_c1'   => 0,
			'questions_c2'   => 0,
			'questions_c3'   => 0,
			'questions_a'    => 0,
			'questions_b'    => 0,
			'message'        => 'invalid_course',
		);

		for ( $n = 1; $n <= 12; $n++ ) {
			$empty[ 'wb' . $n . '_bank' ]           = 0;
			$empty[ 'questions_wb' . $n . '_bank' ] = 0;
		}

		if ( ! $course_id ) {
			return $empty;
		}

		$defs = array();

		for ( $n = 1; $n <= 12; $n++ ) {
			$defs[] = array(
				'quiz_type' => 'wb' . $n . '_bank',
				'title'     => sprintf( 'Workbook %d — 17-Question Practice Bank', $n ),
				'sort'      => $n,
				'time'      => 40,
				'file'      => 'lpcc-ncmhce-wb' . $n . '-bank.php',
				'expect'    => 17,
				'key'       => 'wb' . $n . '_bank',
				'qkey'      => 'questions_wb' . $n . '_bank',
			);
		}

		$defs[] = array(
			'quiz_type' => 'checkpoint_1',
			'title'     => 'Checkpoint 1 — Cumulative Assessment (39 Questions)',
			'sort'      => 13,
			'time'      => 90,
			'file'      => 'lpcc-ncmhce-checkpoint-1.php',
			'expect'    => 39,
			'key'       => 'checkpoint_1',
			'qkey'      => 'questions_c1',
		);
		$defs[] = array(
			'quiz_type' => 'checkpoint_2',
			'title'     => 'Checkpoint 2 — Cumulative Assessment (52 Questions)',
			'sort'      => 14,
			'time'      => 90,
			'file'      => 'lpcc-ncmhce-checkpoint-2.php',
			'expect'    => 52,
			'key'       => 'checkpoint_2',
			'qkey'      => 'questions_c2',
		);
		$defs[] = array(
			'quiz_type' => 'checkpoint_3',
			'title'     => 'Checkpoint 3 — Cumulative Assessment (65 Questions)',
			'sort'      => 15,
			'time'      => 90,
			'file'      => 'lpcc-ncmhce-checkpoint-3.php',
			'expect'    => 65,
			'key'       => 'checkpoint_3',
			'qkey'      => 'questions_c3',
		);
		$defs[] = array(
			'quiz_type' => 'form_a',
			'title'     => 'Form A — Full-Length Simulation (143 Questions)',
			'sort'      => 20,
			'time'      => 240,
			'file'      => 'lpcc-ncmhce-form-a.php',
			'expect'    => 143,
			'key'       => 'form_a',
			'qkey'      => 'questions_a',
		);
		$defs[] = array(
			'quiz_type' => 'form_b',
			'title'     => 'Form B — Full-Length Simulation (143 Questions)',
			'sort'      => 30,
			'time'      => 240,
			'file'      => 'lpcc-ncmhce-form-b.php',
			'expect'    => 143,
			'key'       => 'form_b',
			'qkey'      => 'questions_b',
		);

		$result = $empty;
		$result['message'] = '';

		foreach ( $defs as $def ) {
			$questions = self::load_seed_questions( $def['file'] );
			$count     = count( $questions );
			$result[ $def['qkey'] ] = $count;

			if ( $def['expect'] !== $count ) {
				$result['ok']      = false;
				$result['message'] = sprintf(
					'invalid_question_bank_count:%s expected %d got %d',
					$def['quiz_type'],
					$def['expect'],
					$count
				);
				return $result;
			}
		}

		foreach ( $defs as $def ) {
			$questions = self::load_seed_questions( $def['file'] );
			$quiz_id   = self::replace_form_quiz(
				$course_id,
				$def['quiz_type'],
				$def['title'],
				$def['sort'],
				$questions,
				(int) $def['time']
			);
			$result[ $def['key'] ]  = $quiz_id;
			$result[ $def['qkey'] ] = $def['expect'];

			if ( ! $quiz_id ) {
				$result['ok']      = false;
				$result['message'] = 'quiz_write_failed:' . $def['quiz_type'];
				return $result;
			}
		}

		$result['ok']      = true;
		$result['message'] = 'synced';

		return $result;
	}

	/**
	 * Orchestrate full program sync.
	 *
	 * @param bool $force Re-run even if already seeded at this version.
	 * @return array{ok:bool,course_id:int,message:string,counts:array}
	 */
	public static function sync( $force = false ) {
		if ( ! $force && get_option( self::SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'message'   => 'already_seeded',
				'counts'    => array(),
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		self::ensure_resource_unlock_column();

		$course_id = self::ensure_program();
		if ( ! $course_id ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'message'   => 'ensure_program_failed',
				'counts'    => array(),
			);
		}

		$modules     = self::sync_modules( $course_id );
		$materials   = self::sync_materials( $course_id );
		$assessments = self::sync_assessments( $course_id );

		$ok = ! empty( $assessments['ok'] );

		$counts = array(
			'modules_created'      => (int) ( $modules['created'] ?? 0 ),
			'modules_updated'      => (int) ( $modules['updated'] ?? 0 ),
			'materials_attached'   => (int) ( $materials['attached'] ?? 0 ),
			'materials_updated'    => (int) ( $materials['updated'] ?? 0 ),
			'materials_missing'    => count( $materials['missing'] ?? array() ),
			'checkpoint_1_quiz_id' => (int) ( $assessments['checkpoint_1'] ?? 0 ),
			'checkpoint_2_quiz_id' => (int) ( $assessments['checkpoint_2'] ?? 0 ),
			'checkpoint_3_quiz_id' => (int) ( $assessments['checkpoint_3'] ?? 0 ),
			'form_a_quiz_id'       => (int) ( $assessments['form_a'] ?? 0 ),
			'form_b_quiz_id'       => (int) ( $assessments['form_b'] ?? 0 ),
			'questions_c1'         => (int) ( $assessments['questions_c1'] ?? 0 ),
			'questions_c2'         => (int) ( $assessments['questions_c2'] ?? 0 ),
			'questions_c3'         => (int) ( $assessments['questions_c3'] ?? 0 ),
			'questions_a'          => (int) ( $assessments['questions_a'] ?? 0 ),
			'questions_b'          => (int) ( $assessments['questions_b'] ?? 0 ),
		);

		for ( $n = 1; $n <= 12; $n++ ) {
			$counts[ 'wb' . $n . '_bank_quiz_id' ] = (int) ( $assessments[ 'wb' . $n . '_bank' ] ?? 0 );
			$counts[ 'questions_wb' . $n ]         = (int) ( $assessments[ 'questions_wb' . $n . '_bank' ] ?? 0 );
		}

		if ( $ok ) {
			update_option(
				self::SEED_OPTION,
				array(
					'at'        => current_time( 'mysql' ),
					'course_id' => $course_id,
					'counts'    => $counts,
				),
				false
			);
		}

		return array(
			'ok'        => $ok,
			'course_id' => $course_id,
			'message'   => $ok ? 'synced' : (string) ( $assessments['message'] ?? 'sync_failed' ),
			'counts'    => $counts,
		);
	}

	/**
	 * Whether the user has any completed attempt for a quiz of the given type on the course.
	 *
	 * @param int    $user_id   WordPress user ID.
	 * @param int    $course_id Course ID.
	 * @param string $quiz_type Quiz type (e.g. form_a, checkpoint_1).
	 * @return bool
	 */
	public static function user_has_completed_quiz_type( $user_id, $course_id, $quiz_type ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$course_id = absint( $course_id );
		$quiz_type = sanitize_text_field( (string) $quiz_type );

		if ( ! $user_id || ! $course_id || '' === $quiz_type ) {
			return false;
		}

		$quizzes  = $wpdb->prefix . 'cta_quizzes';
		$attempts = $wpdb->prefix . 'cta_quiz_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT a.id
				FROM {$attempts} a
				INNER JOIN {$quizzes} q ON q.id = a.quiz_id
				WHERE a.user_id = %d
					AND a.course_id = %d
					AND q.course_id = %d
					AND q.quiz_type = %s
					AND a.completed_at IS NOT NULL
				LIMIT 1",
				$user_id,
				$course_id,
				$course_id,
				$quiz_type
			)
		);

		return $found > 0;
	}

	/* -------------------------------------------------------------------------
	 * Private helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Whether public LMS/website copy may advertise the eight audio-review tracks.
	 * Gated until Prompt 11 desktop/mobile playback testing is documented PASS.
	 *
	 * @return bool
	 */
	public static function audio_public_advertising_approved() {
		return (bool) self::AUDIO_PUBLIC_ADVERTISING_APPROVED;
	}

	/**
	 * Short marketing phrase for audio tracks (empty until advertising is approved).
	 *
	 * @return string
	 */
	public static function get_audio_public_phrase() {
		if ( ! self::audio_public_advertising_approved() ) {
			return '';
		}
		return sprintf(
			/* translators: 1: track count, 2: combined runtime */
			__( '%1$d screen-free audio-review tracks (combined runtime %2$s)', 'cta-lms' ),
			(int) self::AUDIO_TRACK_COUNT,
			self::COMBINED_AUDIO_RUNTIME
		);
	}

	/**
	 * Program description HTML for LMS and website.
	 * Audio-review advertising is included only after documented playback testing PASS.
	 *
	 * @return string
	 */
	private static function get_program_description_html() {
		$audio_li   = '';
		$audio_note = '<p><strong>Written program complete.</strong></p>';

		if ( self::audio_public_advertising_approved() ) {
			$audio_li = '<li>Eight screen-free audio-review tracks (combined runtime '
				. self::COMBINED_AUDIO_RUNTIME
				. ') for offline or LMS playback</li>';
			$audio_note = '<p><strong>Written program complete.</strong> Eight recorded audio-review tracks are included (combined runtime '
				. self::COMBINED_AUDIO_RUNTIME
				. ').</p>';
		}

		$html = '
<p>LPCC NCMHCE Exam Preparation is a complete self-paced system for LPCC candidates and other eligible examinees preparing for the National Clinical Mental Health Counseling Examination (NCMHCE).</p>
<p>The program teaches candidates to apply clinical counselor reasoning across NCMHCE-style case studies: intake and assessment, diagnosis and differential reasoning, crisis and level-of-care decisions, treatment planning, evidence-informed interventions, multicultural and contextual practice, and California legal and ethical judgment when several answers appear plausible.</p>
<h3>What Is Included</h3>
<ul>
<li>12 editable and print-optimized LPCC student workbooks</li>
<li>12 paired 17-question practice bank pairs (candidate forms with complete answer rationales)</li>
<li>3 cumulative checkpoints (39-, 52-, and 65-question assessments) with answer rationales</li>
<li>Comprehensive Simulation Form A — 143 questions</li>
<li>Form A remediation workbook (recommended study sequence before Form B)</li>
<li>Comprehensive Simulation Form B — 143 questions</li>
<li>Answer keys and detailed rationales for both simulations</li>
' . $audio_li . '
<li>Flashcard collection, quick-reference and rapid-review library, readiness tracker, Student FAQ, and Start-Here roadmap with 10-, 14-, and 18-week study schedules</li>
</ul>
' . $audio_note . '
<h3>Important Notices</h3>
<ul>
<li><strong>Exam Preparation Only — No CE Credit.</strong> This program does not provide continuing education hours or a CE certificate.</li>
<li>This program prepares candidates for the NCMHCE for LPCC licensure pathways. It is not an AMFTRB National MFT product and is not an ASWB Clinical Social Work examination product.</li>
<li>CTA is an independent educational resource and is not affiliated with or endorsed by NBCC, Pearson VUE, or any state licensing board.</li>
<li>Participation supports examination readiness but does not guarantee passage or determine eligibility. Candidates should follow the blueprint and rules that apply to their actual testing date and jurisdiction.</li>
</ul>';

		return wp_kses_post( $html );
	}

	/**
	 * Learning objectives for NCMHCE case-study counselor reasoning.
	 *
	 * @return string[]
	 */
	private static function get_learning_objectives() {
		return array(
			'Apply NCMHCE case-study counselor reasoning across intake, assessment, diagnosis, planning, and intervention decisions.',
			'Identify FIRST, NEXT, BEST, MOST, INITIAL, and PRIMARY action cues on clinical counseling items.',
			'Prioritize crisis, trauma, abuse, violence, and level-of-care responses before non-urgent clinical actions.',
			'Differentiate mood, anxiety, trauma, OCD, psychotic, substance use, personality, and co-occurring presentations within counselor scope.',
			'Select evidence-informed interventions while maintaining alliance, multicultural competence, and California legal-ethical standards.',
			'Use complete rationales and checkpoint feedback to repair reasoning rather than memorize answer choices.',
			'Build pacing and stamina under 143-question full-length simulation conditions.',
		);
	}

	/**
	 * Syllabus / SEO meta for the sales and LMS pages.
	 *
	 * @return array
	 */
	private static function get_syllabus_meta() {
		$audio_phrase = self::get_audio_public_phrase();
		$short        = 'Twelve LPCC workbooks, twelve practice bank pairs, three cumulative checkpoints, Form A and Form B 143-question simulations, Form A remediation, flashcards, quick references, and study schedules for NCMHCE exam preparation.';
		$meta_desc    = 'Prepare for the NCMHCE with 12 LPCC workbooks, practice banks, three cumulative checkpoints, and two 143-question simulations. Exam preparation only — no CE credit.';

		if ( '' !== $audio_phrase ) {
			$short     = 'Twelve LPCC workbooks, twelve practice bank pairs, three cumulative checkpoints, Form A and Form B 143-question simulations, Form A remediation, eight audio-review tracks (combined runtime '
				. self::COMBINED_AUDIO_RUNTIME
				. '), flashcards, quick references, and study schedules for NCMHCE exam preparation.';
			$meta_desc = 'Prepare for the NCMHCE with 12 LPCC workbooks, practice banks, three cumulative checkpoints, two 143-question simulations, and eight audio-review tracks (combined runtime '
				. self::COMBINED_AUDIO_RUNTIME
				. '). Exam preparation only — no CE credit.';
		}

		return array(
			'public_title'           => self::PUBLIC_TITLE,
			'short_description'      => $short,
			'course_classification'  => 'Exam Preparation Only — No CE Credit',
			'instructional_method'   => 'Self-paced asynchronous',
			'target_audience'        => 'LPCC candidates and other eligible NCMHCE examinees',
			'seo_title'              => 'LPCC NCMHCE Exam Prep | CTA',
			'meta_description'       => $meta_desc,
			'image_alt'              => 'Clinical Training and Supervision Academy LPCC NCMHCE Exam Preparation graphic',
			'primary_cta'            => 'Begin Your Clinical Exam Preparation',
			'page_badge'             => 'Exam Preparation Only — No CE Credit',
			'educational_notice'     => 'Exam Preparation Only — No CE Credit. This program does not award CE hours or a CE certificate. CTA is not affiliated with or endorsed by NBCC, Pearson VUE, or any state licensing board. This is an NCMHCE preparation program for LPCC candidates — not an AMFTRB or ASWB product.',
			'launch_status'          => 'draft_pending_testing',
			'launch_pending_testing' => true,
			'development_draft'      => true,
			'audio_tracks'           => self::AUDIO_TRACK_COUNT,
			'audio_combined_runtime' => self::audio_public_advertising_approved() ? self::COMBINED_AUDIO_RUNTIME : '',
			'audio_public_approved'  => self::audio_public_advertising_approved(),
			'open_access_exam_prep'  => true,
		);
	}

	/**
	 * Twelve workbook module titles (order_index 0–11).
	 *
	 * @return array<int,array{title:string}>
	 */
	private static function get_module_definitions() {
		return array(
			array( 'title' => 'Workbook 1: NCMHCE Case Study Strategy and Applied Clinical Counselor Reasoning' ),
			array( 'title' => 'Workbook 2: Professional Identity, California Scope, Competence, and Counselor Self-Awareness' ),
			array( 'title' => 'Workbook 3: Intake, Informed Consent, Clinical Interviewing, MSE, and Biopsychosocial-Cultural Assessment' ),
			array( 'title' => 'Workbook 4: Crisis, Trauma, Abuse, Violence, and Level-of-Care Decisions' ),
			array( 'title' => 'Workbook 5: Diagnosis I — Mood, Anxiety, Trauma, OCD, and Psychotic Disorders' ),
			array( 'title' => 'Workbook 6: Diagnosis II — Substance Use, Personality, Neurodevelopmental, Eating, Sleep, Somatic, Medical, and Co-Occurring' ),
			array( 'title' => 'Workbook 7: Case Conceptualization, Treatment Planning, Measurement, Progress, and Termination' ),
			array( 'title' => 'Workbook 8: Counseling Theories, Therapeutic Alliance, and Core Counseling Skills' ),
			array( 'title' => 'Workbook 9: Evidence-Based and Evidence-Informed Interventions' ),
			array( 'title' => 'Workbook 10: Multicultural, Developmental, Career, Spiritual, Disability, and Context-Responsive Counseling' ),
			array( 'title' => 'Workbook 11: Individual, Group, Couple, Family, Referral, Continuity, and Interdisciplinary Collaboration' ),
			array( 'title' => 'Workbook 12: California Legal, Ethical, Documentation, Technology, and Professional Practice' ),
		);
	}

	/**
	 * Material map: relative file under MATERIALS_REL + learner-facing title + flags.
	 *
	 * @return array<int,array>
	 */
	private static function get_material_map() {
		$items = array();

		$workbooks = array(
			1  => array(
				'file'  => 'workbooks/CTA_LPCC_WB1_NCMHCE_Case_Study_Strategy_and_Applied_Clinical_Counselor_Reasoning_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 1 — NCMHCE Case Study Strategy and Applied Clinical Counselor Reasoning (Student Workbook)',
			),
			2  => array(
				'file'  => 'workbooks/CTA_LPCC_WB2_Professional_Identity_California_Scope_Competence_and_Counselor_Self_Awareness_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 2 — Professional Identity, California Scope, Competence, and Counselor Self-Awareness (Student Workbook)',
			),
			3  => array(
				'file'  => 'workbooks/CTA_LPCC_WB3_Intake_Informed_Consent_Clinical_Interviewing_MSE_and_Biopsychosocial_Cultural_Assessment_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 3 — Intake, Informed Consent, Clinical Interviewing, MSE, and Biopsychosocial-Cultural Assessment (Student Workbook)',
			),
			4  => array(
				'file'  => 'workbooks/CTA_LPCC_WB4_Crisis_Trauma_Abuse_Violence_and_Level_of_Care_Decisions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 4 — Crisis, Trauma, Abuse, Violence, and Level-of-Care Decisions (Student Workbook)',
			),
			5  => array(
				'file'  => 'workbooks/CTA_LPCC_WB5_Diagnosis_and_Differential_Diagnosis_I_Mood_Anxiety_Trauma_OCD_and_Psychotic_Disorders_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 5 — Diagnosis I — Mood, Anxiety, Trauma, OCD, and Psychotic Disorders (Student Workbook)',
			),
			6  => array(
				'file'  => 'workbooks/CTA_LPCC_WB6_Diagnosis_II_Substance_Personality_Neurodev_CoOccurring_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 6 — Diagnosis II — Substance Use, Personality, Neurodevelopmental, Eating, Sleep, Somatic, Medical, and Co-Occurring (Student Workbook)',
			),
			7  => array(
				'file'  => 'workbooks/CTA_LPCC_WB7_Case_Conceptualization_Treatment_Planning_Measurement_Progress_and_Termination_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 7 — Case Conceptualization, Treatment Planning, Measurement, Progress, and Termination (Student Workbook)',
			),
			8  => array(
				'file'  => 'workbooks/CTA_LPCC_WB8_Counseling_Theories_Therapeutic_Alliance_and_Core_Counseling_Skills_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 8 — Counseling Theories, Therapeutic Alliance, and Core Counseling Skills (Student Workbook)',
			),
			9  => array(
				'file'  => 'workbooks/CTA_LPCC_WB9_Evidence_Based_and_Evidence_Informed_Interventions_Across_Common_Presenting_Concerns_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 9 — Evidence-Based and Evidence-Informed Interventions (Student Workbook)',
			),
			10 => array(
				'file'  => 'workbooks/CTA_LPCC_WB10_Multicultural_Developmental_Career_Spiritual_Disability_and_Context_Responsive_Counseling_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 10 — Multicultural, Developmental, Career, Spiritual, Disability, and Context-Responsive Counseling (Student Workbook)',
			),
			11 => array(
				'file'  => 'workbooks/CTA_LPCC_WB11_Individual_Group_Couple_Family_Referral_Continuity_and_Interdisciplinary_Collaboration_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 11 — Individual, Group, Couple, Family, Referral, Continuity, and Interdisciplinary Collaboration (Student Workbook)',
			),
			12 => array(
				'file'  => 'workbooks/CTA_LPCC_WB12_California_Legal_Ethical_Documentation_Technology_and_Professional_Practice_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 12 — California Legal, Ethical, Documentation, Technology, and Professional Practice (Student Workbook)',
			),
		);

		foreach ( $workbooks as $n => $wb ) {
			$items[] = array(
				'file'         => $wb['file'],
				'title'        => $wb['title'],
				'workbook_num' => $n,
			);
			// Access Correction v1.0: no delayed rationale / progression locks for LPCC Exam Prep.
			$items[] = array(
				'file'             => 'practice-banks/CTA_LPCC_WB' . $n . '_17_Question_Practice_Bank_Candidate_Form_v1.0.docx',
				'title'            => 'Workbook ' . $n . ' — 17-Question Practice Bank (Candidate Form)',
				'workbook_num'     => $n,
				'is_practice_test' => 1,
			);
			$items[] = array(
				'file'         => 'practice-banks/CTA_LPCC_WB' . $n . '_17_Question_Practice_Bank_Answer_Rationales_v1.0.docx',
				'title'        => 'Workbook ' . $n . ' — 17-Question Practice Bank (Answer Rationales)',
				'workbook_num' => $n,
			);
		}

		$checkpoints = array(
			1 => array(
				'cand' => 'checkpoints/CTA_LPCC_Checkpoint_1_WB1-4_39-Question_Cumulative_Assessment_Candidate_Form_v1.0.docx',
				'rat'  => 'checkpoints/CTA_LPCC_Checkpoint_1_WB1-4_39-Question_Cumulative_Assessment_Answer_Rationales_v1.0.docx',
				'q'    => 39,
				'scope'=> 'WB1–4',
			),
			2 => array(
				'cand' => 'checkpoints/CTA_LPCC_Checkpoint_2_WB1-8_52-Question_Cumulative_Assessment_Candidate_Form_v1.0.docx',
				'rat'  => 'checkpoints/CTA_LPCC_Checkpoint_2_WB1-8_52-Question_Cumulative_Assessment_Answer_Rationales_v1.0.docx',
				'q'    => 52,
				'scope'=> 'WB1–8',
			),
			3 => array(
				'cand' => 'checkpoints/CTA_LPCC_Checkpoint_3_WB1-12_65-Question_Cumulative_Assessment_Candidate_Form_v1.0.docx',
				'rat'  => 'checkpoints/CTA_LPCC_Checkpoint_3_WB1-12_65-Question_Cumulative_Assessment_Answer_Rationales_v1.0.docx',
				'q'    => 65,
				'scope'=> 'WB1–12',
			),
		);

		foreach ( $checkpoints as $n => $cp ) {
			$items[] = array(
				'file'             => $cp['cand'],
				'title'            => 'Checkpoint ' . $n . ' — ' . $cp['q'] . '-Question Cumulative Assessment (' . $cp['scope'] . ') Candidate Form',
				'is_practice_test' => 1,
			);
			$items[] = array(
				'file'  => $cp['rat'],
				'title' => 'Checkpoint ' . $n . ' — Answer Rationales',
			);
		}

		$items[] = array(
			'file'             => 'simulations/CTA_LPCC_Comprehensive_Simulation_Form_A_143_Question_Candidate_Exam_v1.0.docx',
			'title'            => 'Form A — 143-Question Comprehensive Simulation (Candidate Exam)',
			'is_practice_test' => 1,
		);
		$items[] = array(
			'file'  => 'simulations/CTA_LPCC_Comprehensive_Simulation_Form_A_143_Question_Answer_Rationales_v1.0.docx',
			'title' => 'Form A — Answer Rationales',
		);
		$items[] = array(
			'file'  => 'simulations/CTA_LPCC_Form_A_Remediation_Workbook_v1.0.docx',
			'title' => 'Form A — Remediation Workbook (recommended before Form B)',
		);
		$items[] = array(
			'file'             => 'simulations/CTA_LPCC_Comprehensive_Simulation_Form_B_143_Question_Candidate_Exam_v1.0.docx',
			'title'            => 'Form B — 143-Question Comprehensive Simulation (Candidate Exam)',
			'is_practice_test' => 1,
		);
		$items[] = array(
			'file'  => 'simulations/CTA_LPCC_Comprehensive_Simulation_Form_B_143_Question_Answer_Rationales_v1.0.docx',
			'title' => 'Form B — Answer Rationales',
		);

		$audio_tracks = self::get_audio_placement_map();

		foreach ( $audio_tracks as $track ) {
			$items[] = array(
				'file'  => $track['file'],
				'title' => $track['title'],
				// Open from enrollment — never set unlock_after_quiz_type.
				'is_audio' => 1,
			);
		}

		$items[] = array(
			'file'  => 'study-tools/CTA_LPCC_Clinical_Exam_Preparation_Flashcard_Collection_v1.0.docx',
			'title' => 'Clinical Exam Preparation Flashcard Collection',
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LPCC_Readiness_Self_Assessment_and_Progress_Tracker_v1.0.docx',
			'title' => 'Readiness Self-Assessment and Progress Tracker',
		);
		$items[] = array(
			'file'  => 'quick-references/CTA_LPCC_Quick_Reference_and_Rapid_Review_Library_v1.0.docx',
			'title' => 'Quick Reference and Rapid Review Library',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LPCC_Student_Start_Here_Roadmap_and_10_14_18_Week_Study_Schedules_v1.0.docx',
			'title' => 'Student Start-Here Roadmap and 10-, 14-, and 18-Week Study Schedules',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LPCC_Student_FAQ_and_Self_Service_Support_Guide_v1.0.docx',
			'title' => 'Student FAQ and Self-Service Support Guide',
		);

		return $items;
	}

	/**
	 * Eight learner-facing audio tracks (exact package filenames, titles, runtimes).
	 * Open from enrollment — no unlock gates. Admin Recording Guide / Completion Record stay out.
	 *
	 * @return array<int,array{file:string,title:string,runtime:string,file_needle:string}>
	 */
	public static function get_audio_placement_map() {
		return array(
			1 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_01_NCMHCE_Case_Reasoning_Sections_Qualifiers_and_Evidence_v1.0.mp3',
				'title'       => 'NCMHCE Case Reasoning: Sections, Qualifiers, and Evidence',
				'runtime'     => '3:58',
				'file_needle' => 'Audio_Track_01_',
			),
			2 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_02_Professional_Identity_Intake_Assessment_and_Differential_Reasoning_v1.0.mp3',
				'title'       => 'Professional Identity, Intake, Assessment, and Differential Reasoning',
				'runtime'     => '10:37',
				'file_needle' => 'Audio_Track_02_',
			),
			3 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_03_Crisis_Trauma_Abuse_Violence_and_Level_of_Care_Sequencing_v1.0.mp3',
				'title'       => 'Crisis, Trauma, Abuse, Violence, and Level-of-Care Sequencing',
				'runtime'     => '4:13',
				'file_needle' => 'Audio_Track_03_',
			),
			4 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_04_Conceptualization_Planning_Measurement_Progress_and_Termination_v1.0.mp3',
				'title'       => 'Conceptualization, Planning, Measurement, Progress, and Termination',
				'runtime'     => '4:05',
				'file_needle' => 'Audio_Track_04_',
			),
			5 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_05_Counseling_Theories_Therapeutic_Alliance_and_Core_Skills_v1.0.mp3',
				'title'       => 'Counseling Theories, Therapeutic Alliance, and Core Skills',
				'runtime'     => '4:09',
				'file_needle' => 'Audio_Track_05_',
			),
			6 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_06_Evidence_Informed_Interventions_and_Context_Responsive_Care_v1.0.mp3',
				'title'       => 'Evidence-Informed Interventions and Context-Responsive Care',
				'runtime'     => '7:30',
				'file_needle' => 'Audio_Track_06_',
			),
			7 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_07_Modality_Referral_Collaboration_and_California_Professional_Practice_v1.0.mp3',
				'title'       => 'Modality, Referral, Collaboration, and California Professional Practice',
				'runtime'     => '7:26',
				'file_needle' => 'Audio_Track_07_',
			),
			8 => array(
				'file'        => 'audio/CTA_LPCC_Audio_Track_08_Integrated_Review_Error_Repair_and_Form_A_Form_B_Readiness_v1.0.mp3',
				'title'       => 'Integrated Review, Error Repair, and Form A/Form B Readiness',
				'runtime'     => '6:47',
				'file_needle' => 'Audio_Track_08_',
			),
		);
	}

	/**
	 * Resolve track number, display title, and runtime for an LPCC audio resource.
	 *
	 * @param object|array $resource Resource row or array.
	 * @return array{track:int,title:string,runtime:string}|null
	 */
	public static function resolve_audio_meta( $resource ) {
		$file_path = '';
		$title     = '';

		if ( is_object( $resource ) ) {
			$file_path = (string) ( $resource->file_path ?? '' );
			if ( '' === $file_path ) {
				$file_path = (string) ( $resource->file_url ?? '' );
			}
			$title = (string) ( $resource->title ?? '' );
		} elseif ( is_array( $resource ) ) {
			$file_path = (string) ( $resource['file_path'] ?? '' );
			if ( '' === $file_path ) {
				$file_path = (string) ( $resource['file_url'] ?? '' );
			}
			$title = (string) ( $resource['title'] ?? '' );
		} else {
			return null;
		}

		$file_path = str_replace( '\\', '/', $file_path );

		foreach ( self::get_audio_placement_map() as $track => $meta ) {
			$needle = (string) ( $meta['file_needle'] ?? '' );
			$base   = basename( (string) ( $meta['file'] ?? '' ) );

			$matched = false;
			if ( '' !== $needle && false !== stripos( $file_path, $needle ) ) {
				$matched = true;
			} elseif ( '' !== $base && false !== stripos( $file_path, $base ) ) {
				$matched = true;
			} elseif ( '' !== $title && 0 === strcasecmp( $title, (string) $meta['title'] ) ) {
				$matched = true;
			}

			if ( $matched ) {
				return array(
					'track'   => (int) $track,
					'title'   => (string) $meta['title'],
					'runtime' => (string) $meta['runtime'],
				);
			}
		}

		return null;
	}

	/**
	 * Find existing resource by title or filename (supports rematch after title polish).
	 *
	 * @param int    $course_id Course ID.
	 * @param string $title     Resource title.
	 * @param string $rel_path  Relative materials path.
	 * @return int
	 */
	private static function find_resource_id( $course_id, $title, $rel_path ) {
		$by_title = self::find_resource_id_by_title( $course_id, $title );
		if ( $by_title ) {
			return $by_title;
		}

		$base = basename( str_replace( '\\', '/', (string) $rel_path ) );
		if ( '' === $base ) {
			return 0;
		}

		global $wpdb;
		$like = '%' . $wpdb->esc_like( $base ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_downloadable_resources
				WHERE course_id = %d
					AND ( file_path LIKE %s OR file_url LIKE %s )
				LIMIT 1",
				absint( $course_id ),
				$like,
				$like
			)
		);
	}

	/**
	 * Find existing downloadable resource ID by exact title for a course.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $title     Resource title.
	 * @return int
	 */
	private static function find_resource_id_by_title( $course_id, $title ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cta_downloadable_resources
				WHERE course_id = %d AND title = %s
				LIMIT 1",
				absint( $course_id ),
				$title
			)
		);
	}

	/**
	 * Ensure unlock_after_quiz_type exists on downloadable resources.
	 */
	private static function ensure_resource_unlock_column() {
		if ( class_exists( 'CTA_Database' ) && method_exists( 'CTA_Database', 'maybe_add_resource_unlock_column' ) ) {
			CTA_Database::maybe_add_resource_unlock_column();
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'cta_downloadable_resources';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'unlock_after_quiz_type' ) );
		if ( empty( $col ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				"ALTER TABLE {$table} ADD COLUMN unlock_after_quiz_type varchar(40) NOT NULL DEFAULT '' AFTER is_practice_test"
			);
		}
	}

	/**
	 * Load a quiz seed question array from includes/quiz-seeds/.
	 *
	 * @param string $file Filename only (e.g. lpcc-ncmhce-form-a.php).
	 * @return array[]
	 */
	private static function load_seed_questions( $file ) {
		$file = basename( (string) $file );
		$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/' . $file;

		if ( ! is_readable( $path ) ) {
			return array();
		}

		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * Create/update a quiz and replace all questions.
	 *
	 * @param int    $course_id  Course ID.
	 * @param string $quiz_type  Quiz type key.
	 * @param string $title      Quiz title.
	 * @param int    $sort       Sort order.
	 * @param array  $questions  Question rows.
	 * @param int    $time_limit Time limit in minutes (0 = none).
	 * @return int Quiz ID or 0.
	 */
	private static function replace_form_quiz( $course_id, $quiz_type, $title, $sort, array $questions, $time_limit = 240 ) {
		global $wpdb;

		$course_id  = absint( $course_id );
		$quiz_type  = sanitize_text_field( $quiz_type );
		$title      = sanitize_text_field( $title );
		$sort       = (int) $sort;
		$time_limit = (int) $time_limit;

		if ( ! $course_id || '' === $quiz_type ) {
			return 0;
		}

		$quiz_table = $wpdb->prefix . 'cta_quizzes';
		$quiz       = null;

		if ( class_exists( 'CTA_Database' ) ) {
			$all = CTA_Database::get_quizzes_by_course( $course_id, false );
			foreach ( (array) $all as $row ) {
				$type = isset( $row->quiz_type ) ? (string) $row->quiz_type : '';
				if ( $quiz_type === $type ) {
					$quiz = $row;
					break;
				}
			}
		}

		if ( $quiz ) {
			$quiz_id = (int) $quiz->id;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$quiz_table,
				array(
					'title'           => $title,
					'quiz_type'       => $quiz_type,
					'passing_score'   => 70,
					'time_limit_mins' => $time_limit,
					'max_attempts'    => 0,
					'status'          => 'active',
					'sort_order'      => $sort,
				),
				array( 'id' => $quiz_id ),
				array( '%s', '%s', '%d', '%d', '%d', '%s', '%d' ),
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$quiz_table,
				array(
					'course_id'       => $course_id,
					'title'           => $title,
					'quiz_type'       => $quiz_type,
					'sort_order'      => $sort,
					'passing_score'   => 70,
					'time_limit_mins' => $time_limit,
					'max_attempts'    => 0,
					'status'          => 'active',
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
			);
			if ( ! $inserted ) {
				return 0;
			}
			$quiz_id = (int) $wpdb->insert_id;
		}

		if ( ! $quiz_id ) {
			return 0;
		}

		self::maybe_widen_option_columns();

		$q_table = $wpdb->prefix . 'cta_quiz_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $q_table, array( 'quiz_id' => $quiz_id ), array( '%d' ) );

		$text = function_exists( 'cta_lms_sanitize_utf8_text' ) ? 'cta_lms_sanitize_utf8_text' : null;

		foreach ( $questions as $index => $question ) {
			$correct = isset( $question['correct_option'] ) ? strtolower( (string) $question['correct_option'] ) : 'a';
			$correct = in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ? $correct : 'a';

			$qt = (string) ( $question['question_text'] ?? '' );
			$oa = (string) ( $question['option_a'] ?? '' );
			$ob = (string) ( $question['option_b'] ?? '' );
			$oc = (string) ( $question['option_c'] ?? '' );
			$od = (string) ( $question['option_d'] ?? '' );
			$ex = (string) ( $question['explanation'] ?? '' );

			if ( $text ) {
				$qt = $text( $qt );
				$oa = $text( $oa );
				$ob = $text( $ob );
				$oc = $text( $oc );
				$od = $text( $od );
				$ex = $text( $ex );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$q_table,
				array(
					'quiz_id'        => $quiz_id,
					'question_text'  => $qt,
					'option_a'       => $oa,
					'option_b'       => $ob,
					'option_c'       => $oc,
					'option_d'       => $od,
					'correct_option' => $correct,
					'explanation'    => $ex,
					'order_index'    => (int) $index,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);
		}

		return $quiz_id;
	}

	/**
	 * Widen option_* columns so long official stems are not truncated.
	 */
	private static function maybe_widen_option_columns() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_quiz_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		foreach ( array( 'option_a', 'option_b', 'option_c', 'option_d' ) as $col ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $col ), ARRAY_A );
			if ( empty( $row['Type'] ) ) {
				continue;
			}
			$type = strtolower( (string) $row['Type'] );
			if ( false !== strpos( $type, 'text' ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "ALTER TABLE {$table} MODIFY {$col} text NOT NULL" );
		}
	}
}

}
