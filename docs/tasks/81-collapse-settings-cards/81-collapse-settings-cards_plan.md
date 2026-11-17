# 81-collapse-settings-cards

## Settings
- Planning model: session model (Sonnet 5.5). Review model: Opus (`ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: yes (user choice on 17.11.2026).
- Standing auto-approve for review findings: yes.

## Context
Issue #81. The cards "Scheduled translation (cron)", "Excluded routes" and "Language codes" on the Settings tab start open. "Try a translation" starts closed. All OC versions are affected (`.tpl` for OC 1.5–3.0, `.oc4.twig` for OC 4.x). Key lines: `client_translate_expert.tpl:112-170` and `client_translate_expert.oc4.twig:112-170`, `client_translate_expert.js:1431` (`teInitCollapse`), `client_translate_expert.js:815` (`teLcmInit`).

## Approach
Use the "Try a translation" pattern for the 3 cards. The card head holds a toggle button. The card body gets `te-hidden` and an id. The language codes card starts open if `$error_language_code_map` is set. The JS submit check of the language code map also opens the card, because it shows its errors inside the card.

## Assumptions
- ✅ verified: the only server-side save error that shows inside these cards is `error_language_code_map` (grep of the templates and `validate()` in the controller).
- ✅ verified: the cron secret warning `#te-cron-secret-unsaved` shows only after the user edits the visible field.
- ❓ unverified: no browser run on a real OpenCart. The jsdom tests cover the JS part.

## Steps
- [x] Templates: .tpl and .oc4.twig
- [x] JS: open the language codes card on a submit error
- [x] Tests: `tests/test_client_translate_expert_settings_cards.php`, JS helper tests
- [x] Bump version + build + test (04.01.0215)
- [x] Independent review → 81-collapse-settings-cards-review.md (4 minor)
- [x] Address review findings: fixed 1 and 2; skipped 3 and 4 (true before this change, out of scope)
- [x] Bump version + build + test (04.01.0215)s (verify loop)
- [x] Re-review? Skipped: minor findings only
- [x] Improvements pass → 81-collapse-settings-cards-improvements.md

## Done
The 3 cards start closed in the .tpl and the .oc4.twig. The language codes card opens on a save error and on a client-side submit error. Final version 04.01.0216. The review found 4 minor findings. Fixed 2, skipped 2 (true before this change). No second review (only minor findings). Follow-up: a number field in a closed card can block the save with no message.
