# 56-te-ui-foundation

## Settings

- Planning model: Opus 5.5 (inline, in the main conversation).
- Review model: Fable 5.1 (pass `model: fable` to `ote-code-reviewer`).
- Flow: full flow. The task changes PHP, JS, CSS, both templates, the OCMOD XML, `build.sh` and the language files.
- Build: bump the patch version and run `./build.release.sh` in Phase 3 and in each verify loop.
- Tests: all unit tests, plus a new unit test for the new library class.
- Commit, push, PR: the user asked for a PR when the work is ready (2026-11-15, after the first review). Commit with the format of `project-workflows.md`, push `feat/56-te-ui-foundation`, then `gh pr create`.
- Store run: yes. The user approved a WIP push of `feat/56-te-ui-foundation` after the first green build. The user then runs `docker/rebuild-stores.sh` on the host (see Assumptions A7). This push is the only push that the user approved.
- Review findings: standing auto-approve (the user said "turn on auto approve mode" on 2026-11-15, during Phase 2). Decide each finding without a question to the user and report the reason. Prefer to fix. Go back to the user for a change of approach, an external system or a scope expansion.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/56

This is step 1 of the "Next tasks" of the PoC of issue 2 (`docs/pocs/2-admin-design/README.md`). The task adds a foundation for the new admin design. The admin page must show no change in behavior. The next tasks use the foundation to change the endpoints to JSON and to make the tabs with the new design.

The task adds these items:

1. The `te-*` CSS components under the root `.te-app` (`design-concept.md`, section 3).
2. JS helpers: `teT`, `teIcon`, a Bootstrap adapter, DOM helpers and a `te-alerts` helper.
3. `document.te_i18n` on the module page, from one `te_i18n` array in the controller.
4. `document.te_i18n_global` on all admin pages, from the header injection.

Affected OpenCart versions: all (1.5–4.1).

Key files (from the investigation, checked with reads):

- `sources/upload/admin/view/stylesheet/client_translate_expert.css` (99 lines). The header loads it on all admin pages, so every new rule must be under `.te-app` or `.te-header-count`.
- `sources/upload/admin/view/javascript/client_translate_expert.js`. Top-level functions, no IIFE. The file already uses default parameters and arrow functions. The modal adapter is at JS:572-620 (`typeof bootstrap !== 'undefined' && bootstrap.Modal`). The error alert is at JS:411-446.
- `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:9-40`: the header patch for OC 1.5–3.0. It writes 3 `document.js_const_*` globals in `client_translate_expert_header_html`. It loads no language file.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:913-941`: `eventHeaderViewAfter` for OC 4.x, inside the OC4 marker block. It writes the same 3 globals. It loads no language file.
- Same controller, lines 81-212: the language strings for the module template, one `$data['key'] = $this->language->get('key');` line for each key. The OC 1.5 build rewrites only the literal `$data[` (build.sh:115).
- Templates: `.tpl:414-425` and `.oc4.twig:405-416` write 10 `document.js_const_*` globals from raw strings, with no JSON encoding.
- `build.sh:207` (library move list for OC4) and `build.sh:249-274` (namespace list for OC4).

## Approach

Add the CSS components and the JS helpers as new code. Do not change the current functions, so the behavior of the page stays the same. Add new language keys with the prefix `te_` for the strings of the helpers. Do not move the 10 current `js_const_*` globals in this task. The next tasks remove them when they change the tabs.

**Design correction 1: no shared language load in the header.** The PoC says that the header injection writes `document.te_i18n_global`. Neither header injection loads a language file today. A `load->language()` call in the OC ≤ 3.0 header merges all module keys into the shared language object of the page. Then the later `column_left` and `footer` controllers can read module values for keys with the same name. For this reason, a new pure library class `ClientTranslateExpertI18n` (`sources/upload/system/library/client_translate_expert_i18n.php`) reads the module language file in a function scope. It returns only the global keys. It does not touch the shared language object. The OC ≤ 3.0 OCMOD patch and the OC4 event both use it. The class has no OpenCart dependencies, so a unit test can test it with temp files (the same pattern as `client_translate_expert_route_filter.php`).

**Design correction 2: JSON encoding.** The current globals echo raw strings in `"..."`. The new objects use `json_encode($array, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)` (PHP 5.3+). A `</script>` or a quote in a translation then cannot break the script. If `json_encode` returns `false`, write `{}`.

**Design correction 3: FA 6 icons.** OC 4.x ships FA 6.4.2 (4.0.2.3) and 6.7.1 (4.1.0.0). Both define `.fa` and the FA 4 alias names (A3). `teIcon(name)` gives `fa fa-<name>` on BS3 pages and `fa-solid fa-<name>` on BS5 pages. A small map handles names that are not the same in FA 4 and FA 6.

## Assumptions

- A1 ✅ verified: the header loads the module JS and CSS on all admin pages that the route filter does not exclude. Evidence: XML:25-31 and CTRL:913-933. So all new CSS rules must be under `.te-app` or `.te-header-count`.
- A2 ✅ verified: host stacks. OC 2.0.3.1 and 2.3.0.2 load FA 4.4.0. OC 3.0.3.8 loads FA 4.7.0. OC 4.0.2.3 loads FA 6.4.2 and OC 4.1.0.0 loads FA 6.7.1 (`admin/controller/common/header.php:23`, `view/stylesheet/fonts/fontawesome/css/all.min.css`). OC 1.5 loads the bundled FA 4.4.0 only on the module page. Evidence: `gh api` core probes of `common/header.tpl` / `header.twig` and the CSS headers.
- A3 ✅ verified: the FA 6 CSS of OC 4.0.2.3 and 4.1.0.0 defines `.fa{`, `.fa-solid`, and the FA 4 names `fa-save`, `fa-exclamation-circle`, `fa-info-circle`, `fa-check-circle`, `fa-refresh`, `fa-times`, `fa-language`, `fa-spinner`, `fa-stop`. Evidence: grep in `docs/tasks/56-te-ui-foundation/tmp/fa-4.*.css`.
- A4 ✅ verified: the admin language folder. OC 1.5 and 2.1 use `$languages[config_admin_language]['directory']` (`admin/index.php:123` / `:122`). OC 3.0 uses the code `config_admin_language` (`admin/controller/startup/startup.php:34`). OC 4.0.2.3 uses `config_language_admin` (`admin/controller/startup/language.php:21`). The `oc_language` table has a `directory` column on 1.5, 2.1, 2.2 and 3.0. The module ships its language file at:
  - OC 1.5–2.1: `admin/language/<english|russian|ukrainian>/module/client_translate_expert.php`
  - OC 2.2: `admin/language/<code>/module/client_translate_expert.php`
  - OC 2.3–3.0: `admin/language/<code>/extension/module/client_translate_expert.php`
  - OC 4.x: `extension/client_translate_expert/admin/language/<code>/module/client_translate_expert.php`

  For this reason, the OCMOD patch gives the helper a list of candidate files: the folder name from `directory` and from the code, each with `extension/module/` and `module/`. Then English (`en-gb`, `english`) is the fallback. The XML needs no per-version rewrite.
- A5 ✅ verified: `getLanguages()` in the admin language model returns an array keyed by the language code on OC 1.5–3.0. Evidence: the current XML already json-encodes it, and JS:296 reads it by code. I check the `directory` key in the code with `isset`, so a missing key is no error.
- A6 ✅ verified: `build.sh` rewrites every `token` in the controller and the JS for OC 3.0/4.x (build.sh:141, 159), and `; ?>` and `<?php echo $` in the XML for OC 3.0 (build.sh:156-158). The new code and keys must not contain `token`, `; ?>` or `.tpl` in the XML add block.
- A7 ✅ verified (2026-11-15): the user ran `docker/rebuild-stores.sh` on the WIP push `d589eaa` (build 04.01.0176) and reported a pass on OC 1.5, 2.3, 3.0 and 4.x: no visible change, no console errors, `document.te_i18n_global` on all admin pages. The later review fixes did not get a store run. They change only the OC4 header method style, the array handling of `teAlerts`, one removed language key and tests. The package checks of 04.01.0177 and 04.01.0178 cover them.
- ~~A7 ❓ unverified: the page shows no change on real OC 1.5, 2.3, 3.0 and 4.x stores.~~ The issue asks for this check. A store run needs a WIP push, because the host checkout is separate. If it is not done, a JS error in the new code can stop `initTranslateExpert` on some version. Mitigation without stores: `node --check`, a Node probe of the pure helpers with stubs, and a grep of each built package.

## Steps

- [x] Library: add `sources/upload/system/library/client_translate_expert_i18n.php` with class `ClientTranslateExpertI18n` (PHP 5.6, static methods, no OpenCart dependencies):
  - `globalKeys()`: the list of keys for `te_i18n_global`.
  - `readFile($file)`: includes the language file in a function scope and returns `$_` (empty array if the file is missing or `$_` is not an array).
  - `pick(array $strings, array $keys)`: returns the keys that exist, with string values.
  - `loadGlobal(array $candidateFiles, array $fallbackFiles)`: uses the first candidate file that exists, then fills missing keys from the first fallback file that exists.
  - `toJson(array $strings)`: `json_encode` with the 4 `JSON_HEX_*` flags. Gives `{}` for an empty array or on failure, so the value is always a JS object.
  - Findings: I added `candidateFiles($baseDir, $folders, $subPaths)`. It makes the candidate list and skips folder names that are not `[A-Za-z0-9_-]+`, because the names come from the database. The XML and the OC4 event then stay short, and the unit test covers the list rules. The OC 3.0 build rewrites `; ?>`, `<?php echo $` and `.tpl` in the whole XML file. The new XML code has none of these strings.
- [x] `build.sh`: add `client_translate_expert_i18n.php` to the OC4 library move list (line 207). Add `OC4_LIB_I18N` and its `add_namespace` line (lines 249-274).
- [x] Unit test: add `tests/test_client_translate_expert_i18n.php` (temp folders, no DB). Cover candidate order, the English fallback for missing keys, a missing file, a file without `$_`, non-string values, `toJson` of `</script>`, quotes and Cyrillic text, and the `{}` result. Any PHP warning fails a test. Add the command to the **Tests** section of `CLAUDE.md` and to `project-workflows.md`.
  - Findings: 34 checks pass. The test also reads the 3 real module language files and checks that each one defines all global keys. `ote-build-test` runs every script in `tests/`, so its definition needs no change.
- [x] Language strings (en-gb, ru-ru, uk-ua): add the keys for the helpers.
  - Module page keys: `te_alert_details`, `te_alert_close`, `te_alert_not_json`, ~~`te_error_internal`~~ (removed by review finding 5).
  - Global keys: `te_global_char_count`, `te_global_reset`, `te_global_reset_confirm`, `te_global_translate`, `te_global_error_internal`.
- [x] Controller, module page: after line 212, build `$te_i18n` from a key list with a `foreach` and `$this->language->get()`. Set `$data['te_i18n_json']` with the same JSON flags. Write no `$data =` and no `array_merge($data, ...)`, because the OC 1.5 build rewrites only `$data[`.
- [x] Templates: write `document.te_i18n = <?php echo $te_i18n_json; ?>;` in the `.tpl` and `document.te_i18n = {{ te_i18n_json }};` in the `.oc4.twig`, next to the current `js_const_*` block.
- [x] Header injection OC 1.5–3.0 (XML): in the `!$teExcluded` block, `require_once` the i18n library with an `is_file` guard. Build the candidate list (A4). Add `document.te_i18n_global = ...;` to `client_translate_expert_header_html`. If the library file is missing, write `{}`.
  - Findings: the candidate order is the code first, then the `directory` column. On OC 2.2+ the code is the folder name. On OC 1.5–2.1 the code (`en`) has no module folder, so the `directory` (`english`) is used.
- [x] Header injection OC 4.x (controller, inside the OC4 marker block): in `eventHeaderViewAfter`, `require_once` the library from `DIR_EXTENSION`. Use the namespaced class. Candidate file from `config_language_admin`, fallback `en-gb`. Add `document.te_i18n_global` to `$config`.
- [x] CSS: add the `te-*` components of `design-concept.md`, section 3, under `.te-app`: the color variables, `te-summary`, `te-langbar`, `te-card`, `te-form` / `te-field`, `te-toolbar`, `te-progress`, `te-table`, `te-meter`, `te-badge`, `te-alerts`, `te-diff`. Add `.te-header-count` as its own root. Use flex and grid only. Do not change the current rules.
- [x] JS: add new top-level functions in ES5 style (`var`, `function`). Do not change the current functions.
  - `teT(key, params)`: reads `document.te_i18n`, then `document.te_i18n_global`, then returns the key. Replaces `{name}` placeholders from `params`.
  - `teIsBs5()`, `teIcon(name)`: FA 4 / FA 6 classes with a small name map.
  - `teBs.tab(el)`, `teBs.modal(el, action)`, `teBs.collapse(el, action)`: `bootstrap.*` on BS5, the jQuery plugins on BS3.
  - `teEl(tag, attrs, text)` and `teText(el, value)`: make DOM nodes and set text with `.text()`.
  - `teAlerts`: `show(container, type, message, details)`, `clear(container)`, `fromResponse(container, response)`. A non-JSON response gives `te_alert_not_json` and the response text in a collapsed "Details" block, cut to 2000 characters, inserted as text.
- [x] Throwaway Node probe in `tmp/`: load the JS with stub `document`, `$` and `bootstrap`. Check `teT` placeholders and fallbacks, `teIcon` on BS3 and BS5, and that `teAlerts.fromResponse` never inserts HTML.
  - Findings: `tmp/jsprobe/probe.js` runs the real JS file in jsdom with jQuery 3.7.1 and with jQuery 1.7.2 (npm has no 1.7.1). Each run gives 37 passed, 0 failed. The probe found one defect: a jqXHR with `status` 0 and no body gave the details text " error", because `0 || ''` drops the zero. Fixed. The probe also shows that the existing init code runs without an exception after the new code loads.
- [x] Bump version + build + test. Grep the built packages: the i18n library and namespace in oc4.x, `te_i18n_global` in the XML of 1.5/2.x/3.0 and in the oc4.x controller, `te_i18n` in each template.
  - Findings: version 04.01.0175 → 04.01.0176. Build green. 7 test scripts, 0 failed (`test_client_translate_expert_i18n.php`: 34). All 6 package checks pass: the OC4 namespace, the OC4 block stripped from 1.5–3.0, the OC 3.0 XML rewrites do not touch the add block (the extracted PHP passes `php -l`), both templates and the language keys in each package.
- [x] Store run on OC 1.5, 2.3, 3.0 and 4.x (only if the user agrees to a WIP push, see A7).
  - Findings: WIP commit `d589eaa` pushed. The user reported a pass (see A7).
- [x] Independent review → `56-te-ui-foundation-review.md`
  - Findings: reviewer Fable 5.1. Verdict: ready to merge. 0 Critical, 1 Important, 5 Minor.
- [x] Address review findings (auto-approve, see Settings)
  - 1 (Important, no permanent JS test): asked the user, because a Node/npm test harness in `tests/` is a scope expansion. The user chose to fix it. Fixed: `tests/js/test_client_translate_expert_js_helpers.js` with `package.json` and `package-lock.json` (pinned jsdom 24.1.3, jquery 3.7.1, jquery 1.7.2 as `jquery1`, ~~replaced by review v2 finding 1~~). `/tests/js/node_modules/` is in `.gitignore`. 88 passed (44 checks for each jQuery version). Mutation check: with the old `String(response.status || '')` the check "a jqXHR with no body" fails for both jQuery versions (86 passed, 2 failed). The fix was restored from a byte copy. Added the command to `CLAUDE.md`, `project-workflows.md` and the test list of `.claude/agents/ote-build-test.md`.
  - 2 (Minor, `teBs.modal` duplicates `showTranslateExpertModal` / `hideTranslateExpertModal`): skipped. A refactor changes the current code, and this task must not change behavior. Follow-up: when a later task changes the progress modal, make the old functions call `teBs.modal`.
  - 3 (Minor, dead `is_file` guard in `getGlobalI18nJson`): fixed. `isCurrentRouteExcluded()` already does an unguarded `require_once` of a sibling library. The method now uses the FQCN call style of `isCurrentRouteExcluded()`. The XML guard stays, because a stale modification cache after an uninstall is possible.
  - 4 (Minor, JSON array handled in two ways in `parseResponse`): fixed. An array object and an array `responseJSON` are now non-JSON, the same as a JSON array string. 2 new probe cases (39 passed with each jQuery).
  - 5 (Minor, `te_error_internal` and `te_global_error_internal` hold the same text): fixed. `te_error_internal` is removed from the 3 language files and the controller key list. `teAlerts.fromResponse` uses `te_global_error_internal`, and `teT` finds it on all pages.
  - 6 (Minor, `teAlerts` needs a container inside `.te-app`): fixed with a comment on `teAlerts`.
- [x] Bump version + build + tests (verify loop)
  - Findings: 04.01.0176 → 04.01.0177. Build green. 8 test scripts, 0 failed (JS helper test: 88). Package checks pass: OC4 FQCN calls, no OC4 code in 1.5–3.0, no `te_error_internal` in any package, `tests/js/node_modules` ignored.
- [x] Re-review? The user asked for a new review pass (2026-11-15).
- [x] Independent review v2 → `56-te-ui-foundation-review-v2.md`
  - Findings: reviewer Fable 5.1. Verdict: ready to merge. 0 Critical, 1 Important, 2 Minor. The reviewer confirmed fixes 3–6 and the JS test (clean `npm ci`, mutation checks of the array fix fail as expected).
- [x] Address review v2 findings (auto-approve)
  - 1 (Important, the `jquery1` npm package pulls 48 lock entries with deprecation warnings and "4 critical" audit lines): fixed. `tests/js/vendor/jquery-1.7.1.min.js` is the file of OpenCart 1.5.6.4 (`gh api`, `admin/view/javascript/jquery/jquery-1.7.1.min.js`, MIT). `jquery1` is removed from `package.json`. The lock file now has 64 packages, and `npm ci` reports 0 vulnerabilities. The test now uses the exact OC 1.5 jQuery version: 88 passed.
  - 2 (Minor, `check()` prints no expected and actual values): skipped. The PHP tests use the same style, and each check message names the case.
  - 3 (Minor, `project-workflows.md` and `ote-build-test.md` disagree on when `npm ci` runs): fixed. Both now run `npm ci` as part of the command.
- [x] Bump version + build + tests (verify loop v2)
  - Findings: 04.01.0177 → 04.01.0178. Build green. 8 test scripts, 0 failed (JS helper test: 88). `npm ci`: 64 packages, 0 vulnerabilities.
- [x] Re-review v3? The user said no (the v2 fixes touched only tests and docs).
- [x] Improvements pass → `56-te-ui-foundation-improvements.md`

## Done

- Landed: the `te-*` CSS layer under `.te-app` and `.te-header-count`, the JS helpers (`teT`, `teIcon`, `teIconEl`, `teEl`, `teText`, `teBs`, `teAlerts`), `document.te_i18n` in both templates, and `document.te_i18n_global` in the OCMOD header op and the OC4 header event. The new library `ClientTranslateExpertI18n` and 8 new language keys come with them. No change in behavior (store run passed).
- New permanent tests: `tests/test_client_translate_expert_i18n.php` (34) and `tests/js/test_client_translate_expert_js_helpers.js` (88, jsdom, jQuery 3.7.1 and the OC 1.5 jQuery 1.7.1). This also resolves the "no JS test harness" friction of tasks 25 and 28.
- Final version: 04.01.0178. Reviews: v1 (1 Important, 5 Minor) and v2 (1 Important, 2 Minor), both by Fable 5.1. All findings fixed except v1-2 and v2-2.
- Gotcha: the OC 3.0 build rewrites `; ?>`, `<?php echo $` and `.tpl` in the whole OCMOD XML, and `token` in the whole controller and JS file.
- Follow-ups: make `showTranslateExpertModal` / `hideTranslateExpertModal` call `teBs.modal` when a later task changes the progress modal (v1-2). The improvements file proposes a gotcha row and two skill edits.
