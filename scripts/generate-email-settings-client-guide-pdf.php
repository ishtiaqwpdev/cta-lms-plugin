<?php
/**
 * Generate comprehensive Email Settings client guide PDF.
 * CLI: php scripts/generate-email-settings-client-guide-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$date = date( 'Y-m-d' );
$out  = dirname( __DIR__ ) . '/docs/CTA_LMS_Email_Settings_Client_Guide_' . $date . '.pdf';

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
code { font-size: 8.5pt; background: #eee; padding: 1pt 3pt; }
</style></head><body>

<h1>Email Settings — Complete Client Guide</h1>
<p class="meta"><strong>Clinical Training and Supervision Academy</strong> | CTA LMS → Email Settings | DATE_PLACEHOLDER | Plugin v1.0.190</p>

<h2>1. What Is This Page?</h2>
<p><strong>CTA LMS → Email Settings</strong> controls every automated email the LMS sends to students and Associates. You can set the sender name and support address, edit subject lines and email body content, turn individual emails on or off, and preview how each message looks before saving.</p>

<div class="box"><strong>In plain language:</strong> When someone registers, enrolls in a course, books supervision, receives a certificate, or has a payment issue — the system sends an email. This page is where you customize those messages so they match CTA's voice and branding.</div>

<p>Page description: <em>"Control the automated messages CTA LMS sends. Saved content overrides the built-in email templates; untouched emails continue using their original defaults."</em></p>

<h2>2. General Email Settings (Top Section)</h2>
<p>These two fields apply to <strong>all</strong> automated emails:</p>
<table>
<tr><th>Field</th><th>Current example</th><th>Purpose</th></tr>
<tr><td><strong>Program Administrator Display Name</strong></td><td>Candice Fuimaono, MS, LMFT</td><td>Appears as the sender name in every automated email</td></tr>
<tr><td><strong>Support Email (From / Reply-To)</strong></td><td>support@clinicaltrainingacademy.com</td><td>Emails are sent from this address; student replies go here</td></tr>
</table>

<div class="box-warn"><strong>Important:</strong> Use a real, monitored inbox for the support email. Students reply to payment, certificate, and supervision emails at this address.</div>

<h2>3. Automated Email Templates — How the Tabs Work</h2>
<p>Below General Settings, eight email templates appear as tabs. Click a tab to edit that email. Each template panel includes:</p>
<ul>
<li><strong>Description</strong> — when the email is sent</li>
<li><strong>Enabled toggle</strong> — turn this email on or off</li>
<li><strong>Subject Line</strong> — editable text field</li>
<li><strong>Available placeholders</strong> — dynamic tags (keep braces exactly as shown)</li>
<li><strong>Email Body</strong> — WordPress visual editor (bold, links, etc.)</li>
<li><strong>Preview Email</strong> — see sample output with test data</li>
</ul>
<p>When finished editing any or all templates, click <strong>Save Email Settings</strong> at the bottom of the page.</p>

<div class="page-break"></div>

<h2>4. All Eight Email Templates — When They Send</h2>

<h3>1. Welcome Email</h3>
<p><strong>Sent when:</strong> A new student or Associate account is created (registration).</p>
<p><strong>Default subject:</strong> Welcome to Clinical Training and Supervision Academy</p>
<p><strong>Placeholders:</strong> <code>{student_name}</code> <code>{student_email}</code> <code>{role_label}</code> <code>{dashboard_url}</code> <code>{faq_url}</code> + common</p>
<p><strong>Typical content:</strong> Greeting, account type, email, link to dashboard, FAQ link.</p>

<h3>2. Enrollment Confirmation</h3>
<p><strong>Sent when:</strong> A student is enrolled in a CE or Exam Prep course (after purchase or admin enrollment).</p>
<p><strong>Default subject:</strong> You're Enrolled — {course_name}</p>
<p><strong>Placeholders:</strong> <code>{course_name}</code> <code>{ce_hours}</code> <code>{payment_reference}</code> <code>{enrolled_date}</code> <code>{course_player_url}</code></p>
<p><strong>Typical content:</strong> Course name, CE hours, payment ref, "Start Learning Now" button.</p>

<h3>3. Booking Confirmation</h3>
<p><strong>Sent when:</strong> An approved Associate books a supervision session.</p>
<p><strong>Default subject:</strong> Supervision Session Confirmed — {session_date}</p>
<p><strong>Placeholders:</strong> <code>{session_type}</code> <code>{session_date}</code> <code>{session_time}</code> <code>{duration}</code> <code>{seats_booked}</code> <code>{seats_total}</code> <code>{dashboard_url}</code></p>
<p><strong>Typical content:</strong> Session type (Group/Individual), date, time, duration, seat count, link to supervision dashboard.</p>

<h3>4. Session Reminder</h3>
<p><strong>Sent when:</strong> Daily automated task — for sessions happening <strong>tomorrow</strong>.</p>
<p><strong>Default subject:</strong> Reminder: Your Supervision Session is Tomorrow</p>
<p><strong>Placeholders:</strong> <code>{session_date}</code> <code>{session_time}</code> <code>{session_type}</code> <code>{duration}</code> <code>{cancellation_deadline}</code> <code>{dashboard_url}</code></p>
<p><strong>Typical content:</strong> Tomorrow's session details + cancellation deadline notice.</p>

<h3>5. Certificate Ready</h3>
<p><strong>Sent when:</strong> A CE certificate is generated (after modules, exam, evaluation, and attestation complete).</p>
<p><strong>Default subject:</strong> Your CE Certificate is Ready — {course_name}</p>
<p><strong>Placeholders:</strong> <code>{course_name}</code> <code>{ce_hours}</code> <code>{certificate_number}</code> <code>{completion_date}</code> <code>{certificate_url}</code> <code>{dashboard_url}</code></p>
<p><strong>Typical content:</strong> Congratulations, certificate number, download link.</p>

<h3>6. Payment Receipt</h3>
<p><strong>Sent when:</strong> Successful payment for a course, bundle, or supervision subscription.</p>
<p><strong>Default subject:</strong> Payment Received — Thank You</p>
<p><strong>Placeholders:</strong> <code>{product_name}</code> <code>{amount}</code> <code>{payment_date}</code> <code>{transaction_id}</code> <code>{dashboard_url}</code> <code>{support_email}</code></p>
<p><strong>Typical content:</strong> Item purchased, amount, transaction ID, access activated message.</p>

<h3>7. Payment Failed</h3>
<p><strong>Sent when:</strong> Stripe reports a failed subscription payment (supervision plans).</p>
<p><strong>Default subject:</strong> Action Required: Payment Failed</p>
<p><strong>Placeholders:</strong> <code>{subscription_plan}</code> <code>{billing_portal_url}</code> <code>{support_email}</code></p>
<p><strong>Typical content:</strong> Warning that supervision access is suspended; link to update payment method.</p>

<h3>8. Supervision Access Locked</h3>
<p><strong>Sent when:</strong> Supervision subscription is cancelled or payment cannot be processed — access paused.</p>
<p><strong>Default subject:</strong> Your Supervision Access Has Been Paused</p>
<p><strong>Placeholders:</strong> <code>{supervision_url}</code> <code>{support_email}</code></p>
<p><strong>Typical content:</strong> Cannot book new sessions; history preserved; reactivate link.</p>

<div class="page-break"></div>

<h2>5. Common Placeholders (Every Email)</h2>
<p>These appear on all templates in addition to email-specific tags:</p>
<table>
<tr><th>Placeholder</th><th>Replaced with</th></tr>
<tr><td><code>{program_admin_name}</code></td><td>Program Administrator Display Name (from General Settings)</td></tr>
<tr><td><code>{support_email}</code></td><td>Support email address (from General Settings)</td></tr>
<tr><td><code>{student_name}</code></td><td>Recipient's display name (most emails)</td></tr>
</table>

<div class="box-warn"><strong>Rule:</strong> Keep placeholder text inside braces <strong>exactly</strong> as shown — e.g. <code>{student_name}</code> not <code>{Student Name}</code>. The system replaces them automatically when sending.</div>

<h2>6. How to Edit an Email (Step by Step)</h2>
<ol>
<li>Go to <strong>CTA LMS → Email Settings</strong></li>
<li>Confirm <strong>General Email Settings</strong> (sender name + support email) are correct</li>
<li>Click the tab for the email you want to edit (e.g. Welcome Email)</li>
<li>Ensure <strong>Enabled</strong> toggle is ON if you want that email to send</li>
<li>Edit the <strong>Subject Line</strong></li>
<li>Edit the <strong>Email Body</strong> in the visual editor — use placeholders where dynamic data is needed</li>
<li>Click <strong>Preview Email</strong> to see how it looks with sample data</li>
<li>Repeat for other tabs as needed</li>
<li>Click <strong>Save Email Settings</strong> at the bottom</li>
<li>Success message: "Email settings saved successfully."</li>
</ol>

<h2>7. Enabled vs Disabled Emails</h2>
<p>Each template has an <strong>Enabled</strong> toggle (ON/OFF switch):</p>
<ul>
<li><strong>Enabled (ON):</strong> System sends this email when the trigger event occurs</li>
<li><strong>Disabled (OFF):</strong> That email is never sent — use only if you handle the communication manually elsewhere</li>
</ul>
<p>Disabling Welcome Email, for example, means new registrants receive no automated welcome message.</p>

<h2>8. Defaults vs Custom Content</h2>
<p>The plugin ships with professional default subject and body for each email. If you leave content unchanged and save, the built-in defaults continue to apply. Once you edit and save custom text, your version overrides the default. To revert to defaults, clear the custom subject/body fields back to the original default text and save.</p>

<h2>9. Email Triggers — Full Workflow Map</h2>
<div class="flow">
<strong>Registration</strong> → Welcome Email<br>
<strong>Course purchase / enrollment</strong> → Enrollment Confirmation + Payment Receipt<br>
<strong>Supervision plan purchase</strong> → Payment Receipt<br>
<strong>Associate books session</strong> → Booking Confirmation<br>
<strong>Day before session</strong> → Session Reminder (automatic daily cron)<br>
<strong>CE completion (cert issued)</strong> → Certificate Ready<br>
<strong>Stripe payment fails</strong> → Payment Failed<br>
<strong>Subscription cancelled</strong> → Supervision Access Locked<br>
<strong>Admin cancels session</strong> → Plain text email (not editable here — sent from Bookings)
</div>

<div class="page-break"></div>

<h2>10. Relationship to Other Admin Screens</h2>
<table>
<tr><th>Screen</th><th>Connection</th></tr>
<tr><td><strong>Bookings</strong></td><td>Booking Confirmation + Session Reminder emails; admin cancel uses separate system email</td></tr>
<tr><td><strong>Course Evaluation</strong></td><td>Certificate Ready sends after evaluation + attestation complete</td></tr>
<tr><td><strong>Users / Approvals</strong></td><td>Welcome on registration; Payment Failed / Supervision Locked on subscription issues</td></tr>
<tr><td><strong>Courses</strong></td><td>Enrollment Confirmation uses course name, CE hours, player URL</td></tr>
<tr><td><strong>Settings</strong></td><td>Dashboard URLs, FAQ link, Stripe billing portal used in email placeholders</td></tr>
</table>

<h2>11. Common Questions &amp; Tasks</h2>
<table>
<tr><th>Question / Task</th><th>Answer / Action</th></tr>
<tr><td>Student didn't get welcome email</td><td>Check Welcome Email tab → Enabled ON. Check spam folder. Verify support email is valid.</td></tr>
<tr><td>Change who emails appear from</td><td>Edit Program Administrator Display Name + Support Email in General Settings.</td></tr>
<tr><td>Add Candice's signature to all emails</td><td>Edit each template body OR add closing line with {program_admin_name} placeholder.</td></tr>
<tr><td>Turn off session reminders</td><td>Session Reminder tab → disable Enabled toggle → Save.</td></tr>
<tr><td>Test before going live</td><td>Use Preview Email button on each tab — shows sample Alex Morgan data.</td></tr>
<tr><td>Certificate email not sending</td><td>Certificate Ready must be Enabled; student must complete full CE chain (exam + eval + attestation).</td></tr>
<tr><td>Payment failed email — when?</td><td>Only for Stripe subscription failures (supervision plans), not one-time CE purchases.</td></tr>
<tr><td>Can I add images?</td><td>Visual editor supports basic HTML; logo/branding is in the email template wrapper (developer setting).</td></tr>
</table>

<h2>12. Best Practices for the Client</h2>
<ul>
<li>Review all eight templates once after launch — confirm tone, links, and placeholders.</li>
<li>Keep support@clinicaltrainingacademy.com monitored — it receives replies.</li>
<li>Preview each email after major edits before relying on it in production.</li>
<li>Do not remove required placeholders (e.g. {dashboard_url}, {certificate_url}) — links will break.</li>
<li>Session Reminder and Payment Failed are critical for supervision — keep enabled unless you have a manual process.</li>
<li>Save after editing multiple tabs — one Save button updates everything on the page.</li>
</ul>

<h2>13. Summary for the Client</h2>
<div class="box">
<p><strong>Email Settings</strong> is your control center for all automated CTA LMS messages. Set the sender name and support email at the top, then use the eight tabs to customize Welcome, Enrollment, Booking, Reminder, Certificate, Payment Receipt, Payment Failed, and Supervision Locked emails. Toggle each on or off, edit subject and body with placeholders, preview with sample data, and save.</p>
<p><strong>Your setup routine:</strong> (1) Confirm General Settings match Candice / support inbox. (2) Skim all eight templates. (3) Preview key emails (Welcome, Enrollment, Certificate). (4) Save. (5) Revisit when branding or policy wording changes.</p>
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
