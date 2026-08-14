<?php
/**
 * Generate Supervision Approvals client guide PDF.
 * CLI: php scripts/generate-approvals-client-guide-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

$root     = dirname( __DIR__ );
$autoload = $root . '/vendor/autoload.php';
require_once $autoload;

$date = date( 'Y-m-d' );
$out  = $root . '/docs/CTA_LMS_Supervision_Approvals_Client_Guide_' . $date . '.pdf';

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
.meta { font-size: 8.5pt; color: #555; margin-bottom: 14pt; }
.box { background: #eef4fb; border-left: 3pt solid #1e3a5f; padding: 9pt 11pt; margin: 10pt 0; }
.box-warn { background: #fff8e6; border-left: 3pt solid #c9a227; padding: 9pt 11pt; margin: 10pt 0; }
.flow { font-size: 9pt; background: #f5f5f5; padding: 8pt; border: 0.5pt solid #ddd; margin: 8pt 0; }
.page-break { page-break-before: always; }
</style>
</head>
<body>

<h1>Supervision Approvals — Client Guide</h1>
<p class="meta"><strong>Clinical Training and Supervision Academy</strong> | CTA LMS Admin | Generated: DATE_PLACEHOLDER | Plugin v1.0.190</p>

<h2>What Is This Screen?</h2>
<p><strong>CTA LMS → Approvals</strong> (Supervision Approvals) is where you review and approve <strong>Associate</strong> applications for the <strong>Clinical Supervision program</strong>. This is separate from CE courses and Exam Prep — it controls who may book supervision sessions, upload BBS documents, and use the Supervision Dashboard.</p>

<div class="box"><strong>Golden rule on this page:</strong> Approval Status = application vetting (your decision). Plan Status = whether they purchased or were assigned a supervision plan. <strong>Full supervision access requires BOTH Approved AND an active plan.</strong></div>

<h2>Filter Tabs</h2>
<table>
<tr><th>Tab</th><th>Shows</th></tr>
<tr><td>All</td><td>Every associate with a supervision application or plan on file</td></tr>
<tr><td>Supervision Application Pending</td><td>Associates waiting for your review — check this daily</td></tr>
<tr><td>Approved</td><td>Associates you have approved</td></tr>
<tr><td>Rejected</td><td>Associates whose applications were rejected or revoked</td></tr>
</table>

<h2>Table Columns</h2>
<table>
<tr><th>Column</th><th>Meaning</th></tr>
<tr><td>Associate</td><td>User name</td></tr>
<tr><td>Email</td><td>Contact email (click to mail)</td></tr>
<tr><td>Approval Status</td><td>Pending (orange) or Approved (green) or Rejected</td></tr>
<tr><td>Plan Status</td><td>What plan they have: Purchased Group Supervision, Purchased Supervision + CE All-Access, Agency-assigned plan, or No Plan</td></tr>
<tr><td>Access</td><td>Full access | Locked | Awaiting plan</td></tr>
<tr><td>Purchase / Assigned</td><td>Date of Stripe payment or admin plan assignment</td></tr>
<tr><td>Actions</td><td>View Details, Approve, Reject, Assign Plan</td></tr>
</table>

<h2>Access Column — Three States</h2>
<table>
<tr><th>Access</th><th>Meaning</th></tr>
<tr><td><strong>Locked</strong></td><td>Application pending or rejected — supervision features blocked</td></tr>
<tr><td><strong>Awaiting plan</strong></td><td>You approved them but they have no purchased or assigned plan yet</td></tr>
<tr><td><strong>Full access</strong></td><td>Approved + active plan + active Stripe subscription — can book sessions and use supervision dashboard</td></tr>
</table>

<div class="page-break"></div>

<h2>Complete Supervision Workflow</h2>

<h3>Step 1 — Associate Registers</h3>
<p>User registers on website and selects <strong>Associate</strong> role. They can immediately purchase CE courses or Exam Prep — those are NOT blocked by supervision status.</p>

<h3>Step 2 — Associate Applies for Supervision</h3>
<p>Associate completes supervision application (agency/employer details) from their dashboard. Status becomes <strong>Supervision Application Pending</strong>. Admins receive email notification. Row appears on this Approvals page with Access = <strong>Locked</strong>.</p>

<h3>Step 3 — Associate Purchases or Gets Assigned a Plan</h3>
<p>Two paths:</p>
<ul>
<li><strong>Self-purchase:</strong> Group Supervision ($260/mo) or Supervision + CE All-Access ($350/mo) via Stripe</li>
<li><strong>Agency-paid:</strong> Admin uses <strong>Assign Plan</strong> on this page (Group or Hybrid) with optional employer note</li>
</ul>
<p>Plan Status column updates to show purchased or agency-assigned plan.</p>

<h3>Step 4 — You Approve (Admin Action)</h3>
<p>Review associate details → click <strong>View Details</strong> → click blue <strong>Approve</strong> button. Approval Status becomes <strong>Approved</strong>.</p>

<h3>Step 5 — Full Access Unlocks (Automatic)</h3>
<p>If Approved AND active plan AND Stripe subscription active: Access = <strong>Full access</strong>. Associate can:</p>
<ul>
<li>Open Supervision Dashboard</li>
<li>Book group/individual sessions (Bookings page)</li>
<li>Upload BBS documents</li>
<li>Access supervision resources</li>
</ul>

<div class="flow">FLOW: Register as Associate → Apply for Supervision (Pending) → Purchase or Assign Plan → Admin Approve → Full Access</div>

<div class="box-warn"><strong>Important:</strong> Pending supervision application does NOT block CE courses or Exam Prep the associate already purchased. Supervision booking and materials stay locked until approved.</div>

<h2>Action Buttons</h2>
<table>
<tr><th>Button</th><th>When to use</th></tr>
<tr><td>View Details</td><td>See full record: registration date, plan name, payment amount, Stripe reference, agency assignment note, rejection reason</td></tr>
<tr><td>Approve</td><td>After vetting application — grants approval status (still needs active plan for full access)</td></tr>
<tr><td>Reject</td><td>Deny application — supervision stays locked; optional internal reason</td></tr>
<tr><td>Revoke / Reject</td><td>On already-approved associates — removes approval and locks supervision access</td></tr>
<tr><td>Assign Plan</td><td>Agency/employer pays — assign Group or Supervision + CE without associate paying via Stripe</td></tr>
</table>

<h2>Available Supervision Plans</h2>
<table>
<tr><th>Plan</th><th>Price</th><th>Includes</th></tr>
<tr><td>Group Supervision</td><td>$260/month</td><td>Supervision sessions only</td></tr>
<tr><td>Supervision + CE All-Access</td><td>$350/month</td><td>Supervision + access to all async CE courses (not Exam Prep)</td></tr>
</table>

<h2>What You Handle vs Automatic</h2>
<table>
<tr><th>You handle</th><th>Automatic</th></tr>
<tr><td>Approve / Reject applications</td><td>Email to admins when application submitted</td></tr>
<tr><td>Assign agency-paid plans</td><td>Stripe subscription sync via webhook</td></tr>
<tr><td>Review View Details before approving</td><td>Access calculation (Approved + plan + active sub)</td></tr>
<tr><td>Revoke if associate leaves program</td><td>Users page shows supervision status mirror</td></tr>
</table>

<h2>Common Scenarios</h2>
<ol>
<li><strong>Paid but still Locked</strong> — They purchased plan but you have not Approved yet → click Approve.</li>
<li><strong>Approved but Awaiting plan</strong> — You approved but no payment → associate must purchase or you Assign Plan.</li>
<li><strong>Agency pays for associate</strong> — Use Assign Plan with employer note → then Approve if not already.</li>
<li><strong>Associate only wants CE</strong> — They do not need Approvals — they buy CE/Exam Prep like any Licensed Professional.</li>
<li><strong>Cancel supervision</strong> — Use Users page → Cancel at Period End on Stripe subscription.</li>
</ol>

<h2>Links to Other Screens</h2>
<ul>
<li><strong>Users</strong> — Supervision column shows same status; Sync Stripe / Cancel subscription</li>
<li><strong>Bookings</strong> — Sessions booked by associates with Full access</li>
<li><strong>Dashboard</strong> — Active Subscribers count includes supervision subscriptions</li>
</ul>

<p style="margin-top:14pt;font-style:italic;">Summary: Supervision Approvals is your vetting desk for Associate supervision applications. Approve qualified associates who have (or will receive) a plan — full supervision access unlocks only when approval and active plan both exist. CE and Exam Prep access is never blocked by a pending supervision application.</p>

</body>
</html>
HTML;

$html = str_replace( 'DATE_PLACEHOLDER', htmlspecialchars( $date, ENT_QUOTES, 'UTF-8' ), $html );

$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true ) );
$dompdf->loadHtml( $html );
$dompdf->setPaper( 'A4', 'portrait' );
$dompdf->render();
file_put_contents( $out, $dompdf->output() );
echo "Wrote: {$out}\n";
