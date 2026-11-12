# 35-multi-value-analysis-filters: review v2

Range: `git diff HEAD` on top of commit `481bd3b` (the fix diff only). Reviewer: Fable 5.2.

## What I did

- I read the full fix diff and the code around each changed block.
- I ran the 4 unit test scripts on PHP 8.2 with `error_reporting=-1`. Results: 24 passed (6 skipped, no key), 40, 72 and 201 passed. The output has no warning, notice or deprecation line.
- I ran `php -l` on each changed PHP file. All files are clean.
- I ran 7 mutations of the SQL code against `testRealSqlText()`. The script is `docs/tasks/35-multi-value-analysis-filters/tmp/mutate_sql_test.sh`. It changes only copies in `tmp/mut/` and removes them at the end.
- I ran the 2 parsers with limit values (`''`, `null`, `','`, `' '`, `'0'`, `'5,'`, `',5'`, `'a,,b'`).

## Fix status

| Finding | Status | Evidence |
|---|---|---|
| I1 | Closed | 6 of 7 mutations fail the test. See Minor 2 for the one that stays green. |
| M1 | Closed | `','` and `' '` give `false` in the 2 parsers. `''` and `null` give `array()`. `parseParams` gives `false` for `table=,`. |
| M2 | Closed for string values | An invalid string list gives `error_bad_filter`, with no session write and no model call. See Minor 1 for the array case. |
| M3 | Closed | No file in `sources/` or `build.sh` contains `entry_all_tables` or `entry_filter_all`. |

## Strengths

- **The I1 test calls the real SQL methods.** `RealSqlLibrary` adds only public wrappers. It does not replace `getTextToTranslateRows()` or `removeDataFromTable()`. `SqlDb` answers only the `INFORMATION_SCHEMA.COLUMNS` query.
- **The I1 test finds real defects.** These mutations each make the test fail:
  - The `exists` text changes to a different subquery in the SELECT (3 failures).
  - The `UPDATE` loses `{$categoryCondition}` (3 failures).
  - The stock `in (...)` list changes to `= first id` (2 failures).
  - The category list keeps only the first id (4 failures).
  - `normalizeIdList()` loses the integer cast (4 failures).
  - An empty category list still adds `exists` (2 failures).
- **The test also checks the position.** The 2 last checks make sure that `exists` is after the `where` keyword, in the SELECT and in the `UPDATE`.
- **M1 adds no regression in the admin actions.** `readIdListParam()` and `readTableListParam()` change `false` to `array()`. Thus `','` gives `array()` there, as before the fix. The JS helper removes empty items before the join, so the page never sends `','`.
- **M1 keeps the cron link compatible.** A missing parameter and an empty parameter still give `array()`. One value, `5,` and `,5` still give a list with one item. Only a value without items now gives `bad_params`.
- **M2 stops before each side effect.** The check in `removeDataFromDb()` is before the session writes and before the model call. The response has the same shape as the other errors (`success`, `message`), so the JS shows the text.
- **M2 does not make the removal wider for unknown tables.** A list of unknown names gives an empty `selectTables()` result. Then the loop removes nothing.
- **The repo constraints hold.** The new code is PHP 5.6 syntax. `readStrictListParam()` is private, so it is not a route. The fix adds no route, no library class and no setting key. `error_bad_filter` is in the 3 language files.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. **An array parameter still gives "all" in the removal.** `sources/upload/admin/controller/extension/module/client_translate_expert.php:481-487`.
   - `readStrictListParam()` changes a value that is not a string to `''`. The parser then gives `array()`.
   - Thus `&product_category_id[]=5` or `&table[]=x` removes data with no filter. This is the unsafe direction that M2 wanted to close.
   - This occurs only with a hand-made URL. The page always sends a string.
   - Fix: return `false` if the key is set and the value is not a string:
     ```php
     if (isset($source[$key]) && !is_string($source[$key])) return false;
     ```

2. **No test case has a table without `product_id`.** `tests/test_client_translate_expert_translate_loop.php:78-89`.
   - `SqlDb` always answers that the column exists. Mutation m7 removed the `$tableHasProductId` guard from `$categoryCondition`, and all 201 tests passed.
   - The result of such a defect is an SQL error on tables such as `category_description`. It is not a data loss.
   - Fix: give `SqlDb` a flag that makes the `INFORMATION_SCHEMA.COLUMNS` query return no row. Add 2 checks for each method: no `exists` and no `product` join with full lists.

3. **Two docblocks are out of date.** `sources/upload/system/library/client_translate_expert_cron_helper.php:91-93` and `:118-120`.
   - The `@return` lines do not say that a value without items gives `false`.
   - For `parseTableList()`, "false if the value is not a string" is now only part of the rule.

4. **The test list has one case twice in effect.** `tests/test_client_translate_expert_cron_helper.php:126-131`. The `parseTableList` checks for `','`, `' '` and `' , '` are 3 lines for one rule. This is harmless. No change is necessary.

## Not verified

- I did not run the admin action `removeDataFromDb` in a store. The M2 result comes from code reading and from the established facts about the build and the JS.
- I did not run the mutated SQL on a database. The mutation check is text-level, as the test is.
- No finding depends on OpenCart core behavior, so I did not use the core source probe.

## Assessment

**Ready to merge?** Yes.

Each fix closes its finding, and no fix adds a regression in the admin actions or in the cron link. The 4 Minor items are optional. Item 1 is the most useful, because it completes the M2 rule with one line.
