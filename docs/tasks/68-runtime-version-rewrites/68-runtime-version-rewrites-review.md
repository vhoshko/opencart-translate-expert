# 68-runtime-version-rewrites: review

Scope: `git diff HEAD` on `refactor/68-runtime-version-rewrites`, plus `tests/test_client_translate_expert_version_rules.php`. The plan is `68-runtime-version-rewrites_plan.md`. A1–A8, A10 and the deferred rewrites (65/300, 66/304, OC 1.5/2.0/2.1-only) are settled. The package diff against the baseline is in `68-audit.md` (another agent), so this review does not repeat it.

Tests run (PHP 8.2, Node): all 9 PHP suites and the JS suite pass. The new suite gives 48 passed, 0 failed. `tests/test_client_translate_expert_ajax_guard.php` passes with no change.

### Strengths

- Each helper takes `$version` as an argument. The tests call each branch with no `VERSION` constant. The call sites pass `VERSION`.
- The helpers are `private static` methods inside the class, not in the polyfill zone. The OC4 polyfill delete (`build.sh:271-275`, range `// add missed functions` → first `class ` line) cannot touch them.
- The removed `build.sh` lines and the code moves are in one change. No old `sed` line stays that can damage the new text. I checked each remaining rewrite against the new code:
  - 2.2 `extension/module/` → `module/` (`build.sh:65`): `extensionListRoute()` returns `'extension/module'` with no slash, so the rewrite does not change it. The debug log routes become `'module/client_translate_expert' . '/' . 'downloadDebugLog'`. This is correct.
  - 2.2 `extension_module` → `module` (`build.sh:66`): no new match.
  - 2.1 `setOutput(...)` (`build.sh:96`): the text of the `setOutput` line did not change. The new `needsPhpTemplateEngine()` guard is on the line before it.
  - 1.5 `\$data\[` (`build.sh:112`, `:116`): `$this->session->data[$name]` and the new XML lines do not match.
  - 3.0 XML `.tpl`, `<\?php echo \$`, `; \?\>` (`build.sh:138-140`): the new XML lines contain no `tpl`, no `php echo $` and no `;` before `?>`.
  - 4.x `extension/module/client_translate_expert` (`build.sh:278`): the debug log routes become `extension/client_translate_expert/module/client_translate_expert.downloadDebugLog`. The OC4 event text `"extension/client_translate_expert/module"` does not match the search text.
  - `add_namespace`: the MODEL starts with `<?php`, an empty line and then the `if`. The `namespace` line goes before the `if`. This is valid.
- Behavior is the same as the old build on each OC version:

  | OC | `tokenName` | `extensionListRoute` | key prefix | template engine | JS prefix / separator |
  |---|---|---|---|---|---|
  | 1.5, 2.0, 2.1, 2.2 | `token` | `extension/module` | none | no | `module` (XML) / `/` |
  | 2.3 | `token` | `extension/extension` | none | no | `extension/module` (XML) / `/` |
  | 3.0 | `user_token` | `marketplace/extension` | `module_` | yes | `extension/module` (XML) / `/` |
  | 4.0.2.x, 4.1 | `user_token` | `marketplace/extension` | `module_` | no | `extension/client_translate_expert/module` / `.` (event) |

- Setting save: `prefixSettingKeys()` gives the same POST keys as the old 3.0 and 4.x templates. All 8 form fields of the TPL and TWIG are in the old 10-key list, and `te-analization-mode` is outside the form. The group code is `module_client_translate_expert` on 3.0 and 4.x, and `client_translate_expert` below 3.0. The counter keys and the `client_translate_expert_usage` group did not change. The catalog cron controller did not change.
- The XML header emits `document.js_const_module_prefix` on each page that loads the JS. The emission does not depend on the module status, so the disabled-module branch (JS:34) also gets it. If the route is excluded, the header does not load the JS at all.
- `languageImageUrl()` OC4 branch is the same as the removed `sed` body. It also adds an `isset` guard. The CORE `getLangImgUrl()` OC4 branch is the same loop as the removed `sed` body. `VERSION` and `version_compare` resolve to the global names in the OC4 namespace.
- `new \Exception(` is valid on PHP 5.3+ in the global namespace. All `catch` blocks in `sources/` already use `\Exception`.
- PHP 5.6 compatibility: the new code outside the OC4 blocks uses only `array` type hints, `self::` and `static`. The new JS is ES5 (`var`, no arrow functions).
- The JS keeps a safe default. If the URL has no `user_token`, the name is `token`. If the header gives no prefix or separator, `teRoute()` uses `extension/module` and `/`.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

**I1. The reviewer agent lost its template checklist item.** File: `.claude/agents/ote-code-reviewer.md:51-53`. Severity: Important. Fix cost: 1 line.
The diff deletes the bullet "OC 1.5–3.0 all render the `.tpl` as a PHP template. Only OC 4.0 uses Twig. A UI change must appear in both `client_translate_expert.tpl` and `client_translate_expert.oc4.twig`, each in its own syntax." No new line replaces it, and the file has no other mention of the TWIG file. This rule is not related to issue #68. Put the bullet back after the new runtime-rules bullet.

**I2. No test proves that `initModule()` and `initTranslateExpert()` keep the header prefix.** Files: `sources/upload/admin/view/javascript/client_translate_expert.js:34`, `:682`, `tests/js/test_client_translate_expert_js_helpers.js` (`testTokenAndRoute`). Severity: Important. Fix cost: small (about 5 lines).
Plan step 9 asks for "`initModule()` does not change a prefix that the header set". The test calls only `teDefaultModulePrefix()`. No test calls `initModule()` or `initTranslateExpert()` (grep of the test file: no match). If someone writes `document.js_const_module_prefix = 'extension/module';` again at line 34 or 682, all tests pass. But AJAX then fails on OC 1.5–2.2 and 4.x, and still works on 2.3 and 3.0, the versions that developers test most often. Fix (either one):
- Add a static check: the JS source has exactly one `js_const_module_prefix =` assignment, and it is inside `teDefaultModulePrefix()`.
- Or call `initTranslateExpert()` in jsdom with `document.js_const_module_prefix = 'module'`, `is_enabled = 0` and a `#te-summary-count` element, with a `$.get` stub. Then assert that the request URL starts with `?route=module/client_translate_expert/`.

#### Minor (nice to have)

**M1. A stale OCMOD cache on OC 2.0–2.2 breaks the AJAX routes.** Files: `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:58`, JS:361-371. Severity: Minor. Fix cost: small. Status: unverified (no core probe was run for the OCMOD refresh flow).
Before this change, the 2.2-based packages had `'module'` in the JS. Now the prefix comes from the modified `header.php`. If an admin updates the files and does not click Refresh in Extensions > Modifications, the old header emits no prefix. The JS then sends `route=extension/module/client_translate_expert/...`, and this route does not exist on 2.0–2.2. OC 2.3 and 3.0 are safe, because the default is correct there. OC 1.5 is safe, because vQmod reads the XML file directly. OC4 is safe, because the event code is in the controller. Fix: put "Refresh the modifications after the update" in the PR text and release notes. Optional: in `teDefaultModulePrefix()`, use `module` if `getParameter('route')` starts with `module/`. This covers the module page.

**M2. The OC4 header constants have no test.** File: `sources/upload/admin/controller/extension/module/client_translate_expert.php:1009-1010`. Severity: Minor. Fix cost: about 3 lines.
`eventHeaderViewAfter` is inside an OC4_EVENT block, so the unit tests do not run it. `testXmlHeader` checks the XML text. Add the same static check on the CTRL source for `document.js_const_module_prefix = "extension/client_translate_expert/module";` and `document.js_const_module_method_separator = ".";`.

**M3. No static check finds a bare setting read in the CTRL or the MODEL.** File: `tests/test_client_translate_expert_version_rules.php:153-160`. Severity: Minor. Fix cost: about 5 lines.
`testXmlHeader` fails on a bare `config->get('client_translate_expert_` in the XML. The CTRL and the MODEL have no such check. A new setting read without `settingKey()` works on 1.5–2.3 and reads an empty value on 3.0 and 4.x. Add the same `preg_match` for the CTRL and the MODEL. Allow `client_translate_expert_languages_json`, which is a runtime config value, not a setting.

**M4. The JS default is in two places.** File: JS:361-371. Severity: Minor. Fix cost: none needed.
`teDefaultModulePrefix()` and `teRoute()` both hold `'extension/module'`. `teRoute()` already falls back, so `teDefaultModulePrefix()` is not necessary for routes. This is harmless. Keep it or remove it. If you keep it, I2 still applies.

### Assessment

**Ready to merge?** With fixes. I found no behavior change for any OC version. The remaining `build.sh` rewrites cannot damage the new code, and the settings save path gives the same keys as before. Fix I1 (an accidental doc deletion) and I2 (a test for the one regression that the old build hid). M1 needs only a line in the PR text.
