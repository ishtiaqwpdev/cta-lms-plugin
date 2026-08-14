<?php
/**
 * Generate CTA LMS Complete Workflow Documentation RTF.
 * Run: php scripts/generate-workflow-documentation-rtf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root = dirname( __DIR__ );
$date = date( 'Y-m-d' );
$out  = $root . '/docs/CTA_LMS_Complete_Workflow_Documentation_' . $date . '.rtf';

function rtf_escape( $text ) {
	$text = (string) $text;
	if ( function_exists( 'mb_convert_encoding' ) ) {
		$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
	}
	$text = str_replace( array( '\\', '{', '}', "\r\n", "\r", "\n" ), array( '\\\\', '\\{', '\\}', '\\par ', '\\par ', '\\par ' ), $text );
	return $text;
}

function rtf_heading( $level, $text ) {
	$sizes = array( 1 => 32, 2 => 28, 3 => 24, 4 => 22 );
	$fs    = $sizes[ $level ] ?? 20;
	return "\\pard\\sb240\\sa120\\fs{$fs}\\b " . rtf_escape( $text ) . "\\b0\\fs22\\par\n";
}

function rtf_para( $text ) {
	return "\\pard\\sa120\\fs22 " . rtf_escape( $text ) . "\\par\n";
}

function rtf_bullet( $text ) {
	return "\\pard\\fi-360\\li720\\sa60\\fs22\\bullet\\tab " . rtf_escape( $text ) . "\\par\n";
}

function rtf_table_row( $cells, $widths ) {
	$row = "\\trowd\n";
	$x   = 0;
	foreach ( $widths as $w ) {
		$x += $w;
		$row .= "\\cellx{$x}\n";
	}
	foreach ( $cells as $cell ) {
		$row .= rtf_escape( $cell ) . "\\cell\n";
	}
	return $row . "\\row\n";
}

$w = array( 2200, 1400, 1200, 1200, 1200, 3200 );

$rtf  = "{\\rtf1\\ansi\\deff0{\\fonttbl{\\f0 Calibri;}}\\f0\\fs22\n";
$rtf .= "\\pard\\qc\\sb480\\sa240\\fs40\\b CTA LMS Plugin — Complete Workflow Documentation\\b0\\fs22\\par\n";
$rtf .= "\\pard\\qc\\sa480\\fs24 Generated: " . rtf_escape( $date ) . " | Plugin Version: 1.0.190\\par\n";
$rtf .= "\\page\n";

// Section 1
$rtf .= rtf_heading( 1, '1. System Overview' );
$rtf .= rtf_para( 'The CTA Academy LMS is a custom WordPress plugin (CTA Academy LMS v1.0.190) that powers the Clinical Training and Supervision Academy learning platform at clinicaltrainingacademy.com. It requires WordPress 5.8+, PHP 7.4+, and uses custom database tables (not Custom Post Types) for courses, enrollments, assessments, certificates, payments, bundles, exam access, and supervision data.' );
$rtf .= rtf_heading( 2, '1.1 Architecture' );
$rtf .= rtf_bullet( 'Bootstrap: cta-plugin.php loads cta-lms.php; version constant CTA_VERSION; db upgrades on plugins_loaded via cta_maybe_upgrade_db().' );
$rtf .= rtf_bullet( 'Data layer: includes/class-cta-database.php creates and migrates 16 custom tables.' );
$rtf .= rtf_bullet( 'Public layer: shortcode-driven pages (catalogs, dashboards, player, quiz, supervision).' );
$rtf .= rtf_bullet( 'Admin layer: top-level CTA LMS menu (Dashboard, Courses, Users, Approvals, Bookings, Settings, Evaluation, Email, Shortcodes).' );
$rtf .= rtf_bullet( 'Commerce: includes/class-cta-stripe.php (Stripe Checkout + webhooks); optional payments bypass for staging.' );
$rtf .= rtf_heading( 2, '1.2 Custom User Roles' );
$rtf .= rtf_bullet( 'cta_licensed_professional — CE and Exam Prep learner access; certificate downloads.' );
$rtf .= rtf_bullet( 'cta_associate — Supervision program (booking, BBS document uploads) plus general learner access when approved.' );
$rtf .= rtf_bullet( 'Note: Custom capabilities (cta_access_courses, etc.) are declared on roles but enforcement uses enrollment/payment/business-logic checks; wp-admin requires manage_options.' );
$rtf .= rtf_heading( 2, '1.3 Two Product Types — Core Behavioral Difference' );
$rtf .= rtf_para( 'CE Courses (product_type = ce): Sequential module locks; final examination; CAMFT-style course evaluation (all sections); completion attestation; CE certificate with provider line and electronic signature; permanent access on individual purchase or membership-gated access.' );
$rtf .= rtf_para( 'Exam Preparation Programs (product_type = exam_prep): No CE credit, no certificate, no evaluation, no attestation. All modules and assessments open from enrollment (Access Correction Notice v1.0). Timed access (default 6 months) stored in cta_exam_access table. Workbook HTML lessons, downloadable DOCX/PDF, chapter tests, Practice Exam A/B, Comprehensive Final, flashcards, and study toolkits.' );

$rtf .= "\\page\n";

// Section 2
$rtf .= rtf_heading( 1, '2. Database Schema' );
$tables = array(
	array( 'cta_courses', 'Central product table (CE + Exam Prep): title, slug, description, ce_hours, price, category, learning_objectives, syllabus_meta (JSON), modules_count, status (draft/published), thumbnail_url, vimeo_id, video_url, product_type, access_period_months, awards_ce_hours, has_ce_certificate.' ),
	array( 'cta_course_modules', 'Course modules: course_id, title, description, video_url, duration_mins, order_index, is_locked.' ),
	array( 'cta_enrollments', 'CE learner enrollments: user_id, course_id, status, progress, modules_completed (JSON), enrolled_at, completed_at, expires_at, payment_id, access_source (purchase|membership).' ),
	array( 'cta_exam_access', 'Exam Prep timed access: user_id, course_id, purchased_at, expires_at, original_expires_at, extended_by_admin_id, extension_notes.' ),
	array( 'cta_quizzes', 'Assessments per course: course_id, title, quiz_type (final, form_a, form_b, wbN_bank, checkpoint_N, comprehensive_final, etc.), sort_order, passing_score, time_limit_mins, max_attempts, status.' ),
	array( 'cta_quiz_questions', 'Quiz items: quiz_id, question_text, options A–D, correct_option, explanation, order_index.' ),
	array( 'cta_quiz_attempts', 'Learner attempts: user_id, quiz_id, course_id, answers (JSON), score, passed, attempt_number, started_at, completed_at. Retakes allowed (no unique user+quiz constraint).' ),
	array( 'cta_evaluations', 'CE course evaluation submissions: user_id, course_id, rating fields, responses (JSON), timezone, status, course_title, student_name, student_email, submitted_at.' ),
	array( 'cta_evaluation_questions', 'Per-course or shared (course_id=0) evaluation question bank: question_key, section_label, label, question_type, options_json, is_required, summary_field, order_index, source_type.' ),
	array( 'cta_course_attestations', 'CE completion attestations: user_id, course_id, student_name (e-signature), signature_date, attestation_text, ip_address, user_agent, attested_at. UNIQUE(user_id, course_id).' ),
	array( 'cta_certificates', 'CE certificates: user_id, course_id, enrollment_id, certificate_number, issued_at, file_path, file_url.' ),
	array( 'cta_downloadable_resources', 'Course downloads: course_id, module_id, attachment_id, title, file_url, file_path, file_type, order_index, is_practice_test, unlock_after_quiz_type (CE gates only).' ),
	array( 'cta_payments', 'Stripe payment records: user_id, stripe_payment_id, amount, currency, payment_type, product_type, product_id, plan_name, plan_details, status.' ),
	array( 'cta_bundles', 'Bundles/memberships: name, slug, plan_type (bundle|annual|subscription), price, billing_cycle, included_courses (JSON course IDs), stripe_price_id, is_featured, status, sort_order.' ),
	array( 'cta_bookings', 'Supervision session bookings: user_id, session_type, session_date/time, duration_mins, seats_total/booked, status, stripe_sub_id, notes.' ),
	array( 'cta_documents', 'Associate BBS document uploads: user_id, file_name/url, doc_category, review_status, reviewed_at/by.' ),
);
$rtf .= rtf_para( 'All tables use the WordPress table prefix (typically wp_). Primary relationships: cta_courses → modules, quizzes, resources, enrollments, exam_access; users → enrollments/exam_access/attempts/evaluations/attestations/certificates/payments.' );
foreach ( $tables as $t ) {
	$rtf .= rtf_heading( 3, $t[0] );
	$rtf .= rtf_para( $t[1] );
}
$rtf .= rtf_para( 'Uninstall note: uninstall.php drops 14 tables but omits cta_exam_access and cta_downloadable_resources (known gap).' );

$rtf .= "\\page\n";

// Section 3
$rtf .= rtf_heading( 1, '3. CE Course Workflow (Full, As Currently Built)' );
$rtf .= rtf_heading( 2, '3.1 Admin Creation' );
$rtf .= rtf_para( 'Admin → CTA LMS → Courses → Add/Edit. Product type CE. Fields: title, slug, category, CE hours, price, description, learning objectives, thumbnail, preview video (Vimeo/YouTube/URL), status. Syllabus meta: course code, level, audience, instructional method, presenter, goals, completion requirements, references, attestation required. Modules panel: title, description, video, duration, is_locked checkbox. Single Final Examination quiz. Downloadable resources with optional unlock_after_quiz_type. Per-course evaluation questions (sync from CAMFT template or learning objectives).' );
$rtf .= rtf_para( 'Publish hold: All CE courses default to Draft. unpublish_all_ce_courses_pending_cepa() forces draft on upgrades. Admin publish requires explicit CEPA confirmation checkbox (CAMFT provider approval required before public CE offering).' );
$rtf .= rtf_heading( 2, '3.2 Learner Journey — Sequence' );
$rtf .= rtf_para( 'Required order (CTA_CE_Completion): Modules (including Capstone) → Final Examination (pass) → Course Evaluation → Attestation → Certificate.' );
$rtf .= rtf_bullet( 'Enrollment: Stripe checkout or payments bypass; cta_enrollments row; access_source purchase (permanent) or membership (expires with subscription).' );
$rtf .= rtf_bullet( 'Module progression: Sequential unlock — module N+1 opens after module N marked complete (cta_complete_module AJAX). Vimeo embeds for CE video modules.' );
$rtf .= rtf_bullet( 'Final exam: Available after all modules complete; default passing score 70%; unlimited attempts until pass; retakes blocked after first pass; rationales hidden for CE finals by default.' );
$rtf .= rtf_bullet( 'Evaluation: CAMFT-style multi-section form from cta_evaluation_questions; types include rating, radio, checkbox, short_text, paragraph, dropdown, info. CTA-CE-001 Law & Ethics includes inline attestation in evaluation Section 9.' );
$rtf .= rtf_heading( 3, '3.2.1 Evaluation Form Structure (CAMFT Template)' );
$rtf .= rtf_para( 'Shared template stored at course_id=0 in cta_evaluation_questions, copied per course on sync. Typical sections: Participant Information (name, email, license type/number), Course Information, Learning Objectives (one rating question per LO synced from course learning_objectives JSON), Overall Course Rating, Instructor/Presenter Rating, Recommendations, Comments, Attestation (CE-001 inline). All required fields validated server-side on cta_submit_evaluation AJAX. Responses stored as JSON in cta_evaluations.responses with summary fields mapped via summary_field column.' );
$rtf .= rtf_bullet( 'Attestation: Typed electronic signature (student_name), signature date, mandatory compliance text, IP and user agent logged in cta_course_attestations.' );
$rtf .= rtf_bullet( 'Certificate: Issued only when all gates pass. Number format CTA-{YEAR}-{6-digit random}. HTML stored in uploads; PDF via Dompdf. Issue timestamp always formatted in America/Los_Angeles (Pacific Time). Provider line: CAMFT-Approved Continuing Education Provider | CEPA Provider #003369 (configurable via cta_cepa_provider_number). Electronic signature: Candice Fuimaono image from assets/certificate-signature.png (or URL option cta_certificate_signature_image_url); signature name from cta_certificate_signature_name or cta_admin_name. Certificate refresh updates license line after account edits.' );
$rtf .= rtf_heading( 2, '3.3 Supervision Program (Associate Workflow)' );
$rtf .= rtf_para( 'Separate from CE/Exam Prep commerce. Role cta_associate assigned at registration. Requires application approval (CTA_Associate_Access), Stripe subscription payment, then access to supervision dashboard, session booking (cta_bookings table), and BBS document uploads (cta_documents). Group supervision $260/mo; Supervision + CE All-Access $350/mo hybrid plan. Pending application does not block purchased CE course access (decoupled v1.0.90+).' );

$rtf .= "\\page\n";

// Section 4
$rtf .= rtf_heading( 1, '4. Exam Preparation Program Workflow (Full, As Currently Built)' );
$rtf .= rtf_heading( 2, '4.1 Configuration Fields' );
$rtf .= rtf_para( 'Product type exam_prep. Fields: formal/internal title, public display name (syllabus_meta.public_title), price, access_period_months (default 6), category Exam Preparation, description, learning objectives, status. Classification: Exam Preparation Only — No CE Credit. Admin publish/unpublish controls visibility directly (v1.0.190 simplified workflow — no launch confirmation dialog).' );
$rtf .= rtf_heading( 2, '4.2 No-Locks Access Model' );
$rtf .= rtf_para( 'From enrollment with active cta_exam_access: all modules immediately accessible; all assessments open; all downloads open (unlock_after_quiz_type ignored for exam prep per Access Correction v1.0). No evaluation, attestation, or certificate. Quiz rationales revealed after submit. Unlimited retakes after pass.' );
$rtf .= rtf_heading( 2, '4.3 Workbooks & Player' );
$rtf .= rtf_para( 'Workbook HTML at assets/course-materials/{program}/lessons/wb{NN}.html plus start-here.html. Player template dashboard-ce-player.php. Previous/Next Workbook navigation labels on Exam Prep player. No video embeds in exam prep player. Printable DOCX workbooks available as downloads.' );
$rtf .= rtf_heading( 2, '4.4 Assessment Structure (Varies by Program)' );
$assess = array(
	array( 'LMFT California Law & Ethics', '$199', 'License module + 9 workbooks (45 chapters), chapter tests, Practice A/B 50q each, Comprehensive Final 100q, flashcards, 6 toolkits', 'california-law-ethics-exam-preparation' ),
	array( 'LCSW California Law & Ethics (CTA-EP-002)', '$199', 'License module + 9 workbooks (45 chapters), workbook banks, Practice A/B 50q, Comprehensive Final 100q, flashcards, toolkits', 'lcsw-california-law-ethics-exam-preparation' ),
	array( 'LPCC California Law & Ethics (CTA-EP-003)', '$199', 'License module + 9 workbooks (45 chapters), 45 chapter tests, Practice A/B 50q, Comprehensive Final 100q, 807-card flashcard system, 6 toolkits', 'lpcc-california-law-ethics-exam-preparation' ),
	array( 'LMFT AMFTRB National', '$329', '12 workbooks, 12×17q banks, 3 checkpoints, Form A/B 180q / 240min, 12 audio reviews, flashcards, quick refs', 'lmft-amftrb-national-exam-preparation' ),
	array( 'LMFT California Clinical', '$249', '12 workbooks, practice banks, Form A/B 150q each (California clinical format), flashcards, toolkits', 'lmft-california-clinical-exam-preparation' ),
	array( 'LCSW ASWB Clinical', '$249', '12 workbooks, practice banks, Form A/B 122q each (2026 ASWB format), flashcards, toolkits', 'lcsw-aswb-clinical-exam-preparation' ),
	array( 'LPCC NCMHCE', '$249', '12 workbooks, 12 practice bank pairs, 3 checkpoints, Form A/B 143q simulations, flashcards, quick refs', 'lpcc-ncmhce-exam-preparation' ),
);
$rtf .= rtf_table_row( array( 'Program', 'Price', 'Assessments / Content', 'Slug' ), $w );
foreach ( $assess as $a ) {
	$rtf .= rtf_table_row( $a, $w );
}
$rtf .= rtf_heading( 2, '4.5 Answer Keys / Rationales' );
$rtf .= rtf_para( 'Online quizzes show rationales after attempt for Exam Prep. Downloadable answer keys and detailed rationales exist as protected resources. Legacy preserved-attempt gating (mark printable attempt) remains in class-cta-course-materials.php for some rationale downloads on programs that still use unlock_after_quiz_type in DB, but runtime exam prep download access is open when enrolled (Access Correction v1.0).' );
$rtf .= rtf_heading( 2, '4.7 Study Toolkits and Downloads' );
$rtf .= rtf_para( 'Downloadable resources registered in cta_downloadable_resources and served via admin-post.php?action=cta_serve_resource (gated endpoint, not direct URLs). Includes printable workbooks (DOCX), answer keys, detailed rationales, remediation workbooks, performance analysis sheets, quick-reference sheets, study schedules, and printable flashcard HTML. Sync classes in includes/class-cta-*-sync.php seed resource rows per program.' );
$rtf .= rtf_heading( 2, '4.8 Flashcard Deck Slug Map (Code-Defined)' );
$deck_map = array(
	array( 'lpcc-ncmhce-exam-preparation', 'lpcc-ncmhce/study-tools/flashcards.json' ),
	array( 'lpcc-california-law-ethics-exam-preparation', 'lpcc-law-ethics/study-tools/flashcards.json' ),
	array( 'lcsw-california-law-ethics-exam-preparation', 'lcsw-law-ethics/study-tools/flashcards.json' ),
	array( 'lcsw-aswb-clinical-exam-preparation', 'lcsw-aswb/study-tools/flashcards.json' ),
	array( 'lmft-california-clinical-exam-preparation', 'lmft-clinical/study-tools/flashcards.json' ),
	array( 'lmft-amftrb-national-exam-preparation', 'lmft-amftrb/study-tools/flashcards.json' ),
	array( 'california-law-ethics-exam-preparation', 'NOT in deck map — printable flashcards only' ),
);
$rtf .= rtf_table_row( array( 'Course Slug', 'JSON Path' ), array( 4000, 5200 ) );
foreach ( $deck_map as $d ) {
	$rtf .= rtf_table_row( $d, array( 4000, 5200 ) );
}

$rtf .= "\\page\n";

// Section 5
$rtf .= rtf_heading( 1, '5. Every Existing Product — Full Inventory' );
$rtf .= rtf_para( 'Note: Numeric database IDs are assigned at runtime and vary by environment. Publish status is admin-controlled in wp_cta_courses.status. Code defaults all products to Draft on create/sync. Live publish status must be verified in Admin → Courses. Product codes below are from codebase definitions.' );
$rtf .= rtf_heading( 2, '5.1 CE Courses' );
$ce = array(
	array( 'California Law & Ethics for Mental Health Professionals', 'CTA-CE-001', '$79', '6.0 hrs', 'Draft (CEPA hold)', 'Full syllabus sync: 6 modules + capstone, Vimeo videos, 25-question final exam seeded, evaluation + attestation' ),
	array( 'Clinical and Ethical Excellence in Telehealth (California Framework)', 'CTA-CE-002', '$45', '3.0 hrs', 'Draft (CEPA hold)', 'Full syllabus sync, final exam, evaluation' ),
	array( 'Advanced Suicide Risk Assessment', '—', '$79', '6.0 hrs', 'Draft (CEPA hold)', 'Syllabus sync content' ),
	array( 'Alcoholism & Other Chemical Substance Dependency', '—', '$149', '15.0 hrs', 'Draft (CEPA hold)', '15 modules; development_draft flag — instructional content marked incomplete' ),
	array( 'Child Abuse Assessment & Mandated Reporting', '—', '$89', '7.0 hrs', 'Draft (CEPA hold)', 'Syllabus sync content' ),
	array( 'HIV/AIDS and Mental Health', '—', '$89', '7.0 hrs', 'Draft (CEPA hold)', 'Syllabus sync content' ),
	array( 'Human Sexuality & Clinical Practice', '—', '$99', '10.0 hrs', 'Draft (CEPA hold)', 'Syllabus sync content' ),
	array( 'Fundamentals of Clinical Supervision', '—', '$169', '15.0 hrs', 'Draft (CEPA hold)', 'In commercial catalog; NOT in syllabus sync data — shell/stub only' ),
);
$w5 = array( 3600, 1200, 800, 800, 1400, 3200 );
$rtf .= rtf_table_row( array( 'Title', 'Code', 'Price', 'CE Hrs', 'Default Status', 'Content Today' ), $w5 );
foreach ( $ce as $row ) {
	$rtf .= rtf_table_row( $row, $w5 );
}
$rtf .= rtf_heading( 2, '5.2 Exam Preparation Programs' );
$ep = array(
	array( 'LMFT California Law & Ethics Exam Preparation', '—', '$199', '6 mo', 'Admin-controlled', 'Full content sync; first Law & Ethics EP program' ),
	array( 'LCSW California Law & Ethics Exam Preparation', 'CTA-EP-002', '$199', '6 mo', 'Admin-controlled', 'Stage 5D content loaded; 9 workbooks, assessments, flashcards' ),
	array( 'LPCC California Law & Ethics Exam Preparation', 'CTA-EP-003', '$199', '6 mo', 'Admin-controlled', 'Full content sync; 807-card flashcard deck' ),
	array( 'LMFT AMFTRB National Exam Preparation', '—', '$329', '6 mo', 'Admin-controlled', '12 workbooks, audio tracks, Form A/B 180q' ),
	array( 'LMFT California Clinical Exam Preparation', '—', '$249', '6 mo', 'Admin-controlled', 'Form A/B 150q; no recorded audio/video at launch per sync notes' ),
	array( 'LCSW ASWB Clinical Exam Preparation', '—', '$249', '6 mo', 'Admin-controlled', 'Form A/B 122q; legacy slug lcsw-california-clinical-exam-preparation' ),
	array( 'LPCC NCMHCE Exam Preparation', '—', '$249', '6 mo', 'Admin-controlled', 'Form A/B 143q; optional audio gated by audio_public_advertising_approved()' ),
);
$rtf .= rtf_table_row( array( 'Title', 'Code', 'Price', 'Access', 'Status', 'Content Today' ), $w5 );
foreach ( $ep as $row ) {
	$rtf .= rtf_table_row( $row, $w5 );
}

$rtf .= "\\page\n";

// Section 6
$rtf .= rtf_heading( 1, '6. Bundles, Memberships, and Pricing' );
$rtf .= rtf_para( 'Source: CTA_Bundle_Catalog (Master Pricing Catalog v3.5). Bundles resolve included_courses by 1-based index into get_ce_catalog(). Indices 9–27 are reserved future CE courses — several bundles reference courses not yet defined, so only currently-defined CE courses attach at purchase time.' );
$bundles = array(
	array( 'First Renewal Bundle', '$139', '5,6', 'Child Abuse + HIV/AIDS' ),
	array( 'Clinical Foundations Bundle', '$179', '1,2,3', 'Law & Ethics + Telehealth + Suicide Risk' ),
	array( 'Behavioral Health Specialty Bundle', '$299', '4,6,7', 'Alcoholism + HIV/AIDS + Human Sexuality' ),
	array( 'California Renewal Essentials Bundle', '$279', '1,2,3,5,6 + future 23-25', 'Six live courses now; three future placeholders' ),
	array( 'First Renewal Compliance Bundle', '$349', '1-6', 'All CE except Supervision & Sexuality' ),
	array( 'Risk Management & Clinical Protection Bundle', '$299', '1,2,3,5,8 + future 24,25,27', 'Five live + three future' ),
	array( 'Neurodivergent Child Therapist Pathway', '$399', '9-18,2,15', 'Only Telehealth (#2) resolves today' ),
	array( 'Child & Adolescent Treatment Specialist Pathway', '$399', '14-18,5,2,9,10,12', 'Telehealth + Child Abuse only' ),
	array( 'Clinical Supervision Leadership Pathway', '$449', '8,19-22,1,3,2', 'Supervision + Law & Ethics + Suicide + Telehealth' ),
	array( 'Addiction & Recovery Specialist Pathway', '$399', '4,3,6,7', 'All four resolve' ),
	array( 'Modern Clinical Practice Pathway', '$399', '23-27,6,7,3,2', 'Four live CE courses' ),
	array( 'Clinical Excellence Annual All-Access Pass', '$299/yr', 'All async CE', 'Excludes live supervision and Exam Prep' ),
	array( 'Supervision + CE All-Access Program', '$350/mo', 'All CE + supervision', 'From CTA_Supervision_Plans hybrid plan' ),
	array( 'Group Supervision', '$260/mo', 'Supervision only', 'CTA_Supervision_Plans group plan' ),
);
$wb = array( 3600, 1000, 1600, 3800 );
$rtf .= rtf_table_row( array( 'Name', 'Price', 'Course #s', 'Resolvable Today' ), $wb );
foreach ( $bundles as $b ) {
	$rtf .= rtf_table_row( $b, $wb );
}
$rtf .= rtf_para( 'Mismatch flags: Specialty pathways reference course numbers 9–27 not in get_ce_catalog() — purchasers get only currently-published matching courses. Obsolete bundle SKUs deactivated (Clinical Focus, Crisis & Risk, First Renewal Starter, Annual All-Access legacy).' );

$rtf .= "\\page\n";

// Section 7
$rtf .= rtf_heading( 1, '7. Website Structure' );
$rtf .= rtf_heading( 2, '7.1 Public Pages (WordPress pages linked via options)' );
$pages = array(
	array( 'CE Courses', '/ce-courses/', '[cta_course_catalog]', 'CE course grid catalog' ),
	array( 'Exam Preparation', '/exam-preparation/', '[cta_exam_prep_catalog]', 'Exam Prep program grid (published only)' ),
	array( 'Single Course / Program', 'linked page', '[cta_single_course]', '?course_id=X product detail + checkout' ),
	array( 'Memberships', '/memberships-page/', '[cta_membership_pricing]', 'Bundles and membership cards' ),
	array( 'Clinical Supervision', '/supervision-booking/', '[cta_supervision_booking]', 'Supervision marketing + booking' ),
	array( 'Login / Register', 'auto-linked', '[cta_login_form]', 'Authentication' ),
	array( 'About, FAQ, Contact, Policies', 'theme pages', 'theme content', 'Linked via cta_about_page_id, cta_faq_page_id, etc.' ),
);
$rtf .= rtf_table_row( array( 'Page', 'Slug', 'Shortcode', 'Purpose' ), array( 2000, 1600, 2200, 3600 ) );
foreach ( $pages as $p ) {
	$rtf .= rtf_table_row( $p, array( 2000, 1600, 2200, 3600 ) );
}
$rtf .= rtf_heading( 2, '7.2 Internal App Pages (excluded from public nav)' );
$rtf .= rtf_bullet( '[cta_student_dashboard] — CE learner portal' );
$rtf .= rtf_bullet( '[cta_supervision_dashboard] — Associate supervision portal' );
$rtf .= rtf_bullet( '[cta_course_player] — Module/workbook player' );
$rtf .= rtf_bullet( '[cta_quiz] — Quiz, evaluation, attestation' );
$rtf .= rtf_heading( 2, '7.3 All Registered Shortcodes' );
$shortcodes = array( 'cta_header', 'cta_footer', 'cta_auth_button', 'cta_login_form', 'cta_course_catalog', 'cta_exam_prep_catalog', 'cta_single_course', 'cta_supervision_booking', 'cta_membership_pricing', 'cta_student_dashboard', 'cta_supervision_dashboard', 'cta_course_player', 'cta_quiz' );
foreach ( $shortcodes as $sc ) {
	$rtf .= rtf_bullet( '[' . $sc . ']' );
}
$rtf .= rtf_heading( 2, '7.4 Navigation' );
$rtf .= rtf_para( 'Hello Elementor theme menu location menu-1 (primary). CTA_Pages::sync_primary_nav_menu() auto-adds: CE Courses, Supervision, Memberships, About, FAQ, Contact, Policies, Login. Logged-in users see Login rewritten to My Dashboard. Exam Preparation page exists but is not in default auto-menu (reachable via catalog links). Mobile uses same WordPress menu (theme responsive). Legacy URL redirects: /courses → /ce-courses; ?product_type=exam_prep on CE catalog → /exam-preparation.' );

$rtf .= "\\page\n";

// Section 8
$rtf .= rtf_heading( 1, '8. Admin Workflow' );
$rtf .= rtf_heading( 2, '8.1 CE Course Admin' );
$rtf .= rtf_para( 'Create course → set product_type CE → add modules with lock flags → seed/build Final Examination quiz → configure evaluation questions (sync CAMFT template) → attach downloadable resources with optional unlock gates → set price/CE hours/category → publish only after CEPA confirmation checkbox.' );
$rtf .= rtf_heading( 2, '8.2 Exam Prep Admin' );
$rtf .= rtf_para( 'Programs seeded by sync classes (Lcsw/Lpcc Law Ethics, AMFTRB, LMFT Clinical, LCSW ASWB, LPCC NCMHCE). Admin can edit title, public display name, price, access months, description, status. Content sync via plugin upgrades or manual Sync actions — sync does NOT override admin publish/draft on existing rows (v1.0.190). Publish clears launch_pending_testing meta. Bulk action: Publish All Exam Prep. Exam access extension panel for manual month extensions per learner.' );
$rtf .= rtf_heading( 2, '8.3 Other Admin Areas' );
$rtf .= rtf_bullet( 'Users — learner accounts, roles, license info' );
$rtf .= rtf_bullet( 'Approvals — associate supervision applications' );
$rtf .= rtf_bullet( 'Bookings — supervision session calendar' );
$rtf .= rtf_bullet( 'Settings — Stripe, page links, CAMFT provider #, certificate signature, timezone' );
$rtf .= rtf_bullet( 'Email Settings — per-type templates' );
$rtf .= rtf_heading( 2, '8.4 Settings Screen Options (admin/views/settings.php)' );
$settings = array(
	'Stripe mode, secret key, publishable key, webhook secret, billing portal configuration ID',
	'Payments bypass toggle (default yes — staging only)',
	'Page assignments: login, CE catalog, exam prep catalog, single course, supervision booking, memberships, student dashboard, supervision dashboard, course player, quiz',
	'CAMFT/CEPA provider number (default #003369)',
	'Certificate signature name, signature image URL, header/footer text',
	'Admin display name, support email, timezone',
);
foreach ( $settings as $s ) {
	$rtf .= rtf_bullet( $s );
}
$rtf .= rtf_heading( 2, '8.5 Key Source Files by Feature' );
$files = array(
	array( 'CE access / membership', 'includes/class-cta-ce-access.php' ),
	array( 'Exam access / timed window', 'includes/class-cta-exam-access.php' ),
	array( 'Completion gates', 'includes/class-cta-ce-completion.php' ),
	array( 'Certificates', 'public/class-cta-certificates.php' ),
	array( 'Stripe checkout/webhooks', 'includes/class-cta-stripe.php' ),
	array( 'Course catalog pricing', 'includes/class-cta-course-catalog.php' ),
	array( 'Bundle catalog v3.5', 'includes/class-cta-bundle-catalog.php' ),
	array( 'Student player/dashboard', 'public/class-cta-student-dashboard.php' ),
	array( 'Quiz/evaluation/attestation UI', 'public/class-cta-quiz.php' ),
	array( 'Downloadable materials gate', 'includes/class-cta-course-materials.php' ),
	array( 'Flashcards', 'includes/class-cta-flashcards.php' ),
	array( 'Workbook HTML loader', 'includes/class-cta-exam-prep-lessons.php' ),
);
$rtf .= rtf_table_row( array( 'Feature', 'Primary File' ), array( 3600, 5600 ) );
foreach ( $files as $f ) {
	$rtf .= rtf_table_row( $f, array( 3600, 5600 ) );
}

$rtf .= "\\page\n";

// Section 9
$rtf .= rtf_heading( 1, '9. Integrations' );
$rtf .= rtf_heading( 2, '9.1 Stripe' );
$rtf .= rtf_para( 'Options: cta_stripe_mode (test|live), secret/publishable keys, webhook secret. Webhook: /wp-json/cta-lms/v1/stripe-webhook. Events: checkout.session.completed, customer.subscription.updated/deleted, invoice.paid, invoice.payment_failed. Checkout AJAX: cta_create_checkout (courses/exam prep), cta_create_subscription (supervision), bundle checkout sessions. Success URL finalizes enrollment via finalize_checkout_session(). cta_payments_bypass defaults to yes — must disable on production for real payments.' );
$rtf .= rtf_heading( 2, '9.2 Email' );
$rtf .= rtf_para( 'Class CTA_Emails. Configurable types: welcome, enrollment_confirmation, booking_confirmation, session_reminder (daily cron), certificate_ready, payment_receipt, payment_failed, supervision_locked. From: cta_admin_name + cta_support_email (default support@clinicaltrainingacademy.com). Hardcoded: agency_representative_approval emails with PDF attachments.' );
$rtf .= rtf_heading( 2, '9.3 Vimeo' );
$rtf .= rtf_para( 'CE courses only. player.vimeo.com embed via CTA_Student_Dashboard::get_vimeo_responsive_embed(). Module video_url or course vimeo_id column. Official Vimeo IDs seeded for CTA-CE-001 and CTA-CE-002. Exam Prep player returns no video markup.' );

$rtf .= "\\page\n";

// Section 10
$rtf .= rtf_heading( 1, '10. Known Issues, Limitations, and Pending Items' );
$issues = array(
	'All CE courses forced Draft pending CAMFT CEPA provider approval — cannot publish without admin CEPA confirmation.',
	'Exam Prep publish status is admin-controlled (v1.0.190). Prior launch-hold meta may remain on draft rows until admin publishes.',
	'Clinical Supervision CE course in catalog but lacks syllabus sync — stub/shell only.',
	'Alcoholism CE marked development_draft — 15 modules with incomplete instructional content flag.',
	'LMFT California Clinical had commercial_pending / $0 seed price historically; catalog restore sets $249.',
	'Several specialty bundles/pathways reference CE course numbers 9–27 not yet in catalog — partial fulfillment only.',
	'cta_payments_bypass defaults to yes — production must set to no for live Stripe.',
	'Uninstall omits cta_exam_access and cta_downloadable_resources tables.',
	'Custom role capabilities declared but not checked via current_user_can — access enforced by business logic.',
	'Legacy unlock_after_quiz_type values may remain in DB for exam prep but ignored at runtime (Access Correction v1.0).',
	'Preserved printable attempt UI still exists for some rationale download paths; primarily relevant to printable/offline workflows.',
	'LCSW Law & Ethics (CTA-EP-002): historically found Published with purchases while content incomplete — v1.0.174 forced draft; purchase records preserved (not modified by force-draft retire in 1.0.190).',
	'Flashcard JSON decks exist for 7 program slug mappings; LMFT California Law & Ethics (california-law-ethics-exam-preparation) has no entry in CTA_Flashcards deck map — flashcards via printable downloads only unless deck added.',
	'No literal Phase 2 / Course Home Dashboard code found — deferred architecture not implemented in codebase.',
	'Recorded audio/video not included at launch for several Exam Prep programs (noted in sync class descriptions).',
	'LPCC NCMHCE audio advertising gated by audio_public_advertising_approved() helper.',
);
foreach ( $issues as $issue ) {
	$rtf .= rtf_bullet( $issue );
}

$rtf .= "\\page\n";

// Section 11
$rtf .= rtf_heading( 1, '11. Change Log Summary' );
$changelog = array(
	'Early Milestone 2 — Core LMS tables, enrollments, Stripe checkout, student dashboard, module player.',
	'CE catalog v3.5 — Eight CE courses priced; bundles/memberships synced; alcoholism category migration.',
	'California Law & Ethics CE (CTA-CE-001) — Full module sync, Vimeo videos, 25-question final exam, evaluation + attestation, certificate template with CEPA provider line and electronic signature, Pacific Time issue stamps.',
	'Telehealth CE (CTA-CE-002) — Syllabus sync and final exam.',
	'Four original Exam Prep programs — LMFT Law & Ethics, LCSW ASWB Clinical, LPCC NCMHCE, LMFT California Clinical content packages and sync classes.',
	'Exam Prep catalog page and [cta_exam_prep_catalog] shortcode; dual-format workbook HTML + DOCX downloads.',
	'In-browser flashcard viewer with DOCX-derived JSON decks; UTF-8 encoding fixes.',
	'Website/navigation corrections — page auto-provisioning, legacy URL redirects, header menu sync, org name standardization.',
	'Certificate fixes — master template, provider line CAMFT-Approved CEPA wording, Fuimaono electronic signature, America/Los_Angeles timestamps.',
	'Three Law & Ethics Exam Prep programs — LCSW (CTA-EP-002), LPCC (CTA-EP-003), plus LMFT California Law & Ethics; Form A/B title unification with downloadable Comprehensive Simulation naming.',
	'LMFT AMFTRB National Exam Prep — 12-workbook program with audio tracks and 180-question simulations.',
	'Access Correction Notice v1.0 — Exam Prep open-access model (no CE-style locks); clear material unlock gates.',
	'Exam Prep launch gate and bulk publish tooling (v1.0.188–189).',
	'v1.0.190 — Simplify Exam Prep workflow: admin publish/draft control only; remove auto-draft sync overrides and force-draft hooks; heal published meta on upgrade.',
);
foreach ( $changelog as $entry ) {
	$rtf .= rtf_bullet( $entry );
}

$rtf .= rtf_para( 'End of document.' );
$rtf .= "}";

if ( ! is_dir( dirname( $out ) ) ) {
	mkdir( dirname( $out ), 0755, true );
}

file_put_contents( $out, $rtf );
echo "Wrote: {$out}\n";
echo 'Size: ' . number_format( filesize( $out ) ) . " bytes\n";
