<?php
/**
 * CTA LMFT California Law & Ethics Exam Preparation — Website/LMS Copy Package (v1.1).
 *
 * Approved final copy (Candice Fuimaono, August 3, 2026). Paste verbatim; do not paraphrase.
 * Status: INTERNAL FINAL COPY — NOT YET AUTHORIZED FOR PUBLIC USE.
 * Program code CTA-EP-001 is internal-only and must never appear on learner/public surfaces.
 *
 * @package CTA_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CTA_Lmft_Law_Ethics_Copy
 */
if ( ! class_exists( 'CTA_Lmft_Law_Ethics_Copy' ) ) {

class CTA_Lmft_Law_Ethics_Copy {

	const TITLE        = 'CTA LMFT California Law & Ethics Exam Preparation Program';
	const PUBLIC_TITLE = 'LMFT California Law & Ethics Exam Preparation';
	const PAGE_LABEL   = 'LMFT Law & Ethics Exam Preparation';
	const SEO_TITLE    = 'California LMFT Law & Ethics Exam Preparation | CTA';
	const URL_SLUG     = 'lmft-california-law-ethics-exam-preparation';
	/** Technical course slug already seeded in LMS maps (do not change without migration). */
	const COURSE_SLUG  = 'california-law-ethics-exam-preparation';
	const PRICE        = 199.00;
	const ACCESS_MONTHS = 6;
	/** Internal control code — never render on public/learner surfaces. */
	const PROGRAM_CODE_INTERNAL = 'CTA-EP-001';
	const CATALOG_STATUS        = 'Under Review';
	const UNIT_TOTAL            = 16;

	/**
	 * SEO meta description (verbatim).
	 *
	 * @return string
	 */
	public static function meta_description() {
		return 'Prepare for the California LMFT Law and Ethics Examination with nine workbooks, an LMFT Practice Act module, original assessments, detailed rationales, practice exams, 807 flashcards, and six months of access.';
	}

	/**
	 * Hero headline (verbatim).
	 *
	 * @return string
	 */
	public static function hero_headline() {
		return 'Prepare for the California LMFT Law and Ethics Examination';
	}

	/**
	 * Hero subheadline (verbatim).
	 *
	 * @return string
	 */
	public static function hero_subheadline() {
		return 'Build legal and ethical reasoning through a complete LMFT-focused study system - not isolated memorization.';
	}

	/**
	 * Hero support line (verbatim).
	 *
	 * @return string
	 */
	public static function hero_support_line() {
		return 'Nine workbooks, a required LMFT Practice Act module, original assessments, detailed rationales, cumulative practice examinations, 807 flashcards, and targeted remediation tools.';
	}

	/**
	 * Short description (verbatim).
	 *
	 * @return string
	 */
	public static function short_description() {
		return 'Prepare for the California LMFT Law and Ethics Examination with a self-paced, six-month program designed for AMFTs and other eligible LMFT applicants. The program combines nine California law and ethics workbooks, a required LMFT Practice Act and professional-identity module, original assessments, detailed post-attempt rationales, cumulative practice examinations, flashcards, and targeted remediation tools.';
	}

	/**
	 * Long description paragraphs (verbatim).
	 *
	 * @return string[]
	 */
	public static function long_description_paragraphs() {
		return array(
			'California law and ethics questions require more than recalling a rule. Candidates must identify the professional role, locate the controlling authority, distinguish current law from a tempting distractor, protect the client or public, and choose the best next action. CTA organizes that reasoning into a coordinated LMFT-focused study system.',
			'The program begins with an LMFT Practice Act and AMFT professional-identity module, then moves through nine California law and ethics workbooks covering consent, telehealth, competence, impairment, client protection, boundaries, cultural humility, confidentiality, and documentation. Learners apply the material through answer-hidden assessments and receive detailed option-by-option rationales only after the approved attempt point.',
			'Practice Examination A, Practice Examination B, and the Comprehensive Final Examination support cumulative review. An 807-card flashcard study center, six printable toolkits, performance worksheets, error-repair tools, and remediation pathways help learners turn missed questions into a focused study plan.',
			'CTA teaches the reasoning behind the right answer - not only the answer itself. The goal is to strengthen the legal, ethical, and professional judgment candidates use on the examination and throughout clinical practice.',
		);
	}

	/**
	 * What Is Included bullets (exact wording/order).
	 *
	 * @return string[]
	 */
	public static function what_is_included() {
		return array(
			'Required LMFT Practice Act, AMFT Professional Identity, and California Examination Distinctions module.',
			'Nine LMFT Candidate Edition workbooks covering 45 shared-core chapters.',
			'Nine answer-hidden workbook assessment forms totaling 765 questions, with separate gated answer keys, detailed option-by-option rationales, exam strategies, and remediation guidance.',
			'A separate 25-question LMFT Practice Act module assessment and controlled rationale edition.',
			'Practice Examination A (50 questions), Practice Examination B (50 questions), and a Comprehensive Final Examination (100 questions), each with a learner booklet, controlled rationale edition, and performance worksheet.',
			'An interactive 807-card flashcard study center plus printable single-sided and duplex editions.',
			'Six LMFT-focused printable toolkits for study mapping, numbers and timelines, California law and ethics decisions, exam strategy, and common traps.',
			'Six months of access to enrolled learner materials and updates released during the active access period.',
		);
	}

	/**
	 * Who This Program Is For bullets (exact wording/order).
	 *
	 * @return string[]
	 */
	public static function who_this_is_for() {
		return array(
			'Registered AMFTs preparing for the California LMFT Law and Ethics Examination.',
			'Other LMFT applicants who are eligible to take the California LMFT Law and Ethics Examination under current BBS rules.',
			'Candidates who want structured legal and ethical reasoning, detailed answer analysis, and a repeatable remediation process.',
		);
	}

	/**
	 * Pathway boundary notice under Who This Program Is For.
	 *
	 * @return string
	 */
	public static function pathway_boundary_notice() {
		return 'This program does not prepare learners for the California LMFT Clinical Examination or the AMFTRB National Examination. Those are separate examination pathways and separate CTA products.';
	}

	/**
	 * Master catalog description (verbatim).
	 *
	 * @return string
	 */
	public static function catalog_description() {
		return 'A coordinated LMFT-focused California law and ethics study system with a required Practice Act module, nine workbooks, answer-hidden assessments, gated detailed rationales, cumulative examinations, 807 flashcards, six toolkits, and targeted remediation.';
	}

	/**
	 * Checkout description (verbatim).
	 *
	 * @return string
	 */
	public static function checkout_description() {
		return 'CTA LMFT California Law & Ethics Exam Preparation Program - self-paced exam preparation for eligible California LMFT Law and Ethics Examination candidates. Includes six months of access to the LMFT Practice Act module, nine workbooks, protected assessments and post-attempt rationales, cumulative practice examinations, 807 flashcards, six study toolkits, and remediation tools. Exam preparation only; no CE credit or certificate.';
	}

	/**
	 * Required checkout acknowledgments (must all be checked).
	 *
	 * @return string[]
	 */
	public static function checkout_acknowledgments() {
		return array(
			'I understand that this is an exam-preparation program and does not provide continuing education credit or a CE certificate.',
			'I understand that CTA does not guarantee examination passage, determine examination eligibility, or issue a professional license.',
			'I understand that the materials are licensed for my personal study use and may not be shared, resold, publicly posted, or commercially reproduced.',
			'I understand that access lasts six months from enrollment and that any extension is governed by CTA\'s approved extension policy.',
			'I have reviewed the CTA Refund Policy and Terms of Use presented at checkout.',
		);
	}

	/**
	 * Receipt / enrollment confirmation template (use placeholders).
	 *
	 * @return string
	 */
	public static function enrollment_confirmation_template() {
		return 'You are enrolled in the CTA LMFT California Law & Ethics Exam Preparation Program. Your six-month access begins on [ENROLLMENT DATE] and is scheduled to end on [EXPIRATION DATE]. Sign in to your learner dashboard and open Start Here before beginning the LMFT Practice Act module.';
	}

	/**
	 * Dashboard card fields.
	 *
	 * @return array{title:string,subtitle:string,button:string,progress_template:string,access_template:string}
	 */
	public static function dashboard_card() {
		return array(
			'title'             => 'LMFT California Law & Ethics Exam Preparation',
			'subtitle'          => 'Practice Act, 9 workbooks, assessments, practice exams, flashcards, and remediation',
			'button'            => 'Continue Studying',
			'progress_template' => '[X] of 16 units complete',
			'access_template'   => 'Access ends [DATE]',
		);
	}

	/**
	 * Fifteen FAQs in approved order (question => answer).
	 *
	 * @return array<int,array{question:string,answer:string}>
	 */
	public static function faqs() {
		return array(
			array(
				'question' => 'Is this a continuing education course?',
				'answer'   => 'No. This is an exam-preparation program. It does not award CE credit, require a CE evaluation, or generate a CE certificate.',
			),
			array(
				'question' => 'Who is the program designed for?',
				'answer'   => 'It is designed for AMFTs and other eligible candidates preparing for the California LMFT Law and Ethics Examination. Candidates should confirm their own eligibility and current examination requirements with BBS.',
			),
			array(
				'question' => 'Does CTA guarantee that I will pass?',
				'answer'   => 'No. The program supports preparation and readiness, but no course can guarantee passage or determine examination eligibility or licensure.',
			),
			array(
				'question' => 'Is CTA affiliated with BBS or Pearson VUE?',
				'answer'   => 'No. CTA is an independent educational resource and is not affiliated with or endorsed by BBS, Pearson VUE, or another examination administrator.',
			),
			array(
				'question' => 'How long will I have access?',
				'answer'   => 'Access lasts six months from enrollment. Your dashboard should display the enrollment and expiration dates.',
			),
			array(
				'question' => 'What should I complete first?',
				'answer'   => 'Begin with Start Here and the required LMFT Practice Act and AMFT Professional Identity module, then move through Workbooks 1 through 9 before the cumulative examinations.',
			),
			array(
				'question' => 'When will I see answers and rationales?',
				'answer'   => 'Answer keys, detailed rationales, exam strategies, and remediation are released only after the associated candidate assessment is submitted or another approved attempt trigger is met.',
			),
			array(
				'question' => 'Can I download and print the materials?',
				'answer'   => 'Approved learner workbooks, exam booklets, worksheets, and toolkits may be downloaded and printed for the enrolled learner\'s personal study use during the access period.',
			),
			array(
				'question' => 'May I share the files with a colleague or study group?',
				'answer'   => 'No. Enrollment and downloadable materials are licensed to the enrolled learner. Sharing, resale, public posting, and commercial reproduction are prohibited.',
			),
			array(
				'question' => 'Are the practice questions copied from the licensing examination?',
				'answer'   => 'No. CTA uses original educational questions. The program does not include live, recalled, copied, or unauthorized licensing-examination items.',
			),
			array(
				'question' => 'Does this program prepare me for the LMFT Clinical Examination or AMFTRB exam?',
				'answer'   => 'No. This product prepares candidates for the California LMFT Law and Ethics Examination. Clinical and national examination preparation are separate CTA products.',
			),
			array(
				'question' => 'Will the content be updated?',
				'answer'   => 'CTA will update materials for significant legal or regulatory changes when reasonably practicable. Learners receive updates issued during their active access period. Updates do not automatically extend access.',
			),
			array(
				'question' => 'What does technical support cover?',
				'answer'   => 'Technical support covers enrollment, login, course access, platform operation, and file-opening issues. CTA responds to learner-support inquiries within two business days. Support does not provide legal advice, determine BBS eligibility, troubleshoot a learner\'s personal device, or provide internet service.',
			),
			array(
				'question' => 'What if I need an access extension?',
				'answer'   => 'One complimentary 30-calendar-day extension may be requested before the original six-month access period expires. Any additional extension requires case-by-case CTA approval.',
			),
			array(
				'question' => 'What is the refund policy?',
				'answer'   => 'A refund may be requested within seven calendar days of purchase only when course materials have not been accessed. After access begins, purchases are nonrefundable except for duplicate charges, CTA-caused access failures, or where applicable law requires otherwise.',
			),
		);
	}

	/**
	 * Six required disclaimers (reusable component source).
	 *
	 * @return array<string,string> keyed notice id => copy
	 */
	public static function disclaimers() {
		return array(
			'independent_resource' => 'Clinical Training & Supervision Academy is an independent educational resource and is not affiliated with or endorsed by the California Board of Behavioral Sciences, Pearson VUE, or another examination administrator.',
			'no_guarantee'         => 'Participation supports examination preparation and does not guarantee passage, establish examination eligibility, determine licensure, or guarantee employment or professional outcomes.',
			'no_ce'                => 'This is an exam-preparation program. It does not provide continuing education credit, require a CE evaluation, or issue a CE certificate.',
			'personal_use'         => 'Enrollment and downloadable materials are licensed for the enrolled learner\'s personal study use. Sharing, resale, public posting, unauthorized distribution, and commercial reproduction are prohibited.',
			'original_content'     => 'CTA questions, rationales, frameworks, graphics, and study tools are original protected educational content and do not include live, recalled, copied, or unauthorized licensing-examination questions.',
			'not_legal_advice'     => 'Educational content is not legal advice. Learners should consult current official sources, BBS, qualified counsel, or another appropriate authority for individual legal or licensing questions.',
		);
	}

	/**
	 * LMS trigger messages (§7).
	 *
	 * @return array<string,string>
	 */
	public static function lms_trigger_messages() {
		return array(
			'before_assessment'       => 'Complete the candidate assessment before opening answer review and remediation.',
			'submission_confirmation' => 'Your attempt has been submitted. Answer review, detailed rationales, and remediation are now available.',
			'controlled_file_title'   => 'Post-Attempt Answer Review, Detailed Rationales, and Remediation',
			'retake_reminder'         => 'Review your error pattern and assigned sections before beginning another attempt.',
			'expired_access'          => 'Your access period has ended. Use [SUPPORT CONTACT / FORM] for questions about your account or the approved extension policy.',
			'no_certificate'          => 'This exam-preparation program does not issue a CE certificate or award continuing education credit.',
		);
	}

	/**
	 * How to Use Each Assessment (5 steps).
	 *
	 * @return string[]
	 */
	public static function assessment_instructions() {
		return array(
			'Complete the answer-hidden candidate form without opening the controlled rationale file.',
			'Submit the attempt through the LMS or the approved completion trigger.',
			'Review the score and every option-level rationale, including questions answered correctly.',
			'Record the reason for each miss or uncertain answer in the performance worksheet or error log.',
			'Return to the assigned workbook section, toolkit, or flashcard set before retesting.',
		);
	}

	/**
	 * Start Here welcome paragraphs.
	 *
	 * @return string[]
	 */
	public static function start_here_welcome() {
		return array(
			'Welcome to the CTA LMFT California Law & Ethics Exam Preparation Program. This program is designed to help you identify the controlling legal or ethical issue, apply LMFT- and AMFT-specific rules, choose the best professional action, and use detailed feedback to repair weak areas.',
			'Start with the required LMFT Practice Act module before Workbook 1. That module establishes the professional-identity, work-setting, supervision, advertising, disclosure, and examination-pathway distinctions used throughout the program.',
		);
	}

	/**
	 * Recommended 16-unit learning sequence.
	 *
	 * @return array<int,array{unit:string,title:string,action:string}>
	 */
	public static function learning_sequence() {
		return array(
			array(
				'unit'   => '00',
				'title'  => 'Start Here',
				'action' => 'Read orientation, notices, access rules, and support boundaries.',
			),
			array(
				'unit'   => '01',
				'title'  => 'LMFT Practice Act Module',
				'action' => 'Complete the module and submit the 25-question assessment.',
			),
			array(
				'unit'   => '02–10',
				'title'  => 'Workbooks 1–9',
				'action' => 'Read each workbook, complete its candidate assessment, then analyze gated rationales and remediation.',
			),
			array(
				'unit'   => '11',
				'title'  => 'Practice Examination A',
				'action' => 'Complete the 50-question form and performance worksheet.',
			),
			array(
				'unit'   => '12',
				'title'  => 'Practice Examination B',
				'action' => 'Complete the second 50-question form and compare error patterns.',
			),
			array(
				'unit'   => '13',
				'title'  => 'Comprehensive Final Examination',
				'action' => 'Complete the 100-question form and build a targeted final study plan.',
			),
			array(
				'unit'   => '14',
				'title'  => 'Study Center and Toolkits',
				'action' => 'Use the 807-card study center and six toolkits throughout the program.',
			),
			array(
				'unit'   => '15',
				'title'  => 'Program Close',
				'action' => 'Review strengths, open gaps, next-study actions, and test-day preparation.',
			),
		);
	}

	/**
	 * Support and access notice template.
	 *
	 * @return string
	 */
	public static function support_access_notice_template() {
		return 'Your access begins on [ENROLLMENT DATE] and ends on [EXPIRATION DATE]. Downloaded materials are for your personal study use. For login, enrollment, access, or file-opening problems, use [SUPPORT CONTACT / FORM]. CTA cannot determine examination eligibility or provide legal advice about an individual licensing matter.';
	}

	/**
	 * Program Close paragraphs.
	 *
	 * @return string[]
	 */
	public static function program_close_paragraphs() {
		return array(
			'You have reached the end of the CTA LMFT California Law & Ethics Exam Preparation Program. Completion means you have worked through the program\'s learning sequence; it does not guarantee examination passage or replace the official BBS eligibility and scheduling process.',
			'Before scheduling or sitting for the examination, review your performance worksheets, unresolved error categories, high-yield timelines, LMFT Practice Act distinctions, and the questions you answered correctly for the wrong reason. Confirm current examination and eligibility information directly with BBS and the designated testing administrator.',
		);
	}

	/**
	 * Final Study Check checklist items.
	 *
	 * @return string[]
	 */
	public static function final_study_check() {
		return array(
			'I can separate LMFT, AMFT, trainee, applicant, supervisor, and employer roles.',
			'I can identify the current controlling source before relying on memory or a generalized ethical principle.',
			'I can distinguish a legal duty from an ethical best practice and identify when both apply.',
			'I can recognize timing words such as FIRST, NEXT, BEST, MOST, INITIAL, and EXCEPT.',
			'I can explain why each distractor is weaker, premature, incomplete, excessive, or assigned to the wrong role.',
			'I have a final review plan based on evidence from my own performance rather than reassurance alone.',
		);
	}

	/**
	 * Purchase panel field labels/copy.
	 *
	 * @return array<string,string>
	 */
	public static function purchase_panel() {
		return array(
			'price'            => '$199 launch price',
			'access'           => 'Six months of online access',
			'format'           => 'Self-paced and asynchronous',
			'credit'           => 'Exam preparation only - no CE credit or certificate',
			'primary_button'   => 'Start LMFT Law & Ethics Exam Preparation',
			'secondary_button' => 'See Everything Included',
			'availability'     => 'Do not activate until Approved for Release',
		);
	}

	/**
	 * Business / assessment rules (config, not marketing copy).
	 *
	 * @return array<string,mixed>
	 */
	public static function business_rules() {
		return array(
			'support_response_business_days' => 2,
			'refund_window_days'             => 7,
			'refund_requires_no_access'      => true,
			'complimentary_extension_days'   => 30,
			'assessment_unlimited_attempts'  => true,
			'assessment_readiness_benchmark' => 80,
			'assessment_benchmark_is_gate'   => false,
			'assessment_scores_after_submit' => true,
			'assessment_fixed_order'         => true,
			'catalog_status'                 => self::CATALOG_STATUS,
			'publicly_purchasable'           => false,
			'publicly_indexed'               => false,
		);
	}

	/**
	 * Workbook titles matching shared-core themes (learner-facing module titles).
	 *
	 * @return array<int,string> 1–9
	 */
	public static function workbook_titles() {
		return array(
			1 => 'Informed Consent, Minors, and Family Involvement',
			2 => 'Telehealth and Technology',
			3 => 'Professional Competence',
			4 => 'Professional Impairment',
			5 => 'Client Welfare and Harm Prevention',
			6 => 'Professional Boundaries, Multiple Relationships, and Exploitation',
			7 => 'Cultural Humility and Bias',
			8 => 'Confidentiality and Information Sharing',
			9 => 'Clinical Documentation and Record Management',
		);
	}

	/**
	 * Long-form HTML description for course.row description.
	 *
	 * @return string
	 */
	public static function program_description_html() {
		$parts = array();
		foreach ( self::long_description_paragraphs() as $p ) {
			$parts[] = '<p>' . esc_html( $p ) . '</p>';
		}

		$parts[] = '<h3>' . esc_html( 'What Is Included' ) . '</h3><ul>';
		foreach ( self::what_is_included() as $item ) {
			$parts[] = '<li>' . esc_html( $item ) . '</li>';
		}
		$parts[] = '</ul>';

		$parts[] = '<h3>' . esc_html( 'Who This Program Is For' ) . '</h3><ul>';
		foreach ( self::who_this_is_for() as $item ) {
			$parts[] = '<li>' . esc_html( $item ) . '</li>';
		}
		$parts[] = '</ul>';
		$parts[] = '<p><em>' . esc_html( self::pathway_boundary_notice() ) . '</em></p>';

		$html = implode( "\n", $parts );
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : $html;
	}

	/**
	 * Syllabus meta payload for sales + LMS pages.
	 *
	 * @return array<string,mixed>
	 */
	public static function syllabus_meta() {
		$panel = self::purchase_panel();
		$card  = self::dashboard_card();
		$rules = self::business_rules();

		return array(
			'course_code'               => self::PROGRAM_CODE_INTERNAL, // admin/internal meta only; templates must not echo publicly.
			'hide_course_code_public'   => true,
			'public_title'              => self::PUBLIC_TITLE,
			'page_label'                => self::PAGE_LABEL,
			'url_slug'                  => self::URL_SLUG,
			'short_description'         => self::short_description(),
			'hero_headline'             => self::hero_headline(),
			'hero_subheadline'          => self::hero_subheadline(),
			'hero_support_line'         => self::hero_support_line(),
			'what_is_included'          => self::what_is_included(),
			'who_this_is_for'           => self::who_this_is_for(),
			'pathway_boundary_notice'   => self::pathway_boundary_notice(),
			'faqs'                      => self::faqs(),
			'disclaimers'               => array_values( self::disclaimers() ),
			'disclaimer_map'            => self::disclaimers(),
			'checkout_description'      => self::checkout_description(),
			'checkout_acknowledgments'  => self::checkout_acknowledgments(),
			'enrollment_confirmation'   => self::enrollment_confirmation_template(),
			'dashboard_card'            => $card,
			'lms_trigger_messages'      => self::lms_trigger_messages(),
			'assessment_instructions'   => self::assessment_instructions(),
			'learning_sequence'         => self::learning_sequence(),
			'unit_total'                => self::UNIT_TOTAL,
			'program_close'             => self::program_close_paragraphs(),
			'final_study_check'         => self::final_study_check(),
			'purchase_panel'            => $panel,
			'catalog_description'       => self::catalog_description(),
			'catalog_status'            => self::CATALOG_STATUS,
			'pricing_offer_level'       => 'California Law & Ethics Exam Preparation',
			'pricing_profession'        => 'LMFT / AMFT',
			'course_classification'     => 'Exam Preparation Only — No CE Credit',
			'instructional_method'      => 'Self-paced and asynchronous',
			'target_audience'           => 'AMFTs and other eligible California LMFT Law and Ethics Examination candidates',
			'seo_title'                 => self::SEO_TITLE,
			'meta_description'          => self::meta_description(),
			'image_alt'                 => 'Clinical Training and Supervision Academy LMFT California Law and Ethics Exam Preparation',
			'primary_cta'               => $panel['primary_button'],
			'secondary_cta'             => $panel['secondary_button'],
			'page_badge'                => 'Exam Preparation Only — No CE Credit',
			'educational_notice'        => implode( ' ', array_values( self::disclaimers() ) ),
			'launch_status'             => 'draft_pending_testing',
			'launch_pending_testing'    => true,
			'development_draft'         => true,
			'open_access_exam_prep'     => true,
			'content_pending'           => false,
			'scaffold_only'             => false,
			'copy_package_version'      => '1.1',
			'copy_internal_status'      => 'INTERNAL FINAL COPY — NOT YET AUTHORIZED FOR PUBLIC USE',
			'publicly_purchasable'      => false,
			'publicly_indexed'          => false,
			'business_rules'            => $rules,
			'readiness_benchmark'       => 80,
			'readiness_benchmark_gate'  => false,
		);
	}

	/**
	 * Whether a course object/slug is this LMFT Law & Ethics program.
	 *
	 * @param object|array|string|null $course Course row, meta, or slug.
	 * @return bool
	 */
	public static function is_this_program( $course ) {
		if ( is_string( $course ) ) {
			$slug = $course;
		} elseif ( is_object( $course ) ) {
			$slug = (string) ( $course->slug ?? '' );
		} elseif ( is_array( $course ) ) {
			$slug = (string) ( $course['slug'] ?? '' );
		} else {
			return false;
		}

		return in_array(
			$slug,
			array( self::COURSE_SLUG, self::URL_SLUG ),
			true
		);
	}

	/**
	 * Fill enrollment/expiration placeholders in a template string.
	 *
	 * @param string $template Template with [ENROLLMENT DATE] / [EXPIRATION DATE] / [SUPPORT CONTACT / FORM].
	 * @param string $enrollment_date Formatted enrollment date.
	 * @param string $expiration_date Formatted expiration date.
	 * @param string $support_contact Support URL or email (implementation field).
	 * @return string
	 */
	public static function fill_placeholders( $template, $enrollment_date = '', $expiration_date = '', $support_contact = '' ) {
		$support = '' !== $support_contact
			? $support_contact
			: (string) get_option( 'cta_support_email', get_option( 'admin_email', '[SUPPORT CONTACT / FORM]' ) );

		return str_replace(
			array( '[ENROLLMENT DATE]', '[EXPIRATION DATE]', '[SUPPORT CONTACT / FORM]', '[DATE]', '[X]' ),
			array(
				$enrollment_date ? $enrollment_date : '[ENROLLMENT DATE]',
				$expiration_date ? $expiration_date : '[EXPIRATION DATE]',
				$support ? $support : '[SUPPORT CONTACT / FORM]',
				$expiration_date ? $expiration_date : '[DATE]',
				'[X]',
			),
			(string) $template
		);
	}
}

}
