# 35-multi-value-analysis-filters

## Settings
- Planning model: Opus 5.5 (inline, main session).
- Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes, after every change (`./build.release.sh` with a version bump).
- Commit, push, PR: not part of this workflow. Ask the user.
- Standing auto-approve for review findings: no.
- Store run: yes. After the first green build, push the branch as WIP (the user approved this push). The user pulls and tests on the host Docker stores.
- Plan approved by the user on 2026-11-12.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/35

On the analysis tab, 3 filters accept one value only: Table (`te-analization-table`), Product category (`te-analization-product-category-id-filter`) and Product stock status (`te-analization-product-stock-status-filter`). To translate 3 tables or 3 categories, the user must do 3 runs. The change affects all OC versions (1.5–4.x).

The cron work (#39) merged. For this reason, the cron link (JS) and `client_translate_expert_cron_helper.php` are in scope too.

Key files (HEAD `a4132c8`):
- Templates: `client_translate_expert.tpl:134-141`, `:176-191`. `client_translate_expert.oc4.twig:134-141`, `:176-187`.
- JS: `makeAnalizeRequest` `client_translate_expert.js:813` (reads at 825-829, GET string at 832-844). `makeRemovingDataFromDbRequest` `:969` (974-987). `updateTranslateExpertCronLink` `:1167` (1193-1207). `analizeTableDetail` `:643`, `doTranslateTableExpert` `:673` and `doTranslateTableExpertRecursive` `:694` get the values from the `onclick` strings.
- Controller: `translateTable()` `:468`, `analizeTableDetail()` `:491`, `analize()` `:509`, `removeDataFromDb()` `:541`, session read in `index()` `:318-322`.
- Library: table selection `client_translate_expert.php:667`, cron `:66`, cron table check `:128`, SQL `:822-830` (`getTextToTranslateRows`) and `:880-888` (`removeDataFromTable`). Core: table selection `client_translate_expert_core.php:302`, `onclick` strings `:473`, `:478`.
- Cron helper: `parseParams` `client_translate_expert_cron_helper.php:52`. Tests: `tests/test_client_translate_expert_cron_helper.php:67-111`, `tests/test_client_translate_expert_translate_loop.php:364-367` (`filters()`), `:548-551` (`cronGet()`).

## Approach
Send each filter as one comma-separated string in all places: GET, POST, the `onclick` strings and the cron link. An empty string means "all". Old single-value cron links stay valid. Two pure helpers in `ClientTranslateExpertCronHelper` parse the strings: `parseIdList()` and `parseTableList()`. The admin controller calls them through new model and library wrappers, as it does for `isValidCronSecret`, because OC4 puts the helper in another namespace. The library takes lists: an empty list means "all". A new protected `selectTables()` replaces the 3 copies of `$x ? array($x) : $this->getTables()`. It keeps only known tables, in `getTables()` order, with sequential keys. A non-empty table filter never widens to "all".

**Design correction (issue item 6).** Do not use `distinct`. The row query has `GROUP BY` (`t2.language_id, t.<text>`, or the primary keys), so the duplicates from the category join never reach Google. Only `count(*) value_count` grows. Replace the `product_to_category` join with `exists (select 1 ... ptc.category_id in (...))` in both queries. The `in (...)` list contains only `(int)` values.

## Assumptions
- ✅ The `GROUP BY` removes the duplicate rows of the category join, and only `value_count` changes. Evidence: `client_translate_expert.php:817` and the SQL at `:832-864` (read in this session).
- ✅ A wrong `value_count` changes only the path in `insertTranslatedTableTextValue` (`client_translate_expert_core.php:375`), not the result. The `exists` form removes the problem.
- ✅ MySQL allows a subquery on another table in a multi-table `UPDATE`. Error 1093 applies only to a subquery on the target table. `product_to_category` is never the target.
- ✅ jQuery `.val()` on an empty `<select multiple>` gives `null` (jQuery < 3, OC 1.5–2.x) or `[]` (jQuery 3, OC 4). The JS uses `($(sel).val() || [])`, which handles both.
- ✅ Twig `in` and PHP `in_array` compare the same types. The controller gives the template the session ids as strings, and the DB gives `category_id` and `stock_status_id` as strings.
- ❓ No store run on OC 1.5 or OC 4 is planned before the review. The host checkout is separate, so a store run needs a WIP push (see the question to the user). Without a store run, the SQL and the templates are checked only by the build, `php -l` and code reading.
- Decision: in the admin, an invalid id list (only possible with a hand-made request) becomes an empty list ("all"). The UI sends only valid ids. The cron link stays strict and returns `bad_params`.

## Steps
- [x] Cron helper: add `parseIdList($value)` (string or int, comma-separated, each item a positive int, unique, `''`/`null` → `array()`, anything else → `false`) and `parseTableList($value)` (string only, split, trim, drop empty items, unique, `array_values`). `parseParams()` uses them for `table`, `product_category_id` and `stock_status`. Keep PHP 5.6 syntax.
- [x] Tests: update `testParseParams` (lists in the expected values, `'15,16'`, `'15,x'` → false, `' a , b ,a'` → `array('a','b')`, array table still false). Add tests for the two new helpers. Update `filters()` and any `'table' => ''` filters in the translate loop tests. Add a test for `selectTables()` (unknown table dropped, order of `getTables()`, non-empty filter with no known table → no tables) and for `runCron` with an unknown table in a list → `bad_params`.
- [x] Library `client_translate_expert.php`: add `selectTables($filterTables)`, and public `parseIdList()` and `parseTableList()` wrappers. Use `selectTables()` at `:66` and `:667`. At `:128`, return `bad_params` if any table is unknown. In `getTextToTranslateRows` and `removeDataFromTable`, use `p.stock_status_id in (...)` and the `exists` semi-join. Build each `in (...)` list with `implode(',', array_map('intval', ...))`.
- [x] Core `client_translate_expert_core.php`: use `selectTables()` at `:302`. At `:473` and `:478`, put `implode(',', ...)` of the lists in the `onclick` strings.
- [x] Model: add `parseIdList()` and `parseTableList()` wrappers.
- [x] Controller: in the 4 actions, read the 3 filters as comma-separated strings and parse them through the model. Store the lists in the session. In `index()`, give the template lists of strings (`(array)` for old scalar session values).
- [x] JS: read each select as `($(sel).val() || [])` without empty items. Send `encodeURIComponent(list.join(','))` in `makeAnalizeRequest`, `makeRemovingDataFromDbRequest` and `updateTranslateExpertCronLink`. The `onclick` functions pass the strings through with no change.
- [x] Templates: `<select multiple size="6">` with `form-control` (`.tpl`) and `form-select` (`.oc4.twig`). Remove the empty "all" option. Mark the selected items with `in_array` and `in`. Add a hint under each filter.
- [x] Language strings (en-gb, ru-ru, uk-ua): add `help_multi_select` ("Hold Ctrl to select more than one value. If nothing is selected, all values are used."). Update `help_cron_link` (selected tables, no selection means all tables).
- [x] Bump version + build + test
  Findings: `ote-implementer` did the code steps. `LibraryClientTranslateExpert` extends `LibraryClientTranslateExpertCore`, so `normalizeList()`, `normalizeIdList()` and `selectTables()` are protected methods in the core. The controller has 3 private helpers (`readIdListParam`, `readTableListParam`, `readSessionList`). They are not routes. The tpl selects now have `form-control` (full width), so the filter label is below the select. I fixed one wrong comment in `translateNextBatch`. Build `04.01.0151` is green. Tests: 24 (6 skipped, no key) + 40 + 67 + 185 passed. The rewrites are correct in the OC 1.5, 2.2, 3.0 and 4.x packages. `build.sh` did not change. No DB-backed test exists for the new SQL, so the store run is the only SQL check.
- [x] WIP push of the branch for the store run (user approved)
  Findings: one WIP commit (code + plan), pushed to `origin/feat/35-multi-value-analysis-filters`. The store run result is pending from the user.
- [x] Independent review (Fable 5.2) → `35-multi-value-analysis-filters-review.md`
  Findings: no Critical. I1 (no test of the SQL text), M1 (cron `table=,` means all tables), M2 (a bad id list widens the removal to all), M3 (unused `entry_all_tables` and `entry_filter_all`), M4 (long URL with thousands of categories). The reviewer checked the SQL on a throwaway MariaDB 10.11 (`tmp/probe_sql.php`). The `exists` choice is confirmed.
- [x] Address review findings (the user picks which to fix or skip)
  Findings: `ote-implementer` applied the 4 fixes. I1: `SqlDb` and `RealSqlLibrary` in the translate loop test call the real SQL methods. M1: both parsers return `false` for a non-empty value with no items. M2: new private `readStrictListParam()`, used only in `removeDataFromDb()`. M3: the 2 keys are removed from the controller and the 3 language files.
  Decision (user): fix I1, M1, M2 and M3. Skip M4 (no change necessary). M2 changes the plan decision "an invalid id list becomes all" for `removeDataFromDb()` only: the removal returns `error_bad_filter`.
- [x] Bump version + build + tests (verify loop)
  Findings: build `04.01.0152` is green. Tests: 72 + 40 + 201 + 24 (6 skipped) passed. The new `load->language` call and `error_bad_filter` are correct in the OC 1.5, 2.2, 3.0 and 4.x packages.
- [x] Re-review? (unreviewed fixes exist. Ask the user.)
  Decision (user): yes.
- [x] Independent review v2 (Fable 5.2, fix diff only) → `35-multi-value-analysis-filters-review-v2.md`
  Findings: no Critical, no Important. I1, M1, M2 and M3 are closed. 6 of 7 SQL mutations fail the I1 test. Minor m1: an array parameter (`table[]=x`) still widened the removal to all. Minor m2: no test had a table without `product_id`. Minor m3: 2 docblocks were out of date. Minor m4: 3 overlapping test lines.
- [x] Address v2 findings
  Decision (user): fix m1, m2 and m3. Skip m4 (harmless). No third review: the findings dropped to edge cases and wording.
  Findings: m1, `readStrictListParam()` returns `false` for a non-string value. m2, `SqlDb::$hasProductId` and 2 new checks for each method. The mutation that removes the `$tableHasProductId` guard now fails 2 checks (203 passed, 2 failed). The file was restored from a copy and has no diff from HEAD. m3, both `@return` lines updated.
- [x] Bump version + build + tests (verify loop 2)
  Findings: build `04.01.0153` is green. Tests: 72 + 40 + 205 + 24 (6 skipped) passed.
- [x] Improvements pass → `35-multi-value-analysis-filters-improvements.md`

## Done
- Landed: the Table, Product category and Product stock status filters are multi-select on OC 1.5–4.x. Comma-separated lists go through the JS, the controller, the session, `selectTables()`, the SQL (`in (...)` and an `exists` semi-join) and the cron link. Final version: `04.01.0153`.
- Reviews: v1 and v2 by Fable 5.2. The user picked the fixes: I1, M1, M2, M3, m1, m2 and m3. M4 and m4 were skipped.
- Gotchas: `distinct` from the issue was not correct, because the `GROUP BY` already removes the duplicates. `removeDataFromDb()` is strict (`error_bad_filter`), but the other actions turn a bad list into "all".
- Open: the store run result of the user is pending. One WIP commit (`481bd3b`) is pushed. The fixes after it are not committed. The commit, push and PR need the go-ahead of the user.

## Update 2026-11-12: Select all and Clear selection buttons
The user asked for 2 more items after PR #42 was open:
- The `help_multi_select` text also tells how to use Shift and Ctrl+A (Cmd+A on Mac).
- Each of the 3 multi-select filters gets a "Select all" button and a "Clear selection" button.

Steps:
- [x] Language strings (en-gb, ru-ru, uk-ua): new `help_multi_select` text, new `button_select_all` and `button_clear_selection`. The existing `button_clear` is for the debug log, so it is not used again.
- [x] Controller `index()`: pass the 2 new strings to the template.
- [x] Templates: a button pair under each select (`btn btn-default btn-xs` in the `.tpl`, `btn btn-outline-secondary btn-sm` in the `.oc4.twig`). Each button uses `data-te-target`, not `data-target`, so Bootstrap 3 does not act on it.
- [x] JS: `initTranslateExpertMultiSelectButtons()` selects or clears all options and fires `change`. The `change` event updates the cron link.
  Findings: the main context applied the edits with `tmp/apply_multiselect_buttons.py`. `php -l` and `node --check` pass. With "Select all", every name goes into the request. This gives the same rows as no selection. A new table that appears later is not included, and the URL is longer (finding M4 of review v1).
- [x] Bump version + build + tests
  Findings: build `04.01.0154` is green. Tests: 72 + 40 + 205 + 24 (6 skipped) passed. The strings, the buttons and `initTranslateExpertMultiSelectButtons` are in the OC 1.5, 2.2, 3.0 and 4.x packages.
- [x] Independent review v3? (ask the user)
  Decision (user): no review. Commit and push to PR #42.

## Update 2026-11-12: order of the filter parts
The user asked for this order in each multi-select filter: title, buttons, multi-select control.
- [x] Templates (`.tpl` and `.oc4.twig`): the button row moved above the select (`margin-bottom: 5px` in the `.tpl`, `mb-1` in the `.oc4.twig`). The category and stock status titles (`entry_table_product_category_id_filter`, `entry_table_product_stock_status_filter`) moved from after the select to a `<label for=...>` above the buttons. The Table title stays in the left label column. The hint stays below the select.
  Findings: the main context applied the change with `tmp/reorder_multiselect.py`. `php -l` on the `.tpl` passes.
- [x] Bump version + build + tests
  Findings: build `04.01.0155` is green. Tests: 72 + 40 + 205 + 24 (6 skipped) passed. The new order is correct in the templates of all 7 packages (OC 1.5–3.0 `.tpl` and the OC 4.x `.twig`).

## Update 2026-11-12: OC 4.1 "Could not load template"
The user reported this error on the OC 4.1 store (port 8041): "Error: Could not load template extension/client_translate_expert/module/client_translate_expert! File: /var/www/html/system/library/template/twig.php Line: 121".
- Root cause (✅ verified):
  - OC 4.1.0.0 `system/library/template/twig.php` turns each `\Twig\Error\SyntaxError` into this text (the `catch` at line 120-121, read with the OpenCart core source probe).
  - OC 4.1.0.0 ships `twig/twig` `v3.18.0` (`system/storage/vendor/composer/installed.json`).
  - Twig v3.18.0 defines no `string` filter (`src/Extension/*.php`).
  - The `.oc4.twig` of this branch used `|string` 3 times (`... |string in te_...`). Review v1, review v2 and the builds did not find it, because no step compiles Twig.
- Not needed: Twig `in` uses `CoreExtension::compare()`, so `"15"` and `15` are equal. The controller gives the template lists of strings.
- A local compile with the downloaded Twig 3.18 was stopped by the sandbox classifier ("Code from External"). The static test below replaces it.
- [x] Fix: remove `|string` in `client_translate_expert.oc4.twig` (3 lines).
- [x] Regression test: new `tests/test_oc4_twig_filters.php`. It reads the Twig blocks of each `.twig` file under `sources/` and fails on a filter that is not in the Twig v3.18.0 list. The list matches `src/Extension/*.php` of v3.18.0.
  Findings: the test fails on the template of `HEAD` (`got array('string')`) and passes with the fix (6 passed). The template was restored from a copy.
- [x] Docs: `CLAUDE.md` Tests, `ote-build-test.md`, `project-workflows.md` (test table, new gotchas row, local PHP is 8.2, not 7.4).
- [x] Bump version + build + tests
  Findings: build `04.01.0156` is green. Tests: 72 + 40 + 205 + 6 + 24 (6 skipped) passed. The oc4.x `client_translate_expert.twig` has no `|string`.

## Update 2026-11-12: review v3
- [x] Independent review v3 (Fable 5.2, all changes after review v2) → `35-multi-value-analysis-filters-review-v3.md`
  Findings: the shipped code is clean. Every Twig construct in the `.oc4.twig` exists in Twig v3.4.2 and v3.18.0. The buttons, labels, i18n, m1–m3 fixes and workflow docs are correct. Important I1: the allowed list of `tests/test_oc4_twig_filters.php` came from v3.18.0 only. OC 4.0.2.0 and 4.0.2.3 ship Twig v3.4.2 (verified in `installed.json`), and v3.4.2 has no `find` and no `shuffle` (verified in `CoreExtension.php`). Minor m1–m4: the test checks only filters, rare syntax gives false negatives, a comment gives a safe false positive, and the docblock named only OC 4.1.
- [x] Address v3 findings
  Decision (user): fix I1. m1–m3 need no change. m4 is part of I1.
  Findings: `find` and `shuffle` are removed from the list, a new checker case covers them (7 passed), and the test docblock, `CLAUDE.md` and `project-workflows.md` name both Twig versions.
- [x] Bump version + build + tests
  Findings: build `04.01.0157` is green. Tests: 72 + 40 + 205 + 7 + 24 (6 skipped) passed.

## Update 2026-11-12: review v3 Minor findings
The user asked why m1–m3 of review v3 were skipped. The summary to the user had overstated their cost and had left out the cheap options in the review. The user then chose the full fix (option 2).
- [x] m1: `tests/test_oc4_twig_filters.php` also checks `is` tests, function calls and tag names, and the filters in `{% apply %}`. The allowed lists hold only the names that both Twig v3.4.2 and v3.18.0 define in the extensions that OpenCart loads (read in `src/Extension/*.php`, `src/TokenParser/*.php` and `src/ExpressionParser.php` of each version). Left out: tests `mapping` and `sequence`, functions `enum`, `enum_cases`, `dump` (OC 4.0.2.3 `twig.php` has `debug` false and adds no extension) and `template_from_string`, tags `guard`, `types` and `sandbox`.
- [x] m2: a scanner like the Twig lexer replaces the block regex. It skips string literals and counts brackets, so `"}}"` and nested hashes do not end a block. It also checks the code in `#{...}` interpolation.
- [x] m3: the scanner skips `{# #}` comments and `{% verbatim %}` blocks.
  Findings: 20 checks pass. Proof: the template of `7d98a36` (with `|string`) fails. A copy with planted `is sequence`, `enum()` and `{% types %}` fails with exactly these 3 names. A scanner with no comment skip fails the comment check. The template and the test were restored from copies. A known limit, written in the docblock: a call of an imported macro without a dot is a false positive (the safe direction).
- [x] Docs: `CLAUDE.md` and `project-workflows.md` (test table and gotchas row, with a `dump()` warning).
- [x] Bump version + build + tests
  Findings: build `04.01.0158` is green. Tests: 72 + 40 + 205 + 20 + 24 (6 skipped) passed.
- [ ] Independent review v4 (Fable 5.2: the Twig name test and `docker/rebuild-stores.sh`) → `35-multi-value-analysis-filters-review-v4.md`
