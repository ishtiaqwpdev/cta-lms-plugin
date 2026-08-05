# Exam Preparation — Release Gate Checklist

**Policy:** All Exam Preparation programs (current and future) must remain **Draft / unpublished** and **not publicly purchasable** until **both** conditions below are met.

## Required before any Exam Prep public launch

- [ ] **Final learner testing completed and verified** for the specific program being launched.
- [ ] **Written approval from CTA (the client)** received authorizing public sale of that program.

Do **not** publish (or leave published) any Exam Prep program that has not cleared both gates.

## Programs covered (as of plugin 1.0.143)

| Public display title | Formal / internal title | Notes |
|---|---|---|
| LMFT California Law & Ethics Exam Preparation | CTA LMFT California Law & Ethics Exam Preparation Program | Existing |
| LCSW ASWB Clinical Exam Preparation | CTA LCSW ASWB Clinical Exam Preparation Program | Existing |
| LMFT California Clinical Exam Preparation | CTA LMFT California Clinical Exam Preparation Program | Existing (commercial terms also pending) |
| LPCC NCMHCE Exam Preparation | CTA LPCC NCMHCE Exam Preparation Program | Existing |
| LCSW California Law & Ethics Exam Preparation | CTA LCSW California Law & Ethics Exam Preparation Program | Add-on shell |
| LPCC California Law & Ethics Exam Preparation | CTA LPCC California Law & Ethics Exam Preparation Program | Add-on shell |
| LMFT AMFTRB National Exam Preparation | CTA LMFT AMFTRB National Exam Preparation Program | Add-on |

Future Exam Prep programs inherit the same gate automatically (`product_type = exam_prep`).

## Technical enforcement (verify before launch)

1. **Status:** Course row `status` must be `draft` until intentionally published after approval.
2. **Public catalog / single course:** Only `status = 'published'` rows are listed or viewable for purchase.
3. **Checkout / Stripe / demo bypass:** Require `status = 'published'`; additionally reject checkout when `syllabus_meta.launch_pending_testing` (or `launch_status = draft_pending_testing`) is set.
4. **Admin publish:** Explicit confirmation required (list Publish button and edit-screen save), stating testing + written CTA approval.
5. **Migrations / restore:** `CTA_Course_Catalog::unpublish_all_exam_prep_pending_launch()` and catalog `launch_pending_testing` keep programs forced to draft until launch flags are cleared for an approved go-live.

## Pre-launch verification (public / non-admin)

For **each** Exam Prep program:

- [ ] Program does **not** appear on the public courses/catalog listing while Draft.
- [ ] Direct course URL does **not** allow purchase while Draft.
- [ ] Checkout / Buy AJAX for that `course_id` fails for a logged-in non-admin learner (and for anonymous where applicable).
- [ ] No membership or bypass path enrolls the program while Draft / launch-pending.

Only after the two policy checkboxes above are complete:

1. Remove `launch_pending_testing` from that program’s catalog/seed definition (so restore does not re-draft it).
2. Publish with the admin confirmation prompt (clears launch-pending meta on the course row).
3. Re-run the purchase verification as a positive test (checkout must succeed for a published, approved program).

