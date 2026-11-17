# Audit of the build.sh rewrites (issue 68)

## Introduction
This audit lists each rewrite in `build.sh`. The module code now makes some of the OC-version choices at run time. The build no longer needs these rewrites.
Each row has one verdict.

- **move** = the rewrite moved into the module code.
- **dead** = the search text is not in `sources/`. The line is deleted.
- **must-stay** = the code cannot do it at run time, or the rewrite is a file or folder layout item.
- **defer** = possible at run time, but not cheap or not in the scope of this task.

## Audit table

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

## Package verification
This section records the check of the built packages. The baseline is `origin/main` (`7906a96`, version 04.01.0208), built with `build.sh release` in a temporary worktree. The new packages are from `releases/v04.01.0209/*.full.zip`. All files are unpacked in `docs/tasks/68-runtime-version-rewrites/tmp/baseline/<oc>` and `tmp/new/<oc>`. The probe scripts are in `tmp/probe/`.

### Check 1: static greps on the new packages
Search text: `user_user_token`, `module_module_`, `marketplace/marketplace`. Result for all 7 packages: no match. Status: pass.
PHP lint of every `.php` file in the new packages: no error. Status: pass.

### Check 2: PHP probe (controller and model helpers)
The probe loads the controller and the model of each new package with stubs. It sets `VERSION`. It calls the private static helpers by reflection. It compares each result with the literal that the baseline controller or model hard-coded. For OC4 it uses the namespaced class names.

| Package | VERSION | Checks | Result |
|---|---|---|---|
| oc1.5 | 1.5.6.4 | 20 | pass |
| oc2.0 | 2.0.3.1 | 20 | pass |
| oc2.1 | 2.1.0.2 | 20 | pass |
| oc2.2 | 2.2.0.0 | 20 | pass |
| oc2.3 | 2.3.0.2 | 20 | pass |
| oc3.0 | 3.0.3.8 | 20 | pass |
| oc4.x | 4.0.2.3 | 19 | pass |

An extra run with VERSION 4.1.0.1 on oc4.x also passes.
The checks are:
- `tokenName` equals the token name of the baseline (`token` or `user_token`).
- `settingKey` equals the baseline literal for the group code and for the 10 keys.
- The model `settingKey` equals the baseline `debug_status` literal.
- `prefixSettingKeys` maps `client_translate_expert_*` keys and keeps other keys.
- `extensionListRoute` equals the route of the baseline redirect after `editSetting`.
- `routeMethodSeparator` equals the separator of the baseline `downloadDebugLog` and `clearDebugLog` routes.
- `needsPhpTemplateEngine` is true only if the baseline has the `template_engine` line (oc3.0 only).
- `languageImageUrl` equals the baseline flag URL (`view/image/flags/` for VERSION below 2.2, `language/<code>/<code>.png` for 2.2 to 3.0, and the `image` value for OC4).

### Check 3: JS probe
The probe loads the new `client_translate_expert.js` of each package in jsdom with jQuery 3.7.1. It sets the header values that the package emits. For OC 1.5 to 3.0 the value comes from the XML expression `version_compare(VERSION, '2.3', '<') ? 'module' : 'extension/module'`. For OC4 the values come from the event code (`extension/client_translate_expert/module` and separator `.`). The page URL has the token name of the OC version.
The probe compares `teRoute()` for the 10 distinct methods with the route literals of the baseline JS of the same package. The 10 methods have 11 call sites, because `translate` is called two times. It also compares the token parameter.
Result: 11 of 11 checks pass in all 7 packages. Status: pass.
Other facts:
- No hard-coded `&token=` or `&user_token=` is left in any new JS file.
- With no `user_token` in the URL, `getOcTokenName()` returns `token`.
- The baseline JS ignored the header and set the prefix in `initModule()`. The new JS keeps a header value and sets the default only if no value exists.

### Check 4: diff of baseline and new packages
Changed files, by class:

| Class | Files | Packages | Explanation |
|---|---|---|---|
| Version string | `OpenCartTranslateExpertClient*.xml` (`<version>`, `?v=`), controller `getCurrentVersion()`, `install.json` | all | 04.01.0208 to 04.01.0209. Expected |
| Moved code (helpers) | admin controller | all | 7 new private static helpers. Call sites use `self::settingKey(..., VERSION)`, `$this->tokenQuery(VERSION)` and `self::extensionListRoute(VERSION)`. Expected |
| Moved code (template engine) | admin controller | oc3.0 (the line is the same, now guarded) | `$this->config->set('template_engine', 'template')` is inside `if (self::needsPhpTemplateEngine(VERSION))`. Expected |
| Moved code (flag URL) | admin controller, `client_translate_expert_core.php` | all | The flag block calls `languageImageUrl()`. `getLangImgUrl()` has an OC4 branch. In oc4.x the old OC4-only body is now the first branch and the 2.x branches follow. Expected |
| Bare setting names | oc3.0 `.tpl`, oc4.x `.twig` | oc3.0, oc4.x | `module_client_translate_expert_*` becomes `client_translate_expert_*` in field names, ids, view variables and the 2 inline script ids. The controller sets the same bare view variables. The POST keys get the prefix in `prefixSettingKeys()`. Expected |
| Bare setting names (controller) | admin controller | oc3.0, oc4.x | POST and view-data keys are bare. Config reads use `settingKey()`. Expected |
| Header XML | `OpenCartTranslateExpertClient.ocmod.xml`, vQmod XML | oc1.5 to oc3.0 | New `$teKeyPrefix` variable. New `js_const_module_prefix` output. For oc1.5 to oc2.2 the config reads are unchanged at run time, because the prefix is empty. Expected |
| Header event | oc4.x controller | oc4.x | New `js_const_module_prefix` and `js_const_module_method_separator` output. Expected |
| Model | admin model | all | `require_once` has the `DIR_EXTENSION` condition. New `settingKey()` for `debug_status`. In oc4.x the `else` branch (`DIR_SYSTEM`) is dead code. Expected |
| JS | `client_translate_expert.js` | all | New `getOcTokenName()`, `teDefaultModulePrefix()` and `teRoute()`. The 11 route call sites use them. The two `URLSearchParams` blocks use `getOcToken()`. Expected |
| Exception class | `vh_google_translator.php` | oc1.5 to oc3.0 | `new Exception(` becomes `new \Exception(`. OC4 already had it. Expected |
| VHGoogleTranslator name | `client_translate_expert_core.php` | oc4.x | `new \Opencart\Extension\...\VHGoogleTranslator(` becomes `new VHGoogleTranslator(`. Both classes have the namespace `Opencart\Extension\ClientTranslateExpert\System\Library`, so the name resolves to the same class. Expected (audit row 336) |

The files `client_translate_expert_core.php` (oc1.5 to oc3.0) differ only in the OC4 branch of `getLangImgUrl()`. The OC4 branch cannot run there, because the `version_compare(VERSION, '4.0', '>=')` check is false.
No other file differs. The catalog cron controller, the language files, the CSS and the other libraries are the same.

### Unexpected findings
No difference is a failure. Two low-risk notes:
1. `languageImageUrl()` for OC4 returns an empty string if `$language['image']` is not set. The baseline read `$language['image']` with no check. This is safer and gives the same result when the key exists.
2. `getLangImgUrl()` in `client_translate_expert_core.php` reads `VERSION` first for the OC4 check. The baseline already read `VERSION` in the same function (the 2.2 check), and `tools/` does not call it. So this is not a regression.
