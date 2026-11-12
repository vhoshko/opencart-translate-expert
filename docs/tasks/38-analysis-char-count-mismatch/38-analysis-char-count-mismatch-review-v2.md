# 38-analysis-char-count-mismatch: review v2

Scope: the fixes that followed the first review (I1, M5, M6 and the three new tests). The input is `git diff HEAD` on branch `fix/38-analysis-char-count-mismatch` (base `0788da8`). The settled decisions and the accepted gaps of the plan are not part of this review.

Abbreviations:

- LTE = `sources/upload/system/library/client_translate_expert.php`
- LOC = `sources/upload/system/library/client_localization_translate_expert.php`
- CTRL = `sources/upload/admin/controller/extension/module/client_translate_expert.php`
- JS = `sources/upload/admin/view/javascript/client_translate_expert.js`
- TEST = `tests/test_client_translate_expert_translate_loop.php`

## What the review ran

- `php -l` on the 5 changed PHP files and `node --check` on JS: no syntax errors (PHP 8.2.35).
- All 4 test scripts: translate loop 172/0, Google translator 24/0 (6 skipped, no key), route filter 40/0, cron helper 51/0.
- One harness on copies: `tmp/review_v2_run.sh`. It uses `tmp/review_v2_trace.php`, `tmp/review_v2_langfile.php` and the first-review probe `tmp/review_probe_zero_length.php`.
- The harness compares three trees: the base, the working tree, and the working tree with the M5 fix reverted.
- The OpenCart core probe for the request escaping (see I1 below).
- No live Google call. No build. No PHP 5.6 binary is available, so the PHP 5.6 check is a read of each added line.

## Strengths

### M5 (flush conditions)

- The new condition is equal to the base condition for each row that SQL can select. The source filter of `getTextToTranslateRows` (LTE:846-847) rejects an empty source text and a NULL source text. Thus each queued row had a raw length of 1 or more on the base, and `count($textRowsToTranslate) > 0` was true exactly when the base condition was true.
- The answer to the question "can a batch with rows but 0 counted characters now go out where it did not before" is no, compared with the base. Compared with the first version of this change, yes, and that is the purpose of the fix.
- The real path for such a batch is the same as on the base. `trimValue` drops the text, `VHGoogleTranslator::translateBatch` gets an empty array and makes no HTTP call, and `translateTableTexts` returns "Internal error." (`0 != 1`). The harness (part 2) ran the real `translate()` and `translateInternal()` for this case on the base and on the working tree. The results are the same, plus the new `translatedCharCount` field.
- The silent stall of the first review (Minor 5) is gone. Harness part 1: the reverted tree returns `success: 1` with 0 API calls. The working tree returns the visible error, in the admin run and in the cron run with a row cap of 1.
- The row-cap branch has no behavior change. The inner `if` at LTE:333 guards only the flush. The second `if` at LTE:346 still stops the run when the cap is reached, with an empty batch or without.
- A differential fuzz (harness part 3) ran 400 cases on the base and on the working tree. The cases have plain texts, `\x0B` texts, admin runs and cron runs with a row cap. After the removal of the planned `translatedCharCount` field, 399 cases are identical: same batches, same results, same counter.
- The one different case is a result of the settled length rule, not of M5. A `\x0B` text has a length of 0 now and 1 on the base, so one batch limit moved. The request fails with "Internal error." in both trees.
- With a batch length of 0, the 5000-character condition (LTE:353) flushes only if the next text is longer than 5000 characters. That flush then fails with the same visible error. This is correct.

### I1 (language-file count)

- LOC:226 uses the same call as CTRL:429 (`html_entity_decode` with default flags). Thus the two numbers agree on each PHP version, also for `&#39;`, which PHP decodes only from 8.1.
- Core behavior, verified with the core probe: `Request::clean()` runs `htmlspecialchars($data, ENT_COMPAT, 'UTF-8')` on each POST value. Evidence: `system/library/request.php` at 1.5.6.4:33, 2.3.0.2:26, 3.0.3.8:46, 4.0.2.3:61 and 4.1.0.0:119.
- Thus the two `html_entity_decode` calls of CTRL (CTRL:412 on the JSON string, CTRL:429 on each text) give one decode of the file text. One decode is what LOC:226 counts. This closes the "unverified" part of the first-review finding.
- `tmp/review_v2_langfile.php` simulates the full path for 14 texts (JS trim, JSON, core escape, the CTRL decodes, the CTRL count, `trimValue`). The analysis count, the counter and the sent length agree for 13 texts. See Minor 3 for the other text.

### M6 (counter refresh in JS)

- The call is in the correct branch (JS:703). The server stores `translatedCharCount` before it writes the response (CTRL:482-485), so the GET of `showTranslatedCharCount()` reads the new value.
- The function returns immediately if the counter display is disabled. The added lines use no new JS syntax and no new AJAX method, so `build.sh` needs no change.

### Tests

- `testFinalFlushFailureKeepsCount` reaches the third failure return (LTE:389): 3 texts of 3000 characters make 3 calls, and the third call is the final flush.
- `testBatchSizeUsesSentLength` uses 600 + 1200 sent characters against 3000 + 4800 raw characters. A raw length in the condition gives `600 + 4800 > 5000` and 2 calls, so the test can find the revert.
- Harness part 4 confirms that a revert of the final flush condition makes 2 assertions of `testZeroLengthTextIsSent` fail.

### PHP 5.6 and packaging

- The added lines use only `count()`, `html_entity_decode()`, `trim()` and `mb_strlen()` with an explicit encoding. No PHP 7+ syntax is present.
- The fixes add no library class, setting key, UI string or schema change. The templates, the language files, `install.php` and `build.sh` need no change.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. **The second assertion of `testZeroLengthTextIsSent` shows a result that production does not give.** TEST:956-957.
   - The stub `translate()` does not call `trimValue`, so it returns `T:\x0B` and the stub writes the row. The assertion "the row does not stay silently" (`left() === 0`) passes for that reason.
   - In production the row stays and the request returns "Internal error." (harness parts 1 and 2). The first assertion (`apiCalls === 1`) is the real regression guard and it is correct.
   - Possible fix: assert `$r->success === 0` with a stub that drops the texts that `trim()` makes empty. Alternatively, change the label and the comment to "the batch is flushed".

2. **Only the final flush condition of M5 has a test.** LTE:333, LTE:353. Harness part 4: a revert of the row-cap condition or of the 5000-character condition passes all 172 tests. The three conditions are the same expression and the data is very unusual, so the risk is low. One cron case with a `\x0B` row first and a row cap of 1 covers LTE:333.

3. **One residual difference stays in the language-file count. Record it in the plan.** LOC:226 against JS:144.
   - JS `String.trim()` removes Unicode white space, for example a literal U+00A0 character. PHP `trim()` does not.
   - A file text that starts or ends with such a literal character counts 1 more in the analysis for each character. Probe result: analysis 26, counter 25, sent 25.
   - An `&nbsp;` entity is not affected, because JS trims before the decode. Such literal characters at the edge of a language-file text are very unusual. No code change is necessary.

## Assessment

**Ready to merge?** Yes.

The three fixes are correct and have no regression. M5 gives the base flush behavior again for each row that SQL can select, the row-cap branch is unchanged, and the I1 count agrees with the controller on all OpenCart versions. The three minor items are test polish and one record in the plan. None of them blocks the merge.
