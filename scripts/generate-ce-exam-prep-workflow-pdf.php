<?php
/**
 * Generate CE vs Exam Prep complete workflow client PDF.
 * CLI: php scripts/generate-ce-exam-prep-workflow-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root     = dirname( __DIR__ );
$autoload = $root . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	fwrite( STDERR, "Composer autoload not found.\n" );
	exit( 1 );
}

require_once $autoload;

if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
	fwrite( STDERR, "Dompdf not available.\n" );
	exit( 1 );
}

$date = date( 'Y-m-d' );
$out  = $root . '/docs/CTA_LMS_CE_and_Exam_Prep_Complete_Workflow_' . $date . '.pdf';

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 44pt 50pt 50pt 50pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.42; color: #1a1a1a; }
h1 { font-size: 19pt; color: #1e3a5f; margin: 0 0 4pt; border-bottom: 2pt solid #c9a227; padding-bottom: 7pt; }
h2 { font-size: 13pt; color: #1e3a5f; margin: 16pt 0 7pt; page-break-after: avoid; }
h3 { font-size: 11pt; color: #333; margin: 11pt 0 5pt; }
p { margin: 0 0 7pt; }
ul, ol { margin: 0 0 9pt 16pt; padding: 0; }
li { margin-bottom: 3pt; }
table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; font-size: 9pt; }
th { background: #1e3a5f; color: #fff; text-align: left; padding: 5pt 7pt; }
td { border: 0.5pt solid #ccc; padding: 4pt 7pt; vertical-align: top; }
tr:nth-child(even) td { background: #f7f9fc; }
.meta { font-size: 8.5pt; color: #555; margin-bottom: 14pt; }
.box-ce { background: #eef4fb; border-left: 3pt solid #1e3a5f; padding: 9pt 11pt; margin: 10pt 0; }
.box-exam { background: #f3f0fa; border-left: 3pt solid #5a3d8a; padding: 9pt 11pt; margin: 10pt 0; }
.box-warn { background: #fff8e6; border-left: 3pt solid #c9a227; padding: 9pt 11pt; margin: 10pt 0; }
.flow { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; background: #f5f5f5; padding: 8pt; margin: 8pt 0; border: 0.5pt solid #ddd; }
.page-break { page-break-before: always; }
.compare th { background: #333; }
.step { font-weight: bold; color: #1e3a5f; }
</style>
</head>
<body>

<h1>CE Courses &amp; Exam Prep — Complete Workflow Guide</h1>
<p class="meta"><strong>Clinical Training and Supervision Academy</strong> | How Both Product Types Work | Generated: DATE_PLACEHOLDER | Plugin v1.0.190</p>

<p>This document explains the <strong>complete end-to-end process</strong> for both product types in your CTA LMS: <strong>CE Courses</strong> (Continuing Education) and <strong>Exam Preparation Programs</strong>. It covers what happens on the website, what the learner experiences, what you control as admin, and what the plugin handles automatically.</p>

<div class="box-warn"><strong>Most important difference:</strong> CE courses use a <strong>strict step-by-step path</strong> ending in a CE certificate. Exam Prep programs use <strong>open access</strong> for six months with <strong>no CE certificate</strong> and no evaluation or attestation.</div>

<h2>Quick Comparison</h2>
<table class="compare">
<tr><th>Feature</th><th>CE Course</th><th>Exam Prep Program</th></tr>
<tr><td>Product type in system</td><td>ce</td><td>exam_prep</td></tr>
<tr><td>CE credit hours</td><td>Yes (e.g. 3–15 hours)</td><td>No (0 CE hours)</td></tr>
<tr><td>CE certificate</td><td>Yes, after full completion</td><td>Never</td></tr>
<tr><td>Access duration</td><td>Permanent (purchase) or while membership active</td><td>6 months from enrollment (default)</td></tr>
<tr><td>Module locks</td><td>Yes — sequential unlock</td><td>No — all open immediately</td></tr>
<tr><td>Assessments</td><td>One Final Examination (after all modules)</td><td>Many: chapter tests, Practice A/B, Comprehensive Final</td></tr>
<tr><td>Course evaluation</td><td>Required (CAMFT-style form)</td><td>Not used</td></tr>
<tr><td>Attestation</td><td>Required (electronic signature)</td><td>Not used</td></tr>
<tr><td>Video lessons</td><td>Yes (Vimeo embeds per module)</td><td>No video — workbook HTML + downloads</td></tr>
<tr><td>Publish rule</td><td>Draft until CAMFT CEPA approval + confirmation</td><td>You control Publish / Draft directly</td></tr>
<tr><td>Where admin manages</td><td>Courses → CE Courses tab</td><td>Courses → Exam Preparation tab</td></tr>
</table>

<div class="page-break"></div>

<h2>Part A — CE Course Complete Process</h2>

<h3>A1. What Is a CE Course?</h3>
<p>A <strong>Continuing Education course</strong> awards official CE hours to licensed mental health professionals after the learner completes all required steps. Examples in your system include California Law &amp; Ethics CE, Telehealth, Suicide Risk Assessment, HIV/AIDS, Human Sexuality, Child Abuse, Alcoholism, and Clinical Supervision.</p>

<h3>A2. Admin Setup (Before Learners See It)</h3>
<ol>
<li>Course exists in <strong>CTA LMS → Courses → CE Courses</strong> with title, price, CE hours, category, modules, final exam, evaluation questions, and optional downloads.</li>
<li>Status starts as <strong>Draft</strong> (hidden from public website).</li>
<li>After <strong>CAMFT CEPA provider approval</strong>, you click <strong>Publish</strong> and confirm the CEPA warning. Only then does the course appear on the public CE catalog and accept payment.</li>
<li>You can <strong>Unpublish</strong> anytime to hide from new buyers. Existing enrolled learners keep access.</li>
</ol>

<div class="box-ce"><strong>Admin control rule:</strong> Published = visible + purchasable. Draft = hidden. CE publish always requires your explicit CEPA confirmation.</div>

<h3>A3. How a Learner Finds and Buys a CE Course</h3>
<ol>
<li>Learner visits website → <strong>CE Courses</strong> catalog page (<code>[cta_course_catalog]</code>).</li>
<li>Only <strong>Published</strong> courses appear.</li>
<li>Learner opens single course page → sees description, CE hours, price, objectives.</li>
<li>Learner logs in or registers (Licensed Professional or Associate role).</li>
<li>Learner pays via <strong>Stripe checkout</strong> (or receives access via membership/bundle).</li>
<li>System creates an <strong>enrollment</strong> record and sends enrollment confirmation email.</li>
</ol>

<h3>A4. Learner Journey — Step by Step (Automatic Sequence)</h3>
<p>The plugin enforces this order. Learners cannot skip ahead:</p>

<p class="step">Step 1 — Access Dashboard</p>
<p>After purchase, learner opens <strong>My Dashboard</strong> (<code>[cta_student_dashboard]</code>) and sees the enrolled CE course.</p>

<p class="step">Step 2 — Complete All Modules (Sequential)</p>
<ul>
<li>Module 1 is open; later modules stay locked until the previous module is marked complete.</li>
<li>Each module may include a <strong>Vimeo video</strong> lesson.</li>
<li>Learner clicks <strong>Mark as Complete</strong> or <strong>Next Module</strong> when finished.</li>
<li>Progress percentage updates on enrollment record.</li>
</ul>

<p class="step">Step 3 — Final Examination</p>
<ul>
<li>Available only after <strong>all modules</strong> are complete.</li>
<li>Default passing score: <strong>70%</strong>.</li>
<li>Unlimited attempts until pass; after pass, retakes are blocked.</li>
<li>Answer rationales are hidden for CE finals (exam security).</li>
</ul>

<p class="step">Step 4 — Course Evaluation</p>
<ul>
<li>CAMFT-style multi-section form (participant info, learning objective ratings, overall rating, comments).</li>
<li>Questions come from the course evaluation bank (synced from CAMFT template).</li>
<li>CTA-CE-001 Law &amp; Ethics includes inline attestation inside the evaluation form.</li>
</ul>

<p class="step">Step 5 — Completion Attestation</p>
<ul>
<li>Learner types full legal name as <strong>electronic signature</strong>.</li>
<li>Signs mandatory compliance statement; IP and date recorded.</li>
<li>Skipped when evaluation already includes attestation (Law &amp; Ethics CE).</li>
</ul>

<p class="step">Step 6 — CE Certificate Issued</p>
<ul>
<li>System generates certificate automatically when all gates pass.</li>
<li>Certificate number format: <strong>CTA-YEAR-######</strong>.</li>
<li>Provider line: <strong>CAMFT-Approved Continuing Education Provider | CEPA Provider #003369</strong>.</li>
<li>Issue date shown in <strong>Pacific Time (America/Los_Angeles)</strong>.</li>
<li>Electronic signature of administrator (Candice Fuimaono, MS, LMFT) on certificate.</li>
<li>Learner downloads PDF; <strong>certificate_ready</strong> email sent.</li>
<li>Enrollment status changes to <strong>completed</strong>.</li>
</ul>

<div class="flow">CE FLOW: Purchase → Modules (sequential) → Final Exam (pass) → Evaluation → Attestation → Certificate</div>

<h3>A5. Access Rules for CE</h3>
<ul>
<li><strong>Individual purchase:</strong> permanent access; enrollment never expires.</li>
<li><strong>Membership/bundle access:</strong> access lasts while subscription is active; if membership ends, course access expires but <strong>certificate remains permanent</strong> if already issued.</li>
<li><strong>Membership + individual purchase:</strong> individual purchase always wins — membership cancellation cannot revoke purchased access.</li>
</ul>

<div class="page-break"></div>

<h2>Part B — Exam Preparation Program Complete Process</h2>

<h3>B1. What Is an Exam Prep Program?</h3>
<p>An <strong>Exam Preparation Program</strong> is a self-paced study product for licensing exams. It does <strong>not</strong> award CE hours or CE certificates. Examples: LMFT/LCSW/LPCC California Law &amp; Ethics Exam Prep, LCSW ASWB Clinical, LPCC NCMHCE, LMFT AMFTRB National, LMFT California Clinical.</p>

<h3>B2. Admin Setup</h3>
<ol>
<li>Programs listed in <strong>CTA LMS → Courses → Exam Preparation</strong> tab.</li>
<li>Each has formal title, public display name, price, 6-month access period, and full content (workbooks, assessments, flashcards, toolkits) loaded by plugin sync.</li>
<li>You set status: <strong>Published</strong> (live) or <strong>Draft</strong> (hidden).</li>
<li>No CAMFT CEPA confirmation required for Exam Prep publish.</li>
<li>Optional: <strong>Publish All Exam Prep</strong> button to bulk-publish all programs.</li>
<li>You can manually extend a learner's access months from the course edit screen if needed.</li>
</ol>

<div class="box-exam"><strong>Admin control rule:</strong> Published = on website catalog + checkout works. Draft = completely hidden from public. Simple on/off — no extra approval dialogs.</div>

<h3>B3. How a Learner Finds and Buys Exam Prep</h3>
<ol>
<li>Learner visits <strong>Exam Preparation</strong> catalog page (<code>[cta_exam_prep_catalog]</code>).</li>
<li>Only <strong>Published</strong> programs appear.</li>
<li>Learner opens program page → sees description, price, 6-month access notice, "No CE Credit" classification.</li>
<li>Learner pays via Stripe checkout.</li>
<li>System creates <strong>exam access</strong> record with <strong>expires_at = purchase date + 6 months</strong>.</li>
<li>Enrollment confirmation email sent.</li>
</ol>

<h3>B4. Learner Journey — Open Access Model</h3>
<p>Unlike CE, there is <strong>no required sequence</strong> and <strong>no locks</strong>. From day one of access:</p>

<p class="step">Step 1 — Dashboard &amp; Course Player</p>
<ul>
<li>Learner opens dashboard and selects the Exam Prep program.</li>
<li>All workbook modules visible immediately — no waiting for previous module completion.</li>
<li>Workbooks display as <strong>online HTML lessons</strong> in the course player with <strong>Previous Workbook / Next Workbook</strong> navigation.</li>
<li>Printable <strong>DOCX/PDF workbooks</strong> available as downloads.</li>
<li><strong>No video</strong> in exam prep player (workbook reading only).</li>
</ul>

<p class="step">Step 2 — Assessments (All Open From Start)</p>
<p>Structure varies by program but typically includes:</p>
<table>
<tr><th>Program type</th><th>Typical assessments</th></tr>
<tr><td>Law &amp; Ethics (LMFT/LCSW/LPCC)</td><td>License module quiz, workbook/chapter tests, Practice Exam A (50q), Practice Exam B (50q), Comprehensive Final (100q)</td></tr>
<tr><td>LCSW ASWB Clinical</td><td>12 workbooks, 17-question banks, Form A &amp; Form B (122 questions each)</td></tr>
<tr><td>LPCC NCMHCE</td><td>12 workbooks, practice banks, 3 checkpoints, Form A &amp; Form B (143 questions each)</td></tr>
<tr><td>LMFT California Clinical</td><td>12 workbooks, Form A &amp; Form B (150 questions each)</td></tr>
<tr><td>LMFT AMFTRB National</td><td>12 workbooks, 17-question banks, 3 checkpoints, Form A &amp; Form B (180 questions / 240 min), 12 audio reviews</td></tr>
</table>
<ul>
<li>Learner can take any assessment at any time — no module completion required first.</li>
<li>Quiz rationales shown after submit (study mode).</li>
<li>Unlimited retakes allowed even after passing.</li>
</ul>

<p class="step">Step 3 — Study Tools</p>
<ul>
<li><strong>Interactive flashcards</strong> — flip, prev/next, shuffle (in-browser viewer).</li>
<li><strong>Printable flashcards</strong> and <strong>study toolkits</strong> as downloads.</li>
<li><strong>Answer keys and detailed rationales</strong> as protected downloads for offline study.</li>
</ul>

<p class="step">Step 4 — Access Expiry</p>
<ul>
<li>After <strong>6 months</strong>, exam access expires unless extended by admin or repurchased.</li>
<li>Repurchase extends from the later of "now" or current expiry date.</li>
<li>No certificate issued at any point.</li>
</ul>

<div class="flow">EXAM PREP FLOW: Purchase → 6 months access → All workbooks + assessments + downloads OPEN → Study at own pace → Access expires (or extend/repurchase)</div>

<h3>B5. What Exam Prep Does NOT Include</h3>
<ul>
<li>No CE hours</li>
<li>No course evaluation form</li>
<li>No completion attestation</li>
<li>No CE certificate</li>
<li>No sequential module locks</li>
<li>No membership bundle inclusion (sold separately)</li>
</ul>

<div class="page-break"></div>

<h2>Part C — Side-by-Side Admin Workflow</h2>

<table>
<tr><th>Task</th><th>CE Course</th><th>Exam Prep</th></tr>
<tr><td>Where to manage</td><td>Courses → CE Courses</td><td>Courses → Exam Preparation</td></tr>
<tr><td>Make live on website</td><td>Publish + CEPA confirm</td><td>Publish (one click)</td></tr>
<tr><td>Hide from website</td><td>Unpublish</td><td>Unpublish</td></tr>
<tr><td>Edit price/hours</td><td>CE hours + price in Edit</td><td>Price + access months in Edit</td></tr>
<tr><td>Edit content</td><td>Modules, final exam, evaluation, downloads</td><td>Assessments panel, downloads (most content pre-synced)</td></tr>
<tr><td>Monitor uptake</td><td>Enrollments column</td><td>Purchases column</td></tr>
<tr><td>Extend learner access</td><td>Not needed (permanent on purchase)</td><td>Exam access extension panel in Edit</td></tr>
<tr><td>Bulk action button</td><td>Restore Prices + Sync Syllabus</td><td>Publish All Exam Prep</td></tr>
</table>

<h2>Part D — Website Pages Learners Use</h2>
<table>
<tr><th>Page</th><th>Shortcode</th><th>Used for</th></tr>
<tr><td>CE Courses catalog</td><td>[cta_course_catalog]</td><td>Browse/buy CE courses</td></tr>
<tr><td>Exam Prep catalog</td><td>[cta_exam_prep_catalog]</td><td>Browse/buy Exam Prep programs</td></tr>
<tr><td>Single product page</td><td>[cta_single_course]</td><td>Course/program detail + checkout</td></tr>
<tr><td>Learner dashboard</td><td>[cta_student_dashboard]</td><td>My courses and progress</td></tr>
<tr><td>Course player</td><td>[cta_course_player]</td><td>Watch CE videos or read Exam Prep workbooks</td></tr>
<tr><td>Quiz / evaluation</td><td>[cta_quiz]</td><td>CE final exam, evaluation, attestation; Exam Prep assessments</td></tr>
</table>

<h2>Part E — Payment &amp; Email (Both Product Types)</h2>
<ol>
<li>Learner clicks Enroll / Buy on single course page.</li>
<li>Stripe Checkout opens (card payment) — unless payments bypass is on (staging only).</li>
<li>On success: webhook + return URL finalize enrollment/exam access.</li>
<li>Emails sent: payment receipt + enrollment confirmation.</li>
<li>CE only: certificate_ready email when certificate generated.</li>
</ol>

<h2>Part F — Your Products at a Glance</h2>
<h3>CE Courses (8 in catalog)</h3>
<ul>
<li>California Law &amp; Ethics CE (CTA-CE-001) — 6 hrs, $79</li>
<li>Telehealth CE (CTA-CE-002) — 3 hrs, $45</li>
<li>Advanced Suicide Risk Assessment — 6 hrs, $79</li>
<li>Alcoholism &amp; Chemical Dependency — 15 hrs, $149</li>
<li>Child Abuse &amp; Mandated Reporting — 7 hrs, $89</li>
<li>HIV/AIDS and Mental Health — 7 hrs, $89</li>
<li>Human Sexuality &amp; Clinical Practice — 10 hrs, $99</li>
<li>Clinical Supervision — 15 hrs, $169</li>
</ul>
<p><em>All CE courses default to Draft until you publish after CEPA approval.</em></p>

<h3>Exam Prep Programs (7 in catalog)</h3>
<ul>
<li>LMFT California Law &amp; Ethics — $199, 6 mo</li>
<li>LCSW California Law &amp; Ethics (CTA-EP-002) — $199, 6 mo</li>
<li>LPCC California Law &amp; Ethics (CTA-EP-003) — $199, 6 mo</li>
<li>LMFT AMFTRB National — $329, 6 mo</li>
<li>LMFT California Clinical — $249, 6 mo</li>
<li>LCSW ASWB Clinical — $249, 6 mo</li>
<li>LPCC NCMHCE — $249, 6 mo</li>
</ul>
<p><em>You control Published/Draft for each program independently.</em></p>

<h2>Summary — Remember These Three Rules</h2>
<ol>
<li><strong>CE = sequential path + certificate.</strong> Modules lock in order → exam → evaluation → attestation → CE certificate. Publish only after CAMFT CEPA approval.</li>
<li><strong>Exam Prep = open study + timed access.</strong> Everything open for 6 months. No certificate. You publish when ready.</li>
<li><strong>Published vs Draft</strong> is your main lever on both: Published = website + sales; Draft = hidden.</li>
</ol>

<p style="margin-top:14pt;font-style:italic;color:#444;">Clinical Training and Supervision Academy — CTA LMS Plugin Documentation</p>

</body>
</html>
HTML;

$html = str_replace( 'DATE_PLACEHOLDER', htmlspecialchars( $date, ENT_QUOTES, 'UTF-8' ), $html );

$dompdf = new \Dompdf\Dompdf(
	array(
		'isRemoteEnabled'       => false,
		'isHtml5ParserEnabled'  => true,
	)
);
$dompdf->loadHtml( $html );
$dompdf->setPaper( 'A4', 'portrait' );
$dompdf->render();

if ( ! is_dir( dirname( $out ) ) ) {
	mkdir( dirname( $out ), 0755, true );
}

file_put_contents( $out, $dompdf->output() );

echo "Wrote: {$out}\n";
echo 'Size: ' . number_format( filesize( $out ) ) . " bytes\n";
