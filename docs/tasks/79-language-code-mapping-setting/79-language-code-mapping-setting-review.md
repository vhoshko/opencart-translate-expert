# Review: 79-language-code-mapping-setting

Scope: `git diff HEAD` plus the untracked files on branch `feat/79-language-code-mapping-setting` (base e780dd2). Requirements: issue #79 and `79-language-code-mapping-setting_plan.md`.

Probes (all under `docs/tasks/79-language-code-mapping-setting/tmp/`):

- `probe_roundtrip.js` and `probe_roundtrip.php`: the real JS helpers in jsdom (jQuery 3.7.1) make the hidden-input JSON. The real `TranslateExpertLanguageCodeMap::normalize()`, `parse()` and `lookup()` then read it, one time raw and one time after the OpenCart `Request::clean()` step. Run: `node probe_roundtrip.js | php probe_roundtrip.php`.
- Mutation probe on `tests/test_translate_expert_library_paths.php`: I broke the DIR_EXTENSION path and then the DIR_SYSTEM path of the new include in a copy of `sources/`. Each time the test failed with one `[FAIL] path resolves to a file: ...` line. I removed the copies after the probe.
- OC 1.5 rewrite simulation: the 4 `build.sh` rewrites (`$data[`, `->redirect`, `extension/module/`, `ControllerExtensionModule`) on a copy of the controller. `php -l` passes. All new `$data[` lines become `$this->data[`. I removed the copy.
- OpenCart core source probe (`gh api`, tags 1.5.6.4, 2.0.3.1, 2.3.0.2, 3.0.3.8, 4.0.2.3, 4.1.0.0): `system/library/request.php` and `admin/controller/startup/setting.php`.
- All PHP tests and the JS helper tests pass (713 JS assertions). I did not run a build.

### Strengths

- The pure helper `TranslateExpertLanguageCodeMap` holds the default JSON, the parse rule, the lookup and the validation in one place. The core, the model and the controller use it. It has no OpenCart dependency, and the unit test covers each error code.
- `mapLanguageCode()` uses `config->has()` as the missing test. This is correct on OC 4.x, where `get()` returns `''` for a missing key. An empty string never triggers the default write (`ensureLanguageCodeMapDefault()` returns early when `has()` is true, and `has('')` is true because `isset('')` is true).
- `ensureLanguageCodeMapDefault()` uses `settingGroupColumn()` (`group` on OC 1.5, `code` on 2.0+), the same group as `editSetting()` for each version (`client_translate_expert` before 3.0, `module_client_translate_expert` from 3.0), and the same duplicate cleanup SQL as `ensureCounterRow()`. That SQL is already in production use. `MIN()` in the derived table prevents a merge, so MySQL error 1093 does not occur.
- The OC4 admin startup loads all `store_id = 0` rows (4.0.2.3 `admin/controller/startup/setting.php:16-20`, `getSettings()` with no group filter). Thus the map is visible in `config` on OC4. This closes the unverified plan item.
- The new library file is in all 3 `build.sh` places (package check list, `OC4_LIB_*`, `add_namespace`). The core and the helper share one namespace on OC4, so the unqualified `TranslateExpertLanguageCodeMap::` calls resolve. No `new X(` rewrite is necessary.
- All new PHP is PHP 5.6 compatible: no `??`, no scalar type hints, no `::class`. `JSON_UNESCAPED_SLASHES` is PHP 5.4+. `json_decode($x, true)` gives `""` for an empty key on PHP 5.6 too (the `_empty_` quirk applies to objects only).
- Escaping is correct. The `.tpl` uses `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` for `value`, `data-te-default` and `data-te-delete`. The `.oc4.twig` uses `|escape('html_attr')`. The JS writes values with `.val()` and errors through `teAlerts` (text only). The jsdom test with `<img onerror>` proves this.
- The row inputs have no `name`, so they do not post as extra setting keys.
- The path test catches a broken path in both branches of the new include (mutation probe).

### Issues

#### Critical (must fix)

**C1. Each save with a non-empty map fails on all OpenCart versions. The settings form cannot be saved.**

- Where: `sources/upload/admin/controller/extension/module/client_translate_expert.php:939` (`validateLanguageCodeMap()`) and `:130` (save branch). Both pass `$this->request->post['client_translate_expert_language_code_map']` to `normalize()` with no decode.
- Cause: OpenCart `Request::clean()` applies `htmlspecialchars($data, ENT_COMPAT, 'UTF-8')` to every POST value. Verified: 1.5.6.4 `system/library/request.php:33`, 2.0.3.1 `:26`, 2.3.0.2 `:26`, 3.0.3.8 `:46`, 4.0.2.3 `:61`, 4.1.0.0 `:119`. Thus `{"ua":"uk"}` arrives as `{&quot;ua&quot;:&quot;uk&quot;}`. `json_decode()` returns null, and `normalize()` gives the `format` error. The rest of this controller already calls `html_entity_decode()` on POST values (for example `:485`, `:546`, `:874`) for this reason.
- Probe result (`probe_roundtrip.php`): for each map that the JS accepts (default map, uppercase keys, 100 pairs, length 20), "raw POST" saves correctly, but "OC-cleaned POST" gives `errors=["format@0"]`. Only the empty map passes.
- Failure scenario:
  1. The admin opens the module page. `index()` writes the default map, so the hidden input holds `{"ua":"uk",...}`.
  2. The admin changes the API key and clicks Save.
  3. The server rejects the map. It shows "A code can have only the characters A-Z, ...". No setting is saved, also not the API key or the status.
  4. The re-display puts the cleaned value (`{&quot;ua...`) into the template. `htmlspecialchars()` escapes it again, so the JS cannot parse it. The card shows "The map is empty".
  5. If the admin clicks Save again, the JS posts `''`. The save succeeds and the map is now empty. The admin loses all pairs with no message.
- Why the tests did not catch it: `tests/test_client_translate_expert_language_code_map_save.php:238` and the other cases post raw JSON. The real `Request` never gives raw JSON.
- Fix (about 5 lines and 1 test case, 15 minutes): decode the value one time, directly after `ensureLanguageCodeMapDefault()` at `:104` and before the POST condition. Then the validation, the save and the re-display all see the real JSON:

  ```php
  if (isset($this->request->post['client_translate_expert_language_code_map']) && is_string($this->request->post['client_translate_expert_language_code_map'])) {
      $this->request->post['client_translate_expert_language_code_map'] = html_entity_decode($this->request->post['client_translate_expert_language_code_map'], ENT_QUOTES, 'UTF-8');
  }
  ```

  In the save test, post `htmlspecialchars($json, ENT_COMPAT, 'UTF-8')` in `testGoodMap`, `testBadMap` and `testEmptyMap`, the same as `Request::clean()`. Also check that the bad-map re-display gives the decoded JSON to the template.

#### Important (should fix)

None other than C1.

#### Minor (nice to have)

**M1. A key of only `0` (or `0`, `1`, ... in sequence) passes the JS check but the server rejects it with a confusing message.**

- Where: `sources/upload/system/library/translate_expert/translate_expert_language_code_map.php:63` (`isList()` check in `normalize()`), and `:21` in `parse()`.
- Cause: `json_decode('{"0":"a"}', true)` gives `array(0 => 'a')`. `isList()` is true, so `normalize()` gives `format`. The controller shows the `chars` text with no pair number. Probe: `zero key` and `zero and one keys` pass the JS and fail on the server even after the C1 fix.
- Failure scenario: an admin types `0` as a store code. The save fails with "A code can have only the characters A-Z, a-z, 0-9, ...", and `0` is in that list. No real language code is `0`, so the impact is low.
- Fix (10 minutes): in the JS, reject a key with digits only (one more rule and one test). Or, in `normalize()`, decode without `true` and check `is_object()` instead of `isList()`.

**M2. The server error text does not say that no setting was saved.**

- Where: `client_translate_expert.php:941-948`. The language string `error_language_code_map` ("... The module did not save the settings.") exists in all 3 files (en-gb `:239`, ru-ru `:239`, uk-ua `:240`), but no code uses it.
- Failure scenario: after a rejected save (for example a stale cached form, or JS off), the top warning shows only "2: A code can have only the characters ...". The admin does not know that the API key change was also lost.
- Fix (2 minutes): put `$this->language->get('error_language_code_map')` in front of the pair messages in `$this->error['warning']`. Or remove the unused string from the 3 files.

**M3. The server error line goes into the label column of the field grid.**

- Where: `client_translate_expert.tpl:163` and `client_translate_expert.oc4.twig:163`. `.te-field` is a 2-column grid (`client_translate_expert.css:134-139`). `.te-field__help` has `grid-column: 2`, but the `.text-danger` div has no column rule. Thus it starts a new grid row in column 1, under the label.
- Fix (2 minutes): add `te-field__help` to the class or add a `grid-column: 2` rule for it (column 1 below 768 px, as for `.te-field__help`).

**M4. The invalid-input border color is hard-coded.**

- Where: `client_translate_expert.css:725-727` uses `#d9534f`. The rest of the file uses `var(--te-error)` (`#c53030`, `:47`).
- Also, the BS5 `.form-control:focus` rule sets `border-color` with the same specificity. The red border can thus change to blue when the input has focus on OC4. I did not check this in a real browser.
- Fix: use `var(--te-error)`. Optionally also add `is-invalid` (BS5) and `has-error` on the row (BS3).

**M5. Translation quality of ru-ru and uk-ua (all keys exist in all 3 files).**

- `ru-ru:241` `'Сбросить по умолчанию'` is not idiomatic. Use `'Сбросить к значениям по умолчанию'`. The uk-ua text `'Скинути до типових'` is acceptable, `'Скинути до типових значень'` is clearer.
- `ru-ru:249` `'встречается в списке два раза'`: use `'повторяется в списке'` (a duplicate can occur more than 2 times). The same applies to uk-ua `:250` `'двічі'`: use `'повторюється в списку'`.
- `ru-ru:239` `'недопустима'`: `'некорректна'` is the more usual UI word. uk-ua `'недопустима'` → `'некоректна'`.
- en-gb `te_lcm_err_duplicate` `'... in the list two times.'` has the same issue: `'A store code occurs more than one time.'`.

**M6. Weak assertion in the default write test.**

- Where: `tests/test_translate_expert_language_code_map_default.php:166`. The DELETE check looks only for the `DELETE` prefix and the key. A change that removes the `MIN(setting_id)` keep condition (and thus deletes all rows of the key, including the new one) still passes.
- Fix (5 minutes): compare the full DELETE text, as the test does for the INSERT. A real DB probe is not necessary, because the SQL is a copy of `ensureCounterRow()`.

**M7. The `<label for="te-lcm-add">` points to the Add button.**

- Where: `client_translate_expert.tpl:151`, `client_translate_expert.oc4.twig:151`. A click on the label activates "Add pair" and adds an empty row. This is surprising for a field label.
- Fix: use a `<div class="te-field__label">` or point `for` to the first key input.

Items that I checked and found clean:

- JS and PHP rule parity: the same character class, the same `MAX_LENGTH` 20 and `MAX_PAIRS` 100, and both lowercase and trim the key and only trim the value. The input `maxlength` 64 is larger than 20 on purpose: the user sees the length error and the browser does not cut the text silently. JS `$.trim` also removes U+00A0, but the JS sends the trimmed value, so the server gets the same string (probe `nbsp around key`).
- Non-ASCII codes: the JS and the server both reject them with `chars`. `strtolower` and `toLowerCase` thus only see ASCII.
- POST without the map key: the save keeps the stored value (`:135`). A missing key on a GET writes the default first, so `''` is not stored by mistake.
- Readability of the card colors: the card uses the existing `.te-card` and `.te-field` rules. The new CSS adds only layout, `font-weight` and the error border. I could not render the OC 1.5 or OC4 admin pages, so I did not check them visually.

### Assessment

**Ready to merge?** No. C1 makes the whole settings form unsaveable on all OpenCart versions as soon as the map has a pair, and a second Save silently erases the map. The fix is small (one `html_entity_decode()` before the POST condition and a test that posts the escaped value). After that fix, the change is in good shape, and the Minor items are optional.
