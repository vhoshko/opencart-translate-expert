# Review v2: 79-language-code-mapping-setting

Scope: only the fixes for C1 and M1 to M7 of pass 1 (`79-language-code-mapping-setting-review.md`). Source: `git diff HEAD` plus the untracked files on branch `feat/79-language-code-mapping-setting`. I did not examine the items that the brief marks as settled again.

Probes (under `docs/tasks/79-language-code-mapping-setting/tmp/`):

- `probe_mutations_v2.sh` (run from the repo root). It makes a copy of `sources/` and the 2 map tests, applies one mutation, runs the tests and removes the copy. Results:
  - M-a: remove `JSON_FORCE_OBJECT`. `test_translate_expert_language_code_map.php` gives 2 failures, and `test_client_translate_expert_language_code_map_save.php` gives 1 failure ("numeric keys are saved as an object", got `["a","b"]`).
  - M-b: remove the `html_entity_decode()` at controller `:109`. The save test gives 8 failures.
  - M-c: make `isObject()` always return true. The map test gives 3 failures (JSON array cases).
- `node -e` check: `Object.keys(JSON.parse('{"ua":"uk","0":"a"}'))` gives `['0','ua']`.
- Test runs: the map test (45 passed), the save test (20 passed), `test_oc4_twig_filters.php` (29 passed) and the JS helper tests (719 passed). I did not run a build.

### Strengths

- C1 fix (`client_translate_expert.php:106-110`): the decode runs one time, before the POST condition. Thus `validateLanguageCodeMap()`, the save branch and the failed-save re-display all see the same raw JSON. `html_entity_decode(..., ENT_QUOTES)` is the exact inverse of the `htmlspecialchars(..., ENT_COMPAT)` in `Request::clean()`, because `clean()` escapes `&` first. A user value `&amp;` arrives as `&amp;amp;` and decodes back to `&amp;`. The `[A-Za-z0-9_-]` rule then rejects it with `chars`. No double-decode path exists. The order after `ensureLanguageCodeMapDefault()` has no effect, because that call reads only `config`.
- `editSetting()` gets the output of `normalize()`, which is `json_encode()` of codes from `[A-Za-z0-9_-]`. Thus no entity and no character that needs an escape can go into the DB on any version.
- The missing-key path (`:139-142`) is unchanged and correct. The decode guard has `isset` and `is_string`, so an array POST value is not touched and `normalize()` gives `format`.
- Re-display: `:368-369` gives the decoded JSON to the template. The `.tpl` and the `.oc4.twig` escape it one time. The save test checks this value (`testBadMap`).
- The save test now posts `htmlspecialchars($json, ENT_COMPAT, 'UTF-8')`, the same as the real `Request`. Mutation M-b proves that the test fails without the fix.
- M1: `isObject()` together with `JSON_FORCE_OBJECT` gives a correct round trip for `{"0":"a"}` and `{"0":"a","1":"b"}` through `normalize()`, `parse()` and `lookup()`. Other edge cases are safe:
  - Leading whitespace: `ltrim()` handles it.
  - A leading BOM: `json_decode()` returns null, so the result is `format`.
  - `{}`: an empty map that saves as `''`.
  - A nested object: `chars`.
  - `[]` or `[1]`: `format`.
  - PHP 5.6: `JSON_FORCE_OBJECT` is PHP 5.3+. `substr('', 0, 1)` returns `false` on PHP 5.6, but the empty string returns earlier.
- M2: `error_language_code_map` is now in front of the pair messages (`:954`). The text is present and correct in en-gb `:239`, ru-ru `:239` and uk-ua `:240`. The test checks the full warning text.
- M3 and M4: `.te-app .te-field__error` (0,2,0) has `grid-column: 2`, and it changes to column 1 in the same `max-width: 767px` block as `.te-field__help`. The invalid rule `.te-app .te-lcm-row input.te-lcm-invalid:focus` (0,4,1) has a higher specificity than the BS5 `.form-control:focus` (0,2,0). It uses `var(--te-error)`.
- M7: the label is now `<div class="te-field__label" id="te-lcm-label">`, and `#te-lcm-rows` has `role="group" aria-labelledby="te-lcm-label"`. The change is the same in the `.tpl` and the `.oc4.twig` (`:151`, `:155`). The rows have their own `aria-label`. `test_oc4_twig_filters.php` passes.
- M5 and M6: the translations are better. The DELETE assertion now compares the full SQL text. I did not check this assertion with a mutation.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

**N1. The JS changes the pair order when a key has only digits.**

- Where: `client_translate_expert.js:724` (`for (var key in data)` in `teLcmParse()`).
- Cause: JS puts integer-like keys first in the property order. A stored `{"ua":"uk","0":"a"}` shows as the rows `0`, `ua`. The next save then stores `{"0":"a","ua":"uk"}`.
- Failure scenario: an admin adds the pair `0`→`a` at the end of the list. After the reload, the pair is at the top. The lookup result does not change. Only the row order changes, and the pair numbers in a later error message change with it. No real language code has only digits, so the impact is very low.
- Fix cost: about 10 lines. Read the pairs in source order (for example a small scan of the JSON text), or accept the behavior as it is. I recommend that you accept it.

**N2. A failed `validate()` hides the map errors.**

- Where: `client_translate_expert.php:112`. `validate() && validateLanguageCodeMap()` stops after the first `false`.
- Failure scenario: a user with no modify permission posts a bad map. The page shows only the permission warning. When the user gets the permission, the map error shows on the next save. This is the usual OpenCart pattern, and the save is still blocked, so no data is lost.
- Fix cost: 2 lines (call both and combine the results). Optional.

### Assessment

**Ready to merge?** Yes. The C1 fix decodes the posted map one time before validation, save and re-display. No double-decode path exists, because the allowed character set rejects any entity text. The M1 to M7 fixes are correct in the PHP, the JS, the CSS and both templates. My 3 mutations (no `JSON_FORCE_OBJECT`, no decode, `isObject()` always true) each make a test fail. The 2 remaining items are cosmetic.
