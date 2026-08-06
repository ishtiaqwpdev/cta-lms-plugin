# LPCC Public Description — Audio Advertising Approval

**Decision:** APPROVED (post-test)  
**Plugin:** 1.0.153  
**Date:** 2026-08-06

## Gate rule

Public LMS/website description may advertise the eight audio-review tracks **only after** Prompts 9–11 are complete:

| Prompt | Requirement | Status |
|--------|-------------|--------|
| 9 | 8 tracks uploaded / wired | PASS |
| 10 | Total + per-track runtimes | PASS |
| 11 | Desktop + mobile playback/access | PASS — see `docs/LPCC_Audio_Playback_Verification.md` |

## Code gate

`CTA_Lpcc_Ncmhce_Sync::AUDIO_PUBLIC_ADVERTISING_APPROVED = true`  
set only because Prompt 11 documented all PASS (desktop + mobile).

When this flag is `false`, program HTML / short / meta descriptions omit audio advertising.

## Public copy now includes (approved)

- Bullet: Eight screen-free audio-review tracks (combined runtime 48 minutes 49 seconds)
- Closing: Written program complete. Eight recorded audio-review tracks are included (combined runtime 48 minutes 49 seconds).
- Course Information line: 8 tracks, combined runtime 48 minutes 49 seconds

## Verification

Confirm `AUDIO_PUBLIC_ADVERTISING_APPROVED` is true **and** `docs/LPCC_Audio_Playback_Verification.md` shows suite PASS before any future marketing change that expands audio claims.
