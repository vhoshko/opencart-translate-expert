# 4-cron-translation review v3

Scope: only the fixes for the five findings of `4-cron-translation-review-v2.md`, in `git diff HEAD` and the new files. The facts in the brief (build 04.01.0135, `php -l`, the PHP 5.6 scan, the four test results, the package greps, the `json_encode` probe, the docker smoke test) are ground truth. This review did not measure them again.

## Method

- This review read `runCron`, `cronFailure`, `hideSecret`, `now`, `createBatchBudget`, `translateNextBatch`, `translateTableWithBudget`, `translateTableTexts`, the cron controller and the loop test line by line.
- It ran the loop test on the sources (66 passed, PHP 7.4).
- It made three mutations in copies of the library under `tmp/v3/` and ran the loop test against each copy. The copies were deleted after the runs. The sources were not changed.
- It ran a probe against the real library: `docs/tasks/4-cron-translation/tmp/v3/harness_v3.php` (with `stub.php`, a copy of the test stubs with a recording `Log`). Command: `V3_SYS="$PWD/sources/upload/system/" php docs/tasks/4-cron-translation/tmp/v3/harness_v3.php`.

No successful translation has run on a real store. All results for the loop come from stubs and a fake clock.

Mutation results (loop test):

| Mutation | Result |
|---|---|
| Fix 1 removed (no time check at `:327`) | 1 failure: `testCapTimeBudget` (100 calls). This agrees with the brief. |
| Fix 2 removed (no budget data in `cronFailure`) | 3 failures in `testCronError` (`chars`, `rows`, `table`). |
| Fix 3 removed (log line uses `$e->getMessage()`) | 0 failures. No test reads the log. |

The `\Throwable` catch, the `$charsCounted` flag and `hideSecret` on `details` and on the exception message each have an assertion that fails without the code. This review found that by reading (`testCronError`, `testCronAddCharCountThrows`, `testCronGoogleFailure`, `testCronException`).

Probe results (real code, and the copy without fix 1 for comparison):

| Case | With fix 1 | Without fix 1 |
|---|---|---|
| S1: cap 1, 50 empty groups first, then 5 normal groups, 0.3 s for each Google call | `rows: 0`, `skipped: 34`, 34 calls, 10.2 s. Each subsequent run gives the same result. | `rows: 1`, `skipped: 50`, 51 calls, 15.3 s. Each subsequent run writes 1 row. |
| S2: 11 s used before the first row, cap 1, 1 empty group first | `rows: 0`, 1 call. Each subsequent run gives the same result. | `rows: 1`, 2 calls. |
| S3: 11 s used before the first row, cap 100, 100 empty groups first | `rows: 0`, `skipped: 100`, 1 call. | `rows: 100`, 2 calls. |
| S4: 11 s used before the first row, cap 100, 1 empty group first | `rows: 99`, 1 call. | `rows: 100`, 2 calls. |
| S5: admin `translateTable`, 250 groups, 4 s for each call | 4 calls, same result. | 4 calls, same result. |
| S6: the DB write throws at write 3 of the first batch (`runCron`) | Response `rows: 3`, `chars: 0`. Real state: 2 groups written, 600 characters sent, counter not called. | Same. |
| S7: exception before the budget exists, debug log on | `rows: 0`, `chars: 0`, `table: ""`, `details: ""`, message redacted, no secret in the log, lock released one time. | Same. |
| S8: exception with the secret in the message, debug log on | No secret in the log. No secret in the body. | Same. |
| S9: second `runCron` on the same library object, exception before the budget exists | Response has the data of the first run (`rows: 30`, `chars: 600`), and the counter gets 600 a second time. | Same. |

## Strengths

- **Fix 1 uses one clock.** `now()` is the only time source for `createBatchBudget` (`:259`) and the three checks (`:327`, `:362`, `:378`). The fake clock in the test thus controls the real checks, not a copy of them.
- **Fix 1 does not change the admin flow.** With `rowCap = 0` the condition at `:313` is false, so the new check cannot run. S5 gives the same result with and without the fix. `now()` returns `microtime(true)`, which is the earlier expression.
- **Fix 1 changes a run only after a flush with empty results.** If the flush wrote all its groups, `rowsQueued` is still at the cap and the check at `:333` stops the run in each case. Thus the default flow (no empty results) is the same as before.
- **Fix 1 bounds a run of only empty results.** The loop stops at the first flush after 10 s. One Google call can go above the limit, not more.
- **Fix 2 gives correct data for a Google-side exception.** `testCronError` shows `rows: 2`, `chars: 6000`, `table: oc_a` after two good batches, and the counter gets 6000 one time. The `table` field is set before the table starts (`:69`), so a failure in table analysis also names the table. A failure before the first table gives `table: ""`. A null budget gives the `cronError` defaults (S7).
- **Fix 3 is complete for the exception path.** `cronFailure` calls `hideSecret` one time and uses the result for the log and the response (`:196-202`). S8 confirms it with the debug log on.
- **Fix 4 uses the real code.** `runCron`, `cronFailure`, `translateNextBatch`, `translateTableWithBudget` and `translateTableTexts` are the production code. `FakeDb` gives only the lock result, and `FakeConfig` gives only the three settings. The tests also assert that the lock is released one time on each failure path.
- **Fix 5 is correct on PHP 5.6 to 8.x.** `JSON_PARTIAL_OUTPUT_ON_ERROR` exists from PHP 5.5, and the `defined()` guard is safe. With the flag, a bad string becomes `null` and the other fields stay. No PHP 7 syntax is in the controller.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

**1. The time check of fix 1 can stop a run before the first write, and the same stop then occurs in each subsequent run.**
`sources/upload/system/library/client_translate_expert.php:327-331`.

The check does not look at `rowsDone`. If the 10 s are used before a group is written, the run returns `rows: 0`, `done: false`, `error: ""`. The skip set is for one run only, so the next run sends the same empty groups again and stops at the same point. The code before fix 1 made progress in these cases (S1, S2, S3).

Two conditions cause it:

- A small cap and a sequence of empty results that uses more than 10 s but is shorter than one page (S1).
- More than 10 s used before the first row, and the first `rowCap` rows all give empty results (S2, S3). The time before the first row includes the scan of the finished tables for "All tables", which is a documented limit. S3 uses the default cap of 100.

Both conditions are rare, and the time before the first row was not measured on a real store. The fix also gives the result that review v2 asked for: a sequence of 100 or more empty groups gave `rows: 0` before fix 1 too, only slower. Thus this is a trade-off, not a clear defect. But "progress per run when possible" is not kept in S1 to S3.

Possible fix: apply the check at `:327` only when `$budget->rowsDone > 0`, and use a larger limit (for example 30 s) when `rowsDone` is 0. Then a run of only empty results stays bounded, and the cases S1 to S3 write rows again. If the current behavior is accepted, add one sentence to the limits in `README.md:24`.

**2. `testCapTimeBudget` does not show what the run gives.**
`tests/test_client_translate_expert_translate_loop.php:489-499`.

The test has 5 normal groups after the empty groups, but it asserts only `apiCalls <= 5` and `done === false`. The run returns `rows: 0`, and that result is not asserted. An assertion on `rows` and on the exact call count (4) makes the selected behavior of item 1 visible in the test.

**3. On a DB write exception, `rows` is too high by one group and the sent characters are not counted.**
`client_translate_expert.php:473` and `:480`, with `:199-207`.

`rowsDone` increases before `insertTranslatedTableTextValue`. If the write throws, the response shows a group that was not written (S6: `rows: 3`, 2 written). Fix 2 makes this value visible. Fix: increase `rowsDone` after the write.

In the same case the batch was sent to Google, but `charsDone` does not include it, so the counter and `chars` are both too low by one batch (S6: 600 sent, 0 counted). `chars` and the counter agree with each other. The second part is earlier behavior, not a result of fix 2.

**4. `cronFailure` can use the budget of an earlier run.**
`client_translate_expert.php:198-200`.

`runCron` does not clear `_lastBudget`. If an exception occurs before `createBatchBudget` (for example in `resetCharCountOnNewMonth`), `cronFailure` uses the budget of the last call on the same object. S9 shows the old `rows` and `chars` in the response and a second count of the same 600 characters. The cron controller makes a new library object for each request, so this path is not reachable today. Fix: set `$this->_lastBudget = null` at the start of `runCron`.

**5. Fix 3 has no test, and two text paths are still not redacted.**

- The loop test passes when the log line uses the raw message (mutation 2). The stub sets `_debug = false` and the `Log` stub drops the text. A `Log` stub that records, as in `tmp/v3/stub.php`, is sufficient.
- `client_translate_expert.php:157`: `message` of the non-exception path does not go through `hideSecret`. `details` on the next line does.
- `client_translate_expert.php:28` and `:41`: `translate` writes the full Google result, with `additional_message`, to the debug log without `hideSecret`.

This review found no real source that puts the cron secret into these texts. This is a consistency item, as in review v2.

**6. The fallback body of fix 5 does not have `message` and `details`.**
`sources/upload/catalog/controller/extension/module/client_translate_expert_cron.php:24`.

`README.md:22` says that a `translate_failed` answer has `message` and `details`. The fallback string has the six base fields only. With the flag set, `json_encode` does not return `false` for bad UTF-8, so the fallback is almost not reachable. Fix: add `"message":"","details":""` to the string.

Also, with the flag a bad `message` becomes `null`, not a string. The client keeps `error`, `rows` and `chars`, which is the important part. No change is necessary.

**7. `testCronError` cannot run on PHP 5.6.**
`tests/test_client_translate_expert_translate_loop.php:154-155`.

A method call on `null` is a fatal error on PHP 5.6, not an `\Error`, so the test script stops there. The library is correct on 5.6. This matters only if the tests must run on 5.6.

## Checked, no finding

- **Endless loop.** The new `break 2` only adds an exit. No new path continues the loop.
- **Flush after the `while` loop.** At `:327` the pending arrays are empty, so the flush at `:371` does not run after the `break 2`.
- **Characters on the new exit.** `charsDone` gets the batch before the check, so the characters of a stopped run are counted one time.
- **`done` on the new exit.** It is `false`, so the client calls again.
- **`chars` after `addCharCount` throws.** `$charsCounted` is true, so `cronFailure` does not count again. The response shows the characters that were sent.
- **`details` key.** It is present on each `translate_failed` path through `runCron`.
- **Lock.** It is released one time on each path of the probe.
- **Google API key.** The fixes add no new text source to the log or the response.
- **Multi-version rules.** The fixes add no route, class, setting key, UI string or language string. `\Throwable` and `\Exception` have a backslash. No PHP 7 syntax was added.

## Assessment

**Ready to merge?** Yes.

The five fixes do what review v2 asked, the admin flow is the same, and the new tests run the production code. Item 1 is the only item that needs a decision: fix 1 exchanges a slow run for a run that can stop with no write in two rare conditions, and the stop then repeats. Items 3, 4 and 6 are one-line changes. The remaining risk is the same as before: no run with a real Google key has occurred.
