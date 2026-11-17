# 61-post-reset-json-errors: review

Scope: `git diff HEAD` on `feat/61-post-reset-json-errors`, plus the untracked `tests/test_client_translate_expert_ajax_guard.php`. Requirements: issue 61 and `61-post-reset-json-errors_plan.md`.

Already verified by the brief (not checked again): all 9 test suites and the release build pass (04.01.0199). `$this->log` is in the registry on OC 1.5.6.4–4.1.0.0. `build.sh` needs no change.

## Strengths

- The 10 closure conversions are faithful. I compared each method with `git show HEAD:...controller...`:
  - Each `$this->badFilterResponse(); return;` became `return $this->badFilterResult();`. The body is the same: `{success: 0, message: error_bad_filter}`.
  - Each `addHeader` and `setOutput(json_encode($result))` tail became `return $result;`.
  - The session writes in `analize`, `removeDataFromDb` and `analizeLocalization` stay in the same order, before the model call. They still do not occur on the bad-filter path.
  - `convert_from_latin1_to_utf8_recursively` stays in `analizeLocalization` (controller line 662).
  - The `foreach ($textArr as &$textEl)` reference loop in `translate` is local to the closure, so its semantics do not change.
  - `storeTranslatedCharCount` in `translate` and `translateTable` stays after the model call and keeps its conditions.
  - The `saveLocalizationFile` permission failure returns the same `{success 0, error_code 3006, message}` body.
  - `resetCache` still returns `{success: true}` (a boolean, as before).
  - No method changes its success response shape. Only the exception path changes (HTML page → JSON).
- `runJsonAction` (controller lines 492-503) is small and correct:
  - It sets the header once on all paths.
  - The closures use `$this` and private methods. A closure in a class method binds `$this` and the class scope on PHP 5.4+. On PHP 5.6, `catch (\Throwable $e)` is valid syntax and never matches. The class does not autoload.
  - The `\Exception` → `\Throwable` order is correct. Both branches call the same `jsonErrorResult`, so the order cannot hide `\Exception` subclass behavior. The leading `\` makes the code safe in the OC4 namespaced controller.
- The log write cannot throw on the supported versions. OC 1.5–3.0 `Log::write` is an `fwrite`. OC 4.0.2.3 and 4.1.0.0 `Log::write` is a `file_put_contents` (probe: `system/library/log.php`). The OC4 error handler (`system/framework.php:34`, 4.0.2.3) echoes or redirects. It does not throw. The deliberate design decision (the OpenCart error log, not `client_translate_expert.log`) has no real problem.
- The POST check sits inside the closure, before any `request` read, session write or model call. The test proves that the model is not called. No other caller uses GET for these two routes. I did a grep of `sources/`, `tools/`, `build.sh`, the templates and the cron controller. Only the two JS `$.post` calls remain, and the `build.sh:388,390` OC4 JS rewrites still match these route substrings.
- `resetTranslationCache` covers each path:
  - Success: `teAlerts.show(..., 'success', teT('te_cache_reset_done'))`.
  - `success: 0`, and also the new HTTP 200 JSON error: `teAlerts.fromResponse`.
  - A non-JSON body or an HTTP failure (jqXHR): `fromResponse`, through `parseResponse`.
  - The `!data ||` guard prevents a TypeError on an empty body.
  - `clear` before each result prevents stacked alerts. `getOcToken()` replaces the duplicate `URLSearchParams` code. The `token` → `user_token` rewrite (`build.sh:159`) applies.
- The JS error path of `makeRemovingDataFromDbRequest` is correct. The new JSON error arrives in `.done` with `success: 0`. `showTranslateExpertErrorMessage` then shows `message` and `additional_message`.
- The `#te-debug-alerts` container is inside `.te-app#te-debug-app`, in the cache card, in both the `.tpl` (line 334) and the `.oc4.twig` (line 332). Close and Details work through the delegated `$(document)` handlers.
- `te_cache_reset_done` is in en-gb, ru-ru and uk-ua, and in the `te_i18n` key list (controller line 194).
- The PHP test loads the real controller source with `require_once`. It does not use a copy. If you remove `runJsonAction` or its `try` block, the test stops with a fatal error or an uncaught exception, so the test fails. If you remove the POST check, the "model is not called" checks fail. The test covers the `\Error` path, the bad-filter path inside a closure, the GET rejection, the POST success path and the single header. The warning-to-exception handler makes a hidden warning visible as a wrong body.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. **An exception text with invalid UTF-8 gives an empty body.** `controller:489` puts `$e->getMessage()` into `additional_message` without a change. A MySQL error can quote non-UTF-8 bytes. Example: "Incorrect string value '\xE9...'" from a latin1 column. Then `json_encode` returns `false`, and the body is empty. Probe: `php -r 'var_dump(json_encode(array("m"=>"\xE9")));'` gives `bool(false)`. The JS then shows the "not JSON" alert, or the old red alert, with no details. The log line still has the text, so the effect is small.
   Fix (cost: 1 line): pass the error array through `$this->convert_from_latin1_to_utf8_recursively(...)` in `jsonErrorResult`. That method already exists and is PHP 5.6 safe.

2. **The `message` of error 2000 and 2001 is English only.** `controller:489`, `615` and `711` hard-code "Internal error." and "Wrong request method.". `teAlerts.fromResponse` (JS line 1022) shows `message` when it is not empty. The ru-ru and uk-ua users therefore see English, although `te_global_error_internal` exists in all 3 languages. This is the same as the existing code 1000 in the libraries, so it is consistent with the plan.
   Fix (cost: small, optional follow-up): use `$this->language->get(...)` for the message. Alternatively, in the JS, map `error_code` 2000 to `teT('te_global_error_internal')`.

3. **The JSON guard does not cover PHP warnings and notices.** It is not a regression, and the plan records this item as unverified. The OC 3.0 handler (`system/framework.php:42`, 3.0.3.8) and the OC 4.0.2.3 handler (`framework.php:34-62`) echo the warning as HTML before the JSON when `error_display` is on. When `error_display` is off, OC4 sends `Location: error_page` and calls `exit()`. On OC 1.5 with the `mysql` DB driver, a SQL error calls `trigger_error` and then `exit()` (`system/database/mysql.php:50-51`, 1.5.6.4). This occurs before any exception, so `runJsonAction` cannot catch it. The `mysqli` driver of 1.5.6.4 throws `ErrorException`, and the guard catches it. Record these limits in the Done section of the plan.

4. **`removeDataFromDb` and `resetCache` do not check the `modify` permission.** These pre-existing gaps are not in the issue scope. Both actions change data: one deletes translated values, and one drops and creates the cache table. Only the OpenCart `access` check of the route protects them. `saveLocalizationFile` (controller lines 788-790) shows the pattern. `resetCharCount` also changes data with GET (JS line 615). Consider one follow-up task for these 3 actions.

5. **`showTranslateExpertErrorMessage` inserts `additional_message` as HTML** (JS lines 220-233). It now can get raw exception text. An OC DB exception has `<br />` and the full SQL. For `removeDataFromDb`, the SQL holds only whitelisted table names and integer ids, so I see no injection path. The old fail path inserted a full HTML error page (`responseText`) in the same way, so this is not a regression. The plan keeps this function on purpose. When this caller moves to `teAlerts` (text only), the problem goes away.

6. **Test gaps (cost: small).** `tests/test_client_translate_expert_ajax_guard.php` exercises only `resetCharCount`, `removeDataFromDb` and `resetCache`. The other 7 conversions have no direct check. Code reading shows that they are correct. A table-driven check that each of the 10 public JSON methods writes exactly one JSON header with a stub model that throws would protect against a later edit that drops the wrapper. The JS test for `resetTranslationCache` also does not check that a second call clears the earlier success alert. The checks of `.te-alert` length 1 cover this item indirectly.

## Assessment

**Ready to merge?** Yes.

All 10 closure conversions keep their inputs, session writes, early returns and success outputs. The helper is PHP 5.6 safe and OC4 namespace safe, and its log write cannot throw. The POST-only check has a test and no GET callers remain. The minor items are optional. Item 1 is a one-line hardening. Items 3-5 are pre-existing limits for a follow-up task.
