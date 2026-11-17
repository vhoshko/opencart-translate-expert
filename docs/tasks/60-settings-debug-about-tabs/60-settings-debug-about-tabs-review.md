# 60-settings-debug-about-tabs: review

Scope: `git diff HEAD` on branch `feat/60-settings-debug-about-tabs` (v04.01.0195). The review reads the plan `60-settings-debug-about-tabs_plan.md` as requirements. It does not check `tmp/`.

Checks done:

- Form field `name` and `id` sets, old (`git show HEAD:`) against new, for the `.tpl` and the `.oc4.twig`.
- The JS ids from the brief, the `resetTranslationCache()` handler and the `te-js-collapse` handler (`client_translate_expert.js:1263`).
- BS3 markup in the `.tpl` and BS5 markup in the `.oc4.twig` (radio, checkbox, input-group, tooltip, alert dismiss).
- The 8 new `te_*` keys in `en-gb`, `ru-ru` and `uk-ua`, and in the controller key list.
- The built packages from `releases/v04.01.0195/*.full.zip`, unzipped to `docs/tasks/60-settings-debug-about-tabs/tmp/rv/`. The packages are oc1.5, oc3.0 and oc4.x.
- Tests: `test_oc4_twig_filters.php` (29 passed), `test_client_translate_expert_i18n.php` (34 passed), `test_vh_google_translator.php` (24 passed, 6 skipped), `test_client_translate_expert_route_filter.php` (40 passed). `php -l` passes on the controller, the 3 language files and the `.tpl`.
- OpenCart core probe: `system/library/request.php` `clean()` at tags `1.5.6.4` (line 33), `2.0.3.1` (line 26) and `3.0.3.8` (line 46). Each applies `htmlspecialchars($data, ENT_COMPAT, 'UTF-8')` to all POST values.

### Strengths

- All form field names stay the same in both templates. The `name`/`id` diff shows only these changes:
  - 4 new wrapper ids (`te-settings-app`, `te-debug-app`, `te-about-app`, `te-try-panel`).
  - 2 new radio ids in the `.tpl` (`debug-status-enabled`, `debug-status-disabled`).
  - The ids and names of the old `<strong>` version elements and of the `reset_translation_cache` div are removed. No JS, OCMOD XML or PHP file refers to them. `reset_translation_cache` stays as an id.
- All 7 JS ids from the brief are present in both templates.
- Each file uses the correct framework markup:
  - `.tpl`: BS3 `input-group-btn`, `input-group-addon`, `div.checkbox > label > input`, `radio-inline`, `data-toggle`, `data-dismiss`.
  - `.oc4.twig`: BS5 `form-select`, direct button in `input-group`, `input-group-text`, `form-check` + `form-check-label`, `form-check-inline`, `data-bs-toggle`, `btn-close`.
- The debug radio now works without the BS3 `data-toggle="buttons"` plugin. The value posts directly, so it also works on OC 1.5, which has no Bootstrap.
- The reset control is now a `<button type="button">` and not a `<div class="btn">`. It cannot submit the form, and the keyboard can reach it.
- The "View" log button now has the label outside the `<i>` icon element, in both templates. This fixes an old markup bug.
- The `.tpl` and the `.oc4.twig` show the same cards, in the same sequence, with the same fields and texts.
- The build rewrites the setting keys correctly in the new markup:
  - oc1.5: the build keeps all keys without a prefix (`$client_translate_expert_*`, `name="client_translate_expert_*"`).
  - oc3.0 `.tpl`: all 10 keys get the `module_` prefix, in the PHP variables, the `name` attributes and the `id` attributes.
  - oc4.x `.twig`: all keys get the `module_` prefix in `{{ }}`, `{% if %}`, `name` and `id`.
- The 8 new language keys are in all 3 language files. The controller passes them to `$data`. The Twig test passes, and the new Twig code uses only `e('html_attr')`, `raw`, `is defined`, `not`, `if`/`else` and `for`.
- The `te-js-collapse` handler binds through `$(document).on('click', '.te-app .te-js-collapse')`. The Try card is in a `.te-app`, so the toggle works without new JS. jQuery 1.7.1 on OC 1.5 supports `.on()`.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. `client_translate_expert.tpl:118` and `client_translate_expert.tpl:124`: the change removes the `(int)` cast on `client_translate_expert_cron_bulk_amount` and the `htmlspecialchars()` call on `client_translate_expert_cron_secret`. The plan does not record this change. The change is safe, because:
   - `Request::clean()` escapes every POST value on OC 1.5–3.0 (core probe above).
   - The stored setting comes from that escaped POST.
   - The controller clamps the stored bulk amount to an int (`controller:281`).

   The change also removes a double escape: before, a stored `&amp;` showed as `&amp;` in the field. The `.oc4.twig` still applies `|e('html_attr')` to the same two values (`client_translate_expert.oc4.twig:118`, `:124`). Thus OC 4 still double-escapes, and the two files now differ. The cron secret rule (`[A-Za-z0-9_-]`) makes this difference invisible for a valid secret. Record the change in the plan "Done" section. Optionally, remove `|e('html_attr')` in the Twig file for parity. If you remove it, first verify the OC 4 Twig `autoescape` setting.

2. `client_translate_expert.tpl:348–349` and `client_translate_expert.oc4.twig:346–347`: the debug status radios do not use the `te-radio-group` wrapper class. The "Website content" tab uses this class for its radio rows (`client_translate_expert.tpl:170`, CSS `client_translate_expert.css:535`). Add `te-radio-group` to the `te-field__control` element for the same spacing on all tabs.

3. `client_translate_expert.css:165–167`: the rule `.te-app .te-field__label:empty { display: block; }` has no effect. The label is a grid item of `.te-field`, and a grid item is always block-level. Remove the rule.

4. `controller:123`: no template uses `entry_try_translate` now, because the card title comes from `te_settings_card_try`. The controller still loads the key. Remove it from the controller, or keep it for a later change. The language files can keep the string.

5. `ru-ru` and `uk-ua`, `te_about_card`: the card title is the same text as the tab name (`О модуле`, `Про модуль`). Thus the About tab shows the same words twice. In `en-gb`, the texts differ ("About" and "About the module"). This is a cosmetic point.

6. `client_translate_expert.tpl:72–80` (and the same block in the `.oc4.twig` at `:72–80`): the generator script left irregular indentation. The `<option>` lines have a deep indent, and the closing `</select></div>` and `</div></div>` are not aligned with their open tags. The HTML is valid. Reindent if you change these lines again.

### Assessment

**Ready to merge?** Yes.

The change keeps all form names and JS ids, and it uses the correct BS3 and BS5 markup in each file. The two templates show the same content. The oc1.5, oc3.0 and oc4.x packages rewrite the setting keys correctly. The minor items are about consistency and cleanup. No item has an effect on behavior or security.
