# 61-post-reset-json-errors

## Settings

- Planning model: Opus (subagent). Review model: Opus (the user chose it). This is the same model as the planner, so the review runs in a fresh context.
- Build and tests: yes. Commit: yes, one feature commit and one separate workflow commit for `.claude/**`. Push and PR: yes. No WIP push and no store run.
- Auto-approve: yes. Decide the review findings and the re-review gate without a question. Prefer a fix to a skip. Apply the improvements to `.claude/**`.
- Investigation: done before the plan, with greps and file reads. The planner checked the controller, the JS, the templates, the model and `build.sh` again.

## Context

- Issue: https://github.com/vhoshko/opencart-translate-expert/issues/61 (depends on #60, merged as #67).
- PoC: `docs/pocs/2-admin-design/README.md` ("Next tasks", step 6) and `docs/pocs/2-admin-design/endpoints-json.md` (section 1 inventory, section 6).
- Affected OC versions: all (1.5–4.1). The controller, the JS and both templates change.
- The issue asks for 3 changes:
  1. `removeDataFromDb` and `resetCache` use POST, not GET. The JS callers use `$.post`.
  2. `resetCache` shows a success message in a `te-alerts` container.
  3. Each JSON AJAX method returns a JSON error, not an OpenCart HTML error page, when a PHP exception occurs. One private controller helper does this. The model does not change.
- Key files:
  - `sources/upload/admin/controller/extension/module/client_translate_expert.php`: `translate` (411), `badFilterResponse` (481), `translateTable` (499), `analizeTableDetail` (528), `analize` (552), `removeDataFromDb` (591), `analizeLocalization` (622), `translatedCharCountInfo` (648), `resetCharCount` (670), `resetCache` (683), `saveLocalizationFile` (753), `te_*` text key list (194).
  - `sources/upload/admin/view/javascript/client_translate_expert.js`: `showTranslateExpertErrorMessage` (206), `makeRemovingDataFromDbRequest` (522), `resetTranslationCache` (631), `teAlerts` (962).
  - `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:332-337` and `client_translate_expert.oc4.twig:330-335` (Debug tab, `te_debug_card_cache` card, `#reset_translation_cache` button).
  - `sources/upload/admin/model/extension/module/client_translate_expert.php:56-126` (each method catches `\Exception`, writes `gebugLog` and re-throws).
  - `tests/js/test_client_translate_expert_js_helpers.js:292-318` (`makeRemovingDataFromDbRequest` tests stub `w.$.get`).
  - `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php`.
- Existing error codes: 1000 ("Internal error." in the libraries), 1004 (empty API key), 3001–3008 (localization). No code uses 2xxx.

## Approach

Add one private controller method `runJsonAction($action)`. It calls the closure in a `try` block and writes the return value as JSON with the `Content-Type: application/json; Charset=UTF-8` header.
It catches `\Exception` first and `\Throwable` second. Each `catch` calls one private method that writes the error to `$this->log` and sets a JSON error.
The JSON error is `{success: 0, error_code: 2000, message: "Internal error.", additional_message: "<class>: <message>"}`. This copies the English literal of code 1000 in the libraries.
The 10 JSON methods put their work in a closure and return the result. `badFilterResponse()` becomes `badFilterResult()` and returns the error object, so the early return works in a closure.
`removeDataFromDb` and `resetCache` read `$this->request->post` and reject a non-POST request with error code 2001. The check is 3 lines. Without it, a GET to `resetCache` still drops the cache table.
The JS uses `$.post` and keeps the route and the token in the URL. `resetTranslationCache` shows the result in a new `#te-debug-alerts` container in the Debug tab cache card.

Design corrections:

- The helper writes only to the OpenCart error log (`$this->log->write`). The model already writes each `\Exception` to `client_translate_expert.log` when the debug mode is on. A `new Log(` call in the controller is then not necessary, and `build.sh` does not change.
- Do not use `#te-summary-alerts` for the cache message. The user is in the Debug tab, and the alert must show next to the button.
- Keep `showTranslateExpertErrorMessage` for `makeRemovingDataFromDbRequest`. It already reads `message` and `additional_message`. A change to `teAlerts` there is out of scope.

## Assumptions

- ✅ verified: the OpenCart registry has `log` on each version, so `$this->log->write()` works in an admin controller. Probe evidence: `system/framework.php` line 31 (4.0.2.3) and line 33 (4.1.0.0) `$registry->set('log', $log)`, `system/framework.php:13` (3.0.3.8), `admin/controller/startup/error.php:4` (2.3.0.2), `system/framework.php:51` (2.0.3.1), `admin/index.php:57` (1.5.6.4). OC 4 `Log::write(string|array $message)` (`system/library/log.php:35`, 4.0.2.3) accepts a string.
- ✅ verified: PHP 5.6 accepts `catch (\Throwable $e)` as syntax and never matches it. A closure in a class method binds `$this` on PHP 5.4+. (PHP manual, and the issue text.)
- ✅ verified: no new route, library class or setting key. No `new Log(` in the controller. `build.sh` needs no change (`build.sh:279-308` rewrites only fixed names in `$OC4_CONTROLLER`).
- ✅ verified: `removeDataFromDb` reads its parameters only through `$this->request->get` and `readStrictListParam($source, ...)` (`controller:591-598`). A switch to `$this->request->post` changes no other code.
- ✅ verified: the route substrings in the JS stay unchanged, so the `build.sh` JS rewrites (lines 380-394) still apply.
- ❓ unverified: no example of a PHP warning before the JSON output. The issue asks to record one if this task finds it. No store run is planned, so only code reading can find one. Record the result in Done.
- ❓ unverified: the visual result in a real store. No browser and no store run.

## Steps

- [x] Controller: add `runJsonAction($action)`, `jsonErrorResult($e)` (log and error object) and `badFilterResult()`. Remove `badFilterResponse()`.
- [x] Controller: put the work of the 10 JSON methods in `runJsonAction` closures. Keep each response format. `downloadDebugLog` and `clearDebugLog` are not AJAX, so do not change them.
- [x] Controller: `removeDataFromDb` and `resetCache` read POST data and return error code 2001 for a non-POST request.
- [x] Controller: add `te_cache_reset_done` to the `te_i18n` key list (line 194).
- [x] JS: `makeRemovingDataFromDbRequest` and `resetTranslationCache` use `$.post` with the token in the URL. `resetTranslationCache` uses `getOcToken()`.
- [x] JS: `resetTranslationCache` shows `teT('te_cache_reset_done')` with `teAlerts.show('#te-debug-alerts', 'success', ...)`. If `success` is not true, or the request fails, use `teAlerts.fromResponse('#te-debug-alerts', ...)`.
- [x] Templates: add `<div class="te-alerts" id="te-debug-alerts"></div>` in the cache card of the `.tpl` and the `.oc4.twig`.
- [x] Language strings: add `te_cache_reset_done` to en-gb, ru-ru and uk-ua.
- [x] PHP test: add `tests/test_client_translate_expert_ajax_guard.php`. Stub `Controller`, `Log`, the loader, the model, the request and the response. Then `require` the controller source. Any PHP warning fails a test. Write one check for each item:
  - Normal path: JSON body and the `Content-Type` header.
  - `\Exception` in the model: `success` 0, `error_code` 2000, `additional_message` has the class and the text, one log line.
  - `\Error` (a `\Throwable` that is not an `\Exception`): the same JSON error.
  - `badFilterResult` in a closure (`removeDataFromDb` with a bad `table` list): `{success: 0, message}`, no log line.
  - A GET request to `removeDataFromDb` and to `resetCache`: error code 2001, and the model is not called.
- [x] JS tests: change the `makeRemovingDataFromDbRequest` stubs from `w.$.get` to `w.$.post`. Add `resetTranslationCache` tests: success gives `.te-alert--success`, `success: 0` and a failed request give an error alert in `#te-debug-alerts`.
- [x] Docs: add the new test to the Tests list of `CLAUDE.md`, the command table of `.claude/project-workflows.md` and the Step 2 list of `.claude/agents/ote-build-test.md`.
- [x] PoC: add "Status: done in issue 61" to `README.md` step 6 and to `endpoints-json.md` section 6. Add the note to the HTTP cells of rows 5 and 10 in the section 1 inventory. Keep the old text.
- [x] Check PHP 5.6 syntax in the added lines (no `??`, no type hints, no arrow functions, no `::class`).
- [x] Bump version (04.01.0198 → 04.01.0199) + build + tests (`ote-build-test`).
- [x] After the build, grep the oc4.x, 2.2 and 1.5 packages for `runJsonAction`, `te-debug-alerts`, `te_cache_reset_done` and the rewritten `removeDataFromDb` and `resetCache` routes in the JS.
- [x] Independent review (Opus, fresh context) → `61-post-reset-json-errors-review.md`
- [x] Address review findings (auto-approve, prefer a fix)
- [x] Bump version + build + tests (verify loop)
- [x] Re-review? (auto-approve: decide. If yes, repeat from the review step → `-review-v2.md`)
- [x] Improvements pass → `61-post-reset-json-errors-improvements.md`, apply the improvements to `.claude/**`
- [x] Commit (feature commit, then a separate workflow commit), push, PR

## Update 2026-11-17 (review fixes)

- Review found no Critical or Important finding. Minor findings 1 and 6 fixed: `jsonErrorResult` now converts the error array to UTF-8, and a test runs all 10 methods with a throwing loader. A mutation run (conversion removed) made the new UTF-8 test fail.
- Findings 2 and 5 were skipped first. The user then asked for both, so they are fixed: the messages come from the language files (`error_internal`, `error_wrong_method`) with an English fallback, and `showTranslateExpertErrorMessage` escapes `additional_message`.
- Recorded, no action: finding 3 (a PHP warning or notice before the JSON, or the OC 1.5 `mysql` driver `trigger_error` and `exit`, is not caught by the helper) and finding 4 (`removeDataFromDb`, `resetCache` and `resetCharCount` have no `modify` permission check, candidate for a follow-up task).
- No re-review: the fixes are small and tested. The review found only minor items.

## Done

- Landed: POST for `removeDataFromDb` and `resetCache` (non-POST gives error_code 2001), success alert in `#te-debug-alerts`, and `runJsonAction` in 10 AJAX methods (error_code 2000). The model is unchanged.
- The helper writes to the OpenCart error log, not to `client_translate_expert.log`. The model already writes `\Exception` to the debug log.
- Final version 04.01.0202. A confirm popup now shows before the cache reset (`te_cache_reset_confirm`). 9 test suites pass (new suite: 60 checks). Release build passes.
- No example of a PHP warning before the JSON output was found. No store run was done.
- Follow-up: add a `modify` permission check to the data-changing AJAX methods.
