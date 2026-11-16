# 58-json-analize-website-content-tab

## Settings

- Planning model: Opus 5.5 (inline, in the main conversation).
- Review model: Fable 5.1 (pass `model: fable` to `ote-code-reviewer`).
- Flow: full flow. The task changes PHP, JS, CSS, both templates, the language files and `build.sh`.
- Build: bump the patch version and run `./build.release.sh` in Phase 3 and in each verify loop.
- Tests: all unit tests. Add PHP tests for `analize` and `analizeTableDetail`. Add JS tests for the new renderers, the state object and the loops.
- Store run: yes. WIP commit, push and PR after the first green build (the user said so on 2026-11-16). The PR body says that the review is not done. Push the review fixes to the same branch.
- Review findings: standing auto-approve (the user said so on 2026-11-16). Decide each finding without a question to the user and report the reason. Prefer to fix. Go back to the user for a change of approach, an external system or a scope expansion.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/58

This is step 3 of the "Next tasks" of the PoC (`docs/pocs/2-admin-design/README.md`). Task 56 (PR #62) added the `te-*` CSS layer and the JS helpers (`teT`, `teEl`, `teIcon`, `teBs`, `teAlerts`, `teFormatInt`). Task 57 (PR #63) changed `translatedCharCountInfo` and `removeDataFromDb` to JSON, and added the `.te-app` root with `te-summary`. Both are in `main`.

Affected OpenCart versions: all (1.5–4.1).

Key files (from the investigation, checked with reads and greps):

- CTRL `analizeTableDetail` (CTRL:545-567, POST) and `analize` (CTRL:569-606, GET). `langIdFrom`, `langIdTo` and `mode` get only `html_entity_decode`. They go into the SQL raw (LIB:828-829). `translateTable` has the same problem.
- CORE `analizeTableDetail` (CORE:46-143). It builds a BS3 grid. It takes the ID columns as the first `count($row) - 7` keys (CORE:116). The row has 8 fields after the PK columns on OC 1.5–3.0 (LIB:853-862), so `language_id_from` also shows as an ID. On OC 4 `build.sh:341-346` removes `language_image_to`, so the rule is correct only there.
- The detail rows come from `getTextToTranslateRows()` (LIB:790-890). The GROUP BY is `t2.language_id, t.<col>` (LIB:833). So each row is one distinct source text, and `value_count` is the number of rows. The view shows the first 100 rows (`TRANSLATION_ANALITIC_SQL_LIMIT`, CORE:7).
- CORE `analize` (CORE:145-283). It returns `done` (bool), `globalCharCount`, `globalLanguageCharCounts`, `analizeHeadInfo`, `result[]` with `headerHTML` and `html`, `tables` (not used), `timeInSec` (a string from `number_format`, LIB:766), `timeInSecInfo` and `lastProcessed*`. With a table filter, LIB:723 also returns tables with 0 characters and tables that are not supported.
- HTML builders: `buildHtmlTranslateTableButton` (CORE:496-531, inline `onclick`), `buildHtmlToLanguageImage` (CORE:463). `removeDataFromDb` (CORE:346-353) builds the language object of section 3.0, rule 7 inline.
- JS: `makeAnalizeRequest` (JS:907-1063) re-parses `#analizeBody` for each batch (JS:1015), reads 8 counters back with `parseInt(.html())`, and its `.fail()` clears the result (JS:1058-1062). The analyze button is not enabled again after an error. `analizeTableDetail` (JS:730-757). `doTranslateTableExpert` and `doTranslateTableExpertRecursive` (JS:760-839) use `document.js_const_statistic_html`, read the count from the DOM (JS:801) and take "translate all" from `$('.et_table')` in the server HTML.
- The same filter ids are read 3 times: `makeAnalizeRequest` (JS:916-925), `makeRemovingDataFromDbRequest` (JS:1069-1075) and the cron link (JS:1325, 1357-1382).
- `showTranslateExpertErrorMessage` (JS:418-453) uses `#te-analization-mode` as the fallback anchor when the caller gives no element. 9 callers depend on it, also `resetTranslationCache` on the Debug tab.
- Templates: `#tab-translate-site` is TPL:128-243 and TWIG:128-239. Each of the 3 translation tabs has its own language pair: `te-analization-language-*`, `te-localization-language-*` and `te-admin-localization-language-*`. All 3 use the session keys `te_langIdFrom` and `te_langIdTo`. The localization JS reads its pair only through `translateExpertLocalizationIds` (JS:57-70). The `.te-app` root (TPL:22-29, TWIG:22-29) does not wrap the tabs or the modal.
- CSS (task 56): `te-langbar`, `te-card`, `te-form`, `te-field`, `te-toolbar`, `te-progress`, `te-table` (with `__group`, `__expand`, `__detail`), `te-badge`, `te-alerts` exist. A total row, a radio group and a progress done or stopped state do not exist.
- `te_i18n` key list: CTRL:209-221.
- `build.sh`: the OC4 JS route rewrites for `analizeTableDetail`, `translateTable` and `analize` exist (build.sh:384-392). `build.sh:405` rewrites the `{$server}view/image/...` text of the builder. It is then a no-op.
- Tests: no test calls `analize` or `analizeTableDetail` now. `tests/test_client_translate_expert_translate_loop.php` has the stub harness (`FakeDb`, `RemoveLibrary`) and a test of `buildHtmlTranslateTableButton` (lines 171-191, 441, 1149-1160). `tests/js/test_client_translate_expert_js_helpers.js` loads the real JS in jsdom with jQuery 3.7.1 and 1.7.1.

## Approach

**Validation (CTRL).** Cast `langIdFrom` and `langIdTo` to int in `analize`, `analizeTableDetail` and `translateTable`. Accept `mode` only if it is `both`, `only_empty` or `same_value`. Else return `badFilterResponse()`. This closes an SQL injection (admin and token only) in the touched code.

**Language object (CORE).** Add a helper `buildLanguageObject($languageId)`. It returns `language_id` (int), `code`, `name` (decoded) and `image` (`getLangImgUrl`), or `null` if the store does not have the language. `removeDataFromDb` uses it too. Keep `getLangImgUrl` short and alone, because `build.sh:348-371` replaces it on OC 4.

**`analizeTableDetail` (CORE).** Return:

```json
{ "success": 1, "table": "oc_product_description", "column": "description", "mode": "both",
  "languageFrom": {...}, "languageTo": {...}, "idColumns": ["product_id"],
  "rows": [ { "ids": { "product_id": 42 }, "textFrom": "...", "textTo": "", "count": 3 } ],
  "hasMore": 0 }
```

- Check `tableName` against `getTables()` and `textColumnName` against `textColumnNames`. Check that both languages exist. Else return `success: 0` and a message.
- Make the language objects from the request parameters, so that zero rows also work.
- `idColumns` is `pkColumnNames` (without `language_id`), not `count($row) - 7`.
- Read `limit + 1` rows. `hasMore` is 1 if there are more than 100.
- `textFrom` and `textTo` are `html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8')`. `text_to` can be NULL.
- `count` is `value_count`, because one row stands for all rows with the same source text.

**`analize` (CORE).** Return:

```json
{ "success": 1, "done": 0, "target": {...}, "globalCharCount": 645210, "timeInSec": 12.4,
  "tables": [ { "table": "oc_product_description", "supported": 1, "charCount": 612000,
                "columns": [ { "column": "name", "charCount": 12000 } ] } ],
  "lastProcessedTable": "...", "lastProcessedColumn": "...", "lastProcessedOffset": 0 }
```

- `done` is 0 or 1. `timeInSec` is a float. Counts are ints.
- `supported` is a new field. The front end then does not show "Translate" for a table that the module cannot translate.
- Remove `analizeHeadInfo`, `headerHTML`, `html`, `timeInSecInfo`, `globalLanguageCharCounts`, `result` and the old `tables`.

**Remove the HTML builders.** Remove `buildHtmlTranslateTableButton`, `buildHtmlToLanguageImage` and their test. Remove the `build.sh:405` rewrite, because nothing matches it then.

**Page frame.** Add one `te-langbar` in its own `.te-app` wrapper, under the tab row in both templates. It has `#te-langbar-from`, a swap button and `#te-langbar-to`. The JS shows it only on `#tab-translate-site`, `#tab-localization` and `#tab-admin-localization`. Remove the 3 old language pairs. `translateExpertLocalizationIds.catalog` and `.admin` point to the langbar ids. The default stays: the session value, else language 0 as the source and language 1 as the target.

**Tab "Website content".** Make the tab in its own `.te-app` wrapper, with the layout of `design-concept.md`, section 7:

1. `te-card` "What to translate": the mode as 3 radios (`name="te-analization-mode"`), the tables multi-select with the current All and None buttons, the deep analysis checkbox and a collapsed "More filters" block with the 5 product filters. The toolbar has "Analyze" left and "Remove translations" right, with the danger style.
2. `te-card` "Cron link", collapsed, with the current `#te-cron-link-*` ids.
3. `te-alerts` area `#te-site-alerts`.
4. `te-progress` panel `#te-site-progress` (`role="status"`, `aria-live="polite"`), hidden until an operation starts.
5. Result area `#te-analization-result` with the `te-table`.

**JS.** Write new code in ES5, in the te-block. The functions:

- `teSiteFilters()` reads the filters once. `makeAnalizeRequest`, `makeRemovingDataFromDbRequest` and the cron link use it. It sends `product_status` and `product_quantity` as `1` only if checked, because CTRL uses `boolval()`.
- `teProgress` helper: `start(el, title)`, `update(el, counters)`, `log(el, line)`, `finish(el, state)`. One stop button with the class `te-js-stop`.
- Analysis loop: `teSiteState` keeps the filters, `target`, `tables` (keyed by table name), `globalCharCount`, `timeInSec`, `requestNumber`, `running` and `stopRequested`. Each batch adds its counts to the state. A table that comes again (deep analysis) gets its counts added, and its row is updated in place. New rows go in with `append()`. The progress shows the current table, "table N of M", the request number, the characters and the time. M is the number of selected tables, or of all options if nothing is selected. The stop flag stops the next request.
- Result table: one group row for each table (expand and collapse), one row for each column with "Details" and "Translate", and a total row with "Translate all". Handlers are delegated `te-js-*` handlers with `data-table` and `data-column`. Rows of a table with `supported` = 0 have no "Translate" button. A 0 count has no "Details".
- Detail view: the modal shows a `te-table` from `analizeTableDetail`, in a `.te-app` wrapper. All values go in with `.text()`. The rows show the IDs, the source, the target, a `te-badge` "identical" or "empty" and the count when it is more than 1. If `hasMore` is 1, a line says that only the first 100 values show.
- Translation loop: the same `te-progress` panel. A state object keeps `translatedCharCount` and the table queue. "Translate all" takes the tables of `teSiteState` with `supported` = 1 and `charCount` > 0. When a column or a table is done, its row shows a `te-badge--ok`. After the loop, `showTranslatedCharCount()` runs as now.
- Errors: `teAlerts.fromResponse('#te-site-alerts', ...)`. A `.fail()` handler does not clear the results. The start buttons are enabled again after each end (done, stop, error).
- `showTranslateExpertErrorMessage` and `showTranslateExpertInfoMessage` use `#te-site-alerts` as the fallback anchor, because `#te-analization-mode` becomes radios.
- Remove `analizeTableDetail` (old), `doTranslateTableExpert`, `doTranslateTableExpertRecursive`, `getTranslateExpertStatisticHtml`, `document.tablesToTranslate` and the `stopTranslationModelProcess` use of these flows.

**Modal.** The analysis and the table translation do not use the modal. The removal and the localization "translate all" keep the modal as now. The detail view uses it with a title from `te_i18n`.

**Language keys.** Add `te_site_*` keys for the labels, the progress texts, the badges and the confirmations to the 3 language files and to the `te_i18n` list. Remove the keys that only the removed code uses: `js_const_statistic_html`, `js_const_in_progress_status`, `js_const_done_status`, `js_const_analization_process_is_started`, `js_const_preparing_detail_info`, `entry_time_in_sec`, `message_analize_global_info`, `message_analize_table_head_info`, `message_analize_field_head_info`, `entry_name`, `entry_summ`, `entity_table`, `entity_column`, `title_translate_all_tables`, `title_translate_table`, `title_translate_table_column`, `title_translate_table_confirmation`, `title_translate_to_lang_suffix` and `title_not_translater_char_count`. Remove the old language pair labels if no template uses them. Before each removal, grep `sources/` and `tests/` for the key.

**Docs.** Add "Status: done in issue 58" to `endpoints-json.md`, sections 3.1 and 3.2, and to the README step 3. Record the corrections of the spec there (`idColumns`, `count`, `hasMore`, `supported`, prefixed table names).

**Design corrections (compared to the PoC).**

1. `idColumns` comes from `pkColumnNames`. The `count($row) - 7` rule is wrong on OC 1.5–3.0.
2. The detail rows are distinct source texts. Add `count` and `hasMore`.
3. Add `supported` to each table, because a table filter returns tables that the module cannot translate.
4. Table names have the `DB_PREFIX`. The spec examples do not.
5. The mode radios break 3 readers of `#te-analization-mode`. The shared `teSiteFilters()` and the new fallback anchor fix this.
6. The `te-langbar` replaces all 3 language pairs in this task, because the issue puts it under the tab row. The rest of the localization tabs stays for PoC step 4.

**Out of scope (recorded, not changed):**

- `analizeLocalization` and the localization tab layout (PoC step 4).
- Non-deep mode skips the other columns of a table that the time limit cuts. This is the current behavior. The help text of the deep checkbox already says this.
- The stop button stops only the next request. The server finishes the current batch.
- Analyze works only when the module is enabled (`initTranslateExpert`, JS:12-30). This is the current behavior.
- GET to POST, `hasPermission` and the model re-throw (PoC step 6).
- `showTranslateExpertModal` calls `teBs.modal` (task 56 follow-up). The detail view uses `teBs.modal` directly.

## Assumptions

- ✅ The detail rows are grouped by source text. Evidence: LIB:833 `$groupBy = ... "t2.language_id, t.{$textColumnName}"` when `text_from_filter` is null, and `analizeTableDetail` passes no filter.
- ✅ OC 1.5–3.0 rows have 8 fields after the PK columns, OC 4 has 7. Evidence: LIB:853-862 and `build.sh:341-346`.
- ✅ `pkColumnNames` has the ID columns without `language_id`. Evidence: the investigation (LIB:556). I check it again in Phase 2 with a read.
- ✅ The browser loads the new JS with the new package (`?v=<version>`, `endpoints-json.md`, section 5).
- ✅ No code outside CTRL, CORE, JS and the translate loop test reads the removed fields and builders. Evidence: the investigation grep over `sources/`, `tests/` and `tools/`.
- ❓ The new tab, the `te-langbar` and the modal detail view render correctly on real OC 1.5, 2.3, 3.0 and 4.x pages. jsdom checks the DOM, not the look. The store run after the WIP push checks it.

## Steps

- [x] Code PHP: CTRL validation (int casts, mode whitelist) in `analize`, `analizeTableDetail`, `translateTable`
- [x] Code PHP: CORE `buildLanguageObject`, `analizeTableDetail` JSON, `analize` JSON, `removeDataFromDb` uses the helper, remove the 2 builders
- [x] Tests PHP: `analize` and `analizeTableDetail` (fields, types, zero rows, `idColumns`, `hasMore`, `supported`, bad table or column, decoded text, NULL `text_to`). Remove the builder test
- [x] `build.sh`: remove the no-op rewrite at line 405
  - Findings (PHP steps): `ote-implementer` did the PHP steps. I read the CTRL diff and the new CORE methods (CORE:46-175, 687-702). A missing `mode` is now an error in the 3 endpoints. The JS always sends it. `globalCharCount` already counts only the target language, because LIB:829 filters `l2` by `langIdTo`. The CTRL validation has no unit test, because no CTRL test harness exists. The new language key `te_site_error_bad_detail_request` is in the 3 files. Translate loop tests: 304 (was 241). The other PHP suites do not change.
- [x] Templates: `te-langbar`, new `#tab-translate-site`, remove the 3 old pairs, in `.tpl` and `.oc4.twig`
- [x] Code JS: `teSiteFilters`, `teProgress`, analysis loop and state, result table, detail view, translation loop, langbar, error anchor. Remove the old functions
- [x] CSS: total row, radio group, progress states (under `.te-app`). Remove dead rules if any
- [x] Language strings: en-gb, ru-ru, uk-ua (add `te_site_*`, remove the unused keys and their CTRL `$data` lines)
- [x] Tests JS: filters, state across batches, append without re-parse, `.fail()` keeps results, stop, detail text, translate-all queue, langbar show and hide, localization reads the langbar
- [x] Docs: PoC status notes, `CLAUDE.md` test description if it changes
  - Findings (front-end steps): a second `ote-implementer` did these steps. I checked with greps: the old functions, the old language pair ids, `#analizeBody` and the `parseInt($(...).html())` reads are gone. The new JS block has no ES2015 syntax. The route strings keep the form that `build.sh:384-392` rewrites. The radios have `form="te-no-form"`, so that the settings form does not post `te-analization-mode` as a setting. 40 `te_site_*` keys were added and 23 old keys were removed. I also removed `entry_analization_result`, because no template uses it. `showTranslatedCharCount()` now runs one time when a translation ends, not after each batch. The modal stays for the removal, the localization "translate all" and the detail view. JS tests: 388 (was 206).
- [x] Bump version + build + test
  - Findings: green. 04.01.0182. 1585 tests pass (JS 388, translate loop 304, 6 integration tests skipped, no key). The rewrite checks in the OC 1.5, 2.2, 3.0 and 4.x packages pass.
- [x] WIP commit, push and PR (the review is not done)
  - Findings: WIP commit with v04.01.0182 on 2026-11-16.
- [x] Store run on OC 1.5, 2.3, 3.0 and 4.x (the user)
  - Findings: the user did the store run on 2026-11-16 and reported 3 changes. See the 2 updates below. The user did not name the checked versions.
- [x] Independent review → 58-json-analize-website-content-tab-review.md
  - Findings: Fable 5.1. Ready to merge. No Critical, no Important, 7 Minor. The usage limit stopped the first run. The same agent continued after the reset.
- [x] Address review findings (auto-approve)
  - 1 (non-ASCII `×` in the JS): fix. `\u00d7`.
  - 2 (`ctype_digit` is a new extension dependency): fix. `preg_match('/^[0-9]+$/')`.
  - 3 (`langIdTo` = 0 means all languages in `analize` and `translateTable`): fix. CTRL returns `badFilterResponse()` when `langIdFrom` or `langIdTo` is not positive, in the 3 endpoints.
  - 4 (the progress counters): fix. At `done`, N is M, so the bar is full. "Now:" shows the last table of the batch, because `lastProcessed*` names the position before the cut.
  - 5 (the localization alerts go before the langbar, so they show on all tabs): fix. This task caused it. The anchor is now the Analyze button of each localization tab. Before this task, it was the source select in the tab.
  - 6 (test gaps): fix. New JS checks: a new column of a collapsed table is hidden, an existing button stays the same node, done gives "3 of 3", the mode radios have `form="te-no-form"` in both templates, the localization anchors.
  - 7 (`showTranslateExpertErrorMessage` inserts `responseText` as HTML): skip. The code is older than this task, and only the removal and localization flows use it. PoC step 4 changes it.
- [x] Bump version + build + tests (verify loop)
  - Findings: green. 04.01.0183. 1596 tests pass (JS 399, 6 integration tests skipped, no key). I checked the fix diff in the main context before the build. Pushed to PR #64.
- [x] Re-review? (ask the user)
  - Findings: the user said no on 2026-11-16, also for the store run changes.
- [x] Improvements pass → 58-json-analize-website-content-tab-improvements.md
- [x] Update the PR body (the review is done)

## Done

- Landed: `analizeTableDetail` and `analize` return JSON data only. The "Website content" tab has the new design with the `te-langbar`, an inline `te-progress`, a result table with `append()` and the detail modal. The counters are in JS state objects. The 2 HTML builders are gone. `langIdFrom`, `langIdTo` and `mode` are validated in 3 endpoints. Final version: 04.01.0185, 1608 tests pass.
- Review: Fable 5.1, 7 Minor findings. 6 fixed, 1 skipped (`showTranslateExpertErrorMessage` inserts HTML, older than this task, PoC step 4). No re-review (the user decided).
- Store run: 3 changes after the review (the button order, a spacer row between tables, the progress counter at the end of a non-deep analysis). They have tests and a green build, but no independent review.
- Gotchas: the usage limit stopped the review agent. The same agent continued after the reset. A substring grep gave a false "still used" for a language key.
- Deferred: the localization tabs (PoC step 4, also the HTML in `showTranslateExpertErrorMessage`). GET to POST, `hasPermission` and the model re-throw (PoC step 6). The stop button stops only the next request.

## Update 2026-11-16 (store run feedback)

The user did a store run of v04.01.0183. The user asked for 2 layout changes in the result table:

- A table row has only "Translate", and a column row had "Details" and then "Translate". So the "Translate" buttons were not in one line. Now "Translate" is always the first button (`teSiteRefreshRow`).
- The tables and their columns were too close together, and the result was difficult to read. Now an empty spacer row `tr.te-table__spacer` (one `td` with `colspan="3"`, 16px, no border, no background) comes before each table except the first. A CSS padding on the group row does not give an empty line, because the group row has a grey background.
- New JS checks: the button order and the spacer rows. JS tests: 403.
- Build of the 2 layout changes: green, 04.01.0184, 1597 tests pass.

## Update 2026-11-16 (store run feedback, progress counters)

The user saw a done analysis with "Now: oc_weight_class_description" and "Table 17 of 25".

- Cause: in non-deep mode, the server can send a last batch with no tables and `done` = 0. The loop then ended in its last `else` branch. That branch did not set N to the total and kept the old table name. Also, N counted the result rows, and tables with 0 characters are not in the response.
- Fix: N is the position of the current table in the table list (the selected tables, or all options). The server goes through the tables in the order of `getTables()`, which is also the option order. An empty batch in non-deep mode ends the run. At the end, the panel shows N of N and no "Now:" line.
- Mutation check: with `finished = !!data.done` only, the new test "an empty batch ends the run" fails. I restored the fix from a byte copy and checked it with grep.
- JS tests: 407. Build: green, 04.01.0185, 1608 tests pass. Pushed to PR #64.
