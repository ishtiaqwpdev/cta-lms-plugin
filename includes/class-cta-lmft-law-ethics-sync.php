<?php
/**
 * LMFT California Law & Ethics Exam Preparation — structural scaffold sync.
 *
 * Seeds the standardized Exam Prep dashboard shell (modules, placeholder lessons,
 * assessment quiz shells) until client content is delivered. Does not overwrite
 * an existing course marketing description or price.
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

	const SEED_OPTION   = 'cta_lmft_law_ethics_seeded_1_0_250';
	const SLUG          = 'california-law-ethics-exam-preparation';
	const TITLE         = 'CTA LMFT California Law & Ethics Exam Preparation Program';
	const PUBLIC_TITLE  = 'LMFT California Law & Ethics Exam Preparation';
	const PRICE         = 199.00;
	const ACCESS_MONTHS = 6;
	const MATERIALS_REL = 'assets/course-materials/lmft-law-ethics/';

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

		$table  = $wpdb->prefix . 'cta_courses';
		$course = self::find_course();
		$meta   = self::get_syllabus_meta();

		if ( $course ) {
			$course_id = (int) $course->id;
			$fields    = array(
				'slug'                 => self::SLUG,
				'product_type'         => 'exam_prep',
				'category'             => 'Exam Preparation',
				'access_period_months' => (int) self::ACCESS_MONTHS,
				'awards_ce_hours'      => 0,
				'has_ce_certificate'   => 0,
				'ce_hours'             => 0,
			);
			$fields = class_exists( 'CTA_Course_Catalog' )
				? CTA_Course_Catalog::prepare_exam_prep_course_row( $fields, $meta, $course )
				: array_merge( $fields, array( 'syllabus_meta' => wp_json_encode( $meta ) ) );

			// Preserve marketing page copy and commercial terms already configured in admin.
			unset( $fields['description'], $fields['price'], $fields['title'], $fields['learning_objectives'], $fields['status'] );

			$formats = array();
			foreach ( array_keys( $fields ) as $key ) {
				$formats[] = in_array( $key, array( 'access_period_months', 'awards_ce_hours', 'has_ce_certificate', 'ce_hours' ), true ) ? '%d' : '%s';
			}

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

		$description = '<p>LMFT California Law &amp; Ethics Exam Preparation scaffold — dashboard structure pending final client content.</p>';
		$fields      = array(
			'title'                => self::TITLE,
			'slug'                 => self::SLUG,
			'description'          => $description,
			'ce_hours'             => 0,
			'price'                => (float) self::PRICE,
			'category'             => 'Exam Preparation',
			'learning_objectives'  => wp_json_encode( array() ),
			'status'               => 'draft',
			'product_type'         => 'exam_prep',
			'access_period_months' => (int) self::ACCESS_MONTHS,
			'awards_ce_hours'      => 0,
			'has_ce_certificate'   => 0,
			'modules_count'        => 0,
		);
		$fields = class_exists( 'CTA_Course_Catalog' )
			? CTA_Course_Catalog::prepare_exam_prep_course_row( $fields, $meta, null )
			: array_merge( $fields, array( 'syllabus_meta' => wp_json_encode( $meta ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			$fields,
			array( '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
		);

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
			}
		}

		foreach ( $defs as $index => $def ) {
			$title     = sanitize_text_field( (string) $def['title'] );
			$order     = (int) $index;
			$module_id = 0;
			$kind      = sanitize_key( (string) ( $def['kind'] ?? 'workbook' ) );
			$wb_num    = isset( $def['workbook_num'] ) ? absint( $def['workbook_num'] ) : 0;

			if ( 'start' === $kind ) {
				$desc = 'Program orientation and recommended study path. Open before the license-specific module and Workbook 1.';
			} elseif ( 'license' === $kind ) {
				$desc = 'Required LMFT/AMFT license-specific module. The separate 25-question assessment follows this module.';
			} else {
				$desc = '[Placeholder] Workbook shell for structural testing. Final workbook content pending client delivery.';
			}

			$match = null;
			if ( 'start' === $kind ) {
				$match = $start_here_row;
			} elseif ( 'license' === $kind ) {
				$match = $license_row;
			} elseif ( $wb_num >= 1 && $wb_num <= 9 ) {
				$match = $by_prefix[ $wb_num ] ?? null;
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
			'title'     => 'Practice Examination A — 50-Question Assessment [Placeholder]',
			'sort'      => 200,
			'time'      => 60,
			'questions' => array(),
			'key'       => 'practice_a',
		);
		$defs[] = array(
			'quiz_type' => 'practice_b',
			'title'     => 'Practice Examination B — 50-Question Assessment [Placeholder]',
			'sort'      => 210,
			'time'      => 60,
			'questions' => array(),
			'key'       => 'practice_b',
		);
		$defs[] = array(
			'quiz_type' => 'comprehensive_final',
			'title'     => 'Comprehensive Final — 100-Question Examination [Placeholder]',
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

		$start_path = $base . 'lessons/start-here.html';
		if ( ! is_readable( $start_path ) ) {
			file_put_contents( $start_path, self::build_start_here_html() );
			++$written;
		}

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
			&& $counts['module_total'] >= 11
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
			&& (int) ( $seed['counts']['module_total'] ?? 0 ) >= 11
			&& (int) ( $seed['counts']['questions_license_25'] ?? 0 ) >= 25 ) {
			return;
		}

		$course = self::find_course();
		if ( ! $course ) {
			return;
		}

		$modules_count = isset( $course->modules_count ) ? (int) $course->modules_count : 0;
		if ( $modules_count >= 11 && is_readable( CTA_PLUGIN_DIR . self::MATERIALS_REL . 'lessons/license-module.html' ) ) {
			return;
		}

		set_transient( 'cta_lmft_le_heal_lock', 1, 10 * MINUTE_IN_SECONDS );
		self::sync( true );
	}

	/**
	 * Syllabus meta for scaffold state.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_syllabus_meta() {
		return array(
			'course_code'            => 'CTA-EP-001',
			'public_title'           => self::PUBLIC_TITLE,
			'short_description'      => '[Placeholder scaffold] LMFT California Law & Ethics dashboard structure — final workbook and assessment content pending client delivery.',
			'course_classification'  => 'Exam Preparation Only — No CE Credit',
			'instructional_method'   => 'Self-paced asynchronous',
			'target_audience'        => 'California LMFT and MFT associate candidates',
			'seo_title'              => 'LMFT California Law & Ethics Exam Prep | CTA',
			'meta_description'       => 'LMFT California Law & Ethics exam preparation program scaffold.',
			'primary_cta'            => 'Begin Your Law & Ethics Exam Preparation',
			'page_badge'             => 'Exam Preparation Only — No CE Credit',
			'educational_notice'     => 'Exam Preparation Only — No CE Credit. Placeholder scaffold — not launch-ready until client content is loaded.',
			'launch_status'          => 'draft_pending_testing',
			'launch_pending_testing' => true,
			'development_draft'      => true,
			'open_access_exam_prep'  => true,
			'content_pending'        => true,
			'scaffold_only'          => true,
		);
	}

	/**
	 * Start Here + license module + nine placeholder workbook module titles.
	 *
	 * @return array<int,array{title:string,kind:string,workbook_num?:int}>
	 */
	private static function get_module_definitions() {
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
			$defs[] = array(
				'title'        => sprintf( 'Workbook %d: [Placeholder Title — Content Pending]', $wb ),
				'kind'         => 'workbook',
				'workbook_num' => $wb,
			);
		}

		return $defs;
	}

	/**
	 * Placeholder Start Here lesson HTML.
	 *
	 * @return string
	 */
	private static function build_start_here_html() {
		return <<<'HTML'
<article class="cta-lesson-article" data-program="lmft-law-ethics" data-workbook="0" data-placeholder="1">
<div class="cta-lesson-table-wrap"><table class="cta-lesson-table"><tbody><tr><td>PLACEHOLDER — CONTENT PENDING. This Start Here page is a structural shell only. Final LMFT California Law &amp; Ethics orientation content will replace this page when provided by the client.</td></tr></tbody></table></div>
<h2 class="cta-lesson-h2">How to Use This Program</h2>
<p class="cta-lesson-p">[Placeholder] Program orientation overview pending client content delivery.</p>
<h2 class="cta-lesson-h2">Key Concepts</h2>
<p class="cta-lesson-p">[Placeholder] License-specific orientation content pending.</p>
<h2 class="cta-lesson-h2">Chapter Summary</h2>
<p class="cta-lesson-p">[Placeholder] Summary section pending.</p>
<h2 class="cta-lesson-h2">Knowledge Check</h2>
<p class="cta-lesson-p">[Placeholder] Orientation knowledge check pending.</p>
</article>
HTML;
	}

	/**
	 * Placeholder workbook lesson HTML.
	 *
	 * @param int $workbook_num Workbook number 1–9.
	 * @return string
	 */
	private static function build_workbook_html( $workbook_num ) {
		$workbook_num = absint( $workbook_num );
		$notice       = sprintf(
			'PLACEHOLDER — CONTENT PENDING. Workbook %d is a structural shell only. Final LMFT California Law &amp; Ethics workbook content will replace this page when provided by the client.',
			$workbook_num
		);

		return '<article class="cta-lesson-article" data-program="lmft-law-ethics" data-workbook="' . $workbook_num . '" data-placeholder="1">'
			. '<div class="cta-lesson-table-wrap"><table class="cta-lesson-table"><tbody><tr><td>' . esc_html( $notice ) . '</td></tr></tbody></table></div>'
			. '<h2 class="cta-lesson-h2">How to Use This Workbook</h2>'
			. '<p class="cta-lesson-p">[Placeholder] Workbook ' . $workbook_num . ' overview section pending client content delivery.</p>'
			. '<h2 class="cta-lesson-h2">Key Concepts</h2>'
			. '<p class="cta-lesson-p">[Placeholder] Core content section pending.</p>'
			. '<h2 class="cta-lesson-h2">Chapter Summary</h2>'
			. '<p class="cta-lesson-p">[Placeholder] Summary section pending.</p>'
			. '<h2 class="cta-lesson-h2">Knowledge Check</h2>'
			. '<p class="cta-lesson-p">[Placeholder] Knowledge check items will be added with final workbook content.</p>'
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
