# 4-cron-translation review v2

Scope: only the fixes for findings 1–7 of `4-cron-translation-review.md`, in `git diff HEAD` and the new files. The facts in the brief (build 04.01.0133, `php -l`, the four test results, the package greps, the mutation result of the loop test) are ground truth. This review did not measure them again.

## Method

- This review read `runCron`, `cronFailure`, `hideSecret`, `translateNextBatch`, `translateTableWithBudget` and `translateTableTexts` line by line.
- It ran the loop test (45 passed) and the cron helper test (46 passed).
- It ran a second harness against the real library. The harness is outside the repository, in the session scratchpad (`h.php`). A stub subclass replaced the row query, the Google call, the DB write and the counter. A stub model supplied `config` and `db`. Thus the harness also ran the real `runCron`, which the loop test does not call.
- It read the packages of `releases/v04.01.0133` for the bulk clamp path. It did not build.

No successful translation has run on a real store. All results for the loop come from stubs.

Harness results (PHP 7.4):

| Case | Result |
|---|---|
| Cap 100, 40 empty groups first | `rows: 100`, `skipped: 40`, 2 Google calls, no text sent two times. |
| Cap 100, 350 empty groups first | `rows: 100`, `skipped: 350`, 5 Google calls, no text sent two times. |
| Cap 100, 3000-character texts, 30 empty groups first | `rows: 100`, `skipped: 30`, `chars` equals the sent characters. |
| Cap 50, empty groups between normal groups, `value_count` 2 and 3 | 4 runs, `done: true`, only the 40 empty groups stay. |
| Google failure on call 3 | `translate_failed`, `rows: 2`, `chars: 6000`, `details` present, counter called one time with 6000. |
| Google failure on call 2, after a cap flush of 100 empty groups | `translate_failed`, `rows: 0`, `chars: 2000`, `skipped: 100`, counter called one time. |
| `\Error` in call 3 | JSON `translate_failed`, counter called one time with 6000, lock released. |
| `addCharCount` throws | JSON `translate_failed`, counter called one time, not two times. |
| Secret in `additional_message` and in an exception message | `***` in the response in both cases. |

In each case the lock was released one time for each run.

## Strengths

- **Fix 1 is correct and it ends.** Each flush in the cap path has a minimum of one group. The group is written (`rowsDone` and `rowsQueued` keep its rows) or it goes into the skip set. Thus each pass makes progress, and the cap check becomes true after a finite number of flushes.
- **Fix 1 does not send a text two times.** After the flush, `offset` is 0 and `queuedKeys` is empty. The written groups leave the query. The skip set stops the empty groups. The remaining rows of the page that is already in memory were not in the flushed batch, because the batch has only rows from before the current row.
- **The admin flow is the same.** `rowCap = 0` makes the outer cap condition false, so the new block cannot run from `translateTable`.
- **The loop test uses the real loop.** The stub replaces only the row query, the Google call, the two writes, the languages and the table analysis. `translateNextBatch`, `translateTableWithBudget` and `translateTableTexts` are the production code. The stub obeys `limit` and `offset`, so the test also reads more than one page after an offset reset.
- **Fix 3 is valid on PHP 5.6.** `catch (\Throwable $e)` after `catch (\Exception $e)` parses on 5.6 and matches nothing there. `finally` still releases the lock.
- **Fix 4 is correct.** The flag is set before the call, so `cronFailure` cannot add the same characters again.
- **Fix 5 has one rule.** The controller, the model and `runCron` all end in `ClientTranslateExpertCronHelper::clampBulkAmount`. The packages for 2.0–2.3, 3.0 and 4.x have the correct model property name and model route on lines `:64` and `:280` (4.x: `:37` and `:249`). The call uses the same path as `isValidCronSecret`, which the docker smoke test ran on four stores.
- **Fix 7 does not leak the Google API key.** `vh_google_translator.php:273-286` builds its messages from the curl error text, the HTTP code and the Google error message. The request URL with the key is not in these messages.
- **`hideSecret` does not change other text.** The secret has 16 characters or more from a small set, so a chance match in a Google message is not a practical risk.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

**1. The cap path of fix 1 has no time check, so a small cap can go far above the 10 s rule.**
`sources/upload/system/library/client_translate_expert.php:296-315` (cap path) and `:337-345` (time check).

The time check runs one time for each page of 100 rows. Before fix 1, the cap path made a maximum of one Google call and then stopped. Now it can make one Google call for each `rowCap` rows of empty results in the same page.

Harness result: cap 1, 100 always-empty groups first, 300 ms for each Google call. The run made 100 Google calls in 30.3 s. It returned `rows: 0`, `done: false`, `skipped: 100`. Each subsequent run gives the same result, so this corner still makes no progress.

The default cap of 100 gives a maximum of one call for each page, so the default is not affected. The condition needs a small cap and a long sequence of empty results. The first review did not measure how frequently Google returns an empty text. The `skipped` count of fix 6 makes the condition visible.

Fix: after the flush in the cap path, if the elapsed time is more than 10 s, set `$done = false` and `break 2`.

**2. The exception path gives a response with less data than the failure path.**
`client_translate_expert.php:193-201`.

`cronFailure` returns `rows: 0`, `chars: 0`, `table: ''`, `skipped: 0` and no `details` key. In the harness, 2 groups were written and 6000 characters were counted before the `\Error`, but the response showed 0 for both. `README.md:22` says that a `translate_failed` answer has `message` and `details`.

Fix: fill `rows`, `chars` and `skipped` from `$this->_lastBudget` when it is not null, and add `details` as an empty string.

**3. `cronFailure` writes the exception message to the debug log before `hideSecret`.**
`client_translate_expert.php:195`.

The response is redacted, but the log line is not. The comment at `:95` says that the secret never goes to the debug log. This review found no real path that puts the secret into an exception message, so this is a consistency item. The harness showed the secret in the log only because the stub put it into the message.

Fix: call `hideSecret` one time and use the result for the log and for the response.

**4. Fixes 3, 4 and 7 have no automated test, and one stall case has a weak assertion.**
`tests/test_client_translate_expert_translate_loop.php`.

- No test calls `runCron`. Thus the `\Throwable` catch, the `$charsCounted` flag, `details` and `hideSecret` have no check. A stub model with `config` and `db` is sufficient, as the harness of this review shows.
- No test makes the stub `translate` return `success = 0`. The three failure returns of the loop (`:302`, `:322`, `:352`) have no check.
- `testEmptyFirstDoesNotStall(40, …)` at `:264-277` asserts only `rows > 0`. The code before fix 1 gave 60 rows in this case, so this case passes with and without the fix. An assertion `rows === 100` makes it a real check. The cases with 120 groups and with `value_count` 500 are real checks, as the brief states.

**5. A message that is not valid UTF-8 gives an empty response body.**
`sources/upload/catalog/controller/extension/module/client_translate_expert_cron.php` (`json_encode`), with `client_translate_expert.php:157` and `:199`.

Fix 7 puts external text into the body. If `details` or an exception message has bytes that are not valid UTF-8, `json_encode` returns `false` and the body is empty. Not verified: this review did not find a message source that gives such bytes. `JSON_PARTIAL_OUTPUT_ON_ERROR` is available on PHP 5.6 and keeps the other fields.

## Checked, no finding

- **Endless loop.** No path in the cap block continues without a written group or a new skip entry. `rowsQueued` cannot go below 0, because the code subtracts only the rows that it added.
- **Rows already in memory after the offset reset.** The `foreach` continues on the old page. It queues only rows that are not written and not skipped. The next query at offset 0 returns them again, and `queuedKeys` stops a second queue.
- **Cap value.** With 40 empty groups first and cap 100, the run gives exactly 100 rows. The freed rows are used.
- **`break 2` after the flush.** The pending arrays are empty, so the flush after the `while` loop does not run a second time.
- **Failure in the cap flush.** The characters of the batches before the failure are counted one time. The failed batch is not counted.
- **Time rule outside the cap path.** `charsDone` includes the characters of empty results, so the time check also starts after a batch of only empty results.
- **`skipped`.** It is the size of the skip set for the run, across all tables. The README text agrees with it.
- **Flag order in fix 4.** If `addCharCount` throws before its `UPDATE`, the characters are not counted. This is the safe direction and the probability is very low.
- **Bulk value in the templates.** The `.tpl` casts to `int` and the `.oc4.twig` uses `e('html_attr')`.

## Assessment

**Ready to merge?** Yes.

The seven fixes are correct, and this review found no regression in the admin flow, the duplicate guard or the character count. The five minor items are optional. Item 1 is the most useful, because it closes the last no-progress corner for a small cap. The remaining risk is that no run with a real Google key has occurred.
