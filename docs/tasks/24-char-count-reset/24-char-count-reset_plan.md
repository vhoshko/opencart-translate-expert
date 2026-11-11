# 24-char-count-reset

## Settings
- Planning model: Opus 5.5 (inline). Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: yes. The user asked for a PR after the review.
- Standing auto-approve for review findings: no.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/24

Each save of the module settings form sets `client_translate_expert_char_count` to 0. The store then loses the counted Google API usage for the month. All OpenCart versions (1.5–4.x) run this code path.

Root cause:
- `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:76` and `client_translate_expert.oc4.twig:76` show the count in a `<div>`. A `<div>` is not a form field, so the POST never contains the key.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:48-51` sets the missing key to 0. Then line 64 calls `editSetting()` with the POST, and this call writes 0 over the stored count.
- Lines 60-63 do the same for `client_translate_expert_last_reset_char_month`. Each save sets it to `date('m')`.

Other writes of these two keys use `editSettingValue` only (lines 573-574, 611, 623-624).

## Approach
In `index()`, before `editSetting()`, set the two keys from the stored config values. Use `0` and `date('m')` only if no stored value exists.

Design correction: the issue says "if the POST has no key". The plan always uses the stored value and ignores the POST value. No form field sends these keys, so a POST value can only come from a crafted request. This change also blocks a manual overwrite of the counter through the settings form.

On OC 3.0 and 4.x, `build.sh:127` and `build.sh:394` add the `module_` prefix to both keys. Both keys are already in the fixed list, so `build.sh` needs no change.

## Assumptions
- ✅ verified: `$this->config->get('client_translate_expert_char_count')` returns the stored count in the admin controller on all versions. Evidence: the production code at `client_translate_expert.php:538` and `:607` reads the count this way.
- ✅ verified: `build.sh` rewrites both keys to the `module_` form for OC 3.0 and 4.x. Evidence: the fixed list at `build.sh:127` and `build.sh:394` has both keys.

## Steps
- [x] Code: in `index()`, replace the two `isset` blocks for `char_count` and `last_reset_char_month` with an unconditional copy of the stored value (fallback `0` and `date('m')`).
  Findings: the change is in `client_translate_expert.php:48-53`. The code uses a ternary with no `??`, so it stays PHP 5.6 compatible. The user approved the full flow.
- [x] Bump version + build + test
  Findings: version 04.01.0120. Build green. Tests: 24 passed, 6 skipped (no API key) and 40 passed. The oc3.0 and oc4.x packages use the `module_` prefix for both keys in `index()`.
- [x] Independent review → 24-char-count-reset-review.md
  Findings: Fable 5.2 says ready to merge. It found 0 Critical, 0 Important and 3 Minor findings.
- [x] Address review findings (the user picks which to fix or skip)
  Findings:
  - Finding 1 (a translation that runs during a save can lose its count): the user chose to skip it, with no new issue. A full fix needs a redesign: an own setting group, a SQL increment and a migration. `storeTranslatedCharCount()` has the same race between two translation requests.
  - Finding 2 (no test and no reproduction in a store): no code change. The user can do the check steps from the issue by hand.
  - Finding 3: fixed. The count `<div>` in the `.tpl` and `.oc4.twig` has no `name` attribute now. No JS or OCMOD selects it by name.
- [x] Bump version + build + tests (verify loop)
  Findings: version 04.01.0121. Build green. Tests: 24 passed, 6 skipped and 40 passed. The oc4.x template has the `div` with no `name` attribute and shows the count as before.
- [x] Re-review? (ask the user)
- [x] Improvements pass → 24-char-count-reset-improvements.md

Skipped steps:
- Templates and language strings: no UI change.
- Investigation subagent: the issue gives the exact lines. A direct read confirmed them.
- Regression test: no test harness covers the admin controller. The suites cover `VHGoogleTranslator` and `ClientTranslateExpertRouteFilter` only.

## Done
- A settings save now keeps the stored `client_translate_expert_char_count` and `client_translate_expert_last_reset_char_month` values. A POST value for these keys has no effect.
- The count `<div>` in the `.tpl` and `.oc4.twig` has no `name` attribute now.
- Final version: ~~04.01.0121~~ 04.01.0123 (after the atomic counter update). Build green. Tests: 24 passed, 6 skipped and 40 passed.
- ~~Deferred: the counter read-modify-write race (review finding 1). The user chose to skip it, with no issue.~~ Superseded by the Update of 11.11.2026: the counter writes are atomic now, in version 04.01.0123.
- Not done: a manual check in a running store. The user asked for the commit, push and PR after the review.

## Update 11.11.2026: atomic counter writes
After PR #27, the user asked to fix review finding 1 in this task, and not in a new issue. The skip decision for finding 1 above is superseded.

### Context
Two races lose counted characters:
- `storeTranslatedCharCount()` (`client_translate_expert.php:596-610`) adds to the count from the config snapshot of its request. Two translation requests at the same time can lose an increment.
- `editSetting()` deletes all rows of the `client_translate_expert` group and inserts them again. A translation that writes the count during a save loses its increment.

### Approach
- Move the two counter rows (`client_translate_expert_char_count`, `client_translate_expert_last_reset_char_month`) into their own group, `client_translate_expert_usage`. `editSetting('client_translate_expert', ...)` then never deletes them.
- Find the counter rows by `store_id = 0` and `key` only, not by group. Old rows in the `client_translate_expert` group then work until the move.
- Add to the count with one SQL `UPDATE ... SET value = value + N`. Guard the expression against a non-numeric value.
- Do the monthly reset with a conditional `UPDATE` of the month row (`value <> current month`). Only the request that changes the month row resets the count.
- In `index()`, move the rows before `editSetting()`, and remove the two keys from the POST.
- Delete the counter rows in the controller `uninstall()` only. `resetCache()` calls the model `uninstall()`, so the model `uninstall()` must keep the rows.
- Put the SQL in the module model. Use the `group` column on OC < 2.0 and `code` on OC 2.0+.

### Assumptions
- ✅ verified: the admin loads every setting of `store_id = 0`, whatever the group. Evidence: `admin/controller/startup/startup.php:5` on OC 2.3 and 3.0, and `getSettings()` (`admin/model/setting/setting.php:15` on 4.0, `:27` on 4.1), read in the Docker stores.
- ✅ verified: `editSetting()` deletes by `store_id` and `code` only. Evidence: `admin/model/setting/setting.php:20` on 2.3 and 3.0, `:50` on 4.0, `:102` on 4.1.
- ✅ verified: `build.sh:127-136` adds the `module_` prefix to both keys in the model for OC 3.0, and OC 4.x starts from the OC 3.0 sources (`build.sh:153`).
- ❓ unverified: OC 1.5 uses the `group` column and loads all settings of `store_id = 0`. No OC 1.5 store is available. If this is false, the counter does not work on OC 1.5.

### Steps
- [x] Code: model methods and controller changes
  Findings: the model has new methods `prepareCounterSettings`, `getCharCount`, `addCharCount`, `resetCharCount`, `resetCharCountOnNewMonth` and `deleteCounterSettings`. The controller calls them from `index()`, `translatedCharCountInfo()`, `resetCharCount()`, `storeTranslatedCharCount()`, `resetCharCountOnStartMonth()` and `uninstall()`. The code no longer uses `editSettingValue`. The settings page still reads the count from the config, because the config loads the rows of every group.
- [x] Bump version + build + test
  Findings: version 04.01.0122. Build green. Tests: 24 passed, 6 skipped and 40 passed. The oc3.0 and oc4.x models use the `module_` keys, and `$counterGroup` stays `client_translate_expert_usage`. The oc1.5 and oc2.2 controllers call `model_module_client_translate_expert`.
- [x] Test in the Docker stores (oc23, oc30, oc40, oc41)
  Findings:
  - The guarded `UPDATE` works in MariaDB 10.6 strict mode: `'123'` gives 130, `''` and `'abc'` give 7.
  - `tmp/test_counter.sh`: 48 checks passed, 0 failed on all four stores (save, second save, settings page, new-month reset, same month, manual reset, missing rows).
  - `tmp/race.sh`: 10 processes x 20 `addCharCount(5)`, with 10 settings saves at the same time. The result is 1000 and one row on oc30 and oc40.
  - The old read-then-write pattern in the same setup gave 135 of 1000 on oc30. The race is real, and the test catches it.
  - No OC 1.5 store exists, so the `group` column branch is not tested.
- [x] Independent review → 24-char-count-reset-review-v2.md
  Findings: Fable 5.2 found 0 Critical, 2 Important and 5 Minor findings. The user said to fix the issue as I think best, so I decided the findings myself.
- [x] Address review findings
  Findings:
  - 1 (the `group`/`code` column on OC 2.0.0.0): fixed. `SHOW COLUMNS ... LIKE 'code'` detects the column, and the result is cached. This also removes the unverified OC 1.5 assumption.
  - 2 (a second counter row after a concurrent insert): fixed. After an insert, a `DELETE ... JOIN` keeps the row with the lowest `setting_id`. Reads use `ORDER BY setting_id`.
  - 3 (two statements in the monthly reset): fixed. One multi-table `UPDATE` changes the month row and the count row.
  - 4 (a missing month row means no reset): kept. A missing row means first use, when the count also starts at 0.
  - 5 (three queries in `addCharCount()`): skipped. The last read updates the config for the request.
  - 6 (`DOUBLE` arithmetic): fixed with `CAST(value AS UNSIGNED)`.
  - 7 (no automated test): `tmp/race.sh` now has a `norows` variant.
- [x] Bump version + build + tests (verify loop)
  Findings: version 04.01.0123. Build green. Tests: 24 passed, 6 skipped and 40 passed. Docker: `test_counter.sh` 0 failures on the four stores. `race.sh` and `race.sh norows` give 1000 and one row on oc30 and oc40.
- [x] Commit and push to PR #27
- [x] Independent review → 24-char-count-reset-review-v3.md
  Findings: Fable 5.2 says ready to merge. It found 0 Critical, 0 Important and 2 Minor findings, and it confirmed the four v2 fixes as correct on mysqli and pdo.
  - Minor 1 (a possible InnoDB deadlock in the cleanup `DELETE` on first use, not verified): skipped. `race.sh norows` on oc40 (InnoDB) gave 1000. A `try`/`catch` would only hide an error.
  - Minor 2 (the comment wording about the oldest row): skipped. No code change is necessary.
  - No more review passes. The findings dropped to theoretical points.
