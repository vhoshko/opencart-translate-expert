# Review: 58-json-analize-website-content-tab

Branch: `feat/58-json-analize-website-content-tab`. Range: `origin/main...HEAD` (one commit, `75a7c1c`).

Checks that I ran: `php -l` on the changed PHP files, the 4 unit suites (translate loop 304, i18n 34, Twig names 29, JS helpers 388, all pass), a key parity probe over the 3 language files (171 keys each, no missing key, the placeholders of all `te_site_*` strings match), a grep for each removed language key over `sources/`, `tests/`, `tools/` and `build.sh` (no use left), and a grep for ES2015 syntax in the JS (all hits are in the old localization code, none in the new block).

## Strengths

- The analysis loop is correct against the server. I traced `getTablesToTranslateAnalizationInfo` and `getTableToTranslateAnalizationInfo` (LIB:518-780). The server sets `lastProcessedTable` to the table before the cut table, and `lastProcessedColumn` to the column before the cut column. The JS sends each `continue_*` value only if it is true. This matches the "after" meaning of the server and the old `makeAnalizeRequest`. The server cannot return `done = 0` with all three `lastProcessed*` values empty, so the deep loop cannot repeat one request without end.
- The request parameters match the old flows. `teSiteFilterParams` sends `product_status` and `product_quantity` only as `1`, the lists as comma strings, and the cron link keeps the old parameter order (the JS test checks the full URL). `teSitePostParams` sends `textColumnName: ''` for a table job, and LIB:308 treats an empty column as "all columns", as the old inline `onclick` did.
- XSS: every server value in the result table, the progress panel and the detail modal goes through `teEl` and `teText`, which use `.text()` and `.attr()`. The JS test puts a `<script>` text through the detail view and checks that no element is made.
- The state objects remove the DOM re-parse. The JS test checks that the row nodes of batch 1 are the same nodes after batch 2, and that `.fail()` keeps the rows.
- `analizeTableDetail` validates the table against `getTables()`, the column against `textColumnNames`, and both languages before any row query. The PHP test covers all 6 bad cases and checks that no row query runs.
- The CTRL validation closes the raw SQL use of `langIdFrom`, `langIdTo` and `mode` (LIB:828-829) in the 3 touched endpoints with int casts and a strict whitelist.
- `build.sh`: the route strings keep the `/client_translate_expert/<method>` form that lines 384-392 rewrite. `getLangImgUrl` is still one short function with one closing brace, so the `sed` range at lines 348-371 does not reach `buildLanguageObject`. The removed line 405 rewrite had no target left.
- Both templates carry the same ids, the same `data-te-*` attributes and the same structure, each in its own Bootstrap syntax (`radio-inline` and `checkbox` on BS3, `form-check` on BS5, `fa-exchange` and `fa-right-left`).
- The docs record the 5 corrections of the spec in `endpoints-json.md`.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. `sources/upload/admin/view/javascript/client_translate_expert.js:2005`. The file now has one non-ASCII character (the `×` sign). Before this change the file was pure ASCII. Failure scenario: a web server that sends the JS with a Latin-1 charset shows `Ã—` in the detail view. Fix: write `' ×'` in the string literal.

2. `sources/upload/system/library/client_translate_expert_core.php:91`. `ctype_digit` is new in the code base. The `ctype` extension is bundled and on by default, but a minimal PHP build can leave it out (`--disable-ctype`). Then the detail view gives a fatal error. Fix: use `preg_match('/^[0-9]+$/', $value)`, which has no extension dependency, or keep the id as a string.

3. `sources/upload/admin/controller/extension/module/client_translate_expert.php:559-562` and `505-510`. `analize` and `translateTable` accept `langIdTo = 0`. LIB:829 then sets `l2WhereCondition` to `1 = 1`, so `analize` counts all target languages and `translateTable` sends all target languages to Google. `removeDataFromDb` rejects the same case with `error_language_to_required`. The JS always sends the langbar value, so no user sees this. Fix: return `badFilterResponse()` when `langIdFrom` or `langIdTo` is 0, as a safety net with the same shape as the `mode` check.

4. `sources/upload/admin/view/javascript/client_translate_expert.js:1936-1947`. The counters of the progress panel are cosmetic only in 2 cases. In non-deep mode without a table filter, `seen` counts only the tables of the result, and the server leaves out the tables with 0 characters, so "Table N of M" stays below M. In deep mode, `current` shows `lastProcessedTable`, which is the table before the cut table. Fix: none needed for correctness. If you want a precise label, count `n` from the position of `lastProcessedTable` in the option list.

5. `sources/upload/admin/view/javascript/client_translate_expert.js:60-70`. The localization alerts now go before `#te-langbar-app`, which sits outside the tab panes. An error of the catalog localization tab stays visible on all tabs until the next error removes it. The old anchor was inside its tab. This is a small behavior change of PoC step 4 scope. Fix: keep it, or point `anchor` at the result area of each localization tab.

6. Tests. Two branches of the new JS have no assertion: the `collapsed` branch of `teSiteMergeTable` (a new column row of a collapsed group must get `te-hidden`), and the "an existing button stays" rule of `teSiteRefreshRow` (one `Translate` button on `oc_a.name` after batch 2). The `form="te-no-form"` safety net has no test either. A check that `$('<form>').append(radio)` and `form.elements` or `.serializeArray()` leave the radio out would prove it in jsdom. The CTRL validation has no test because no CTRL harness exists, which the plan records.

7. `sources/upload/admin/view/javascript/client_translate_expert.js:424-449`. `showTranslateExpertErrorMessage` still inserts `responseText` as HTML. The removal flow (task 57) and the localization tabs (PoC step 4) use it. The new flows of this task do not. No change needed here, but PoC step 4 must not keep it.

## Safety nets of the brief

- `form="te-no-form"` on the radios: correct. A `form` attribute that names no form element gives the input no form owner, so the settings form does not post `te-analization-mode` as a setting key, and the 3 radios still form one group. The multi-selects and checkboxes have no `name`, so they were never posted.
- `isset` on `text_to`: correct. A NULL `text_to` (no target row) becomes `''`, and the detail view shows the "empty" badge. The PHP test covers it.
- `ctype_digit` cast of ids: correct for OpenCart primary keys. See Minor 2 for the extension dependency.
- `is_string` check of `mode`: correct. An array value becomes `''` and fails the whitelist without a PHP warning.
- `hasMore` with `limit + 1`: correct. The GROUP BY gives one row for each distinct source text and no post-filter removes rows, so 101 rows means more rows exist. The PHP test covers 100 and 101 rows.

## Assessment

**Ready to merge?** Yes.

The PHP and JS changes match the plan, the loops and the parameters match the server and the old flows, all server values go in as text, and the tests cover each code path that selects a branch. The 7 minor items are hardening and polish. The store run on OC 1.5, 2.3, 3.0 and 4.x that the user does in parallel is the only check that this review cannot replace.
