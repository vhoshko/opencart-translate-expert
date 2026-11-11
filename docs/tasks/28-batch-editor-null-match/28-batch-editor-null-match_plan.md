# 28-batch-editor-null-match

## Settings
- Planning model: Opus 5.5 (inline). Review model: Fable.
- Flow: stripped-down flow (the user agreed). No Explore agent and no sanity check.
- Build and tests: yes, through `ote-build-test` in full mode.
- Commit, push and PR: no. The workflow never does these steps.
- Auto-approve for review findings: not granted. Ask the user for each finding.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/28

`updateBatchEditorElements()` in `sources/upload/admin/view/javascript/client_translate_expert.js:249` selects `textarea[id^='description_'][id$='_description']` with no `name`. This selector also selects an id with no numeric part, for example `description_description`. The regex `/^(.*)\_(\d+)\_(.*)$/` then returns `null`, and `:261` reads `nameMatch[1]`. A TypeError occurs. The throw stops `addTranslateExpertElements()`. On the first call from `initTranslateExpert()`, it also stops the `setInterval` and `initModule()`.

The JS file is the same for all OpenCart versions (1.5–4.1), so the bug and the fix apply to all versions.

## Approach
Add a null check after the `exec` call in the `each` callback. If the id does not match, skip that element. Use the same two-line `if (!nameMatch)` / `return;` style that the file uses at `:330` and `:560`.

## Assumptions
- ✅ verified: a `return` in a jQuery `each` callback skips only the current element. A `return false` stops the loop. The fix returns `undefined`, so the loop continues. This is standard jQuery behavior, and the same file uses it.

## Steps
- [x] Code: add the null check in `updateBatchEditorElements()`
  Findings: a `node` probe showed that the regex returns `null` for `description_description` and matches `description_1_description`.
- [x] Bump version + build + test
  Findings: green. Version 04.01.0125. Unit tests 24 passed (6 integration tests skipped, no key). Route filter tests 40 passed. The null check is present in the oc1.5 and oc4.x packages.
- [x] Independent review (Fable) → 28-batch-editor-null-match-review.md
  Findings: no Critical or Important findings. Minor 1: a skipped element is selected again on each interval call. Minor 2: no JS regression test.
- [x] Address review findings (the user picks which to fix or skip)
  Findings: the user chose to fix Minor 1. The regex check moved into `.filter()`, as in `updateHandyProductManagerElements()`. The in-loop null check is now redundant, so it is removed. The regex has no `g` flag, so a second `exec` call is safe. Minor 2 needs no code action. Do the manual browser check from the issue before the release.
- [x] Bump version + build + tests (verify loop)
  Findings: green. Version 04.01.0126. Same test counts. The new filter line is present in the oc1.5 and oc4.x packages.
- [x] Re-review? (unreviewed fixes exist. Ask the user.) The user said yes.
- [x] Independent review (Fable) → 28-batch-editor-null-match-review-v2.md
  Findings: ready to merge. One Minor finding with no action necessary: the regex runs two times for each matching element. The adjacent function does the same. Not fixed. No further pass is recommended.
- [x] Improvements pass → 28-batch-editor-null-match-improvements.md

Dropped steps:
- Templates and language strings: no UI change and no new strings.
- Regression test: the repository has no JS test harness.

## Done
- `updateBatchEditorElements()` now filters out nameless textareas whose id has no `_<digits>_` part. This stops the TypeError. The structure is the same as in `updateHandyProductManagerElements()`.
- Final version: 04.01.0126 (`releases/v04.01.0126/`). Two Fable reviews: ready to merge.
- No JS regression test exists. Do the manual browser check from issue #28 before the release.
- Nothing is committed. The changed files are the JS file, `_version.txt` and this task folder.
