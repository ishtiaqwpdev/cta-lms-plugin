# AMFTRB Pre-Launch Testing Checklist

**Program:** CTA LMFT AMFTRB National Exam Preparation Program  
**Plugin:** 1.0.147  
**Date:** 2026-08-06  
**Overall:** `PASS_OFFLINE_HOLD_LIVE` — **25 PASS / 0 FAIL / 5 BLOCKED**  
**Machine-readable:** `docs/AMFTRB_Prelaunch_Testing_Checklist.json`  
**Runner:** `C:\xampp\php\php.exe scripts/test-amftrb-prelaunch-checklist.php`

## Prior status

This 5-area checklist had **not** been fully verified or documented before this run. Earlier work implemented order, audio, and gates in code, but live learner QA was still on HOLD.

## Area results

### 1. Course navigation and lesson order — PASS (offline)

| ID | Result | Check |
|----|--------|--------|
| mod_count | PASS | 12 workbook modules defined |
| wb_order | PASS | Student workbooks WB1→WB12 |
| wb_audio_bank_order | PASS | Each block: WB → Audio → Candidate Bank → Rationale; CP1 after WB4, CP2 after WB8, CP3 after WB12 |
| form_order | PASS | Form A candidate before Form B |
| form_gates_candidate | PASS | Form A = `modules_complete`; Form B = `form_b_ready` |
| files_exist | PASS | All 69 mapped learner materials on disk |
| no_internal_in_assets | PASS | No internal-controls paths under learner materials |

### 2. Audio playback and transcript access — PASS (structural) / BLOCKED (browser)

| ID | Result | Check |
|----|--------|--------|
| tracks_1_12 | PASS | 12/12 MP3s present; valid ID3/MPEG headers; placement-map runtimes resolve |
| transcript_file | PASS | Authoritative transcript DOCX present |
| player_markup | PASS | `<audio class="cta-audio-player">` in learner template |
| transcript_link_markup | PASS | Track N transcript link in template |
| runtime_line | PASS | Runtime line from placement map |
| browser_playback | **BLOCKED** | No WordPress learner session to play tracks in-browser |

### 3. Downloads and protected-resource permissions — PASS (gates) / BLOCKED (live serve)

| ID | Result | Check |
|----|--------|--------|
| packages_deny | PASS | `_packages/.htaccess` Deny from all |
| deny_markers | PASS | `03_INTERNAL_CONTROLS` + blueprint/inventory + `02_PROTECTED_RATIONALES` markers blocked |
| rationale_gate | PASS | Rationale locked before preserved attempt; unlocked after |
| candidate_open | PASS | Candidate bank accessible while enrolled |
| list_filter | PASS | Internal rows stripped from student list |
| preserve_ui | PASS | “Record that I completed this assessment” control present |
| live_serve_urls | **BLOCKED** | Needs live `cta_serve_resource` against seeded course |

### 4. Desktop and mobile learner views — PARTIAL / BLOCKED (live)

| ID | Result | Check |
|----|--------|--------|
| audio_css | PASS | Audio player CSS present |
| mobile_breakpoints | PASS | Mobile `@media` breakpoints in theme-compat |
| desktop_walkthrough | **BLOCKED** | No WP learner UI host (`xampp/htdocs` empty) |
| mobile_walkthrough | **BLOCKED** | Same |

### 5. Assessment and resource access controls — PASS (logic) / BLOCKED (live UI)

| ID | Result | Check |
|----|--------|--------|
| gates_locked_default | PASS | wb/CP/Form gates locked without attempt |
| form_a_after_attempt | PASS | `form_a` opens after preserved attempt |
| form_b_ready_gate | PASS | Form B candidate requires Form A remediation complete |
| rationale_gate_coverage | PASS | Rationales gated for WB1–12 banks, CP1–3, Form A/B |
| launch_hold | PASS | Remains draft / `launch_pending_testing` until live sign-off |
| live_gate_walkthrough | **BLOCKED** | Needs enrolled test learner on WP |

## Launch decision

**Do not clear checkout HOLD yet.** Offline structural and gate verification passed with zero failures. The five BLOCKED items require a WordPress test-learner environment (desktop + mobile walkthrough, in-browser audio, live download/serve URLs, live gate UI).

## Live QA to finish (when WP is available)

1. Enroll a test learner on AMFTRB (draft/admin preview OK).
2. Confirm materials order matches Area 1.
3. Play all 12 tracks; open Track N transcript links.
4. Download a candidate bank; confirm rationale 403 until “Record attempt”; then unlocks.
5. Confirm Form A → remediation → Form B sequence.
6. Repeat viewport checks at desktop (~1280px) and mobile (~375px).
7. Document screenshots; then clear HOLD only after Founder/CEO approval.
