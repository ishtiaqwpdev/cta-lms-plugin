<?php
/**
 * Official Advanced Suicide Risk Assessment (CTA-CE-003) course evaluation.
 *
 * Approved for publication per document control — reproduced from the controlling
 * syllabus learner outcomes (Chunk 1) and evaluation specification. Do not paraphrase.
 *
 * @package CTA_LMS
 */

$likert_required = array(
	'1' => '1 — Strongly Disagree',
	'2' => '2 — Disagree',
	'3' => '3 — Neutral',
	'4' => '4 — Agree',
	'5' => '5 — Strongly Agree',
);

$likert_with_na = array(
	'1'  => '1 — Strongly Disagree',
	'2'  => '2 — Disagree',
	'3'  => '3 — Neutral',
	'4'  => '4 — Agree',
	'5'  => '5 — Strongly Agree',
	'na' => 'N/A — Not Applicable',
);

/**
 * @return array[]
 */
return array(
	// -------------------------------------------------------------------------
	// Participant Information
	// -------------------------------------------------------------------------
	array(
		'id'       => 'course_title_display',
		'section'  => 'Participant Information',
		'label'    => 'Course Title',
		'type'     => 'info',
		'required' => false,
		'value'    => 'Advanced Suicide Risk Assessment: Evidence-Based Intervention and Ethical Documentation',
		'summary'  => '',
	),
	array(
		'id'       => 'ce_hours_format_display',
		'section'  => 'Participant Information',
		'label'    => 'CE Hours / Format',
		'type'     => 'info',
		'required' => false,
		'value'    => '6.0 CE Hours (360 minutes) / Asynchronous Distance Learning',
		'summary'  => '',
	),
	array(
		'id'       => 'presenter_display',
		'section'  => 'Participant Information',
		'label'    => 'Presenter / Author',
		'type'     => 'info',
		'required' => false,
		'value'    => 'Candice Fuimaono, MS, LMFT',
		'summary'  => '',
	),
	array(
		'id'       => 'participant_cert_name',
		'section'  => 'Participant Information',
		'label'    => 'Participant Name',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'participant_email',
		'section'  => 'Participant Information',
		'label'    => 'Email Address',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'participant_license_type',
		'section'  => 'Participant Information',
		'label'    => 'License/Registration Type',
		'type'     => 'dropdown',
		'required' => true,
		'options'  => array(
			'LMFT'                 => 'LMFT',
			'LCSW'                 => 'LCSW',
			'LPCC'                 => 'LPCC',
			'LEP'                  => 'LEP',
			'Registered Associate' => 'Registered Associate',
			'Other'                => 'Other',
		),
		'summary'  => '',
	),
	array(
		'id'       => 'participant_license_number',
		'section'  => 'Participant Information',
		'label'    => 'License/Registration Number',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'participant_state_jurisdiction',
		'section'  => 'Participant Information',
		'label'    => 'State/Jurisdiction',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'participant_completion_date',
		'section'  => 'Participant Information',
		'label'    => 'Course Completion Date',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// Rating Scale (display legend)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'rating_scale_legend',
		'section'  => 'Rating Scale',
		'label'    => '1 = Strongly Disagree | 2 = Disagree | 3 = Neutral | 4 = Agree | 5 = Strongly Agree | N/A = Not Applicable',
		'type'     => 'info',
		'required' => false,
		'value'    => 'Select one response per statement using the scale above. N/A is not permitted for learning objectives or Section 5 items.',
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// Section 4 — Learning Objectives (6, required 1–5, no N/A)
	// -------------------------------------------------------------------------
	array(
		'id'              => 'SRA_EVAL_OBJ01',
		'section'         => 'Section 4 — Learning Objectives',
		'label'           => 'Identify at least five warning signs, risk factors, or protective factors associated with suicide risk and distinguish chronic suicidal ideation, acute suicidal intent, and non-suicidal self-injury.',
		'type'            => 'rating',
		'required'        => true,
		'options'         => $likert_required,
		'summary'         => '',
		'source_type'     => 'learning_objective',
		'objective_index' => 0,
	),
	array(
		'id'              => 'SRA_EVAL_OBJ02',
		'section'         => 'Section 4 — Learning Objectives',
		'label'           => 'Apply the Columbia-Suicide Severity Rating Scale and the SAFE-T framework to a clinical case and classify information needed for a comprehensive suicide-risk formulation.',
		'type'            => 'rating',
		'required'        => true,
		'options'         => $likert_required,
		'summary'         => '',
		'source_type'     => 'learning_objective',
		'objective_index' => 1,
	),
	array(
		'id'              => 'SRA_EVAL_OBJ03',
		'section'         => 'Section 4 — Learning Objectives',
		'label'           => 'Prepare a collaborative six-step safety plan that includes warning signs, coping strategies, social supports, professional resources, and lethal-means safety.',
		'type'            => 'rating',
		'required'        => true,
		'options'         => $likert_required,
		'summary'         => '',
		'source_type'     => 'learning_objective',
		'objective_index' => 2,
	),
	array(
		'id'              => 'SRA_EVAL_OBJ04',
		'section'         => 'Section 4 — Learning Objectives',
		'label'           => 'Identify three clinical or legal factors relevant to a California danger-to-self determination and select an appropriate crisis-response or level-of-care action.',
		'type'            => 'rating',
		'required'        => true,
		'options'         => $likert_required,
		'summary'         => '',
		'source_type'     => 'learning_objective',
		'objective_index' => 3,
	),
	array(
		'id'              => 'SRA_EVAL_OBJ05',
		'section'         => 'Section 4 — Learning Objectives',
		'label'           => 'Write a suicide-risk documentation note that includes at least four elements supporting clinical rationale, consultation, intervention, and follow-up.',
		'type'            => 'rating',
		'required'        => true,
		'options'         => $likert_required,
		'summary'         => '',
		'source_type'     => 'learning_objective',
		'objective_index' => 4,
	),
	array(
		'id'              => 'SRA_EVAL_OBJ06',
		'section'         => 'Section 4 — Learning Objectives',
		'label'           => 'Design a postvention and clinician-support protocol that addresses continuity of care, consultation, countertransference, secondary traumatic stress, and professional wellness.',
		'type'            => 'rating',
		'required'        => true,
		'options'         => $likert_required,
		'summary'         => '',
		'source_type'     => 'learning_objective',
		'objective_index' => 5,
	),

	// -------------------------------------------------------------------------
	// Section 5 — Course Content and Learning Experience (8, required 1–5, no N/A)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'SRA_EVAL_LEVEL',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The course was appropriate to my education, experience, and licensure or registration level.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => 'rating',
	),
	array(
		'id'       => 'SRA_EVAL_RELEVANCE',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The course content was relevant to my professional practice.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_PRESENTATION',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The presentation effectively supported learning through explanation, clinical reasoning, cases, and applied examples.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_MATERIALS',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The instructional materials were suitable and useful.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => 'content_quality',
	),
	array(
		'id'       => 'SRA_EVAL_CURRENCY',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The information appeared current and accurate.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_ORGANIZATION',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The course was organized logically and progressed in a coherent sequence.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_PACING',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The pacing supported my learning.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_DOWNLOADS',
		'section'  => 'Section 5 — Course Content and Learning Experience',
		'label'    => 'The downloadable learner resources are practical and relevant to the course objectives.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// Section 6 — Instructor/Presenter (4; last item allows N/A)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'SRA_EVAL_INST_KNOW',
		'section'  => 'Section 6 — Instructor/Presenter',
		'label'    => 'The instructor demonstrated knowledge of the subject matter.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => 'instructor_rating',
	),
	array(
		'id'       => 'SRA_EVAL_INST_CLEAR',
		'section'  => 'Section 6 — Instructor/Presenter',
		'label'    => 'The instructor communicated the material clearly.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_INST_EXAMPLES',
		'section'  => 'Section 6 — Instructor/Presenter',
		'label'    => 'The instructor\'s examples and explanations supported practical application.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_required,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_INST_RESP',
		'section'  => 'Section 6 — Instructor/Presenter',
		'label'    => 'The instructor\'s responsiveness was appropriate for the asynchronous format.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_with_na,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// Section 7 — Technology and Administration (6; N/A allowed on all)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'SRA_EVAL_ADMIN',
		'section'  => 'Section 7 — Technology and Administration',
		'label'    => 'Course administration and learner instructions were clear.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_with_na,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_TECH_SUPPORT',
		'section'  => 'Section 7 — Technology and Administration',
		'label'    => 'Technology support was adequate and timely.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_with_na,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_TECH_LEARN',
		'section'  => 'Section 7 — Technology and Administration',
		'label'    => 'The course technology contributed positively to learning.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_with_na,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_TECH_USE',
		'section'  => 'Section 7 — Technology and Administration',
		'label'    => 'The LMS and course navigation were user-friendly.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_with_na,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_MEDIA',
		'section'  => 'Section 7 — Technology and Administration',
		'label'    => 'Videos, links, assessments, and downloads functioned as expected.',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_with_na,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_FACILITIES',
		'section'  => 'Section 7 — Technology and Administration',
		'label'    => 'The location and facilities were appropriate. (Select N/A for asynchronous distance learning.)',
		'type'     => 'rating',
		'required' => true,
		'options'  => $likert_with_na,
		'summary'  => '',
	),

	// -------------------------------------------------------------------------
	// Section 8 — Qualitative Feedback (optional open text)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'SRA_EVAL_STRENGTHS',
		'section'  => 'Section 8 — Qualitative Feedback',
		'label'    => 'What were the strongest or most useful aspects of this course?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_IMPROVE',
		'section'  => 'Section 8 — Qualitative Feedback',
		'label'    => 'What could be improved?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_APPLY',
		'section'  => 'Section 8 — Qualitative Feedback',
		'label'    => 'What is one concept, framework, or practice you expect to apply?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_UNCLEAR',
		'section'  => 'Section 8 — Qualitative Feedback',
		'label'    => 'Were any topics unclear, incomplete, or difficult to use?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_FUTURE',
		'section'  => 'Section 8 — Qualitative Feedback',
		'label'    => 'What future course topics would be most useful?',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_EVAL_TECH_COMMENTS',
		'section'  => 'Section 8 — Qualitative Feedback',
		'label'    => 'Please share any comments about technology, navigation, accessibility, or learner support.',
		'type'     => 'paragraph',
		'required' => false,
		'summary'  => 'comments',
	),

	// -------------------------------------------------------------------------
	// Section 9 — Mandatory Completion Attestation (inline with evaluation)
	// -------------------------------------------------------------------------
	array(
		'id'       => 'sra_attest_statement_display',
		'section'  => 'Section 9 — Mandatory Completion Attestation',
		'label'    => 'Completion Attestation',
		'type'     => 'info',
		'required' => false,
		'value'    => 'By submitting this evaluation, I attest that I personally completed all six required instructional modules in this asynchronous course and completed the final examination. I understand that the course-specific evaluation and this attestation are required before the CE certificate is issued.',
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_ATTEST_COMPLETE',
		'section'  => 'Section 9 — Mandatory Completion Attestation',
		'label'    => 'I agree to and submit the completion attestation above.',
		'type'     => 'checkbox',
		'required' => true,
		'options'  => array(
			'1' => 'I agree to and submit the completion attestation above.',
		),
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_ATTEST_SIGNATURE',
		'section'  => 'Section 9 — Mandatory Completion Attestation',
		'label'    => 'Participant Signature/Electronic Confirmation',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
	array(
		'id'       => 'SRA_ATTEST_DATE',
		'section'  => 'Section 9 — Mandatory Completion Attestation',
		'label'    => 'Date',
		'type'     => 'short_text',
		'required' => true,
		'summary'  => '',
	),
);
