# LPCC Audio — Desktop/Mobile Playback Verification

**Date:** 2026-08-06  
**Plugin:** 1.0.152  
**Method:** LMS-parity HTML5 `<audio controls>` harness against the 8 package MP3s (same markup/CSS class as `templates/partials/course-materials.php`). Viewports: desktop **1920×1080**, mobile **390×844**.

**Not previously verified** — first full playback/access run for this prompt.

## Fix required for seek / complete play

`CTA_Course_Materials::handle_serve_request()` previously streamed files with a full `readfile()` and **no HTTP Range / 206** support. Native players could not seek (seekable range stayed `0–0`). **Fixed in 1.0.152** — serve endpoint now advertises `Accept-Ranges: bytes` and honors `Range` with `206 Partial Content`.

## Pass/fail record

| Test item | Desktop | Mobile |
|-----------|---------|--------|
| Playback starts and stops correctly (all 8 tracks) | **PASS** | **PASS** |
| Seeking works correctly (all 8 tracks) | **PASS** | **PASS** |
| Volume controls work correctly (all 8 tracks) | **PASS** | **PASS** |
| Track titles in correct playlist order (1→8) | **PASS** | **PASS** |
| Complete audio file plays without interruption (each track reaches `ended`) | **PASS** | **PASS** |
| Set as a whole (all 8 tracks pass above) | **PASS** | **PASS** |

### Per-track detail (both viewports identical)

| Track | Title | Play/Pause | Seek | Volume | Complete file |
|------:|-------|------------|------|--------|---------------|
| 1 | NCMHCE Case Reasoning: Sections, Qualifiers, and Evidence | PASS | PASS | PASS | PASS |
| 2 | Professional Identity, Intake, Assessment, and Differential Reasoning | PASS | PASS | PASS | PASS |
| 3 | Crisis, Trauma, Abuse, Violence, and Level-of-Care Sequencing | PASS | PASS | PASS | PASS |
| 4 | Conceptualization, Planning, Measurement, Progress, and Termination | PASS | PASS | PASS | PASS |
| 5 | Counseling Theories, Therapeutic Alliance, and Core Skills | PASS | PASS | PASS | PASS |
| 6 | Evidence-Informed Interventions and Context-Responsive Care | PASS | PASS | PASS | PASS |
| 7 | Modality, Referral, Collaboration, and California Professional Practice | PASS | PASS | PASS | PASS |
| 8 | Integrated Review, Error Repair, and Form A/Form B Readiness | PASS | PASS | PASS | PASS |

## Scope note

No local WordPress learner enrollment session was available under `C:\xampp\htdocs`. Verification used the same player pattern and package files the LMS renders. After deploying **1.0.152**, a signed-in learner on **LPCC NCMHCE Exam Preparation → Course materials** should exercise the gated `cta_serve_resource` URLs once for final live confirmation.
