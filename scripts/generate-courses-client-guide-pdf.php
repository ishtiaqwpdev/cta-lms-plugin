<?php
/**
 * Generate CE Courses admin client guide PDF.
 * CLI: php scripts/generate-courses-client-guide-pdf.php
 *
 * @package CTA_LMS
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( "CLI only.\n" );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$date = date( 'Y-m-d' );
$out  = dirname( __DIR__ ) . '/docs/CTA_LMS_Courses_Client_Guide_' . $date . '.pdf';

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<style>
@page { margin: 44pt 50pt 50pt 50pt; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.42; color: #1a1a1a; }
h1 { font-size: 19pt; color: #1e3a5f; border-bottom: 2pt solid #c9a227; padding-bottom: 7pt; margin: 0 0 4pt; }
h2 { font-size: 13pt; color: #1e3a5f; margin: 16pt 0 7pt; }
p { margin: 0 0 7pt; } ul, ol { margin: 0 0 9pt 16pt; } li { margin-bottom: 3pt; }
table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; font-size: 9pt; }
th { background: #1e3a5f; color: #fff; padding: 5pt 7pt; text-align: left; }
td { border: 0.5pt solid #ccc; padding: 4pt 7pt; }
.meta { font-size: 8.5pt; color: #555; margin-bottom: 14pt; }
.box { background: #fff8e6; border-left: 3pt solid #c9a227; padding: 9pt 11pt; margin: 10pt 0; }
.box-ce { background: #eef4fb; border-left: 3pt solid #1e3a5f; padding: 9pt 11pt; margin: 10pt 0; }
.page-break { page-break-before: always; }
</style></head><body>

<h1>Courses Page — Client Guide</h1>
<p class="meta">Clinical Training and Supervision Academy | CTA LMS Admin | DATE_PLACEHOLDER | v1.0.190</p>

<h2>What Is This Screen?</h2>
<p><strong>CTA LMS → Courses</strong> manages all products: <strong>CE Courses</strong> and <strong>Exam Preparation Programs</strong>. You control what is live on the website, pricing, and open each product to edit content.</p>

<h2>Three Tabs</h2>
<table>
<tr><th>Tab</th><th>Content</th></tr>
<tr><td>CE Courses</td><td>Continuing Education courses (CE hours + certificate)</td></tr>
<tr><td>Exam Preparation</td><td>Exam Prep programs (6-month access, no CE certificate)</td></tr>
<tr><td>All</td><td>Both types in one list</td></tr>
</table>

<h2>Top Buttons</h2>
<table>
<tr><th>CE Courses tab</th><th>Exam Preparation tab</th></tr>
<tr><td>Add New Course</td><td>Add Exam Prep Program</td></tr>
<tr><td>Restore Prices + Sync Syllabus (admin maintenance)</td><td>Publish All Exam Prep (bulk publish)</td></tr>
</table>

<div class="box"><strong>CE warning:</strong> CE courses must stay Draft until CAMFT CEPA provider approval. Publish requires confirmation dialog.</div>
<div class="box-ce"><strong>Exam Prep info:</strong> Published = on website + checkout. Draft = hidden. No CEPA confirmation needed.</div>

<h2>Table Columns</h2>
<p><strong>CE:</strong> #, Title, CE Hours, Price, Category, Status, Enrollments, Actions</p>
<p><strong>Exam Prep:</strong> #, Title, Access (months), Price, Category, Status, Purchases, Actions</p>

<h2>Actions Per Row</h2>
<ul>
<li><strong>Edit</strong> — full course/program editor (modules, exams, downloads, price)</li>
<li><strong>Publish / Unpublish</strong> — make live or hide from website</li>
<li><strong>Delete</strong> — permanent removal (prefer Unpublish instead)</li>
</ul>

<div class="page-break"></div>

<h2>CE Course — Your Workflow</h2>
<ol>
<li>Course exists (usually pre-built by plugin)</li>
<li>Keep <strong>Draft</strong> until CAMFT CEPA approval</li>
<li>Click <strong>Publish</strong> + confirm CEPA warning</li>
<li>Learners see course on CE catalog and can purchase</li>
<li>Learner path (automatic): Modules → Final Exam → Evaluation → Attestation → Certificate</li>
</ol>

<h2>Exam Prep — Your Workflow</h2>
<ol>
<li>Program listed in Exam Preparation tab</li>
<li>Click <strong>Publish</strong> when ready for public sale (one click)</li>
<li>Or use <strong>Publish All Exam Prep</strong> for all programs at once</li>
<li>Learner gets 6 months access; all workbooks and assessments open immediately</li>
<li>No CE certificate, no evaluation, no module locks</li>
</ol>

<h2>Published vs Draft Rule</h2>
<table>
<tr><th>Status</th><th>Website</th><th>Checkout</th></tr>
<tr><td>Published</td><td>Visible on catalog</td><td>Works</td></tr>
<tr><td>Draft</td><td>Hidden</td><td>Blocked</td></tr>
</table>
<p>Existing enrolled learners keep access if you Unpublish — only new sales stop.</p>

<h2>Your 8 CE Courses (catalog)</h2>
<p>Law &amp; Ethics CE ($79, 6hr), Telehealth ($45, 3hr), Suicide Risk ($79, 6hr), Alcoholism ($149, 15hr), Child Abuse ($89, 7hr), HIV/AIDS ($89, 7hr), Human Sexuality ($99, 10hr), Clinical Supervision ($169, 15hr).</p>

<h2>Your 7 Exam Prep Programs (catalog)</h2>
<p>LMFT Law &amp; Ethics ($199), LCSW Law &amp; Ethics ($199), LPCC Law &amp; Ethics ($199), LMFT AMFTRB ($329), LMFT California Clinical ($249), LCSW ASWB Clinical ($249), LPCC NCMHCE ($249). All 6-month access.</p>

<div class="box-ce"><strong>Summary:</strong> Courses is your product switchboard — Published goes live, Draft stays hidden. CE needs CEPA approval to publish; Exam Prep is your direct Publish/Draft control.</div>

</body></html>
HTML;

$html = str_replace( 'DATE_PLACEHOLDER', htmlspecialchars( $date, ENT_QUOTES, 'UTF-8' ), $html );
$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => false ) );
$dompdf->loadHtml( $html );
$dompdf->setPaper( 'A4', 'portrait' );
$dompdf->render();
file_put_contents( $out, $dompdf->output() );
echo "Wrote: {$out}\n";
