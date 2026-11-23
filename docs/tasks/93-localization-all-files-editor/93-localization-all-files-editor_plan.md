# 93-localization-all-files-editor

## Settings

- Planning model: Opus
- Implementation model: Sonnet (`ote-implementer`)
- Review model: Opus
- Review findings rule: fix all findings. Skip a finding only with a verified reason, and write the reason in the review file.
- Build and tests: yes. The task changes files in `sources/`.
- Commit, push and PR: none. Do these only if the user asks later.
- Standing auto-approve: no. Ask the user to approve the plan before Phase 2.

## Context

GitHub issue #93 (https://github.com/vhoshko/opencart-translate-expert/issues/93) is the spec. The user settled all design questions. Do not open them again.

The Catalog files tab and the Admin files tab show only the files that have keys to translate. The user cannot see a fully translated file and cannot correct a translated value by hand. A hand edit of an existing key adds a duplicate line, because the save only appends. The change affects all OpenCart versions (1.5–4.x). The library, the JS and the language files are the same in each package.

Settled decisions (from the issue and the user):

1. The filter "Show only files to translate" works in the front end only. The default is off. The module does not store it.
2. A new last column "Action" holds "Translate and save file". A fully translated file has no such button.
3. If all files are translated, "Translate all" is hidden. The total row has its own state.
4. The editor for a translated key is the editor of today: the source text and a target textarea. The textarea shows the current target value.
5. The save replaces the existing statement of a key in place. It does not change expression values.

Key files (verified on 23.11.2026, version 04.01.0221):

- `sources/upload/system/library/translate_expert/translate_expert_localization.php`
  - `:248` `buildAnalizeFileItem()` builds `keys` from `toTranslate` only.
  - `:282` `calcAnalizeTotals()` counts all files in `fileCount`.
  - `:307` `analizeLocalizationFile()`. `contentTo` is the merged text after `parsePhpInclude()` (`:334`), not the disk content.
  - `:355` `parseLocalizationFile()`. For a duplicate key, the last statement wins (`$res[$key] = ...`).
  - `:461` `parseLocalizationFileByRegExp()` has no `expression` flag.
- `sources/upload/admin/view/javascript/client_translate_expert.js`
  - `:2225` `teLocBuildFile()` only appends lines.
  - `:2271` `teLocBuildTableSkeleton()`: 4 `th` (File, Keys, Chars, State). "Translate all" is in the total row.
  - `:2293` `teLocBadge()`: saved, missing, partial. No "translated" state.
  - `:2315` `teLocBuildFileRow()`: key count `file.keys.length` at `:2322`. The button and the badge share one cell.
  - `:2333` `teLocRenderSource()` marks each key in `keys`.
  - `:2368` `teLocRenderDiff()`: the textarea is empty.
  - `:2412`, `:2423`: `colspan` 4.
  - `:2443` `teLocHasKeys()`, `:2452` `teLocHasUnsaved()`, `:2541` (`teLocOnBatch`), `:2656` (`teLocTranslateAllStart`): skip files with no keys.
  - `:2461` `teLocEndRun()` shows `te_loc_no_files` if no file has keys.
  - `:2568` `teLocReadValues()`, `:2580` `teLocTranslateFile()`, `:2621` `teLocSaveFile()`.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:263` `$te_i18n` whitelist (JS `teT` keys) and `:266` `$te_text_key` whitelist (template keys).
- `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php` (`te_loc_*` at en-gb `:222-249`).
- `tests/test_client_translate_expert_localization.php:403` `testAdminAnalysis()` compares whole key items, the file fields (`:442`) and the totals (`:459`).
- `tests/js/test_client_translate_expert_js_helpers.js`: `testLocAnalysis()` (`:1082`, th text `File|Keys|Chars|State` at `:1110`), `testLocFileHelpers()` (`:1215`), `testLocTranslateSave()` (`:1250`), `testLocTranslateAll()` (`:1384`), `testLocStatic()` (`:1467`).

## Approach

The server returns all source keys of each file, in source order, with the target data of each key. The file count and character totals keep counting the missing keys only. The JS lists every file. It computes "keys to translate" from the per-key `translated` flag. `teLocBuildFile()` changes to "replace in place, then append": for each changed translated key, it replaces the last occurrence of `targetFullText` in `contentTo` by string slicing. It never uses a replacement string, because `$&` in a translation corrupts the result. Then it appends the missing keys as now. The JS builds the filter checkbox above the table, so the `.tpl` and the `.oc4.twig` do not change. The filter toggles one class on the result container. A CSS rule hides the marked rows, so the filter also applies to rows of later batches.

Design notes:

- Per-key fields: `key`, `text`, `fullText`, `expression` (source, as now), `translated` (0|1), `targetText`, `targetFullText`, `targetExpression` (0|1). If `translated` = 0, the 3 target fields are `''`, `''`, 0.
- Totals get a new field `translatedFileCount` = `fileCount` − `needToTranslateFileCount`.
- File state: `saved` > "translated" (no key with `translated` = 0) > `missing` (`targetExists` = 0) > `partial`.
- Total row state: "translated" if no file has keys to translate and is not saved. Else "partial". Refresh it after the analysis and after each save.
- Filter rows: `tr.te-loc-file` and its detail row get `te-loc-done` if the file has no key to translate at analysis time. A root group row gets `te-loc-done` while all its files have it. A save does not change the class. The checkbox is in the result block, so a new analysis resets it to off. `teLocSetBusy()` does not disable it.
- A fully translated file gets no "Translate and save file" button in the Action cell and no "Translate file" button in the editor bar. "Save file" and the per-key translate buttons stay.
- Editor: a translated literal key shows the decoded target value in the textarea: `teLocUnescape(targetText, teLocQuote(targetFullText))`. A key with `expression` = 1 or `targetExpression` = 1 shows read-only code. For a translated key with a source expression, show `targetFullText`.
- `teLocBuildFile()` rules for each key:
  - `translated` = 0 and source expression: append `fullText` (as now).
  - `translated` = 0 and literal: append the statement if the value is not blank (as now).
  - `translated` = 1 and an expression on either side: no change.
  - `translated` = 1 and literal: if the value is not blank and differs from the decoded target value, replace the last occurrence of `targetFullText` with `teLocStatement(key, value)`. A blank textarea means "no change". If `targetFullText` is not found, append the statement (PHP uses the last assignment).
  - If nothing is replaced and nothing is appended, return `null`. If only replacements occur, return the replaced content with no other change.
- `teLocTranslateFile()` and "Translate all" translate only keys with `translated` = 0. With `onlyKey`, the per-key button also translates a translated key.
- `te_loc_no_files` shows after an analysis that finds no file with keys to translate. The list then still shows all files.

## Assumptions

- ✅ verified: `contentTo` in the response is the merged text after `parsePhpInclude()`. `analizeLocalizationFile()` sets `contentTo` after the call (`translate_expert_localization.php:311-327`).
- ✅ verified: `targetFullText` is a substring of `contentTo`. Both come from the same merged text: `parseLocalizationFile()` builds `full_text` from the tokens of that text (`:420-422`).
- ✅ verified: for a duplicate key, the parser keeps the last statement (`$res[$key] = ...` overwrites). "Replace the last occurrence" then changes the effective statement.
- ✅ verified: `parseLocalizationFileByRegExp()` (`:461`) sets no `expression` flag. Without `token_get_all`, every key is a literal. The new code must read `expression` with `!empty()`.
- ✅ verified: the controller passes the library result through. `analizeLocalization()` of the controller (`:733-751`) calls the model and sends the result as JSON. No field filter exists.
- ✅ verified: the template has no localization table markup (`.tpl:303-336`, `.oc4.twig:302-334`). The JS builds the table. A JS-built filter needs no template change.
- ❓ unverified: the payload size and the render time with all keys of all files. Not measured. `contentFrom` and `contentTo` are in the response already, so the size grows by less than 2 times. If false, a large site gets a slow list. Record a measurement in Done if a large fixture is available.
- ❓ unverified: a `targetFullText` text can also occur inside a comment or inside another literal after the real statement. Not tested. If true, the save changes the wrong text. Accept this as a known limitation.
- Known limitation (existing behaviour, not changed): the save writes the merged text, so it inlines the `include(DIR_LANGUAGE ...)` lines of the target file.

## Steps

Owners: **S** = server implementer, **F** = front-end implementer. Dispatch S and F in one message.

- S files: `sources/upload/system/library/translate_expert/translate_expert_localization.php`, `tests/test_client_translate_expert_localization.php`. S does not edit the controller.
- F files: `sources/upload/admin/view/javascript/client_translate_expert.js`, `sources/upload/admin/view/stylesheet/client_translate_expert.css`, `sources/upload/admin/controller/extension/module/client_translate_expert.php` (i18n whitelist only), the 3 language files, `tests/js/test_client_translate_expert_js_helpers.js`, `CLAUDE.md` (Tests section only). F owns the whole-file round trip.
- F codes against the response shape in Approach. F does not wait for S.

Steps:

- [x] S1 Library: in `analizeLocalizationFile()`, keep `toTranslate`. Add `keys` with all source keys in source order and the target data of each key. In `buildAnalizeFileItem()`, build `keys` from this list with the 8 fields of Approach. Cast the flags to int. Keep `charCount` and the totals on `toTranslate`. Add `translatedFileCount` to `calcAnalizeTotals()`. Use PHP 5.6 syntax (`array()`, no `??`).
- [x] S2 PHP tests: update `testAdminAnalysis()`, `testCatalogAnalysis()` and the helpers `keyNames()` and `keyItem()` for the new key fields and the totals. Add these tests:
  - A fully translated file is in `files`, all its keys have `translated` = 1, and `charCount` is 0.
  - A partial file has the correct flags, `targetText`, `targetFullText` and `targetExpression` for each key.
  - A target expression gives `targetExpression` = 1. A source expression keeps `expression` = 1.
  - The key order is the source order.
  - `translatedFileCount` = `fileCount` − `needToTranslateFileCount`.
- [x] F1 JS list:
  - Append all files in `teLocOnBatch()`.
  - Add `teLocTodoCount(file)` (keys with `translated` = 0). Use it in `teLocHasKeys()`, `teLocHasUnsaved()`, `teLocTranslateAllStart()` and the key count cell.
  - Add the "Action" `th` and `td`. Move the row button to the Action cell. Hide it for a fully translated file.
  - Change `colspan` to 5.
  - Add the "translated" state to `teLocBadge()`.
  - Add the total row state cell. Put "Translate all" in the total row Action cell.
  - Show "Files translated" in `teLocRenderTotals()`.
  - Change the `te_loc_no_files` condition.
- [x] F2 JS filter: build the checkbox above the table in the result block. Toggle a class on the result container. Add the `te-loc-done` class to rows as in Approach. Do not store the state. Do not disable the checkbox in `teLocSetBusy()`.
- [x] F3 JS editor and save:
  - Prefill the textarea of a translated literal key.
  - Show read-only code for a target expression.
  - Mark only keys with `translated` = 0 in `teLocRenderSource()`.
  - Hide "Translate file" for a fully translated file.
  - Rewrite `teLocBuildFile()` with the rules of Approach.
  - Limit `teLocTranslateFile()` to keys with `translated` = 0 unless `onlyKey` is given.
- [x] F4 CSS: add the filter hide rule (`.te-loc-only-todo tr.te-loc-done { display: none; }` or the same with the project class names) and the filter label style.
- [x] F5 Language strings: add `te_loc_col_action`, `te_loc_state_translated`, `te_loc_filter_todo` ("Show only files to translate") and `te_loc_totals_translated` to en-gb, ru-ru and uk-ua. Add them to the `$te_i18n` whitelist of the controller (`:263`). The 3 key sets must stay the same.
- [x] F6 JS tests: update the `I18N` stub, the th text (`File|Keys|Chars|State|Action`) and the old `teLocBuildFile` checks. Add these tests:
  - The list shows a fully translated file with the "translated" badge and no row button.
  - "Translate all" is hidden if all files are translated. The total row state is correct.
  - The filter is off by default. It hides the done rows and their detail rows and root rows. It also hides rows of a later batch. It writes nothing to `localStorage` or `sessionStorage`. It stays enabled while busy.
  - The textarea of a translated key has the current value. An expression key has no textarea.
  - An edit of a translated key replaces the statement in place. The key occurs one time in the result.
  - A value with `$&`, `$1` and `$'` is saved literally.
  - An expression statement stays unchanged after a save.
  - A blank or unchanged textarea gives no change. With no other change, the result is `null`.
  - The replacement of a duplicate key changes the last occurrence.
  - `teLocTranslateFile()` sends only missing keys. With `onlyKey`, it sends a translated key.
  - "Translate all" skips fully translated files.
  - Round trip: if `php` is in `PATH`, write the built content to a temp file. Run `php -r '$_ = array(); include $argv[1]; echo json_encode($_);'` through `child_process.spawnSync`. Check each value. If `php` is not found, print a skip note.
- [x] F7 Docs: if a test description in the `CLAUDE.md` Tests section is no longer correct, update it (JS helper tests: the filter, the Action column, the in-place save). Write in ASD-STE100.
- [x] Templates: no change. The JS builds the filter and the table. `testLocStatic()` must still pass.
- [x] Bump version + build + tests: bump `_version.txt` (04.01.0221 → 04.01.0222). Run `./build.release.sh`, all 16 PHP test scripts and the JS tests (`ote-build-test`, full mode).
- [x] Independent review → `93-localization-all-files-editor-review.md`
- [x] Address review findings (fix all. Skip only with a verified reason, and write it in Findings)
- [x] Bump version + build + tests (verify loop)
- [x] Re-review? Review v2 is done. The user asked for a PR now. The last small fixes (hide buttons, 2 test assertions) have no separate review. The PR body says this.
- [x] Improvements pass → `93-localization-all-files-editor-improvements.md`

## Done

- Issue #93 is implemented. The Catalog and Admin files tabs list all language files, with the "translated" state, the front-end filter, the Action column and an editor for translated keys.
- The save replaces the last occurrence of a target statement in place. After a save, the saved values are the new baseline of the file.
- Review v1 found 1 Important finding (CR and CRLF values seen as edits). It is fixed. Review v2 found no Critical or Important finding and 3 Minor findings. Minor 1 and 2 are fixed. Minor 3 is skipped (two saves of one file in flight: this was possible before, low risk, the reviewer says no change is necessary).
- Final version: 04.01.0225. All 16 PHP test scripts pass. The JS helper tests pass with 817 asserts.
- Not done: payload size and render time with all keys of all files are not measured. A save still inlines the `include(DIR_LANGUAGE ...)` lines of the target.

## Update 23.11.2026 (review v1 and v2)

- Findings (review v1): Important 1 is fixed with `teLocNormalizeEol()`. Minor 2 is fixed with `teLocApplySaved()`, so it is no longer a known limit. Minor 1 (translated badge for a no-key file with a missing target) is accepted.
- Findings (review v2): after a save, the row and editor translate buttons are hidden and the key count is 0. Two test assertions cover a failed save and a save that ends after a new analysis.
- Findings (tests): old tests that expected "a second save sends the same content" now expect no request. Tests that save a file restore its analysis state, so later checks start from it.
