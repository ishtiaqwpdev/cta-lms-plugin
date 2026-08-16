<?php
/**
 * Per-program configuration for the Exam Prep Course Home "Getting Started" section.
 *
 * Same layout/structure across all exam-prep programs; content and resource links vary by slug.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Exam_Prep_Getting_Started
 */
if ( ! class_exists( 'CTA_Exam_Prep_Getting_Started' ) ) {

class CTA_Exam_Prep_Getting_Started {

	/**
	 * Default roadmap steps (structure shared by all programs).
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function default_roadmap_steps() {
		return array(
			array(
				'key'         => 'orientation',
				'label'       => __( 'Getting Started', 'cta-lms' ),
				'description' => __( 'Orientation, exam overview, and study plan', 'cta-lms' ),
			),
			array(
				'key'         => 'workbooks',
				'label'       => __( 'Workbooks', 'cta-lms' ),
				'description' => __( 'Guided reading and applied reasoning', 'cta-lms' ),
			),
			array(
				'key'         => 'practice_banks',
				'label'       => __( 'Practice Banks', 'cta-lms' ),
				'description' => __( 'Chapter and workbook quizzes with rationales', 'cta-lms' ),
			),
			array(
				'key'         => 'flashcards',
				'label'       => __( 'Flashcards', 'cta-lms' ),
				'description' => __( 'Spaced review of high-yield concepts', 'cta-lms' ),
			),
			array(
				'key'         => 'practice_exams',
				'label'       => __( 'Practice Exams', 'cta-lms' ),
				'description' => __( 'Full-length Form A / Form B simulations', 'cta-lms' ),
			),
			array(
				'key'         => 'readiness',
				'label'       => __( 'Final Readiness Check', 'cta-lms' ),
				'description' => __( 'Self-assessment and exam-day preparation', 'cta-lms' ),
			),
		);
	}

	/**
	 * Resolve program config key from a course row.
	 *
	 * @param object $course Course row.
	 * @return string
	 */
	public static function program_key_for_course( $course ) {
		$slug = sanitize_title( (string) ( $course->slug ?? '' ) );
		$map  = self::get_program_slug_map();

		return isset( $map[ $slug ] ) ? $map[ $slug ] : 'default';
	}

	/**
	 * Course slug → internal program key.
	 *
	 * @return array<string,string>
	 */
	public static function get_program_slug_map() {
		return array(
			'california-law-ethics-exam-preparation'       => 'lmft-law-ethics',
			'lcsw-california-law-ethics-exam-preparation'  => 'lcsw-law-ethics',
			'lpcc-california-law-ethics-exam-preparation'  => 'lpcc-law-ethics',
			'lmft-amftrb-national-exam-preparation'        => 'lmft-amftrb',
			'lmft-california-clinical-exam-preparation'    => 'lmft-clinical',
			'lcsw-aswb-clinical-exam-preparation'          => 'lcsw-aswb',
			'lpcc-ncmhce-exam-preparation'                 => 'lpcc-ncmhce',
		);
	}

	/**
	 * Build merged getting-started payload for rendering.
	 *
	 * @param object $course    Course row.
	 * @param array  $resources Downloadable resource rows for the course.
	 * @return array<string,mixed>
	 */
	public static function get_config_for_course( $course, $resources = array() ) {
		$key    = self::program_key_for_course( $course );
		$base   = self::get_program_configs();
		$config = isset( $base[ $key ] ) ? $base[ $key ] : $base['default'];

		$config['program_key'] = $key;
		$config['roadmap_steps'] = ! empty( $config['roadmap_steps'] )
			? $config['roadmap_steps']
			: self::default_roadmap_steps();

		$config = self::attach_resource_links( $config, $resources );

		/**
		 * Filter exam prep getting-started section content per program.
		 *
		 * @param array  $config    Section config.
		 * @param object $course    Course row.
		 * @param array  $resources Resource rows.
		 */
		return apply_filters( 'cta_exam_prep_getting_started_config', $config, $course, $resources );
	}

	/**
	 * Match downloadable resources to schedule and readiness links.
	 *
	 * @param array $config    Program config.
	 * @param array $resources Resource rows.
	 * @return array
	 */
	private static function attach_resource_links( array $config, $resources ) {
		$schedules_url   = '';
		$readiness_url   = '';
		$schedules_title = '';
		$readiness_title = '';

		foreach ( (array) $resources as $resource ) {
			$title = (string) ( $resource->title ?? '' );

			if ( ! $schedules_url && self::is_study_schedule_resource_title( $title ) ) {
				if ( class_exists( 'CTA_Course_Materials' ) ) {
					$schedules_url   = CTA_Course_Materials::get_serve_url( (int) $resource->id );
					$schedules_title = $title;
				}
			}

			if ( ! $readiness_url && self::is_readiness_resource_title( $title ) ) {
				if ( class_exists( 'CTA_Course_Materials' ) ) {
					$readiness_url   = CTA_Course_Materials::get_serve_url( (int) $resource->id );
					$readiness_title = $title;
				}
			}
		}

		if ( empty( $config['study_schedules']['combined_url'] ) && $schedules_url ) {
			$config['study_schedules']['combined_url']    = $schedules_url;
			$config['study_schedules']['combined_title']  = $schedules_title;
		}

		if ( empty( $config['readiness']['url'] ) && $readiness_url ) {
			$config['readiness']['url']   = $readiness_url;
			$config['readiness']['title'] = $readiness_title;
		}

		return $config;
	}

	/**
	 * Whether a downloadable title is a study-schedule / roadmap pacing file.
	 *
	 * Bare week numbers (e.g. "10") must never match — that incorrectly binds
	 * Workbook 10 into the Study Schedules box on Course Home.
	 *
	 * @param string $title Resource title.
	 * @return bool
	 */
	public static function is_study_schedule_resource_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		if ( '' === $title ) {
			return false;
		}

		// Workbooks (and workbook practice banks) are never schedule resources.
		if ( preg_match( '/\bworkbook\s*\d+/', $title ) ) {
			return false;
		}

		$needles = array(
			'study schedule',
			'study schedules',
			'student roadmap',
			'learner roadmap',
			'start-here roadmap',
			'start here roadmap',
			'roadmap and',
			'roadmap, schedules',
			'roadmap, schedule',
			'10-week',
			'14-week',
			'18-week',
			'10-, 14-',
			'10-,14-',
			'week study schedule',
			'pacing plan',
			'pacing options',
			'schedules, and progress',
		);

		return self::title_matches_any( $title, $needles );
	}

	/**
	 * Whether a downloadable title is a readiness / progress-tracker tool.
	 *
	 * @param string $title Resource title.
	 * @return bool
	 */
	public static function is_readiness_resource_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		if ( '' === $title ) {
			return false;
		}

		if ( preg_match( '/\bworkbook\s*\d+/', $title ) ) {
			return false;
		}

		return self::title_matches_any(
			$title,
			array(
				'readiness',
				'progress tracker',
			)
		);
	}

	/**
	 * @param string   $title    Lowercased title.
	 * @param string[] $needles  Substrings to match.
	 * @return bool
	 */
	private static function title_matches_any( $title, $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $title, strtolower( $needle ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Program-specific content (placeholder-ready structure).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_program_configs() {
		return array(
			'default' => array(
				'orientation' => array(
					'intro' => __( 'This exam preparation program combines structured workbooks, practice questions, flashcards, and full-length practice exams. Use this dashboard to orient yourself, choose a study schedule, and track readiness before test day.', 'cta-lms' ),
				),
				'study_sequence' => self::default_study_sequence(),
				'exam_overview'  => array(
					'exam_name' => __( '[Exam name — to be configured]', 'cta-lms' ),
					'format'    => __( '[Exam format — to be configured]', 'cta-lms' ),
					'domains'   => array(
						__( '[Domain / content area 1]', 'cta-lms' ),
						__( '[Domain / content area 2]', 'cta-lms' ),
						__( '[Domain / content area 3]', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro'         => __( 'Choose a pacing plan that fits your timeline. Schedules are available in your downloadable study tools.', 'cta-lms' ),
					'combined_url'  => '',
					'combined_title'=> '',
				),
				'readiness' => array(
					'summary' => __( 'You are ready when you consistently score at or above target on practice exams, have closed knowledge gaps from your error log, and can explain why answers are correct—not just memorize facts.', 'cta-lms' ),
					'url'     => '',
					'title'   => '',
				),
			),
			'lmft-clinical' => array(
				'orientation' => array(
					'intro' => __( 'Prepare for the California LMFT Clinical Exam with 12 applied-reasoning workbooks, workbook practice banks, flashcards, and California-format Form A/B practice exams. Start with orientation, pick a 10-, 14-, or 18-week schedule, then work through materials in the recommended sequence below.', 'cta-lms' ),
				),
				'study_sequence' => self::default_study_sequence( 12 ),
				'exam_overview'  => array(
					'exam_name' => __( 'California LMFT Clinical Exam', 'cta-lms' ),
					'format'    => __( 'Clinical vignette–based exam assessing diagnosis, treatment planning, crisis response, law & ethics, and California-specific practice.', 'cta-lms' ),
					'domains'   => array(
						__( 'Clinical assessment and diagnosis', 'cta-lms' ),
						__( 'Treatment planning and interventions', 'cta-lms' ),
						__( 'Crisis and safety', 'cta-lms' ),
						__( 'Law, ethics, and professional practice (California)', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro' => __( 'Download the roadmap document for 10-, 14-, and 18-week pacing options aligned to all 12 workbooks and practice exams.', 'cta-lms' ),
				),
				'readiness' => array(
					'summary' => __( 'Use the Readiness Self-Assessment to track workbook completion, practice bank performance, and Form A/B scores before scheduling your exam.', 'cta-lms' ),
				),
			),
			'lmft-amftrb' => array(
				'orientation' => array(
					'intro' => __( 'Prepare for the AMFTRB National MFT Exam with 12 workbooks, 12 chapter practice banks, checkpoint assessments, audio reviews, flashcards, and 180-question Form A/B simulations. Begin with the Start Here roadmap and select a study schedule that matches your exam date.', 'cta-lms' ),
				),
				'study_sequence' => self::default_study_sequence( 12, true ),
				'exam_overview'  => array(
					'exam_name' => __( 'AMFTRB National MFT Examination', 'cta-lms' ),
					'format'    => __( '200-item national exam (this program uses 180-question Form A/B simulations); knowledge and scenario-based items across MFT practice domains.', 'cta-lms' ),
					'domains'   => array(
						__( 'Practice of marriage and family therapy', 'cta-lms' ),
						__( 'Intake, assessment, and diagnosis', 'cta-lms' ),
						__( 'Treatment planning and interventions', 'cta-lms' ),
						__( 'Professional ethics and legal responsibilities', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro' => __( 'The Start Here roadmap includes 10-, 14-, and 18-week schedules plus baseline inventory and progress tracking tools.', 'cta-lms' ),
				),
				'readiness' => array(
					'summary' => __( 'Complete the Final Readiness Gate workbook after Form B remediation to confirm you are exam-ready.', 'cta-lms' ),
				),
			),
			'lcsw-aswb' => array(
				'orientation' => array(
					'intro' => __( 'Prepare for the ASWB Clinical Exam with 12 workbooks aligned to ASWB content areas, workbook practice banks, flashcards, and 122-question Form A/B simulations (2026 format). Review the roadmap, choose a study schedule, and follow the sequence below.', 'cta-lms' ),
				),
				'study_sequence' => self::default_study_sequence( 12 ),
				'exam_overview'  => array(
					'exam_name' => __( 'ASWB Clinical Exam', 'cta-lms' ),
					'format'    => __( '170-question computer-based exam (program uses 122-question Form A/B practice simulations); scenario-based clinical social work items.', 'cta-lms' ),
					'domains'   => array(
						__( 'Values & Ethics', 'cta-lms' ),
						__( 'Assessment & Planning', 'cta-lms' ),
						__( 'Intervention & Practice', 'cta-lms' ),
						__( 'Practice Simulations', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro' => __( 'Download the Student Roadmap for 10-, 14-, and 18-week study schedule options.', 'cta-lms' ),
				),
				'readiness' => array(
					'summary' => __( 'Use the Readiness Self-Assessment and Progress Tracker to confirm consistent performance on practice banks and Form A/B before test day.', 'cta-lms' ),
				),
			),
			'lpcc-ncmhce' => array(
				'orientation' => array(
					'intro' => __( 'Prepare for the NCMHCE with 12 workbooks, paired practice banks, checkpoint assessments, flashcards, and 143-question Form A/B clinical simulations. Start with orientation, select a 10-, 14-, or 18-week schedule, and work through the program in the order below.', 'cta-lms' ),
				),
				'study_sequence' => self::default_study_sequence( 12, true ),
				'exam_overview'  => array(
					'exam_name' => __( 'NCMHCE (National Clinical Mental Health Counseling Examination)', 'cta-lms' ),
					'format'    => __( 'Scenario-based simulations requiring clinical decision-making across counseling domains (program uses 143-question Form A/B practice simulations).', 'cta-lms' ),
					'domains'   => array(
						__( 'Professional practice and ethics', 'cta-lms' ),
						__( 'Intake, assessment, and diagnosis', 'cta-lms' ),
						__( 'Treatment planning and interventions', 'cta-lms' ),
						__( 'Core counseling constructs and modalities', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro' => __( 'The Start-Here Roadmap document includes 10-, 14-, and 18-week pacing plans for all 12 workbooks.', 'cta-lms' ),
				),
				'readiness' => array(
					'summary' => __( 'Complete the Readiness Self-Assessment and Progress Tracker after integrated review workbooks and strong Form A/B performance.', 'cta-lms' ),
				),
			),
			'lmft-law-ethics' => array(
				'orientation' => array(
					'intro' => __( 'Welcome to the CTA LMFT California Law & Ethics Exam Preparation Program. This program is designed to help you identify the controlling legal or ethical issue, apply LMFT- and AMFT-specific rules, choose the best professional action, and use detailed feedback to repair weak areas. Start with the required LMFT Practice Act module before Workbook 1.', 'cta-lms' ),
				),
				'roadmap_steps' => self::law_ethics_roadmap_steps(),
				'study_sequence' => array(
					array(
						'title'       => __( 'Start Here', 'cta-lms' ),
						'description' => __( 'Read orientation, notices, access rules, and support boundaries.', 'cta-lms' ),
					),
					array(
						'title'       => __( 'LMFT Practice Act Module', 'cta-lms' ),
						'description' => __( 'Complete the module and submit the 25-question assessment.', 'cta-lms' ),
					),
					array(
						'title'       => __( 'Workbooks 1–9', 'cta-lms' ),
						'description' => __( 'Read each workbook, complete its candidate assessment, then analyze gated rationales and remediation.', 'cta-lms' ),
					),
					array(
						'title'       => __( 'Practice Examination A', 'cta-lms' ),
						'description' => __( 'Complete the 50-question form and performance worksheet.', 'cta-lms' ),
					),
					array(
						'title'       => __( 'Practice Examination B', 'cta-lms' ),
						'description' => __( 'Complete the second 50-question form and compare error patterns.', 'cta-lms' ),
					),
					array(
						'title'       => __( 'Comprehensive Final Examination', 'cta-lms' ),
						'description' => __( 'Complete the 100-question form and build a targeted final study plan.', 'cta-lms' ),
					),
					array(
						'title'       => __( 'Study Center and Toolkits', 'cta-lms' ),
						'description' => __( 'Use the 807-card study center and six toolkits throughout the program.', 'cta-lms' ),
					),
					array(
						'title'       => __( 'Program Close', 'cta-lms' ),
						'description' => __( 'Review strengths, open gaps, next-study actions, and test-day preparation.', 'cta-lms' ),
					),
				),
				'exam_overview'  => array(
					'exam_name' => __( 'California LMFT Law and Ethics Examination', 'cta-lms' ),
					'format'    => __( 'Self-paced, asynchronous online exam-preparation program with six months of access. Exam preparation only — no CE credit or certificate.', 'cta-lms' ),
					'domains'   => array(
						__( 'LMFT Practice Act and AMFT professional identity', 'cta-lms' ),
						__( 'Consent, telehealth, competence, and impairment', 'cta-lms' ),
						__( 'Client protection, boundaries, and cultural humility', 'cta-lms' ),
						__( 'Confidentiality and documentation', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro' => __( 'Study schedule and error-log tools are included in your downloadable program toolkit (when available for this release).', 'cta-lms' ),
				),
				'readiness' => array(
					'summary' => __( 'An 80% score may be used as a CTA study benchmark. It is not a completion gate and does not guarantee examination passage. Use your performance worksheets and Final Study Check before scheduling with BBS.', 'cta-lms' ),
				),
			),
			'lcsw-law-ethics' => array(
				'orientation' => array(
					'intro' => __( 'Prepare for the California LCSW/ASW Law & Ethics Exam with license-specific Start Here orientation, 9 workbooks (45 chapters), chapter tests, flashcards, Form A/B practice exams, and a comprehensive final. Follow the roadmap and recommended sequence below.', 'cta-lms' ),
				),
				'roadmap_steps' => self::law_ethics_roadmap_steps(),
				'study_sequence' => self::law_ethics_study_sequence( 'LCSW/ASW' ),
				'exam_overview'  => array(
					'exam_name' => __( 'California LCSW/ASW Law & Ethics Examination', 'cta-lms' ),
					'format'    => __( '75-question exam covering BBS statutes, regulations, NASW/CAMFT-aligned ethics, and California social work scope of practice.', 'cta-lms' ),
					'domains'   => array(
						__( 'Confidentiality and privilege', 'cta-lms' ),
						__( 'Scope of practice', 'cta-lms' ),
						__( 'Supervision and professional conduct', 'cta-lms' ),
						__( 'Mandated reporting and legal compliance', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro' => __( 'Use the 45-Chapter Master Study Map for pacing guidance across all workbook chapters.', 'cta-lms' ),
				),
				'readiness' => array(
					'summary' => __( 'Use the Readiness Checklist Toolkit to confirm chapter mastery, practice exam performance, and final exam readiness.', 'cta-lms' ),
				),
			),
			'lpcc-law-ethics' => array(
				'orientation' => array(
					'intro' => __( 'Prepare for the California LPCC/APCC Law & Ethics Exam with license-specific orientation, 9 workbooks (45 chapters), 45 chapter tests, flashcards, Form A/B practice exams, and a comprehensive final. Review the program journey and select study tools below.', 'cta-lms' ),
				),
				'roadmap_steps' => self::law_ethics_roadmap_steps(),
				'study_sequence' => self::law_ethics_study_sequence( 'LPCC/APCC' ),
				'exam_overview'  => array(
					'exam_name' => __( 'California LPCC/APCC Law & Ethics Examination', 'cta-lms' ),
					'format'    => __( '75-question exam covering BBS statutes, regulations, professional ethics, and California professional counselor scope of practice.', 'cta-lms' ),
					'domains'   => array(
						__( 'Confidentiality and privilege', 'cta-lms' ),
						__( 'Scope of practice', 'cta-lms' ),
						__( 'Supervision and professional conduct', 'cta-lms' ),
						__( 'Mandated reporting and legal compliance', 'cta-lms' ),
					),
				),
				'study_schedules' => array(
					'intro' => __( 'Download the R9A Study Schedules and Error Log for 10-, 14-, and 18-week pacing options.', 'cta-lms' ),
				),
				'readiness' => array(
					'summary' => __( 'Use the Readiness Checklist Toolkit and Program Close guide to confirm you are prepared for the real exam.', 'cta-lms' ),
				),
			),
		);
	}

	/**
	 * Default recommended study sequence for 12-workbook clinical programs.
	 *
	 * @param int  $workbook_count Workbooks in program.
	 * @param bool $with_checkpoints Include checkpoint step.
	 * @return array<int,array<string,string>>
	 */
	private static function default_study_sequence( $workbook_count = 12, $with_checkpoints = false ) {
		$sequence = array(
			array(
				'title'       => __( 'Start Here & choose a schedule', 'cta-lms' ),
				'description' => __( 'Read orientation, pick 10-, 14-, or 18-week pacing, and preview the full roadmap.', 'cta-lms' ),
			),
			array(
				'title'       => __( 'Work through workbooks in order', 'cta-lms' ),
				'description' => sprintf(
					/* translators: %d: number of workbooks */
					__( 'Complete Workbooks 1–%d with active reading and note-taking. Mark complete as you finish each.', 'cta-lms' ),
					(int) $workbook_count
				),
			),
			array(
				'title'       => __( 'Take practice banks after each workbook', 'cta-lms' ),
				'description' => __( 'Use chapter/workbook quizzes to test recall; review every rationale, even for correct answers.', 'cta-lms' ),
			),
		);

		if ( $with_checkpoints ) {
			$sequence[] = array(
				'title'       => __( 'Complete checkpoint assessments', 'cta-lms' ),
				'description' => __( 'Use mid-program checkpoints to identify weak domains before full simulations.', 'cta-lms' ),
			);
		}

		$sequence[] = array(
			'title'       => __( 'Review flashcards between workbooks', 'cta-lms' ),
			'description' => __( 'Use spaced repetition to reinforce terms, statutes, and clinical distinctions.', 'cta-lms' ),
		);
		$sequence[] = array(
			'title'       => __( 'Take Form A, then Form B practice exams', 'cta-lms' ),
			'description' => __( 'Simulate exam conditions. Remediate missed items before retaking or scheduling the real exam.', 'cta-lms' ),
		);
		$sequence[] = array(
			'title'       => __( 'Complete readiness self-assessment', 'cta-lms' ),
			'description' => __( 'Confirm consistent target scores and closed error-log gaps before test day.', 'cta-lms' ),
		);

		return $sequence;
	}

	/**
	 * Law & ethics program roadmap (9 workbooks + chapter tests).
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function law_ethics_roadmap_steps() {
		return array(
			array(
				'key'         => 'orientation',
				'label'       => __( 'Start Here', 'cta-lms' ),
				'description' => __( 'License-specific orientation module', 'cta-lms' ),
			),
			array(
				'key'         => 'workbooks',
				'label'       => __( '9 Workbooks', 'cta-lms' ),
				'description' => __( '45 chapters of law & ethics content', 'cta-lms' ),
			),
			array(
				'key'         => 'practice_banks',
				'label'       => __( 'Chapter Tests', 'cta-lms' ),
				'description' => __( 'Per-chapter practice with rationales', 'cta-lms' ),
			),
			array(
				'key'         => 'flashcards',
				'label'       => __( 'Flashcards', 'cta-lms' ),
				'description' => __( 'Statute, ethics, and scenario review', 'cta-lms' ),
			),
			array(
				'key'         => 'practice_exams',
				'label'       => __( 'Form A & Form B', 'cta-lms' ),
				'description' => __( '50-question practice exams each', 'cta-lms' ),
			),
			array(
				'key'         => 'readiness',
				'label'       => __( 'Comprehensive Final', 'cta-lms' ),
				'description' => __( '100-question final readiness simulation', 'cta-lms' ),
			),
		);
	}

	/**
	 * Law & ethics recommended sequence.
	 *
	 * @param string $license_label License label for copy.
	 * @return array<int,array<string,string>>
	 */
	private static function law_ethics_study_sequence( $license_label ) {
		return array(
			array(
				'title'       => __( 'Complete Start Here orientation', 'cta-lms' ),
				'description' => sprintf(
					/* translators: %s: license type label */
					__( 'Review %s-specific licensing context and program rules before Workbook 1.', 'cta-lms' ),
					$license_label
				),
			),
			array(
				'title'       => __( 'Work through Workbooks 1–9', 'cta-lms' ),
				'description' => __( 'Follow chapter order; complete the chapter test after each chapter or workbook section.', 'cta-lms' ),
			),
			array(
				'title'       => __( 'Use flashcards for statutes and ethics codes', 'cta-lms' ),
				'description' => __( 'Reinforce BBS, CAMFT, and high-frequency law distinctions daily.', 'cta-lms' ),
			),
			array(
				'title'       => __( 'Take Form A and Form B practice exams', 'cta-lms' ),
				'description' => __( 'Practice under timed conditions; log misses in your error log.', 'cta-lms' ),
			),
			array(
				'title'       => __( 'Pass the Comprehensive Final', 'cta-lms' ),
				'description' => __( 'Use the 100-question final as your last simulation before scheduling the BBS exam.', 'cta-lms' ),
			),
		);
	}
}

}
