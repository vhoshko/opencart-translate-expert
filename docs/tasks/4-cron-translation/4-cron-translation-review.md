# 4-cron-translation review

Scope: `git diff HEAD` and the three new files, against `4-cron-translation_plan.md`. The facts in the brief (build, tests, package greps, docker smoke test) are ground truth. This review did not measure them again.

## Method

The smoke test did not cover a successful translation. Thus this review did two things for the batch loop:

- It read `translateTableWithBudget`, `translateTableTexts`, `translateNextBatch` and `runCron` line by line.
- It ran the loop in a simulation. A subclass of `LibraryClientTranslateExpert` replaced the SQL row query, the Google call and the DB write with stubs in memory. The same script ran against the `HEAD` library and the new library.

The simulation uses the real loop code. It does not use a real database or the real Google API. The scripts are outside the repository, in the session scratchpad (`harness.php`, `harness2.php`).

Simulation results that agree with the plan:

| Case | Result |
|---|---|
| Admin `translateTable`, 255 groups, 60 characters each | Same result shape as `HEAD` (`success`, `done`, `translatedCharCount`). One call, `done: true`. |
| Cron, one table, cap 100, `value_count` 7 | 105 rows for each run (15 groups). The run stops before the next group. `done: true` on the last run. |
| Cron, All tables, cap 100 | Run 1: 100 rows in the first table, `done: false`. Run 2: 30 + 45 rows across tables, `done: true`. |
| Cron, cap 1 | One group for each run. No run has zero rows. |
| Cron, 3000-character texts | 177 API calls, no text sent twice, `chars` equals the sent characters. |

In each of these cases, the sum of `chars` was equal to the characters that the stub received. No text went to the stub twice.

## Strengths

- The controller is thin. All logic is in the library, and the pure rules are in `ClientTranslateExpertCronHelper` with 46 tests.
- The order of checks in `runCron` is safe. The secret check is first, so a caller without the secret cannot learn the module status or the parameter rules. A saved secret that is not valid always gives `forbidden`.
- All request values that go into SQL are safe. The language ids and the product filters are integers. The table name must be in `getTables()`. The mode is one of three fixed values.
- `GET_LOCK` is released in `finally`, so each return path and each exception path releases it. The lock name is 40 characters, which is below the MySQL limit of 64.
- The duplicate guard corrects a real defect in `HEAD`. In the simulation, `HEAD` sent 108 of 255 texts two times and reported 21780 characters for 15300 characters of text. The new code sent each text one time and reported 15300.
- The empty-result guard stops a data loss in `same_value` mode. `HEAD` wrote the empty text over the target value.
- The counter move is behaviour-identical. The core library sets the `module_` key names at runtime for `VERSION >= 3.0`. Before, `build.sh` wrote the same names into the model for the 3.0 and 4.x packages. The core library is not in the 3.0 rewrite list, so there is no double prefix. The usage group name `client_translate_expert_usage` did not change.
- `build.sh` has an entry for each new item: the two setting keys in both lists, the helper in the OC4 library list, and the catalog controller move, namespace, class name and `new X(` rewrite.
- No new line uses PHP 7+ syntax. `finally`, `catch (\Exception $e)`, the `array` type hint and class constants are all valid on PHP 5.6.
- The templates agree. The `.tpl` and the `.oc4.twig` have the same ids, and both escape the secret and the cron URL. The three language files have the same eight new keys.
- The cron link has no "all languages" problem. The target language select has no empty option, so `langIdTo` is always an id.

## Issues

### Critical (must fix)

None.

### Important (should fix)

**1. Always-empty groups count against the row cap, and they can stop all progress.**
`sources/upload/system/library/client_translate_expert.php:266-270` (cap check) and `:395-403` (skip).

The plan says that a group with an empty Google result is "not counted against the cap". The code adds `value_count` to `rowsQueued` when it queues the group (`:289`). It subtracts the value only after the flush (`:400`). But when the cap check is true, `break 2` leaves the read loop. The flush then runs, and the run ends with `done = false`. The run does not use the rows that the subtraction made free.

Simulation results, cap 100:

| Rows at the start of the row order | Result of each run |
|---|---|
| 40 always-empty groups, then normal groups | 60 rows, not 100. Each run sends the 40 empty texts again. |
| 120 always-empty groups, then normal groups | `rows: 0`, `done: false`, 3000 characters counted. Each subsequent run is the same. |
| 1 always-empty group with `value_count` 500, then normal groups | `rows: 0`, `done: false`. Each subsequent run is the same. |

In the last two cases the cron job never completes and never translates the rows after the block. Each run pays for the same characters. The JSON gives no error. One group is sufficient when its `value_count` is equal to or more than the cap, because the query groups equal texts.

This goes beyond the known limit in the plan. The known limit is a retry (and a cost) for each run. It is not a stop of all progress. The requirement says that repeated hits progress through everything.

Not verified: this review did not measure how frequently the Google API returns an empty text for a text that is not empty. The stop occurs only if that condition occurs.

Fix: when the cap check is true and the pending batch is not empty, flush the pending batch first. Then set `offset` to 0, clear the pending arrays and do the cap check again. Stop the run only if `rowsQueued` is still at or above the cap. The skip set prevents a second queue of the empty groups, so this loop ends.

**2. No automated test covers the batch loop.**
`tests/test_client_translate_expert_cron_helper.php` tests only the pure helper. The row cap, the duplicate guard, the empty-result guard, `done` and the character sum have no test, and the smoke test did not reach them. These are the parts with the most risk, and they also changed the admin flow. Issue 1 shows that reading alone did not find a defect there.

Fix: add a test that makes a subclass of `LibraryClientTranslateExpert` and replaces `getLanguages`, `getTableToTranslateAnalizationInfo`, `getTextToTranslateRows`, `translate`, `insertSameTranslatedText` and `insertTranslatedTableTextValue`. All of them are `protected` or `public`, so no production code must change. Assert these items: each text is sent one time, the sum of `chars` equals the sent characters, a run stops at the cap, and the three cases of Issue 1.

### Minor (nice to have)

**3. `runCron` does not catch `\Error`.** `client_translate_expert.php:165`. On PHP 7+, a `TypeError` or a different `\Error` in the batch does not go into the `catch (\Exception $e)` block. `finally` releases the lock, but the characters of the completed batches are not counted and the response is a PHP fatal error, not JSON. A second `catch (\Throwable $e)` block with the same body is valid syntax on PHP 5.6. It does nothing there.

**4. A counter failure can count the characters two times.** `client_translate_expert.php:143-145` and `:168-169`. `addCharCount` is in the `try` block. If its `UPDATE` is successful and its subsequent `SELECT` throws, the `catch` block adds `_lastBudget->charsDone` again. The probability is very low. Set a flag (or set `_lastBudget->charsDone` to 0) immediately before the call to remove the risk.

**5. The bulk amount rule exists in two places.** `admin/controller/extension/module/client_translate_expert.php:64-65` and `:280-281` write `100` and `100000` again. `ClientTranslateExpertCronHelper::clampBulkAmount` has the same rule. If one changes, the other does not. A model method that delegates to the helper (as `isValidCronSecret` does) removes the copy.

**6. `rows: 0` with `done: false` has no explanation in the response.** After the fix of Issue 1 this can still occur when a run sends only always-empty texts and then the time limit stops it. A cron operator cannot tell this from a normal run. A count of skipped groups in the JSON body makes it visible.

**7. `additional_message` is dropped.** `client_translate_expert.php:150`. The implementer recorded this already. On a Google error the caller sees only "Internal error." The real cause is not in the response. Only the holder of the secret can read the response, so the cause is safe to include.

**8. Admin character count is now lower than before for the same work.** This is a correction, not a defect: `HEAD` counted the duplicate sends, and it also sent them. Mention it in the release notes, because a user who compares the counter between versions will see the difference.

## Checked, no finding

- **Admin `translateTable` result and `done`.** The result shape is the same on success and on failure. The time rule is the same (10 s, only after the first written batch). A fresh budget with `rowCap = 0` makes the cap check always false.
- **Loop end.** Without a flush, `offset` always increases. With a flush, the written rows leave the query. Skipped groups are in the skip set. No path reads the same page again without a change.
- **Cap rule.** The check is `rowCap > 0 && rowsQueued > 0 && rowsQueued >= rowCap`, before a group is queued. The first group always runs. The budget is shared across tables, so the cap is for the run, not for each table.
- **Character count.** `charsDone` grows only after a successful flush. `runCron` adds it one time on success, one time on a failed batch, and one time in the `catch` block. The paths do not overlap, apart from Minor 4.
- **Month reset.** `runCron` calls `resetCharCountOnNewMonth(date('m'))` before the batch, with the same setting and the same order as the admin controller.
- **Secret in logs.** `runCron` logs only the parsed filters and the cap. `parseParams` does not return the secret.
- **Upgrade.** No schema change. An existing install has no secret, so the endpoint answers `forbidden` until the user saves one. The bulk amount falls back to 100.

## Assessment

**Ready to merge?** With fixes.

The security model, the build rewrites, the counter move and the admin flow are correct, and the duplicate guard is a real improvement. Fix Issue 1 before the merge, because one always-empty group can stop the cron job with no error and with a cost on each run. Add the loop test of Issue 2 in the same change, so that the fix and the cap rule have an automated check.
