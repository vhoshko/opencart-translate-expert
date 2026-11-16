# 57-json-char-count-remove-data

## Settings

- Planning model: Opus 5.5 (inline, in the main conversation).
- Review model: Fable 5.1 (pass `model: fable` to `ote-code-reviewer`).
- Flow: full flow. The task changes PHP, JS, both templates and the language files.
- Build: bump the patch version and run `./build.release.sh` in Phase 3 and in each verify loop.
- Tests: all unit tests. Add PHP tests for `removeDataFromDb` steps and `clearedRows`. Add JS tests for the new renderers.
- Commit, push, PR: yes, after Phase 7 (the user said so on 2026-11-16). Commit with the format of `project-workflows.md`, push `feat/57-json-char-count-remove-data`, then `gh pr create`.
- Store run: yes. The user approved the plan on 2026-11-16 and asked for a WIP push and a PR after the first green build. The user tests on the stores and reads the PR at the same time. Later pushes of review fixes go to the same branch and PR.
- Review findings: standing auto-approve (the user said so on 2026-11-16). Decide each finding without a question to the user and report the reason. Prefer to fix. Go back to the user for a change of approach, an external system or a scope expansion.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/57

This is step 2 of the "Next tasks" of the PoC (`docs/pocs/2-admin-design/README.md`). Task 56 (PR #62, merged) added the `te-*` CSS, the JS helpers (`teT`, `teEl`, `teIcon`, `teBs`, `teAlerts`), `document.te_i18n` and `document.te_i18n_global`. This task changes 2 endpoints to JSON data and adds the `te-summary` bar.

Affected OpenCart versions: all (1.5–4.1).

Key files (from the investigation, checked with reads):

- `translatedCharCountInfo`: CTRL:657-692. It returns `info` (HTML from `entry_server_translation_char_count_info`, with an inline `onclick=confirm()`), `color` and 3 `*Display` fields. `charCount` comes raw from the setting table, so it can be a string.
- `resetCharCount`: CTRL:694-705, returns `success: true`.
- JS `showTranslatedCharCount()` (JS:1105-1147) reads only `success` and `info`. It prepends an `<li id="te-translated-char-count-info">` to the header menu. It runs from `initModule()` (JS:1227), after each translation (JS:238, 715, 783, 791, 1030) and after a reset (JS:1165). `initModule()` runs only if the module is enabled.
- `removeDataFromDb`: CTRL:608-637 (`langIdTo` is not cast to int), CORE:285-344. CORE returns `headerHTML`, `body` (`implode('<br>')` of language strings) and `timeInSecInfo` (HTML). The missing-language error is hard-coded English with `success: false`.
- `removeDataFromTable()`: LIB:894-930. It runs one `UPDATE ... SET col = '' WHERE ... col <> ''` and returns nothing. `$langIdToParam` goes into the SQL raw.
- JS `makeRemovingDataFromDbRequest()` (JS:1058-1103). Its `.fail()` handler writes the response into `#te-analization-result` (JS:1101), so it clears the results.
- CSS: `te-summary` (CSS:138-158), `te-meter` (CSS:329-357), `te-badge` (CSS:359-382) are only under `.te-app`. `te-header-count` (CSS:483-512) has `--ok` and `--over`. The old `#translation_char_count_info*` and `#reset_translation_char_count*` rules (CSS:59-99) become dead.
- Neither template has a `.te-app` root now.
- Unused after the change: `entry_server_translation_char_count_info` (LANG:88-93) and the 5 `message_remove_data_from_*` keys (LANG:78-82). CTRL:103-107 copies the 5 keys into `$data`, but no template uses them.
- Tests: `tests/test_client_translate_expert_translate_loop.php` calls `removeDataFromTable()` through `RealSqlLibrary::remove()`. `FakeDb` has no `countAffected()`. `tests/js/test_client_translate_expert_js_helpers.js` loads the real JS file in jsdom.

## Approach

**`translatedCharCountInfo`.** Return `success: 1`, `charCount` (int), `monthFreeLimit` (int), `priceFor20MInUSD` (int) and `costOverFreeLimit` (float, rounded to 2 decimals). Remove the other fields and the language string. `resetCharCount` returns `success: 1`.

**Header item.** A new JS renderer makes the item with `teEl`: root `span.te-header-count.te-header-count--ok|--over`, a label (`te_global_char_count`), a value `"300 000 / 500 000 ($0.00)"` and a reset `button.te-header-count__reset` (`te_global_reset`). The JS formats the numbers. The `<li id="te-translated-char-count-info">` wrapper and the 3 header selectors stay. A delegated handler on `document` for `.te-js-reset-char-count` asks `confirm(teT('te_global_reset_confirm'))` and then calls `resetTranslationCharCount()`. The handler works on all admin pages.

**`te-summary` bar.** Both templates get a `<div class="te-app">` root above the panel, with the `te-summary` bar and a `te-alerts` area for the bar. The server renders the 2 badges: module status (Enabled or Disabled) and API key (Set or Not set). The source is the saved config, not the POST data. The JS fills the meter part from the JSON: "This month: 300 000 / 500 000", the `te-meter` bar with the percent, the cost and the reset button. On the module page, the JS does not add the header item, because `te-summary` replaces it (design-concept.md, section 5, note 1). The bar loads its data also when the module is disabled, because the "show globally" flag and the status do not apply to the module page.

**Errors of the count request.** On the module page, the JS shows the error in the `te-alerts` area of the bar. On other admin pages, the JS writes the error to the console only. `showTranslateExpertErrorMessage()` hides the progress modal, and other pages do not have this modal.

**`removeDataFromDb`.** CTRL casts `langIdTo` to int. CORE returns `success: 1`, `target` (language object of section 3.0, rule 7, with a decoded `name`), `steps[]` and `timeInSec` (float). The steps are `start`, `table` (`table`, `clearedRows`), `column` (`table`, `column`, `clearedRows`) and `finish`. `removeDataFromTable()` returns `(int)$this->_model->db->countAffected()`. The `clearedRows` of a table step is the sum of its column steps. If `langIdTo` is not a positive int or is not a store language, CORE returns `success: 0` with a message from a new language key.

**Remove log.** The JS renders a title line (`te_remove_completed` with the target language name), one log line for each step (labels from `te_i18n`) and a time line. All text goes in with `.text()`. The `.fail()` handler does not clear `#te-analization-result`.

**Language keys.** New `te_*` keys in the 3 language files: the summary labels, the remove step labels and the time label. Add them to the `te_i18n` list in CTRL:216. Remove `entry_server_translation_char_count_info` and the 5 `message_remove_data_from_*` keys and their `$data` lines.

**Docs.** Rename `deletedRows` to `clearedRows` in `endpoints-json.md`, section 3.3. Update note 2 there: OC 1.5.6.4 has `countAffected()` (checked in the issue).

**Design corrections.**

1. `badFilterResponse()` (CTRL:501) also serves `removeDataFromDb`. It changes to `success: 0`. All JS callers test `!data.success`, so the other endpoints keep their behavior.
2. The `te-meter` uses only `ok` and `over`, as section 3.5 says. This task adds no `warn` threshold.
3. The cast of `langIdTo` to int closes an SQL injection in `removeDataFromTable()` (admin and token only). This is a small change in the touched code.

**Out of scope (recorded, not changed):**

- GET for `removeDataFromDb` and `resetCharCount`, and the model re-throw (PoC README step 6).
- The header item does not show on OC 1.5, because no selector matches the OC 1.5 header. This is the current behavior.
- The progress modal of the removal stays. The follow-up of task 56 (make `showTranslateExpertModal` call `teBs.modal`) waits for the task that changes the modal.

## Assumptions

- ✅ `countAffected()` exists on all supported versions. Evidence: the issue notes (OC 1.5.6.4 `system/library/db.php:27`, checked on 2026-11-15). CORE:988 already calls `$db->countAffected()` in production code.
- ✅ The `<> ''` condition makes MySQL count only changed rows. MySQL `affected_rows` for `UPDATE` counts changed rows by default (no `CLIENT_FOUND_ROWS`). The `WHERE` also excludes rows that are already empty, so the count is the same with `CLIENT_FOUND_ROWS`.
- ✅ No other code reads the removed fields. Evidence: the investigation grep over `sources/`, `tools/` and `tests/` for `info`, `headerHTML`, `timeInSecInfo`, `charCountDisplay` found only CTRL, CORE and JS.
- ✅ The header loads the JS and CSS with `?v=<version>`, so the browser loads the new JS with the new package (`endpoints-json.md`, section 5).
- ❓ The new header item and `te-summary` render correctly on real OC 2.3, 3.0 and 4.x pages. The jsdom tests check the DOM, not the look. If this is false, the item looks wrong in the header. A store run checks it.

## Steps

- [x] Code PHP: CTRL `translatedCharCountInfo`, `resetCharCount`, `badFilterResponse`, `removeDataFromDb` (int cast), `te_i18n` keys, summary data (`te_summary_status`, `te_summary_key_set`), remove dead `$data` lines
- [x] Code PHP: CORE `removeDataFromDb` (target, steps, timeInSec, errors), LIB `removeDataFromTable` returns `clearedRows`
- [x] Code JS: header item renderer, `te-summary` renderer, delegated reset handler, remove log renderer, `.fail()` fix
- [x] CSS: remove the dead `#translation_char_count_info*` rules. Add a `te-log` style if necessary (under `.te-app`)
- [x] Templates: `.te-app` root with `te-summary` and `te-alerts` in `.tpl` and `.oc4.twig`
- [x] Language strings: en-gb, ru-ru, uk-ua (add `te_*` keys, remove 6 keys)
- [x] Tests: PHP (`FakeDb::countAffected()`, `removeDataFromTable` return value, `removeDataFromDb` steps and errors), JS (header item, summary, reset handler, remove log, `.fail()` keeps results)
- [x] Docs: `endpoints-json.md` section 3.3 rename to `clearedRows`
  - Findings (all code steps): `ote-implementer` did the code steps. I checked the PHP, JS and template diff in the main context. CORE checks `is_int($languageIdTo)`. The model passes the int from CTRL with no change (model:56-59). The CORE loop matched all languages when `languageIdTo` was empty. Now it matches only the target language. `entry_time_in_sec` stays, because `analize` uses it (CORE:240). The new `config->get` lines use keys that the `module_` prefix list of `build.sh` already has. No new AJAX method, library class or setting key, so `build.sh` does not change. Tests: JS 180, translate loop 241, other suites unchanged.
- [x] Bump version + build + test
  - Findings: green. 04.01.0179. 1292 tests pass (6 integration tests skipped, no key). The rewrite checks in the OC 1.5, 2.2, 3.0 and 4.x packages pass.
- [ ] Store run on OC 2.3, 3.0 and 4.x (only if the user approves a WIP push)
  - Findings: WIP push and PR #63 on 2026-11-16 (commit with v04.01.0179). The user does the store run.
- [x] Independent review → 57-json-char-count-remove-data-review.md
  - Findings: Fable 5.1. Ready to merge with fixes. 1 Important, 5 Minor, no Critical.
- [x] Address review findings (auto-approve)
  - 1 (Important, no test for the gating and the error routing of `showTranslatedCharCount`): fix. Add jsdom tests. The file has the `$.get` stub pattern.
  - 2 (Minor, dead inner language loop in CORE `removeDataFromDb`): fix. Call `removeDataFromTable()` one time for each column.
  - 3 (Minor, the steps guard accepts a string): fix. Use `$.isArray` (jQuery 1.7.1 has it). Add a test.
  - 4 (Minor, a missing `timeInSec` shows `{sec}`): fix. Use `Number(data.timeInSec) || 0`.
  - 5 (Minor, `endpoints-json.md` inventory and "Now" lines show the old state): fix. The inventory is a dated record, so I added status notes and did not rewrite the "Now" lines.
  - 6 (Minor, no `hasPermission` check in `translatedCharCountInfo`, `resetCharCount` and `removeDataFromDb`): skip. The issue is older than this change and is out of scope. Follow-up: add it to the PoC step 6 task (GET to POST).
- [x] Bump version + build + tests (verify loop)
  - Findings: green. 04.01.0180. 1314 tests pass (JS 202, 6 integration tests skipped, no key). I checked the fix diff in the main context before the build.
- [x] Re-review? (ask the user)
- [ ] Improvements pass → 57-json-char-count-remove-data-improvements.md
- [ ] Commit, push, PR

## Done
