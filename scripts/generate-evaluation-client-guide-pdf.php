<?php
/**
 * Generate comprehensive Course Evaluation client guide PDF.
 * CLI: php scripts/generate-evaluation-client-guide-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$date = date( 'Y-m-d' );
$out  = dirname( __DIR__ ) . '/docs/CTA_LMS_Course_Evaluation_Client_Guide_' . $date . '.pdf';

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<style>
@page { margin: 40pt 48pt 48pt 48pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.4; color: #1a1a1a; }
h1 { font-size: 19pt; color: #1e3a5f; border-bottom: 2pt solid #c9a227; padding-bottom: 7pt; margin: 0 0 4pt; }
h2 { font-size: 13pt; color: #1e3a5f; margin: 15pt 0 6pt; page-break-after: avoid; }
h3 { font-size: 11pt; color: #333; margin: 10pt 0 5pt; }
p { margin: 0 0 6pt; } ul, ol { margin: 0 0 8pt 16pt; } li { margin-bottom: 3pt; }
table { width: 100%; border-collapse: collapse; margin: 7pt 0 11pt; font-size: 9pt; }
th { background: #1e3a5f; color: #fff; padding: 5pt 7pt; text-align: left; }
td { border: 0.5pt solid #ccc; padding: 4pt 7pt; vertical-align: top; }
.meta { font-size: 8.5pt; color: #555; margin-bottom: 12pt; }
.box { background: #eef4fb; border-left: 3pt solid #1e3a5f; padding: 9pt 11pt; margin: 9pt 0; }
.box-warn { background: #fff8e6; border-left: 3pt solid #c9a227; padding: 9pt 11pt; margin: 9pt 0; }
.example { background: #f9f9f9; border: 0.5pt solid #ddd; padding: 8pt; margin: 8pt 0; font-size: 9pt; }
.flow { font-size: 9pt; background: #f5f5f5; padding: 8pt; border: 0.5pt solid #ddd; margin: 8pt 0; line-height: 1.5; }
.page-break { page-break-before: always; }
</style></head><body>

<h1>Course Evaluation — Complete Client Guide</h1>
<p class="meta"><strong>Clinical Training and Supervision Academy</strong> | CTA LMS → Course Evaluation | DATE_PLACEHOLDER | Plugin v1.0.190</p>

<h2>1. What Is This Page?</h2>
<p><strong>CTA LMS → Course Evaluation</strong> is your admin hub for <strong>CE course feedback</strong>. After a learner finishes all modules and passes the final examination, they must complete a CAMFT-style evaluation form before receiving their CE certificate. This page lets you:</p>
<ul>
<li><strong>Review</strong> every evaluation a student has submitted</li>
<li><strong>Filter and export</strong> submissions for compliance records (CAMFT / BBS reporting)</li>
<li><strong>Manage</strong> the shared CAMFT question template library used when building new CE courses</li>
</ul>

<div class="box"><strong>Important:</strong> Course Evaluation applies to <strong>CE courses only</strong>. Exam Preparation programs do <strong>not</strong> use evaluations or CE certificates. Supervision bookings are also unrelated.</div>

<h2>2. Page Layout — Two Tabs</h2>
<table>
<tr><th>Tab</th><th>Purpose</th><th>When to use</th></tr>
<tr><td><strong>Submissions</strong> (default)</td><td>List of student evaluation forms already submitted</td><td>Weekly review, compliance exports, reading individual feedback</td></tr>
<tr><td><strong>Question Templates (CAMFT library)</strong></td><td>Master question bank copied into new CE courses</td><td>When updating standard evaluation wording for future courses</td></tr>
</table>

<p class="description">Page subtitle: <em>"Review student evaluation submissions and manage the shared CAMFT question template library used when seeding new courses."</em></p>

<h2>3. Submissions Tab — Filter Submissions</h2>
<p>Use filters to narrow the list before reviewing or exporting:</p>
<table>
<tr><th>Filter</th><th>What it does</th></tr>
<tr><td><strong>Course</strong></td><td>Dropdown — "All CE courses" or one specific published CE course</td></tr>
<tr><td><strong>Student search</strong></td><td>Search by student name or email</td></tr>
<tr><td><strong>Date from / Date to</strong></td><td>Limit submissions to a date range</td></tr>
<tr><td><strong>Status</strong></td><td>All, Completed, or Draft</td></tr>
</table>
<p><strong>Apply Filters</strong> — runs the search. <strong>Reset</strong> — clears filters back to all submissions.</p>

<h3>Export CSV</h3>
<p>Click <strong>Export CSV</strong> to download a spreadsheet of submissions matching your current filters (up to 10,000 rows). The file includes: course ID/title, student ID/name/email, evaluation ID, all responses (JSON), summary ratings, comments, submitted date, and status. Use this for CAMFT compliance archives or internal reporting.</p>

<div class="page-break"></div>

<h2>4. Submissions Tab — Submissions Table</h2>
<table>
<tr><th>Column</th><th>Meaning</th></tr>
<tr><td><strong>Course</strong></td><td>Full CE course title</td></tr>
<tr><td><strong>Student</strong></td><td>Learner name + email address</td></tr>
<tr><td><strong>Date</strong></td><td>When the evaluation was submitted (site timestamp)</td></tr>
<tr><td><strong>Status</strong></td><td>Usually <strong>completed</strong> when the learner finished the form</td></tr>
<tr><td><strong>Actions</strong></td><td><strong>View</strong> — opens full submission detail</td></tr>
</table>

<div class="example"><strong>Example rows from your live site:</strong><br>
• <em>Alcoholism &amp; Other Chemical Substance Dependency…</em> — Harlan Gregory (harlan@…) — 2026-08-11 04:57:29 — completed<br>
• <em>California Law &amp; Ethics for Mental Health Professionals…</em> — Candice Fuimaono (candice@…) — 2026-08-11 04:57:29 — completed<br>
Both students passed their final exam, completed the evaluation, and are on track for certificate (after attestation if required).</div>

<h2>5. Viewing a Single Submission</h2>
<p>Click <strong>View</strong> on any row to open the full evaluation detail page. You will see:</p>
<ul>
<li><strong>Course</strong> and <strong>Student</strong> (with clickable email)</li>
<li><strong>Submitted</strong> date/time</li>
<li><strong>Status</strong> (completed / draft)</li>
<li><strong>Summary ratings</strong> — Overall Rating, Content Quality, Instructor Rating, Would Recommend (Yes/No)</li>
<li><strong>Comments</strong> — free-text feedback if provided</li>
<li><strong>Responses table</strong> — every question label and the student's answer</li>
<li><strong>Raw responses JSON</strong> — technical backup of stored data</li>
</ul>
<p>Use <strong>← Back to submissions</strong> to return to the list.</p>

<h2>6. Where Do Evaluation Questions Come From?</h2>
<p>Each CE course has its <strong>own independent evaluation form</strong>. Questions are built in two places:</p>

<h3>A. Per-course (Courses → Edit CE course)</h3>
<p>On the course edit screen, scroll to the <strong>Course Evaluation</strong> panel. Two quick actions:</p>
<ul>
<li><strong>Sync Learning Objective Questions</strong> — auto-creates rating questions from the course learning objectives</li>
<li><strong>Add CAMFT / Standard Questions</strong> — copies all questions from the shared CAMFT template library into this course</li>
</ul>
<p>You can also add, edit, reorder, or delete individual questions per course.</p>

<h3>B. Shared template library (this page → Question Templates tab)</h3>
<p>The CAMFT library (course_id = 0) holds standard questions. When you click "Add CAMFT / Standard Questions" on a course, those questions are <strong>copied</strong> into that course — each course keeps its own copy so edits on one course do not affect others.</p>

<div class="box-warn"><strong>Note:</strong> Deleting a template question here does <strong>not</strong> change past student submissions. Existing courses that already copied the question are unaffected unless you edit them separately.</div>

<div class="page-break"></div>

<h2>7. Question Templates Tab — Managing the CAMFT Library</h2>
<p>Switch to <strong>Question Templates (CAMFT library)</strong> to add or edit master questions.</p>

<h3>Add / Edit Template Question form</h3>
<table>
<tr><th>Field</th><th>Purpose</th></tr>
<tr><td><strong>Section</strong></td><td>Group label shown to students (e.g. "Course Content", "Instructor")</td></tr>
<tr><td><strong>Question</strong></td><td>Full question text</td></tr>
<tr><td><strong>Type</strong></td><td>Question format (see types below)</td></tr>
<tr><td><strong>Options</strong></td><td>Choices for radio/dropdown/checkbox; optional for rating (defaults 1–5 Likert + N/A)</td></tr>
<tr><td><strong>Required</strong></td><td>Student must answer before submitting</td></tr>
<tr><td><strong>Status</strong></td><td>Active, Draft, or Inactive</td></tr>
<tr><td><strong>Summary mapping</strong></td><td>Optional — maps answer into summary columns (rating, content_quality, instructor_rating, would_recommend, comments)</td></tr>
</table>

<h3>Question types available</h3>
<table>
<tr><th>Type</th><th>Student sees</th></tr>
<tr><td>Rating Scale (1–5 + N/A)</td><td>Likert scale: Strongly Disagree → Strongly Agree, plus N/A</td></tr>
<tr><td>Radio Button</td><td>Single choice from listed options</td></tr>
<tr><td>Checkbox</td><td>Multiple selections allowed</td></tr>
<tr><td>Short Text</td><td>Single-line text field</td></tr>
<tr><td>Paragraph</td><td>Multi-line text area</td></tr>
<tr><td>Dropdown</td><td>Single choice from dropdown menu</td></tr>
<tr><td>Information (display only)</td><td>Instruction text — no answer required</td></tr>
</table>

<h3>CAMFT Template Questions table</h3>
<p>Lists all library questions with Section, Question preview, Type, Required, Status, and Edit/Delete actions. Use the <strong>Reorder</strong> field (comma-separated question IDs) and <strong>Save Order</strong> to control display order on student forms.</p>

<h2>8. Complete CE Learner Workflow (Where Evaluation Fits)</h2>
<p>Evaluation is step 3 in the required CE completion sequence:</p>
<div class="flow">
<strong>Step 1</strong> — Complete all course modules (including Capstone if present)<br>
<strong>Step 2</strong> — Pass Final Examination (70% minimum, unlimited attempts, no time limit)<br>
<strong>Step 3</strong> — Submit Course Evaluation (CAMFT-style form on learner dashboard)<br>
<strong>Step 4</strong> — Complete Attestation (signature confirming course completion)<br>
<strong>Step 5</strong> — CE Certificate issued automatically
</div>

<p>The learner cannot skip ahead: evaluation is locked until the final exam is passed. Certificate is locked until evaluation (and attestation) are done.</p>

<p><strong>Law &amp; Ethics course (CTA-CE-001):</strong> Attestation may be built into the evaluation form itself (Section 9). Other courses use a separate attestation step after evaluation.</p>

<div class="page-break"></div>

<h2>9. What You Control vs What Is Automatic</h2>
<table>
<tr><th>You (admin) control</th><th>System handles automatically</th></tr>
<tr><td>Build evaluation questions per course (Courses → Edit)</td><td>Lock evaluation until final exam passed</td></tr>
<tr><td>Maintain CAMFT template library (this page)</td><td>Validate required questions on submit</td></tr>
<tr><td>Review submissions and export CSV</td><td>Store responses with student name, email, course, timestamp</td></tr>
<tr><td>Edit question wording for new courses</td><td>Calculate summary ratings (overall, content, instructor, recommend)</td></tr>
<tr><td>Reorder questions on template or per course</td><td>Guide learner to attestation → certificate after evaluation</td></tr>
</table>

<h2>10. Relationship to Other Admin Screens</h2>
<table>
<tr><th>Screen</th><th>Connection</th></tr>
<tr><td><strong>Courses → Edit (CE)</strong></td><td>Per-course evaluation question builder; Sync Objectives + Add CAMFT buttons</td></tr>
<tr><td><strong>Dashboard</strong></td><td>Completion stats; recent enrollments — evaluation is part of completion path</td></tr>
<tr><td><strong>Users</strong></td><td>Student profile; certificate history after evaluation + attestation complete</td></tr>
<tr><td><strong>Email Settings</strong></td><td>Certificate email sent after full completion chain</td></tr>
<tr><td><strong>Bookings / Approvals</strong></td><td>Not related — supervision only</td></tr>
</table>

<h2>11. Common Questions &amp; Tasks</h2>
<table>
<tr><th>Question / Task</th><th>Answer / Action</th></tr>
<tr><td>Why no Exam Prep evaluations here?</td><td>Exam Prep has no CE certificate or CAMFT evaluation — by design.</td></tr>
<tr><td>Student says evaluation won't open</td><td>They must pass the final exam first. Check their quiz attempts on the course.</td></tr>
<tr><td>Need CAMFT report for one course</td><td>Filter by Course → set date range → Export CSV.</td></tr>
<tr><td>Update standard questions for all future courses</td><td>Edit templates on Question Templates tab. Existing courses: re-copy or edit per course.</td></tr>
<tr><td>Status shows "completed" but no certificate</td><td>Student may still need attestation (Step 4). Law &amp; Ethics may need Section 9 signature in evaluation.</td></tr>
<tr><td>Can I delete a submission?</td><td>Not from this UI — submissions are compliance records. Contact developer if correction needed.</td></tr>
<tr><td>New CE course has no evaluation questions</td><td>Courses → Edit → Course Evaluation → Add CAMFT / Standard Questions + Sync Learning Objective Questions.</td></tr>
</table>

<h2>12. Compliance &amp; Record-Keeping Tips</h2>
<ul>
<li>Export CSV monthly or quarterly and archive for CAMFT / BBS audit trails.</li>
<li>Filter by course when preparing provider reports for a specific CE offering.</li>
<li>Use <strong>View</strong> to read individual comments before responding to learner feedback.</li>
<li>Keep template library aligned with current CAMFT evaluation requirements — update templates before launching new course versions.</li>
<li>Summary ratings (Rating, Content, Instructor, Recommend) appear in CSV exports for quick aggregate analysis in Excel.</li>
</ul>

<h2>13. Summary for the Client</h2>
<div class="box">
<p><strong>Course Evaluation</strong> is where you review CE student feedback and manage the standard CAMFT question library. Learners complete this form after passing the final exam and before receiving their certificate. Use <strong>Submissions</strong> to filter, view, and export records; use <strong>Question Templates</strong> to maintain shared questions copied into each CE course.</p>
<p><strong>Your routine:</strong> (1) When launching a new CE course, ensure evaluation questions are seeded on the course edit page. (2) Periodically review submissions and export CSV for compliance. (3) Update CAMFT templates when regulatory wording changes — then copy into affected courses as needed.</p>
</div>

<p style="margin-top:12pt;font-size:8.5pt;color:#666;">Clinical Training and Supervision Academy — CTA LMS Plugin Documentation</p>

</body></html>
HTML;

$html = str_replace( 'DATE_PLACEHOLDER', htmlspecialchars( $date, ENT_QUOTES, 'UTF-8' ), $html );
$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true ) );
$dompdf->loadHtml( $html );
$dompdf->setPaper( 'A4', 'portrait' );
$dompdf->render();
file_put_contents( $out, $dompdf->output() );
echo "Wrote: {$out}\n";
echo 'Size: ' . number_format( filesize( $out ) ) . " bytes\n";
