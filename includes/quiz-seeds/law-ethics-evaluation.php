<?php
/**
 * Official California Law & Ethics (CTA-CE-001) course evaluation (v1.0).
 *
 * Exact CAMFT-compliant 9-section structure from
 * CTA_California_Law_Ethics_Course_Evaluation_v1.0 — do not paraphrase.
 *
 * @package CTA_LMS
 */

/**
 * @return array[]
 */
return array(
	// -------------------------------------------------------------------------
	// 1. Course and Participant Information
	// -------------------------------------------------------------------------
	array(
		'id'       => 'course_title_display',
		'section'  => '1. Course and Participant Information',
		'label'    => 'Course Title',
		'type'     => 'info',
		'required' => false,
		'value'    => 'California Law & Ethics for Mental Health Professionals: Navigating the Evolving Clinical Landscape',
		'summary'  => '',
	),
	array(
		'id'       => 'ce_hours_format_display',
		'section'  => '1. Course and Participant Information',
		'label'    => 'CE Hours / Format',
		'type'     => 'info',
		'required' => false,
		'value'    => '6.0 CE Hours (360 minutes) / Asynchronous Distance Learning',
		'summary'  => '',
	),
	array(
		'id'       => 'presenter_display',
		'section'  => '1. Course and Participant Information',
		'label'    => 'Presenter / Author',
		'type'     => 'info',
		'required' => false,
		'value'    => 'Candice Fuimaono, MS, LMFT',
		'summary'  => '',
	),
	array(
		'id'       => 'participant_cert_name',
		'section'  => '1. Course and Participant Information',
		'label'    => 'Participant Name',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'participant_email',
		'section'  => '1. Course and Participant Information',
		'label'    => 'Email Address',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'participant_completion_date',
		'section'  => '1. Course and Participant Information',
		'label'    => 'Completion Date',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'participant_license_type',
		'section'  => '1. Course and Participant Information',
		'label'    => 'License / Registration Type',
		'type'     => 'dropdown',
		'required' => true,
		'options'  => array(
			'LMFT'                  => 'LMFT',
			'LCSW'                  => 'LCSW',
			'LPCC'                  => 'LPCC',
			'LEP'                   => 'LEP',
			'Registered Associate'  => 'Registered Associate',
			'Other'                 => 'Other',
		),
		'summary'  => '',
	),
	array(
		'id'       => 'participant_license_number',
		'section'  => '1. Course and Participant Information',
		'label'    => 'License / Registration Number (optional)',
		'type'     => 'short_text',
		'required' => false,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// 2. Rating Scale (display legend)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'rating_scale_legend',
		'section'  => '2. Rating Scale',
		'label'    => '1 = Strongly Disagree · 2 = Disagree · 3 = Neutral · 4 = Agree · 5 = Strongly Agree · N/A = Not Applicable',
		'type'     => 'info',
		'required' => false,
		'value'    => 'Select one response per statement using the scale above.',
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// 3. Achievement of Measurable Learning Objectives (6)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'lo_0',
		'section'  => '3. Achievement of Measurable Learning Objectives',
		'label'    => 'Identify four California legal, regulatory, or ethical sources that govern mental health practice and distinguish scope of practice from scope of competence.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
		'source_type' => 'learning_objective',
		'objective_index' => 0,
	),
	array(
		'id'       => 'lo_1',
		'section'  => '3. Achievement of Measurable Learning Objectives',
		'label'    => 'Prepare an informed-consent checklist that includes required fee, privacy, telehealth, digital-communication, and professional-boundary elements.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
		'source_type' => 'learning_objective',
		'objective_index' => 1,
	),
	array(
		'id'       => 'lo_2',
		'section'  => '3. Achievement of Measurable Learning Objectives',
		'label'    => 'Distinguish confidentiality, psychotherapist-patient privilege, and lawful disclosure and select an appropriate response to a subpoena or request for records.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
		'source_type' => 'learning_objective',
		'objective_index' => 2,
	),
	array(
		'id'       => 'lo_3',
		'section'  => '3. Achievement of Measurable Learning Objectives',
		'label'    => 'Apply California minor-consent, parental-involvement, custody, and mandated-reporting standards to a clinical case example.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
		'source_type' => 'learning_objective',
		'objective_index' => 3,
	),
	array(
		'id'       => 'lo_4',
		'section'  => '3. Achievement of Measurable Learning Objectives',
		'label'    => 'Apply California duty-to-protect, crisis-intervention, and documentation standards to a case involving suicide risk or a serious threat of violence.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
		'source_type' => 'learning_objective',
		'objective_index' => 4,
	),
	array(
		'id'       => 'lo_5',
		'section'  => '3. Achievement of Measurable Learning Objectives',
		'label'    => 'Prepare a record-management and practice-continuity plan that addresses client access, retention, security, practice closure, professional wills, and licensure or business-risk concerns.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
		'source_type' => 'learning_objective',
		'objective_index' => 5,
	),

	// -------------------------------------------------------------------------
	// 4. Course Content and Instructional Design (8)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'content_appropriateness',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The course content was appropriate to my education, experience, and licensure or registration level.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => 'rating',
	),
	array(
		'id'       => 'content_relevance',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The course content was relevant to my professional practice.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'content_presentation',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The presentation and applied-learning activities supported my learning.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'content_materials',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The instructional materials and downloadable resources were suitable and useful.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => 'content_quality',
	),
	array(
		'id'       => 'content_currency',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The information presented was current and accurate.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'content_organization',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The course was organized logically and the modules progressed in a clear sequence.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'content_knowledge_checks',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The knowledge checks and answer explanations reinforced important concepts.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'content_final_exam',
		'section'  => '4. Course Content and Instructional Design',
		'label'    => 'The final examination reflected the content and objectives taught in the course.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// 5. Presenter / Author (4)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'presenter_knowledge',
		'section'  => '5. Presenter / Author',
		'label'    => 'The presenter demonstrated knowledge of the subject matter.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => 'instructor_rating',
	),
	array(
		'id'       => 'presenter_clarity',
		'section'  => '5. Presenter / Author',
		'label'    => 'The presenter communicated the material clearly.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'presenter_examples',
		'section'  => '5. Presenter / Author',
		'label'    => 'The presenter used practical examples that supported professional application.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'presenter_responsiveness',
		'section'  => '5. Presenter / Author',
		'label'    => 'The presenter was responsive to participant questions or needs, when applicable.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// 6. Administration and Distance-Learning Technology (6)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'admin_registration',
		'section'  => '6. Administration and Distance-Learning Technology',
		'label'    => 'Course registration, access, and administration were organized and clear.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'admin_facilities',
		'section'  => '6. Administration and Distance-Learning Technology',
		'label'    => 'The location and facilities supported learning, when applicable.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'admin_tech_support',
		'section'  => '6. Administration and Distance-Learning Technology',
		'label'    => 'Technology support was adequate and timely, when needed.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'admin_tech_contribution',
		'section'  => '6. Administration and Distance-Learning Technology',
		'label'    => 'The course technology contributed positively to my learning.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'admin_navigation',
		'section'  => '6. Administration and Distance-Learning Technology',
		'label'    => 'The course platform and navigation were user-friendly.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'admin_media',
		'section'  => '6. Administration and Distance-Learning Technology',
		'label'    => 'Videos, slides, links, and downloadable materials functioned as expected.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// 7. Overall Evaluation (5)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'overall_goals',
		'section'  => '7. Overall Evaluation',
		'label'    => 'The course met its stated educational goals.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'overall_knowledge',
		'section'  => '7. Overall Evaluation',
		'label'    => 'I gained knowledge or skills that I can apply in professional practice.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'overall_legal_ethical',
		'section'  => '7. Overall Evaluation',
		'label'    => 'The course strengthened my legal and ethical decision-making.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'overall_satisfaction',
		'section'  => '7. Overall Evaluation',
		'label'    => 'Overall, I was satisfied with this continuing education course.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'overall_recommend',
		'section'  => '7. Overall Evaluation',
		'label'    => 'I would recommend this course to other mental health professionals.',
		'type'     => 'rating',
		'required' => true,
		'summary'  => 'would_recommend',
	),

	// -------------------------------------------------------------------------
	// 8. Optional Qualitative Feedback (6 open text)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'feedback_most_valuable',
		'section'  => '8. Optional Qualitative Feedback',
		'label'    => 'What was the most valuable aspect of this course?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'feedback_apply_practice',
		'section'  => '8. Optional Qualitative Feedback',
		'label'    => 'How do you plan to apply what you learned in your professional practice?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'feedback_could_improve',
		'section'  => '8. Optional Qualitative Feedback',
		'label'    => 'What content, activity, or resource could be improved?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'feedback_topics_wanted',
		'section'  => '8. Optional Qualitative Feedback',
		'label'    => 'What additional topics would you like CTA to offer in future continuing education courses?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'feedback_tech_concerns',
		'section'  => '8. Optional Qualitative Feedback',
		'label'    => 'Did you experience any technical, accessibility, or navigation concerns?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'comments',
		'section'  => '8. Optional Qualitative Feedback',
		'label'    => 'Additional comments',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => 'comments',
	),

	// -------------------------------------------------------------------------
	// 9. Required Course-Completion Attestation (same evaluation flow)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'completion_attestation_agree',
		'section'  => '9. Required Course-Completion Attestation',
		'label'    => 'I attest that I personally completed the required instructional modules and embedded learning activities for this 6.0-hour asynchronous course. I understand that the CE certificate is issued only after all course requirements, including the final examination, course evaluation, and this attestation, are completed.',
		'type'     => 'checkbox',
		'required' => true,
		'options'  => array(
			'1' => 'I confirm this attestation',
		),
		'summary'  => '',
	),
	array(
		'id'       => 'completion_attestation_signature',
		'section'  => '9. Required Course-Completion Attestation',
		'label'    => 'Participant Signature / Typed Name',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'completion_attestation_date',
		'section'  => '9. Required Course-Completion Attestation',
		'label'    => 'Date',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
);
