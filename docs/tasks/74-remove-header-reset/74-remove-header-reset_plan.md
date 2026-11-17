# 74-remove-header-reset

## Settings
- Planning model: session model (inline).
- Review model: a model that differs from the planning model (`ote-code-reviewer`, Opus).
- Flow: stripped-down (one JS file, one CSS file, one test file, under 20 lines of logic).
- Build, tests: yes.
- Standing auto-approve for review findings: yes (user, 17.11.2026). Prefer to fix.
- Commit, push and PR: yes, after the improvements pass (user, 17.11.2026).

## Context
- Issue: https://github.com/vhoshko/opencart-translate-expert/issues/74.
- The header char count item has a Reset button. See `sources/upload/admin/view/javascript/client_translate_expert.js:1144`.
- The module page meter has its own Reset button (`:1167`). It stays.
- The delegated click handler (`:1174`) serves the class `te-js-reset-char-count`. It stays.
- Affected: all OC versions (the header item shows in OC 1.5 to 4.x).
- The CSS rules for `te-header-count__reset` are in `sources/upload/admin/view/stylesheet/client_translate_expert.css:664` and `:705-717`.
- Tests: `tests/js/test_client_translate_expert_js_helpers.js:217`, `:220-223`, `:248-258` and `:409`.
- The keys `te_global_reset` and `te_global_reset_confirm` stay in the language files and in `client_translate_expert_i18n.php`. The module page still uses them (`teT` at `:1167` and `:1180`).

## Approach
Remove the button from `teRenderHeaderCount`. Remove the CSS rules of the button. Change the tests: the header item has no button. The delegated handler test uses `teRenderSummaryCount` as its host. No language change.

## Assumptions
- ✅ verified: the module page meter is rendered by `teRenderSummaryCount` and has the class `te-js-reset-char-count` (`:1167`).
- ✅ verified: grep shows no other user of `te-header-count__reset` in `sources/`, `tests/` and `build.sh`.

## Steps
- [x] Code: remove the button line in `teRenderHeaderCount`
- [x] CSS: remove the 3 rule groups of `te-header-count__reset`
- [x] Tests: update the JS helper tests (header has no button, handler test uses the summary)
- [x] Bump version + build + test
- [x] Independent review → 74-remove-header-reset-review.md
- [x] Address review findings
- [x] Improvements pass → 74-remove-header-reset-improvements.md

## Done
- The header char count item has no Reset button. The module page meter keeps its Reset button and the delegated handler.
- The CSS rules of `te-header-count__reset` and the unused `--te-accent` and `gap` of `.te-header-count` are removed.
- The review found 3 minor findings (2 stale comments, 1 unused CSS). All 3 are fixed. Final version: 04.01.0208. 633 JS tests and all PHP tests pass.
- No re-review of the comment-only fixes (user asked for an unattended run).
