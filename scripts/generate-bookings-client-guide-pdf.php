<?php
/**
 * Generate comprehensive Supervision Bookings client guide PDF.
 * CLI: php scripts/generate-bookings-client-guide-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$date = date( 'Y-m-d' );
$out  = dirname( __DIR__ ) . '/docs/CTA_LMS_Bookings_Client_Guide_' . $date . '.pdf';

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

<h1>Supervision Bookings — Complete Client Guide</h1>
<p class="meta"><strong>Clinical Training and Supervision Academy</strong> | CTA LMS → Bookings | DATE_PLACEHOLDER | Plugin v1.0.190</p>

<h2>1. What Is This Page?</h2>
<p><strong>CTA LMS → Bookings → Supervision Bookings</strong> is your <strong>supervision calendar control panel</strong>. Here you create time slots when Associates can attend live clinical supervision. This page is <strong>only for the Supervision program</strong> — it does not manage CE courses, Exam Prep programs, or general learner enrollments.</p>

<div class="box"><strong>In plain language:</strong> You post available supervision appointment times. Approved Associates with an active supervision subscription see those times on their dashboard and book a seat. You monitor how many seats are filled and can cancel a slot if your schedule changes.</div>

<h2>2. Who Can Book Sessions?</h2>
<p>Only <strong>Associates</strong> with <strong>Full supervision access</strong> can book. Full access requires ALL of the following (managed on the Approvals page):</p>
<ol>
<li><strong>Approval Status = Approved</strong> (you clicked Approve on their supervision application)</li>
<li><strong>Active supervision plan</strong> — purchased Group Supervision ($260/mo) or Supervision + CE All-Access ($350/mo), OR admin-assigned agency plan</li>
<li><strong>Active Stripe subscription</strong> — payment current (not past due or cancelled)</li>
</ol>
<p>Licensed Professionals who only buy CE or Exam Prep <strong>never</strong> use this Bookings page — they have no supervision sessions to schedule.</p>

<div class="box-warn"><strong>Important:</strong> An Associate waiting for approval (Supervision Application Pending) can still purchase CE courses and Exam Prep. Only supervision booking, meeting links, and BBS document uploads stay locked until approved.</div>

<h2>3. Page Layout — What You See</h2>
<h3>Header</h3>
<ul>
<li><strong>Title:</strong> Supervision Bookings</li>
<li><strong>Add New Session</strong> (blue button, top right) — only on Upcoming Sessions tab</li>
</ul>

<h3>Two Tabs</h3>
<table>
<tr><th>Tab</th><th>Purpose</th><th>When to use</th></tr>
<tr><td><strong>Upcoming Sessions</strong></td><td>Open slots you created that Associates can still book</td><td>Daily — add new slots, watch seat counts, cancel if needed</td></tr>
<tr><td><strong>Session History</strong></td><td>Past sessions and who booked them</td><td>Weekly — review attendance records</td></tr>
</table>

<h2>4. Upcoming Sessions Table — Column Guide</h2>
<table>
<tr><th>Column</th><th>What it means</th><th>Example from your site</th></tr>
<tr><td><strong>Date</strong></td><td>Day the session occurs</td><td>Aug 12, 2026</td></tr>
<tr><td><strong>Time</strong></td><td>Start time in Pacific Time (PDT/PST)</td><td>8:00 AM PDT</td></tr>
<tr><td><strong>Type</strong></td><td>Group or Individual</td><td>Group</td></tr>
<tr><td><strong>Booked / Total</strong></td><td>Seats taken ÷ total capacity</td><td>0 / 8 = open group, nobody booked yet</td></tr>
<tr><td><strong>Status</strong></td><td>Green "Open" = available for booking</td><td>Open</td></tr>
<tr><td><strong>Actions</strong></td><td>Cancel button — removes slot and notifies booked associates</td><td>Cancel</td></tr>
</table>

<div class="example"><strong>Your current upcoming slots (example):</strong><br>
• Aug 12, 2026 — 8:00 AM PDT — Group — 0/8 — Open<br>
• Aug 17, 2026 — 7:00 PM PDT — Individual — 0/1 — Open<br>
Both are ready for Associates to book. When someone books, the first number increases (e.g. 1/8).</div>

<div class="page-break"></div>

<h2>5. Session Types — Rules</h2>
<table>
<tr><th>Type</th><th>Max seats</th><th>Duration</th><th>Best for</th></tr>
<tr><td><strong>Group</strong></td><td>8 (you can set 1–8 in Add Session form)</td><td>120 minutes (2 hours)</td><td>Multiple associates in one supervision meeting</td></tr>
<tr><td><strong>Individual</strong></td><td>1 seat only</td><td>60 minutes (1 hour)</td><td>One-on-one supervision</td></tr>
</table>
<p>The system prevents Associates from booking overlapping sessions on the same day (e.g. cannot book a group and individual that conflict in time).</p>

<h2>6. How to Add a New Session (Step by Step)</h2>
<ol>
<li>Go to <strong>CTA LMS → Bookings</strong></li>
<li>Make sure <strong>Upcoming Sessions</strong> tab is active</li>
<li>Click <strong>Add New Session</strong></li>
<li>In the popup form, enter:
<ul>
<li><strong>Date</strong> — pick session date</li>
<li><strong>Time</strong> — enter in <strong>Pacific Time</strong> (site default: America/Los_Angeles)</li>
<li><strong>Type</strong> — Group (max 8 seats, 120 min) or Individual (1 seat, 60 min)</li>
<li><strong>Total Seats</strong> — for Group only (default 8; can reduce if smaller group)</li>
</ul></li>
<li>Click <strong>Create Session</strong></li>
<li>New row appears in Upcoming Sessions with Status = Open and 0/X booked</li>
</ol>

<h2>7. How to Cancel a Session</h2>
<ol>
<li>On Upcoming Sessions, find the row</li>
<li>Click <strong>Cancel</strong></li>
<li>If Associates already booked seats, the system:
<ul>
<li>Marks their bookings as cancelled</li>
<li>Sends each booked Associate an email: "Your supervision session on [date] at [time] has been cancelled"</li>
<li>Marks the open slot as cancelled</li>
</ul></li>
</ol>
<p>Use Cancel when you need to remove a slot from the calendar. Associates will need to book a different session.</p>

<h2>8. Complete End-to-End Workflow</h2>

<h3>Phase A — Before any bookings (your setup)</h3>
<ol>
<li>Associate registers and applies for supervision → shows Pending on Approvals</li>
<li>Associate purchases supervision plan OR you Assign Plan (agency-paid)</li>
<li>You Approve application on Approvals page → Access becomes Full (if plan active)</li>
<li>You create session slots on this Bookings page (Add New Session)</li>
</ol>

<h3>Phase B — Associate books (automatic on website)</h3>
<ol>
<li>Associate logs in → opens Supervision Dashboard or supervision booking page</li>
<li>Calendar shows your open sessions (Upcoming slots with available seats)</li>
<li>Associate selects a session and clicks Book</li>
<li>System confirms seat — Booked/Total updates (e.g. 0/8 → 1/8)</li>
<li><strong>Booking confirmation email</strong> sent to Associate</li>
</ol>

<h3>Phase C — Before session day (automatic)</h3>
<ol>
<li><strong>Session reminder email</strong> sent the day before (daily cron job)</li>
<li>Associate sees booked session on Supervision Dashboard</li>
<li>When session time arrives, Associate can join meeting link (if URL configured in settings)</li>
</ol>

<h3>Phase D — After session</h3>
<ol>
<li>Session moves to Session History tab after date passes</li>
<li>Dashboard → Recent Bookings shows latest reservations</li>
</ol>

<div class="flow">YOU: Create slots on Bookings page<br>
ASSOCIATE: Book seat on Supervision Dashboard<br>
SYSTEM: Confirmation email → Reminder email → Session occurs<br>
YOU: Review Session History for records</div>

<div class="page-break"></div>

<h2>9. Session History Tab</h2>
<p>Switch to <strong>Session History</strong> to see past and completed bookings. Columns:</p>
<table>
<tr><th>Column</th><th>Meaning</th></tr>
<tr><td>User</td><td>Associate who booked</td></tr>
<tr><td>Date / Time</td><td>When session was scheduled</td></tr>
<tr><td>Type</td><td>Group or Individual</td></tr>
<tr><td>Status</td><td>Confirmed, cancelled, completed, etc.</td></tr>
</table>
<p>Use this for attendance records and follow-up. Upcoming tab shows empty slots; History shows who actually booked.</p>

<h2>10. Emails Connected to Bookings</h2>
<table>
<tr><th>Email</th><th>When sent</th><th>Editable in</th></tr>
<tr><td>Booking confirmation</td><td>Associate books a seat</td><td>CTA LMS → Email Settings</td></tr>
<tr><td>Session reminder</td><td>Day before scheduled session</td><td>Email Settings (session_reminder)</td></tr>
<tr><td>Session cancelled</td><td>Admin clicks Cancel on a slot with bookings</td><td>System message (hardcoded)</td></tr>
</table>

<h2>11. What You Control vs What Is Automatic</h2>
<table>
<tr><th>You (admin) control</th><th>System handles automatically</th></tr>
<tr><td>Create session dates/times</td><td>Display open slots to Associates on frontend</td></tr>
<tr><td>Choose Group vs Individual</td><td>Seat counting (Booked / Total)</td></tr>
<tr><td>Cancel sessions when schedule changes</td><td>Prevent double-booking same session</td></tr>
<tr><td>Plan how many sessions per month</td><td>Block overlapping bookings same day</td></tr>
<tr><td>Approve Associates first (Approvals page)</td><td>Send confirmation + reminder emails</td></tr>
<tr><td>Notify Associates if you cancel</td><td>Move past sessions to History</td></tr>
</table>

<h2>12. Relationship to Other Admin Screens</h2>
<table>
<tr><th>Screen</th><th>Connection</th></tr>
<tr><td><strong>Approvals</strong></td><td>Associates must be Approved + have plan before they can book</td></tr>
<tr><td><strong>Users</strong></td><td>Supervision column shows Active/Pending; Cancel subscription here</td></tr>
<tr><td><strong>Dashboard</strong></td><td>Recent Bookings widget — quick view of latest reservations</td></tr>
<tr><td><strong>Settings</strong></td><td>Page links for supervision dashboard; Stripe; timezone (Pacific)</td></tr>
<tr><td><strong>Email Settings</strong></td><td>Customize booking and reminder email text</td></tr>
</table>

<h2>13. Common Questions &amp; Tasks</h2>
<table>
<tr><th>Question / Task</th><th>Answer / Action</th></tr>
<tr><td>Why 0/8 on all sessions?</td><td>Normal — slots are open; no Associate has booked yet. Share calendar with approved Associates.</td></tr>
<tr><td>Associate says they cannot book</td><td>Check Approvals → must be Approved + Full access. Check Users → supervision Active, not Past Due.</td></tr>
<tr><td>How to schedule a month of groups?</td><td>Add New Session repeatedly — create each date/time slot.</td></tr>
<tr><td>Session full (8/8)</td><td>Associates cannot book more; add another Group slot same week if needed.</td></tr>
<tr><td>Wrong time entered</td><td>Cancel slot → create new one with correct Pacific Time.</td></tr>
<tr><td>Is this for CE courses?</td><td>No. CE uses Courses + learner dashboard. Bookings is supervision only.</td></tr>
</table>

<h2>14. Timezone Reminder</h2>
<p>All session times are entered and displayed in <strong>Pacific Time (America/Los_Angeles)</strong> — same as CE certificates and admin timestamps. When you create "8:00 AM PDT" that is what Associates see. Do not use other time zones unless you intentionally adjust.</p>

<h2>15. Summary for the Client</h2>
<div class="box">
<p><strong>Supervision Bookings</strong> is where you publish live supervision appointment slots. Create Group (up to 8 people, 2 hours) or Individual (1 person, 1 hour) sessions. Approved Associates with active supervision plans book online. You watch Booked/Total fill up, use Session History for records, and Cancel if plans change — booked Associates are emailed automatically.</p>
<p><strong>Your weekly routine:</strong> (1) Check Approvals for new Associates to approve. (2) Add upcoming session slots for the next 2–4 weeks. (3) Glance at Booked/Total to see uptake. (4) Review Session History after sessions occur.</p>
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
