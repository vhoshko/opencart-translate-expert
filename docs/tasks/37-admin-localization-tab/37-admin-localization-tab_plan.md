# 37-admin-localization-tab

## Settings

- Planning model: Opus 5.5 (inline, session model).
- Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes, after each change set (`ote-build-test`, full mode).
- Commit, push, PR: no. The workflow does not commit. Ask the user at the end.
- Standing auto-approve for review findings: not granted. Ask the user for each finding.
- Plan approved by the user on 2026-11-13. No store run before the review (unit test only).
- Branch: `feat/37-admin-localization-tab` from `origin/main` (`f3c212b`).
- Unrelated uncommitted files (`.claude/project-workflows.md`, two `docs/tasks/44-*` files, one `docs/tasks/36-*` file) stay in the working tree. The user said to ignore them. Do not include them in this task.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/37

The "Localization files translation" tab (`#tab-localization`) translates only the catalog (storefront) language files. The task adds a second tab, "Admin localization files translation", for the admin language files. On OC 4.x the new tab also scans the `admin/language/` folder of each installed extension. All OC versions (1.5–4.1) are affected.

Key files (line numbers on `f3c212b`):

- `sources/upload/system/library/client_translate_expert_core.php:842` `getLanguageFolder()`: catalog folder only. `:665` `getDirContents()`: recursive `.php` read. Keys are relative paths with a leading `/`.
- `sources/upload/system/library/client_localization_translate_expert.php:13` `analizeLocalization()`, `:40-51` main file rename, `:75` `saveLocalizationFile()`, `:150` `buildAnalizeDetailedHtml()`, `:273` `parsePhpInclude()` (hard-coded `DIR_CATALOG . 'language/'` at `:280`).
- `sources/upload/admin/model/extension/module/client_translate_expert.php:68` and `:80`: thin pass-through to the library. The localization calls do not go through `system/library/client_translate_expert.php`.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:631` `analizeLocalization()`, `:768` `saveLocalizationFile()`, `:171` `tab_localization`.
- `sources/upload/admin/view/javascript/client_translate_expert.js:49` `doSaveLocalizationExpert`, `:86` `doTranslateLocalizationExpert`, `:109` `doTranslateLocalizationAllExpert`, `:791` `makeLocalizationAnalizeRequest`, `:1164` button bind. The JS uses hard-coded ids and the globals `document.localizationAnalizationResult`, `.localization-analize-table` and `.translate_localization_button`.
- `.tpl:31`, `:242-275` and `.oc4.twig:31`, `:238-271`: the current tab.
- `build.sh:383-384`: OC4 JS method rewrites for `analizeLocalization` and `saveLocalizationFile`.

Investigation: Explore agent. I checked the claims above with file reads.

## Approach

Give the existing AJAX actions a `scope` parameter (`catalog` or `admin`). Do not add new actions. Thus `build.sh` needs no change. The default is `catalog`, and the server accepts only the two values. Do not change the catalog tab behavior, except for the fixes below.

The library gets one new method, `getLocalizationRoots($scope, $languageFrom, $languageTo)`. It returns a list of roots. Each root has an id, a source folder and a target folder. The `catalog` scope has one root, `core`, from `getLanguageFolder()`. The `admin` scope has these roots:

- `core`: the admin language folder of the language (see Assumptions for each version).
- OC 4.x only: one root `ext:<name>` for each folder in `DIR_EXTENSION` that has `admin/language/<fromCode>/`. Skip the language extension of the source language and of the target language, because the `core` root covers that folder. The target folder is `DIR_EXTENSION/<name>/admin/language/<toCode>/`.

Each analysis item gets a `root` field. The HTML row gets `data-root`. The display path of an extension file shows the extension name. Save sends `scope`, `root` and `path`. The server computes the roots again and finds the folder by the root id. The client never sends a folder.

Design corrections (beyond the issue text):

1. Security guard on save. Today `saveLocalizationFile` writes POST content to `<folder> . <path>` with no check. A `path` with `../` writes PHP anywhere. The new tab writes into `admin/`, so the risk increases. Add these checks for both scopes:
   - The controller requires `hasPermission('modify', ...)`.
   - The library rejects a `path` that does not start with `/`, does not end with `.php`, or contains `..`, a backslash or a NUL byte.
   - The library rejects an unknown `scope` or `root`.
2. Main file rename. The name comes from a new helper. It returns `filename` if the language row has it (OC 1.5), `directory` if VERSION < 2.2 (OC 2.0–2.1), and `code` in other cases. This fixes the rename on OC 2.0–2.1 for both scopes (today `/en.php` never matches `english.php`). The rename applies to the `core` root only.
3. `parsePhpInclude` gets the include base folder: `DIR_CATALOG . 'language/'` for `catalog`, `DIR_APPLICATION . 'language/'` for `admin`.
4. Escape `data-path-to` and `data-root` with `htmlspecialchars(ENT_QUOTES)`. A file name with `'` breaks the HTML today.
5. The JS finds the scope from the closest `[data-scope]` element. Each scope keeps its own analysis result and button list. The error paths write into the result div of the scope, not `#te-analization-result`.

The new tab reuses the session keys `te_langIdFrom` and `te_langIdTo`, as the catalog tab does.

## Assumptions

- ✅ `DIR_APPLICATION` is the `admin/` folder in the admin of each version. Evidence: installer `step_3.php` writes `define('DIR_APPLICATION', ... 'admin/')`: 1.5.6.4 `install/controller/step_3.php:55`, 2.2.0.0 `:58`, 3.0.3.8 `:62`, 4.0.2.3 `:76`, 4.1.0.0 `:217`.
- ✅ Admin core folder: OC 1.5–2.1 `admin/language/<directory>/`, OC 2.2+ `admin/language/<code>/`. Evidence: tag listings of `upload/admin/language`. 2.0.3.1 and 2.1.0.2 have `english/` and `english.php`. 2.2.0.0 has `en-gb/` and `en-gb.php`.
- ✅ Main file name: OC 1.5 loads `filename` (1.5.6.4 `admin/index.php:124`). OC 2.0–2.1 load `directory` (2.1.0.2 `admin/index.php:123`). OC 2.2+ use `<code>.php`.
- ✅ OC 4 language extension: if a language has `extension = X`, the admin loads `extension/X/<route>` after the core route. Its files are in `DIR_EXTENSION/X/admin/language/<code>/`. Evidence: 4.0.2.3 `admin/controller/startup/language.php:32-33` and `:67-71`. 4.1.0.0 has the same code.
- ✅ OC 4 extension admin files: `admin/controller/startup/extension.php` registers `DIR_EXTENSION/<code>/admin/language/` for each installed extension (4.0.2.3 `:30`, 4.1.0.0 `:32`). Thus a translated file in `DIR_EXTENSION/<ext>/admin/language/<toCode>/` loads.
- ❓ A store run on OC 1.5–4.1 is not done before the review. The host checkout is separate, so a run needs a WIP push. If a path is wrong, the tab shows "folder does not exist" or saves the file into the wrong folder. The unit test below covers the path rules for each version with a temp folder tree.
- ❓ Performance on OC 4.x. The admin scope reads about 300–500 files on each 10-file page (the current design). Not measured. If it is too slow, the analysis requests time out. The catalog tab has the same design today.

## Steps

- [x] Code: library. Add `getLocalizationRoots()`, the main-file-name helper, the scope in `analizeLocalization()`, `saveLocalizationFile()` and `parsePhpInclude()`, the save guard and the `root` field and escaping in the HTML.
  Findings: `getAdminLanguageFolder()` is in the core library, next to `getLanguageFolder()`. `getLocalizationTargetFolder()` is the one source of the target folder for the analysis and the save. An extension root needs a safe name (`[A-Za-z0-9_-]`) and an existing `admin/language` folder. The "Translate all" button now calls `doTranslateLocalizationAllExpert(0, this)`.
- [x] Code: model pass-through and controller (`scope` param, whitelist, `modify` permission on save, `tab_admin_localization` data).
  Findings: the controller accepts only string POST values for `path`, `langId`, `fileContent` and `root`. `$this->load->language()` exists in the OC 2.1.0.2 loader (`system/engine/loader.php:110`). No new action, so `build.sh` does not change.
- [x] Code: JS scope handling (analysis, translate, translate all, save, button bind).
  Findings: one id map `translateExpertLocalizationIds`. Results are in `document.localizationAnalizationResults[scope]`. The lookup uses `pathTo` and `root`. The JS reads `data-path-to` and `data-root` with `.attr()`. On an analysis error, the button is enabled again (before, it stayed disabled).
- [x] Templates: new `#tab-admin-localization` in `.tpl` and `.oc4.twig`, after `#tab-localization`. Add `data-scope` on both result divs. Fix the copy-paste `for=` label.
- [x] Language strings: `tab_admin_localization` and the save-guard error in en-gb, ru-ru, uk-ua.
  Findings: the save-guard key is `error_localization_save_target_invalid`. The permission error uses the existing `error_permission`.
- [x] Tests: new `tests/test_client_translate_expert_localization.php`. It runs one child process for each VERSION (1.5.6.4, 2.1.0.2, 2.3.0.2, 3.0.3.8, 4.0.2.3) on a temp folder tree. It checks the roots, the main file rename, the include base and the save guard. Add it to `CLAUDE.md` Tests.
  Findings: 194 checks pass. Mutation proof (fix disabled, test run, file restored and checked with `cmp`): (1) no `directory` rename gives 3 failures on OC 2.1. (2) no path guard gives 8 failures. (3) catalog include folder for the admin scope gives 5 failures. The test subclass must be declared after the library loads, because PHP declares a class with an unknown parent only at run time.
- [x] Bump version + build + test
  Findings: version 04.01.0168. All 6 test scripts pass (localization 194, cron helper 83, route filter 40, translate loop 217, Twig 29, translator 24 + 6 skipped without a key). Package checks pass: the oc4.x JS uses the dot form, the `hasPermission` route on save matches `validate()` in the oc1.5, oc2.2, oc3.0 and oc4.x packages, and oc1.5 passes `tab_admin_localization` through `$this->data`.
- [x] Independent review → `37-admin-localization-tab-review.md`
  Findings: Fable 5.2. 0 Critical, 2 Important, 5 Minor. Verdict: ready to merge with fixes.
- [x] Address review findings (the user picks which to fix or skip)
  Findings: the user chose to fix all 7.
  1. `getMainLanguageFileName()` uses `filename` only if VERSION < 2.0. A store upgraded from OC 1.5 can keep the column, and the old code also limited it by version. New test case: leftover `filename`.
  2. The test runs 8 versions: 1.5.6.4, 2.0.3.1, 2.1.0.2, 2.2.0.0, 2.3.0.2, 3.0.3.8, 4.0.2.3, 4.1.0.0.
  3. A save returns error 3007 (`error_localization_save_failed` + full path) if `mkdir()` or `file_put_contents()` fails. The test blocks the folder with a file, because `chmod` does not stop root.
  4. The OC 4 fixture uses `default.php`. No rename occurs on OC 4.
  5. The "Translate all" modal shows `[<extension>]` before the path.
  6. New OC 4 case: the source language is from a language extension and the target is not. This case runs before the save tests, because the save tests write `uk-ua` files.
  7. `tmp/loc.orig.php` is removed.
  The controller `modify` check has no test harness (no controller test exists). The build checks its route in each package.
  Mutation proof: `filename` on all versions gives 7 failures. The `'2.2'` boundary changed to `'2.3'` gives 6 failures on 2.2.0.0. No write-failure check gives 34 failures, with the save-failure check among them. The files were restored and checked with `cmp`.
- [x] Bump version + build + tests (verify loop)
  Findings: version 04.01.0169. All 6 test scripts pass (localization 348). The oc1.5 and oc4.x packages have `error_localization_save_failed` and `getSaveFailedResult`. The oc4.x JS uses the dot form for `saveLocalizationFile`.
- [x] Re-review? (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → `-review-v2.md`, v3, …)
  Findings: the user said yes.
- [x] Independent review → `37-admin-localization-tab-review-v2.md` (fix pass only)
  Findings: Fable 5.2. Fixes 1, 2, 4, 5, 6 and 7 are clean. Fix 3 has 1 Important finding and 4 Minor findings. The user chose to fix all 5.
  1. ~~A save returns error 3007 if `mkdir()` or `file_put_contents()` fails (fix 3 of review 1).~~ This was not true on OC 4.x. The OC 4 admin error handler (4.0.2.3 `admin/controller/startup/error.php:27-62`) has no `error_reporting()` check, so `@` does not stop it. It redirects to `error.html` and calls `exit()`, or it prints HTML. Fix: a local `set_error_handler(function () { return true; })` and `restore_error_handler()` around the two calls.
  2. New test case: the target file path is an existing folder, so `file_put_contents()` fails.
  3. The test error handler throws on each warning again, also on a suppressed warning. This copies the OC 4 behavior.
  4. The message shows the folder (`dirname`), not the file path.
  5. On OC 4, the check labels say that `default.php` keeps its name.
  Mutation proof: no local error handler gives 8 failures. No `file_put_contents()` result check gives 8 failures. The file was restored and checked with `cmp`. The localization test has 364 checks.
- [x] Bump version + build + tests (verify loop after review v2)
  Findings: version 04.01.0170. All 6 test scripts pass (localization 364). In the oc4.x package, `set_error_handler` and `restore_error_handler` stay global in the namespaced library.
- [x] Re-review? (after review v2)
- [x] Improvements pass → `37-admin-localization-tab-improvements.md`
  Findings: the user did not ask for review v3. One new gotcha: the OC 4 admin error handler ignores `@`. 4 proposed edits wait for the go-ahead of the user.

Out of scope (follow-up issues):

- OC 4.x catalog files of extensions (`DIR_EXTENSION/<ext>/catalog/language/`).
- OC 4.1 nested OCMOD extensions (`DIR_EXTENSION/ocmod/extension/<code>/admin/language/`).
- Translation value escaping in the JS (only `"` is escaped today, `$` and `\` are not).

## Done

- Landed: the new tab "Admin localization files translation" (`#tab-admin-localization`) in the `.tpl` and the `.oc4.twig`. The `scope` (`catalog` or `admin`) goes to the existing `analizeLocalization` and `saveLocalizationFile` actions. On OC 4.x, the tab also scans the `admin/language/` folder of each installed extension and saves each file in its own extension folder.
- Also landed, for both tabs: the save guard (`modify` permission, path, scope and root checks), the 3007 error when a write fails, the OC 2.0–2.1 main file rename through `directory`, and attribute escaping. New test `tests/test_client_translate_expert_localization.php` (8 OC versions, 364 checks).
- Final version: 04.01.0170. Reviews: v1 (2 Important, 5 Minor) and v2 (1 Important, 4 Minor). All findings are fixed.
- Not done: no store run (the user chose the unit test only). Nothing is committed or pushed. The unrelated uncommitted files (`.claude/project-workflows.md`, `docs/tasks/44-*`, `docs/tasks/36-*`) are still in the working tree. No stash was made.
- Follow-ups: OC 4.x catalog files of extensions, OC 4.1 nested OCMOD extensions (`DIR_EXTENSION/ocmod/extension/<code>/admin/language/`), and the JS escaping of `$` and `\` in translated values.
