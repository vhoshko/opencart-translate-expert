# 89-google-client-batch-segment-limit review

Scope: `git diff HEAD` (the `_version.txt` bump 04.01.0216 → 04.01.0217, `translate_expert_google_client.php`, `tests/test_translate_expert_google_client.php`). Requirements: `89-google-client-batch-segment-limit_plan.md`.

### Strengths

- The fix is small and in the correct layer. Only `translateInternalBatch()` changes. The controller, the JS and the response format do not change, as the plan requires.
- The flush condition is correct. The code checks `count($batchTexts) >= GOOGLE_TRANSLATE_MAX_SEGMENTS` before it adds a text. Thus a batch holds 100 texts maximum, never 101. The `||` with the length check keeps the old length rule.
- The long-text path is not affected. A text above `GOOGLE_TRANSLATE_MAX_LENGTH` calls `$flushBatch()` first and then `translateInternal()`. After that path the batch is empty, so the count starts again at 0.
- The index mapping is correct across several flushes. `$batchIndexes` keeps the original index of each text, and `$flushBatch()` writes each result to `$results[$originalIndex]`. `translateBatch()` then maps back to the input positions of the empty, null and blank texts.
- PHP 5.6 compatible. The change uses only `count()`, `||` and a class constant with a literal value. `php -l` passes.
- No new class or route, so `build.sh` needs no new entry.
- The new test uses unique texts (`'t0'` … `'t299'`) and checks each position for `'<text>_translated'`. Thus a wrong order across a batch boundary fails the test. The assertions `max($sizes) <= 100` and `count($sizes) === 3` also catch an off-by-one (`>` in place of `>=` gives sizes 101, 101, 95).
- The test reads the result with `isset` guards, so a bad result fails an assertion and does not stop the run.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. `tests/test_translate_expert_google_client.php:105` — the new test does not mix a long text (above `GOOGLE_TRANSLATE_MAX_LENGTH`) with the segment cap. The code is correct for this case. I ran the probe `docs/tasks/89-google-client-batch-segment-limit/tmp/probe_review_long_and_order.php` with 250 short texts and 3 long texts (at index 100, in a batch, and at the end). Result: `batches=100,49,100,1 singles=6 order_ok=yes max=100`. A unit test for this case would protect the interaction against a future change. This is optional, because the old long-text tests and this probe already show the behavior.

### Assessment

**Ready to merge?** Yes. The flush condition is correct for the length path, the long-text path and the segment cap, and the results keep the input order across all flushes. The new test catches a wrong order and an off-by-one at the cap, and all unit tests pass (25 passed, 0 failed, 6 skipped).
