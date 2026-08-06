# AMFTRB Public Checkout HOLD — Verification

**Date:** 2026-08-06  
**Plugin:** 1.0.148  
**Overall:** **PASS (held)** — public checkout/enrollment not available

## Already correct (confirmed)

| Layer | Behavior |
|-------|----------|
| Sync `ensure_program()` | Forces `status = draft` on every sync |
| Syllabus meta | `launch_pending_testing = true`, `launch_status = draft_pending_testing` |
| Exam-access catalog | AMFTRB listed as draft + launch pending |
| Public catalog | Only `published` courses; launch-pending exam prep filtered out |
| Single-course URL | Draft → “Course not found”; launch-pending (if published) → not purchasable for non-admins |
| Stripe `cta_create_checkout` | Requires `published`; rejects `launch_pending_testing` with `exam_prep_launch_pending` |
| Admin publish | Requires explicit `cta_confirm_exam_prep_publish` (testing + written CTA approval) |

## Hardened in 1.0.148

- Single-course sidebar: no Enroll/Stripe CTA while `launch_pending_testing`
- Public exam-prep catalog: skip launch-pending / commercial-pending programs
- Direct single-course URL: non-admins see “not available for purchase yet” when launch-pending
- Upgrade migration re-runs AMFTRB sync + `unpublish_all_exam_prep_pending_launch()`

## Offline verify

```
C:\xampp\php\php.exe scripts/test-amftrb-checkout-hold.php
→ 18 passed, 0 failed
```

## Live public-user check

No WordPress host in this environment (`xampp/htdocs` empty). After deploying **1.0.148**:

1. As logged-out user, open Exam Prep catalog → AMFTRB must **not** appear.
2. Hit `?course_id=<amftrb_id>` → not found / not available (not an Enroll button).
3. If enroll AJAX is forced with the course ID → error: “not available for purchase yet” / `exam_prep_launch_pending`.

**Keep HOLD** until Prompt 5 live testing is complete **and** written client release approval is received.
