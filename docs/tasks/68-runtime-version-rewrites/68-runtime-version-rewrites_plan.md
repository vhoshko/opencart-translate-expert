# 68-runtime-version-rewrites

## Settings
- Planning model: Opus (general-purpose subagent writes this file).
- Review model: Opus (`ote-code-reviewer`). The user knows that the review is then not independent and confirmed this choice.
- Scope: "audit + easy moves". Audit every rewrite of `build.sh`. Move the easy rewrites into the module code. Keep the other rewrites in the build.
- Unattended mode: yes.
  - Standing auto-approve for review findings. Prefer to fix a finding.
  - The agent decides the re-review gate.
  - Apply the improvements to `.claude/**`.
  - Then commit, push and open a PR (see the last step).
- Build and tests: after every change. Bump the version before each build (`CLAUDE.md`).
- Store run on the host: not in the unattended flow. See Assumption A9.

## Context
- Issue: https://github.com/vhoshko/opencart-translate-expert/issues/68.
- Branch: `refactor/68-runtime-version-rewrites` (at `origin/main` `7906a96`). `_version.txt` is `04.01.0208`.
- `build.sh` has 443 lines. Many lines rewrite the source for each OC version. The 3.0 and 4.x rewrites are fixed lists. A new AJAX method, library class or setting key needs a new `build.sh` line. If the developer forgets the line, the module fails only on OC 3.0 or 4.x (gotcha row in `.claude/project-workflows.md:81`).
- Affected OC versions: all. The moves change the OC 2.2, 3.0 and 4.x packages. The 1.5, 2.0 and 2.1 packages come from the 2.2 package, so they change too.
- Abbreviations in this plan:
  - CTRL = `sources/upload/admin/controller/extension/module/client_translate_expert.php` (997 lines)
  - MODEL = `sources/upload/admin/model/extension/module/client_translate_expert.php`
  - JS = `sources/upload/admin/view/javascript/client_translate_expert.js` (2647 lines)
  - XML = `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml`
  - TPL = `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl`
  - TWIG = `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig`
  - CORE = `sources/upload/system/library/client_translate_expert_core.php`
  - CRONH = `sources/upload/system/library/client_translate_expert_cron_helper.php`
- Investigation: no Explore agent. The caller gave the candidate list. This planner checked each claim with `grep` and file reads (see Assumptions).

### Key facts (verified with grep or a file read)
- CTRL `token`: 12 lines (78, 240, 245, 251, 256, 260, 262, 354, 355, 356, 833, 852). Each line has `'token=' . $this->session->data['token']`. No other `token` text is in CTRL.
- CTRL `'extension/extension'`: lines 78, 245, 262.
- CTRL `'client_translate_expert'` (the setting group code): line 74 only (`editSetting`).
- CTRL setting reads and POST keys: lines 54–72, 200–201, 264–312, 753, 943–944, 992. Line 945 (`client_translate_expert_languages_json`) is not a setting.
- CTRL `setOutput($this->load->view(`: line 407 only. No `template_engine` text is in `sources/`.
- CTRL language flag block: lines 226–233 (`version_compare(VERSION, '2.2', '<')`).
- CTRL debug log routes: lines 354–355 (`client_translate_expert/downloadDebugLog`, `client_translate_expert/clearDebugLog`).
- CTRL polyfill zone: lines 3–29 (`// add missed functions` … before `class`). `build.sh:291` deletes this zone for OC4. Put no new code there.
- CTRL OC4 event code: `// OC4_EVENT_START` blocks at 882–888, 917–923, 926–996. `eventHeaderViewAfter` emits `document.js_const_*` at 952–957.
- Setting keys: the `build.sh` loops (145, 415) hold **10** keys, not 7: `status`, `key`, `debug_status`, `char_count`, `reset_char_count_on_start_month`, `show_char_count_globally`, `last_reset_char_month`, `excluded_routes`, `cron_secret`, `cron_bulk_amount` (each with the `client_translate_expert_` prefix).
- Setting key users (`grep -rnow`): CTRL, MODEL (189, 198: `debug_status`), TPL, TWIG, XML (21, 53, 54). The libraries and `catalog/controller/extension/module/client_translate_expert_cron.php:13` already select the key at runtime. CORE:31–38 sets `module_` counter keys at runtime. CORE:17 `_counterGroup = 'client_translate_expert_usage'` is not in the lists.
- JS: no setting key name is in JS (one false hit, `js_const_client_translate_expert_show_char_count_is_enabled` at 556). The TPL/TWIG inline scripts use `input-client_translate_expert_key` and `button-client_translate_expert_key_toggle` (TPL:417, 422; TWIG:415, 420).
- JS `js_const_module_prefix`: 13 lines. 2 assignments (34, 667) and 11 route call sites (492, 528, 564, 628, 648, 1660, 1799, 1868, 2350, 2441, 2471).
- JS token: `getOcToken()` at 353–354 (`getParameter('token')`). `urlParams.get('token')` at 562 and 626. `&token=` at 492, 529, 565, 629, 649, 1661, 1800, 1869, 2351, 2441, 2471.
- JS `$('#translateExpertModal').modal('hide')`: no match (JS:387 is `$(modalEl).modal('hide')`). So `build.sh:399` is dead.
- `editSettingValue`: no match in `sources/`. So `build.sh:312` is dead.
- MODEL has no `extension/module` text. So `build.sh:301` is dead.
- The JS init (JS:14–37) runs on `DOMContentLoaded`. `initModule()` (JS:665) runs on each admin page when the module is enabled. JS:34 runs when the module is disabled and `#te-summary-count` exists.
- The module page template does not load the JS. Only the header (XML for OC ≤ 3.0, the OC4 event) loads it.
- `tests/test_client_translate_expert_ajax_guard.php:123` requires CTRL with stubs and defines no `VERSION`. It calls only `resetCharCount`, `removeDataFromDb` and `resetCache`. These methods do not read `VERSION`.
- The JS tests set `document.js_const_module_prefix = 'extension/module'` (297, 348, 524). They assert `route=extension/module/client_translate_expert/<method>&token=`. jsdom has no URL, so `location.search` is empty.

## Audit of the build.sh rewrites
Verdicts:
- **move** = move into the code in this task.
- **dead** = the search text is not in `sources/`. Delete the line.
- **must-stay** = the code cannot do it at runtime, or the rewrite is a file or folder layout item.
- **defer** = possible at runtime, but not cheap or not in the scope of this task. Give the reason.

Copy this table to `68-audit.md` in step 1.

| build.sh line(s) | OC | Rewrite | Verdict | Reason |
|---|---|---|---|---|
| 45–46 | all | `{client_version}` in XML and CTRL | must-stay | Build data, not a version rule |
| 53–58, 62, 161–166 | ≤ 3.0 | strip `// OC4_EVENT_*` blocks | must-stay | PHP 8 syntax does not parse on PHP 5.6 |
| 63 | 2.2 | `install.php` `extension/module` → `module` | must-stay | Route data in `install.php` (caller decision) |
| 64, 68, 77 | 2.2 | class names `ControllerExtensionModule…` → `ControllerModule…`, `ModelExtensionModule…` | must-stay | Class names are compile-time items |
| 65 | 2.2 | CTRL `extension/module/` → `module/` | defer | Route path is bound to the folder layout. About 40 sites need a route helper |
| 66 | 2.2 | CTRL `extension_module` → `module` | defer | Model property name from the route. About 25 sites |
| 67 | 2.2 | CTRL `extension/extension` → `extension/module` | move (c) | One helper selects the extension list route |
| 69–70 | 2.2 | JS `'extension/module'` → `'module'` | move (d) | Header emits the prefix. JS keeps a default |
| 72–93 | 2.2 | folder moves | must-stay | File layout |
| 99 | 2.1 | `setOutput` with `config_template` path | defer | OC 2.1 only. Not in the issue scope |
| 100, 109 | 2.1, 2.0 | `load->language` ↔ `language->load` | defer | OC 2.0/2.1 only. Not in the issue scope |
| 101–103 | 2.1 | language folder names | must-stay | File layout |
| 115, 119 | 1.5 | `$data[` → `$this->data[` | must-stay | About 160 sites in CTRL. Not cheap |
| 116 | 1.5 | `$this->response->redirect` → `$this->redirect` | defer | Cheap (4 sites), but OC 1.5 only. Not in the issue scope |
| 117–135 | 1.5 | vQmod search text, `<!--OC15-TE-->` block, file move | must-stay | vQmod search text and file layout |
| 140 | 3.0, 4.x | CTRL `extension/extension` → `marketplace/extension` | move (c) | Same helper as line 67 |
| 141 | 3.0, 4.x | CTRL `token` → `user_token` | move (a) | Helper selects the token name |
| 142 | 3.0, 4.x | prepend `template_engine = template` before `setOutput` | move (e) | Runtime guard 3.0 ≤ VERSION < 4.0 |
| 143 | 3.0, 4.x | setting group code `'client_translate_expert'` → `'module_client_translate_expert'` | move (b) | Helper `settingKey()` |
| 145–154 | 3.0, 4.x | `module_` prefix on 10 keys (CTRL, MODEL, TPL, XML) | move (b) | Helper `settingKey()`. The CTRL maps the POST keys |
| 156–158 | 3.0 | XML `.tpl` → `.twig`, `<?php echo $` → `{{ `, `; ?>` → ` }}` | must-stay | OCMOD search text and template syntax of the OC 3.0 core header |
| 159 | 3.0, 4.x | JS `token` → `user_token` | move (a) | JS reads the name from the URL |
| 175–252 | 4.x | remove XML, folder moves, `install.php` removal | must-stay | File layout |
| 254–276 | 4.x | `namespace` lines | must-stay | Compile-time item |
| 279–281 | 4.x | `extends` base classes | must-stay | Compile-time item |
| 284–286 | 4.x | short class names | must-stay | Compile-time item |
| 290–294 | 4.x | remove the PHP 5 polyfills | must-stay | Keep. It is harmless and not a version rule of the module |
| 297 | 4.x | remove `template_engine` line | dead after (e) | The guard does the same at runtime |
| 300 | 4.x | CTRL route path → `extension/client_translate_expert/module/…` | defer | Same reason as line 65 |
| 301 | 4.x | MODEL route path | dead | MODEL has no `extension/module` text |
| 304 | 4.x | model property name | defer | Same reason as line 66 |
| 308–309 | 4.x | CTRL `/downloadDebugLog`, `/clearDebugLog` → `.` | move (d) | Helper `routeMethodSeparator()` |
| 312 | 4.x | `editSettingValue` → `editValue` | dead | No match in `sources/` |
| 316–321 | 4.x | language flag `image_url` block | move (g) | Add an OC4 branch to the existing runtime check |
| 327–328 | 4.x | MODEL `require_once` with `DIR_EXTENSION` | move (h) | Same conditional form as the cron controller (lines 3–7) |
| 331–333 | 4.x | `new LibraryClientTranslateExpert(` and `new LocalizationLibraryClientTranslateExpert(` with namespace (MODEL, cron) | must-stay | The caller is in another namespace |
| 336 | 4.x | CORE `new VHGoogleTranslator(` with namespace | dead (redundant) | CORE and `vh_google_translator.php` share one namespace in OC4, so the unqualified name resolves to the same class |
| 339 | 4.x | CORE `new Log(` → `\Opencart\System\Library\Log` | must-stay | Namespace of an OC core class |
| 343–346 | 4.x | SQL `l2.image` line | must-stay | SQL fragment (caller decision) |
| 349–371 | 4.x | CORE `getLangImgUrl()` body | move (g) | Add an OC4 branch to the existing runtime check (CORE:679–685) |
| 374 | 4.x | `new Exception(` → `new \Exception(` | move (i) | Write `new \Exception(` in the source. It is valid on PHP 5.3+ in the global namespace. No runtime check |
| 378 | 4.x | JS `js_const_module_prefix` OC4 value | move (d) | OC4 event emits the prefix |
| 383–392 | 4.x | JS method separator for 10 methods (11 call sites) | move (d) | JS helper `teRoute()` reads the separator from the header |
| 399 | 4.x | JS `$('#translateExpertModal').modal('hide')` | dead | No match in `sources/` |
| 403 | 4.x | JS `rootUrl + 'view/image/…'` path | defer | Needs a new asset base constant. Not a fixed-list item |
| 405–411 | 4.x | `heading_title` icon path in the language files | must-stay | Language file content. Out of the code scope |
| 413–418 | 4.x | TWIG `module_` prefix on 10 keys | move (b) | The templates keep the bare names. The CTRL maps them |
| 427–440 | 4.x | `install.json`, zip layout | must-stay | Package layout |

## Approach
Add small pure helpers to the CTRL class. Each helper takes `$version` as an argument, so the tests can call each branch without a `VERSION` constant. The call sites pass `VERSION`. Put the helpers inside the class as `private static` methods, after `getCurrentVersion()` (CTRL:34–37). Do not put them in the polyfill zone. Do not put them in a library file: in OC4 the controller has another namespace, and an unqualified static call to a library class fails there.

Design decisions:
1. **Setting keys (b).** The TPL and TWIG keep the bare names (`name=`, ids and view variables). Thus no template change and no new view variable. The CTRL maps each POST key that starts with `client_translate_expert_` to `settingKey()` before `editSetting()`. The CTRL reads each setting with `settingKey()`. The CTRL rule is the same as `ClientTranslateExpertCronHelper::settingKey()` (CRONH:144). A test asserts that both give the same result for each version. The CTRL cannot call CRONH directly (namespace in OC4). The MODEL gets the same small rule for the debug key. The XML uses an inline `version_compare` expression, because the header must not stop the page when the module files are missing.
2. **Token (a).** The CTRL helper selects `user_token` for VERSION ≥ 3.0. The JS reads `user_token` first, then `token`. It sends back the name that it found. If neither name exists, the JS uses `token` (the JS tests expect this).
3. **Routes (c, d).** One CTRL helper selects the extension list route: `< 2.3` `extension/module`, `< 3.0` `extension/extension`, else `marketplace/extension`. The header emits `document.js_const_module_prefix` (XML: `module` for VERSION < 2.3, else `extension/module`. OC4 event: `extension/client_translate_expert/module`). The OC4 event also emits `document.js_const_module_method_separator = '.'`. The JS defaults are `extension/module` and `/`. JS:34 and JS:667 set a default only if the value is not set.
4. **Order of work.** Delete each `build.sh` line in the same step as its code move. Reason: some old `sed` lines would damage the new code (for example `token` → `user_token` makes `user_user_token`, and `extension/extension` → `marketplace/extension` changes the helper text).
5. The route path rewrites (65, 300) and the model property names (66, 304) stay in the build. They are bound to the folder layout. This keeps the `/` → `.` problem only for these generic rewrites, not for each method name.

## Assumptions
- A1 ✅ verified: `VERSION` is defined in every admin request of OC 1.5–4.x. Evidence: CTRL:228 and CTRL:391 already call `version_compare(VERSION, …)` on all packages.
- A2 ✅ verified: the version boundaries are correct. Evidence: the current build output works in production. `build.sh:67` gives `extension/module` for the 2.2-based packages (1.5–2.2). `build.sh:140–141` gives `marketplace/extension` and `user_token` for 3.0 and 4.x. 2.3 keeps `extension/extension` and `token`.
- A3 ✅ verified: OC4 `$language['image']` holds the full flag URL in the admin language list. Evidence: `build.sh:316–321` and `build.sh:349–371` ship this code in the current OC4 package.
- A4 ✅ verified: OC 4.0.2.x and 4.1.0.x use `.` as the method separator. Evidence: `build.sh:308–309`, `383–392` and the route filter note (`client_translate_expert_route_filter.php:64`).
- A5 ✅ verified: the XML header op runs inside `admin/controller/common/header.php` `index()`. `VERSION` is defined there. It can `require_once` a library file (XML:15–17 already does this with `is_file`).
- A6 ✅ verified: MODEL loads in a test with a `class Model {}` stub and `DIR_SYSTEM`. Probe: `php -r 'class Model {} define("DIR_SYSTEM", …); require MODEL;'` printed `ok`.
- A7 ✅ verified: the JS does not read any setting key id or name (grep of the 10 keys in JS: one false hit only). The TPL/TWIG inline scripts use the bare ids. With no rewrite, ids and selectors stay consistent.
- A8 ✅ verified: OC 3.0 `editSetting($code, $data)` stores only keys that start with `$code`. Evidence: the current OC 3.0 package posts `module_client_translate_expert_*` names with the group `module_client_translate_expert`. The new mapped array has the same keys. ❓ For OC 1.5, `editSetting` can store all POST keys. The mapping keeps non-module keys unchanged, so the result is the same as now.
- A9 ❓ unverified: no store run on a real OC 2.2, 3.0 or 4.x store before the PR. Reason: the host checkout is separate, and a store run needs a pushed branch and the user. Mitigation: the package probes in step 10. If this assumption is false, settings save or AJAX routes can fail on one OC version. The PR text asks for a store run with `docker/rebuild-stores.sh` before the merge.
- A10 ✅ verified: the JS is ES5 (no `let`, `const` or arrow functions found). New JS must stay ES5.

## Steps
- [x] 1. Audit file: write `docs/tasks/68-runtime-version-rewrites/68-audit.md`. Put the audit table of this plan in a section "Audit of the build.sh rewrites". Add a short intro for the issue comment. Do not post it.
- [x] 2. Code (a) token:
  - CTRL: add `private static function tokenName($version)` (`user_token` if `version_compare($version, '3.0', '>=')`, else `token`).
  - CTRL: add `private function tokenQuery($version)` that returns `<name>=<session value>`.
  - CTRL: replace `'token=' . $this->session->data['token']` on the 12 lines with `$this->tokenQuery(VERSION)`. Keep the `view=1&`, `&type=module` and `&module_id=` parts.
  - JS: add `getOcTokenName()` (`user_token` if the URL has it, else `token`). Change `getOcToken()` to `getParameter(getOcTokenName())`.
  - JS: replace `'&token=' + getOcToken()` and the `…&token=' + getOcToken()` route ends (492, 529, 649, 1661, 1800, 1869, 2351, 2441, 2471) with `'&' + getOcTokenName() + '=' + getOcToken()`.
  - JS: replace the `urlParams.get('token')` blocks at 561–565 and 625–629 with the same form.
  - `build.sh`: delete lines 141 and 159.
- [x] 3. Code (b) setting keys:
  - CTRL: add `private static function settingKey($name, $version)` (same rule as CRONH:144).
  - CTRL: add `private static function prefixSettingKeys(array $post, $version)`. It maps each key that starts with `client_translate_expert_` with `settingKey()`. It keeps other keys.
  - CTRL:74: call `editSetting(self::settingKey('client_translate_expert', VERSION), self::prefixSettingKeys($this->request->post, VERSION))`. Keep CTRL:53–72 on the bare POST names.
  - CTRL: wrap each `config->get('client_translate_expert_<key>')` in `self::settingKey(…, VERSION)` (lines 200, 201, 265, 271, 277, 280, 284, 290, 296, 301, 306, 312, 753). Keep the `$data[…]` names and POST names bare.
  - CTRL OC4 block (943, 944, 992): use `self::settingKey(…, VERSION)`.
  - MODEL: add `private static function settingKey($name, $version)`. Use it at 189 and 198.
  - XML: add `$teKeyPrefix = version_compare(VERSION, '3.0', '>=') ? 'module_' : '';` before line 18. Use it at 21, 53, 54. Do not write `; ?>`, `<?php echo $` or `.tpl` in the new XML code (`build.sh:156–158` still rewrite these strings in the OC 3.0 XML).
  - Do not change `client_translate_expert_usage`, CORE:31–38 or the cron controller.
  - `build.sh`: delete line 143, lines 145–154 and lines 413–418.
- [x] 4. Code (c) extension list route:
  - CTRL: add `private static function extensionListRoute($version)`.
  - CTRL 78, 245, 262: use `self::extensionListRoute(VERSION)`.
  - `build.sh`: delete lines 67 and 140.
- [x] 5. Code (d) JS module prefix and method separator:
  - CTRL: add `private static function routeMethodSeparator($version)` (`.` if ≥ 4.0, else `/`).
  - CTRL 354–355: build the route as `'extension/module/client_translate_expert' . self::routeMethodSeparator(VERSION) . 'downloadDebugLog'` (and `clearDebugLog`). `build.sh:65` and `:300` still rewrite the path part.
  - XML (in `client_translate_expert_header_html`): emit `document.js_const_module_prefix = "<prefix>";`, with `module` if VERSION < 2.3, else `extension/module`.
  - CTRL OC4 `eventHeaderViewAfter`: emit `document.js_const_module_prefix = "extension/client_translate_expert/module";` and `document.js_const_module_method_separator = ".";`.
  - JS: add `teRoute(method)`. It returns `prefix + '/client_translate_expert' + separator + method`. It uses the defaults `extension/module` and `/` when a value is not set.
  - JS: use `teRoute()` at the 11 call sites.
  - JS:34 and JS:667: set the default only if `document.js_const_module_prefix` is not set.
  - `build.sh`: delete lines 69–70, 308–309, 378 and 383–392.
- [x] 6. Code (e) template engine:
  - CTRL: add `private static function needsPhpTemplateEngine($version)` (true if 3.0 ≤ VERSION < 4.0).
  - CTRL before line 407: `if (self::needsPhpTemplateEngine(VERSION)) { $this->config->set('template_engine', 'template'); }`. Do not change the text of line 407 (`build.sh:99` matches it for OC 2.1).
  - `build.sh`: delete lines 142 and 297.
- [x] 7. Code (f) dead and redundant lines: run `rg` again for each search text. Then delete `build.sh` lines 301, 312, 336 and 399.
- [x] 8. Code (g, h, i) other cheap moves:
  - (g) CTRL: add `private static function languageImageUrl($code, $language, $version)` with 3 branches (≥ 4.0: `$language['image']`, < 2.2: flags path, else `language/<code>/<code>.png`). Use it in the loop at 226–233. CORE `getLangImgUrl()` (679–685): add the OC4 branch from `build.sh:361–370` first. Delete `build.sh:314–321` and `:348–371`.
  - (h) MODEL lines 3–4: use the conditional `require_once` form of the cron controller (lines 3–7). Delete `build.sh:326–328`.
  - (i) `vh_google_translator.php` (all 6 `new Exception(` calls, for example 222, 241, 247): write `new \Exception(`. Delete `build.sh:373–374`.
- [x] 9. Tests:
  - New `tests/test_client_translate_expert_version_rules.php`. It defines the `Controller` and `Model` stubs, `DIR_SYSTEM`, and requires CTRL, MODEL and CRONH. It calls the private static helpers through `ReflectionMethod`. Any PHP warning fails it. It defines no `VERSION`. One test group for each function that selects a code path:
    - `tokenName`: 1.5.6.4, 2.3.0.2 → `token`. 3.0.0.0, 3.0.3.8, 4.0.2.3 → `user_token`.
    - `tokenQuery`: a stub session with both names gives `token=a` for 2.3.0.2 and `user_token=b` for 3.0.3.8.
    - `settingKey` (CTRL and MODEL): bare below 3.0, `module_` from 3.0. Same result as `ClientTranslateExpertCronHelper::settingKey` for 1.5.6.4, 2.2.0.0, 2.3.0.2, 3.0.0.0, 3.0.3.8, 4.0.2.3, 4.1.0.0.
    - `prefixSettingKeys`: 2.3.0.2 keeps the array. 3.0.3.8 prefixes only the `client_translate_expert_*` keys.
    - `extensionListRoute`: 1.5.6.4, 2.2.0.0 → `extension/module`. 2.3.0.2 → `extension/extension`. 3.0.3.8, 4.0.2.3 → `marketplace/extension`.
    - `routeMethodSeparator`: 3.0.3.8 → `/`. 4.0.2.3, 4.1.0.0 → `.`.
    - `needsPhpTemplateEngine`: 2.3.0.2 false, 3.0.0.0 true, 3.0.3.8 true, 4.0.2.3 false.
    - `languageImageUrl`: 1.5.6.4, 2.2.0.0, 4.0.2.3 (one test for each branch).
  - CORE `getLangImgUrl()` OC4 branch: no unit test (CORE reads the `VERSION` constant). The package probe in step 10 covers it. If a test is cheap in `tests/test_client_translate_expert_translate_loop.php`, add it.
  - JS tests (`tests/js/test_client_translate_expert_js_helpers.js`). Add an optional URL argument to `makeWindow()`:
    - `getOcTokenName`/`getOcToken`: `?user_token=abc` → `user_token`, `abc`. `?token=x` → `token`, `x`. No parameter → `token`.
    - A request URL on an OC 3.0 URL has `&user_token=abc`.
    - `teRoute`: default → `extension/module/client_translate_expert/analize`. Prefix `module` → `module/client_translate_expert/analize`. OC4 values → `extension/client_translate_expert/module/client_translate_expert.analize`.
    - `initModule()` does not change a prefix that the header set.
  - Keep the current JS assertions on `&token=` (default name).
  - `tests/test_client_translate_expert_ajax_guard.php` must pass with no change. If a new code path in the tested methods reads `VERSION`, define `VERSION` in that test and record the reason.
  - Add the new test to the test lists in `CLAUDE.md`, `.claude/project-workflows.md` and `.claude/agents/ote-build-test.md`.
- [x] 10. Package verification (behavioral):
  1. Baseline: `git worktree add docs/tasks/68-runtime-version-rewrites/tmp/baseline-src origin/main`. Run `./build.sh` in the worktree (it uses its own `_version.txt` and writes only into the worktree). Unzip each package into `tmp/baseline/<oc>/` (oc1.5, oc2.0, oc2.1, oc2.2, oc2.3, oc3.0, oc4.x). Remove the worktree after the step (`git worktree remove`).
  2. New: bump the version, run `./build.release.sh`, and unzip `releases/v<ver>/*.full.zip` and its packages into `tmp/new/<oc>/`.
  3. Static checks on `tmp/new`: no `user_user_token`, no `module_module_`, no `marketplace/marketplace`, no `module/client_translate_expert.client_translate_expert`. The helper text `extension/extension` and `token` is intact in the 2.2 and 3.0 packages (proof that the old `sed` lines are gone).
  4. PHP probe `tmp/probe_rules.php`: run one PHP process for each package controller and model, with stubs and the matching `VERSION` (1.5.6.4, 2.0.3.1, 2.1.0.2, 2.2.0.0, 2.3.0.2, 3.0.3.8, 4.0.2.3). Print `tokenQuery`, `extensionListRoute`, the debug log routes, `settingKey` of the 10 keys and the group code, and `needsPhpTemplateEngine`. Compare each value with the literal in the baseline package (`grep` of the baseline CTRL, MODEL and TPL).
  5. JS probe `tmp/probe_js.js`: load each new package JS in jsdom with the constants that its header emits. Compare `teRoute()` for the 10 methods with the route literals of the baseline JS of the same package.
  6. `diff -r tmp/baseline/<oc> tmp/new/<oc>` for each package. Explain each changed file. The expected changes are only the moved code, the bare setting names in the OC 3.0 TPL and OC4 TWIG, and the version string.
  7. `php -l` each changed PHP file of the new packages. Check the added lines for PHP 7+ syntax outside the OC4 blocks.
  8. Write the results to `68-audit.md` (section "Package verification").
- [x] 11. Docs:
  - `.claude/project-workflows.md`: change the gotcha row at line 81. The fixed lists now hold only the `new X(` namespace calls. Setting keys use `settingKey()`. JS routes use `teRoute()`. CTRL method routes use `routeMethodSeparator()`. Change the TPL row at line 37 (the controller sets `template_engine` at runtime).
  - `.claude/agents/ote-build-test.md` (104–107, 123), `.claude/agents/ote-implementer.md` (37–40), `.claude/agents/ote-code-reviewer.md` (51–56): same change.
  - `CLAUDE.md`: no "fixed list" text exists. Add only the new test.
  - Write the doc changes in ASD-STE100.
- [x] 12. Bump version + build + all tests (`ote-build-test`). Run step 10 again on this build if a code change happened after step 10.
- [x] 13. Independent review (`ote-code-reviewer`, Opus) → `68-runtime-version-rewrites-review.md`. Tell the reviewer to use `git diff HEAD` and to read the audit and the probe results.
- [x] 14. Address the review findings (auto-approve, prefer to fix).
- [x] 15. Bump version + build + tests (verify loop). Run the package probes again if code changed.
- [x] 16. Re-review: the agent decides. If the fixes change logic, run a re-review → `-review-v2.md`.
- [x] 17. Improvements pass → `68-runtime-version-rewrites-improvements.md`. Apply the improvements to `.claude/**`.
- [x] 18. Commit, push, PR:
  - Check that the repo root has no zip or build folders, and that `tmp/` holds no tracked file.
  - Feature commit: `sources/`, `build.sh`, `tests/`, `CLAUDE.md`, the fixed-list doc changes of step 11 and `docs/tasks/68-runtime-version-rewrites/*.md`. Message: `refactor: move the OC-version rewrites from build.sh into the module code`, body `closes #68`.
  - Workflow commit: the improvements to `.claude/**` from step 17. Message: `chore: apply the improvements of task 68 to the workflow`.
  - End each message with the attribution line from the session. Use a HEREDOC.
  - `git push -u origin refactor/68-runtime-version-rewrites`. `gh pr create` to `main`. In the PR body, give the audit summary, the probe results and a request for a store run on OC 2.2, 3.0 and 4.x (A9).


## Update 2026-11-17 (review findings, auto-approve)
Review file: `68-runtime-version-rewrites-review.md`. No Critical finding.
- I1 (Important) fixed: the bullet about the `.tpl` and `.oc4.twig` is back in `.claude/agents/ote-code-reviewer.md`.
- I2 (Important) fixed: a JS test checks that only `teDefaultModulePrefix` assigns `js_const_module_prefix`.
- M2 fixed: a PHP test checks that the OC4 event emits the prefix and the separator.
- M3 fixed: a PHP test finds a bare `config->get('client_translate_expert_...')` call in the controller and the model.
- M1 not fixed in code: OC 2.0–2.2 read the prefix from the cached `header.php`. The PR text tells the admin to refresh the modifications.
- M4: no action.
- Re-review: skipped. The fixes change tests and docs only, and no source logic.
Findings: package probes (`68-audit.md`) found no unexpected difference from the baseline. A9 stays unverified (no store run).

## Done
- Moved the token name, the `module_` setting prefix, the extension list route, the JS route prefix and separator, the `template_engine` setting, the flag URL and the model `require_once` from `build.sh` into the code with runtime version checks. Removed 4 dead or redundant rewrites.
- `build.sh` keeps only the rewrites that code cannot do at runtime (class names, folders, namespaces, OC4 block strip, OCMOD and vQmod text, SQL fragment).
- Tests: new `tests/test_client_translate_expert_version_rules.php` (52 checks), 653 JS checks. Package probes match the baseline on all 7 packages.
- Open: a store run on OC 2.2, 3.0 and 4.x (A9). OC 2.0–2.2 admins must refresh the modifications after the update (M1).
- Deferred: route path rewrites (lines 65, 300), model property rewrites (66, 304) and the OC 1.5–2.1 only rewrites.
