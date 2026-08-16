<?php
/**
 * LMFT California Law & Ethics Exam Preparation — program sync + website/LMS copy.
 *
 * Applies approved Point 6 Website/LMS Copy Package (v1.1) while keeping the course
 * draft / launch-pending (not publicly purchasable) until separate release approval.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Law_Ethics_Sync
 */
if ( ! class_exists( 'CTA_Lmft_Law_Ethics_Sync' ) ) {

class CTA_Lmft_Law_Ethics_Sync {

	const SEED_OPTION         = 'cta_lmft_law_ethics_seeded_1_0_250';
	const TOOLKIT_SEED_OPTION = 'cta_lmft_law_ethics_toolkits_seeded_1_0_251';
	const COPY_SEED_OPTION    = 'cta_lmft_law_ethics_copy_seeded_point6_v1_1';
	const PACKAGE_TOOLKIT_DIR = '_packages/CTA_LMFT_Law_and_Ethics_EP_Complete_David_Handoff_Package_v1.0/05_Study_Center_and_Toolkits/';
	const SLUG          = 'california-law-ethics-exam-preparation';
	const TITLE         = 'CTA LMFT California Law & Ethics Exam Preparation Program';
	const PUBLIC_TITLE  = 'LMFT California Law & Ethics Exam Preparation';
	const PRICE         = 199.00;
	const ACCESS_MONTHS = 6;
	const MATERIALS_REL = 'assets/course-materials/lmft-law-ethics/';
	/** Recommended readiness benchmark only — not a completion gate. */
	const READINESS_BENCHMARK = 80;

	/**
	 * Find the LMFT Law & Ethics course by slug or title.
	 *
	 * @return object|null
	 */
	public static function find_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';
		$match = array(
			array( 'slug', self::SLUG ),
			array( 'title', self::TITLE ),
			array( 'title', self::PUBLIC_TITLE ),
			array( 'title', 'California Law & Ethics Exam Preparation' ),
		);

		foreach ( $match as $pair ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$pair[0]} = %s ORDER BY id ASC LIMIT 1",
					$pair[1]
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Create or update the exam_prep program row.
	 *
	 * @return int Course ID or 0.
	 */
	public static function ensure_program() {
		global $wpdb;

		$table       = $wpdb->prefix . 'cta_courses';
		$course      = self::find_course();
		$description = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::program_description_html()
			: self::get_program_description_html();
		$objectives  = wp_json_encode( self::get_learning_objectives() );
		$meta        = self::get_syllabus_meta();

		$fields = array(
			'title'                => self::TITLE,
			'slug'                 => self::SLUG,
			'description'          => $description,
			'ce_hours'             => 0,
			'price'                => (float) self::PRICE,
			'category'             => 'Exam Preparation',
			'learning_objectives'  => $objectives,
			'status'               => 'draft',
			'product_type'         => 'exam_prep',
			'access_period_months' => (int) self::ACCESS_MONTHS,
			'awards_ce_hours'      => 0,
			'has_ce_certificate'   => 0,
		);
		$fields = class_exists( 'CTA_Course_Catalog' )
			? CTA_Course_Catalog::prepare_exam_prep_course_row( $fields, $meta, $course )
			: array_merge( $fields, array( 'syllabus_meta' => wp_json_encode( $meta ) ) );

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

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Upsert Start Here + nine placeholder workbook modules.
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
		$license_row    = null;
		$by_prefix      = array();
		$by_kind_extra  = array();
		foreach ( (array) $existing as $row ) {
			$title = (string) ( $row->title ?? '' );
			if ( null === $start_here_row && preg_match( '/^Start\s+Here\s*:/i', $title ) ) {
				$start_here_row = $row;
				continue;
			}
			if ( null === $license_row && preg_match( '/Practice\s+Act|License[-\s]Specific\s+Module|AMFT\s+Professional\s+Identity/i', $title ) ) {
				$license_row = $row;
				continue;
			}
			if ( preg_match( '/^Workbook\s+(\d+)\s*:/i', $title, $m ) ) {
				$n = (int) $m[1];
				if ( $n >= 1 && $n <= 9 && ! isset( $by_prefix[ $n ] ) ) {
					$by_prefix[ $n ] = $row;
				}
				continue;
			}
			if ( preg_match( '/^Practice\s+Examination\s+A\b/i', $title ) && ! isset( $by_kind_extra['practice_a'] ) ) {
				$by_kind_extra['practice_a'] = $row;
				continue;
			}
			if ( preg_match( '/^Practice\s+Examination\s+B\b/i', $title ) && ! isset( $by_kind_extra['practice_b'] ) ) {
				$by_kind_extra['practice_b'] = $row;
				continue;
			}
			if ( preg_match( '/^Comprehensive\s+Final/i', $title ) && ! isset( $by_kind_extra['final'] ) ) {
				$by_kind_extra['final'] = $row;
				continue;
			}
			if ( preg_match( '/^Study\s+Center/i', $title ) && ! isset( $by_kind_extra['study'] ) ) {
				$by_kind_extra['study'] = $row;
				continue;
			}
			if ( preg_match( '/^Program\s+Close/i', $title ) && ! isset( $by_kind_extra['close'] ) ) {
				$by_kind_extra['close'] = $row;
			}
		}

		foreach ( $defs as $index => $def ) {
			$title     = sanitize_text_field( (string) $def['title'] );
			$order     = (int) $index;
			$module_id = 0;
			$kind      = sanitize_key( (string) ( $def['kind'] ?? 'workbook' ) );
			$wb_num    = isset( $def['workbook_num'] ) ? absint( $def['workbook_num'] ) : 0;

			if ( 'start' === $kind ) {
				$desc = 'Read orientation, notices, access rules, and support boundaries.';
			} elseif ( 'license' === $kind ) {
				$desc = 'Complete the module and submit the 25-question assessment.';
			} elseif ( 'practice_a' === $kind ) {
				$desc = 'Complete the 50-question form and performance worksheet.';
			} elseif ( 'practice_b' === $kind ) {
				$desc = 'Complete the second 50-question form and compare error patterns.';
			} elseif ( 'final' === $kind ) {
				$desc = 'Complete the 100-question form and build a targeted final study plan.';
			} elseif ( 'study' === $kind ) {
				$desc = 'Use the 807-card study center and six toolkits throughout the program.';
			} elseif ( 'close' === $kind ) {
				$desc = 'Review strengths, open gaps, next-study actions, and test-day preparation.';
			} else {
				$desc = 'Read each workbook, complete its candidate assessment, then analyze gated rationales and remediation.';
			}

			$match = null;
			if ( 'start' === $kind ) {
				$match = $start_here_row;
			} elseif ( 'license' === $kind ) {
				$match = $license_row;
			} elseif ( $wb_num >= 1 && $wb_num <= 9 ) {
				$match = $by_prefix[ $wb_num ] ?? null;
			} elseif ( isset( $by_kind_extra[ $kind ] ) ) {
				$match = $by_kind_extra[ $kind ];
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
	 * Create/update assessment quizzes. License module loads 25 secured questions; workbook shells stay empty until client content arrives.
	 *
	 * @param int $course_id Course ID.
	 * @return array<string,mixed>
	 */
	public static function sync_assessments( $course_id ) {
		$course_id = absint( $course_id );
		$result    = array(
			'ok'                   => false,
			'message'              => 'invalid_course',
			'license_25'           => 0,
			'questions_license_25' => 0,
			'quizzes'              => 0,
		);

		if ( ! $course_id ) {
			return $result;
		}

		$license_questions = self::load_seed_questions( 'lmft-law-ethics-license-25.php' );
		$license_count     = count( $license_questions );
		$result['questions_license_25'] = $license_count;

		if ( 25 !== $license_count ) {
			$result['message'] = sprintf( 'invalid_question_bank_count:license_25 expected 25 got %d', $license_count );
			return $result;
		}

		$defs = array(
			array(
				'quiz_type' => 'license_25',
				'title'     => 'LMFT Practice Act Module — 25-Question Assessment',
				'sort'      => 1,
				'time'      => 40,
				'questions' => $license_questions,
				'key'       => 'license_25',
			),
		);

		for ( $wb = 1; $wb <= 9; ++$wb ) {
			$defs[] = array(
				'quiz_type' => 'wb' . $wb . '_bank',
				'title'     => sprintf( 'Workbook %d — Workbook Assessment [Placeholder]', $wb ),
				'sort'      => 10 + ( $wb * 10 ),
				'time'      => 0,
				'questions' => array(),
				'key'       => 'wb' . $wb . '_bank',
			);
		}

		$defs[] = array(
			'quiz_type' => 'practice_a',
			'title'     => 'Practice Examination A — 50-Question Assessment',
			'sort'      => 200,
			'time'      => 60,
			'questions' => array(),
			'key'       => 'practice_a',
		);
		$defs[] = array(
			'quiz_type' => 'practice_b',
			'title'     => 'Practice Examination B — 50-Question Assessment',
			'sort'      => 210,
			'time'      => 60,
			'questions' => array(),
			'key'       => 'practice_b',
		);
		$defs[] = array(
			'quiz_type' => 'comprehensive_final',
			'title'     => 'Comprehensive Final Examination — 100-Question Assessment',
			'sort'      => 220,
			'time'      => 120,
			'questions' => array(),
			'key'       => 'comprehensive_final',
		);

		$written = 0;
		foreach ( $defs as $def ) {
			$quiz_id = self::replace_form_quiz(
				$course_id,
				(string) $def['quiz_type'],
				(string) $def['title'],
				(int) $def['sort'],
				(array) $def['questions'],
				(int) $def['time']
			);
			if ( $quiz_id ) {
				++$written;
				if ( ! empty( $def['key'] ) ) {
					$result[ (string) $def['key'] ] = $quiz_id;
				}
			}
		}

		$result['quizzes'] = $written;
		$result['ok']      = $written > 0 && ! empty( $result['license_25'] );
		$result['message'] = $result['ok'] ? 'synced' : 'quiz_write_failed';

		return $result;
	}

	/**
	 * Write placeholder lesson HTML and empty flashcard deck JSON if missing.
	 *
	 * @return array{lessons:int,flashcards:bool}
	 */
	public static function ensure_placeholder_assets() {
		$base = CTA_PLUGIN_DIR . self::MATERIALS_REL;
		wp_mkdir_p( $base . 'lessons' );
		wp_mkdir_p( $base . 'study-tools' );

		$written = 0;
		$written += self::write_orientation_lessons( false );

		for ( $wb = 1; $wb <= 9; ++$wb ) {
			$path = $base . 'lessons/wb' . sprintf( '%02d', $wb ) . '.html';
			if ( is_readable( $path ) ) {
				continue;
			}
			file_put_contents( $path, self::build_workbook_html( $wb ) );
			++$written;
		}

		$flash_path = $base . 'study-tools/flashcard-study-center.json';
		$flash_ok   = false;
		if ( ! is_readable( $flash_path ) ) {
			$payload = array(
				'program' => 'lmft-law-ethics',
				'title'   => 'LMFT California Law & Ethics — Flashcard Study Center',
				'version' => '1.0',
				'domains' => array(),
				'cards'   => array(),
			);
			file_put_contents( $flash_path, wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n" );
			$flash_ok = true;
		}

		return array(
			'lessons'    => $written,
			'flashcards' => $flash_ok || is_readable( $flash_path ),
		);
	}

	/**
	 * Full scaffold sync (Start Here + license module + workbook placeholders + assessments).
	 *
	 * @param bool $force Re-run even if seeded.
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

		$assets    = self::ensure_placeholder_assets();
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
		$assessments = self::sync_assessments( $course_id );

		$counts = array(
			'modules_created'      => (int) ( $modules['created'] ?? 0 ),
			'modules_updated'      => (int) ( $modules['updated'] ?? 0 ),
			'module_total'         => count( $modules['modules'] ?? array() ),
			'quiz_shells'          => (int) ( $assessments['quizzes'] ?? 0 ),
			'lessons_written'      => (int) ( $assets['lessons'] ?? 0 ),
			'license_module_html'  => is_readable( CTA_PLUGIN_DIR . self::MATERIALS_REL . 'lessons/license-module.html' ) ? 1 : 0,
			'license_25_quiz_id'   => (int) ( $assessments['license_25'] ?? 0 ),
			'questions_license_25' => (int) ( $assessments['questions_license_25'] ?? 0 ),
		);

		$ok = ! empty( $assessments['ok'] )
			&& $counts['module_total'] >= 16
			&& 1 === $counts['license_module_html']
			&& 25 === $counts['questions_license_25'];

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
	 * Self-heal when the course exists but modules were never seeded.
	 *
	 * @return void
	 */
	public static function maybe_heal_incomplete_content() {
		if ( get_transient( 'cta_lmft_le_heal_lock' ) ) {
			return;
		}

		$seed = get_option( self::SEED_OPTION );
		if ( is_array( $seed ) && ! empty( $seed['course_id'] )
			&& (int) ( $seed['counts']['module_total'] ?? 0 ) >= 16
			&& (int) ( $seed['counts']['questions_license_25'] ?? 0 ) >= 25 ) {
			return;
		}

		$course = self::find_course();
		if ( ! $course ) {
			return;
		}

		$modules_count = isset( $course->modules_count ) ? (int) $course->modules_count : 0;
		if ( $modules_count >= 16 && is_readable( CTA_PLUGIN_DIR . self::MATERIALS_REL . 'lessons/license-module.html' ) ) {
			return;
		}

		set_transient( 'cta_lmft_le_heal_lock', 1, 10 * MINUTE_IN_SECONDS );
		self::sync( true );
	}

	/**
	 * Apply Point 6 website/LMS copy (description, meta, Start Here, Program Close).
	 * Keeps course draft / launch-pending — does not publish or open checkout.
	 *
	 * @param bool $force Re-apply even if already seeded.
	 * @return array{ok:bool,course_id:int,message:string}
	 */
	public static function apply_website_lms_copy( $force = false ) {
		if ( ! $force && get_option( self::COPY_SEED_OPTION ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'message'   => 'already_seeded',
			);
		}

		if ( class_exists( 'CTA_Database' ) ) {
			CTA_Database::ensure_tables();
		}

		self::write_orientation_lessons( true );
		$course_id = self::ensure_program();
		if ( ! $course_id ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'message'   => 'ensure_program_failed',
			);
		}

		$modules = self::sync_modules( $course_id );
		self::sync_assessments( $course_id );

		update_option(
			self::COPY_SEED_OPTION,
			array(
				'at'           => current_time( 'mysql' ),
				'course_id'    => $course_id,
				'module_total' => count( $modules['modules'] ?? array() ),
				'copy_version' => '1.1',
			),
			false
		);

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'message'   => 'synced',
		);
	}

	/**
	 * Syllabus / SEO meta from approved copy package.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_syllabus_meta() {
		if ( class_exists( 'CTA_Lmft_Law_Ethics_Copy' ) ) {
			return CTA_Lmft_Law_Ethics_Copy::syllabus_meta();
		}

		return array(
			'course_code'            => 'CTA-EP-001',
			'hide_course_code_public'=> true,
			'public_title'           => self::PUBLIC_TITLE,
			'short_description'      => 'Prepare for the California LMFT Law and Ethics Examination with a self-paced, six-month program designed for AMFTs and other eligible LMFT applicants.',
			'course_classification'  => 'Exam Preparation Only — No CE Credit',
			'instructional_method'   => 'Self-paced and asynchronous',
			'target_audience'        => 'AMFTs and other eligible California LMFT Law and Ethics Examination candidates',
			'seo_title'              => 'California LMFT Law & Ethics Exam Preparation | CTA',
			'meta_description'       => 'Prepare for the California LMFT Law and Ethics Examination with nine workbooks, an LMFT Practice Act module, original assessments, detailed rationales, practice exams, 807 flashcards, and six months of access.',
			'primary_cta'            => 'Start LMFT Law & Ethics Exam Preparation',
			'page_badge'             => 'Exam Preparation Only — No CE Credit',
			'educational_notice'     => 'This is an exam-preparation program. It does not provide continuing education credit, require a CE evaluation, or issue a CE certificate.',
			'launch_status'          => 'draft_pending_testing',
			'launch_pending_testing' => true,
			'development_draft'      => true,
			'open_access_exam_prep'  => true,
			'content_pending'        => false,
			'scaffold_only'          => false,
			'publicly_purchasable'   => false,
			'catalog_status'         => 'Under Review',
		);
	}

	/**
	 * Learning objectives derived from Final Study Check / program goals.
	 *
	 * @return string[]
	 */
	private static function get_learning_objectives() {
		return array(
			'Identify the controlling legal or ethical issue and apply LMFT- and AMFT-specific rules.',
			'Distinguish professional roles (LMFT, AMFT, trainee, applicant, supervisor, employer) before choosing an action.',
			'Locate the current controlling source before relying on memory or a generalized ethical principle.',
			'Distinguish a legal duty from an ethical best practice and identify when both apply.',
			'Recognize timing words such as FIRST, NEXT, BEST, MOST, INITIAL, and EXCEPT on examination items.',
			'Use detailed option-by-option rationales and remediation tools to repair weak areas after each assessment.',
		);
	}

	/**
	 * Program description HTML (approved long copy + included + who for).
	 *
	 * @return string
	 */
	private static function get_program_description_html() {
		if ( class_exists( 'CTA_Lmft_Law_Ethics_Copy' ) ) {
			return CTA_Lmft_Law_Ethics_Copy::program_description_html();
		}
		return '<p>CTA LMFT California Law &amp; Ethics Exam Preparation Program.</p>';
	}

	/**
	 * Sixteen module titles in approved learning sequence order.
	 *
	 * @return array<int,array{title:string,kind:string,workbook_num?:int}>
	 */
	private static function get_module_definitions() {
		$wb_titles = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::workbook_titles()
			: array();

		$defs = array(
			array(
				'title' => 'Start Here: Program Orientation',
				'kind'  => 'start',
			),
			array(
				'title' => 'LMFT Practice Act, AMFT Professional Identity & California Examination Distinctions',
				'kind'  => 'license',
			),
		);

		for ( $wb = 1; $wb <= 9; ++$wb ) {
			$topic  = isset( $wb_titles[ $wb ] ) ? (string) $wb_titles[ $wb ] : 'California Law and Ethics';
			$defs[] = array(
				'title'        => sprintf( 'Workbook %d: %s', $wb, $topic ),
				'kind'         => 'workbook',
				'workbook_num' => $wb,
			);
		}

		$defs[] = array(
			'title' => 'Practice Examination A',
			'kind'  => 'practice_a',
		);
		$defs[] = array(
			'title' => 'Practice Examination B',
			'kind'  => 'practice_b',
		);
		$defs[] = array(
			'title' => 'Comprehensive Final Examination',
			'kind'  => 'final',
		);
		$defs[] = array(
			'title' => 'Study Center and Toolkits',
			'kind'  => 'study',
		);
		$defs[] = array(
			'title' => 'Program Close',
			'kind'  => 'close',
		);

		return $defs;
	}

	/**
	 * Write Start Here + Program Close lesson HTML from approved copy.
	 *
	 * @param bool $force Overwrite existing files.
	 * @return int Files written.
	 */
	public static function write_orientation_lessons( $force = false ) {
		$base = CTA_PLUGIN_DIR . self::MATERIALS_REL . 'lessons/';
		wp_mkdir_p( $base );
		$written = 0;

		$start_path = $base . 'start-here.html';
		if ( $force || ! is_readable( $start_path ) ) {
			file_put_contents( $start_path, self::build_start_here_html() );
			++$written;
		}

		$close_path = $base . 'program-close.html';
		if ( $force || ! is_readable( $close_path ) ) {
			file_put_contents( $close_path, self::build_program_close_html() );
			++$written;
		}

		return $written;
	}

	/**
	 * Start Here lesson HTML (approved Welcome + sequence + assessment use + support).
	 *
	 * @return string
	 */
	private static function build_start_here_html() {
		$welcome = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::start_here_welcome()
			: array();
		$sequence = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::learning_sequence()
			: array();
		$steps = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::assessment_instructions()
			: array();
		$support = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::support_access_notice_template()
			: '';

		$html  = '<article class="cta-lesson-article" data-program="lmft-law-ethics" data-workbook="0">' . "\n";
		$html .= '<h2 class="cta-lesson-h2">Welcome</h2>' . "\n";
		foreach ( $welcome as $p ) {
			$html .= '<p class="cta-lesson-p">' . esc_html( $p ) . '</p>' . "\n";
		}

		$html .= '<h2 class="cta-lesson-h2">Recommended Learning Sequence</h2>' . "\n";
		$html .= '<div class="cta-lesson-table-wrap"><table class="cta-lesson-table"><thead><tr><th>Unit</th><th>Title</th><th>Learner Action</th></tr></thead><tbody>' . "\n";
		foreach ( $sequence as $row ) {
			$html .= '<tr><td>' . esc_html( (string) $row['unit'] ) . '</td><td>' . esc_html( (string) $row['title'] ) . '</td><td>' . esc_html( (string) $row['action'] ) . '</td></tr>' . "\n";
		}
		$html .= '</tbody></table></div>' . "\n";

		$html .= '<h2 class="cta-lesson-h2">How to Use Each Assessment</h2>' . "\n";
		$html .= '<ol class="cta-lesson-ol">' . "\n";
		foreach ( $steps as $step ) {
			$html .= '<li class="cta-lesson-li">' . esc_html( $step ) . '</li>' . "\n";
		}
		$html .= '</ol>' . "\n";

		$html .= '<h2 class="cta-lesson-h2">Support and Access Notice</h2>' . "\n";
		$html .= '<p class="cta-lesson-p">' . esc_html( $support ) . '</p>' . "\n";
		$html .= '</article>' . "\n";

		return $html;
	}

	/**
	 * Program Close lesson HTML.
	 *
	 * @return string
	 */
	private static function build_program_close_html() {
		$paras = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::program_close_paragraphs()
			: array();
		$check = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::final_study_check()
			: array();

		$html  = '<article class="cta-lesson-article" data-program="lmft-law-ethics" data-workbook="close">' . "\n";
		$html .= '<h2 class="cta-lesson-h2">Program Close</h2>' . "\n";
		foreach ( $paras as $p ) {
			$html .= '<p class="cta-lesson-p">' . esc_html( $p ) . '</p>' . "\n";
		}
		$html .= '<h2 class="cta-lesson-h2">Final Study Check</h2>' . "\n";
		$html .= '<ul class="cta-lesson-ul">' . "\n";
		foreach ( $check as $item ) {
			$html .= '<li class="cta-lesson-li">' . esc_html( $item ) . '</li>' . "\n";
		}
		$html .= '</ul>' . "\n";
		$html .= '</article>' . "\n";

		return $html;
	}

	/**
	 * Placeholder workbook lesson HTML.
	 *
	 * @param int $workbook_num Workbook number 1–9.
	 * @return string
	 */
	private static function build_workbook_html( $workbook_num ) {
		$workbook_num = absint( $workbook_num );
		$titles       = class_exists( 'CTA_Lmft_Law_Ethics_Copy' )
			? CTA_Lmft_Law_Ethics_Copy::workbook_titles()
			: array();
		$topic        = isset( $titles[ $workbook_num ] ) ? (string) $titles[ $workbook_num ] : 'California Law and Ethics';
		$notice       = sprintf(
			'Workbook %d instructional body content is pending full client workbook upload. Module title and sequence follow the approved program map: %s.',
			$workbook_num,
			$topic
		);

		return '<article class="cta-lesson-article" data-program="lmft-law-ethics" data-workbook="' . $workbook_num . '" data-placeholder="1">'
			. '<div class="cta-lesson-table-wrap"><table class="cta-lesson-table"><tbody><tr><td>' . esc_html( $notice ) . '</td></tr></tbody></table></div>'
			. '<h2 class="cta-lesson-h2">How to Use This Workbook</h2>'
			. '<p class="cta-lesson-p">Read the workbook, complete the candidate assessment without opening the controlled rationale file, then review option-level rationales and remediation.</p>'
			. '<h2 class="cta-lesson-h2">Key Concepts</h2>'
			. '<p class="cta-lesson-p">Core workbook content for this unit will appear here when the candidate edition file is loaded.</p>'
			. '<h2 class="cta-lesson-h2">Chapter Summary</h2>'
			. '<p class="cta-lesson-p">Summary content pending workbook file delivery.</p>'
			. '<h2 class="cta-lesson-h2">Knowledge Check</h2>'
			. '<p class="cta-lesson-p">Use the workbook assessment in the LMS after reading the candidate edition.</p>'
			. '</article>';
	}

	/**
	 * Create/update a quiz shell (questions optional).
	 *
	 * @param int    $course_id  Course ID.
	 * @param string $quiz_type  Quiz type key.
	 * @param string $title      Quiz title.
	 * @param int    $sort       Sort order.
	 * @param array  $questions  Question rows.
	 * @param int    $time_limit Time limit minutes.
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
			foreach ( (array) CTA_Database::get_quizzes_by_course( $course_id, false ) as $row ) {
				if ( $quiz_type === (string) ( $row->quiz_type ?? '' ) ) {
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
					'passing_score'   => self::READINESS_BENCHMARK,
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
					'passing_score'   => self::READINESS_BENCHMARK,
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

		$q_table = $wpdb->prefix . 'cta_quiz_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $q_table, array( 'quiz_id' => $quiz_id ), array( '%d' ) );

		$text = function_exists( 'cta_lms_sanitize_utf8_text' ) ? 'cta_lms_sanitize_utf8_text' : null;
		foreach ( $questions as $index => $question ) {
			$correct = isset( $question['correct_option'] ) ? strtolower( (string) $question['correct_option'] ) : 'a';
			$correct = in_array( $correct, array( 'a', 'b', 'c', 'd' ), true ) ? $correct : 'a';
			$qt      = (string) ( $question['question_text'] ?? '' );
			$oa      = (string) ( $question['option_a'] ?? '' );
			$ob      = (string) ( $question['option_b'] ?? '' );
			$oc      = (string) ( $question['option_c'] ?? '' );
			$od      = (string) ( $question['option_d'] ?? '' );
			$ex      = (string) ( $question['explanation'] ?? '' );
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
	 * Copy approved toolkit DOCX files from the client handoff package when present.
	 *
	 * @return array{copied:int,skipped:int,missing:array<int,string>}
	 */
	public static function copy_toolkit_assets_from_package() {
		$src_dir = CTA_PLUGIN_DIR . self::PACKAGE_TOOLKIT_DIR;
		$dest_dir = CTA_PLUGIN_DIR . self::MATERIALS_REL . 'study-tools/';
		wp_mkdir_p( $dest_dir );

		$copied  = 0;
		$skipped = 0;
		$missing = array();

		foreach ( self::get_toolkit_material_definitions() as $def ) {
			$filename = basename( str_replace( '\\', '/', (string) $def['file'] ) );
			$source   = $src_dir . $filename;
			$dest     = $dest_dir . $filename;

			if ( ! is_readable( $source ) ) {
				if ( ! is_readable( $dest ) ) {
					$missing[] = $filename;
				}
				continue;
			}

			if ( is_readable( $dest ) && filesize( $dest ) === filesize( $source ) ) {
				++$skipped;
				continue;
			}

			if ( @copy( $source, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				++$copied;
			} else {
				$missing[] = $filename . ' (copy failed)';
			}
		}

		return compact( 'copied', 'skipped', 'missing' );
	}

	/**
	 * Attach the six approved Study Center toolkits (opaque DOCX downloads).
	 *
	 * @param int $course_id Course ID.
	 * @return array{attached:int,updated:int,skipped:int,missing:array}
	 */
	public static function sync_toolkit_materials( $course_id ) {
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

		$order_index = 500;
		foreach ( self::get_toolkit_material_definitions() as $def ) {
			$title = sanitize_text_field( (string) $def['title'] );
			$rel   = ltrim( str_replace( '\\', '/', (string) $def['file'] ), '/' );
			$source = CTA_PLUGIN_DIR . self::MATERIALS_REL . $rel;

			if ( ! is_readable( $source ) ) {
				$missing[] = $rel;
				++$skipped;
				++$order_index;
				continue;
			}

			$existing_id = self::find_resource_id( $course_id, $title, $rel );

			if ( $existing_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->prefix . 'cta_downloadable_resources',
					array(
						'title'                  => $title,
						'module_id'              => 0,
						'order_index'            => (int) $order_index,
						'is_practice_test'       => 0,
						'unlock_after_quiz_type' => '',
					),
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

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$ok = $wpdb->insert(
				$wpdb->prefix . 'cta_downloadable_resources',
				array(
					'course_id'              => $course_id,
					'module_id'              => 0,
					'attachment_id'          => 0,
					'title'                  => $title,
					'file_url'               => $imported['file_url'],
					'file_path'              => $imported['relative_path'],
					'file_type'              => $imported['file_type'],
					'order_index'            => (int) $order_index,
					'is_practice_test'       => 0,
					'unlock_after_quiz_type' => '',
				),
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
	 * Sync six Study Center toolkits for CTA-EP-001.
	 *
	 * @param bool $force Re-run even if already seeded.
	 * @return array{ok:bool,course_id:int,message:string,counts:array}
	 */
	public static function sync_toolkits( $force = false ) {
		if ( ! $force && get_option( self::TOOLKIT_SEED_OPTION ) ) {
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

		$copy      = self::copy_toolkit_assets_from_package();
		$course_id = self::ensure_program();
		if ( ! $course_id ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'message'   => 'ensure_program_failed',
				'counts'    => array(),
			);
		}

		$materials = self::sync_toolkit_materials( $course_id );
		$defs      = self::get_toolkit_material_definitions();
		$present   = 0;

		foreach ( $defs as $def ) {
			$rel = ltrim( str_replace( '\\', '/', (string) $def['file'] ), '/' );
			if ( is_readable( CTA_PLUGIN_DIR . self::MATERIALS_REL . $rel ) ) {
				++$present;
			}
		}

		$synced_count = (int) ( $materials['attached'] ?? 0 ) + (int) ( $materials['updated'] ?? 0 );
		$ok           = 6 === $present && $synced_count >= 6 && empty( $materials['missing'] );

		$counts = array(
			'toolkits_expected'   => 6,
			'toolkits_on_disk'    => $present,
			'toolkits_attached'   => (int) ( $materials['attached'] ?? 0 ),
			'toolkits_updated'    => (int) ( $materials['updated'] ?? 0 ),
			'toolkits_skipped'    => (int) ( $materials['skipped'] ?? 0 ),
			'package_files_copied'=> (int) ( $copy['copied'] ?? 0 ),
			'materials_missing'   => $materials['missing'] ?? array(),
			'package_missing'     => $copy['missing'] ?? array(),
		);

		if ( $ok ) {
			update_option(
				self::TOOLKIT_SEED_OPTION,
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
			'message'   => $ok ? 'synced' : ( 6 === $present ? 'resource_sync_incomplete' : 'toolkit_files_missing' ),
			'counts'    => $counts,
		);
	}

	/**
	 * Six approved LMFT Law & Ethics study toolkits (Study Center sibling to flashcards).
	 *
	 * @return array<int,array{file:string,title:string}>
	 */
	public static function get_toolkit_material_definitions() {
		return array(
			array(
				'file'  => 'study-tools/CTA_LE_LMFT_45_Chapter_Exam_Traps_and_Correction_Rules_Toolkit_v1.1_Corrected.docx',
				'title' => '45-Chapter Exam Traps & Correction Rules Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LE_LMFT_45_Chapter_Master_Study_Map_and_Readiness_Checklist_Toolkit_v1.1_Corrected.docx',
				'title' => '45-Chapter Master Study Map & Readiness Checklist Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LE_LMFT_Exam_Strategy_and_Study_Planning_Toolkit_v1.1_Corrected.docx',
				'title' => 'Exam Strategy & Study Planning Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LE_LMFT_High_Yield_California_Ethics_Decision_Guides_Toolkit_v1.1_Corrected.docx',
				'title' => 'High-Yield California Ethics Decision Guides Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LE_LMFT_High_Yield_California_Law_Decision_Guides_Toolkit_v1.1_Corrected.docx',
				'title' => 'High-Yield California Law Decision Guides Toolkit',
			),
			array(
				'file'  => 'study-tools/CTA_LE_LMFT_High_Yield_Numbers_Timelines_and_Trigger_Words_Toolkit_v1.1_Corrected.docx',
				'title' => 'High-Yield Numbers, Timelines & Trigger Words Toolkit',
			),
		);
	}

	/**
	 * Find existing resource by title or filename.
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
	 * Load secured quiz seed questions.
	 *
	 * @param string $file Basename under includes/quiz-seeds/.
	 * @return array<int,array<string,string>>
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
}

}
