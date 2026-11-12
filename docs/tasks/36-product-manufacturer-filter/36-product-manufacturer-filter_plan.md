# 36-product-manufacturer-filter

## Settings

- Planning model: Opus 5.5 (inline).
- Review model: Sonnet 5.5 (pass `model: sonnet` to `ote-code-reviewer`).
- Build and tests: as the dev-workflow skill sets. Commit, push and PR: not agreed. The workflow does not commit.
- Standing auto-approve for review findings: not granted. Ask the user for each finding.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/36

The product filter on the analysis tab has no manufacturer filter. The user cannot translate only the products of one or more manufacturers. The task adds a multiselect Product manufacturer filter. It works the same way as the category and stock status filters from #35 (merged as `e301422`). The cron work (`0788da8`) is also merged, so the cron link and `ClientTranslateExpertCronHelper` must send and parse the new filter too.

Affected OC versions: all (1.5–4.x).

Investigation: the Explore agent was skipped. One grep for `stock_status` over `sources/`, `build.sh` and `tests/` found each place that the new filter must follow. Key places:

- Controller `sources/upload/admin/controller/extension/module/client_translate_expert.php`: page data `:324`, list load `:332-338`, `translateTable` `:515`, `analizeTableDetail` `:538`, `analize` `:555`, `:569`, `removeDataFromDb` `:585-600`.
- Model `sources/upload/admin/model/extension/module/client_translate_expert.php:20-59` (four pass-through methods).
- Core `sources/upload/system/library/client_translate_expert_core.php`: `analizeTableDetail` `:46-86`, `analize` `:145-254`, `removeDataFromDb` `:285-325`, `insertTranslatedTableTextValue` `:369-382`, `buildHtmlTranslateTableButton` `:483-510`.
- Library `sources/upload/system/library/client_translate_expert.php`: `translateTable` `:48-52`, `translateNextBatch` `:73`, `translateTableWithBudget` `:288-412`, `translateTableTexts` `:443-505`, `getTableToTranslateAnalizationInfo` `:513-597`, `getTablesToTranslateAnalizationInfo` `:669-710`, `getTextToTranslateRows` `:777-878` (SQL `:833-841`), `removeDataFromTable` `:887-901`.
- Cron helper `sources/upload/system/library/client_translate_expert_cron_helper.php:67-87`.
- JS `sources/upload/admin/view/javascript/client_translate_expert.js`: `analizeTableDetail` `:644`, `doTranslateTableExpert` `:674-736`, analysis `:834-846`, removal `:983-992`, cron link change binding `:1177`, cron link `:1219-1226`.
- Templates: `.tpl:188-195`, `.oc4.twig:185-191`.
- Language: `entry_table_product_stock_status_filter`, en-gb `:167`, ru-ru `:167`, uk-ua `:168`.
- Tests: `tests/test_client_translate_expert_cron_helper.php:74-134`, `tests/test_client_translate_expert_translate_loop.php` (stub overrides `:187`, `:192`, `:241`, filters `:403`, SQL text `:1065-1085`).

`build.sh` needs no change. The task adds no AJAX method, library class or setting key.

## Approach

Add a `$manufacturer_id` list parameter directly after each `$stock_status` parameter. Thread it through the same call chain: JS → controller → model → core and library → SQL. Use the existing list helpers: `readIdListParam` and `readStrictListParam` in the controller, `normalizeIdList` in the library and `parseIdList` in the cron helper. The SQL adds `and p.manufacturer_id in (...)` to the `product` join. The join condition also activates when the manufacturer list is not empty. The session key is `te_manufacturer_id`. The request parameter is `manufacturer_id`, a comma-separated ID list, the same as `stock_status`. The default value of the new parameter is `null` (no filter), so old call shapes keep their behavior.

Design correction to the issue text: the issue SQL uses `implode(',', $manufacturer_id)` on the raw value. Use `normalizeIdList()` first, as the stock status code does. This keeps only positive integers in the SQL text.

## Assumptions

- ✅ verified: admin `catalog/manufacturer` → `getManufacturers($data = array())` exists on OC 1.5.6.4, 2.0.3.1, 2.2.0.0, 2.3.0.2, 3.0.3.8, 4.0.2.3 and 4.1.0.0. With no `start` or `limit`, it has no `LIMIT` and sorts by `name`. Evidence: the GitHub probe of `upload/admin/model/catalog/manufacturer.php` at each tag (function lines 1-39).
- ✅ verified: the `product` table has `manufacturer_id int(11) NOT NULL` on all versions. Evidence: `upload/install/opencart.sql` at 1.5.6.4, 2.3.0.2 and 3.0.3.8 (`oc_product` line 14). `upload/system/helper/db_schema.php` at 4.0.2.3 and 4.1.0.0 has `'name' => 'manufacturer_id'` in the table definitions.
- ❓ unverified (accepted limit): a product with no manufacturer has `manufacturer_id = 0`. The filter cannot select these products, because `normalizeIdList` drops `0`. The issue does not ask for a "no manufacturer" option.

## Steps

- [x] Code: controller (list load, four actions, session, page data), model, core, library SQL, cron helper
  Findings: `ote-implementer` made the change. The main context checked the diff: each call that passes `$stock_status` also passes `$manufacturer_id` in the next position. The counts match: library 23/23, core 18/18, model 8/8. `removeDataFromDb` rejects a bad `manufacturer_id` with `error_bad_filter`, the same as `stock_status`.
- [x] Templates: `.tpl` and `.oc4.twig`, after the stock status filter
- [x] JS: four request paths, both onclick functions, the recursive call, the cron link and its change binding
- [x] Language strings: `entry_table_product_manufacturer_filter` in en-gb, ru-ru, uk-ua, plus the controller `$data` line
- [x] Tests: cron helper `manufacturer_id` parse cases, translate loop SQL text and stub signatures
  Findings: cron helper 72 → 75 tests, translate loop 205 → 215 tests. The `RealSqlLibrary` helpers `select` and `remove` take the manufacturer list as the third argument. Twelve `translateTable(...)` calls in the loop test got one more `null` argument, because the new parameter has no default (the same as `$stock_status`).
- [x] Real SQL check on a throwaway MariaDB instance (select and remove with a manufacturer list)
  Findings: `tmp/probe_sql.php` runs the real `getTextToTranslateRows` and `removeDataFromTable` on MariaDB. 7/7 cases pass: no filter, manufacturer list, manufacturer with stock status, manufacturer with category (empty and match), hostile item cut to `7`, and removal of only the listed manufacturers. The instance was stopped and its data folder removed.
- [x] Bump version + build + test
  Findings: `04.01.0159` → `04.01.0160`. Build green. Tests 383 passed (75 + 40 + 215 + 29 + 24), 6 skipped (no API key). The new id, `getManufacturers` load, SQL condition and cron key are in all 7 packages (oc1.5–oc3.0, oc4.x).
- [x] Independent review → 36-product-manufacturer-filter-review.md
  Findings: Sonnet 5.5. No critical or important issues, 4 minor issues.
- [x] Address review findings (the user picks which to fix or skip)
  Findings: see the two `## Update 2026-11-12` sections.
- [x] Bump version + build + tests (verify loop)
- [x] Re-review? (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → -review-v2.md, v3, …)
- [x] Improvements pass → 36-product-manufacturer-filter-improvements.md

## Done

- Landed: a multiselect Product manufacturer filter (`manufacturer_id`) through the templates, JS, controller, model, library SQL and cron link. Also a 100-item selection cap on the Table, category, stock status and manufacturer lists, with a hint, a "Select first 100" caption and server rejection (`error_bad_filter`, cron `bad_params`). Also a 4000-character warning for the cron link, and the uk-ua "товару" fix.
- Final version: `04.01.0162`. Tests: 393 passed, 6 skipped (no API key). Reviews: v1 (Sonnet 5.5) and v2 (Fable 5.2), with no critical or important findings.
- Behavior change: `analize`, `translateTable` and `analizeTableDetail` now reject a bad list. Before this change, they used a bad list as "no filter".
- Gotchas: a `cd` moved the session working directory. The bundled Bootstrap CSS puts `.help-block` after `.text-danger`.
- Deferred: no browser or store run of the JS and templates. No test reads the JS function signatures. Products with `manufacturer_id = 0` cannot be selected.

## Update 2026-11-12: review decisions and scope change

The review (`36-product-manufacturer-filter-review.md`) found no critical or important issues. It found 4 minor issues. The user decided:

1. Onclick argument order has no test: **fix**. Add a test that checks the argument order in both onclick strings of `buildHtmlTranslateTableButton`.
2. The uk-ua text uses the Russian-style genitive "товара": **fix both lines**. Change it to "товару" in the manufacturer string and in the stock status string.
3. The manufacturer list has no size limit: **scope change**. The user asked for a selection cap on all multiselect lists, with a hint that shows only when the list is longer than the cap.
4. `tmp/probe_sql.php` must not ship: no action. `.gitignore` line 20 (`/docs/tasks/*/tmp/`) excludes it.

Selection cap design (approved by the user):

- The cap is 100 selected items per list. It applies to the Table, category, stock status and manufacturer lists. The constant is `ClientTranslateExpertCronHelper::LIST_MAX_ITEMS`.
- UI: if a list has more than 100 options, show the hint "You can select up to 100 items" under it. The "Select all" button caption changes to "Select first 100" and the button selects the first 100 options. If a change selects more than 100, keep the first 100 and highlight the hint.
- Server: `parseIdList` and `parseTableList` return `false` for more than 100 unique items. `analize`, `translateTable` and `analizeTableDetail` return `error_bad_filter` for a `false` list, the same as `removeDataFromDb`. Before this change, they used a `false` list as "no filter", so an over-limit list could translate all rows.
- Cron: the cron helper rejects more than 100 items, so the cron run returns `bad_params`.
- Cron link: keep commas unencoded in the list values. If the link is longer than 4000 characters, show a warning under it.

URL size: HTTP has no fixed 4 KB limit. The usual server limits are about 8 KB (Apache `LimitRequestLine` 8190 bytes, nginx 8 KB buffer). A hosting cron panel or a proxy can be stricter, and this is not verified. With the cap, the worst case is about 6 KB: three ID lists of 100 items and a Table list of 100 names. A normal selection is much shorter, because an empty Table list means all tables.

Steps:

- [x] Fix 1: onclick argument order test
- [x] Fix 2: uk-ua "товару" in two strings
- [x] Cap: helper constant and parser checks, with tests
- [x] Cap: controller strict reading in `analize`, `translateTable` and `analizeTableDetail`, and the limit value for the templates
- [x] Cap: templates (hint, data attribute, cron link warning) in `.tpl` and `.oc4.twig`
- [x] Cap: JS (hint, caption, selection cap, cron link commas and length warning)
- [x] Cap: language strings in en-gb, ru-ru, uk-ua
  Findings: `ote-implementer` applied all items. The main context checked the cap constant, the parser checks, the strict reading in all four controller actions (before the session writes), the removed lenient readers, the uk-ua text and the JS cap logic. Tests: cron helper 83, translate loop 217. The JS and templates have no browser test. The hint lookup uses `nextAll()`, because the three product selects in the `.tpl` share one parent.
- [x] Bump version + build + tests (verify loop)
  Findings: `04.01.0160` → `04.01.0161`. Build green. Tests 393 passed (83 + 40 + 217 + 29 + 24), 6 skipped (no API key). `data-te-max`, `te-cron-link-long`, `getListMaxItems`, `LIST_MAX_ITEMS` and `help_multi_select_limit` are in the oc1.5, oc2.2, oc3.0 and oc4.x packages.
- [x] Re-review? (the cap is a scope change that no reviewer has seen. Ask the user.)
  Findings: the user asked for a second review on Fable 5.2 (not Sonnet 5.5) for this pass.
- [x] Independent review → 36-product-manufacturer-filter-review-v2.md (Fable 5.2)
  Findings: no critical or important issues, 4 minor. A usage limit stopped the agent once. It continued from its saved state.

## Update 2026-11-12: v2 review decisions

The user declined the per-finding question and said "continue please". The main context applied its recommendations:

1. The `.tpl` hint does not turn red on OC 1.5–3.0: **fixed**. In the bundled Bootstrap CSS, `.help-block` (line 2326) comes after `.text-danger` (line 1212). The 4 hints now use `text-muted` (line 1185) with an inline margin, so `text-danger` wins.
2. Cap tests call `count()` on a `false` result (fatal `TypeError` on PHP 8): **fixed**. A `$countOrValue` closure returns the count or the raw value. Proof: a `<= 99` mutation of `parseIdList` gives 3 FAIL lines and no fatal error. The code was restored from a copy, and the restore was checked with grep and a test run (83 passed).
3. `testButtonOnclickArguments` does not read the JS signatures: **skipped**. The repo has no JS test harness.
4. A restored session selection over the cap gives `error_bad_filter`: **fixed**. `initTranslateExpertMultiSelectButtons` trims the selection to the first 100 when the page opens and highlights the hint.

- [x] Bump version + build + tests (verify loop after v2 fixes)
  Findings: `04.01.0161` → `04.01.0162`. Build green. Tests 393 passed, 6 skipped (no API key). The oc1.5, oc2.2 and oc3.0 `.tpl` files have 4 `text-muted te-multi-select-limit` hints and no `help-block` hint.
- [x] Re-review? (ask the user)
  Findings: the user said "continue without rereview".
