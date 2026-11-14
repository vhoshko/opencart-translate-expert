# 49-oc4-extension-catalog-localization

## Settings

- Planning model: Opus 5.5 (inline, session model)
- Review model: Sonnet 5.5 (pass `model: sonnet` to `ote-code-reviewer`)
- Build and tests: yes. Commit, push and PR: no (the workflow stops after the review and the improvements)
- Standing auto-approve for review findings: yes (the user said "turn on autoapprove" on 2026-11-13). Decide each finding and report the reason. Ask the user for a change to the approach, the scope or an external system
- User instruction (2026-11-13): make the catalog logic as similar as possible to the admin localization logic

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/49

On OC 4.x the Localization files tab scans only one catalog language folder for each language. Extensions keep their catalog language files in `DIR_EXTENSION/<ext>/catalog/language/<code>/`. The tab never scans these folders, so these files stay in the source language. PR #48 (merged as `a684ee8`) added `ext:<name>` roots for the `admin` scope only. This task adds the same roots for the `catalog` scope.

Affected OC versions: 4.0 and 4.1 only. On OC 1.5–3.0 the extension files are inside the catalog language folder, and the recursive scan finds them.

Key files:

- `sources/upload/system/library/client_localization_translate_expert.php:173` `isOc4ExtensionName()`: checks `admin/language` only
- `sources/upload/system/library/client_localization_translate_expert.php:181` `getLocalizationTargetFolder()`: accepts `ext:<name>` for the `admin` scope only (`:193`)
- `sources/upload/system/library/client_localization_translate_expert.php:204` `getLocalizationRoots()`: returns early for the `catalog` scope (`:212`) and scans `admin/language/<fromCode>/` only (`:228`)
- `tests/test_client_translate_expert_localization.php`: runs on 1.5.6.4 … 4.0.2.3, 4.1.0.0. Admin `ext:` cases at `:271-278`, `:333-334`, `:375`, `:515-519`, `:565-571`

Investigation: skipped. The greps for `DIR_EXTENSION`, `getLocalizationRoots` and `getLocalizationTargetFolder` find all the code. The JS, the templates and the controller already send `scope` and `root` (PR #48).

## Approach

Make the `ext:<name>` code path scope-generic. The admin and the catalog then use the same code. The only difference is the folder part `<scope>/language`.

1. `isOc4ExtensionName($name)` → `isOc4ExtensionName($name, $scope)`: check `DIR_EXTENSION . $name . '/' . $scope . '/language'`.
2. `getLocalizationTargetFolder()`: accept `ext:<name>` for both scopes. Return `DIR_EXTENSION . $name . '/' . $scope . '/language/' . $toCode . '/'`. Keep the same checks (safe name, existing language folder, not the language extension of the target).
3. `getLocalizationRoots()`: remove the admin-only early return. Scan `DIR_EXTENSION/<name>/<scope>/language/<fromCode>/`. Keep the skip of the language extensions of the source and the target.
4. Add catalog cases to the tests, as mirrors of the admin cases.

The include folder for an `ext:` root stays `getLocalizationIncludeFolder($scope)`, the same as for the admin `ext:` roots.

## Assumptions

- ✅ verified: The OC 4.x catalog loads extension language files from `DIR_EXTENSION/<ext>/catalog/language/<code>/` for each installed extension. Evidence: `catalog/controller/startup/extension.php` 4.0.2.3 `:30` and 4.1.0.0 `:32` call `$this->language->addPath('extension/' . $ext, DIR_EXTENSION . $ext . '/catalog/language/')`. `system/library/language.php` 4.0.2.3 `:145` and 4.1.0.0 `:153` build the file path as `$path[$namespace] . $code . <rest of route> . '.php'`. A translated file in `<ext>/catalog/language/<toCode>/` therefore loads for the target language.
- ✅ verified: If the target language comes from a language extension, the catalog also loads an optional override file `extension/<lang-ext>/catalog/language/<code>/extension/<ext>/…`. Evidence: `catalog/controller/startup/language.php` `after()` 4.0.2.3 `:54-79`, 4.1.0.0 `:62-87`. The override loads after the main file, and only if it exists. The admin has the same logic (OC 4.0.2.3 `admin/controller/startup/language.php:67-71`). The admin `ext:` target is the folder of the extension itself, so the catalog target is also the folder of the extension itself. This keeps the two scopes the same.
- ❓ unverified: no run on a live OC 4.x store. The tests stub the file system. If the stub tree differs from a real store, the risk is limited: the code only reads and writes folders, and the folder layout is verified above.

## Steps

- [x] Code: make `isOc4ExtensionName()`, `getLocalizationTargetFolder()` and `getLocalizationRoots()` scope-generic in `client_localization_translate_expert.php`
  Findings: `isOc4ExtensionName($name, $scope)` has 3 callers, all in this file. It now also calls `isLocalizationScope($scope)`, so an unknown scope cannot build a folder path. PHP 5.6 safe (no new syntax).
- [x] Tests: add catalog `ext:` files to the OC 4 tree. Add catalog checks for the roots, the analysis files, the reverse direction, the save guard and the save
  Findings: 595 → 683 passed. New tree items: `opencart/catalog/…/payment/cod.php` (with an include), `catalogonly` (catalog files only), `ukrainian/catalog/language/{uk-ua,en-gb}`, `bad name/catalog`. Mutation check: with the `origin/main` library, the suite fails (479 passed, 4 failed) on "catalog roots" for 4.0.2.3 and 4.1.0.0. The missing `ext:opencart` key throws and stops the rest of that version run, so the later catalog checks do not run in the mutation. The fix was restored from a byte copy and checked with `git diff --stat`.
- [x] Bump version + build + test
  Findings: 04.01.0173 → 04.01.0174. All 6 test scripts pass (localization 683). The oc4.x package has `isOc4ExtensionName($name, $scope)`. No PHP 5.6 hits.
- [x] Independent review → `49-oc4-extension-catalog-localization-review.md`
  Findings: Sonnet 5.5. No Critical or Important issues. 3 Minor items, each marked "no change needed".
- [x] Address review findings (auto-approve)
  Findings: skipped all 3 Minor items, because each one asks for no action. Separate fix from the improvements pass: the mutation run stopped on an unguarded `$roots['ext:opencart']` read, so the later catalog checks did not run. Added `isset` guards in `testRoots()` and `testCatalogAnalysis()`. The new mutation run: 659 passed, 12 failed (6 for each OC 4 version: roots, target folder, root name, analysis files, save, saved file). With the fix restored: 683 passed.
- [x] Bump version + build + tests (verify loop)
  Findings: 04.01.0174 → 04.01.0175. All 6 test scripts pass (localization 683). Release build pass.
- [x] Re-review? (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → -review-v2.md, v3, …)
  Findings: the user said no (2026-11-13). Only `isset` guards in the test file changed after the review.
- [x] Improvements pass → `49-oc4-extension-catalog-localization-improvements.md`
  Findings: the user approved edit 2. The gotchas row is now in `.claude/project-workflows.md`.

Templates and language strings: no change. The UI already shows the `[<ext>]` prefix and sends `root` for each row.

## Done

- Landed: the `ext:<name>` roots now work for the `catalog` scope on OC 4.x. Admin and catalog share one code path in `client_localization_translate_expert.php`. 88 new test checks (595 → 683).
- Final version: 04.01.0175. All 6 test scripts pass. Release build pass.
- Gotcha: an unguarded array read in the localization test stopped the mutation run (second time). The fix is now a row in the gotchas table of `project-workflows.md`.
- Deferred: a live run on an OC 4.x store. OC 4.1 nested OCMOD extensions (`DIR_EXTENSION/ocmod/…`) stay out of scope, as the issue says.
- Not done by this workflow: commit, push and PR.
