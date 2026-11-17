# 79-language-code-mapping-setting

## Settings
- Planning model: Opus (written by a general-purpose subagent).
- Review model: Opus (the same model as planning). The user confirmed that the review is NOT independent.
- Build: yes. Bump the patch version before each run. Use `./build.release.sh`.
- Tests: all unit tests in `CLAUDE.md` plus the JS helper tests.
- Commit, push, PR: not requested. Do not commit, push or open a PR.
- Standing auto-approve for review findings: OFF. Ask the user about each finding.
- Decisions of the user (settled before planning):
  - The admin form posts the map as one hidden input with the JSON string. The input is inside `#form-bestseller`, so the map stays in the module settings group.
  - The server normalizes and validates the map before `editSetting()`.
  - A pure helper with no OpenCart dependency holds the default JSON, the parse function and the pair validation. The core, the controller and the tests share this one definition.
  - The module writes the default only when the setting is missing or null. An empty string is a valid user choice (empty map).
  - The module writes the default at install and at the start of the admin `index()`.
  - Add, delete and reset are client-side. The normal Save button saves the map. No new AJAX method.
  - The reset control uses native `confirm(teT('...'))`, as `resetTranslationCache` does.

## Context
- Issue: https://github.com/vhoshko/opencart-translate-expert/issues/79 (enhancement).
- Why: `TranslateExpertCore::mapGoogleLanguageCode()` has 4 fixed pairs (`ua`→`uk`, `rs`→`sr`, `ge`→`ka`, `cz`→`cs`). A store owner cannot add, change or remove a pair without a code change.
- Affected OC versions: all (1.5, 2.0 to 2.3, 3.0, 4.x). The admin translation, the cron link and `tools/` use the same core method.
- Base: branch `feat/79-language-code-mapping-setting` at e780dd2. `_version.txt` is `04.01.0212`. Line numbers below are from e780dd2.

Key files:
- `sources/upload/system/library/translate_expert/translate_expert_core.php`
  - `:17` `_counterGroup`, `:21-43` constructor (hard-coded `module_` prefix for OC >= 3.0 at `:34-39`).
  - `:462-468` `mapGoogleLanguageCode()` (static, fixed pairs).
  - `:518-519` the only callers, in `translateInternal()`.
  - `:839` `settingGroupColumn()`, `:849` `getCounterValue()`, `:855` `ensureCounterRow()` (SQL template with the duplicate-row cleanup), `:875` `prepareCounterSettings()`, `:934` `install()`.
- `sources/upload/system/library/translate_expert/translate_expert.php:4-8` includes the core and the cron helper. `Translate()` cuts each code at `-` before the mapping (`:23-26`).
- `sources/upload/system/library/translate_expert/translate_expert_cron_helper.php:144` public static `settingKey()`.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php`
  - `:44` `settingKey()`, `:92` `index()`, `:101` `load->model('setting/setting')`.
  - `:103-127` POST branch: `validate()`, counter keys unset, cron secret trim, bulk amount clamp, `prepareCounterSettings()`, then `editSetting(settingKey('client_translate_expert'), prefixSettingKeys(post))`.
  - `:244-251` the two key lists for `document.te_i18n` (JS strings) and for template text.
  - `:258-263` `error_warning`, `:312-362` config reads for the form values.
  - `:779-780` `resetCache` calls `uninstall()` and `install()` of the model. `:907` `validate()`. `:929` `install()`.
- `sources/upload/admin/model/extension/module/client_translate_expert.php:109` `install()`, `:134` `prepareCounterSettings()` proxy, `:184` `clampCronBulkAmount()`, `:189` `settingKey()`.
- `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:64` `#tab-general`, `:133-144` Routes card, `:145` Try card.
- `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig:64` `#tab-general`, `:134` Routes card, `:145` Try card.
- `sources/upload/admin/view/javascript/client_translate_expert.js:660` `confirm(teT('te_cache_reset_confirm'))`, `:680` `initModule()`, `:846` `teT()`, `:871` `teIsBs5()`, `:892` `teIcon()`, `:907` `teEl()`.
- `build.sh:43` package file check list, `:257-263` `OC4_LIB_*`, `:281-287` `add_namespace` for library files.
- Tests: `tests/test_translate_expert_core_translate.php` (StubCore at `:60` does not call `parent::__construct` and has no `_model`), `tests/test_client_translate_expert_translate_loop.php` (`RealSqlLibrary` `:100`, `FakeModel` `:138`, `StubTranslateLibrary` `:196`), `tests/test_translate_expert_library_paths.php`, `tests/test_client_translate_expert_localization.php` (language key parity), `tests/js/test_client_translate_expert_js_helpers.js`.

Investigation: an Explore agent mapped the code. The planning agent checked the line numbers with grep and file reads. The planning agent also read `system/library/config.php` and `system/engine/config.php` of OpenCart with the source probe (see Assumptions).

## Approach
Add a new pure library file `translate_expert_language_code_map.php` with the class `TranslateExpertLanguageCodeMap`. It holds the default JSON, `parse($value)` (JSON to array, any bad input gives an empty array) and `normalize($json)` (validate and normalize the pairs, return the JSON or a list of errors). Replace `mapGoogleLanguageCode()` with the instance method `mapLanguageCode($code)` that reads `$this->_model->config`. Add `ensureLanguageCodeMapDefault()` in the core. The model proxies it, and the library `install()` and the controller `index()` call it. The admin page gets a new card "Language code map" with a hidden input and client-side rows.

Design corrections:
- OC 4.0 and 4.1 `config->get()` returns `''` for a missing key, not `null` (see Assumptions). Thus `get() === null` cannot find a missing key on OC4. Use `config->has($key)` for "missing or null". `has()` exists in all versions and uses `isset`, so it is false for a missing key and for a null value, and true for `''`.
- ~~Proposal: if `has($key)` is false, `mapLanguageCode()` uses the default map in memory.~~ **Rejected by the user (17.11.2026):** the cron feature is not released yet. If `has($key)` is false, `mapLanguageCode()` uses an empty map, as issue item 5 says. Old proposal text follows for the record: Reason: after an update, the cron link and `tools/` can run before an admin opens the module page. With "missing = empty map", the cron sends `ua` to Google until then, which is a regression. An empty string still gives an empty map, as the issue asks. Ask the user at the plan checkpoint. If the user rejects it, follow issue item 5 literally.
- A new library file needs 3 lines in `build.sh` (`:43`, `OC4_LIB_*`, `add_namespace`). The core includes the new file. The controller reaches the helper only through the model, so the controller needs no OC4 FQCN.
- Invalid pairs on save: reject the save and show the errors. Do not sanitize. Reason: a silent drop of a pair loses user data with no message. The JS check stops a bad save first, so the server check is a guard only.

## Assumptions
- ✅ verified: OC 4.0.2.3 `system/engine/config.php:48-49` and 4.1.0.0 `:50-51` return `''` for a missing key. OC 1.5.6.4, 2.0.3.1 and 2.3.0.2 `system/library/config.php:5-6`, and 3.0.3.8 `:23-24` return `null`. `has()` uses `isset` in all these versions (1.5.6.4 `:13-14`, 3.0.3.8 `:44-45`, 4.0.2.3 `:69-70`, 4.1.0.0 `:71-72`).
- (a) ✅ verified from code: `editSetting()` deletes all rows of the group and inserts only the posted keys. OC 3.0.3.8 `admin/model/setting/setting.php:19-31`. Thus a key that is not on the form is lost on save. The hidden input prevents this. ❓ Not run on a live store. Failure mode: the map disappears on each save, and the next page open writes the default again.
- (b) ❓ unverified: the behavior of `config->get()` for a DB row with value `''` on each version. From the code above, `isset('')` is true, so `get()` returns `''` and `has()` returns true. Rule: treat only `has() === false` as "not set". Treat `''` as an empty map, and never overwrite it. Failure mode: a store with an empty map gets the default again on each page open. A unit test with a config stub that holds `''` covers the module side.
- (c) ✅ by design: the default check in `index()` runs on each open of the main page. It costs one `config->has()` call with no SQL when the key exists. An INSERT runs only when the key is missing.
- (d) ✅ verified: OC 1.5 to 3.0 render the `.tpl`. OC 4.x renders `client_translate_expert.oc4.twig` (`.claude/project-workflows.md`, Source Layout). OC 3.0 renders the `.tpl` as PHP.
- ❓ unverified: OC4 admin startup loads all `oc_setting` rows of store 0, so `config->get()` sees the module key in any group. The Explore agent reported this. Failure mode: the map has no effect on OC4. Check in the store run or with the source probe (`admin/controller/startup/setting.php`, tag 4.0.2.3).
- ✅ verified: the OC4 form `#form-bestseller` posts normally (no `data-oc-toggle="ajax"`), so the POST branch of `index()` handles all versions.
- ❓ unverified: the Save button submits the form with a `submit` event on all versions (`type="submit" form="form-bestseller"`). A native `form.submit()` call skips the JS handler. Failure mode: the hidden input keeps the old JSON and the row edits are lost. The server check still blocks a bad map.
- ✅ by design: the row inputs of the card have no `name` attribute. Thus they do not post as extra setting keys (OC 1.5 `editSetting()` inserts every posted key).
- ❓ unverified: Twig autoescape is off in OC 4.x (task 6 plan, `system/library/template/twig.php`). Use `|escape('html_attr')` for the hidden input value in the `.oc4.twig`. This is correct with autoescape on or off.

## Steps

Pure helper and tests:
- [x] Add `sources/upload/system/library/translate_expert/translate_expert_language_code_map.php` with the class `TranslateExpertLanguageCodeMap`. Use PHP 5.6 syntax only (no `??`, no scalar type hints, no `::class`).
- [x] Add `DEFAULT_JSON = '{"ua":"uk","rs":"sr","ge":"ka","cz":"cs"}'`, `MAX_LENGTH = 20` and `MAX_PAIRS = 100`.
- [x] Add `parse($value)`. If the value is not a string, is empty, is not valid JSON or is not a JSON object, return an empty array. Skip a pair with a non-string key or value. Lowercase and trim each key.
- [x] Add `lookup(array $map, $code)`. Compare the code in lowercase. If no pair matches, return the code unchanged.
- [x] Add `normalize($json)`. Return `array('json' => <normalized JSON or ''>, 'errors' => array(...))`. Reject these pairs:
  - empty key
  - empty value
  - duplicate key (after trim and lowercase)
  - a key or value with a character other than `A-Z`, `a-z`, `0-9`, `-` and `_`
  - a key or value longer than `MAX_LENGTH`
  - more pairs than `MAX_PAIRS`
- [x] Make each error an array with a code and the pair index, so the controller can show a language string.
- [x] Write an empty map as `''`. Write a non-empty map with `json_encode` and no escaped slashes.
- [x] Add the file to `build.sh:43`, to `OC4_LIB_*` and to `add_namespace`.
- [x] Add `tests/test_translate_expert_language_code_map.php`. Cover `parse()` (empty, null, `'null'`, invalid JSON, JSON array, JSON scalar, valid object, upper-case key), `lookup()`, and each `normalize()` error and the success case. Any PHP warning fails a test.
- [x] Check that `tests/test_translate_expert_library_paths.php` finds the new path.

Core change and tests:
- [x] Include the new file at the top of `translate_expert_core.php`, in the OC4 path and in the else path.
- [x] Add `_languageCodeMapKey` and `_settingGroup` in the constructor. Use `client_translate_expert_language_code_map` and `client_translate_expert`. Add the `module_` prefix for OC >= 3.0 in the same `if` block as the other keys.
- [x] Replace `mapGoogleLanguageCode()` with `protected function mapLanguageCode($code)`. Read the map with `$this->_model->config`. If the key is missing, use an empty map (user decision, 17.11.2026).
- [x] Change `translateInternal()` `:518-519` to `$this->mapLanguageCode(...)`. Grep `sources/`, `tests/` and `tools/` for `mapGoogleLanguageCode` and remove each hit.
- [x] Update StubCore in `tests/test_translate_expert_core_translate.php`. Give it a fake `_model` with a `config` stub (`get`, `has`, `set`).
- [x] Add core tests for the key prefix by version, for each value (missing, `''`, invalid JSON, custom map, default map) and for the source and target codes that the stub Google client receives.
- [x] Check that the stubs in `tests/test_client_translate_expert_translate_loop.php` (`FakeModel`, `RealSqlLibrary`, `StubTranslateLibrary`) return null for unknown keys and have `has()`. If a stub has no `has()`, add it.
- [x] Add a cron path test: `TranslateExpert` with a config stub that holds a custom map. Show that the stub Google client receives the mapped code.
- [x] Mutation check: make `mapLanguageCode()` return the code unchanged. The new tests must fail on the expected assertion. Restore from a byte copy and verify with `git diff --stat`.

Model, controller, install and default write, with tests:
- [x] Add `public function ensureLanguageCodeMapDefault()` in the core. If `config->has(key)` is false, insert a row (`store_id` 0, group column from `settingGroupColumn()`, group `_settingGroup`, value `DEFAULT_JSON`, `serialized` 0). Then remove duplicate rows as `ensureCounterRow()` does, and call `config->set()`.
- [x] Call `ensureLanguageCodeMapDefault()` from the library `install()` at `:934`. `resetCache` calls `install()` again, which is safe because of the `has()` check.
- [x] Add model proxies `ensureLanguageCodeMapDefault()`, `normalizeLanguageCodeMap($json)` and `getLanguageCodeMapDefault()`, next to `prepareCounterSettings()` at `:134`.
- [x] In the controller `index()`, call the default write right after `load->model('setting/setting')` at `:101`. Load the module model first. Do it before the POST branch and before the config reads at `:312-362`.
- [x] In the POST branch, normalize the posted `client_translate_expert_language_code_map`. If the result has errors, set `$this->error['language_code_map']` and `$this->error['warning']`, and do not call `editSetting()`. If it has no errors, replace the posted value with the normalized JSON.
- [x] If the POST has no map key, keep the current stored value in the post before `editSetting()`. This prevents a loss of the map from an old cached form.
- [x] Set the template data: `client_translate_expert_language_code_map` (POST value, else `config->get()`), `te_language_code_map_default` and `error_language_code_map`. Do not use a local `$data` variable for anything else (OC 1.5 build rewrite).
- [x] Add a DB stub test for `ensureLanguageCodeMapDefault()`: missing key inserts one row with the correct group and key per version, `''` and a custom value insert nothing, `config->set()` runs after the insert. Check the SQL text against `ensureCounterRow()`.
- [x] Add a controller test (stubs, as in `tests/test_client_translate_expert_ajax_guard.php`) for the save path: a bad map gives no `editSetting()` call and shows the error, a good map posts the normalized JSON, a missing map keeps the stored value.

Templates (`.tpl` and `.oc4.twig`):
- [x] Add a card between the Routes card and the Try card in both files. Use `.te-card > .te-card__head + .te-card__body > .te-form > .te-field`. Title `te_settings_card_language_codes`.
- [x] Add a hidden input `name="client_translate_expert_language_code_map"` with `id="input-client_translate_expert_language_code_map"` and `data-te-default="..."`. In the `.tpl`, escape both values with `htmlspecialchars`. In the `.oc4.twig`, use `|escape('html_attr')`.
- [x] Add an empty rows container, the "Add pair" and "Reset to default" buttons, a help text and the server error text.
- [x] Do not write `{{ }}` in the `.tpl`. Run `php tests/test_oc4_twig_filters.php`.

JS and JS tests:
- [x] Add a helper block in `client_translate_expert.js` (ES5 only): render the rows from the hidden input, add a row, delete a row, reset with `confirm(teT('te_lcm_reset_confirm'))`, collect and validate the rows.
- [x] Use the same rules and `MAX_LENGTH` as the PHP helper. Show the errors with `teAlerts` in the `.te-alerts` container and mark the bad inputs.
- [x] On the `submit` event of `#form-bestseller`, write the JSON into the hidden input. If a pair is bad, stop the submit.
- [x] Use `teEl`, `teIcon` and `teIsBs5()` for the elements and classes. Call the init from `initModule()`.
- [x] Add jsdom tests in `tests/js/test_client_translate_expert_js_helpers.js` for jQuery 3.7.1 and 1.7.1: render from JSON, render from `''` and bad JSON, add, delete, reset with confirm true and false, each validation error, the submit stop, the JSON that the submit writes.

Language files:
- [x] Add the same new keys to en-gb, ru-ru and uk-ua. Put `te_settings_card_language_codes` next to `te_settings_card_routes`. Do not change `heading_title`.
- [x] Add the JS keys (`te_lcm_*`) to the first key list in the controller `:244-251`. Add the card title to the second list.
- [x] Add `entry_language_code_map`, `help_language_code_map` and `error_language_code_map` (server message).
- [x] Run `php tests/test_client_translate_expert_localization.php` for the key parity.

`CLAUDE.md` Tests section and docs:
- [x] Add each new test file and its command to the Tests section of `CLAUDE.md`, with one short description.
- [x] Add `translate_expert_language_code_map.php` to the library list in the Architecture section of `CLAUDE.md` and to the Source Layout table of `.claude/project-workflows.md`.
- [x] Remove "language code mapping" from the core description if the text says the core holds the fixed mapping.

Version bump, build and tests:
- [x] Bump the patch version in `_version.txt` with `/bump-version`.
- [x] Run `./build.release.sh` through `ote-build-test` in full mode. Give the agent the new setting key, the new library class and the new file.
- [x] Check the OC 1.5, 2.2, 3.0 and 4.x packages for the new file. Check the namespace line in the OC4 file. Check that no `.zip` or `OpenCartTranslateExpertClient-*` folder stays in the root.
- [x] Run all tests in `CLAUDE.md` and the JS helper tests.

Independent review:
- [x] Dispatch `ote-code-reviewer` (Opus) → `79-language-code-mapping-setting-review.md`. Tell the reviewer to use `git diff HEAD`.

Fixes:
- [x] Ask the user about each finding. Fix or skip as the user decides.

Verify:
- [x] Bump the version, build and run the tests again (verify loop).

Re-review question:
- [x] If unreviewed fixes exist, ask the user about a re-review. If yes, repeat from the review step → `-review-v2.md`.

Improvements:
- [x] Write `79-language-code-mapping-setting-improvements.md`.

## Done
Done (17.11.2026):
- Issue #79 is done in the working tree. Nothing is committed, pushed or opened as a PR.
- The setting `client_translate_expert_language_code_map` replaces the fixed mapping. The default is written only if the key is missing. A missing key gives an empty map (user decision). The main tab has a "Language code map" card.
- Final version: `04.01.0214`. The release build and all tests pass (14 PHP scripts, 719 JS assertions). The 6 Google integration tests were skipped (no API key).
- Review pass 1 found 1 Critical (OpenCart `Request::clean()` escapes the posted JSON) and 7 Minor. The user chose to fix all. Review pass 2 (`-review-v2.md`) found no Critical and no Important issue.
- Deferred, Minor, optional (reviewer recommends no action): N1 row order of digit-only keys in `teLcmParse`. N2 `validate() && validateLanguageCodeMap()` hides the map errors if the permission check fails.
- Not done: a store run on a real OpenCart 1.5 to 4.1 store. The card colors and the focus state were not checked in a browser.
- Review model was the same as the planning model (Opus). The review was not independent (user choice).
