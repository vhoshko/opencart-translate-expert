# 77-library-folders-google-client

## Settings
- Planning model: Opus (written by a general-purpose subagent).
- Review model: Opus (the same model as planning). The user confirmed that the review is NOT independent.
- Build: yes. Bump the patch version before each run. Use `./build.release.sh`.
- Tests: all unit tests in `CLAUDE.md` plus the JS helper tests.
- Commit, push, PR: not requested.
- Standing auto-approve for review findings: ON (the user asked on 17.11.2026). Decide each finding without a question. Prefer to fix. Report each decision with the reason. Ask the user for a change of the agreed approach, an external system or a scope expansion.
- Decisions of the user:
  - One common folder `sources/upload/system/library/translate_expert/`.
  - Rename all 7 library files to the prefix `translate_expert_` and all 7 classes to the prefix `TranslateExpert`.
  - The empty-text rule stays in the Google client.
  - Add a factory method `createGoogleClient` in the core.
  - The Google class name is `TranslateExpertGoogleClient`.
- Plan approved by the user on 17.11.2026, with these answers:
  - Rename the 4 test files and add the core test (step 8 and 9).
  - Put the package check as a permanent check in `build.sh` (step 10).
  - Accept the risk of the generic class name `TranslateExpert` on OC 1.5 to 3.0.
  - Drop the array branch of `fixFormatParameters()`, add `isTranslatableText()`, change the `ua` integration test to `uk`.
- Step 22 is done: the user asked for the issue #77 text update, and it was applied on 17.11.2026.

## Context
- Issue: https://github.com/vhoshko/opencart-translate-expert/issues/77 (refactor, no behavior change).
- Why: the 7 library files are flat in `system/library/` with 3 different name patterns. `vh_google_translator.php` also holds module rules (language code mapping, format fix) that belong to the core.
- Affected OC versions: all (1.5, 2.0 to 2.3, 3.0, 4.x). Each package loads the library files by an explicit path.
- Base: `origin/main` 4f162b6, `_version.txt` 04.01.0210. Line numbers below are from 4f162b6.

Rename map (`git mv` into `sources/upload/system/library/translate_expert/`):

| Old file | Old class | New file | New class |
|---|---|---|---|
| `client_translate_expert.php` | `LibraryClientTranslateExpert` | `translate_expert.php` | `TranslateExpert` |
| `client_translate_expert_core.php` | `LibraryClientTranslateExpertCore` | `translate_expert_core.php` | `TranslateExpertCore` |
| `client_localization_translate_expert.php` | `LocalizationLibraryClientTranslateExpert` | `translate_expert_localization.php` | `TranslateExpertLocalization` |
| `client_translate_expert_cron_helper.php` | `ClientTranslateExpertCronHelper` | `translate_expert_cron_helper.php` | `TranslateExpertCronHelper` |
| `client_translate_expert_i18n.php` | `ClientTranslateExpertI18n` | `translate_expert_i18n.php` | `TranslateExpertI18n` |
| `client_translate_expert_route_filter.php` | `ClientTranslateExpertRouteFilter` | `translate_expert_route_filter.php` | `TranslateExpertRouteFilter` |
| `vh_google_translator.php` | `VHGoogleTranslator` | `translate_expert_google_client.php` | `TranslateExpertGoogleClient` |

Sites that name a library file or a library class (grep at 4f162b6):

- Library files:
  - `client_translate_expert.php:4,5,7,8` (includes of core and cron helper), `:11` (`extends LibraryClientTranslateExpertCore`), `:103-242` (12 calls of `ClientTranslateExpertCronHelper::`).
  - `client_localization_translate_expert.php:4,6` (include of core), `:9` (class and `extends`).
  - `client_translate_expert_core.php:3` (class), `:463,465` (include of the Google file), `:468,479,481` (log strings), `:480` (`new VHGoogleTranslator`).
  - `client_translate_expert_cron_helper.php:7`, `client_translate_expert_i18n.php:14`, `client_translate_expert_route_filter.php:10`, `vh_google_translator.php:3` (class lines).
- Model `sources/upload/admin/model/extension/module/client_translate_expert.php:4,5` (OC4) and `:7,8` (else) `require_once`, `:199` `new LibraryClientTranslateExpert(`, `:208` `new LocalizationLibraryClientTranslateExpert(`.
- Catalog cron `sources/upload/catalog/controller/extension/module/client_translate_expert_cron.php:4` (OC4), `:6` `require_once`, `:14` `new LibraryClientTranslateExpert(`.
- Admin controller `sources/upload/admin/controller/extension/module/client_translate_expert.php:1026` (i18n `require_once`), `:1031-1034` (FQCN `...\ClientTranslateExpertI18n`), `:1039` (comment), `:1042` (route filter `require_once`), `:1044` (FQCN `...\ClientTranslateExpertRouteFilter`). All are in `// OC4_EVENT_START` … `// OC4_EVENT_END` blocks.
- OCMOD XML `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:13` (comment), `:16-23` (route filter path and class), `:35-48` (i18n path and class). The OC 1.5 vQmod XML is a copy of this file (`build.sh:114-125`).
- `build.sh:188` (flat `mv` loop), `:226-232` (`OC4_LIB_*`), `:250-256` (`add_namespace`), `:287-289` (`new` rewrites in the OC4 model and OC4 catalog cron). `:167` makes `$EXT_DIR/system/library`. `:48-50` and `:191-194` handle `google-cloud-translate` (the folder `sources_external/` does not exist). `:292` and `:296,298` use `OC4_LIB_CORE` and `OC4_LIB_MAIN`. `build.release.sh` has no library name.
- Tests: `tests/test_vh_google_translator.php` (306 lines), `tests/test_client_translate_expert_route_filter.php:11,13,19,131,151,168`, `tests/test_client_translate_expert_cron_helper.php:11` and 80+ class calls, `tests/test_client_translate_expert_i18n.php:12` and 30+ class calls, `tests/test_client_translate_expert_version_rules.php:31,97`, `tests/test_client_translate_expert_translate_loop.php:3,30,100,127,196`, `tests/test_client_translate_expert_localization.php:3,21,159,269-270`. `tests/test_client_translate_expert_ajax_guard.php:123` loads only the controller.
- Docs: `README.md:98,101`, `CLAUDE.md:79,82,85,88,106,112-118,135-142,145`, `.claude/project-workflows.md:32-36,49-52,54`, `.claude/agents/ote-build-test.md:63,74-76,86`, `.claude/agents/ote-implementer.md:21,34`, `.claude/agents/ote-code-quality-reviewer.md:26`, `.claude/agents/ote-code-reviewer.md:27,47`, `docs/pocs/2-admin-design/endpoints-json.md:8-10`, `docs/pocs/2-admin-design/design-concept.md:13,200`.
- `tools/` has no library reference. `sources/install.php` and `sources/*.TXT` have no library reference.

Task 68 check: grep for `LibraryClientTranslateExpert` and `Localization` in `sources/` and `build.sh` finds no version rule that uses a library class name. The only hits outside the library are `build.sh:287-289`, the model (`:199,208`) and the catalog cron (`:14`). The `Localization` hits in the controller (`:708,726,846,862`) and the model (`:73,76,85,88,89,92`) are method names. They do not change.

Out of scope (do not rename):
- The OC4 extension folder `extension/client_translate_expert/` and its namespace part `ClientTranslateExpert`.
- The admin controller, the model, the catalog cron controller and their class names.
- Routes, setting keys, DB tables, language files and the log file name `client_translate_expert.log`.
- The OCMOD XML name `OpenCartTranslateExpertClient.ocmod.xml` and the vQmod XML name.
- The undefined `$startTime` in `translateInternal()` of the core (existing code, no change).

## Approach
Move the 7 library files with `git mv` into one folder and rename the files and classes. Change every include path, class name, `build.sh` rule, test and doc in one pass. A half-done move breaks every OC version. Move the language code mapping and the format fix from the Google client into `translateInternal()` of the core. The core gets a factory `createGoogleClient()` as a test seam. Design correction: the scope is larger than the text of issue #77 (one common folder, all 7 files renamed). The issue text is out of date.

## Assumptions
- ✅ verified: no file uses `$this->load->library`. All library files load by `include_once` or `require_once` with an absolute path (grep of `sources/`).
- ✅ verified: OC4 does not autoload the module libraries. OC 4.0.2.3 `admin/controller/startup/extension.php:24` (4.1.0.0 `:26`) registers `Opencart\System\Library\Extension\<Code>`. The module namespace `Opencart\Extension\ClientTranslateExpert\System\Library` has no autoloader registration. Thus the explicit includes are the only load path.
- Decision: keep the OC4 namespace `Opencart\Extension\ClientTranslateExpert\System\Library` for all 7 files. Do not add `\TranslateExpert` to it. The namespace does not need to match the folder, because the explicit includes load the files. This keeps `add_namespace` and the controller FQCN prefix unchanged.
- Decision: the OC4 package keeps the subfolder `extension/client_translate_expert/system/library/translate_expert/`. The OC4 include paths use this subfolder.
- ✅ verified: the OC installers make subfolders. OC 3.0.3.8 `admin/controller/marketplace/install.php:211-212` (`mkdir`). OC 2.0.3.1 and 2.3.0.2 `admin/controller/extension/installer.php:337-338` (`ftp_mkdir`). OC 4.0.2.3 `admin/controller/marketplace/installer.php:389` and 4.1.0.0 `:421` (`mkdir`). OC 1.5 has no installer (manual copy).
- ❓ unverified: the `google-cloud-translate` subfolder is not a precedent. `sources_external/` is not in the repo, so no package ever had a library subfolder. Failure mode: an installer skips the subfolder, and the module page fails with a fatal error. Mitigation: the build check and a manual install on a test store.
- ❓ unverified: how the OC loaders handle a library subfolder. The module does not use the loader, so this does not apply.
- ✅ verified: the OC 2.3 autoloader `system/startup.php:93-94` (2.3.0.2) maps a class to `library/<lowercase class>.php` with an `is_file` check. The new names have no such file. The autoloader does nothing, as before.
- ❓ unverified: the OCMOD and vQmod header ops skip a missing file without an error (`is_file` check at XML `:17`, `:36`). A wrong path removes the header items silently. Mitigation: the static path test in step 9 and the build check in step 10.
- ✅ verified: the old and new class names are all different. Stale flat files on a store cannot cause a "Cannot redeclare class" error.
- ❓ unverified: on OC 3.0 and 4.x, a manual copy-over update does not add the new folder to `oc_extension_path`. Failure mode: an uninstall leaves `translate_expert/` on the server. Impact is low.
- ✅ verified: `translateInternal()` of the core is the only caller of the Google client (`core:480`). `client_translate_expert.php:29` is the only caller of `translateInternal()`. Thus the mapping and the format fix in the core cover every path.
- ✅ verified: the batch empty-text rule is `$text && trim($text)` (`vh_google_translator.php:63`). It skips `''`, `null`, blank strings and `"0"`. It keeps their positions after `array_values()`.
- Decision: drop the array branch of `fixFormatParameters()`. Only `testFixFormatParametersArray` (`tests/test_vh_google_translator.php:104-117`) calls it. Production code calls the fix with one string, and the batch calls it for each position. The core applies the fix in a loop by position, so the branch has no caller.

## Steps
- [x] 1. Make the folder and move the files. Run `git mv` for each of the 7 files into `sources/upload/system/library/translate_expert/` with the new name. Do not change the file content in this step.
- [x] 2. Rename the 7 classes in their files. Change `extends LibraryClientTranslateExpertCore` to `extends TranslateExpertCore` in `translate_expert.php` and `translate_expert_localization.php`.
- [x] 3. Change the includes inside the library:
  - `translate_expert.php`: the OC4 paths become `DIR_EXTENSION . 'client_translate_expert/system/library/translate_expert/translate_expert_core.php'` and `.../translate_expert_cron_helper.php`.
  - `translate_expert.php`: the else paths become `DIR_SYSTEM . 'library/translate_expert/translate_expert_core.php'` and `.../translate_expert_cron_helper.php`.
  - `translate_expert_localization.php`: the same change for the core include.
  - `translate_expert_core.php`: the same change for the include of `translate_expert_google_client.php`.
  - Change the 12 `ClientTranslateExpertCronHelper::` calls in `translate_expert.php` to `TranslateExpertCronHelper::`.
- [x] 4. Change the callers:
  - Model `:4,5,7,8`: the new paths of `translate_expert.php` and `translate_expert_localization.php`. Model `:199`: `new TranslateExpert(`. Model `:208`: `new TranslateExpertLocalization(`.
  - Catalog cron `:4,6`: the new path of `translate_expert.php`. `:14`: `new TranslateExpert(`.
  - Admin controller `:1026,1042`: the new OC4 paths. `:1031-1034,1044`: the FQCN class names `TranslateExpertI18n` and `TranslateExpertRouteFilter`. `:1039`: the comment path. Keep the namespace prefix.
  - OCMOD XML `:13,16,35`: `DIR_SYSTEM . 'library/translate_expert/translate_expert_route_filter.php'` and `.../translate_expert_i18n.php`. `:20,23,46-48`: `TranslateExpertRouteFilter::` and `TranslateExpertI18n::`.
- [x] 5. Change `build.sh`:
  - `:167`: change to `mkdir -p "$EXT_DIR/system/library/translate_expert"`.
  - `:188-190`: move the folder `sources_release_40/upload/system/library/translate_expert` to `$EXT_DIR/system/library/translate_expert` in one `mv`. Do not use a list of file names.
  - `:226-232`: point the `OC4_LIB_*` variables to the new paths. Rename `OC4_LIB_LOC` and `OC4_LIB_GOOGLE` only if the new names are clearer. Keep `:250-256` in step with the variables.
  - `:287-289`: change the patterns to `new TranslateExpert(` and `new TranslateExpertLocalization(`. Change the targets to `\\Opencart\\Extension\\ClientTranslateExpert\\System\\Library\\TranslateExpert(` and `...\\TranslateExpertLocalization(`.
  - Make sure that the pattern `new TranslateExpert(` does not match `new TranslateExpertLocalization(`. The `(` makes the match exact.
  - Make sure that the controller rewrite `ControllerExtensionModuleClientTranslateExpert` does not touch the new FQCNs.
  - Grep `build.sh` and `build.release.sh` for each old name. Expect zero hits.
- [x] 6. Change the Google client `translate_expert_google_client.php`:
  - Keep the constructor, `translate()`, `translateBatch()`, `callGoogleTranslateApi()`, `callGoogleTranslateApiBatch()`, `sendGoogleTranslateRequest()`, `translateInternal()`, `translateInternalBatch()` and `GOOGLE_TRANSLATE_MAX_LENGTH`.
  - Remove the language code mapping from `translate()` (`:16-26`) and `translateBatch()` (`:43-53`).
  - Remove `fixFormatParameters()` and `fixFormatParameter()` (`:88-125`) and their calls (`:34`, `:80`).
  - Add `public static function isTranslatableText($text)` that returns `$text && trim($text)`. Use it in `translateBatch()` instead of the inline check. The core uses the same method.
  - Keep the empty-text rule and the positions in `translateBatch()`.
- [x] 7. Change the core `translate_expert_core.php`:
  - Add `protected static function mapGoogleLanguageCode($code)` with the 4 rules: `ua`→`uk`, `rs`→`sr`, `ge`→`ka`, `cz`→`cs`.
  - In `translateInternal()`, map `$source` and `$target` before the call of the client.
  - Move `fixFormatParameters($text, $translatedText)` (single string only) and `fixFormatParameter()` into the core as `protected`.
  - Add `protected function createGoogleClient($googleApiKey)` that returns `new TranslateExpertGoogleClient($googleApiKey)`. Use it instead of `new VHGoogleTranslator` at `:480`.
  - For a string: after the client call, set `$res->text` to `fixFormatParameters($text, $res->text)`.
  - For an array: use `array_values($text)`. For each position where `TranslateExpertGoogleClient::isTranslatableText()` is true, set `$res->texts[$i]` to the fixed text. Do not touch the other positions.
  - Apply the fix before the `var_export($res)` log line, as before.
  - Rename the log strings at `:468,479,481` to `translate_expert_google_client` and `TranslateExpertGoogleClient`.
  - Keep PHP 5.6 syntax. Do not use `??`, scalar type hints, return types or arrow functions.
- [x] 8. Change the tests:
  - `git mv tests/test_vh_google_translator.php tests/test_translate_expert_google_client.php`. Keep the constructor test, the 2 batch tests and the 6 integration tests. Use the class `TranslateExpertGoogleClient`.
  - Remove the mapping test and the 2 `fixFormatParameters` tests from the client test. Change the integration test with `'ua'` (`:185`) to `'uk'`, because the client no longer maps codes.
  - Add a client test for `"0"`, `null` and blank texts: positions stay, and the client sends no request for them.
  - `git mv` the route filter, cron helper and i18n tests to `tests/test_translate_expert_route_filter.php`, `tests/test_translate_expert_cron_helper.php` and `tests/test_translate_expert_i18n.php`. Change the paths and class names in them.
  - Keep the names of the translate loop, localization, ajax guard and version rules tests. They test module flows, not one library file. Change the paths and class names in them.
  - Localization test `:269-270`: write the stub to `$tmp/extension/client_translate_expert/system/library/translate_expert/translate_expert_core.php`, and point it to the new source path.
- [x] 9. Add the core and path tests:
  - Add `tests/test_translate_expert_core_translate.php` (no DB, no API key, any PHP warning fails).
  - Use a subclass of `TranslateExpertCore` that overrides `createGoogleClient()`. It returns a stub subclass of `TranslateExpertGoogleClient` that overrides `callGoogleTranslateApi()` and `callGoogleTranslateApiBatch()`.
  - Test the mapping of the 4 codes for source and target, for a string and for an array.
  - Test the format fix for a string (`%s` from `%с`, `%d` from `%д`).
  - Test the format fix by position in a batch with empty, blank, `null` and `"0"` texts between the real texts. Each skipped text comes back unchanged at its position.
  - Test that a skipped text with `%д` in it is not changed.
  - Add a static path test to the same file or a new file `tests/test_translate_expert_library_paths.php`. It reads each `translate_expert/` path in `sources/` (PHP and XML). It fails if the file does not exist under `sources/upload/system/library/`.
  - The static test also fails on any old file name or old class name in `sources/`, `build.sh` and `tests/`.
- [x] 10. Add a build check to `build.sh`. After each zip, check that the 7 new files exist at the expected path in the package. Check that no old name from step 9 is in the package. If a check fails, stop the build with a non-zero exit code.
- [x] 11. Update the docs (STE for `CLAUDE.md` and `.claude/**`):
  - `CLAUDE.md:79,82,85,88,106`: the new test names. `:112-118`: the new class names. Add the new test of step 9 to the test list.
  - `CLAUDE.md:135-142`: the architecture tree with `system/library/translate_expert/` and the new file names. Remove "with language code mapping" from the Google client line. Add it to the core line. `:145`: `TranslateExpert::runCron()`.
  - `.claude/project-workflows.md:32-36`: the new paths. `:33`: `TranslateExpert::runCron()`. `:49-52,54`: the new test names. Add the new test.
  - `.claude/agents/ote-build-test.md:63` (`system/library/translate_expert/*`), `:74-76,86` (new test names). Add the new test.
  - `.claude/agents/ote-implementer.md:21,34`, `.claude/agents/ote-code-quality-reviewer.md:26`, `.claude/agents/ote-code-reviewer.md:27,47`: the new paths and test names.
  - `README.md:98,101`: the new test name.
  - `docs/pocs/2-admin-design/endpoints-json.md:8-10` and `design-concept.md:13,200`: the new paths and file names.
  - Do not change `docs/tasks/**` old records.
- [x] 12. Add a README note to the update steps (`README.md:52`, `:67`, `:78`). For OC 1.5 to 3.0: "After the update and the Modifications refresh, you can delete the old library files in `system/library/`." List the 7 old file names. For OC 4.x, the current step already deletes the old folder content.
  - Write in the note that the user must click **Refresh** before the old files are deleted. The cached OCMOD header still has the old paths until the refresh. The vQmod caches have the same rule for OC 1.5.
- [x] 13. Check PHP 5.6 syntax in the changed library, model, catalog cron and test files (see `.claude/agents/ote-build-test.md` Step 1). Run `php -l` on each changed PHP file.
- [x] 14. Bump the version, build and test. Run `/bump-version`, then `./build.release.sh`. Run all unit tests in `CLAUDE.md`, the new tests and the JS helper tests.
- [x] 15. Check the packages. For each package in `releases/v<version>/`:
  - List the zip with `unzip -l`. Find the 7 new files in `system/library/translate_expert/` (OC 1.5 to 3.0) or in `extension/client_translate_expert/system/library/translate_expert/` (OC4 zip).
  - Grep the unzipped content for the old names of step 9. Expect zero hits.
  - In the OC4 package, check that each of the 7 files has the namespace line after line 1.
  - In the OC 1.5 package, check the paths in `vqmod/xml/OpenCartTranslateExpertClient.xml`.
- [x] 16. Compare with a baseline (dev-workflow Phase 3, "Compare with a baseline"):
  - Build the packages from `origin/main` in a temporary worktree under `docs/tasks/77-library-folders-google-client/tmp/`. Remove the worktree after the build.
  - Unzip each baseline package and each branch package. Run `diff -r` for each pair.
  - Expect only these differences: the version strings, the 7 library files (old paths removed, new paths added), the include and class lines in the model, catalog cron, controller (OC4) and OCMOD or vQmod XML.
  - For each library file, run `diff` between the old file in the baseline package and the new file in the branch package. Expect only the planned changes of steps 2, 3, 6 and 7.
  - Explain each changed file in the plan Findings.
- [x] 17. Independent review → `77-library-folders-google-client-review.md`.
- [x] 18. Address the review findings. Standing auto-approve is ON: decide each finding and report the reasons.
- [x] 19. Verify loop: bump the version, build and run all tests again after the fixes.
- [x] 20. Ask the user if a re-review is necessary.
- [x] 21. Improvements pass → `77-library-folders-google-client-improvements.md`.
- [x] 22. Update the text of GitHub issue #77 to the new scope (one common folder, 7 renamed files and classes, mapping and format fix in the core, factory method). Ask the user first. Do not run `gh issue edit` without approval.

## Done
- Landed: the 7 library files are in `sources/upload/system/library/translate_expert/` with the prefix `translate_expert_` and the classes `TranslateExpert*`. The Google client keeps only the API calls, the splitting, the grouping and the empty-text rule. The core has the language code mapping, the format fix and `createGoogleClient()`. `build.sh` has a permanent `check_package`. Final version: 04.01.0212. Release: `releases/v04.01.0212/`.
- Checks: 1794 PHP checks and 653 JS checks pass. The baseline `diff -r` of all 7 packages shows only the planned differences. The review (Opus, not independent) found 1 Important and 4 Minor findings. All 5 were fixed. The user declined a second review pass.
- Findings: the plan step 5 said to run `mkdir -p` for the `translate_expert` folder. This would nest the folder with `mv`, so `build.sh` makes only the parent folder. The path test of the first version passed with a wrong prefix. The OCMOD XML skips a missing file with no error, so the final path test resolves each full path.
- Deferred: the Google API integration tests (6) did not run, because no API key was given. No manual install on a test store was done (a library subfolder was never in a package before). No commit, push or PR was requested.
- Issue #77: the text was updated on 17.11.2026 to the new scope.
