# Review: 57-json-char-count-remove-data

Range: `origin/main...HEAD` (one commit, `95ed0f0`). Plan: `57-json-char-count-remove-data_plan.md`.

Checks that I ran:

- `php tests/test_client_translate_expert_translate_loop.php`: 241 pass.
- `php tests/test_client_translate_expert_i18n.php`: 34 pass.
- `php tests/test_oc4_twig_filters.php`: 29 pass.
- `node tests/js/test_client_translate_expert_js_helpers.js`: 180 pass (jQuery 1.7.1 and 3.7.1, with and without BS5).
- Key parity of the 3 language files: the key sets of `en-gb`, `ru-ru` and `uk-ua` are equal.
- Core probe: `system/library/template/twig.php` has `'autoescape' => false` on OC 3.0.3.8 (line 24) and OC 4.0.2.3 (line 110). The new Twig output is language strings only, so this is the same as the rest of the template.
- `build.sh`: `client_translate_expert_status` and `client_translate_expert_key` are in the `module_` prefix list. The task adds no AJAX method, library class or setting key.
- No other caller reads the removed fields. A grep of `sources/`, `tools/` and `tests/` for `removeDataFromDb`, `removeDataFromTable`, `translatedCharCountInfo`, `charCountDisplay` and `headerHTML` finds only CTRL, model, CORE, LIB, JS and the tests.

## Strengths

- **The language check runs before the first `UPDATE`.** CORE `removeDataFromDb` resolves `$targetLanguage` from the store languages first. An unknown or non-positive id gives `success: 0` and runs no query. The tests cover `0`, `-1` and `99`.
- **The SQL injection in `removeDataFromTable()` is closed.** CTRL casts `langIdTo` to int, and LIB casts again before the SQL (`$langIdToInt`). The test checks the SQL text `where t.language_id = 2`.
- **The front end inserts only text.** All renderers use `teEl` and `teT`. The JS tests put HTML in the labels, the language name and the table name, and check that no `<b>` or `<i>` element exists.
- **The formats follow section 3.0.** Integer flags in `translatedCharCountInfo`, `resetCharCount`, `badFilterResponse()` and CORE. `timeInSec` is a float. The `target` object has the shape of rule 7 with a decoded `name`.
- **`clearedRows` is correct by construction.** The `table` step gets the sum of its `column` steps. The test uses `'7'` (a string) from `FakeDb::countAffected()` and checks the int cast.
- **Both templates changed in their own syntax.** The `.tpl` and the `.oc4.twig` have the same `te-app` root, the `te-summary-alerts` area and the `te-summary-count` meter slot. The badges come from the saved config, not from POST data.
- **The JS stays ES5 and works on jQuery 1.7.1.** The delegated handler uses `$(document).on('click', selector, fn)`, bound one time. The test file runs each suite with both jQuery versions.
- **The dead code is gone.** The 6 language keys, the 5 `$data` lines, the old `#translation_char_count_info*` CSS and the `color` and `*Display` fields are removed. No file has PHP 7+ syntax.

## Issues

### Critical (must fix)

None.

### Important (should fix)

1. **`sources/upload/admin/view/javascript/client_translate_expert.js:1107-1157` has no test.** The tests cover the renderers (`teRenderHeaderCount`, `teRenderSummaryCount`) and `makeRemovingDataFromDbRequest()`. They do not cover `showTranslatedCharCount()`, which holds the behavior that changed:
   - the module page branch renders into `#te-summary-count` and does not add the header item,
   - the module page branch ignores `js_const_client_translate_expert_show_char_count_is_enabled`,
   - on another page, the first call makes the `<li id="te-translated-char-count-info">` and a second call reuses it,
   - `showTeCharCountError()` writes to `#te-summary-alerts` on the module page and only to the console on other pages.

   The test file already has the pattern: replace `w.$.get` with a `$.Deferred()` and call the function (see `testRemoveLog`). Add one test group with a `#te-summary-count` element and one with a `#header .navbar-nav` element. Without this, a regression in the gating logic passes the test suite.

### Minor (nice to have)

2. **`sources/upload/system/library/client_translate_expert_core.php:333-341`: the inner language loop is now dead logic.** The code resolves `$targetLanguage` before the table loop. The `foreach ($languages as $code => $language)` with `if ($languageIdTo != $language['language_id']) continue;` then matches exactly one language. Call `removeDataFromTable()` one time for each column and remove the loop. This makes the step order easier to read and removes a loose `!=` compare.

3. **`sources/upload/admin/view/javascript/client_translate_expert.js:1684`: the `steps` guard accepts a string.** `typeof data.steps.length === 'number'` is true for a string. Each character then goes through the loop as a step with no `type`, so nothing shows. The result is correct, but the intent is "array". Use `$.isArray(data.steps)` (jQuery 1.7.1 has it, and `teAlerts` already uses `Array.isArray`). Also add one test with `steps` absent or not an array. The current tests cover only `steps: []` and an unknown `type`.

4. **`sources/upload/admin/view/javascript/client_translate_expert.js:1705`: a missing `timeInSec` leaves the `{sec}` placeholder visible.** `teT` keeps a placeholder when the parameter is `undefined`. The server always sends the value, so this is a safety net only. `{ sec: Number(data.timeInSec) || 0 }` gives `0` instead.

5. **`docs/pocs/2-admin-design/endpoints-json.md`: the inventory is stale.** Section 1 still lists `removeDataFromDb` (row 5) and `translatedCharCountInfo` (row 8) as "JSON with HTML" and says "5 endpoints return HTML". The "Now:" lines of sections 3.3 and 3.5 describe the old responses. The issue asked only for the `clearedRows` rename, so this is not a plan deviation. A 4-line update keeps the PoC file true for the next tasks.

6. **Pre-existing, not from this diff:** `translatedCharCountInfo`, `resetCharCount` and `removeDataFromDb` have no `hasPermission` check. Only 3 actions in CTRL (lines 766, 809, 825) check it. The admin token still protects the routes. Record this for the step 6 task of the PoC README (GET to POST), because that task touches the same actions.

## Assessment

**Ready to merge? With fixes.** The PHP and JS changes are correct, the formats follow the PoC, and all unit tests pass. Fix item 1 (a test for `showTranslatedCharCount()`) before the merge, because that function holds the main front-end behavior change and has no coverage. Items 2 to 5 are small cleanups that can go in the same pass.
