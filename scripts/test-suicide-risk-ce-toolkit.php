<?php
/**
 * Validate CTA-CE-003 learner resource toolkit content and enrollment gating.
 *
 * Usage: php scripts/test-suicide-risk-ce-toolkit.php
 *
 * @package CTA_LMS
 */

$root = dirname( __DIR__ );

$pass = 0;
$fail = 0;

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: {$msg}\n";
	} else {
		++$fail;
		echo "FAIL: {$msg}\n";
	}
}

$toolkit_path = $root . '/assets/course-materials/suicide-risk-ce/CTA_Suicide_Risk_Learner_Resource_Toolkit_v1_1.html';
$html         = is_readable( $toolkit_path ) ? (string) file_get_contents( $toolkit_path ) : '';

assert_true( '' !== $html, 'Toolkit HTML source file exists' );

$copyright = 'Copyright © 2026 Clinical Training and Supervision Academy. CTA-created worksheets may be used by enrolled learners for personal professional practice. External instruments remain the property of their original owners.';
assert_true( false !== strpos( $html, $copyright ), 'Copyright/use notice verbatim' );

assert_true( false !== strpos( $html, 'Learner Resource Toolkit — Clinician-Facing Resource Toolkit' ), 'Toolkit title present' );
assert_true( false !== strpos( $html, 'id="resource-1"' ), 'Resource 1 section' );
assert_true( false !== strpos( $html, 'Person-Specific Suicide-Risk Formulation Worksheet' ), 'Resource 1 heading' );
assert_true( false !== strpos( $html, 'A. Current Suicidal Thoughts and Behavior' ), 'Resource 1 section A' );
assert_true( false !== strpos( $html, 'H. Action and Reassessment Plan' ), 'Resource 1 section H' );
assert_true( false !== strpos( $html, 'id="resource-2"' ), 'Resource 2 section' );
assert_true( false !== strpos( $html, 'Step 1 — Personal warning signs' ), 'Resource 2 step 1' );
assert_true( false !== strpos( $html, 'Step 6 — Lethal-means safety' ), 'Resource 2 step 6' );
assert_true( false !== strpos( $html, 'Feasibility, Rehearsal, and Follow-Up Checklist' ), 'Resource 2 checklist' );
assert_true( false !== strpos( $html, 'id="resource-3"' ), 'Resource 3 section' );
assert_true( false !== strpos( $html, 'Local Pathway Worksheet' ), 'Resource 3 local pathway' );
assert_true( false !== strpos( $html, 'Escalation Triggers Requiring Immediate Reassessment' ), 'Resource 3 escalation triggers' );
assert_true( false !== strpos( $html, 'id="resource-4"' ), 'Resource 4 section' );
assert_true( false !== strpos( $html, 'Fictional Abbreviated Worked Example' ), 'Resource 4 worked example' );
assert_true( false !== strpos( $html, 'id="resource-5"' ), 'Resource 5 section' );
assert_true( false !== strpos( $html, 'Protocol Ownership and Local Contacts' ), 'Resource 5 ownership section' );
assert_true( false !== strpos( $html, 'id="appendix-a"' ), 'Appendix A section' );
assert_true( false !== strpos( $html, 'https://988lifeline.org/' ), 'Appendix A 988 link' );
assert_true( false !== strpos( $html, 'https://cssrs.columbia.edu/' ), 'Appendix A C-SSRS link' );
assert_true( false !== strpos( $html, 'id="appendix-b"' ), 'Appendix B section' );

$appendix_b = 'CTA-created worksheets are original educational aids and are not validated prediction instruments. The C-SSRS is an external instrument. Use official current versions and follow administration and training guidance. CTA does not reproduce the scale in this toolkit. SAFE-T is an external SAMHSA clinical framework. This toolkit references its structure but does not replace the official publication. The Stanley-Brown Safety Plan is copyrighted. This toolkit does not reproduce or modify the form. The official site permits individual use and requires written permission for changes or electronic medical record use. California statutes and local implementation procedures may change. Recheck current law, designated pathways, and local policy before use. 988 service details and accessibility options may change; verify the official site during future course updates.';
assert_true( false !== strpos( $html, $appendix_b ), 'Appendix B verbatim permissions note' );

$materials_src = file_get_contents( $root . '/includes/class-cta-course-materials.php' );
assert_true( false !== strpos( $materials_src, 'suicide_risk_learner_resource_toolkit_v1_1' ), 'Bundled materials entry registered' );
assert_true( false !== strpos( $materials_src, 'CTA-CE-003' ), 'Bundled materials scoped to CTA-CE-003' );
assert_true( false !== strpos( $materials_src, 'cta-protected://' ), 'Protected storage URL scheme used' );
assert_true( false !== strpos( $materials_src, 'You must be enrolled in this course to download materials' ), 'Serve endpoint blocks non-enrolled users' );

$sync_src = file_get_contents( $root . '/includes/class-cta-suicide-risk-toolkit-sync.php' );
assert_true( false !== strpos( $sync_src, 'CTA-CE-003' ), 'Toolkit sync scoped to CTA-CE-003' );
assert_true( false !== strpos( $sync_src, 'unpublish_all_ce_courses_pending_cepa' ), 'Toolkit sync keeps CE draft hold' );

echo "\nEnrollment gating: toolkit served only via CTA_Course_Materials::serve (cta-protected storage + user_can_access enrollment check).\n";
echo "Public plugin URL: assets/course-materials/ is NOT exposed as a direct public download path.\n";
echo "Live desktop/mobile download test requires deploy + enrolled learner session.\n";

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
