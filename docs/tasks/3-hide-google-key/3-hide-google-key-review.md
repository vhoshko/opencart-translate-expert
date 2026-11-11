# 3-hide-google-key review

Scope: `git diff HEAD` on branch `feat/3-hide-google-key`. The diff changes 7 files: the admin controller, 3 language files, the 2 templates and `_version.txt`.

Checks that I ran:

- `php -l` on the controller, the 3 language files and the `.tpl`: no syntax errors.
- `php tests/test_vh_google_translator.php`: 24 passed, 0 failed, 6 skipped (no API key).
- `php tests/test_client_translate_expert_route_filter.php`: 40 passed, 0 failed.
- I read `build.sh` to trace the rewrites. I did not run a build.

### Strengths

- The change is small and matches the plan. The input name and value do not change, so the save flow and the stored setting stay the same.
- Both templates have the change, each in its own syntax. The `.tpl` uses the Bootstrap 3 markup (`input-group-btn`, `btn-default`). The `.oc4.twig` uses the Bootstrap 5 markup (`btn-outline-secondary`).
- The handler sets the native `input.type` property. It does not call `.attr('type', …)`, which throws in jQuery 1.8 and earlier. OC 1.5 core loads jQuery 1.7, so this choice is correct.
- The handler uses only `.on()`, `.text()` and `.data()` with a dashed key. All three work from jQuery 1.7 to jQuery 3.x.
- The IDs survive the `module_` rewrite. `build.sh:127` and `build.sh:394` replace `client_translate_expert_key` in the full template text. The input `id`, the button `id` and both selectors in the handler get the prefix together.
- The inline handler is the correct location. `client_translate_expert.js` does not get the setting-key rewrite, so a selector in that file would break on OC 3.0 and 4.x.
- The button has `type="button"`, so a click does not submit the form.
- The 2 new strings exist in `en-gb`, `ru-ru` and `uk-ua`. The controller sends them to `$data`, and the OC 1.5 rewrite (`$data[` → `$this->data[`) covers them.
- The change adds no PHP 7+ syntax, no AJAX method, no library class and no setting key. No `build.sh` entry is necessary.
- The labels live in `data-text-show` and `data-text-hide`. The handler has no hardcoded text.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:56` and `client_translate_expert.oc4.twig:56` — the `type="password"` input brings the browser password manager into the settings form. The plan lists this as an unverified assumption, and it is still unverified. There are two risks:

   - Autofill on page load. If a browser or a password manager extension ignores `autocomplete="new-password"`, it puts the admin login password into the key field. The user cannot see this, because the field is masked. The next save then overwrites the Google key, and all translations fail.
   - Save prompt on submit. `new-password` tells the browser that this is a new credential, so the browser can offer to save the key as the password for the admin host. If the user accepts, the browser can later put the API key into the admin login form.

   Do the manual check from the plan before the merge. Test Chrome and Firefox as a minimum, with a saved admin login. Check that the key field stays unchanged on load and after a save. If the check fails, one possible fix is to keep `type="text"` and to mask it with CSS (`-webkit-text-security: disc`, plus a font-based fallback for browsers that do not support it). The toggle then changes a class and not the input type.

#### Minor (nice to have)

1. `client_translate_expert.tpl:348` and `client_translate_expert.oc4.twig:339` — the handler binds after `$('#te-analization-language-to').change();`. If a `change` handler throws, the script block stops and the button does nothing. Recent commits (#29, #31) fixed throws in the admin JS, so this is a real possibility. Bind the handler before the `.change()` call to remove the dependency.

2. `client_translate_expert.tpl:57` and `client_translate_expert.oc4.twig:57` — the button gives no state to assistive technology. Add `aria-pressed="false"` and update it in the handler, or add `aria-controls` with the input `id`. This is optional.

3. The handler is the same in both templates (6 lines). This duplication is acceptable for the reason given in Strengths. Do not extract it.

### Assessment

**Ready to merge?** With fixes.

The code is correct, and it survives all `build.sh` rewrites that I traced for OC 1.5 through 4.x. The one open item is the password manager behavior of the `type="password"` input. It can overwrite the stored key without a visible sign, so do the manual browser check before the merge. I did not run a build or a browser test, so the rendered result on each OpenCart version is not confirmed by this review.
