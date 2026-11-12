# 38-analysis-char-count-mismatch

## Settings

- Planning model: Opus 5.5 (inline). Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Google API key: first none (user decision). On 2026-11-12, after the plan approval, the user gave a key in chat. Use it for the integration tests and probes only. Do not write the key into any file. A docker store run needs a pushed branch (the host checkout is separate), so it is not part of this task.
- Plan approved by the user on 2026-11-12.
- Build and tests: yes. Commit, push, PR: not agreed. The workflow does not commit.
- Standing auto-approve for review findings: not granted.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/38. The analysis tab shows a character count for a table. The translation run adds a different number to `client_translate_expert_char_count`. Google Cloud can bill a third number. The user also asked if the text that goes to Google is different from the text that the analysis measures.

Base: `origin/main` at `0788da8`. That commit contains PR #39 (cron), which already changed the translate loop:

- #39 added a duplicate guard (`$queuedKeys`) that stops a second send of a group in the pending batch. This fixes a part of possible cause 1.
- #39 moved the counting into the library (`addBatchChars`). Cron counts after each batch. The admin flow counts at the end of the request.

All OpenCart versions (1.5–4.1) run the touched code. The library is the same on all versions.

Key files (lines on `0788da8`, LTE = `sources/upload/system/library/client_translate_expert.php`, CORE = `client_translate_expert_core.php`, CTRL = admin controller):

- Analysis count: LTE:601 `mb_strlen($row['text_from'])`, raw DB value, one count for each group `GROUP BY t2.language_id, t.<col>` (LTE:816).
- Translate loop: LTE:300-400. Batch size and counter use `mb_strlen($textToTranslate)` of the raw DB value (LTE:354, LTE:368).
- Text before the send: LTE:433-451. `decodeHtml` (CORE:733) decodes a text that contains `&lt;`, `&amp;`, `&quot;` or `&#39;`. Then `translate()` (LTE:17-21) calls `trimValue` (CORE:669), which trims each text and drops empty texts.
- Same-value check: LTE:488-489 compare `trim(strtolower(...))`. The SQL filter (LTE:804-808) compares `LOWER()` with all ` `, `\t`, `\n`, `\r` removed.
- Admin counter: CTRL:478-480 stores `translatedCharCount` only if it is set. The failure returns of LTE:337, 358, 389 do not set it.
- Manual field translate: CTRL:446-458 counts `mb_strlen` before trim, and also counts when the translation fails.
- Language file analysis: `client_localization_translate_expert.php:225` counts untrimmed `mb_strlen`.

Investigation results for the 4 possible causes in the issue:

1. Offset reset and second read: partly fixed by #39. It is still open for a row that SQL selects again after its write (see difference D3 below).
2. Analysis split after 1 s: deep mode (the default) continues correctly. A failed request stops the analysis and the partial sum stays on screen. This is UI behavior, not a count rule. Out of scope.
3. HTML entities: confirmed. The module counts the encoded text. Google gets the decoded text.
4. `strlen` in `vh_google_translator.php`: refuted as a count cause. The module batches are at most 5000 characters, so the 50,000-byte split does not occur.

Differences between the three numbers:

- D1. Entity decoding: `&lt;p&gt;` counts 9, Google gets `<p>` (3). Analysis and counter are both higher than the Google billing.
- D2. Outer whitespace: the module counts it, `trimValue` removes it before the send. Analysis and counter are higher than the billing.
- D3. A row stays selectable after its write. Examples are a Cyrillic case change (`СУПЕР` → `Супер`) or a change of inner spaces (`10 %` → `10%`). PHP says "different", so no `translate_expert_same_translations` record is written. SQL says "same", so the next read selects the row again. The row is sent and counted on every read, and the table never gets to done. The counter is higher than the analysis. Modes `both` and `same_value`, admin and cron.
- D4. Admin flow: if a later batch in the same request fails, the counter loses the characters of the batches that went to Google before the failure.
- D5. Manual field translate counts untrimmed text and counts a failed call.
- D6. Twins (new effect of #39): one text in a group with no target row and in a group with an empty target row. Analysis counts both groups. If `value_count > 1`, one write fills both, so the counter is lower than the analysis. This is rare. Out of scope (recorded only).

## Approach

Use one length rule for the analysis, the batch size and the counter: the length of the text exactly as it goes to Google. Add one protected helper in the core library, `getBillableCharCount($text)`. It decodes the text with `decodeHtml` (the same entity condition), then trims it with `trim()`, then returns `mb_strlen`. Use it at LTE:354, LTE:368 and LTE:601. Make the PHP same-value check use the SQL rule: remove ` `, `\t`, `\n`, `\r` and use `mb_strtolower(..., 'UTF-8')`. Then a row that SQL sees as "same" gets its `translate_expert_same_translations` record and does not come back. Put `translatedCharCount` into the failure returns of the translate loop, so that the admin counter keeps the batches that Google already got. In the manual translate action, count trimmed text and count only a successful call. In the language file analysis, count trimmed text.

Design correction to the issue: the issue says "the analysis and the translation both count `mb_strlen()` of `text_from`". That is true, but both are different from what Google gets (D1, D2). The fix changes both numbers to the Google rule, not only the analysis.

## Assumptions

- ❓ unverified: Google bills the characters of each `q` value as the module sends it (after decode and trim), including HTML tags. No key is available. The Cloud Translation pricing page says that Google bills each character of the input. If this is false, D1 and D2 change the counter in the wrong direction by the size of the entities and the outer spaces only.
- ✅ verified: the text sent to Google is the decoded, trimmed text. Evidence: LTE:444-447 (`decodeHtml`), LTE:21 (`trimValue`), CORE:669-689, CORE:733-746.
- ✅ verified: the SQL "same value" rule removes ` `, `\t`, `\n`, `\r` and uses `LOWER()`. The PHP rule uses only `trim(strtolower())`. Evidence: LTE:804-808 and LTE:488-489. `insertSameTranslatedText` (CORE:346-367) hashes the text with the same SQL rule, so a record for the translated text excludes the row through `stt2`.
- ❓ unverified: MySQL `LOWER()` and PHP `mb_strtolower` give the same result for the store texts. The collation can also treat `Cafe` and `Café` as equal, and PHP cannot copy that. The residual case is rare. If it occurs, the row behaves as today (D3 stays for that text only).
- ✅ verified: cron counts each batch through `addBatchChars` and does not read `translatedCharCount` from the failure return (LTE:61-100, LTE:271-276). The D4 change has no effect on cron.

## Steps

- [x] Code (CORE): add `getBillableCharCount($text)` and `normalizeForSameCheck($text)` as protected helpers. Keep PHP 5.6 syntax.
  Findings: both helpers are next to `decodeHtml`. `getBillableCharCount` calls `decodeHtml` itself, so the entity condition stays in one place.
- [x] Code (LTE): use `getBillableCharCount` at the batch-size check, the counter sum and the analysis count. Use `normalizeForSameCheck` in the same-value check (both comparisons).
- [x] Code (LTE): add `'translatedCharCount' => $budget->charsDone` to the three failure returns of `translateTableWithBudget`.
- [x] Code (CTRL): in `translate()`, count `mb_strlen(trim(...))` and store the count only if `$result->success` is true.
  Findings: no test harness exists for the admin controller. No regression test covers this change.
- [x] Code (`client_localization_translate_expert.php:225`): count `mb_strlen(trim(...))`.
  Findings: no test harness exists for the language file analysis. No regression test covers this change.
- [x] Tests (`tests/test_client_translate_expert_translate_loop.php`): add regression tests:
  - an entity text and a text with outer spaces: the counted characters equal the billable length of the sent texts;
  - a Cyrillic case change and an inner-space change: the run writes a same-translation record and does not send the row again;
  - a failure in the second batch of an admin request: the result has `translatedCharCount` for the first batch;
  - the analysis count equals the counted characters for the same fixture rows. Run the real `getTableToTranslateAnalizationInfo` with fake columns if possible. Otherwise test the shared helper, and record the gap.
- [x] Prove that the new tests fail with the fix disabled (bug fix rule).
  Findings: the tests call the real `getTableToTranslateAnalizationInfo` (fake `getColumns`), so no gap. With both library files from `origin/main`: 7 FAIL, each on the expected assertion (136 instead of 102 three times, no same-translation record twice, `translatedCharCount` NULL). Restored from copies, `cmp` clean, 166/0.
- [x] Bump version + build + test
  Findings: 04.01.0144 green. php -l clean, PHP 5.6 check clean. Tests: cron helper 51/0, route filter 40/0, translate loop 166/0, Google translator 24/0/6 skipped without key and 39/0 with the user key. Both new helpers are in every package (oc1.5–oc4.x).
- [x] Independent review → 38-analysis-char-count-mismatch-review.md
  Findings: Fable 5.2. Ready to merge with fixes: 0 critical, 1 important, 7 minor. The table path is correct.
- [x] Address review findings (the user picks which to fix or skip)
  Decisions (user, 2026-11-12): fix I1, M5, M1, M2, M6. Record M3, M4, M7 as gaps.
  Findings:
  - I1: `client_localization_translate_expert.php` counts `trim(html_entity_decode($text))`, the same call as the admin controller (default flags, so it follows the PHP version).
  - M5: the three flush conditions use `count($textRowsToTranslate) > 0`, so a text with a sent length of 0 (`\x0B`) is flushed again, as on the base.
  - M6: the JS failure branch of `doTranslateTableExpertRecursive` calls `showTranslatedCharCount()`.
  - M1, M2, M5 tests added (translate loop 172/0). Proof: the M1 test first passed with the mutation (600 + 3000 stays under 5000). It was changed to 600 + 4800 raw, and then it failed with the mutation. M5 test: 2 FAIL on the version before the M5 fix. All new tests on `origin/main` library files: 9 FAIL on the expected assertions. Restored, `cmp` clean.
  - Known gaps (not fixed): M3, no test for the `text_to` side of the same-value check. M4, the stub cannot show that SQL does not select the row again (only a store run can). M7, three rare undercounts: the "Internal error." return after a successful Google call, an exception after the Google call in an admin request, and a partial failure of a split request in the manual `translate` action.
- [x] Bump version + build + tests (verify loop)
  Findings: 04.01.0145 green. php -l, node --check and the PHP 5.6 check clean. Tests: 51/0, 40/0, 172/0, 24/0/6 skipped (no key for this run).
- [x] Re-review? (ask the user)
  Decision (user, 2026-11-12): yes.
- [x] Independent review → 38-analysis-char-count-mismatch-review-v2.md
  Findings: Fable 5.2. Ready to merge: 0 critical, 0 important, 3 minor. M5 has no regression (differential fuzz, 400 cases). The OpenCart core probe shows that `Request::clean()` runs `htmlspecialchars` on each POST value (1.5.6.4 to 4.1.0.0), so I1 is correct.
  Decisions (user, 2026-11-12): fix minor 1 and minor 2, record minor 3, then stop (no third review).
  Findings:
  - Minor 1: the stub has `trimLikeReal`, which drops the texts that `trim()` makes empty, as `trimValue` does. `testZeroLengthTextIsSent` now asserts `success === 0` (visible error), as in production.
  - Minor 2: a cron case (row cap 1, `\x0B` row first) is added. A mutation of line 333 back to the length check passes all tests. This is correct and not a test gap: after `break 2`, the final flush (line 385) always sends the queued rows, so the result is the same. Line 353 is equivalent in the same way (the rows merge into one batch, which fails the same way).
  - Minor 3 (recorded only): JS `String.trim()` removes a literal U+00A0 at the edge of a language-file text, and PHP `trim()` does not. The analysis can be 1 character higher for each such text.
  - Scope addition (user, 2026-11-12): the new test found an older bug. The two count-mismatch messages in `translateTableTexts` interpolated the array `$translationResults->texts` in a double-quoted string ("Array to string conversion" warning on PHP 8, notice on PHP 7). Both now use a single-quoted string plus the counts. Translate loop 174/0 with no warning.
- [x] Bump version + build + tests (verify loop 2)
  Findings: 04.01.0146 green. php -l, node --check and the PHP 5.6 check clean. Tests: 51/0, 40/0, 174/0, 24/0/6 skipped.
- [x] Improvements pass → 38-analysis-char-count-mismatch-improvements.md

Dropped steps: templates and language strings (no UI change).

## Live probe (2026-11-12, user key, key not stored)

Script: `tmp/probe_live_google.php` (key from the `GOOGLE_KEY` env var), output in `tmp/probe_live_google.out`. About 285 characters were sent in total.

- ✅ D3 confirmed with real Google: `10 %` (en → uk) comes back as `10%`. The old PHP rule says "different", the SQL rule says "same". On a store this row is sent again on each read. Other candidates (brand names, `SUPER SALE`, Cyrillic upper case) came back unchanged or translated, so the old rule and the new rule agree on them.
- ✅ Count fix with real Google, 6 texts with entities, outer spaces and a newline: old rule 136, analysis 102, counter 102, sum of `q` lengths sent 102.
- ✅ A `"0"` text comes back as `0`. It does not break the batch (the investigation claim was wrong).
- Observation (out of scope): one text with `</` sends the whole batch as `format=html`. Then Google drops the newline of another text in the same batch (`"Line one\nline two"` → `"Рядок один, рядок два"`). This existed before.
- ❓ still unverified: the Google billing number. The API does not return billed characters. The user can compare the quota usage in the console with the 285 characters of the probe.
- Quota observation (user, 2026-11-12): "v2 and v3 general model characters per day" showed 471 of 16,000. This session sent 338 characters (probe part 1: 176, part 2: 102, `"0"`/`"Hello"` probe: 5, one integration test run: 55). The user sent the other 133 characters in their own test requests. The total agrees with the sent text, but it cannot show how Google counts (characters or bytes, HTML tags). A controlled test can show this: one known request at a time, then read the increase in the quota.

## Done

- Landed: one length rule (`getBillableCharCount`: decoded, trimmed text, as Google gets it) for the table analysis, the batch size and the counter. The PHP same-value check uses the SQL rule (`normalizeForSameCheck`), so a row such as `10 %` → `10%` is not sent and counted again. An admin failure keeps the count of the batches that Google already got, and the page shows the stored total. The manual translate action counts trimmed text and only on success. The language-file analysis counts the decoded, trimmed text. The count-mismatch message has no "Array to string conversion" warning.
- Final version: 04.01.0146 (`releases/v04.01.0146/`). Two reviews (Fable 5.2): 1 important + 7 minor, then 3 minor. Fixed: I1, M1, M2, M5, M6, v2 minor 1 and 2. Translate loop tests 166 → 174.
- Live evidence (user key, not stored): for 6 texts, the old count was 136, and the analysis, the counter and the sent text are now 102. Google billing is still not observable through the API.
- Release note: the analysis count and the module counter are lower than before for text with HTML entities or outer spaces. The new numbers are the characters that Google gets.
- Deferred, recorded above: M3, M4, M7, twin groups, accent collation, mixed-batch `format=html` newline loss, analysis UI stop on failure, U+00A0 trim difference on language files. Commit, push and PR are not done (the workflow does not commit).
