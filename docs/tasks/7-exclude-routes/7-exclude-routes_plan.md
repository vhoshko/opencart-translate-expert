# 7-exclude-routes

## Settings
- Planning model: Opus 5.5 (inline).
- Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: no (the workflow never does these).
- Auto-approve for review findings: not granted. Ask the user for each finding.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/7 — "Add settings to not load the module on some pages, like: route=extension/module/my_module1".

The module injects its JS, CSS and `document.js_const_*` values into the header of every admin page. On some pages this is not necessary, or it conflicts with other modules. The new setting lists admin routes where the module must not load. The change affects all OC versions (1.5–4.1).

Key files:
- `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:9-37` — header controller op and header template ops (OC ≤ 3.0).
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:775-806` — OC4 events `eventHeaderControllerAfter` and `eventHeaderViewAfter`. The event `$route` is `common/header`, so the page route comes from `$this->request->get['route']`.
- Same controller `index()`: save at 47-69, labels at 98-101, values at 238-268.
- `client_translate_expert.tpl:37-92` and `client_translate_expert.oc4.twig` (same lines) — General tab fields.
- `build.sh:127` (OC3 list) and `build.sh:392` (OC4 twig list) — fixed setting-key rewrite lists.
- Language files en-gb, ru-ru, uk-ua.

## Approach
> **Note.** The review fixes changed some details of this section. See `## Update 10.11.2026` and `## Update 10.11.2026 (v2)` for the current behavior.

Add the setting `client_translate_expert_excluded_routes`: a textarea with one rule on each line. A rule matches the current route exactly. A rule that ends in `*` matches by prefix. The check normalizes each rule:
- trim spaces and `\r`, skip empty lines
- remove a leading `route=` and a leading `/`
- change `|` to `.` (OC 4.0.2+ changes `|` to `.` in the request route)

If no route is in the request, the check uses `common/dashboard`. The check never excludes a route that contains `module/client_translate_expert`, because the settings page needs the header JS.

On an excluded page the module does not load at all:
- OC ≤ 3.0: the header controller op builds the `<script>` and `<link>` tags into `$data['client_translate_expert_head_html']`. On an excluded page this value is empty, `client_translate_expert_is_enabled` is 0, and the language query does not run. The header.tpl op echoes the value. `<!--OC15-TE-->` stays in the template op, so `build.sh:115` still works.
- OC4: `eventHeaderControllerAfter` and `eventHeaderViewAfter` return early on an excluded page.

The match logic is about 12 lines. It is in the XML op (PHP 5.6 syntax) and in a private OC4 controller method. A shared library class needs new build entries and a `require` on every admin page, so it costs more than the duplicate.

Design correction from the investigation: a rule like `catalog/product` does not match `catalog/product/edit` or `catalog/product.form`. The help text tells the user to write `catalog/product*` for all methods.

## Assumptions
- ✅ verified: OC 4.0.2.3 and 4.1.0.4 change `|` to `.` in `$request->get['route']` before controllers run. Evidence: `system/framework.php:104` (oc40) and `:140` (oc41) in the Docker stores.
- ✅ verified: on OC 3.0 and 4.x, `editSetting` saves only POST keys that start with the setting code. The new key starts with `client_translate_expert_` and the build adds the `module_` prefix through the fixed lists. Evidence: existing keys use the same path.
- ✅ verified: the OC3 build converts only `<?php echo $x; ?>` to `{{ x }}`. Twig autoescape is off in OC 3.0, so raw HTML output works. Evidence: `build.sh:138-140`, the languages JSON already depends on this.
- ❓ unverified: OC 1.5 header. No 1.5 store exists. The existing OCMOD searches (`public function index(`, `<header `) may not match on 1.5. This change does not make 1.5 worse.

## Steps
- [x] Code: OCMOD XML header controller op — route check, `head_html`, skip language query.
  Findings: local variables use a `te` prefix (`$teRoute`, `$teRule`) because the op runs inside the core header controller. The op has no `<?php echo $` and no `; ?>`, so `build.sh:139-140` does not change it.
- [x] Code: OCMOD XML header.tpl op — echo `head_html` in place of the fixed tags.
  Findings: `{client_version}` moved into the controller op. `build.sh:45` replaces it in the whole XML file, so it still gets the version.
- [x] Code: OC4 events — early return, private `isCurrentRouteExcluded()` method in OC4 markers.
- [x] Code: controller `index()` — label, help and value for the new key.
- [x] Code: `build.sh` — add the key to the lists at 127 and 392.
- [x] Templates: textarea and help text in .tpl and .oc4.twig.
  Findings: the textarea name has no `[n]`, so the JS adds no translate button to it.
- [x] Language strings: en-gb, ru-ru, uk-ua (`entry_excluded_routes`, `help_excluded_routes`).
  Findings: `docs/tasks/7-exclude-routes/tmp/match_test.php` checks the match logic with 11 cases. All pass. It is a throwaway check, not a regression test.
- [x] Bump version + build + test. Check the new key in the oc3.0 and oc4.x packages.
  Findings: 04.01.0113 → 04.01.0114. Build green. Tests 24 passed, 0 failed, 6 skipped (no API key). The oc3.0, oc4.x, oc2.2 and oc1.5 packages have the correct key forms and no OC4 code in pre-4.0 packages.
- [x] Independent review → 7-exclude-routes-review.md
  Findings: Fable 5.2. No critical issues, 1 important (I1), 5 minor (M1–M5).
- [x] Address review findings (the user picks which to fix or skip)
  Findings: the user chose to fix all six. See `## Update 10.11.2026`.
- [x] Bump version + build + tests (verify loop)
  Findings: 04.01.0114 → 04.01.0115. Build green. Tests 24 passed, 0 failed, 6 skipped. The oc1.5, oc2.2, oc2.3, oc3.0 and oc4.x packages pass the rewrite checks. `xmllint` passes on the source, oc3.0 and oc1.5 XML.
- [x] Re-review? (ask the user)
- [x] Independent review v2 → 7-exclude-routes-review-v2.md
  Findings: Fable 5.2. 1 important, 2 minor. All fixed. See `## Update 10.11.2026 (v2)`.
- [x] Bump version + build + tests (verify loop v2)
  Findings: 04.01.0115 → 04.01.0116. Build green. Tests 24 passed, 0 failed, 6 skipped. The user did not want a v3 review.
- [x] Improvements pass → 7-exclude-routes-improvements.md

No regression test: the test harness covers only `VHGoogleTranslator`.

## Update 10.11.2026
Review fixes (all six findings, chosen by the user):
- I1: the help text now gives `catalog/product*`. ~~`catalog/product/*` and `catalog/product.*`~~ did not match the list page.
- M1: the XML op now builds the `document.js_const_*` script into `$data['client_translate_expert_header_html']`. On an excluded page this value is empty. ~~The three separate `$data` values for the header script~~ are gone.
- M2: if no route is in the request, the XML uses `common/home` on OC 1.5 and `common/dashboard` on 2.0+.
- M3: both copies accept `route=x`, a full `index.php?route=x&user_token=...` URL and a trailing `/`.
- M4: the XML comment and the controller comment point to each other.
- M5: the new strings have their own comment in the three language files.

The throwaway check now has 14 cases and passes for VERSION 3.0.3.9 and 1.5.6.4.

## Update 10.11.2026 (v2)
Second review (Fable 5.2, `7-exclude-routes-review-v2.md`) confirmed the six fixes. The user chose to fix all three new findings:
- I1: a rule of only `*` now matches all routes (except the module page) on all PHP versions. Before, `strpos($route, '')` gave `Warning: strpos(): Empty needle` on PHP 5.6–7.4 and matched nothing.
- M1: this section and the Approach note.
- M2: a pasted admin URL that has `index.php` but no `route=` now means the dashboard route.

Live Docker check of v04.01.0115 (OC 2.3.0.2, 3.0.3.9, 4.0.2.3, 4.1.0.4): all checks pass. Excluded pages (`catalog/product`, product edit, `sale/order`) have no module JS, CSS or inline script. `catalog/category`, the dashboard and the module page have them. The form save stores the textarea value, and a saved rule works at once. No PHP notices. The check left module settings rows in the oc30 and oc40 stores (status=1, empty API key).

## Update 10.11.2026 (shared library)
The user asked to remove the duplicate match logic after PR #26 opened.

> **Superseded.** The shared library replaces the two copies of the match logic (XML op and OC4 `isCurrentRouteExcluded()`) from `## Approach`. The plan rejected a shared library because of build entries and a `require` on every admin page. The real cost is small: two `build.sh` lines and one small file with no OpenCart dependencies.

- [x] Code: new `sources/upload/system/library/client_translate_expert_route_filter.php`, class `ClientTranslateExpertRouteFilter`, static `isExcluded($route, $rulesText, $defaultRoute)` and `defaultRoute()`. PHP 5.6 syntax.
- [x] Code: the XML op calls it after `require_once(DIR_SYSTEM . 'library/...')`. The OC4 method calls the namespaced class after `require_once(DIR_EXTENSION . 'client_translate_expert/system/library/...')`. The OC4 call is inside the OC4 markers, so it needs no build rewrite.
- [x] Code: `build.sh` moves the file into the OC4 extension folder and adds the OC4 library namespace.
- [x] Tests: new `tests/test_client_translate_expert_route_filter.php`, 34 tests. Any PHP warning fails a test.
  Findings: if you remove the empty-prefix guard, 5 tests fail on `strpos(): Empty needle`. The proof used a copy under `tmp/proof/`. So the tests would have caught the v2 I1 bug. The source library still has the guard.
- [x] Docs: `CLAUDE.md`, `.claude/project-workflows.md`, `ote-build-test.md`, `ote-implementer.md` and `dev-workflow` name the new test script.
- [x] Bump version + build + tests.
  Findings: 04.01.0116 → 04.01.0117. Build green. `test_vh_google_translator.php` 24 passed, 0 failed, 6 skipped. `test_client_translate_expert_route_filter.php` 34 passed, 0 failed. Pre-4.0 packages have the library without a namespace. The oc4.x package has it in the extension `system/library/` with the OC4 namespace.
- [x] Live check in the Docker stores.
  Findings: v04.01.0117 in OC 2.3, 3.0, 4.0 and 4.1. 36 of 36 checks pass, which include a rule of only `*`. The library file is in the correct folder in each store. No PHP error text. The store data is back to the recorded values. The oc23 `error.log` has 2 notices from 16:50:02, before this install: `Undefined variable: client_translate_expert_head_html` and `..._header_html` in the cached `header.tpl`. The probable cause is a request during an earlier modification refresh (not proved).
- [x] Independent review v3 → 7-exclude-routes-review-v3.md
  Findings: Fable 5.2. Parity with ac6bf72 is complete. 1 important, 5 minor. The user chose to fix all six.
  - I1: the XML op loads the library only if `is_file()` finds it. If the file is absent, the module does not load and the admin page does not stop. Before this fix, an uninstall without a modification refresh gave a fatal error on each admin page (OC 1.5–3.0).
  - M1, M2: 6 more tests (40 in total): `defaultRoute()`, a URL rule on OC 1.5, prefix rules against a missing route, a `*` that is not the last character.
  - M3: `dev-workflow` and `ote-code-reviewer.md` name both test scripts.
  - M4: the Done block.
  - M5: two STE sentences in this section.
- [x] Bump version + build + tests (verify loop v3).
  Findings: 04.01.0117 → 04.01.0118. Build green. `test_vh_google_translator.php` 24 passed, 0 failed, 6 skipped. `test_client_translate_expert_route_filter.php` 40 passed, 0 failed.
- [x] Live check of the I1 guard (oc23, oc30): remove the library file, open an admin page, then restore the file.
  Findings: with the file absent, the dashboard and category pages give HTTP 200 with no PHP error text and no module JS. `error.log` gets no new lines. With the file back, the JS loads again. The store data is back to the recorded state.

## Done
- Landed: setting `client_translate_expert_excluded_routes` (exact route, or prefix with a trailing `*`). On a matching admin page the module adds no JS, CSS or inline script. The module page is always exempt.
- The rules are in one library: `system/library/client_translate_expert_route_filter.php` (`ClientTranslateExpertRouteFilter::isExcluded()`). The OCMOD header op (OC 1.5–3.0) loads it only if the file is present. The OC4 header events call the namespaced class.
- Tests: `tests/test_client_translate_expert_route_filter.php` has 40 tests. Any PHP warning fails a test.
- Final version: 04.01.0118. Three Fable 5.2 reviews (v1, v2, v3), with all findings fixed. Live checks in the OC 2.3, 3.0, 4.0 and 4.1 Docker stores passed on 04.01.0115 and 04.01.0117. The guard check passed on 04.01.0118 (OC 2.3, 3.0).
- Gotchas: `strpos()` with an empty needle (PHP < 8), language files with translated comment anchors, a `require_once` in an OCMOD op needs an `is_file()` guard.
- Follow-ups: #24 (settings save resets the char count), #25 (JS `route.indexOf` on null). The oc30 and oc40 test stores keep module settings rows from the first live check.
