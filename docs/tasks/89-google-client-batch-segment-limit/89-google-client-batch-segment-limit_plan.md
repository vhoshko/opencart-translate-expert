# 89-google-client-batch-segment-limit

## Settings

- Planning model: Opus.
- Implementation model: Sonnet (`ote-implementer`).
- Review model: Opus.
- Review findings rule: fix all findings. Skip a finding only with a verified reason, and write the reason in the review file.
- Build and tests: bump the version and run `./build.release.sh` after each code change. Run all unit tests.
- Commit: no. Push: no. PR: no.
- Standing auto-approve: not granted.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/89

The localization tab sends one `translate` request with about 190 short texts. Google answers HTTP 400 "Too many text segments". The admin shows error code 1000. All OpenCart versions (1.5–4.x) use the same client library, so the bug affects all versions.

Root cause:

- `sources/upload/system/library/translate_expert/translate_expert_google_client.php:103` `translateInternalBatch()` groups the texts only by total length (`GOOGLE_TRANSLATE_MAX_LENGTH`, 50000 characters, line 6). It has no limit on the number of texts in one group.
- `translate_expert_google_client.php:167` `callGoogleTranslateApiBatch()` sends each group as one `q[]` array in one Google request.
- Many short texts fit under the length limit, so one request gets too many texts.

Callers that use the same path:

- `translate_expert_google_client.php:31` `translateBatch()` removes the empty, blank, null and "0" texts. Then it calls `translateInternalBatch()` and puts each result back at its input position.
- `TranslateExpertCore::translateInternal()` (`translate_expert_core.php`, about line 533) calls `translateBatch()` for the table translation.
- The localization JS (`client_translate_expert.js`, about line 2600) sends the full `textArr` of one file to the controller `translate()`.

Key test file: `tests/test_translate_expert_google_client.php`. The stub class `TestableTranslateExpertGoogleClient` (line 217) overrides `callGoogleTranslateApiBatch()` (line 243). It counts the calls and keeps only the texts of the last call.

## Approach

Add the constant `GOOGLE_TRANSLATE_MAX_SEGMENTS = 100` to `TranslateExpertGoogleClient`. The value is the same as `TRANSLATION_ANALITIC_SQL_LIMIT` in `translate_expert_core.php:13`. The client cannot read the core constant, because the client has no dependency on the core. In `translateInternalBatch()`, call `$flushBatch()` before a text is added if the batch already holds `GOOGLE_TRANSLATE_MAX_SEGMENTS` texts. Keep the length limit and the long-text path. One client call then sends several Google requests and returns one result array in input order. The JS, the controller `translate()` and the response format do not change. One admin request stays correct for a file of any size (5000 or more texts). No new limit research is in scope.

## Assumptions

- ✅ verified: the length check is the only grouping rule. Evidence: `translate_expert_google_client.php:128-140` checks only `strlen()` against `GOOGLE_TRANSLATE_MAX_LENGTH`.
- ✅ verified: the stub class can count each Google request and its size. Evidence: `callGoogleTranslateApiBatch()` is `protected` (line 167), and the stub overrides it (test file line 243).
- ✅ verified: `translateBatch()` maps the results back by index (lines 39-61). Thus, more Google requests do not change the position of an empty text.
- ✅ verified: the user agreed on the value 100. The issue text gives the same value.
- ❓ unverified: Google accepts 100 short texts in one request. No live API key is available for this task. The issue shows that about 190 texts fail and that 100 is the existing project batch size. If 100 is too high, the error stays for large files, and the constant needs a lower value.
- ❓ unverified: the cron path (`runCron()`) uses `translateBatch()` through the core. If it uses another path, that path does not get the fix. Task scope does not include the cron path.

## Steps

- [x] Code: in `translate_expert_google_client.php`, add `const GOOGLE_TRANSLATE_MAX_SEGMENTS = 100;` below `GOOGLE_TRANSLATE_MAX_LENGTH`. Add a short comment that names Google's "Too many text segments" error.
- [x] Code: in `translateInternalBatch()`, before the text is added to the batch, call `$flushBatch()` if `count($batchTexts) >= self::GOOGLE_TRANSLATE_MAX_SEGMENTS`. Keep the length check. Update the docblock to name both limits.
- [x] Code: keep the code PHP 5.6 compatible. Do not use `??`, type hints, nullable types or arrow functions.
- [x] Test: in `TestableTranslateExpertGoogleClient`, record the size of each batch call in a new array `$batchSizes`. Add the getter `getBatchSizes()`. Keep the existing getters.
- [x] Test: add `testBatchSplitsByMaxSegments()` and call it in `run()` with the other unit tests. Use 300 short texts (`'t0'` … `'t299'`). Put an empty text, a `null` and a blank text at known positions.
- [x] Test: in the new test, assert these items:
  - Each batch size is at most `TranslateExpertGoogleClient::GOOGLE_TRANSLATE_MAX_SEGMENTS`.
  - The batch call count is more than 1. With 300 translatable texts, the count is 3.
  - The sum of the batch sizes equals the number of translatable texts.
  - Each translatable text gives `'<text>_translated'` at its input position.
  - The empty, `null` and blank texts come back unchanged at their positions.
  - The result has the same length as the input.
- [x] Test: guard each array read of the result with `isset`, so a wrong result fails an assertion and does not stop the run.
- [x] Mutation check: disable the new `$flushBatch()` call for the segment limit (temporary edit). Run `php tests/test_translate_expert_google_client.php`. The new test must fail on the "at most the limit" assertion and on the call count assertion. Restore the code. Run the tests again and get a pass. Record the output in Done.
- [x] Bump version (`/bump-version`, 04.01.0216 → 04.01.0217 (first build), then 04.01.0218 (verify loop)) + `./build.release.sh` + all unit tests from `.claude/project-workflows.md`.
- [x] After the build, check that the oc4.x package and the 1.5 package contain `GOOGLE_TRANSLATE_MAX_SEGMENTS` in `translate_expert_google_client.php`.
- [x] Independent review (Opus, `ote-code-reviewer`) → `89-google-client-batch-segment-limit-review.md`.
- [x] Address review findings (fix all. Skip only with a verified reason, and write it in Findings).
- [x] Bump version + build + tests (verify loop).
- [x] Re-review? (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → `-review-v2.md`, v3, …)
- [x] Improvements pass → `89-google-client-batch-segment-limit-improvements.md`.

Dropped steps:

- Templates: no UI change.
- Language strings: no new strings.
- JS and controller: the agreed fix does not change them.

Manual check for the user (after the build, on an OC 4.x store):

1. Open the module and go to the Localization tab.
2. Select a language file with more than 128 keys (for example `en-gb`).
3. Push "Translate" for the file.
4. Make sure that all keys get a translation and that no "Too many text segments" error shows.

## Done

- Landed: `GOOGLE_TRANSLATE_MAX_SEGMENTS = 100` in `TranslateExpertGoogleClient`. `translateInternalBatch()` flushes the batch at 100 texts. The JS, the controller and the response format did not change.
- Tests: 2 new tests in `tests/test_translate_expert_google_client.php` (300 short texts, and 250 short texts with 3 long texts). With the cap disabled, 3 assertions fail. With the cap restored, 28 tests pass. All 15 PHP scripts and 723 JS tests pass.
- Review: 1 Minor finding (no test for long texts with the cap). Fixed. No re-review, by the decision of the user. Review file: `89-google-client-batch-segment-limit-review.md`.
- Final version: 04.01.0218. Release: `releases/v04.01.0218/`.
- Not verified: Google accepts 100 short texts in one request (no live key). The cron path was not checked. Findings: the stub adds `_translated` to each chunk of a long text, so a test must remove the suffix before it compares a long text.
- Nothing committed, pushed or opened as a PR.
