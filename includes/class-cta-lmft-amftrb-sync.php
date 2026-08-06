<?php
/**
 * CTA LMFT AMFTRB National Exam Preparation — program, modules, and materials sync.
 *
 * Assessment delivery for v1 is printable candidate banks, checkpoints, and Form A/B
 * simulations under assets/course-materials/lmft-amftrb/. Online quiz seeding from
 * DOCX (quiz-seeds PHP) is intentionally not implemented here; banks can be added later.
 *
 * Remains draft with launch_pending_testing until learner testing and Founder/CEO
 * release approval (checkout HOLD).
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Amftrb_Sync
 */
if ( ! class_exists( 'CTA_Lmft_Amftrb_Sync' ) ) {

class CTA_Lmft_Amftrb_Sync {

	const SEED_OPTION   = 'cta_lmft_amftrb_seeded_1_0_146';
	const SLUG          = 'lmft-amftrb-national-exam-preparation';
	const TITLE         = 'CTA LMFT AMFTRB National Exam Preparation Program';
	const PUBLIC_TITLE  = 'LMFT AMFTRB National Exam Preparation';
	const PRICE         = 329.00;
	const ACCESS_MONTHS = 6;
	const MATERIALS_REL = 'assets/course-materials/lmft-amftrb/';
	const TRANSCRIPT_TITLE = 'Authoritative Audio Transcript v1.1 (Tracks 1–12)';

	/**
	 * Find the LMFT AMFTRB course by slug or title.
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
	 * Create or update the exam_prep program as draft (checkout HOLD).
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

		$desc = 'Complete the student workbook, audio review, and paired 17-question candidate bank for this unit.';

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
	 * Attach all student materials (workbooks, audio, banks, checkpoints, simulations, study tools).
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
			$unlock      = isset( $item['unlock_after_quiz_type'] )
				? sanitize_text_field( (string) $item['unlock_after_quiz_type'] )
				: '';

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
	 * Orchestrate program sync (program + modules + materials only; no online quiz seeds).
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

		$modules   = self::sync_modules( $course_id );
		$materials = self::sync_materials( $course_id );

		$ok = true;

		$counts = array(
			'modules_created'    => (int) ( $modules['created'] ?? 0 ),
			'modules_updated'    => (int) ( $modules['updated'] ?? 0 ),
			'materials_attached' => (int) ( $materials['attached'] ?? 0 ),
			'materials_updated'  => (int) ( $materials['updated'] ?? 0 ),
			'materials_skipped'  => (int) ( $materials['skipped'] ?? 0 ),
			'materials_missing'  => count( $materials['missing'] ?? array() ),
			'missing_paths'      => array_values( (array) ( $materials['missing'] ?? array() ) ),
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
			'message'   => 'synced',
			'counts'    => $counts,
		);
	}

	/**
	 * Whether the user has any completed attempt for a quiz of the given type on the course.
	 *
	 * @param int    $user_id   WordPress user ID.
	 * @param int    $course_id Course ID.
	 * @param string $quiz_type Quiz type (e.g. form_a, checkpoint_1, wb1_bank).
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
	 * Program description HTML matching the AMFTRB public guide (draft / HOLD).
	 *
	 * @return string
	 */
	private static function get_program_description_html() {
		$html = '
<p>CTA LMFT AMFTRB National Exam Preparation Program is a self-paced national-examination preparation program for marriage and family therapy candidates whose licensing jurisdiction requires or accepts the AMFTRB National Examination. The program combines structured instruction, original one-best-answer practice, controlled rationales, written remediation, and two fresh full-length readiness simulations.</p>
<p>Official examination mechanics addressed by this program: 180 objective multiple-choice items, four answer choices per item, one correct answer, and a 240-minute testing window across the six-domain national architecture.</p>
<h3>What Is Included</h3>
<ul>
<li>12 editable and printable instructional workbooks organized around the current six-domain national examination architecture</li>
<li>12 original 17-question workbook banks (204 practice questions) with separate controlled answer keys and detailed option-by-option rationales</li>
<li>Three cumulative checkpoints: 45 questions after Workbook 4, 60 questions after Workbook 8, and 90 questions after Workbook 12</li>
<li>Comprehensive Simulation Form A — 180 questions / 240 minutes, with controlled rationales, domain report, and required remediation</li>
<li>Comprehensive Simulation Form B — 180 questions / 240 minutes, with controlled rationales, domain report, and final readiness gate</li>
<li>12 recorded audio review tracks (one aligned to each workbook; combined runtime approximately 1 hour 15 minutes) with an accessible authoritative transcript</li>
<li>Start Here roadmap with 10-, 14-, and 18-week schedules, baseline inventory, progress tracker, and error log</li>
<li>Twelve quick-reference sheets and a 120-card flashcard study collection</li>
<li>California transition and candidate-routing companion (maintained separately from the national core)</li>
</ul>
<p><strong>Publication hold.</strong> This program remains in draft until price, access, catalog, website, LMS, learner testing, and Founder/CEO release approval are complete. Checkout is held while launch_pending_testing is set.</p>
<h3>Important Notices</h3>
<ul>
<li><strong>Exam Preparation Only — No CE Credit.</strong> This program does not award continuing education hours or a CE certificate.</li>
<li><strong>No guarantee.</strong> Completion, CTA practice scores, and readiness decisions do not guarantee an official examination result, licensure, eligibility, or board acceptance.</li>
<li><strong>No affiliation.</strong> Clinical Training and Supervision Academy is not affiliated with, endorsed by, or sponsored by AMFTRB, PTC, Prometric, AAMFT, or any licensing board. CTA materials are original educational resources and do not reproduce live or official examination questions.</li>
<li>This is a separate national AMFTRB product. It must not be used as though it carries California BBS clinical-exam timing, domains, or legal framing. California Law and Ethics preparation is a separate product.</li>
<li>Participation supports examination readiness but does not determine eligibility. Candidates should follow the blueprint and rules that apply to their actual testing date and jurisdiction.</li>
</ul>';

		return wp_kses_post( $html );
	}

	/**
	 * Learning objectives for AMFTRB National MFT exam preparation.
	 *
	 * @return string[]
	 */
	private static function get_learning_objectives() {
		return array(
			'Apply one-best-answer systemic reasoning across AMFTRB-style national examination items.',
			'Sequence alliance, intake, assessment, diagnosis, planning, intervention, progress review, and ethical practice.',
			'Prioritize crisis, suicide, violence, abuse, trauma, and emergency responses before non-urgent clinical actions.',
			'Select couple, family, and integrated intervention models matched to relational patterns and context.',
			'Use controlled rationales, checkpoints, and remediation to repair reasoning rather than memorize answer choices.',
			'Build pacing and stamina under the 180-question / 240-minute full-length simulation structure.',
		);
	}

	/**
	 * Syllabus / SEO meta — draft pending testing; educational notices match public guide.
	 *
	 * @return array
	 */
	private static function get_syllabus_meta() {
		return array(
			'public_title'           => self::PUBLIC_TITLE,
			'short_description'      => 'Twelve LMFT AMFTRB workbooks, twelve 17-question banks, three cumulative checkpoints, Form A and Form B 180-question / 240-minute simulations, 12 audio reviews with transcript, flashcards, quick references, and study schedules. Exam Preparation Only — No CE Credit.',
			'course_classification'  => 'Exam Preparation Only — No CE Credit',
			'instructional_method'   => 'Self-paced asynchronous online program with printable resources and recorded audio reviews',
			'target_audience'        => 'LMFT/MFT candidates preparing for the AMFTRB National Examination',
			'seo_title'              => 'LMFT AMFTRB National Exam Prep | CTA',
			'meta_description'       => 'Prepare for the AMFTRB National MFT exam with 12 workbooks, 204 practice questions, three checkpoints, two 180-question simulations, and 12 audio reviews. Exam preparation only — no CE credit.',
			'image_alt'              => 'Clinical Training and Supervision Academy LMFT AMFTRB National Exam Preparation graphic',
			'primary_cta'            => 'Begin Your National Exam Preparation',
			'page_badge'             => 'Exam Preparation Only — No CE Credit • Draft Pending Testing',
			'educational_notice'     => 'Exam Preparation Only — No CE Credit. This program does not award CE hours or a CE certificate. CTA is not affiliated with, endorsed by, or sponsored by AMFTRB, PTC, Prometric, AAMFT, or any licensing board. Completion does not guarantee examination passage or determine eligibility. This is not a California BBS Clinical exam product.',
			'launch_status'          => 'draft_pending_testing',
			'launch_pending_testing' => true,
			'development_draft'      => true,
			'audio_tracks'           => 12,
			'exam_mechanics'         => '180 items | 240 minutes | six domains',
		);
	}

	/**
	 * Twelve workbook module titles (order_index 0–11) from the LMS implementation guide.
	 *
	 * @return array<int,array{title:string}>
	 */
	private static function get_module_definitions() {
		return array(
			array( 'title' => 'Workbook 1: Exam Strategy, Systemic Reasoning, and One-Best-Answer Decisions' ),
			array( 'title' => 'Workbook 2: Therapeutic Alliance, Systemic Practice, and Self of the Therapist' ),
			array( 'title' => 'Workbook 3: Intake, Assessment, Mental Status, Relational Patterns, and Context' ),
			array( 'title' => 'Workbook 4: Diagnosis, Differential Diagnosis, Instruments, and Systemic Case Formulation' ),
			array( 'title' => 'Workbook 5: Treatment Design, Goals, Contracts, Measurement, and Systemic Planning' ),
			array( 'title' => 'Workbook 6: Crisis, Suicide, Violence, Abuse, Trauma, and Emergency Response' ),
			array( 'title' => 'Workbook 7: Couple, Family, Attachment, Structural, Strategic, and Intergenerational Interventions' ),
			array( 'title' => 'Workbook 8: Narrative, Solution-Focused, Experiential, Behavioral, Cognitive, and Mindfulness Interventions' ),
			array( 'title' => 'Workbook 9: Complex Presentations, Substance Use, Recovery, Sexuality, Health, Development, Grief, and Integrated Care' ),
			array( 'title' => 'Workbook 10: Progress Evaluation, Research Literacy, Plan Revision, Termination, and Continuity' ),
			array( 'title' => 'Workbook 11: Diversity, Social Justice, Community Systems, Groups, Referral, Collaboration, and Supervision' ),
			array( 'title' => 'Workbook 12: Ethics, Law, Business Practice, Documentation, Technology, Teletherapy, and AI' ),
		);
	}

	/**
	 * Authoritative LMS audio placement map (tracks 1–12): file, display title, exact runtime, match needle.
	 *
	 * @return array<int,array{file:string,title:string,runtime:string,file_needle:string}>
	 */
	public static function get_audio_placement_map() {
		return array(
			1  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_01_Exam_Strategy_and_Systemic_Reasoning_v1.0.mp3',
				'title'       => 'Audio Review - Exam Strategy, Systemic Reasoning, and One-Best-Answer Decisions',
				'runtime'     => '5:39.644',
				'file_needle' => 'Audio_Track_01_',
			),
			2  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_02_Therapeutic_Alliance_and_Systemic_Practice_v1.0.mp3',
				'title'       => 'Audio Review - Therapeutic Alliance, Systemic Practice, and Self of the Therapist',
				'runtime'     => '5:37.476',
				'file_needle' => 'Audio_Track_02_',
			),
			3  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_03_Intake_Assessment_and_Context_v1.0.mp3',
				'title'       => 'Audio Review - Intake, Assessment, Mental Status, Relational Patterns, and Context',
				'runtime'     => '6:20.056',
				'file_needle' => 'Audio_Track_03_',
			),
			4  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_04_Diagnosis_and_Systemic_Formulation_v1.0.mp3',
				'title'       => 'Audio Review - Diagnosis, Differential Diagnosis, Instruments, and Systemic Case Formulation',
				'runtime'     => '6:07.935',
				'file_needle' => 'Audio_Track_04_',
			),
			5  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_05_Treatment_Design_and_Systemic_Planning_v1.0.mp3',
				'title'       => 'Audio Review - Treatment Design, Goals, Contracts, Measurement, and Systemic Planning',
				'runtime'     => '6:02.580',
				'file_needle' => 'Audio_Track_05_',
			),
			6  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_06_Crisis_and_Emergency_Response_v1.0.mp3',
				'title'       => 'Audio Review - Crisis, Suicide, Violence, Abuse, Trauma, and Emergency Response',
				'runtime'     => '6:29.799',
				'file_needle' => 'Audio_Track_06_',
			),
			7  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_07_Couple_Family_and_Systemic_Interventions_v1.0.mp3',
				'title'       => 'Audio Review - Couple, Family, Attachment, Structural, Strategic, and Intergenerational Interventions',
				'runtime'     => '5:09.891',
				'file_needle' => 'Audio_Track_07_',
			),
			8  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_08_Integrated_Intervention_Models_v1.0.mp3',
				'title'       => 'Audio Review - Narrative, Solution-Focused, Experiential, Behavioral, Cognitive, and Mindfulness Interventions',
				'runtime'     => '5:45.678',
				'file_needle' => 'Audio_Track_08_',
			),
			9  => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_09_Complex_Presentations_and_Integrated_Care_v1.0.mp3',
				'title'       => 'Audio Review - Complex Presentations, Substance Use, Recovery, Sexuality, Health, Development, Grief, and Integrated Care',
				'runtime'     => '6:51.220',
				'file_needle' => 'Audio_Track_09_',
			),
			10 => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_10_Progress_Evaluation_and_Continuity_v1.0.mp3',
				'title'       => 'Audio Review - Progress Evaluation, Research Literacy, Plan Revision, Termination, and Continuity',
				'runtime'     => '6:43.879',
				'file_needle' => 'Audio_Track_10_',
			),
			11 => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_11_Diversity_Community_and_Collaboration_v1.0.mp3',
				'title'       => 'Audio Review - Diversity, Social Justice, Community Systems, Groups, Referral, Collaboration, and Supervision',
				'runtime'     => '7:00.310',
				'file_needle' => 'Audio_Track_11_',
			),
			12 => array(
				'file'        => 'audio/CTA_LMFT_AMFTRB_Audio_Track_12_Ethics_Law_Technology_and_AI_v1.0.mp3',
				'title'       => 'Audio Review - Ethics, Law, Business Practice, Documentation, Technology, Teletherapy, and AI',
				'runtime'     => '7:38.344',
				'file_needle' => 'Audio_Track_12_',
			),
		);
	}

	/**
	 * Resolve track number, display title, and exact runtime for an audio resource.
	 *
	 * @param object|array $resource Downloadable resource row or array with file_path/file_url/title.
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
	 * Material map: relative file under MATERIALS_REL + learner-facing title + flags.
	 *
	 * @return array<int,array>
	 */
	private static function get_material_map() {
		$items = array();

		$transcript_title = self::TRANSCRIPT_TITLE;

		$workbooks = array(
			1  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB1_Exam_Strategy_Systemic_Reasoning_and_One_Best_Answer_Decisions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 1 — Exam Strategy, Systemic Reasoning, and One-Best-Answer Decisions (Student Workbook)',
			),
			2  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB2_Therapeutic_Alliance_Systemic_Practice_and_Self_of_the_Therapist_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 2 — Therapeutic Alliance, Systemic Practice, and Self of the Therapist (Student Workbook)',
			),
			3  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB3_Intake_Assessment_Mental_Status_Relational_Patterns_and_Context_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 3 — Intake, Assessment, Mental Status, Relational Patterns, and Context (Student Workbook)',
			),
			4  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB4_Diagnosis_Differential_Diagnosis_Instruments_and_Systemic_Case_Formulation_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 4 — Diagnosis, Differential Diagnosis, Instruments, and Systemic Case Formulation (Student Workbook)',
			),
			5  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB5_Treatment_Design_Goals_Contracts_Measurement_and_Systemic_Planning_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 5 — Treatment Design, Goals, Contracts, Measurement, and Systemic Planning (Student Workbook)',
			),
			6  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB6_Crisis_Suicide_Violence_Abuse_Trauma_and_Emergency_Response_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 6 — Crisis, Suicide, Violence, Abuse, Trauma, and Emergency Response (Student Workbook)',
			),
			7  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB7_Couple_Family_Attachment_Structural_Strategic_and_Intergenerational_Interventions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 7 — Couple, Family, Attachment, Structural, Strategic, and Intergenerational Interventions (Student Workbook)',
			),
			8  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB8_Narrative_Solution_Focused_Experiential_Behavioral_Cognitive_and_Mindfulness_Interventions_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 8 — Narrative, Solution-Focused, Experiential, Behavioral, Cognitive, and Mindfulness Interventions (Student Workbook)',
			),
			9  => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB9_Complex_Presentations_Substance_Use_Recovery_Sexuality_Health_Development_Grief_and_Integrated_Care_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 9 — Complex Presentations, Substance Use, Recovery, Sexuality, Health, Development, Grief, and Integrated Care (Student Workbook)',
			),
			10 => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB10_Progress_Evaluation_Research_Literacy_Plan_Revision_Termination_and_Continuity_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 10 — Progress Evaluation, Research Literacy, Plan Revision, Termination, and Continuity (Student Workbook)',
			),
			11 => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB11_Diversity_Social_Justice_Community_Systems_Groups_Referral_Collaboration_and_Supervision_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 11 — Diversity, Social Justice, Community Systems, Groups, Referral, Collaboration, and Supervision (Student Workbook)',
			),
			12 => array(
				'file'  => 'workbooks/CTA_LMFT_AMFTRB_WB12_Ethics_Law_Business_Practice_Documentation_Technology_Teletherapy_and_AI_Student_Workbook_v1.0.docx',
				'title' => 'Workbook 12 — Ethics, Law, Business Practice, Documentation, Technology, Teletherapy, and AI (Student Workbook)',
			),
		);

		$audio = self::get_audio_placement_map();

		// Exact rationale filenames on disk (WB1–4 / WB5 use 17_Question_Answer_Key; WB6–12 use Controlled_).
		$rationales = array(
			1  => 'rationales/CTA_LMFT_AMFTRB_WB1_17_Question_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			2  => 'rationales/CTA_LMFT_AMFTRB_WB2_17_Question_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			3  => 'rationales/CTA_LMFT_AMFTRB_WB3_17_Question_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			4  => 'rationales/CTA_LMFT_AMFTRB_WB4_17_Question_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			5  => 'rationales/CTA_LMFT_AMFTRB_WB5_17_Question_Answer_Key_and_Detailed_Rationales_v1.1.docx',
			6  => 'rationales/CTA_LMFT_AMFTRB_WB6_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			7  => 'rationales/CTA_LMFT_AMFTRB_WB7_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			8  => 'rationales/CTA_LMFT_AMFTRB_WB8_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			9  => 'rationales/CTA_LMFT_AMFTRB_WB9_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			10 => 'rationales/CTA_LMFT_AMFTRB_WB10_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			11 => 'rationales/CTA_LMFT_AMFTRB_WB11_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			12 => 'rationales/CTA_LMFT_AMFTRB_WB12_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
		);

		foreach ( $workbooks as $n => $wb ) {
			$items[] = array(
				'file'         => $wb['file'],
				'title'        => $wb['title'],
				'workbook_num' => $n,
			);

			if ( isset( $audio[ $n ] ) ) {
				$items[] = array(
					'file'         => $audio[ $n ]['file'],
					'title'        => $audio[ $n ]['title'],
					'workbook_num' => $n,
					'is_audio'     => 1,
				);
			}

			$items[] = array(
				'file'             => 'question-banks/CTA_LMFT_AMFTRB_WB' . $n . '_17_Question_Candidate_Bank_v1.0.docx',
				'title'            => 'Workbook ' . $n . ' — 17-Question Candidate Bank',
				'workbook_num'     => $n,
				'is_practice_test' => 1,
			);
			$items[] = array(
				'file'                   => $rationales[ $n ],
				'title'                  => 'Workbook ' . $n . ' — Answer Key and Detailed Rationales',
				'workbook_num'           => $n,
				'unlock_after_quiz_type' => 'wb' . $n . '_bank',
			);

			// Checkpoint 1 after Workbook 4.
			if ( 4 === (int) $n ) {
				$items[] = array(
					'file'             => 'question-banks/CTA_LMFT_AMFTRB_Checkpoint_1_45_Question_Candidate_Assessment_v1.0.docx',
					'title'            => 'Checkpoint 1 — 45-Question Cumulative Assessment (WB1–4) Candidate Form',
					'workbook_num'     => 4,
					'is_practice_test' => 1,
				);
				$items[] = array(
					'file'                   => 'rationales/CTA_LMFT_AMFTRB_Checkpoint_1_45_Question_Answer_Key_and_Detailed_Rationales_v1.0.docx',
					'title'                  => 'Checkpoint 1 — Answer Key and Detailed Rationales',
					'workbook_num'           => 4,
					'unlock_after_quiz_type' => 'checkpoint_1',
				);
			}

			// Checkpoint 2 + remediation after Workbook 8.
			if ( 8 === (int) $n ) {
				$items[] = array(
					'file'             => 'question-banks/CTA_LMFT_AMFTRB_Checkpoint_2_60_Question_Candidate_Assessment_v1.0.docx',
					'title'            => 'Checkpoint 2 — 60-Question Cumulative Assessment (WB1–8) Candidate Form',
					'workbook_num'     => 8,
					'is_practice_test' => 1,
				);
				$items[] = array(
					'file'                   => 'rationales/CTA_LMFT_AMFTRB_Checkpoint_2_60_Question_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
					'title'                  => 'Checkpoint 2 — Controlled Answer Key and Detailed Rationales',
					'workbook_num'           => 8,
					'unlock_after_quiz_type' => 'checkpoint_2',
				);
				$items[] = array(
					'file'                   => 'remediation/CTA_LMFT_AMFTRB_Checkpoint_2_Domain_Performance_Report_v1.0.docx',
					'title'                  => 'Checkpoint 2 — Domain Performance Report',
					'workbook_num'           => 8,
					'unlock_after_quiz_type' => 'checkpoint_2',
				);
				$items[] = array(
					'file'                   => 'remediation/CTA_LMFT_AMFTRB_Checkpoint_2_Required_Remediation_Workbook_and_Workbook_9_Progression_Gate_v1.0.docx',
					'title'                  => 'Checkpoint 2 — Required Remediation Workbook and Workbook 9 Progression Gate',
					'workbook_num'           => 8,
					'unlock_after_quiz_type' => 'checkpoint_2',
				);
			}

			// Checkpoint 3 after Workbook 12.
			if ( 12 === (int) $n ) {
				$items[] = array(
					'file'             => 'question-banks/CTA_LMFT_AMFTRB_Checkpoint_3_90_Question_Candidate_Assessment_v1.0.docx',
					'title'            => 'Checkpoint 3 — 90-Question Cumulative Assessment (WB1–12) Candidate Form',
					'workbook_num'     => 12,
					'is_practice_test' => 1,
				);
				$items[] = array(
					'file'                   => 'rationales/CTA_LMFT_AMFTRB_Checkpoint_3_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
					'title'                  => 'Checkpoint 3 — Controlled Answer Key and Detailed Rationales',
					'workbook_num'           => 12,
					'unlock_after_quiz_type' => 'checkpoint_3',
				);
			}
		}

		// Form A — unlock after modules complete; rationales/rem after form_a.
		$items[] = array(
			'file'                   => 'question-banks/CTA_LMFT_AMFTRB_Simulation_Form_A_180_Question_Candidate_Assessment_v1.0.docx',
			'title'                  => 'Form A — 180-Question Comprehensive Simulation (Candidate Assessment)',
			'is_practice_test'       => 1,
			'unlock_after_quiz_type' => 'modules_complete',
		);
		$items[] = array(
			'file'                   => 'rationales/CTA_LMFT_AMFTRB_Simulation_Form_A_180_Question_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			'title'                  => 'Form A — Controlled Answer Key and Detailed Rationales',
			'unlock_after_quiz_type' => 'form_a',
		);
		$items[] = array(
			'file'                   => 'remediation/CTA_LMFT_AMFTRB_Simulation_Form_A_Domain_Performance_Report_v1.0.docx',
			'title'                  => 'Form A — Domain Performance Report',
			'unlock_after_quiz_type' => 'form_a',
		);
		$items[] = array(
			'file'                   => 'remediation/CTA_LMFT_AMFTRB_Simulation_Form_A_Required_Remediation_Workbook_v1.0.docx',
			'title'                  => 'Form A — Required Remediation Workbook',
			'unlock_after_quiz_type' => 'form_a',
		);

		// Form B — unlock when form_b_ready; rationales/rem after form_b.
		$items[] = array(
			'file'                   => 'question-banks/CTA_LMFT_AMFTRB_Simulation_Form_B_180_Question_Candidate_Assessment_v1.0.docx',
			'title'                  => 'Form B — 180-Question Comprehensive Simulation (Candidate Assessment)',
			'is_practice_test'       => 1,
			'unlock_after_quiz_type' => 'form_b_ready',
		);
		$items[] = array(
			'file'                   => 'rationales/CTA_LMFT_AMFTRB_Simulation_Form_B_180_Question_Controlled_Answer_Key_and_Detailed_Rationales_v1.0.docx',
			'title'                  => 'Form B — Controlled Answer Key and Detailed Rationales',
			'unlock_after_quiz_type' => 'form_b',
		);
		$items[] = array(
			'file'                   => 'remediation/CTA_LMFT_AMFTRB_Simulation_Form_B_Domain_Performance_Report_v1.0.docx',
			'title'                  => 'Form B — Domain Performance Report',
			'unlock_after_quiz_type' => 'form_b',
		);
		$items[] = array(
			'file'                   => 'remediation/CTA_LMFT_AMFTRB_Simulation_Form_B_Required_Remediation_Workbook_and_Final_Readiness_Gate_v1.0.docx',
			'title'                  => 'Form B — Required Remediation Workbook and Final Readiness Gate',
			'unlock_after_quiz_type' => 'form_b',
		);

		// Course-level study tools, Start Here, California companion, and transcript (workbook_num 0 / omitted).
		$items[] = array(
			'file'  => 'audio/CTA_LMFT_AMFTRB_WB1-12_Authoritative_Audio_Recording_Script_and_Transcript_v1.1.docx',
			'title' => $transcript_title,
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LMFT_AMFTRB_WB1-12_120_Card_Flashcard_Study_Collection_v1.0.docx',
			'title' => '120-Card Flashcard Study Collection',
		);
		$items[] = array(
			'file'  => 'study-tools/CTA_LMFT_AMFTRB_WB1-12_Quick_Reference_Collection_v1.0.docx',
			'title' => 'Quick Reference Collection',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LMFT_AMFTRB_Start_Here_Learner_Roadmap_Schedules_and_Progress_Tools_v1.0.docx',
			'title' => 'Start Here — Learner Roadmap, Schedules, and Progress Tools',
		);
		$items[] = array(
			'file'  => 'student-support/CTA_LMFT_AMFTRB_California_Transition_and_Candidate_Routing_Companion_v1.0.docx',
			'title' => 'California Transition and Candidate Routing Companion',
		);

		return $items;
	}

	/**
	 * Find existing downloadable resource by exact title, or by basename in file_path/file_url.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $title     Resource title.
	 * @param string $rel_path  Relative source path under MATERIALS_REL.
	 * @return int
	 */
	private static function find_resource_id( $course_id, $title, $rel_path ) {
		$by_title = self::find_resource_id_by_title( $course_id, $title );
		if ( $by_title ) {
			return $by_title;
		}

		$basename = basename( str_replace( '\\', '/', (string) $rel_path ) );
		if ( '' === $basename ) {
			return 0;
		}

		global $wpdb;

		$like = '%' . $wpdb->esc_like( $basename ) . '%';

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
}

}
