<?php
/**
 * CTA LPCC California Law & Ethics Exam Preparation (CTA-EP-003) — program, modules, materials, and assessment sync.
 *
 * Student materials are an allowlist under assets/course-materials/lpcc-law-ethics/.
 * Trees admin-only/, chapter-tests-admin/, and Website/Catalog marketing copy are never published to learners.
 * Separate from LPCC NCMHCE Exam Preparation.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lpcc_Law_Ethics_Sync
 */
if ( ! class_exists( 'CTA_Lpcc_Law_Ethics_Sync' ) ) {

class CTA_Lpcc_Law_Ethics_Sync {

	const SEED_OPTION   = 'cta_lpcc_law_ethics_seeded_1_0_172';
	const SLUG          = 'lpcc-california-law-ethics-exam-preparation';
	const TITLE         = 'CTA LPCC California Law & Ethics Exam Preparation Program';
	const PUBLIC_TITLE  = 'LPCC California Law & Ethics Exam Preparation';
	const PRICE         = 199.00;
	const ACCESS_MONTHS = 6;
	const MATERIALS_REL = 'assets/course-materials/lpcc-law-ethics/';

	/**
	 * Find the LPCC Law & Ethics course by slug or title.
	 *
	 * @return object|null
	 */
	public static function find_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		$candidates = array(
			array( 'slug', self::SLUG ),
			array( 'title', self::TITLE ),
			array( 'title', self::PUBLIC_TITLE ),
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
	 * Upsert Start Here + 9 workbook modules (order_index 0–9).
	 * Match existing by "Start Here:" or "Workbook N:" title prefix.
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

		$start_here_row = null;
		$by_prefix      = array();
		foreach ( (array) $existing as $row ) {
			$title = (string) ( $row->title ?? '' );
			if ( null === $start_here_row && preg_match( '/^Start\s+Here\s*:/i', $title ) ) {
				$start_here_row = $row;
				continue;
			}
			if ( preg_match( '/^Workbook\s+(\d+)\s*:/i', $title, $m ) ) {
				$n = (int) $m[1];
				if ( $n >= 1 && $n <= 9 && ! isset( $by_prefix[ $n ] ) ) {
					$by_prefix[ $n ] = $row;
				}
			}
		}

		foreach ( $defs as $index => $def ) {
			$title     = sanitize_text_field( (string) $def['title'] );
			$order     = (int) $index;
			$module_id = 0;
			$is_start  = ( 0 === $index );
			$desc      = $is_start
				? 'Program orientation and the required LPCC/APCC license-specific module. Recommended first; not an access gate.'
				: 'Candidate workbook and paired chapter tests for this unit. Open from enrollment; recommended order is guidance only.';

			$match = null;
			if ( $is_start ) {
				$match = $start_here_row;
			} else {
				$wb_num = $index; // order_index 1 → Workbook 1
				if ( isset( $by_prefix[ $wb_num ] ) ) {
					$match = $by_prefix[ $wb_num ];
				}
			}

			if ( $match ) {
				$module_id = (int) $match->id;
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
	 * Attach learner-facing materials (Start Here, workbooks, practice exams, final, study tools, program close).
	 * Idempotent by friendly title. unlock_after_quiz_type is always empty (open access).
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

		$modules           = class_exists( 'CTA_Database' )
			? CTA_Database::get_course_modules( $course_id )
			: array();
		$module_by_n       = array();
		$module_start_here = 0;
		foreach ( (array) $modules as $mod ) {
			$title = (string) $mod->title;
			if ( ! $module_start_here && preg_match( '/^Start\s+Here\s*:/i', $title ) ) {
				$module_start_here = (int) $mod->id;
			}
			if ( preg_match( '/^Workbook\s+(\d+)\s*:/i', $title, $m ) ) {
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
			// Exam Prep open access: never persist material unlock gates.
			$unlock      = '';

			// Never attach admin-only / DO_NOT_PUBLISH / archive package trees.
			if ( class_exists( 'CTA_Course_Materials' )
				&& method_exists( 'CTA_Course_Materials', 'is_admin_restricted_source_path' )
				&& CTA_Course_Materials::is_admin_restricted_source_path( $source . ' ' . $rel ) ) {
				$missing[] = $rel . ' (admin_restricted)';
				++$skipped;
				++$order_index;
				continue;
			}

			// Hard skip admin/marketing trees even if path helper is absent.
			if ( preg_match( '#^(admin-only|chapter-tests-admin)/#i', $rel )
				|| false !== stripos( $rel, 'Website_Catalog' )
				|| false !== stripos( $rel, 'Checkout_FAQ_and_LMS_Copy' ) ) {
				$missing[] = $rel . ' (admin_or_marketing_excluded)';
				++$skipped;
				++$order_index;
				continue;
			}

			if ( ! empty( $item['start_here'] ) || ( isset( $item['workbook_num'] ) && 0 === (int) $item['workbook_num'] ) ) {
				$module_id = $module_start_here;
			} elseif ( ! empty( $item['workbook_num'] ) ) {
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
	 * Ensure license assessment, 45 chapter tests, Practice A/B, and comprehensive final quizzes.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function sync_assessments( $course_id ) {
		$course_id = absint( $course_id );

		$empty = array(
			'ok'                      => false,
			'license_25'              => 0,
			'practice_a'              => 0,
			'practice_b'              => 0,
			'comprehensive_final'     => 0,
			'questions_license_25'    => 0,
			'questions_practice_a'    => 0,
			'questions_practice_b'    => 0,
			'questions_comprehensive' => 0,
			'chapters_ok'             => 0,
			'message'                 => 'invalid_course',
		);

		$chapter_defs = self::get_chapter_assessment_definitions();
		foreach ( $chapter_defs as $cdef ) {
			$empty[ $cdef['key'] ]  = 0;
			$empty[ $cdef['qkey'] ] = 0;
		}

		if ( ! $course_id ) {
			return $empty;
		}

		$defs   = array();
		$defs[] = array(
			'quiz_type' => 'license_25',
			'title'     => 'License-Specific Module — 25-Question Assessment',
			'sort'      => 1,
			'time'      => 40,
			'file'      => 'lpcc-law-ethics-license-25.php',
			'expect'    => 25,
			'key'       => 'license_25',
			'qkey'      => 'questions_license_25',
		);

		foreach ( $chapter_defs as $cdef ) {
			$defs[] = $cdef;
		}

		$defs[] = array(
			'quiz_type' => 'practice_a',
			'title'     => 'Practice Examination A — 50-Question Assessment',
			'sort'      => 200,
			'time'      => 60,
			'file'      => 'lpcc-law-ethics-practice-a.php',
			'expect'    => 50,
			'key'       => 'practice_a',
			'qkey'      => 'questions_practice_a',
		);
		$defs[] = array(
			'quiz_type' => 'practice_b',
			'title'     => 'Practice Examination B — 50-Question Assessment',
			'sort'      => 210,
			'time'      => 60,
			'file'      => 'lpcc-law-ethics-practice-b.php',
			'expect'    => 50,
			'key'       => 'practice_b',
			'qkey'      => 'questions_practice_b',
		);
		$defs[] = array(
			'quiz_type' => 'comprehensive_final',
			'title'     => 'Comprehensive Final — 100-Question Examination',
			'sort'      => 220,
			'time'      => 120,
			'file'      => 'lpcc-law-ethics-comprehensive-final.php',
			'expect'    => 100,
			'key'       => 'comprehensive_final',
			'qkey'      => 'questions_comprehensive',
		);

		$result            = $empty;
		$result['message'] = '';

		foreach ( $defs as $def ) {
			$questions              = self::load_seed_questions( $def['file'] );
			$count                  = count( $questions );
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

		$chapters_ok = 0;
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

			if ( 0 === strpos( (string) $def['quiz_type'], 'wb' ) ) {
				++$chapters_ok;
			}
		}

		$result['chapters_ok'] = $chapters_ok;
		$result['ok']          = true;
		$result['message']     = 'synced';

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
			'modules_created'             => (int) ( $modules['created'] ?? 0 ),
			'modules_updated'             => (int) ( $modules['updated'] ?? 0 ),
			'materials_attached'          => (int) ( $materials['attached'] ?? 0 ),
			'materials_updated'           => (int) ( $materials['updated'] ?? 0 ),
			'materials_missing'           => count( $materials['missing'] ?? array() ),
			'license_25_quiz_id'          => (int) ( $assessments['license_25'] ?? 0 ),
			'practice_a_quiz_id'          => (int) ( $assessments['practice_a'] ?? 0 ),
			'practice_b_quiz_id'          => (int) ( $assessments['practice_b'] ?? 0 ),
			'comprehensive_final_quiz_id' => (int) ( $assessments['comprehensive_final'] ?? 0 ),
			'questions_license_25'        => (int) ( $assessments['questions_license_25'] ?? 0 ),
			'questions_practice_a'        => (int) ( $assessments['questions_practice_a'] ?? 0 ),
			'questions_practice_b'        => (int) ( $assessments['questions_practice_b'] ?? 0 ),
			'questions_comprehensive'     => (int) ( $assessments['questions_comprehensive'] ?? 0 ),
			'chapters_ok'                 => (int) ( $assessments['chapters_ok'] ?? 0 ),
		);

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
	 * @param string $quiz_type Quiz type (e.g. practice_a, wb1_c1).
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
	 * Program description HTML for LMS and website (exact learner-facing copy).
	 *
	 * @return string
	 */
	private static function get_program_description_html() {
		$html = '
<p>LPCC California Law &amp; Ethics Exam Preparation is a separate, profession-specific study program for California APCCs and other eligible LPCC applicants. The program combines current California legal requirements, counseling ethical standards, LPCC/APCC professional identity, applied decision-making, and original examination practice.</p>
<p>The completed program includes a required LPCC/APCC license-specific module, nine learner workbooks covering 45 law-and-ethics chapters, answer-hidden assessments, detailed option-by-option rationales, two distinct 50-question practice examinations, one 100-question comprehensive final examination, performance and remediation tools, an interactive and printable 807-card flashcard system, and six high-yield printable study toolkits.</p>
<p>Learners may access and download approved study materials from the beginning of enrollment and study offline. The recommended order is guidance only; the program does not use continuing-education completion locks, a CE evaluation, or a certificate. Access is valid for six months from purchase. Exam Preparation Only — No CE Credit. This program does not award CE hours or a CE certificate.</p>';

		return wp_kses_post( $html );
	}

	/**
	 * Learning objectives for LPCC/APCC California Law & Ethics exam preparation.
	 *
	 * @return string[]
	 */
	private static function get_learning_objectives() {
		return array(
			'Identify LPCC and APCC professional identity, statutory scope, competence, and assessment boundaries under California law.',
			'Apply APCC registration, supervision, and disclosure requirements to real practice settings.',
			'Distinguish confidentiality, privilege, authorization, and lawful disclosure in response to subpoenas or court requests.',
			'Recognize child, elder, dependent-adult, and danger/crisis reporting and safety obligations.',
			'Apply telehealth, technology, privacy, and jurisdiction standards to California practice.',
			'Identify professional boundaries, multiple-relationship risks, and documentation/record-keeping requirements.',
		);
	}

	/**
	 * Syllabus / SEO meta for the sales and LMS pages.
	 *
	 * @return array
	 */
	private static function get_syllabus_meta() {
		return array(
			'course_code'            => 'CTA-EP-003',
			'public_title'           => self::PUBLIC_TITLE,
			'short_description'      => 'LPCC/APCC license-specific module, nine workbooks covering 45 law-and-ethics chapters, chapter tests with rationales, Practice A and B (50 questions each), a 100-question comprehensive final, flashcards, and high-yield study toolkits. Exam preparation only — no CE credit.',
			'course_classification'  => 'Exam Preparation Only — No CE Credit',
			'instructional_method'   => 'Self-paced asynchronous',
			'target_audience'        => 'California APCCs and other eligible LPCC applicants',
			'seo_title'              => 'LPCC California Law & Ethics Exam Prep | CTA',
			'meta_description'       => 'Prepare for the California LPCC Law & Ethics exam with nine workbooks, 45 chapter tests, Practice A/B, a 100-question final, and flashcards. Exam preparation only — no CE credit.',
			'image_alt'              => 'Clinical Training and Supervision Academy LPCC California Law and Ethics Exam Preparation graphic',
			'primary_cta'            => 'Begin Your Law & Ethics Exam Preparation',
			'page_badge'             => 'Exam Preparation Only — No CE Credit',
			'educational_notice'     => 'Exam Preparation Only — No CE Credit. This program does not award CE hours or a CE certificate. CTA is an independent educational resource and is not affiliated with or endorsed by any state licensing board. This program is separate from LPCC NCMHCE Exam Preparation.',
			'launch_status'          => 'draft_pending_testing',
			'launch_pending_testing' => true,
			'development_draft'      => true,
			'open_access_exam_prep'  => true,
			'content_pending'        => false,
		);
	}

	/**
	 * Ten module titles (order_index 0–9): Start Here + Workbooks 1–9.
	 *
	 * @return array<int,array{title:string}>
	 */
	private static function get_module_definitions() {
		return array(
			array( 'title' => 'Start Here: Program Orientation and LPCC/APCC License-Specific Module' ),
			array( 'title' => 'Workbook 1: Informed Consent, Minors, and Family Involvement' ),
			array( 'title' => 'Workbook 2: Telehealth and Technology' ),
			array( 'title' => 'Workbook 3: Professional Competence' ),
			array( 'title' => 'Workbook 4: Professional Impairment' ),
			array( 'title' => 'Workbook 5: Client Welfare and Harm Prevention' ),
			array( 'title' => 'Workbook 6: Professional Boundaries, Multiple Relationships, and Exploitation' ),
			array( 'title' => 'Workbook 7: Cultural Humility and Bias' ),
			array( 'title' => 'Workbook 8: Confidentiality and Information Sharing' ),
			array( 'title' => 'Workbook 9: Clinical Documentation and Record Management' ),
		);
	}

	/**
	 * Material map: relative file under MATERIALS_REL + learner-facing title + flags.
	 * Does not include admin-only/, chapter-tests-admin/, Website Catalog copy, or flashcards.json.
	 *
	 * @return array<int,array>
	 */
	private static function get_material_map() {
		$items = array();

		// Start Here (module order_index 0).
		$start_here = array(
			array(
				'file'       => 'start-here/CTA_LPCC_Law_and_Ethics_EP_Start_Here_and_Program_Orientation_v1.1_Release.docx',
				'title'      => 'Start Here — Program Orientation',
				'start_here' => 1,
			),
			array(
				'file'       => 'start-here/CTA_LPCC_Law_and_Ethics_EP_License_Specific_Module_LPCC_APCC_Candidate_Edition_v1.0.docx',
				'title'      => 'License-Specific Module — LPCC/APCC Candidate Edition',
				'start_here' => 1,
			),
			array(
				'file'             => 'start-here/CTA_LPCC_Law_and_Ethics_EP_License_Specific_Module_25_Question_Candidate_Assessment_v1.0.docx',
				'title'            => 'License-Specific Module — 25-Question Candidate Assessment',
				'start_here'       => 1,
				'is_practice_test' => 1,
			),
			array(
				'file'       => 'start-here/CTA_LPCC_Law_and_Ethics_EP_License_Specific_Module_Controlled_Answer_Key_Rationales_and_Remediation_v1.0.docx',
				'title'      => 'License-Specific Module — Controlled Answer Key, Rationales, and Remediation',
				'start_here' => 1,
			),
			array(
				'file'       => 'start-here/CTA_LPCC_Law_and_Ethics_EP_License_Specific_Module_25_Card_Flashcards_and_Remediation_v1.0.docx',
				'title'      => 'License-Specific Module — 25-Card Flashcards and Remediation',
				'start_here' => 1,
			),
		);
		foreach ( $start_here as $row ) {
			$items[] = $row;
		}

		$workbooks = array(
			1 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB01_Informed_Consent_Minors_and_Family_Involvement_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 1 — Informed Consent, Minors, and Family Involvement (Candidate Edition)',
			),
			2 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB02_Telehealth_and_Technology_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 2 — Telehealth and Technology (Candidate Edition)',
			),
			3 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB03_Professional_Competence_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 3 — Professional Competence (Candidate Edition)',
			),
			4 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB04_Professional_Impairment_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 4 — Professional Impairment (Candidate Edition)',
			),
			5 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB05_Client_Welfare_and_Harm_Prevention_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 5 — Client Welfare and Harm Prevention (Candidate Edition)',
			),
			6 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB06_Professional_Boundaries_Multiple_Relationships_and_Exploitation_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 6 — Professional Boundaries, Multiple Relationships, and Exploitation (Candidate Edition)',
			),
			7 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB07_Cultural_Humility_and_Bias_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 7 — Cultural Humility and Bias (Candidate Edition)',
			),
			8 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB08_Confidentiality_and_Information_Sharing_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 8 — Confidentiality and Information Sharing (Candidate Edition)',
			),
			9 => array(
				'file'  => 'workbooks/CTA_LPCC_Law_and_Ethics_EP_WB09_Clinical_Documentation_and_Record_Management_Candidate_Edition_v1.0.docx',
				'title' => 'Workbook 9 — Clinical Documentation and Record Management (Candidate Edition)',
			),
		);

		foreach ( $workbooks as $n => $wb ) {
			$items[] = array(
				'file'         => $wb['file'],
				'title'        => $wb['title'],
				'workbook_num' => $n,
			);
		}

		// Practice Examination A (R6).
		$items[] = array(
			'file'             => 'practice-a/CTA_LPCC_Law_and_Ethics_EP_Practice_Examination_A_50_Question_Candidate_Booklet_v1.1_Release.docx',
			'title'            => 'Practice Examination A — 50-Question Candidate Booklet',
			'is_practice_test' => 1,
		);
		$items[] = array(
			'file'  => 'practice-a/CTA_LPCC_Law_and_Ethics_EP_Practice_Examination_A_Controlled_Answer_Key_v1.1_Release.docx',
			'title' => 'Practice Examination A — Controlled Answer Key',
		);
		$items[] = array(
			'file'  => 'practice-a/CTA_LPCC_Law_and_Ethics_EP_Practice_Examination_A_Detailed_Rationales_and_Remediation_Guide_v1.1_Release.docx',
			'title' => 'Practice Examination A — Detailed Rationales and Remediation Guide',
		);
		$items[] = array(
			'file'  => 'practice-a/CTA_LPCC_Law_and_Ethics_EP_R6_Practice_Examination_A_Performance_Analysis_and_Remediation_Worksheet_v1.0.docx',
			'title' => 'Practice Examination A — Performance Analysis and Remediation Worksheet (R6)',
		);
		$items[] = array(
			'file'  => 'practice-a/CTA_LPCC_Law_and_Ethics_EP_R6_Practice_Examination_A_Response_and_Confidence_Sheet_v1.0.docx',
			'title' => 'Practice Examination A — Response and Confidence Sheet (R6)',
		);

		// Practice Examination B (R7).
		$items[] = array(
			'file'             => 'practice-b/CTA_LPCC_Law_and_Ethics_EP_Practice_Examination_B_50_Question_Candidate_Booklet_v1.1_Release.docx',
			'title'            => 'Practice Examination B — 50-Question Candidate Booklet',
			'is_practice_test' => 1,
		);
		$items[] = array(
			'file'  => 'practice-b/CTA_LPCC_Law_and_Ethics_EP_Practice_Examination_B_Controlled_Answer_Key_v1.1_Release.docx',
			'title' => 'Practice Examination B — Controlled Answer Key',
		);
		$items[] = array(
			'file'  => 'practice-b/CTA_LPCC_Law_and_Ethics_EP_Practice_Examination_B_Detailed_Rationales_and_Remediation_Guide_v1.1_Release.docx',
			'title' => 'Practice Examination B — Detailed Rationales and Remediation Guide',
		);
		$items[] = array(
			'file'  => 'practice-b/CTA_LPCC_Law_and_Ethics_EP_R7_Practice_Examination_B_Performance_Analysis_and_Remediation_Worksheet_v1.0.docx',
			'title' => 'Practice Examination B — Performance Analysis and Remediation Worksheet (R7)',
		);
		$items[] = array(
			'file'  => 'practice-b/CTA_LPCC_Law_and_Ethics_EP_R7_Practice_Examination_B_Response_and_Confidence_Sheet_v1.0.docx',
			'title' => 'Practice Examination B — Response and Confidence Sheet (R7)',
		);

		// Comprehensive Final (R8).
		$items[] = array(
			'file'             => 'comprehensive-final/CTA_LPCC_Law_and_Ethics_EP_Comprehensive_Final_100_Question_Candidate_Booklet_v1.1_Release.docx',
			'title'            => 'Comprehensive Final — 100-Question Candidate Booklet',
			'is_practice_test' => 1,
		);
		$items[] = array(
			'file'  => 'comprehensive-final/CTA_LPCC_Law_and_Ethics_EP_Comprehensive_Final_Controlled_Answer_Key_v1.1_Release.docx',
			'title' => 'Comprehensive Final — Controlled Answer Key',
		);
		$items[] = array(
			'file'  => 'comprehensive-final/CTA_LPCC_Law_and_Ethics_EP_Comprehensive_Final_Detailed_Rationales_and_Remediation_Guide_v1.1_Release.docx',
			'title' => 'Comprehensive Final — Detailed Rationales and Remediation Guide',
		);
		$items[] = array(
			'file'  => 'comprehensive-final/CTA_LPCC_Law_and_Ethics_EP_R8_Comprehensive_Final_Performance_Analysis_and_Final_Remediation_Plan_v1.0.docx',
			'title' => 'Comprehensive Final — Performance Analysis and Final Remediation Plan (R8)',
		);
		$items[] = array(
			'file'  => 'comprehensive-final/CTA_LPCC_Law_and_Ethics_EP_R8_Comprehensive_Final_Response_and_Confidence_Sheet_v1.0.docx',
			'title' => 'Comprehensive Final — Response and Confidence Sheet (R8)',
		);

		// Study tools (printable flashcards, interactive Study Center, R9A toolkits). flashcards.json is viewer data only.
		$study_tools = array(
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_Printable_Flashcards_Single_Sided_v1.1_Release.pdf',
				'title' => 'Printable Flashcards — Single-Sided',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_Printable_Flashcards_Duplex_Cut_Apart_v1.1_Release.pdf',
				'title' => 'Printable Flashcards — Duplex Cut-Apart',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_Interactive_Flashcard_Study_Center_v1.1_Release.html',
				'title' => 'Interactive Flashcard Study Center',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_Exam_Strategy_and_Study_Planning_Toolkit_v1.1_Release.docx',
				'title' => 'R9A — Exam Strategy and Study Planning Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_Study_Schedules_and_Error_Log_v1.1_Release.docx',
				'title' => 'R9A — Study Schedules and Error Log',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_High_Yield_Numbers_Timelines_and_Trigger_Words_Toolkit_v1.1_Release.docx',
				'title' => 'R9A — High-Yield Numbers, Timelines, and Trigger Words Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_High_Yield_California_Law_Decision_Guides_Toolkit_v1.1_Release.docx',
				'title' => 'R9A — High-Yield California Law Decision Guides Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_High_Yield_California_Ethics_Decision_Guides_Toolkit_v1.1_Release.docx',
				'title' => 'R9A — High-Yield California Ethics Decision Guides Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_45_Chapter_Exam_Traps_and_Correction_Rules_Toolkit_v1.1_Release.docx',
				'title' => 'R9A — 45-Chapter Exam Traps and Correction Rules Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_45_Chapter_Master_Study_Map_and_Readiness_Checklist_Toolkit_v1.1_Release.docx',
				'title' => 'R9A — 45-Chapter Master Study Map and Readiness Checklist Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LPCC_Law_and_Ethics_EP_R9A_Programwide_Rapid_Review_Guide_v1.1_Release.docx',
				'title' => 'R9A — Programwide Rapid Review Guide',
			),
		);
		foreach ( $study_tools as $tool ) {
			$items[] = $tool;
		}

		// Program Close (learner-facing readiness guide only — not Website/Catalog FAQ package).
		$items[] = array(
			'file'  => 'program-close/CTA_LPCC_Law_and_Ethics_EP_Program_Close_and_Final_Readiness_Guide_v1.1_Release.docx',
			'title' => 'Program Close and Final Readiness Guide',
		);

		return $items;
	}

	/**
	 * Forty-five ACTIVE chapter test definitions (quiz_type wb{N}_c{M}).
	 *
	 * @return array<int,array>
	 */
	private static function get_chapter_assessment_definitions() {
		$chapters = array(
			1 => array(
				'Foundations of Informed Consent',
				'Information Required for Informed Decision-Making',
				'Ongoing Informed Consent',
				'Required Disclosures',
				'General Consent Requirements for Minors',
				'Minor Consent Exceptions',
				'Working with Parents and Families',
			),
			2 => array(
				'Telehealth Foundations',
				'Telehealth Informed Consent',
				'Telehealth Privacy and Security',
				'Technology, Electronic Communication, and Professional Responsibilities',
				'Social Media, Boundaries, and Digital Ethics',
				'Technology Risk Management and Compliance',
			),
			3 => array(
				'Professional Competence Foundations',
				'Scope of Practice vs. Scope of Competence',
				'Maintaining Professional Competence',
				'Consultation and Seeking Professional Assistance',
				'Clinical Supervision, Feedback, and Accountability',
				'Professional Roles and Role Clarification',
			),
			4 => array(
				'Recognizing Professional Impairment',
				'Substance Use and Professional Impairment',
				'Mental Health, Burnout, and Compassion Fatigue',
				'Physical Conditions, Fatigue, and Professional Functioning',
				'Responding Ethically to Professional Impairment',
			),
			5 => array(
				'The Principle of Nonmaleficence',
				'Avoiding and Preventing Client Harm',
				'Beneficence and Promoting Client Welfare',
				'Professional Responsibility and Accountability',
				'Professional Judgment and Ethical Decision-Making',
			),
			6 => array(
				'Professional Boundaries',
				'Boundary Crossings and Boundary Violations',
				'Multiple and Dual Relationships',
				'Gifts, Bartering, and Financial Relationships',
				'Sexual Misconduct and Client Exploitation',
			),
			7 => array(
				'Cultural Humility and Culturally Responsive Practice',
				'Bias, Assumptions, and Ethical Decision-Making',
				'Working With Diverse Populations',
			),
			8 => array(
				'Confidentiality in Individual, Couple, Family, and Group Treatment',
				'Authorizations, Releases of Information, and Limits on Disclosure',
				'Privacy, Protected Health Information, and HIPAA',
				'Client Access to Records and Responding to Record Requests',
			),
			9 => array(
				'Clinical Documentation Standards',
				'Documentation Errors, Risk Management, and High-Risk Situations',
				'Record Retention Requirements',
				'Record Security, Storage, and Record Destruction',
			),
		);

		$defs = array();
		$sort = 10;

		foreach ( $chapters as $wb => $titles ) {
			foreach ( $titles as $i => $chapter_title ) {
				$ch       = $i + 1;
				$quiz_type = 'wb' . $wb . '_c' . $ch;
				$file      = sprintf( 'lpcc-law-ethics-wb%02d-c%02d.php', $wb, $ch );

				$defs[] = array(
					'quiz_type' => $quiz_type,
					'title'     => sprintf( 'WB%d-C%d — %s (Chapter Test)', $wb, $ch, $chapter_title ),
					'sort'      => $sort,
					'time'      => 25,
					'file'      => $file,
					'expect'    => 13,
					'key'       => $quiz_type,
					'qkey'      => 'questions_' . $quiz_type,
				);
				++$sort;
			}
		}

		return $defs;
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
	 * @param string $file Filename only (e.g. lpcc-law-ethics-practice-a.php).
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
	private static function replace_form_quiz( $course_id, $quiz_type, $title, $sort, array $questions, $time_limit = 60 ) {
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
