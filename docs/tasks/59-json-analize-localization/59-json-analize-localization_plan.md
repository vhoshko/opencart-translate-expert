# 59-json-analize-localization

## Settings

- Planning model: Fable 5.1 (`Plan` subagent).
- Review model: Fable 5.1 (the same model as the planner, user choice).
- Branch: `feat/59-json-analize-localization` from a fresh `origin/main` (`16aef02`, includes #58).
- Standing auto-approve: granted by the user. This covers the plan approval and the review findings. Decide each finding without a question and record the reason.
- End-to-end: the user asked to finish the task with no stop. After Phase 7, apply all the improvements, commit, push and create the PR. The re-review gate is decided under auto-approve: do a re-review pass if the fixes change logic.
- Build: bump the patch version and run `./build.release.sh` after the changes and in each verify loop.
- Store run: none planned. The host checkout is separate.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/59

This is step 4 of the "Next tasks" of the PoC (`docs/pocs/2-admin-design/README.md`). Task 58 (PR #64) did the same change for the "Website content" tab. This task follows its patterns.

Affected OpenCart versions: all (1.5–4.1). Every PHP file stays PHP 5.6 compatible. New JS is ES5, because OC 1.5 ships jQuery 1.7.1.

Short names: CTRL = `sources/upload/admin/controller/extension/module/client_translate_expert.php`, LOC = `sources/upload/system/library/client_localization_translate_expert.php`, CORE = `sources/upload/system/library/client_translate_expert_core.php`, JS = `sources/upload/admin/view/javascript/client_translate_expert.js`, CSS = `sources/upload/admin/view/stylesheet/client_translate_expert.css`, TPL = `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl`, TWIG = `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig`, LANG = `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php`.

Key files (line numbers from `16aef02`):

- CTRL `analizeLocalization` (630-646): no int casts, only `html_entity_decode`. A non-numeric `nextFileIndex` gives a PHP 8 TypeError in `array_slice`. CTRL `saveLocalizationFile` (753-774) checks the `modify` permission and decodes the POST values (the inverse of the OpenCart request clean, OC 3.0.3.8 `system/library/request.php:46`). Keep it.
- LOC `analizeLocalization` (17-81): checks 3004, 3003, 3001. Roots from `getLocalizationRoots` (206-241). `analizeLocalizationFile` (409-434) inlines includes with `parsePhpInclude` (436-454). `calcAnalizeTotals` (370-397) runs each batch. `ANALIZE_BULK_SIZE` = 10. HTML builders: `buildAnalizeHtml` (243-286), `buildAnalizeDetailedHtml` (288-368), `highlightToTranslate` (399-407).
- LOC `parseLocalizationFile` (457-537): one literal gives `text` (raw literal content with PHP escapes) and `full_text`. Another value gives `text` `''`, `full_text` and `expression` 1. `pathFrom` and `pathTo` are relative to the language folder. `isValidLocalizationPath` (134-143) needs this form.
- CORE `buildLanguageObject` (687-702). `getLangImgUrl` (679-685) must stay unchanged (`build.sh:348-371` sed).
- JS old localization code: 41-254, 747-797, binding in `initModule` (951-956). It uses ES2015. It re-parses the table for each batch (780-781). It adds `"\n\n<?php\n"` before `<?php` for a missing target (201-211). It escapes only `"` (221).
- JS te-block (1091-2213): `teT`, `teEl`, `teText`, `teBs`, `teAlerts`, `teFormatInt`, `teInitCollapse`, `teProgress` (1555-1631), the site tab patterns.
- Templates: TPL 260-293 and TWIG 258-291 hold the 2 old panes. `js_const_*` lines at TPL 395-397 and TWIG 388-390 (CTRL 150-152). CTRL 133-134 set `entry_localization_analization(_result)`.
- CSS: old rules 33-57. `te-diff` rules 426-455 exist and are unused.
- Tests: `tests/test_client_translate_expert_localization.php` (8 OC versions, `analysisRows` 370-377, `detailsHtml` 411, 445, 513, `html` 524). `tests/js/test_client_translate_expert_js_helpers.js` (`siteHtml` 400-425, `stubAjax` 439-449, old localization checks 890-894, 961-962).
- `build.sh`: routes `analizeLocalization`, `saveLocalizationFile` and `translate` are in the rewrite list (385-386, 391). LOC is in the library list (207). No change needed.
- `_version.txt`: 04.01.0185.

## Approach

Change LOC to return `files[]` with `keys[]`, `totals` (batch 0 only) and the 2 language objects, and remove the 3 HTML builders. Cast and check the request values in CTRL. Make the 2 localization tabs with the `te-*` layer: a card with the totals and the toolbar, an inline `te-progress`, a file table with state badges, OC 4.x root headings, and an expandable `te-diff` key list with a source text, a target textarea or a read-only PHP statement, and a collapsed source file view with a text-node highlight. Keep one state object for each scope. Build the file to save in JS from `contentTo` and the textarea values, with single-quoted PHP literals. Make "Translate all" a `teProgress` loop with a working Stop. Remove the old JS, the modal use of the localization flow, the dead CSS and the dead language keys. Change the PHP and JS tests, and add tests for each loop branch.

### Response of `analizeLocalization` (LOC)

```json
{
  "success": 1,
  "done": 0,
  "nextFileIndex": 10,
  "totals": { "fileCount": 212, "needToTranslateFileCount": 37, "needToTranslateValueCount": 410, "needToTranslateCharCount": 18250 },
  "languageFrom": { "language_id": 1, "code": "en-gb", "name": "English", "image": "language/en-gb/en-gb.png" },
  "languageTo":   { "language_id": 2, "code": "uk-ua", "name": "Українська", "image": "language/uk-ua/uk-ua.png" },
  "files": [
    {
      "root": "ext:opencart",
      "rootName": "opencart",
      "pathFrom": "/payment/cod.php",
      "pathTo": "/payment/cod.php",
      "targetExists": 0,
      "keys": [
        { "key": "text_title", "text": "Cash On Delivery", "fullText": "$_['text_title'] = 'Cash On Delivery';", "expression": 0 }
      ],
      "contentFrom": "<?php\n$_['text_title'] = 'Cash On Delivery';\n",
      "contentTo": ""
    }
  ]
}
```

Rules:

1. `success`, `done`, `targetExists` and `expression` are `0` or `1`. `nextFileIndex` and the 4 totals are ints.
2. `totals` is in the response only when the request `nextFileIndex` is 0. `calcAnalizeTotals` does not run for later batches. The field is absent, not `null`.
3. `files` holds the slice `nextFileIndex .. nextFileIndex + 10` of all files: core root first, then the `ext:` roots, each root in `scandir` order. A `nextFileIndex` at or over the file count gives `files: []` and `done: 1`.
4. `keys[]` keeps the statement order. `text` is the raw literal content (with PHP escapes). `fullText` is the statement source.
5. `languageFrom` and `languageTo` come from `buildLanguageObject`. A file item has no language fields.
6. `pathFrom` and `pathTo` stay relative to the language folder.
7. Errors keep `{ success: 0, error_code, message, additional_message }`. New: `error_code` 3002 when `getLanguage` gives an empty row for one of the 2 ids (message `error_bad_filter`).
8. `contentFrom` and `contentTo` stay include-inlined, as now.

### CTRL `analizeLocalization`

Cast `langIdFrom`, `langIdTo` and `nextFileIndex` to int. If a language id is not positive, return `badFilterResponse()`. If `nextFileIndex < 0`, use 0. Write the int values to the session.

### Templates

Replace each old tab pane with one `.te-app` root (BS3 `btn-default` on TPL, BS5 `btn-outline-secondary` on TWIG):

```html
<div class="tab-pane" id="tab-localization">
  <div class="te-app te-loc-app" id="te-loc-app-catalog" data-scope="catalog">
    <div class="te-alerts" id="te-loc-alerts-catalog"></div>
    <div class="te-card">
      <div class="te-card__head">{te_loc_card_files}</div>
      <div class="te-card__body">
        <div class="te-loc-totals te-hidden" id="te-loc-totals-catalog"></div>
        <div class="te-toolbar">
          <button type="button" id="te-begin-localization-analization" class="btn btn-primary te-js-loc-analyze">{te_loc_analyze}</button>
          <span class="te-toolbar__spacer"></span>
          <button type="button" id="te-loc-translate-all-catalog" class="btn btn-default te-js-loc-translate-all te-hidden">{te_loc_translate_all}</button>
        </div>
      </div>
    </div>
    <div class="te-progress te-hidden" id="te-loc-progress-catalog" role="status" aria-live="polite"></div>
    <div class="te-loc-result" id="te-loc-result-catalog"></div>
  </div>
</div>
```

The admin pane uses `admin` in each id and `data-scope="admin"`. Its Analyze button keeps the id `te-begin-admin-localization-analization`. Remove the 3 `js_const_*` lines from both script blocks.

### JS (ES5, in the te-block, after the site tab code)

- `teLocIds(scope)`: `app`, `alerts`, `totals`, `progress`, `result`, `analyze`, `translateAll` selectors.
- `teLocScope(el)`: `data-scope` of the closest `.te-loc-app`.
- `teLocNewState(scope)`: `{ scope, langIdFrom, langIdTo, languageFrom, languageTo, files: [], totals, nextFileIndex: 0, requestNumber: 0, running: false, mode: null, stopRequested: false, queue: [], queueTotal: 0 }`. `files[i]` is the server item plus `saved`. The index is the server index. The language ids are frozen at the start of the analysis.

Analysis loop:

- `teLocStartAnalysis(scope)`: if running, return. Clear the alerts. New state with the langbar values. New table skeleton. Hide the totals and "Translate all". Busy on. `teProgress.start`. `teLocRequestAnalysis(scope)`.
- `teLocRequestAnalysis(scope)`: `$.get` of the `analizeLocalization` route (the route string form that `build.sh:385` rewrites) with `{ langIdFrom, langIdTo, nextFileIndex, scope }`.
- `teLocOnBatch(scope, data)`: if not success → `teLocFail`. On the first batch keep the languages and the totals and render the totals. Append each file with keys. Update the progress. Branches in this order:
  1. `data.done` → `teLocEndRun(scope, 'done')`.
  2. A `nextFileIndex` that does not grow → `teLocEndRun(scope, 'done')`.
  3. `stopRequested` → `teLocEndRun(scope, 'stopped')`.
  4. Else request the next batch.
- `teLocEndRun(scope, result)`: finish the progress, busy off. Show "Translate all" if a file has keys. If no file has keys and the result is `done`, show `te_loc_no_files`. After a translation run, call `showTranslatedCharCount()`.
- `teLocFail(scope, response)`: `teAlerts.fromResponse` and `teLocEndRun(scope, 'error')`. The rows stay.

Rendering:

- `teLocRenderTotals`, `teLocBuildTableSkeleton`, `teLocAppendFile` (root group row when the root changes and `rootName` is not empty), `teLocBuildFileRow` (expand button, label, key count, badge), `teLocFileLabel` (`pathFrom`, or `pathFrom → pathTo`), `teLocBadge` (`saved` ok, `targetExists` 0 missing, else partial), `teLocRefreshState`, `teLocBuildDetailRow`, `teLocRenderDiff` (head with the 2 codes, per key the source text and a textarea, or a read-only `fullText` with a muted badge for an expression, a foot with the "Source file" collapse and the 2 buttons, a hidden `pre.te-loc-source`).
- `teLocRenderSource(content, keys)`: a document fragment of text nodes and `mark.te-loc-hl` elements. Walk the keys in order with `indexOf` from the current position. Skip a statement that is not found. No HTML string.

File building helpers (pure functions):

- `teLocQuote(fullText)`: the quote character of the literal. Default `'`.
- `teLocUnescape(text, quote)`: single pass over the PHP escapes of the quote type.
- `teLocEscape(value)`: `\` → `\\`, then `'` → `\'`.
- `teLocStatement(key, value)`: `$_['key'] = '<escaped>';`.
- `teLocDecodeEntities(s)`: decode the HTML entities that Google returns with `format: 'html'`.
- `teLocReadValues(scope, index)`: non-empty textarea values by key.
- `teLocBuildFile(file, values)`: lines in key order (expression → `fullText`, value → statement). No lines → `null`. A new file starts with `<?php\n` with no blank line before it. An existing file with an open `<?php` gets `\n\n` and the lines. A file that ends after `?>` gets `\n\n<?php\n`. The build always starts from the server `contentTo`, so a second save does not duplicate statements. The output ends with one `\n`.

Translate and save:

- `teLocTranslateFile(scope, index, done, fail)`: send the unescaped trimmed literals (no expression, no empty) to `/translate` with the frozen languages and `format: 'html'`. Put the decoded texts into the textareas. Call `showTranslatedCharCount()`. An expression-only file sends no request.
- `teLocSaveFile(scope, index, done, fail)`: `teLocBuildFile`. If `null`, show `te_loc_nothing_to_save` and call `done(false)`. Else POST `saveLocalizationFile` with `{ fileContent, langId, path: pathTo, scope, root }`. On success mark the file `saved`.
- Single buttons disable themselves while their request runs.
- `teLocTranslateAllStart(scope)` and `teLocTranslateAllNext(scope)`: a queue of the files with keys, confirm, translate then save per file, progress title and counters, a log line per saved file. Stop works between files.
- `teLocInit()`: delegated handlers on `.te-loc-app` with the `.teLoc` namespace. Called from `initModule` in place of the old binding.

Remove from the JS: `clickTranslateExpertLocalizationHeader`, `translateExpertLocalizationIds`, `getTranslateExpertLocalizationScope`, `getTranslateExpertLocalizationResults`, `doSaveLocalizationExpert`, `doTranslateLocalizationExpert`, `doTranslateLocalizationAllExpert`, `doTranslateLocalization`, `makeLocalizationAnalizeRequest`, `showTranslateExpertInfoMessage` (no caller left), and the old binding. Keep `showTranslateExpertErrorMessage` and `confirmStopTranslationModelProcess` (other flows use them).

### CSS

Remove the old rules 33-57. Add under `.te-app`: `.te-loc-totals`, `.te-diff__head`, `textarea.te-diff__target`, `.te-diff__source--code`, `.te-diff__target--code`, `.te-diff__foot`, `.te-loc-source`, `.te-loc-hl`, `.te-table__detail > td`, `.te-loc-root td`.

### Language keys (3 files, same keys)

- New `$data` keys: `te_loc_card_files`, `te_loc_analyze`, `te_loc_translate_all`.
- New `te_i18n` keys: `te_loc_analyzing`, `te_loc_translating`, `te_loc_cnt_files`, `te_loc_totals_files`, `te_loc_totals_to_translate`, `te_loc_totals_values`, `te_loc_totals_chars`, `te_loc_col_file`, `te_loc_col_keys`, `te_loc_col_state`, `te_loc_state_missing`, `te_loc_state_partial`, `te_loc_state_saved`, `te_loc_toggle`, `te_loc_root`, `te_loc_expression`, `te_loc_source_file`, `te_loc_translate_file`, `te_loc_save_file`, `te_loc_saved`, `te_loc_nothing_to_save`, `te_loc_no_files`, `te_loc_translate_all_confirm`.
- Change `tab_localization` to "Catalog files" and `tab_admin_localization` to "Admin files".
- Remove after a `grep -rnw` check: `entry_localization_analization`, `entry_localization_analization_result`, `entry_localization_file_to_translate_info`, `entry_localization_file_to_translate_info_full_translated`, `entry_localization_file_expression_info`, `entry_localization_file_target_missing`, `entry_fileCount`, `entry_needToTranslateFileCount`, `entry_needToTranslateValueCount`, `entry_needToTranslateCharCount`, `entry_TranslateAll`, `js_const_saved`, `js_const_localization_translate_progress`, `js_const_localization_translate_done`, `button_localization_translate`, `button_localization_save`.

### Design corrections (compared to the PoC and the issue)

1. `languageFrom` and `languageTo` stay in the response. The issue text omits them, but the front end needs them.
2. `pathFrom` and `pathTo` are relative to the language folder, not the full path of the PoC example. The save needs this form.
3. `targetExists` and `expression` are `0` or `1` (rule 3.0.4).
4. `totals` is absent for `nextFileIndex > 0`, and `calcAnalizeTotals` runs only for batch 0.
5. The file items have no per-file language rows.
6. CTRL casts the 3 ints and rejects a non-positive language id. LOC returns 3002 for an unknown language id.
7. The key list is the main view. The source file shows in a collapsed "Source file" block with a text-node highlight. This satisfies issue item 4 and keeps design section 8.
8. The table shows only files with at least one key. `fileCount` still counts all files.
9. The saved file is built in JS with single-quoted PHP literals. A new file starts with `<?php\n`. The Google text is entity-decoded in JS.
10. "Translate all" runs in the `te-progress` panel of its tab, one run for each tab, with a working Stop. It shows after the analysis is done.
11. The card has no "Source folder" line, because the server does not send folder paths.
12. "Translate file" and "Save file" are in the expanded key list.
13. The localization flow does not use the 3 old alert functions.

### Out of scope

- The server rescans all files for each batch.
- `contentFrom` and `contentTo` stay include-inlined.
- The `translate` endpoint decodes each text 2 times (CTRL 422, 439).
- `showTranslateExpertErrorMessage` still inserts HTML for the removal flow and `doTranslateExpert` (PoC step 5 or 6).
- GET to POST, `hasPermission` on `analizeLocalization` and the model re-throw (PoC step 6).

## Assumptions

- ✅ `buildLanguageObject` works in the localization test harness. Evidence: CORE reads `getLanguages()`, and `FakeLocalisationLanguageModel::getLanguages` (test 175-178) returns the languages by id.
- ✅ `scandir` gives an alphabetical order, so the file order is stable between batches. Evidence: CORE 524-536, `getLocalizationRoots` sorts the extension names (LOC 225).
- ✅ The CTRL `html_entity_decode` of `fileContent` must stay. Evidence: OC 3.0.3.8 `system/library/request.php:46`.
- ✅ `html_entity_decode` with `ENT_COMPAT` (PHP < 8.1 default) leaves `&#39;` as it is. Evidence: a local `php -r` probe. So the Google entities are decoded in JS.
- ✅ The `.te-js-stop` handlers of the tabs do not clash. Evidence: `teSiteInit` binds on `#te-site-app` (JS 2178).
- ✅ No code outside LOC, CTRL, JS, the templates and the localization test reads `analize`, `html`, `detailsHtml` or the removed keys. Evidence: `grep -rnw` over `sources`, `tests` and `build.sh`.
- ✅ `build.sh` needs no change. Evidence: rewrite list 385-386, 391, library list 207.
- ❓ The look on real OC 1.5, 2.3, 3.0 and 4.x pages. jsdom checks the DOM only. No store run is planned. Every new CSS rule must be under `.te-app`.
- ❓ Google returns `&#39;` and `&quot;` with `format: 'html'`. The JS decode is safe in both cases.

## Steps

- [x] Code PHP (LOC): new response shape with `buildAnalizeFileItem`, totals for batch 0 only, int casts, 3002 check, language objects. Remove the 3 HTML builders and the old fields.
  Findings: `calcAnalizeTotals` still reads the raw `toTranslate` entries before the file items are built. The counts do not change. The 3002 check runs after 3004 and 3003 and before 3001. Verified with a grep.
- [x] Code PHP (CTRL): int casts, `badFilterResponse()`, session ints, `te_loc_*` keys in the 2 lists, remove `$data` lines 133-134 and 150-152.
  Findings: verified with a read of the method.
- [x] Tests PHP: change `analysisRows`, `testAdminAnalysis`, `testCatalogAnalysis`, `testConcatenatedValueWithoutTarget`, `testOc4SourceFromLanguageExtension`. Add `testBatching` and `testAnalysisErrors`. Remove the `FakeLanguage` special case.
  Findings: 1021 passed, 0 failed over 8 OC versions. `testBatching` also checks a negative index. `testAnalysisErrors` checks 3002 for the source and for the target id.
- [x] Templates: the 2 new panes in TPL and TWIG. Remove the 3 `js_const_*` lines.
- [x] Code JS: the `teLoc*` functions in ES5. Remove the old functions and binding.
  Findings: `teLocDecodeEntities` sets `innerHTML` of a detached textarea and not jQuery `.html()`, because `.html()` runs a `<script` in the Google text. A test checks this. The save callback updates the badge only if the state object of the scope did not change. The core root id is `'core'`, so core files get no group row.
- [x] CSS: remove 33-57, add the new rules under `.te-app`.
- [x] Language strings: en-gb, ru-ru, uk-ua.
  Findings: `grep -rnw` shows no user of the 16 removed keys. The only `js_const_saved` hit is a negative check in the JS test.
- [x] Tests JS (543 passed with jQuery 3.7.1 and 1.7.1; a mutation of the highlight class and of the growth check gave 6 failures): `testLocAnalysis` (one test per loop branch: done, continue, stop, success 0, fail, non-JSON, no growth; root groups; renamed file; scope isolation), `testLocDiff`, `testLocFileHelpers`, `testLocTranslateSave`, `testLocTranslateAll` (done, stop, save error, expression-only), `testLocBs5`, static template and ES5 checks.
- [x] Docs: PoC `endpoints-json.md` 3.4, `README.md` step 4, `design-concept.md` section 8 status notes. `CLAUDE.md` test descriptions.
- [x] Bump version + build + all unit tests. Check the route rewrites in the OC 1.5, 2.2, 3.0 and 4.x packages.
  Findings: 04.01.0186. PHP 1535 passed, JS 543 passed, 0 failed. The routes are correct in the oc1.5, oc2.2, oc3.0 and oc4.x packages. No `=>` in the packaged JS.
- [x] Independent review (Fable 5.1) → `59-json-analize-localization-review.md`.
  Findings: 0 Critical, 3 Important, 5 Minor. The first run stopped at the usage limit. It continued after the reset.
- [x] Address the review findings (auto-approve). Decisions:
  - 1 (entities in the source decoded): fix. A real trigger in OC core files.
  - 2 (`?>` in a literal breaks the saved file): fix. A broken live store page is possible.
  - 3 ("Translate all" sends saved files again): fix. It costs Google characters.
  - 4 (save handler reads the new state): fix. Cheap.
  - 5 (per-file buttons enabled during the analysis): fix. Cheap.
  - 6 (rare PHP escapes in double-quoted values): fix. A small escape table with tests.
  - 7 (char total counts the escaped literal): skip. The bug is older than this task. The total is a small overestimate, and a fix needs the quote type on the server.
  - 8a (empty path in the first progress title): fix. 8b (`checkResponseShape` in the catalog test): fix. 8c (highlight of a repeated statement): skip. The effect is only the highlight, and the case is rare.
  Findings: JS 569 passed, PHP localization 1077 passed. The review probe passes 20 of 20 cases. A mutation of fix 1 and of fix 2 each made 2 new tests fail on the expected assertion. The JS was restored from a byte copy and checked with `cmp`. Fix 1 uses the rule `/&[#\w]+;/` and not the 4 fixed entities of CORE, so `&nbsp;` also keeps the text verbatim. Fix 8a also adds `te_loc_translate_all` to the `te_i18n` list in CTRL.
- [x] Verify loop: bump + build + tests.
  Findings: 04.01.0187. PHP localization 1077, JS 569, 0 failed. The route checks pass in all packages.
- [x] Re-review? Yes, under auto-approve, because the fixes change the file build and the "Translate all" queue.
- [x] Independent review v2 (Fable 5.1, fix pass only) → `59-json-analize-localization-review-v2.md`.
  Findings: no Critical, no Important. The 8 fixes close their findings. A revert probe made the tests fail for fixes 1-6. Minor decisions under auto-approve:
  - Minor 1 (`\xHH` and octal byte escapes above 0x7F become code points, not UTF-8 bytes): skip. The case is rare, the result is not worse than before the fix, and a correct decode adds a new code path.
  - Minor 2 (no test for the start title of "Translate all"): skip. The title shows for a moment only.
  - Minors 3 and 4: the reviewer marked them as no action.
  The passes find no correctness issue now, so the review loop stops.
- [x] Improvements pass → `59-json-analize-localization-improvements.md`. Apply the improvements.
  Findings: 4 edits applied: the `general-purpose` planner in `dev-workflow` Phase 0, Phase 1 and the dispatch table, the parallel implementers in Phase 2, the round-trip probe in the Phase 4 brief, and the HTTP 429 row in **Known Environment Gotchas**.
- [x] Commit, push, PR.
  Findings: commits b519a24 (feature) and 74ffd5e (workflow). PR #65.

## Done

- `analizeLocalization` returns JSON `files[]`, `keys[]`, `totals` (batch 0 only) and the 2 language objects. The 3 HTML builders are removed. CTRL casts the request values, and LOC returns 3002 for an unknown language id.
- The "Catalog files" and "Admin files" tabs use the `te-*` design: a totals card, `te-progress` with a working Stop, a file table with badges and root groups, a `te-diff` key list and a source file view with a text-node highlight. JS builds the saved file with single-quoted literals.
- Final version: 04.01.0187. PHP tests 1535+ and JS tests 569 pass. A round-trip probe of 20 cases passes.
- Gotchas: Google keeps input entities with `format: 'html'`, so the JS decodes only when the source has no entity. A `?>` inside a literal is not a close tag.
- Deferred: char total of escaped literals (finding 7), UTF-8 byte escapes (v2 Minor 1), the HTML alert function of the other flows (PoC step 5 or 6).
