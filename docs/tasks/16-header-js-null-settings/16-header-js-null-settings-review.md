# Review: 16-header-js-null-settings

Scope: `git diff HEAD` on `fix/16-header-js-null-settings`. That covers `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:13-14` and `_version.txt` (04.01.0108 → 04.01.0109). The plan is `16-header-js-null-settings_plan.md`.

### Strengths
- The fix is minimal and sits at the right layer. The value is normalised in the controller-side `$data` assignment, so every template flavour gets an int. That covers the `.tpl` on 1.5–2.3 and the rewritten `{{ }}` Twig on 3.0. No per-version template branching is needed.
- The OCMOD header now matches the OC4 event handler, which already does `(int)$isEnabled` / `(int)$showCharCount` (`admin/controller/extension/module/client_translate_expert.php:793-794`).
- `(int)` is valid in PHP 5.6 and survives every `build.sh` rewrite:
  - 1.5 only rewrites `$data[` to `$this->data[` (`build.sh:114`).
  - 3.0 prefixes the setting-key strings (`build.sh:127-136`) and rewrites only `<?php echo $` / `; ?>` (`build.sh:139-140`). Neither pattern occurs on the controller side.
- The trailing-whitespace cleanup on line 13 is harmless.
- The version was bumped as CLAUDE.md requires.

### Completeness across packages
- **2.3:** the XML is copied from `sources/` with only `{client_version}` substituted (`build.sh:44-45`).
- **2.2 / 2.1 / 2.0:** each is a `cp -r` of the previous release dir (`build.sh:61, 93, 103`), and none of them modify the XML. The cast ships unchanged.
- **1.5:** derived from the 2.0 dir. The cast is preserved under `$this->data[...]`.
- **3.0:** derived from `sources_release` with `module_`-prefixed keys. The cast is preserved.
- **4.0:** the XML is removed (`build.sh:157`). The event handler already casts.

The fix is complete for every OCMOD package.

### Other injected values
- `client_translate_expert_languages` (OCMOD, `ocmod.xml:16`) is `json_encode($this->model_localisation_language->getLanguages())`.
  - `getLanguages()` always returns an array, so the normal output is `{...}` or `[]`. Neither is empty, and this value does not have the null-setting problem.
  - The only theoretical empty output is when `json_encode` returns `false`, for example on invalid UTF-8 in a language name. That case is pre-existing, unrelated to #16 and very unlikely. See Minor.
- OC4 handler (`client_translate_expert.php:795`) already falls back to `'{}'` for a falsy languages JSON. No issue.

### Effect on JS consumers
- `client_translate_expert.js:9-10`: `typeof ... !== 'undefined' && document.js_const_client_translate_expert_is_enabled`.
  - Before the fix, the unsaved state was a parse error, so the script block never ran. Now it is `0`, which is falsy, so the module stays disabled. That is the intended behaviour before activation.
  - A saved `'1'` becomes `1`, which is still truthy. A saved `'0'` or `''` becomes `0`, which is still falsy.
- `client_translate_expert.js:1012` uses `!document.js_const_client_translate_expert_show_char_count_is_enabled`. The same truthiness analysis applies, so behaviour is unchanged.

No other reader of these constants exists (grep over `sources/`).

### Issues

#### Critical (must fix)
None.

#### Important (should fix)
None.

#### Minor (nice to have)
- `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:16`: `json_encode(...)` can return `false`, which echoes as an empty string and gives the same `= ;` syntax error for `js_const_client_translate_expert_languages`.
  - This is pre-existing and needs malformed language data, so it is out of scope for #16.
  - Optional hardening to mirror the OC4 handler: `$json = json_encode(...); $data['client_translate_expert_languages'] = $json ? $json : '{}';` (PHP 5.6-safe).
- There is no automated regression test for OCMOD output. The plan acknowledges this and uses the Docker before/after run as evidence. That is acceptable given there is no harness.

### Assessment
**Ready to merge?** Yes. The two-character cast fixes the reported syntax error in every OCMOD package (1.5, 2.0–2.3, 3.0), and the existing OC4 handler already does the same. It survives all `build.sh` rewrites and leaves JS truthiness unchanged for saved values.
