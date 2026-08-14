<?php
/**
 * Generate CTA LMS Dashboard Client Guide PDF.
 * CLI: php scripts/generate-dashboard-client-guide-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root     = dirname( __DIR__ );
$autoload = $root . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	fwrite( STDERR, "Composer autoload not found. Run composer install.\n" );
	exit( 1 );
}

require_once $autoload;

if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
	fwrite( STDERR, "Dompdf not available.\n" );
	exit( 1 );
}

$date = date( 'Y-m-d' );
$out  = $root . '/docs/CTA_LMS_Dashboard_Client_Guide_' . $date . '.pdf';

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 48pt 54pt 54pt 54pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; line-height: 1.45; color: #1a1a1a; }
h1 { font-size: 20pt; color: #1e3a5f; margin: 0 0 6pt; border-bottom: 2pt solid #c9a227; padding-bottom: 8pt; }
h2 { font-size: 13pt; color: #1e3a5f; margin: 18pt 0 8pt; page-break-after: avoid; }
h3 { font-size: 11pt; color: #333; margin: 12pt 0 6pt; }
p { margin: 0 0 8pt; }
ul, ol { margin: 0 0 10pt 18pt; padding: 0; }
li { margin-bottom: 4pt; }
table { width: 100%; border-collapse: collapse; margin: 10pt 0 14pt; font-size: 9.5pt; }
th { background: #1e3a5f; color: #fff; text-align: left; padding: 6pt 8pt; }
td { border: 0.5pt solid #ccc; padding: 5pt 8pt; vertical-align: top; }
tr:nth-child(even) td { background: #f7f9fc; }
.meta { font-size: 9pt; color: #555; margin-bottom: 16pt; }
.box { background: #f0f4fa; border-left: 3pt solid #1e3a5f; padding: 10pt 12pt; margin: 12pt 0; }
.summary { font-style: italic; color: #333; border: 0.5pt solid #c9a227; padding: 10pt; margin-top: 16pt; background: #fffdf5; }
.page-break { page-break-before: always; }
</style>
</head>
<body>

<h1>CTA LMS Dashboard — Client Guide</h1>
<p class="meta"><strong>Clinical Training and Supervision Academy</strong> | Admin Overview | Generated: DATE_PLACEHOLDER | Plugin v1.0.190</p>

<h2>What Is This Screen?</h2>
<p>When you log into WordPress and open <strong>CTA LMS → Dashboard</strong>, you arrive at the administrative home screen for your Learning Management System (LMS). This is your <strong>command center</strong>: it shows how the academy is performing and gives quick access to every management area.</p>
<p>The dashboard is <strong>read-only for numbers</strong> — you monitor activity here, then use the left menu or Quick Action buttons to make changes (publish a course, approve a learner, update settings, etc.).</p>

<h2>The Six Summary Cards — What Each Number Means</h2>
<table>
<tr><th>Card</th><th>What it counts</th><th>What it tells you</th></tr>
<tr><td><strong>Published Courses</strong></td><td>All courses and Exam Prep programs with status Published</td><td>How many products are live on the website. Draft items are not included.</td></tr>
<tr><td><strong>Enrolled Users</strong></td><td>Unique learners with at least one enrollment record</td><td>How many people have signed up for at least one CE course or program.</td></tr>
<tr><td><strong>Completions</strong></td><td>CE enrollments marked completed</td><td>Learners who finished the full CE path: modules, final exam, evaluation, attestation, and certificate. Exam Prep does not count here.</td></tr>
<tr><td><strong>Total Revenue</strong></td><td>Sum of all completed payments in the LMS</td><td>Recorded payments for courses, bundles, memberships, and supervision.</td></tr>
<tr><td><strong>Active Subscribers</strong></td><td>Users with active supervision subscription status</td><td>Associates on a paid supervision or Supervision + CE plan — not the same as enrolled in a CE course only.</td></tr>
<tr><td><strong>Certificates Issued</strong></td><td>Total CE certificates ever generated</td><td>Permanent proof of CE completion. Certificates remain even if membership ends.</td></tr>
</table>
<p>These numbers update automatically as learners enroll, pay, complete courses, and receive certificates.</p>

<h2>Recent Enrollments Table</h2>
<p>Shows the latest sign-ups (most recent first): <strong>User</strong>, <strong>Course</strong>, <strong>Date</strong>, and <strong>Payment</strong> status (e.g. completed).</p>
<p><strong>Use it for:</strong> Daily check that new purchases are recording correctly. If someone paid but does not appear here, check Users or Settings → Stripe.</p>

<h2>Recent Bookings Table</h2>
<p>Shows supervision session bookings by associates (Clinical Supervision program — separate from CE/Exam Prep). Columns: User, Session type, Date/time, Status.</p>
<p>If it displays <strong>No bookings yet</strong>, no associate has booked a session recently.</p>

<h2>Quick Action Buttons</h2>
<table>
<tr><th>Button</th><th>Takes you to</th><th>Typical use</th></tr>
<tr><td>Add New Course</td><td>Course editor</td><td>Create a new CE course or Exam Prep entry (most content is pre-built; you set price, status, details).</td></tr>
<tr><td>Approvals</td><td>Pending applications</td><td>Review associate supervision applications.</td></tr>
<tr><td>View All Users</td><td>User list</td><td>See all learners, roles, enrollments, license info.</td></tr>
<tr><td>Configure Settings</td><td>Global LMS settings</td><td>Stripe, page links, certificate text, timezone, CAMFT provider number.</td></tr>
</table>

<div class="page-break"></div>

<h2>Left Menu — Full Admin Workflow</h2>

<h3>1. Dashboard</h3>
<p>Monitor stats and recent activity. No editing on this screen.</p>

<h3>2. Courses (Most important)</h3>
<ul>
<li>See all CE Courses and Exam Preparation Programs</li>
<li><strong>Published</strong> = visible on website + checkout works</li>
<li><strong>Draft</strong> = hidden from public (admin preview only)</li>
<li>Exam Prep: Publish / Unpublish with one click</li>
<li>CE courses: require CAMFT CEPA confirmation before publish (regulatory hold)</li>
<li>Edit: title, price, CE hours, modules, final exam, downloads, evaluation questions</li>
</ul>
<div class="box"><strong>Your control rule:</strong> Published = learners can see and buy. Draft = learners cannot see or buy.</div>

<h3>3. Users</h3>
<p>All registered learners. View enrollments, progress, license details. Handle support and account issues here.</p>

<h3>4. Approvals</h3>
<p>Associate supervision applications. Approve or reject before full supervision dashboard access. Pending approval does not block someone who already purchased a CE course.</p>

<h3>5. Bookings</h3>
<p>Supervision session calendar and booking records.</p>

<h3>6. Settings</h3>
<ul>
<li>Stripe — test/live mode, keys, webhook</li>
<li>Page links — CE catalog, Exam Prep catalog, login, dashboards</li>
<li>Certificates — provider line (CAMFT CEPA #003369), signature</li>
<li>Timezone — Pacific Time for certificates and timestamps</li>
<li>Payments bypass — must be OFF on live site for real payments</li>
</ul>

<h3>7. Course Evaluation</h3>
<p>Global CAMFT-style evaluation template. Questions sync to individual CE courses.</p>

<h3>8. Email Settings</h3>
<p>Automated emails: welcome, enrollment, certificate ready, payment receipt, session reminders. Edit subject and body per type.</p>

<h3>9. Shortcodes</h3>
<p>Reference list of shortcodes on website pages (catalog, login, dashboard, etc.).</p>

<div class="page-break"></div>

<h2>How the Full System Works — Two Product Types</h2>

<h3>A. CE Courses (Continuing Education)</h3>
<p><strong>Examples:</strong> California Law &amp; Ethics CE, Telehealth, Suicide Risk, HIV/AIDS, etc.</p>
<p><strong>Learner path (automatic, sequential):</strong></p>
<ol>
<li>Purchase or access via membership</li>
<li>Watch modules in order (each unlocks after the previous is completed)</li>
<li>Pass Final Examination</li>
<li>Complete Course Evaluation (CAMFT-style form)</li>
<li>Sign Completion Attestation (electronic signature)</li>
<li>Receive CE Certificate (PDF, Pacific Time date, CAMFT provider line)</li>
</ol>
<p><strong>Your admin role:</strong> Keep course Draft until CAMFT CEPA approval, then Publish with confirmation. Do not publish CE courses for public sale before provider approval.</p>

<h3>B. Exam Preparation Programs</h3>
<p><strong>Examples:</strong> LMFT/LCSW/LPCC Law &amp; Ethics Exam Prep, ASWB Clinical, NCMHCE, AMFTRB National, LMFT California Clinical.</p>
<p><strong>Learner path (open access, no CE certificate):</strong></p>
<ol>
<li>Purchase → 6 months access from enrollment</li>
<li>All workbooks and assessments open immediately — no module locks</li>
<li>Online workbook reading + downloadable/printable workbooks</li>
<li>Chapter tests, Practice Exam A/B, Comprehensive Final</li>
<li>Flashcards and study toolkits</li>
<li>No evaluation, no attestation, no CE certificate</li>
</ol>
<p><strong>Your admin role:</strong> Publish when ready for public sale; Draft to hide. Optionally extend access per learner from course edit screen.</p>

<h3>C. Memberships, Bundles &amp; Supervision</h3>
<ul>
<li>Bundles / Memberships — sold via Memberships page; grant access to multiple CE courses</li>
<li>Supervision — monthly subscription; associates book sessions and upload BBS documents</li>
<li>Active Subscribers on dashboard = supervision subscriptions, not CE-only buyers</li>
</ul>

<h2>What You Handle vs What the Plugin Handles</h2>
<table>
<tr><th>You handle (admin)</th><th>Plugin handles (automatic)</th></tr>
<tr><td>Publish / Draft courses and Exam Prep</td><td>Module locking for CE courses</td></tr>
<tr><td>Approve supervision applications</td><td>Quiz scoring and pass/fail</td></tr>
<tr><td>Stripe and email configuration</td><td>Certificate generation after CE completion</td></tr>
<tr><td>Review enrollments and revenue</td><td>Exam Prep 6-month access expiry</td></tr>
<tr><td>CAMFT publish confirmation for CE</td><td>Payment recording and enrollment</td></tr>
<tr><td>Manual exam access extension (if needed)</td><td>Evaluation and attestation storage</td></tr>
</table>

<h2>Suggested Daily / Weekly Routine</h2>
<p><strong>Daily (2–3 minutes):</strong> Check Recent Enrollments for new sales. Check Approvals if badge shows pending count.</p>
<p><strong>Weekly:</strong> Review Published Courses count. Confirm Total Revenue aligns with Stripe. Check Completions and Certificates for CE progress.</p>
<p><strong>When launching a product:</strong> Courses → Publish → verify on website → test checkout once.</p>

<h2>Important Reminders</h2>
<ol>
<li>Published = live; Draft = hidden — simple rule for Exam Prep admin work.</li>
<li>CE courses stay Draft until you publish with CEPA confirmation.</li>
<li>Exam Prep never issues CE certificates — Completions count is CE only.</li>
<li>Dashboard numbers are live from your database — not editable on this screen.</li>
<li>Check Approvals regularly — associates may be waiting for supervision access.</li>
</ol>

<p class="summary"><strong>One-sentence summary:</strong> The CTA LMS Dashboard is your real-time academy overview — published products, learner activity, revenue, supervision subscribers, and CE certificates — with links to every area where you publish courses, approve associates, manage users, and configure payments and emails.</p>

</body>
</html>
HTML;

$html = str_replace( 'DATE_PLACEHOLDER', htmlspecialchars( $date, ENT_QUOTES, 'UTF-8' ), $html );

$dompdf = new \Dompdf\Dompdf(
	array(
		'isRemoteEnabled' => false,
		'isHtml5ParserEnabled' => true,
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
