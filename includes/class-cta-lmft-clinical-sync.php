<?php
/**
 * CTA LMFT California Clinical Exam Preparation — program, modules, materials, and Form A/B sync.
 *
 * Commercial terms (price, access period, classification) remain pending client confirmation.
 * Program is seeded as draft with LIVE_PRICE 0 until confirmed.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Clinical_Sync
 */
if ( ! class_exists( 'CTA_Lmft_Clinical_Sync' ) ) {

class CTA_Lmft_Clinical_Sync {

	const SEED_OPTION              = 'cta_lmft_clinical_seeded_1_0_154';
	const SLUG                     = 'lmft-california-clinical-exam-preparation';
	const TITLE                    = 'CTA LMFT California Clinical Exam Preparation Program';
	const PUBLIC_TITLE             = 'LMFT California Clinical Exam Preparation';
	const LEGACY_TITLE             = 'LMFT California Clinical Exam Preparation';
	const MATERIALS_REL            = 'assets/course-materials/lmft-clinical/';
	const PRICE_PENDING            = true;
	const SUGGESTED_PRICE          = 249.00;
	const SUGGESTED_ACCESS_MONTHS  = 6;
	const LIVE_PRICE               = 0.00;
	const ACCESS_MONTHS_DB         = 6;

	/**
	 * Find the LMFT Clinical course by slug or title (current or legacy).
	 *
	 * @return object|null
	 */
	public static function find_course() {
		global $wpdb;

		$table = $wpdb->prefix . 'cta_courses';

		$candidates = array(
			array( 'slug', self::SLUG ),
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
	 * Create or update the exam_prep program as draft with pending commercial terms.
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
			'price'                => (float) self::LIVE_PRICE,
			'category'             => 'Exam Preparation',
			'learning_objectives'  => $objectives,
			'syllabus_meta'        => $meta,
			'status'               => 'draft',
			'product_type'         => 'exam_prep',
			'access_period_months' => (int) self::ACCESS_MONTHS_DB,
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
			$title       = sanitize_text_field( (string) $item['title'] );
			$rel         = ltrim( str_replace( '\\', '/', (string) $item['file'] ), '/' );
			$source      = CTA_PLUGIN_DIR . self::MATERIALS_REL . $rel;
			$module_id   = 0;
			$is_practice = ! empty( $item['is_practice_test'] ) ? 1 : 0;
			// Access Correction Notice: never persist material unlock gates for Exam Prep.
			$unlock      = '';

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

			$existing_id = self::find_resource_id_by_title( $course_id, $title );

			if ( $existing_id ) {
				$update = array(
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
	 * Ensure Form A and Form B quizzes with 150 questions each.
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

		if ( 150 !== count( $questions_a ) || 150 !== count( $questions_b ) ) {
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
			'Form A — Full-Length Simulation (150 Questions)',
			20,
			$questions_a
		);
		$form_b = self::replace_form_quiz(
			$course_id,
			'form_b',
			'Form B — Full-Length Simulation (150 Questions)',
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
			'questions_a' => 150,
			'questions_b' => 150,
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
			'modules_created'    => (int) ( $modules['created'] ?? 0 ),
			'modules_updated'    => (int) ( $modules['updated'] ?? 0 ),
			'materials_attached' => (int) ( $materials['attached'] ?? 0 ),
			'materials_updated'  => (int) ( $materials['updated'] ?? 0 ),
			'materials_missing'  => count( $materials['missing'] ?? array() ),
			'form_a_quiz_id'     => (int) ( $assessments['form_a'] ?? 0 ),
			'form_b_quiz_id'     => (int) ( $assessments['form_b'] ?? 0 ),
			'questions_a'        => (int) ( $assessments['questions_a'] ?? 0 ),
			'questions_b'        => (int) ( $assessments['questions_b'] ?? 0 ),
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
	 * Program description HTML (no recorded A/V claims; commercial terms pending).
	 *
	 * @return string
	 */
	private static function get_program_description_html() {
		$html = '
<p>LMFT California Clinical Exam Preparation is a complete self-paced system for AMFTs, LMFT candidates, and other eligible examinees preparing for the California LMFT Clinical Examination.</p>
<p>The program teaches candidates to combine clinical engagement, relational and family-systems assessment, diagnosis and differential reasoning, crisis and safety judgment, intervention selection, California legal and ethical practice, and FIRST/NEXT/BEST exam cues when several answers appear plausible. Content and simulations are structured around the California clinical exam format: 150 total questions (125 scored and 25 pretest) within a 240-minute testing window.</p>
<h3>What Is Included</h3>
<ul>
<li>12 editable and print-optimized LMFT student workbooks</li>
<li>12 paired 17-question workbook banks (204 total practice placements) with complete rationales</li>
<li>Comprehensive Simulation Form A — 150 questions</li>
<li>Comprehensive Simulation Form B — 150 questions</li>
<li>Controlled answer keys and detailed rationales for both simulations (released after each form is submitted)</li>
<li>Flashcard collection, quick-reference sheets, readiness tracker, Student FAQ, and 10-, 14-, and 18-week study schedules</li>
</ul>
<p><strong>Written program complete.</strong> Recorded audio and video are not included at launch.</p>
<h3>Important Notices</h3>
<ul>
<li><strong>Exam Preparation Only — No CE Credit (proposed).</strong> Classification and commercial terms are pending client confirmation. This program is intended as exam preparation only and does not provide continuing education hours or a CE certificate.</li>
<li>CTA is an independent educational resource and is not affiliated with or endorsed by the California Board of Behavioral Sciences (BBS), Pearson VUE, or any licensing board.</li>
<li>This program prepares candidates for the California LMFT Clinical Examination. It is not an AMFTRB National MFT exam preparation product and should not be confused with the national examination.</li>
<li>Participation supports examination readiness but does not guarantee passage or determine eligibility. Candidates should follow the blueprint and rules that apply to their actual testing date and jurisdiction.</li>
<li>No recorded audio or video lessons are included in this initial release.</li>
</ul>';

		return wp_kses_post( $html );
	}

	/**
	 * Learning objectives for LMFT California Clinical exam preparation.
	 *
	 * @return string[]
	 */
	private static function get_learning_objectives() {
		return array(
			'Identify FIRST, NEXT, BEST, MOST, INITIAL, and PRIMARY action cues on California clinical items.',
			'Apply relational, couple, family-systems, and person-in-context reasoning to assessment and intervention choices.',
			'Sequence engagement, intake, mental status, diagnosis, planning, intervention, progress review, and termination.',
			'Prioritize crisis, abuse, neglect, and safety responses before non-urgent clinical or administrative actions.',
			'Apply California legal, ethical, and professional practice standards without confusing BBS Clinical and AMFTRB National frameworks.',
			'Build pacing and stamina under the 150-question / 240-minute examination structure.',
		);
	}

	/**
	 * Syllabus / SEO meta — commercial fields flagged pending; suggested values are not live terms.
	 *
	 * @return array
	 */
	private static function get_syllabus_meta() {
		return array(
			'public_title'                    => self::PUBLIC_TITLE,
			'commercial_pending'              => true,
			'pricing_status'                  => 'pending_client_confirmation',
			'access_period_status'            => 'pending_client_confirmation',
			'classification_status'           => 'pending_client_confirmation',
			'suggested_price'                 => (float) self::SUGGESTED_PRICE,
			'suggested_access_period_months'  => (int) self::SUGGESTED_ACCESS_MONTHS,
			'suggested_classification'        => 'Exam Preparation Only — No CE Credit',
			'course_classification'           => 'Exam Preparation Only — No CE Credit (pending client confirmation)',
			'short_description'               => 'Twelve LMFT-specific workbooks, focused practice banks, complete rationales, and two 150-question California Clinical simulations. Pricing and access terms pending client confirmation. Recorded audio and video are not included at launch.',
			'instructional_method'            => 'Self-paced asynchronous online program with printable resources',
			'target_audience'                 => 'AMFTs, LMFT candidates, and other eligible California LMFT Clinical examinees',
			'seo_title'                       => 'LMFT California Clinical Exam Prep | CTA',
			'meta_description'                => 'Prepare for the California LMFT Clinical exam with 12 workbooks, 204 focused practice questions, complete rationales, and two 150-question simulations. Pricing pending confirmation.',
			'image_alt'                       => 'Clinical Training and Supervision Academy LMFT California Clinical Exam Preparation graphic',
			'primary_cta'                     => 'Begin Your Clinical Exam Preparation',
			'page_badge'                      => 'Exam Preparation • Pricing Pending Confirmation',
			'educational_notice'              => 'Exam Preparation Only — No CE Credit (pending client confirmation). This program does not award CE hours or a CE certificate. Recorded audio and video are not included at launch. CTA is not affiliated with or endorsed by the California BBS, Pearson VUE, or AMFTRB. This is not a National MFT exam product.',
			'price_pending'                   => (bool) self::PRICE_PENDING,
			'launch_status'                   => 'draft_pending_testing',
			'launch_pending_testing'          => true,
			'development_draft'               => true,
		);
	}

	/**
	 * Twelve workbook module titles (order_index 0–11).
	 *
	 * @return array<int,array{title:string}>
	 */
	private static function get_module_definitions() {
		return array(
			array( 'title' => 'Workbook 1: Exam Strategy and Clinical Reasoning' ),
			array( 'title' => 'Workbook 2: Clinical Engagement, Intake, and Mental Status Assessment' ),
			array( 'title' => 'Workbook 3: Developmental, Psychosocial, and Diversity Assessment' ),
			array( 'title' => 'Workbook 4: Relational, Family System, Trauma, and Strengths Assessment' ),
			array( 'title' => 'Workbook 5: Diagnosis and Differential Diagnosis' ),
			array( 'title' => 'Workbook 6: Crisis, Abuse, and Safety' ),
			array( 'title' => 'Workbook 7: Planning, Progress, and Termination' ),
			array( 'title' => 'Workbook 8: Family, Couple, Attachment, and Relational Interventions' ),
			array( 'title' => 'Workbook 9: Trauma, Psychological, Behavioral, and Recovery Interventions' ),
			array( 'title' => 'Workbook 10: Developmental, Cultural, and Contextual Interventions' ),
			array( 'title' => 'Workbook 11: Theory, Groups, Referral, and Interdisciplinary Collaboration' ),
			array( 'title' => 'Workbook 12: California Legal, Ethical, and Professional Practice' ),
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
				'file'  => 'workbooks/CTA_LMFT_WB1_Exam_Strategy_and_Clinical_Reasoning_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 1 — Exam Strategy and Clinical Reasoning (Student Workbook)',
			),
			2  => array(
				'file'  => 'workbooks/CTA_LMFT_WB2_Clinical_Engagement_Intake_and_Mental_Status_Assessment_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 2 — Clinical Engagement, Intake, and Mental Status Assessment (Student Workbook)',
			),
			3  => array(
				'file'  => 'workbooks/CTA_LMFT_WB3_Developmental_Psychosocial_and_Diversity_Assessment_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 3 — Developmental, Psychosocial, and Diversity Assessment (Student Workbook)',
			),
			4  => array(
				'file'  => 'workbooks/CTA_LMFT_WB4_Relational_Family_System_Trauma_and_Strengths_Assessment_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 4 — Relational, Family System, Trauma, and Strengths Assessment (Student Workbook)',
			),
			5  => array(
				'file'  => 'workbooks/CTA_LMFT_WB5_Diagnosis_and_Differential_Diagnosis_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 5 — Diagnosis and Differential Diagnosis (Student Workbook)',
			),
			6  => array(
				'file'  => 'workbooks/CTA_LMFT_WB6_Crisis_Abuse_and_Safety_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 6 — Crisis, Abuse, and Safety (Student Workbook)',
			),
			7  => array(
				'file'  => 'workbooks/CTA_LMFT_WB7_Planning_Progress_and_Termination_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 7 — Planning, Progress, and Termination (Student Workbook)',
			),
			8  => array(
				'file'  => 'workbooks/CTA_LMFT_WB8_Family_Couple_Attachment_and_Relational_Interventions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 8 — Family, Couple, Attachment, and Relational Interventions (Student Workbook)',
			),
			9  => array(
				'file'  => 'workbooks/CTA_LMFT_WB9_Trauma_Psychological_Behavioral_and_Recovery_Interventions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 9 — Trauma, Psychological, Behavioral, and Recovery Interventions (Student Workbook)',
			),
			10 => array(
				'file'  => 'workbooks/CTA_LMFT_WB10_Developmental_Cultural_and_Contextual_Interventions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 10 — Developmental, Cultural, and Contextual Interventions (Student Workbook)',
			),
			11 => array(
				'file'  => 'workbooks/CTA_LMFT_WB11_Theory_Groups_Referral_and_Interdisciplinary_Collaboration_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 11 — Theory, Groups, Referral, and Interdisciplinary Collaboration (Student Workbook)',
			),
			12 => array(
				'file'  => 'workbooks/CTA_LMFT_WB12_California_Legal_Ethical_and_Professional_Practice_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 12 — California Legal, Ethical, and Professional Practice (Student Workbook)',
			),
		);

		foreach ( $workbooks as $n => $wb ) {
			$items[] = array(
				'file'         => $wb['file'],
				'title'        => $wb['title'],
				'workbook_num' => $n,
			);
			$items[] = array(
				'file'             => 'question-banks/CTA_LMFT_WB' . $n . '_17_Question_Bank_v1.0.docx',
				'title'            => 'Workbook ' . $n . ' — 17-Question Practice Bank',
				'workbook_num'     => $n,
				'is_practice_test' => 1,
			);
		}

		$items[] = array(
			'file'                   => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_A_150_Question_Exam_v1.0.docx',
			'title'                  => 'Form A — 150-Question Comprehensive Simulation',
			'is_practice_test'       => 1,
		);
		$items[] = array(
			'file'                   => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_B_150_Question_Exam_v1.0.docx',
			'title'                  => 'Form B — 150-Question Comprehensive Simulation',
			'is_practice_test'       => 1,
		);
		$items[] = array(
			'file'                   => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_A_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			'title'                  => 'Form A — Answer Key and Detailed Rationales',
		);
		$items[] = array(
			'file'                   => 'simulations/CTA_LMFT_Comprehensive_Simulation_Form_B_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			'title'                  => 'Form B — Answer Key and Detailed Rationales',
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LMFT_Clinical_Exam_Preparation_Flashcard_Collection_v1.0.docx',
			'title' => 'Clinical Exam Preparation Flashcard Collection',
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LMFT_Readiness_Self_Assessment_and_Progress_Tracker_v1.0.docx',
			'title' => 'Readiness Self-Assessment and Progress Tracker',
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LMFT_Quick_Reference_Sheet_Collection_v1.0.docx',
			'title' => 'Quick Reference Sheet Collection',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LMFT_Student_Roadmap_and_10_14_18_Week_Study_Schedules_v1.0.docx',
			'title' => 'Student Roadmap and 10-, 14-, and 18-Week Study Schedules',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LMFT_Student_FAQ_and_Self_Service_Support_Guide_v1.0.docx',
			'title' => 'Student FAQ and Self-Service Support Guide',
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
		$file = ( 'b' === $form ) ? 'lmft-clinical-form-b.php' : 'lmft-clinical-form-a.php';
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
}

}
