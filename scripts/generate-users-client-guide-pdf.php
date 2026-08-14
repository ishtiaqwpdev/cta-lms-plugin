<?php
/**
 * Generate CTA Users admin client guide PDF.
 * CLI: php scripts/generate-users-client-guide-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$date = date( 'Y-m-d' );
$out  = dirname( __DIR__ ) . '/docs/CTA_LMS_Users_Client_Guide_' . $date . '.pdf';

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<style>
@page { margin: 44pt 50pt 50pt 50pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.42; color: #1a1a1a; }
h1 { font-size: 19pt; color: #1e3a5f; border-bottom: 2pt solid #c9a227; padding-bottom: 7pt; margin: 0 0 4pt; }
h2 { font-size: 13pt; color: #1e3a5f; margin: 16pt 0 7pt; }
h3 { font-size: 11pt; margin: 11pt 0 5pt; }
p { margin: 0 0 7pt; } ul, ol { margin: 0 0 9pt 16pt; } li { margin-bottom: 3pt; }
table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; font-size: 9pt; }
th { background: #1e3a5f; color: #fff; padding: 5pt 7pt; text-align: left; }
td { border: 0.5pt solid #ccc; padding: 4pt 7pt; }
.meta { font-size: 8.5pt; color: #555; margin-bottom: 14pt; }
.box { background: #eef4fb; border-left: 3pt solid #1e3a5f; padding: 9pt 11pt; margin: 10pt 0; }
.page-break { page-break-before: always; }
</style></head><body>

<h1>CTA Users — Client Guide</h1>
<p class="meta">Clinical Training and Supervision Academy | CTA LMS Admin | DATE_PLACEHOLDER | v1.0.190</p>

<h2>What Is This Screen?</h2>
<p><strong>CTA LMS → Users</strong> is your learner directory. Everyone who registers appears here. You manage license information (for CE certificates), enrollment counts, supervision subscription status, and Stripe subscription actions.</p>

<h2>Role Tabs</h2>
<table>
<tr><th>Tab</th><th>Who</th></tr>
<tr><td>All</td><td>Licensed Professionals + Associates + Administrators</td></tr>
<tr><td>Licensed Professionals</td><td>Licensed clinicians buying CE / Exam Prep</td></tr>
<tr><td>Associates</td><td>Pre-licensed associates (may use supervision program)</td></tr>
<tr><td>Administrators</td><td>WordPress admin accounts</td></tr>
</table>

<h2>Filters</h2>
<ul>
<li><strong>License info:</strong> All | Missing license number | Has license number — use Missing to find learners before certificate issue</li>
<li><strong>Supervision status:</strong> Active, Past Due, Locked, Cancelled, Application Pending, None</li>
<li><strong>Search:</strong> Name or email</li>
</ul>

<h2>Table Columns</h2>
<table>
<tr><th>Column</th><th>Meaning</th></tr>
<tr><td>Name / Email</td><td>Learner identity and login</td></tr>
<tr><td>Role</td><td>Licensed Professional, Associate, or Administrator</td></tr>
<tr><td>License Number</td><td>BBS number on CE certificates — Missing = not entered yet</td></tr>
<tr><td>License Type</td><td>LMFT, LCSW, LPCC, APCC, etc.</td></tr>
<tr><td>Joined</td><td>Registration date</td></tr>
<tr><td>Enrolled</td><td>Number of course enrollment records</td></tr>
<tr><td>Supervision</td><td>Subscription/application status for associates</td></tr>
<tr><td>Actions</td><td>Edit License, Stats, Stripe controls, WP Profile</td></tr>
</table>

<div class="page-break"></div>

<h2>Action Buttons</h2>
<table>
<tr><th>Button</th><th>Purpose</th></tr>
<tr><td>Edit License</td><td>Enter/correct license number and type — same fields as learner Account Settings; updates CE certificates</td></tr>
<tr><td>Stats</td><td>Popup: enrollments, progress, completions, certificates, exam access, payments</td></tr>
<tr><td>Sync Stripe</td><td>Refresh supervision subscription status from Stripe</td></tr>
<tr><td>Cancel at Period End</td><td>Stop renewal at end of billing month (preferred)</td></tr>
<tr><td>Cancel Now</td><td>Immediate subscription cancel and lock supervision</td></tr>
<tr><td>Reactivate</td><td>Undo scheduled cancellation before period ends</td></tr>
<tr><td>WP Profile</td><td>WordPress user profile (email, password reset)</td></tr>
</table>

<h2>Two User Types</h2>
<h3>Licensed Professional</h3>
<ul>
<li>Buys CE courses, Exam Prep, memberships</li>
<li>Uses CE Dashboard (My Dashboard)</li>
<li>License on file required for correct CE certificate</li>
</ul>
<h3>Associate</h3>
<ul>
<li>Can also buy CE and Exam Prep</li>
<li>May apply for Clinical Supervision (review on Approvals page)</li>
<li>Supervision requires: Approved application + active paid plan</li>
<li>Pending supervision does NOT block purchased CE access</li>
<li>Uses Supervision Dashboard for sessions and BBS uploads</li>
</ul>

<h2>Supervision Status Meanings</h2>
<table>
<tr><th>Status</th><th>Meaning</th></tr>
<tr><td>—</td><td>No supervision — CE/Exam Prep only</td></tr>
<tr><td>Supervision Application Pending</td><td>Review on Approvals page</td></tr>
<tr><td>Active</td><td>Paid supervision subscription live</td></tr>
<tr><td>Past Due</td><td>Payment failed</td></tr>
<tr><td>Locked / Cancelled</td><td>Access ended</td></tr>
</table>

<h2>Common Tasks</h2>
<ol>
<li>Certificate wrong license → Edit License → Save</li>
<li>Find missing licenses → Filter Missing license info</li>
<li>Support question on progress → Search user → Stats</li>
<li>Stripe status wrong → Sync Stripe</li>
<li>End supervision → Cancel at Period End</li>
<li>Pending application → Go to Approvals (not resolved here alone)</li>
</ol>

<div class="box"><strong>Summary:</strong> Users is your learner roster — license details, enrollments, supervision status, and subscription controls. Pair with Approvals for associate vetting and Courses for product management.</div>

</body></html>
HTML;

$html = str_replace( 'DATE_PLACEHOLDER', htmlspecialchars( $date, ENT_QUOTES, 'UTF-8' ), $html );
$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false ) );
$dompdf->loadHtml( $html );
$dompdf->setPaper( 'A4', 'portrait' );
$dompdf->render();
file_put_contents( $out, $dompdf->output() );
echo "Wrote: {$out}\n";
