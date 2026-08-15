<?php
/**
 * CTA LCSW ASWB Clinical Exam Preparation — program, modules, materials, and Form A/B sync.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lcsw_Aswb_Sync
 */
if ( ! class_exists( 'CTA_Lcsw_Aswb_Sync' ) ) {

class CTA_Lcsw_Aswb_Sync {

	const SEED_OPTION   = 'cta_lcsw_aswb_clinical_seeded_1_0_154';
	const SLUG          = 'lcsw-aswb-clinical-exam-preparation';
	const TITLE         = 'CTA LCSW ASWB Clinical Exam Preparation Program';
	const PUBLIC_TITLE  = 'LCSW ASWB Clinical Exam Preparation';
	const LEGACY_SLUG   = 'lcsw-california-clinical-exam-preparation';
	const LEGACY_TITLE  = 'LCSW California Clinical Exam Preparation';
	const LEGACY_FORMAL = 'CTA LCSW California Clinical Exam Preparation Program';
	const THUMBNAIL_REL = 'assets/course-images/lcsw-aswb/CTA_LCSW_ASWB_Clinical_Exam_Preparation_Program_Website_Image_v1.0.png';
	const IDENTITY_HEAL = 'cta_lcsw_aswb_identity_healed_1_0_206';
	const PRICE         = 249.00;
	const ACCESS_MONTHS = 6;
	const MATERIALS_REL = 'assets/course-materials/lcsw-aswb/';

	/**
	 * Find the LCSW ASWB course by new/legacy slug or title.
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
			array( 'title', self::LEGACY_FORMAL ),
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
	 * Create or update the exam_prep program with approved commercial + syllabus fields.
	 *
	 * @return int Course ID or 0.
	 */
	public static function ensure_program() {
		global $wpdb;

		$table  = $wpdb->prefix . 'cta_courses';
		$course = self::find_course();

		$description = self::get_program_description_html();
		$objectives  = wp_json_encode( self::get_learning_objectives() );
		$meta_array  = self::get_syllabus_meta();

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
			? CTA_Course_Catalog::prepare_exam_prep_course_row( $fields, $meta_array, $course )
			: array_merge( $fields, array( 'syllabus_meta' => wp_json_encode( $meta_array ) ) );

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

		$desc = 'Complete the student workbook and paired 17-question bank for this unit.';

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
						'course_id'   => $course_id,
						'title'       => $title,
						'description' => $desc,
						'video_url'   => '',
						'duration_mins' => 0,
						'order_index' => $order,
						'is_locked'   => 0,
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
	 * Attach all student materials (workbooks, banks, simulations, study tools).
	 * Idempotent by friendly title. Form A/B candidate exams gated to modules / Form B readiness;
	 * answer keys gated via unlock_after_quiz_type after each form is submitted.
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
			$title      = sanitize_text_field( (string) $item['title'] );
			$rel        = ltrim( str_replace( '\\', '/', (string) $item['file'] ), '/' );
			$source     = CTA_PLUGIN_DIR . self::MATERIALS_REL . $rel;
			$module_id  = 0;
			$is_practice = ! empty( $item['is_practice_test'] ) ? 1 : 0;
			$unlock      = class_exists( 'CTA_Course_Materials' )
				? CTA_Course_Materials::infer_protected_rationale_unlock_type(
					(object) array(
						'title'     => $title,
						'file_path' => $rel,
						'file_url'  => '',
					)
				)
				: '';
			if ( ! empty( $item['unlock_after_quiz_type'] ) ) {
				$unlock = sanitize_text_field( (string) $item['unlock_after_quiz_type'] );
			}

			if ( class_exists( 'CTA_Course_Materials' )
				&& CTA_Course_Materials::is_admin_restricted_source_path( $source . ' ' . $rel ) ) {
				$missing[] = $rel . ' (admin_restricted)';
				++$skipped;
				++$order_index;
				continue;
			}

			if ( ! empty( $item['workbook_num'] ) ) {
				$wn = (int) $item['workbook_num'];
				$module_id = isset( $module_by_n[ $wn ] ) ? (int) $module_by_n[ $wn ] : 0;
			}

			if ( ! is_readable( $source ) ) {
				$missing[] = $rel;
				++$skipped;
				++$order_index;
				continue;
			}

			$existing_id = self::find_resource_id_by_title( $course_id, $title );

			if ( $existing_id ) {
				$update = array(
					'module_id'               => $module_id,
					'order_index'             => $order_index,
					'is_practice_test'        => $is_practice,
					'unlock_after_quiz_type'  => $unlock,
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->prefix . 'cta_downloadable_resources',
					$update,
					array( 'id' => $existing_id ),
					array( '%d', '%d', '%d', '%s' ),
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
	 * Ensure Form A and Form B quizzes with 122 questions each.
	 *
	 * @param int $course_id Course ID.
	 * @return array{ok:bool,form_a:int,form_b:int,questions_a:int,questions_b:int,message:string}
	 */
	public static function sync_assessments( $course_id ) {
		$course_id = absint( $course_id );

		if ( ! $course_id ) {
			return array(
				'ok'          => false,
				'form_a'      => 0,
				'form_b'      => 0,
				'questions_a' => 0,
				'questions_b' => 0,
				'message'     => 'invalid_course',
			);
		}

		$questions_a = self::load_form_questions( 'a' );
		$questions_b = self::load_form_questions( 'b' );

		if ( 122 !== count( $questions_a ) || 122 !== count( $questions_b ) ) {
			return array(
				'ok'          => false,
				'form_a'      => 0,
				'form_b'      => 0,
				'questions_a' => count( $questions_a ),
				'questions_b' => count( $questions_b ),
				'message'     => 'invalid_question_bank_count',
			);
		}

		$form_a = self::replace_form_quiz(
			$course_id,
			'form_a',
			'Form A — 122-Question Comprehensive Simulation',
			20,
			$questions_a
		);
		$form_b = self::replace_form_quiz(
			$course_id,
			'form_b',
			'Form B — 122-Question Comprehensive Simulation',
			30,
			$questions_b
		);

		if ( ! $form_a || ! $form_b ) {
			return array(
				'ok'          => false,
				'form_a'      => $form_a,
				'form_b'      => $form_b,
				'questions_a' => count( $questions_a ),
				'questions_b' => count( $questions_b ),
				'message'     => 'quiz_write_failed',
			);
		}

		return array(
			'ok'          => true,
			'form_a'      => $form_a,
			'form_b'      => $form_b,
			'questions_a' => 122,
			'questions_b' => 122,
			'message'     => 'synced',
		);
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
			'modules_created'   => (int) ( $modules['created'] ?? 0 ),
			'modules_updated'   => (int) ( $modules['updated'] ?? 0 ),
			'materials_attached'=> (int) ( $materials['attached'] ?? 0 ),
			'materials_updated' => (int) ( $materials['updated'] ?? 0 ),
			'materials_missing' => count( $materials['missing'] ?? array() ),
			'form_a_quiz_id'    => (int) ( $assessments['form_a'] ?? 0 ),
			'form_b_quiz_id'    => (int) ( $assessments['form_b'] ?? 0 ),
			'questions_a'       => (int) ( $assessments['questions_a'] ?? 0 ),
			'questions_b'       => (int) ( $assessments['questions_b'] ?? 0 ),
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
	 * @param string $quiz_type Quiz type (e.g. form_a, form_b).
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

		$quizzes = $wpdb->prefix . 'cta_quizzes';
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
	 * Approved program description HTML (no recorded A/V claims as included media).
	 *
	 * @return string
	 */
	private static function get_program_description_html() {
		$html = '
<p>LCSW ASWB Clinical Exam Preparation is a complete self-paced system for ASWs, LCSW candidates, and other eligible examinees preparing for the ASWB Clinical Social Work Licensing Examination.</p>
<p>The program teaches candidates to combine social work values, self-determination, person-in-environment assessment, clinical judgment, service planning, intervention, advocacy, and systems reasoning when several answers appear plausible. Content and simulations reflect the redesigned examination structure used for testing on or after August 3, 2026, including three content areas and a mixture of three- and four-option questions.</p>
<h3>What Is Included</h3>
<ul>
<li>12 editable and print-optimized LCSW student workbooks</li>
<li>12 paired 17-question workbook banks (204 total practice placements) with complete rationales</li>
<li>A 25-question integrated mini-mock within Workbook 12</li>
<li>2026 Comprehensive Simulation Form A — 122 questions</li>
<li>2026 Comprehensive Simulation Form B — 122 questions</li>
<li>Controlled answer keys and detailed rationales for both simulations (released after each form is submitted)</li>
<li>Mixed three- and four-option question practice</li>
<li>Flashcard collection, quick-reference sheets, readiness tracker, Student FAQ, August 2026 exam-day guide, and 10-, 14-, and 18-week study schedules</li>
</ul>
<p><strong>Written program complete.</strong> Recorded audio and video are not included at launch.</p>
<h3>Important Notices</h3>
<ul>
<li><strong>Exam Preparation Only — No CE Credit.</strong> This program does not provide continuing education hours or a CE certificate.</li>
<li>CTA is an independent educational resource and is not affiliated with or endorsed by ASWB, Pearson VUE, or any state licensing board.</li>
<li>Participation supports examination readiness but does not guarantee passage or determine eligibility. Candidates should follow the blueprint and rules that apply to their actual testing date and jurisdiction.</li>
<li>No recorded audio or video lessons are included in this initial release.</li>
</ul>';

		return wp_kses_post( $html );
	}

	/**
	 * "What Candidates Will Build" learning objectives.
	 *
	 * @return string[]
	 */
	private static function get_learning_objectives() {
		return array(
			'Identify FIRST, NEXT, BEST, MOST, INITIAL, and PRIMARY action cues.',
			'Sequence engagement, assessment, planning, intervention, evaluation, referral, and follow-up.',
			'Apply social work values, self-determination, social justice, and person-in-environment reasoning.',
			'Distinguish the best response from answers that are plausible but premature, overly restrictive, outside scope, or insufficiently collaborative.',
			'Use complete rationales and error logs to repair reasoning rather than memorize answer choices.',
			'Build pacing and stamina under the current 122-question examination structure.',
		);
	}

	/**
	 * Syllabus / SEO meta for the sales and LMS pages.
	 *
	 * @return array
	 */
	private static function get_syllabus_meta() {
		return array(
			'public_title'           => self::PUBLIC_TITLE,
			'short_description'      => 'Twelve social work–specific workbooks, focused practice banks, complete rationales, and two 122-question simulations aligned to the 2026 ASWB Clinical examination structure.',
			'course_classification'  => 'Exam Preparation Only — No CE Credit',
			'instructional_method'   => 'Self-paced asynchronous online program with printable resources',
			'target_audience'        => 'ASWs, LCSW candidates, and other eligible ASWB Clinical examinees',
			'seo_title'              => 'LCSW ASWB Clinical Exam Prep | CTA',
			'meta_description'       => 'Prepare for the 2026 ASWB Clinical exam with 12 workbooks, 204 focused practice questions, complete rationales, a 25-question mini-mock, and two 122-question simulations.',
			'image_alt'              => 'Clinical Training and Supervision Academy LCSW ASWB Clinical Exam Preparation graphic',
			'primary_cta'            => 'Begin Your Clinical Exam Preparation',
			'page_badge'             => 'Exam Preparation • No CE Credit',
			'educational_notice'     => 'Exam Preparation Only — No CE Credit. This program does not award CE hours or a CE certificate. Recorded audio and video are not included at launch. CTA is not affiliated with or endorsed by ASWB, Pearson VUE, or any state licensing board.',
			'launch_status'          => 'draft_pending_testing',
			'launch_pending_testing' => true,
			'development_draft'      => true,
		);
	}

	/**
	 * Twelve workbook module titles (order_index 0–11).
	 *
	 * @return array<int,array{title:string}>
	 */
	private static function get_module_definitions() {
		return array(
			array( 'title' => 'Workbook 1: ASWB Exam Strategy and Applied Reasoning' ),
			array( 'title' => 'Workbook 2: Values, Ethics, Self-Determination, and Social Justice' ),
			array( 'title' => 'Workbook 3: Human Development, Diversity, and Person-in-Environment' ),
			array( 'title' => 'Workbook 4: Clinical Assessment, Interviewing, and Mental Status' ),
			array( 'title' => 'Workbook 5: Diagnosis, Medical Factors, and Psychopharmacology' ),
			array( 'title' => 'Workbook 6: Crisis, Abuse, and Risk Assessment' ),
			array( 'title' => 'Workbook 7: Treatment and Service Planning' ),
			array( 'title' => 'Workbook 8: Clinical Interventions and Trauma-Informed Practice' ),
			array( 'title' => 'Workbook 9: Family, Couple, Group, and Parenting Interventions' ),
			array( 'title' => 'Workbook 10: Case Management, Advocacy, Resources, and Collaboration' ),
			array( 'title' => 'Workbook 11: Practice Evaluation, Research, Supervision, and Administration' ),
			array( 'title' => 'Workbook 12: Integrated Review and 25-Question Mini-Mock' ),
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
				'file'  => 'workbooks/CTA_LCSW_WB1_ASWB_Exam_Strategy_and_Applied_Reasoning_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 1 — ASWB Exam Strategy and Applied Reasoning (Student Workbook)',
			),
			2  => array(
				'file'  => 'workbooks/CTA_LCSW_WB2_Values_Ethics_Self_Determination_and_Social_Justice_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 2 — Values, Ethics, Self-Determination, and Social Justice (Student Workbook)',
			),
			3  => array(
				'file'  => 'workbooks/CTA_LCSW_WB3_Human_Development_Diversity_and_Person_in_Environment_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 3 — Human Development, Diversity, and Person-in-Environment (Student Workbook)',
			),
			4  => array(
				'file'  => 'workbooks/CTA_LCSW_WB4_Clinical_Assessment_Interviewing_and_Mental_Status_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 4 — Clinical Assessment, Interviewing, and Mental Status (Student Workbook)',
			),
			5  => array(
				'file'  => 'workbooks/CTA_LCSW_WB5_Diagnosis_Medical_Factors_and_Psychopharmacology_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 5 — Diagnosis, Medical Factors, and Psychopharmacology (Student Workbook)',
			),
			6  => array(
				'file'  => 'workbooks/CTA_LCSW_WB6_Crisis_Abuse_and_Risk_Assessment_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 6 — Crisis, Abuse, and Risk Assessment (Student Workbook)',
			),
			7  => array(
				'file'  => 'workbooks/CTA_LCSW_WB7_Treatment_and_Service_Planning_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 7 — Treatment and Service Planning (Student Workbook)',
			),
			8  => array(
				'file'  => 'workbooks/CTA_LCSW_WB8_Clinical_Interventions_and_Trauma_Informed_Practice_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 8 — Clinical Interventions and Trauma-Informed Practice (Student Workbook)',
			),
			9  => array(
				'file'  => 'workbooks/CTA_LCSW_WB9_Family_Couple_Group_and_Parenting_Interventions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 9 — Family, Couple, Group, and Parenting Interventions (Student Workbook)',
			),
			10 => array(
				'file'  => 'workbooks/CTA_LCSW_WB10_Case_Management_Advocacy_Resources_and_Collaboration_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 10 — Case Management, Advocacy, Resources, and Collaboration (Student Workbook)',
			),
			11 => array(
				'file'  => 'workbooks/CTA_LCSW_WB11_Practice_Evaluation_Research_Supervision_and_Administration_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 11 — Practice Evaluation, Research, Supervision, and Administration (Student Workbook)',
			),
			12 => array(
				'file'  => 'workbooks/CTA_LCSW_WB12_Integrated_Review_and_25_Question_Mini_Mock_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 12 — Integrated Review and 25-Question Mini-Mock (Student Workbook)',
			),
		);

		foreach ( $workbooks as $n => $wb ) {
			$items[] = array(
				'file'         => $wb['file'],
				'title'        => $wb['title'],
				'workbook_num' => $n,
			);
			$items[] = array(
				'file'             => 'question-banks/CTA_LCSW_WB' . $n . '_17_Question_Bank_v1.0.docx',
				'title'            => 'Workbook ' . $n . ' — 17-Question Practice Bank',
				'workbook_num'     => $n,
				'is_practice_test' => 1,
			);
		}

		$items[] = array(
			'file'                   => 'simulations/CTA_LCSW_2026_Comprehensive_Simulation_Form_A_122_Question_Exam_v1.0.docx',
			'title'                  => 'Form A — 122-Question Comprehensive Simulation',
			'is_practice_test'       => 1,
		);
		$items[] = array(
			'file'                   => 'simulations/CTA_LCSW_2026_Comprehensive_Simulation_Form_B_122_Question_Exam_v1.0.docx',
			'title'                  => 'Form B — 122-Question Comprehensive Simulation',
			'is_practice_test'       => 1,
		);
		$items[] = array(
			'file'                    => 'simulations/CTA_LCSW_2026_Comprehensive_Simulation_Form_A_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			'title'                   => 'Form A — Answer Key and Detailed Rationales',
		);
		$items[] = array(
			'file'                    => 'simulations/CTA_LCSW_2026_Comprehensive_Simulation_Form_B_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			'title'                   => 'Form B — Answer Key and Detailed Rationales',
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LCSW_Clinical_Exam_Preparation_Flashcard_Collection_v1.0.docx',
			'title' => 'Clinical Exam Preparation Flashcard Collection',
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LCSW_Readiness_Self_Assessment_and_Progress_Tracker_v1.0.docx',
			'title' => 'Readiness Self-Assessment and Progress Tracker',
		);
		$items[] = array(
			'file'  => 'quick-references/CTA_LCSW_Quick_Reference_Sheet_Collection_v1.0.docx',
			'title' => 'Quick Reference Sheet Collection',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LCSW_Student_Roadmap_and_10_14_18_Week_Study_Schedules_v1.0.docx',
			'title' => 'Student Roadmap and 10-, 14-, and 18-Week Study Schedules',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LCSW_Student_FAQ_and_Self_Service_Support_Guide_v1.0.docx',
			'title' => 'Student FAQ and Self-Service Support Guide',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LCSW_ASWB_August_2026_Exam_Day_and_Simulation_Guide_v1.0.docx',
			'title' => 'August 2026 Exam-Day and Simulation Guide',
		);

		return $items;
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
	 * Load Form A or Form B question seed array.
	 *
	 * @param string $form a|b.
	 * @return array[]
	 */
	private static function load_form_questions( $form ) {
		$form = strtolower( (string) $form );
		$file = ( 'b' === $form ) ? 'lcsw-aswb-form-b.php' : 'lcsw-aswb-form-a.php';
		$path = CTA_PLUGIN_DIR . 'includes/quiz-seeds/' . $file;

		if ( ! is_readable( $path ) ) {
			return array();
		}

		$questions = include $path;
		return is_array( $questions ) ? $questions : array();
	}

	/**
	 * Create/update a form quiz and replace all questions.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $quiz_type form_a|form_b.
	 * @param string $title     Quiz title.
	 * @param int    $sort      Sort order.
	 * @param array  $questions Question rows.
	 * @return int Quiz ID or 0.
	 */
	private static function replace_form_quiz( $course_id, $quiz_type, $title, $sort, array $questions ) {
		global $wpdb;

		$course_id = absint( $course_id );
		$quiz_type = sanitize_text_field( $quiz_type );
		$title     = sanitize_text_field( $title );
		$sort      = (int) $sort;

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
					'time_limit_mins' => 240,
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
					'time_limit_mins' => 240,
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

	/**
	 * Approved learner-facing titles that must map to the ASWB product identity.
	 *
	 * @return string[]
	 */
	public static function get_stale_display_titles() {
		return array(
			self::LEGACY_TITLE,
			self::LEGACY_FORMAL,
		);
	}

	/**
	 * Whether a stored title/public_title still uses the pre-ASWB California Clinical label.
	 *
	 * @param string $value Title or public_title value.
	 * @return bool
	 */
	public static function is_stale_display_title( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return false;
		}

		return in_array( $value, self::get_stale_display_titles(), true );
	}

	/**
	 * Force the approved ASWB product identity: title, slug, syllabus meta, and artwork.
	 *
	 * @param bool $force Re-run even if already healed at this version.
	 * @return array{ok:bool,course_id:int,message:string,changes:array<string,bool>}
	 */
	public static function heal_product_identity( $force = false ) {
		global $wpdb;

		if ( ! $force && get_option( self::IDENTITY_HEAL ) ) {
			return array(
				'ok'        => true,
				'course_id' => 0,
				'message'   => 'already_healed',
				'changes'   => array(),
			);
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'        => false,
				'course_id' => 0,
				'message'   => 'course_not_found',
				'changes'   => array(),
			);
		}

		$course_id = (int) $course->id;
		$changes   = array(
			'title'         => false,
			'slug'          => false,
			'syllabus_meta' => false,
			'thumbnail'     => false,
		);

		$update = array();
		$formats = array();

		if ( self::TITLE !== (string) ( $course->title ?? '' ) ) {
			$update['title'] = self::TITLE;
			$formats[]       = '%s';
			$changes['title'] = true;
		}

		$slug = sanitize_title( (string) ( $course->slug ?? '' ) );
		if ( self::SLUG !== $slug ) {
			$update['slug'] = self::SLUG;
			$formats[]      = '%s';
			$changes['slug'] = true;
		}

		$meta = array();
		if ( ! empty( $course->syllabus_meta ) ) {
			$decoded = json_decode( (string) $course->syllabus_meta, true );
			$meta    = is_array( $decoded ) ? $decoded : array();
		}

		$identity_meta = self::get_syllabus_meta();
		$identity_keys = array(
			'public_title',
			'image_alt',
			'seo_title',
			'meta_description',
			'short_description',
		);

		foreach ( $identity_keys as $key ) {
			if ( ! isset( $identity_meta[ $key ] ) ) {
				continue;
			}
			if ( ! isset( $meta[ $key ] ) || $meta[ $key ] !== $identity_meta[ $key ] ) {
				$meta[ $key ]           = $identity_meta[ $key ];
				$changes['syllabus_meta'] = true;
			}
		}

		if ( $changes['syllabus_meta'] ) {
			$update['syllabus_meta'] = wp_json_encode( $meta );
			$formats[]               = '%s';
		}

		if ( ! empty( $update ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'cta_courses',
				$update,
				array( 'id' => $course_id ),
				$formats,
				array( '%d' )
			);
		}

		$thumb = self::sync_thumbnail( true );
		if ( ! empty( $thumb['ok'] ) ) {
			$changes['thumbnail'] = true;
		}

		update_option(
			self::IDENTITY_HEAL,
			array(
				'at'        => current_time( 'mysql' ),
				'course_id' => $course_id,
				'changes'   => $changes,
			),
			false
		);

		return array(
			'ok'        => true,
			'course_id' => $course_id,
			'message'   => 'healed',
			'changes'   => $changes,
		);
	}

	/**
	 * Self-heal stale California Clinical labels on an existing course row.
	 *
	 * @return void
	 */
	public static function maybe_heal_stale_product_identity() {
		if ( get_transient( 'cta_lcsw_aswb_identity_heal_lock' ) || get_option( self::IDENTITY_HEAL ) ) {
			return;
		}

		$course = self::find_course();
		if ( ! $course ) {
			return;
		}

		$needs_heal = self::SLUG !== sanitize_title( (string) ( $course->slug ?? '' ) )
			|| self::is_stale_display_title( (string) ( $course->title ?? '' ) );

		if ( ! $needs_heal && ! empty( $course->syllabus_meta ) ) {
			$decoded = json_decode( (string) $course->syllabus_meta, true );
			$meta    = is_array( $decoded ) ? $decoded : array();
			if ( ! empty( $meta['public_title'] ) && self::is_stale_display_title( (string) $meta['public_title'] ) ) {
				$needs_heal = true;
			}
		}

		if ( ! $needs_heal ) {
			return;
		}

		set_transient( 'cta_lcsw_aswb_identity_heal_lock', 1, 10 * MINUTE_IN_SECONDS );
		self::heal_product_identity( true );
	}

	/**
	 * Attach the approved LCSW ASWB course artwork to thumbnail_url.
	 *
	 * @param bool $force Re-run even if already applied at this seed key.
	 * @return array{ok:bool,course_id:int,thumbnail_url:string,message:string}
	 */
	public static function sync_thumbnail( $force = false ) {
		$seed_option = 'cta_lcsw_aswb_thumbnail_1_0_206';

		if ( ! $force && get_option( $seed_option ) ) {
			return array(
				'ok'            => true,
				'course_id'     => 0,
				'thumbnail_url' => '',
				'message'       => 'already_seeded',
			);
		}

		$course = self::find_course();
		if ( ! $course ) {
			return array(
				'ok'            => false,
				'course_id'     => 0,
				'thumbnail_url' => '',
				'message'       => 'lcsw_aswb_course_not_found',
			);
		}

		$thumbnail_url = self::resolve_approved_thumbnail_url();
		if ( '' === $thumbnail_url ) {
			return array(
				'ok'            => false,
				'course_id'     => (int) $course->id,
				'thumbnail_url' => '',
				'message'       => 'thumbnail_asset_not_found',
			);
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$wpdb->prefix . 'cta_courses',
			array( 'thumbnail_url' => $thumbnail_url ),
			array( 'id' => (int) $course->id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array(
				'ok'            => false,
				'course_id'     => (int) $course->id,
				'thumbnail_url' => $thumbnail_url,
				'message'       => 'update_failed',
			);
		}

		update_option( $seed_option, 1, false );

		return array(
			'ok'            => true,
			'course_id'     => (int) $course->id,
			'thumbnail_url' => $thumbnail_url,
			'message'       => 'synced',
		);
	}

	/**
	 * Resolve approved LCSW ASWB course image URL (bundled plugin asset).
	 *
	 * @return string
	 */
	public static function resolve_approved_thumbnail_url() {
		$bundled = CTA_PLUGIN_DIR . self::THUMBNAIL_REL;
		if ( is_readable( $bundled ) ) {
			return esc_url_raw( CTA_PLUGIN_URL . self::THUMBNAIL_REL );
		}

		return '';
	}
}

}
