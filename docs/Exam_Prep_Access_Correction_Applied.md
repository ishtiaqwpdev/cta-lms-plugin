# CTA Exam-Prep Access Correction Notice v1.0 — Applied

**Plugin:** 1.0.154  
**Date:** 2026-08-06

## Controlling rule

Exam Preparation programs (LPCC, LCSW, LMFT California, LMFT AMFTRB) do **not** use CE-style locks. All learner-facing content is open from enrollment. CE courses retain progression/exam/evaluation locks.

## Changes

- `CTA_Exam_Access::uses_assessment_gates()` always returns `false`
- Exam Prep `user_can_access()` ignores `unlock_after_quiz_type`
- Material maps for AMFTRB / LCSW / LMFT Clinical / LPCC no longer seed unlock gates
- Sync always writes `unlock_after_quiz_type = ''` for Exam Prep resources
- Upgrade clears leftover unlock columns on Exam Prep courses
- Form A → Form B shown as **advisory banner only** (not a blocker)
- AMFTRB learner titles softened from “Required / Progression Gate” to “recommended…”
- Admin/internal restricted paths unchanged

## Offline verify

`scripts/test-exam-prep-access-correction.php` — 40 PASS / 0 FAIL
