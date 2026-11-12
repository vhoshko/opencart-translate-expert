# 4-cron-translation review v4

Scope: the changes since `4-cron-translation-review-v3.md`, in `git diff HEAD` and the new files.

- A: cron time limit and character count for each batch.
- B: items 2 to 7 of review v3.
- C: an empty Google result becomes the source text.
- D: the new loop test.

The facts in the brief are ground truth (`php -l`, the four test results, build 04.01.0136, the three mutation results). This review did not measure them again. This review did not run a build script.

## Method

- This review read `runCron`, `cronFailure`, `createBatchBudget`, `addBatchChars`, `translateNextBatch`, `translateTableWithBudget`, `translateTableTexts`, `getTextToTranslateRows`, `insertSameTranslatedText`, `insertTranslatedTableTextValue`, the translator class, the cron controller and the loop test.
- It ran the loop test (127 passed) and the cron helper test (46 passed) on PHP 7.4.
- It made nine mutations in copies of the library under `tmp/v4/mut/` and ran a copy of the loop test against each. The copies were deleted. The sources and the tests were not changed.
- It ran a probe with the real SQL: `docs/tasks/4-cron-translation/tmp/v4/probe_sql_v4.php`. Command: `php docs/tasks/4-cron-translation/tmp/v4/probe_sql_v4.php`.

The probe uses the real `translateNextBatch`, `translateTableWithBudget`, `translateTableTexts`, `getTextToTranslateRows`, `insertSameTranslatedText` and `insertTranslatedTableTextValue`. Only the Google call and the table analysis are stubs. The SQL runs on the MariaDB 10.6 test container, in one session, on `TEMPORARY` tables with the prefix `tev4_`. Nothing stays in the database.

No translation with a real Google key has run. All Google results in this review come from stubs.

### Probe data

Source language 1, target language 2, one text column.

| id | Source text | Target before |
|---|---|---|
| 1 | `.` | no row |
| 2 | `Dot &amp; dash` | empty |
| 3 | `<p>Hi</p>` | `<p>Hi</p>` |
| 4 | `KEEP` | `Existing translation` |
| 5 | `Same Text` | no row |
| 6 | `Same Text` | empty |
| 7 | `Same Text` | `same text` |
| 8 | `normal one` | no row |
| 9 | `  Padded Text  ` | empty |
| 10 | `Line\nTwo &quot;q&quot;` | no row |

The stub returns an empty result for each text, but not for id 8. The probe ran each mode two times: one time with `''` and one time with `" \n\t "` as the result. The two results were the same.

### Probe results for change C

| Mode | Rows selected | Result after run 1 | Selected in the same mode after run 1 |
|---|---|---|---|
| `only_empty` | 1, 2, 5, 6, 8, 9, 10 | 1, 2, 5, 9, 10 hold the exact source text. 8 holds the translation. 10 hash rows. | Only id 6 (see item 1) |
| `same_value` | 3, 7 | 3 is the same. 7 changes from `same text` to `Same Text`. 4 hash rows. | None |
| `both` | 1, 2, 3, 5, 6 (count 2), 8, 9, 10 | As `only_empty`, and 3 gets a hash. 12 hash rows. | Only ids 6 and 7 (see item 1) |

In each of the six runs, id 4 stayed `Existing translation`. After the second run, each mode selected nothing in its own mode.

### Mutation results (loop test)

| Mutation | Result |
|---|---|
| M1: `rowsDone` increases before the DB write | 1 failure (`testCronWriteException`) |
| M2: no `_lastBudget = null` in `runCron` | 2 failures (`testCronLastBudgetReset`) |
| M3: `message` without `hideSecret` | 2 failures (`testCronMessageHidesSecret`) |
| M4: the source text is used for the write, but after the same-text comparison (no hash) | 1 failure (`testEmptyResultUsesSource`) |
| M5: `cronFailure` counts the characters again | The test run stops with failures |
| M6: the admin budget also counts for each batch | 1 failure (`admin translateTable does not count by itself`) |
| M7: `charsDone` increases before `addCharCount` | 0 failures (see item 3) |
| M8: `set_time_limit` and `ignore_user_abort` removed | 0 failures. A unit test cannot show this. |
| M9: time check after the last flush removed (`:374-379`) | 0 failures (see item 5) |

## Answers to the questions

### C: empty result becomes the source text

- **The rule is correct in each mode.** The probe table shows it with the real SQL.
- **`only_empty`.** The target holds the source text. The source query does not select source texts that are only spaces, tabs or line ends, so the target is not empty for the SQL. The row is not selected again.
- **`same_value`.** The hash that `insertSameTranslatedText` stores is equal to the hash that the `stt1` join calculates. Both come from the same stored source text. The row is not selected again.
- **`both`.** Both conditions are false after the write. The row is not selected again.
- **A row that one mode completes is also complete for the other modes.** After an `only_empty` run, `same_value` selects none of the rows that the run wrote.
- **It cannot overwrite a different translation.** The query selects a row only if the target is empty, is not there, or is equal to the source without case and white space. Thus the only target that changes is one that the mode already treats as not translated (id 7: `same text` to `Same Text`). The count-above-1 path uses the same `WHERE`, so it has the same limit.
- **HTML and entities.** The fallback writes `text_from`, which is the stored form. The decoded text that goes to Google is not used. Ids 2 and 10 keep `&amp;` and `&quot;` with no second encode. Id 9 keeps its spaces. The hash is also made from the stored form, so it agrees with the join.
- **`trim` and the SQL agree.** `trim` removes space, tab, line feed, carriage return, NUL and vertical tab. The SQL removes the first four. A result that `trim` finds empty is thus always replaced, and the replacement is never empty for the SQL.
- **A bad Google response does not use this path.** `callGoogleTranslateApiBatch` throws if the count is wrong or a `translatedText` key is missing (`vh_google_translator.php:239-247`). Thus only a real empty string from Google gets the source text and the hash.
- **Row cap and duplicate guard.** After each flush that has success, each group in the queue is written. `rowsQueued` and `rowsDone` are thus equal after a flush, and the cap needs no correction. The removal of the skip set leaves no dead code. See item 1 for a defect of the duplicate guard that is not a result of change C.

### A: count for each batch, time limit, lock

| Path | Count |
|---|---|
| Success | One `addCharCount` call for each flush. The sum is equal to the characters sent. |
| Google failure in batch N | Batches 1 to N-1 counted one time. Batch N not counted. |
| Exception in the Google call of batch N | Same as above. `cronFailure` adds nothing. |
| `addCharCount` throws | One call, no second count. The response `chars` does not include that batch (item 3). |
| DB write throws | The batch went to Google but is not counted. This is the known edge in the plan. |
| Admin | `countBatches` is false. The controller adds `translatedCharCount` one time (`admin/controller/...:478-480`). No double count (M6). |

- **Month reset.** It runs one time, in the `try` block, before the first batch. A failure there gives `translate_failed` with zero data and releases the lock.
- **900 s limit.** The check runs after each page of 100 groups and after the last flush. Between two checks, the code can make at most one Google call for each group of the page. The row cap also stops the run in that page. Each curl call stops after 30 s. Thus a run can go above 900 s by one page at most, and that is acceptable for a safety limit.
- **The "page of only empty results" case is gone.** Empty results are now written and counted against the cap as all other rows. They have no special path.
- **`set_time_limit(0)` and `ignore_user_abort(true)` with the lock.** Both calls come after `GET_LOCK` and have a `function_exists` guard. A second request gets `busy` immediately, because the lock timeout is 0. If the host stops the process, the DB connection closes and MySQL releases the lock. This review found no path that keeps the lock after the process ends.
- **Run with no work.** The time check needs `charsDone > 0`. A run that finds nothing to translate thus has no limit from the module, only the time of the table scan. This is the documented "All tables" limit, and `set_time_limit(0)` now removes the PHP limit for it too. No change is necessary.

### B and D: tests

- The loop test uses the production `runCron`, `cronFailure`, `translateNextBatch`, `translateTableWithBudget`, `translateTableTexts`, `createBatchBudget` and `addBatchChars`. The stubs replace only the row query, the Google call, the two DB writes, the counter and the clock.
- Each of items 2, 4 and 5 of review v3 has an assertion that fails without the fix (M1, M2, M3). Item 6 (the fallback JSON) and item 7 (PHP 5.6 guard with `class_exists('Error')`) are correct by reading.
- The time-limit test is exact: it asserts 2, 4, 4 and 11 calls, not a range.
- The test for change C asserts the written text, the hash call and its language pair, the row count and the number of runs.

### PHP 5.6 and OC 4 namespace

- The new lines use a class constant, `function_exists`, `@`, `finally` and `catch (\Throwable $e)`. All are valid on PHP 5.6.
- The library has no class reference without a backslash, apart from `ClientTranslateExpertCronHelper`. `build.sh` puts the helper in the same namespace. Functions and constants fall back to the global namespace.

## Strengths

- **Change C is one line, and it uses the existing path.** The source text goes through the same comparison, the same hash insert and the same write as a real "translated equals source" result. No second write path exists.
- **Change C uses `=== ''` after `trim`.** A translation of `0` is not replaced.
- **The fallback writes the stored source form.** This removes the risk of a double entity encode, which the usual path has through `encodeHtml`.
- **`addBatchChars` is the one place that counts.** The three flush points call it, the admin flag turns the DB count off, and `cronFailure` only reads the budget. The earlier `$charsCounted` flag is not necessary.
- **One clock and one limit field.** `now()` and `budget->timeLimit` serve the admin flow and the cron flow, so the fake clock controls the real checks.
- **`rowsDone` now shows written groups only**, and `_lastBudget` cannot carry data from an earlier run.
- **The docs agree with the code.** `README.md`, the three `README.*.TXT` files and the help strings give the 15-minute limit, the host limits and the empty-result rule. No `skipped` field is left in the sources or the docs.

## Issues

### Critical (must fix)

None.

### Important (should fix)

**1. The duplicate guard can leave a row, and the run then reports `done: true`.**
`sources/upload/system/library/client_translate_expert.php:311-313` and `:367-380`.

This defect is not in the changes since review v3. The real-SQL probe found it, and the stub tests cannot show it. Change C does not cause it.

The source query groups by `t2.language_id` and the source text. If one product has no target row and a second product has an empty target row, the same text is in two groups. The guard keeps the first group and drops the second. After a flush in the middle of a page, the code sets `offset = 0` and reads the rows again, so the second group gets its turn. After the last flush, no second read occurs.

Probe result with normal Google results (ids 5 and 6, text `normal dup`):

| Call | Result |
|---|---|
| Cron run 1, mode `only_empty` | `rows: 1`, `done: true`. Id 5 translated, id 6 still empty. |
| Cron run 2 | `rows: 1`, `done: true`. Id 6 translated. The text went to Google a second time. |
| Admin `translateTable`, call 1 | `done: true`, 1 row still not translated. |

Mode `both` gives the same result.

Why it matters:

- `done: true` is false for that run. The help text says "Call it again until `done` is `true`", so a script that obeys it stops with rows left.
- In the admin page, the user sees "done" while the analysis still shows characters. The code on `main` has no guard, so it wrote both groups in one call. This is a change in the admin flow.
- The same text goes to Google two times, in two runs.

The effect is small: the next run completes the row, and a periodic cron job corrects it without help.

Fix: set a flag when the guard drops a group (`:312-313`). After the last flush, if the flag is set, set `$done = false`. The next call then reads the rows again and finds the second group. Add a stub case with two groups that have the same text.

### Minor (nice to have)

**2. The `RELEASE_LOCK` query in `finally` can throw and replace the JSON response.**
`client_translate_expert.php:181-184`.

If the DB connection is lost in a run, `cronFailure` builds the response, but the query in `finally` throws again. The exception then leaves `runCron`, and the controller sends no JSON. MySQL has already released the lock in that case. Core behavior verified with the source probe: the `mysqli` driver throws on a query error (`system/library/db/mysqli.php`, tags 2.3.0.2 line 40, 3.0.3.8 line 48, 4.0.2.3 line 76, and `system/database/mysqli.php` line 41 on 1.5.6.4). A run of 15 minutes makes a lost connection more possible than a run of 10 seconds. Fix: put the release query in its own `try` block with empty `catch (\Exception $e)` and `catch (\Throwable $e)` blocks.

**3. If `addCharCount` throws, the response `chars` does not include the batch that was written.**
`client_translate_expert.php:261-266`.

`charsDone` increases after `addCharCount`. On a throw, the response shows the rows of the batch in `rows` but not its characters in `chars`. The counter has no double count. Fix: increase `charsDone` first, then call `addCharCount`. Mutation M7 shows that all 127 checks pass with that order. `testCronAddCharCountThrows` can then assert `chars`.

**4. `CLAUDE.md:87` still says "skipped groups".**
The comment for the loop test names a feature that change C removed. Change it to "empty results".

**5. Two test gaps.**

- The time check after the last flush (`:374-379`) has no test (M9). It applies only when a table has a second text column or a second target language, and the stub has one of each. A stub with two text columns is sufficient.
- The stub row query has no hash exclusion, so the loop test cannot show the `same_value` result of change C. The plan already records this. The probe of this review shows the result with the real SQL, but it needs the test container and is not a part of the test set.

## Checked, no finding

- **Endless loop.** Each written row leaves the selection of its mode (probe), so a second read from `offset = 0` always gets fewer rows.
- **Count above 1 with the hash.** The hash insert runs before the second query of `insertTranslatedTableTextValue`. In `same_value` and `both`, that query then drops rows whose target is already equal to the source. These rows need no write. Rows with an empty target are still written (id 6 in the second run).
- **`details` and `message`.** Both go through `hideSecret` on the non-exception path, and the fallback JSON in the controller has both keys.
- **DB error text.** The core driver puts the SQL text into the exception message, so a DB failure puts SQL into `message`. The caller holds the secret, and the SQL holds no Google key and no secret. No change is necessary.
- **Google API key.** No new line writes it to the log or the response.
- **Multi-version rules.** Changes A to D add no route, class, setting key, AJAX method, template string or language key. The earlier `build.sh` entries for the cron controller, the helper and the two setting keys are still there.
- **i18n.** The 15-minute text is in the help strings of `en-gb`, `ru-ru` and `uk-ua` and in the three `README.*.TXT` files. This review read the English text only.
- **Google usage.** Change C sends each text one time and then never again. Before, an always-empty text went to Google in each run.

## Assessment

**Ready to merge?** With fixes.

Changes A to D are correct. The real SQL confirms the new empty-result rule in the three modes, it cannot overwrite a different translation, and the count for each batch occurs one time on each path. Item 1 is not a part of these changes, but it makes `done: true` false in one data shape and changes the admin flow compared with `main`. The fix is a flag and one assignment, so do it before the merge. Items 2 to 5 are optional. The remaining risk is the same as before: no run with a real Google key has occurred.
