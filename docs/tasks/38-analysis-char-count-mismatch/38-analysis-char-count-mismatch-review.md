# 38-analysis-char-count-mismatch: review

Scope: `git diff HEAD` on branch `fix/38-analysis-char-count-mismatch` (base `0788da8`), plus the plan in this folder.

Abbreviations:

- LTE = `sources/upload/system/library/client_translate_expert.php`
- CORE = `sources/upload/system/library/client_translate_expert_core.php`
- LOC = `sources/upload/system/library/client_localization_translate_expert.php`
- CTRL = `sources/upload/admin/controller/extension/module/client_translate_expert.php`
- TEST = `tests/test_client_translate_expert_translate_loop.php`

## What the review ran

- `php -l` on the 5 changed PHP files: no syntax errors (PHP 8.2.35).
- All 4 test scripts pass: translate loop 166/0, Google translator 24/0 (6 skipped, no key), route filter 40/0, cron helper 51/0.
- A mutation harness on copies: `tmp/review_mutations.sh`. It reverts one changed line at a time and runs TEST.
- A probe for a text that `trim()` makes empty: `tmp/review_probe_zero_length.php`. It ran on the working tree and on the base.
- No live Google call. No build. No PHP 5.6 binary is available, so the PHP 5.6 check is a read of each added line.

## Strengths

- The length rule is in one place. `getBillableCharCount` (CORE:735) calls `decodeHtml` itself, so the entity condition cannot drift from the send path.
- The rule is correct for a mixed batch. `translateTableTexts` decides to decode for the batch, but `decodeHtml` tests each text. Thus the count of one text equals its sent length in any batch.
- The analysis (LTE:602), the batch size (LTE:354) and the counter (LTE:368) all use the helper. The answer to the user question is clear: the old count measured the stored text, and Google gets the decoded, trimmed text.
- `normalizeForSameCheck` (CORE:742) removes the same 4 characters as the SQL rule and the hash in `insertSameTranslatedText`. The change from `==` to `===` also stops a false match of numeric strings (`"1.0" == "1"`), which SQL does not match.
- The failure returns do not count twice. The admin budget has `countBatches = false`, so only the controller stores `charsDone`. Cron does not read `translatedCharCount`.
- The tests run the real `getTableToTranslateAnalizationInfo` on the same stub rows as the translate loop. They compare three numbers: analysis, counter and sent characters.
- The mutation harness confirms that the tests find a revert of the analysis count, the counter sum, the trim, the decode, `mb_strtolower` and the whitespace removal.
- Each added line is PHP 5.6 syntax. The change adds no AJAX method, library class, setting key, UI string or schema change, so `build.sh`, the templates, the language files and `install.php` need no change.
- No SQL changed. The new `translatedCharCount` field in a failure response contains a number only.

## Issues

### Critical (must fix)

None.

### Important (should fix)

1. **The language-file analysis still counts text that is different from the text sent to Google.** LOC:225 against CTRL:428-429.
   - The language-file translation sends its texts through the `translate` action as `textArr`. CTRL:429 runs `html_entity_decode` on each text before the send and before the count.
   - LOC:225 counts the raw text of the language file with `trim()` only.
   - Example: `Tom &amp; Jerry` counts 15 in the analysis. The counter adds 11, and Google gets 11 characters. OpenCart language files contain entities such as `&nbsp;`, `&amp;` and `&raquo;`.
   - This is difference D1 of the plan, on the language-file path. The plan step asked for `trim()` only, so the code agrees with the plan. But the approved rule is "the text exactly as it goes to Google", and the plan does not record this residual difference.
   - This finding does not depend on OpenCart core behavior, because the decode at CTRL:429 is module code. The number of decode passes (1 or 2) depends on the core request escaping. That part is unverified.
   - Fix: count `mb_strlen(trim(html_entity_decode($text)), 'UTF-8')` at LOC:225, with the same flags as CTRL:429. Alternatively, record the difference in the plan as out of scope. Note that the default flags of `html_entity_decode` changed in PHP 8.1 (`&#39;` is decoded only from 8.1).

### Minor (nice to have)

1. **No test covers the batch-size check.** LTE:354. Mutation M3 changes this line back to `mb_strlen($textToTranslate)` and all 166 tests pass. A test with entity texts near the 5000 limit, with an assertion on the number of API calls, closes this gap.

2. **The failure test covers one of the three failure returns.** LTE:337, LTE:358, LTE:389. Mutation M8b (LTE:358) fails the test. Mutations M8a (LTE:337) and M8c (LTE:389) pass.
   - LTE:389 is reachable from the admin page: the last batch fails after an earlier batch. A second case with `failAtCall = 3` covers it.
   - LTE:337 is in the row-cap branch. The admin budget has `rowCap = 0`, and cron ignores the field. The field is harmless there, and a test is not necessary.

3. **The `text_to` side of the same-value check has no test.** LTE:490. Mutation M9 replaces the comparison with `false` and all tests pass. The stub always returns `text_to = ''`. The base also had no test for this branch.

4. **The same-value test proves the record, not the second half of the plan step.** TEST:892. The plan step says "writes a same-translation record and does not send the row again". The stub marks a group as done at each write, so it cannot show a second send. The "no second send" result comes from the SQL `stt1`/`stt2` joins, which only a DB test can show. Record this gap in the plan.

5. **A text that only `trim()` makes empty now stalls without an error.** LTE:368, LTE:385. Verified with `tmp/review_probe_zero_length.php`.
   - The SQL filter keeps a source text that contains only `\x0B` or `\0`. PHP `trim()` removes these characters, so the new count is 0.
   - Base: each request stops with "Internal error." (`0 != 1`). The row blocks the run, and the error is visible.
   - Now: the admin run returns `success: 1, done: true, translatedCharCount: 0` and the row stays. With such a row first and a row cap of 1, each cron run returns `done: false, rows: 0` and makes no progress.
   - The row blocked the run before this change too, and such data is very unusual. The change is only that the stall is now silent. A possible fix is to skip a row with a count of 0 before `rowsQueued` increases.

6. **The counter display does not refresh after a failed table request.** `admin/view/javascript/client_translate_expert.js:699-703`. The failure branch returns before `showTranslatedCharCount()`. The server now stores the characters of the earlier batches, but the page shows the old total until the next refresh. The stored value is correct.

7. **Some characters that Google got are still not counted. Record this in the plan.**
   - "Internal error." return of `translateTableTexts` (LTE:462-466): the Google call was successful, but the failed batch is not in `charsDone`.
   - An exception after the Google call in an admin request (for example a DB write error): no return value, so the controller stores nothing.
   - The manual `translate` action now counts only a successful call (CTRL:459). If `VHGoogleTranslator` splits a very large request and a later part fails, the first parts are not counted. Before this change the action counted them (and also counted full failures).
   - All three cases are unusual and each is an undercount.

Safety nets (`is_object($result)` at CTRL:459, `(string)` casts at CORE:737 and CORE:744): they are correct and have no side effect. `empty($result->success)` handles `0`, `'0'`, `false` and a missing property. No finding.

## Assessment

**Ready to merge?** With fixes.

The table path is correct: analysis, batch size and counter use one rule that equals the sent text, the same-value check agrees with the SQL rule, and all tests pass. Before the merge, fix or record Important 1 (language-file analysis against decoded text), and add the cheap tests of Minor 1 and Minor 2.
