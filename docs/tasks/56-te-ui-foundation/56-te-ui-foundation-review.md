# 56-te-ui-foundation review

Reviewed range: `origin/main...HEAD` (one commit, `d589eaa`). Working tree clean.

Checks that I ran:

- `php -l` on the new library, the new test and the admin controller: clean.
- All 7 test scripts in `tests/`: 0 failed (`test_client_translate_expert_i18n.php`: 34 passed).
- `node --check` on the JS: clean. The jsdom probe in `tmp/jsprobe/`: 37 passed with jQuery 3.7.1 and 37 passed with jQuery 1.7.2.
- Built packages of `releases/v04.01.0176` (extracted to the scratchpad): the 1.5 vQmod XML and the 3.0 OCMOD XML hold `te_i18n_global`, the `$this->data[` rewrite applies on 1.5, the `.php` paths survive the 3.0 `.tpl` rewrite, and the PHP of both add blocks passes `php -l`. The OC4 library has the namespace. The OC4 controller, the 3 OC4 language files and the OC4 Twig hold the new code. The OC4 block is absent from the 1.5, 2.3 and 3.0 controllers.
- Core probes with `gh api`: OC 1.5.6.4 and 2.0.3.1 use `config_admin_language` and `DIR_LANGUAGE` (`admin/index.php:120-124`, `system/library/language.php:16`). OC 4.1.0.0 uses `config_language_admin` (`admin/controller/startup/language.php:27`).
- The 12 FA6 names in `teIconFa6Names` exist in the FA 6.4.2 and 6.7.1 CSS files in `tmp/`.
- The new CSS has 94 open and 94 close braces. Every new rule starts with `.te-app` or `.te-header-count`.

### Strengths

- The library `client_translate_expert_i18n.php` is pure PHP 5.6 with no OpenCart dependency. It reads the language file in a function scope, so the shared language object of the page stays unchanged. This is the correct fix for the header problem that the plan describes.
- The JSON output uses the 4 `JSON_HEX_*` flags in the library, in the controller and in the test. The test shows that `</script>` and quotes cannot close the script block. The `{}` fallback keeps the value a JS object.
- The XML add block uses no `; ?>`, `<?php echo $`, `.tpl` or `token`. It survives every build rewrite. I confirmed this in the built packages, not only from the brief.
- `build.sh` has the new library in the OC4 move list and in the namespace list. The OC4 package shows the result.
- The JS block is ES5. All text goes in with `.text()`. The probe shows that an HTML response cannot inject markup through `teAlerts`, with jQuery 3.7.1 and with jQuery 1.7.2.
- The unit test uses temp folders, fails on any PHP warning, and also reads the 3 real module language files for the global keys. This catches a missing key in a translation at test time.
- The 9 new keys exist in `en-gb`, `ru-ru` and `uk-ua`.
- The change is additive. No current function, template block or CSS rule changed. The existing init code runs after the new code loads (probe). The "no change in behavior" requirement holds in the source.
- `CLAUDE.md` and `.claude/project-workflows.md` document the new test and the new library.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. **The JS helpers have no permanent test.** `docs/tasks/56-te-ui-foundation/tmp/jsprobe/probe.js` is the only check of `teT`, `teIcon`, `teBs` and `teAlerts`. The `tmp/` folder is throwaway, and `node_modules` lives in it. `teAlerts` is the text-only boundary for server responses, and the next tasks build every tab on these helpers. A later change can break them with no signal. Fix: move `probe.js` and `package.json` to `tests/js/`, add the command to the **Tests** section of `CLAUDE.md`, and keep `node_modules` out of git. If the user does not want Node in the test toolchain, record that decision in the plan and keep the probe out of `tmp/`.

#### Minor (nice to have)

2. **`sources/upload/admin/view/javascript/client_translate_expert.js:1489-1511`.** `teBs.modal` repeats the show and hide-pending logic of `showTranslateExpertModal` and `hideTranslateExpertModal` (JS:592-614). The plan says "do not change the current functions", so this is a planned duplicate. Record a follow-up: make the two old functions call `teBs.modal` in the next task, so the two copies do not drift.

3. **`sources/upload/admin/controller/extension/module/client_translate_expert.php:962-966`.** The `is_file` guard in `getGlobalI18nJson()` is dead code. `isCurrentRouteExcluded()` runs first in the same event and does an unguarded `require_once` of a sibling file from the same `system/library/` folder. If that folder is missing, the page has already stopped. Also, this method calls the class through a string variable (`$i18n::toJson(`), while `isCurrentRouteExcluded()` uses the direct FQCN. Use one style. The guard in the XML is different: it protects the OC 1.5–3.0 header when the module files are gone and the modification cache is stale, the same case as the route filter guard. Keep that one.

4. **`sources/upload/admin/view/javascript/client_translate_expert.js:1612-1625`.** `teAlerts.parseResponse` accepts a JSON array as `data` when the input is an object or a jqXHR with `responseJSON`. Then `fromResponse` shows "Internal error" with no details. A JSON array as a string gives "not JSON" with the text in Details. Make the two paths the same: treat `Array.isArray(response)` and `Array.isArray(response.responseJSON)` as non-JSON.

5. **Language files, keys `te_error_internal` and `te_global_error_internal`.** The two keys hold the same text in all 3 languages. `teT` already falls back to `te_i18n_global`, so the module page can use `te_global_error_internal` and the module key can go. Decide this in the task that moves the hard-coded "Internal error" string in the JS. Not a defect.

6. **`sources/upload/admin/view/stylesheet/client_translate_expert.css:124`.** `.te-hidden` and all `.te-alert*` rules exist only under `.te-app`. `teAlerts.show(container, ...)` accepts any container. If a future caller passes a container outside `.te-app`, the Details `<pre>` shows open and the alert has no style. Add one line to the `teAlerts` comment: the container must be inside `.te-app`.

### Open plan step

The store run on OC 1.5, 2.3, 3.0 and 4.x (plan assumption A7) is not done. It needs the WIP push and the host rebuild. The package greps and the probe cover the build and the pure helpers, but not a real page load. This is a plan step, not a code defect.

### Assessment

**Ready to merge?** Yes. The change is additive, PHP 5.6 safe outside the OC4 block, scoped in CSS, text-only in JS, and verified in the built packages for every OpenCart version. The one Important finding is about where the JS probe lives, not about the code. Do the store run before the final merge.
