# 35-multi-value-analysis-filters: review

Range: `git diff origin/main...HEAD` (commit `481bd3b`). Reviewer: Fable 5.2.

## What I did

- I read the full diff and the code around each changed block.
- I ran the 4 unit test scripts. Results: 24 passed (6 skipped, no key), 40, 67 and 185 passed.
- I ran the real `getTextToTranslateRows()`, `removeDataFromTable()` and `selectTables()` against a throwaway MariaDB 10.11 instance. All 20 checks passed.
- The probe is `docs/tasks/35-multi-value-analysis-filters/tmp/probe_sql.php`. Its only argument is the socket path of a MariaDB server with a `root` user that has no password. The probe creates and drops the database `te35`.

## Strengths

- **The `exists` choice is correct.** The probe confirms it. A product in 2 selected categories gives 1 row with `value_count` = 1. `distinct` cannot repair `count(*)` in a query that has `GROUP BY`, so the plan is right to reject it.
- **The SQL is correct in each position.** The probe ran these cases with correct results:
  - The SELECT with the `GROUP BY` on the text
  - The SELECT with the `text_from` filter (`GROUP BY` on the primary keys)
  - The single-table `UPDATE` with `exists`
  - The multi-table `UPDATE` with the `product` join and `exists`
  - A table that has no `product_id` column
- **The `in (...)` lists contain only integers.** `normalizeIdList()` (`client_translate_expert_core.php:463`) casts each item at the point where the SQL text is made. The probe sent `10) or 1=1 -- ` as an item. The SQL text contained only `10`.
- **The table filter never becomes wider.** `selectTables()` (`client_translate_expert_core.php:475`) keeps only the names from `getTables()`, in `getTables()` order, with sequential keys. Thus no request value goes into a table name in the SQL.
- **The analysis paging is correct with a list.** The list order is stable between requests, so the `continue_after_table` skip and the last-table check in `getTablesToTranslateAnalizationInfo` work as before.
- **The session and template round trip is safe.** `readSessionList()` always gives a list of strings. The templates only compare the session values. They never print them. Thus an unknown table name in the session cannot reach the HTML.
- **The cron link stays backward compatible.** An old link with one `table`, one `product_category_id` or one `stock_status` gives a list with one item. A list with an unknown table gives `bad_params`, and a test covers this.
- **The repo constraints hold.** The added PHP is PHP 5.6 syntax. The change adds no route, no library class and no setting key, so `build.sh` needs no new entry. The 3 new controller helpers are private. All 3 languages have the new string.
- **The JS helper handles both jQuery lines.** `$(selector).val() || []` covers `null` (jQuery 2.1.1) and `[]` (jQuery 3). `encodeURIComponent` protects the comma-separated value in the 3 URLs.

## Issues

### Critical (must fix)

None.

### Important (should fix)

1. **No automated test covers the new SQL.** `sources/upload/system/library/client_translate_expert.php:833-845` and `:893-905`.
   - The test stub replaces `getTextToTranslateRows()`, and no test calls `removeDataFromTable()`. A later change can break the `exists` placement or the `in (...)` list, and all 4 test scripts stay green.
   - The SQL is the part of this change with the highest risk. `removeDataFromTable()` deletes data.
   - Fix: add a text-level test to `tests/test_client_translate_expert_translate_loop.php`. `FakeDb` already records each query. Make it return one row for the `INFORMATION_SCHEMA.COLUMNS` query, so that `tableHasFIeld()` is true. Call the 2 real methods through a small subclass. Make sure of these items:
     - The text contains `p.stock_status_id in (5,6)`.
     - The text contains `exists (select 1 from oc_product_to_category ptc where ptc.product_id = t.product_id and ptc.category_id in (10,11))`.
     - The text does not contain `join oc_product_to_category`.
     - With empty lists, the text contains no `exists` and no `product` join.
     - With a hostile item, the text contains only the integer.
   - The probe in `tmp/` shows the necessary subclass and the calls.

### Minor (nice to have)

1. **The cron link accepts a `table` value that contains only separators as "all tables".** `sources/upload/system/library/client_translate_expert_cron_helper.php:122-137`.
   - `table=,` or `table=%20` now gives an empty list. `runCron` then translates all tables. Before this change, each of these values gave `bad_params`.
   - `parseIdList()` (`:95-114`) does the same for `product_category_id=,` and `stock_status=,`.
   - This occurs only with a hand-made link. But the plan says that the cron link stays strict, and a wider run uses Google characters.
   - Fix: in the 2 helpers, return `false` if the value is not empty and the list is empty. Add one test line for each helper.

2. **A bad id list makes `removeDataFromDb` wider.** `sources/upload/admin/controller/extension/module/client_translate_expert.php:469-474` and `:573-574`.
   - The "invalid list becomes all" rule is a settled decision, and I do not argue against it for the analysis and the translation. I record only one point: `removeDataFromDb` deletes data, so "all" is the unsafe direction there.
   - A related point: `normalizeIdList()` uses an `(int)` cast, so it is less strict than `parseIdList()`. This is safe for the SQL. No change is necessary.
   - Fix, if you want it: in `removeDataFromDb()` only, return an error if `parseIdList()` gives `false`.

3. **Two language strings have no user now.** `client_translate_expert.php:138` and `:200` of the admin controller still load `entry_all_tables` and `entry_filter_all`. No template prints them after this change. Remove the 2 controller lines and the strings in the 3 language files, or keep them on purpose.

4. **A very long selection can make the URL too long.** `sources/upload/admin/view/javascript/client_translate_expert.js:838-845`, `:986-991` and `:1197-1214`.
   - The analysis request and the removal request use GET. Each encoded comma adds 3 characters. A selection of some thousands of categories can go above the URL limit of the web server. The same applies to the cron link.
   - This is a limit case. The hint already tells the user that no selection means all values. No change is necessary now.

## Not verified

- I did not run the SQL on MySQL 5.x or MySQL 8. The probe used MariaDB 10.11 only. The SQL uses standard syntax, and the store run of the user covers the other servers.
- I did not render the Twig template. I read `{% if x|string in list %}` and found no problem: the 2 sides are strings.
- I did not run the JS in a browser. The multi-select behavior on jQuery 2.1.1 and jQuery 3 comes from code reading and from the plan assumption.
- The page layout (the label is below the select) needs the store run.
- No finding depends on OpenCart core behavior, so I did not use the core source probe.

## Assessment

**Ready to merge?** Yes, after the store run passes.

The SQL, the table selection, the paging, the session round trip and the cron parse are correct, and the probe confirms the SQL on a real server. The Important item is a test gap, not a defect. Add the SQL text test before the merge if possible, because it is the only automatic protection for a query that deletes data.
