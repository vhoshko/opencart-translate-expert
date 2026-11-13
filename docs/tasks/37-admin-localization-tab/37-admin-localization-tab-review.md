# 37-admin-localization-tab: review

Scope: `git diff HEAD -- sources tests CLAUDE.md` and the new file `tests/test_client_translate_expert_localization.php`. Version `04.01.0168`.

## How I did the review

- I read the full diff and the code around it.
- I ran all 6 test scripts. All pass (localization: 194 passed, 0 failed).
- I read `build.sh` for each rewrite that touches the changed lines.
- I read OpenCart core files from release tags with the core source probe.
- I ran a probe: `docs/tasks/37-admin-localization-tab/tmp/review_probe.php` (cases `filename30` and `oc4src`).

I did not run a build and I did not run a store. The JS and the controller have no automated test, so I checked them by reading only.

## Strengths

- No new AJAX action, library class or setting key. Thus `build.sh` needs no new entry. I confirmed this: the `/` → `.` rewrites at `build.sh:383-384` still match the two JS URLs.
- `getLocalizationTargetFolder()` is the one source of the target folder for the analysis and for the save. The client sends a root id only, never a folder.
- The save guard closes a real hole. Before this change, a `path` with `../` wrote PHP to any folder, with no `modify` check.
- The folder rules agree with OpenCart core. I verified these items:
  - OC 2.0.3.1 `admin/index.php:122-123` and `index.php:177-178` load the main file by `directory`. OC 2.1.0.2 `index.php:192-193` does the same. Thus the `directory` rename is correct for OC 2.0–2.1.
  - OC 2.3.0.2 `admin/controller/startup/startup.php:23-24` loads the main file by `code`.
  - OC 4.0.2.3 `admin/controller/startup/language.php:32-33` adds `DIR_EXTENSION/<ext>/admin/language/` for a language extension.
  - OC 4.0.2.3 `admin/controller/startup/extension.php` adds `DIR_EXTENSION/<code>/admin/language/` for each installed extension. `system/library/language.php:131-146` then loads `<that folder>/<code>/<file>.php`. Thus a file that the module saves in `DIR_EXTENSION/<ext>/admin/language/<toCode>/` loads.
- OC 4 with a source language from a language extension works. The probe case `oc4src` shows the correct core root, the correct target for a language without an extension, and no double root.
- The JS keeps the two tabs apart. Each scope has its own result list, its own button list for "Translate all" and its own error position. No other element in the templates has `data-scope`.
- The raw `token` → `user_token` rewrite (`build.sh:141`, `:159`) finds no new match in the added JS or controller lines.
- `$this->load->language()` in `saveLocalizationFile` is safe on OC 1.5 (1.5.6.4 `system/engine/loader.php:71`). The build also rewrites this call for OC 2.1 and earlier.
- The `hasPermission` route gets the OC 4 rewrite (`build.sh:298`) and the generic OC ≤ 2.2 rewrite.
- Both templates have the new tab, each in its own syntax. All three language files have the two new strings.
- The new test uses the real library and a real folder tree. It also proves that a rejected save writes no file.

## Issues

### Critical (must fix)

None.

### Important (should fix)

**1. A leftover `filename` column breaks the main file rename on OC 2.2–3.0 (regression in the catalog tab).**

`sources/upload/system/library/client_localization_translate_expert.php:145-152`

```php
if (!empty($language['filename']))
    return $language['filename'];
```

This line has no version gate. The old code used `filename` only if `VERSION < 2.1`. On OC 2.2+ it always used `code`.

The `filename` column exists only in the OC 1.5 schema (1.5.6.4 `install/opencart.sql`). The OC 2.0.3.1 and 2.3.0.2 schemas do not have it. The 2.0.3.1 upgrade model (`install/model/upgrade.php`) drops only some named columns, and `filename` is not one of them. Thus a store that was upgraded from OC 1.5 can keep the column with the value `english`.

Probe case `filename30` (OC 3.0.3.8, source row with `filename = 'english'`): the result is `/en-gb.php => /en-gb.php`. The module saves the file as `uk-ua/en-gb.php`. OpenCart never loads that file, and `uk-ua/uk-ua.php` stays without a translation. The old code gave `/uk-ua.php` here.

Not verified: I did not read the upgrade models of the later versions. One of them can drop the column. The store must also be an upgraded store, so the number of affected stores is small.

Fix: use `filename` only if `version_compare(VERSION, '2.0', '<')`. Add a test case for OC 3.0.3.8 with a `filename` value in the language rows.

**2. The test does not cover the `2.2` version boundary.**

`tests/test_client_translate_expert_localization.php:31`

The versions are 1.5.6.4, 2.1.0.2, 2.3.0.2, 3.0.3.8 and 4.0.2.3. Three new or changed rules use `version_compare(VERSION, '2.2', '<')`: the admin folder, the catalog folder and the main file name. If a person changes `'2.2'` to `'2.3'` in one of them, each test still passes, because no test runs on 2.2.x. I found this by reading. I did not run this mutation.

Fix: add `2.2.0.0` to the list. Optional: add `2.0.3.1` (the plan names OC 2.0–2.1 as the target of the rename fix) and `4.1.0.0`.

### Minor (nice to have)

**3. The save reports success when the write fails.** `client_localization_translate_expert.php:108-112`. The code does not check the results of `mkdir()` and `file_put_contents()`. On OC ≤ 3.0 a failed write gives a warning in the log, and the JS shows "Saved!". This is old behavior. But the new tab writes into `admin/` and into extension folders, where a read-only folder is more likely. Fix: if `file_put_contents()` returns `false`, return an error result.

**4. The OC 4 test fixture has a main file that OC 4 does not have.** `tests/test_client_translate_expert_localization.php:210`, `:217`. OC 4.0.2.3 has `admin/language/en-gb/default.php` and loads `default` (`startup/language.php`). The fixture makes `en-gb/en-gb.php` and checks the rename to `uk-ua.php`. The library does the correct thing on a real OC 4 store: no rename occurs (probe case `oc4src` shows `/default.php => /default.php`). But the test shows a rename that never occurs there. Fix: on OC 4, use `default.php` in the fixture and check that the path does not change.

**5. The "Translate all" modal does not show the extension name.** `client_translate_expert.js:152-157`. The text has `pathTo` only. On OC 4, two roots can hold the same relative path. Then the progress text is the same for two different files. Fix: add the `data-root` value to the text if it is not `core`.

**6. The test has no case for these items.** These are gaps, not defects. The probe shows that the first one works.
- OC 4 with a source language from a language extension (the skip of the source extension).
- OC 4 with a target language without an extension.
- The controller: the `modify` check and the scope whitelist. No test in the repository runs the controller, so this is in line with the other tests.

**7. A leftover file is in the task `tmp` folder.** `docs/tasks/37-admin-localization-tab/tmp/loc.orig.php` (19 KB) looks like the backup from the mutation proof. Remove it before the commit, or do not commit the `tmp` folder.

### Checked, no finding

- Save guard, path: a path must start with `/`, end with `.php` and have no `..`. Such a path stays below the target folder. `//x.php` and `/.php` also stay below it.
- Save guard, root: only `core` or `ext:<name>` with a safe name and an existing `admin/language` folder. The catalog scope rejects each `ext:` root.
- The save accepts `ext:<source language extension>`, which the analysis skips. The file still goes into an admin language folder, so this is not an escape.
- A user with the `modify` permission can still write PHP of any content into a language folder. This is the purpose of the feature, and it was the same before.
- The four safety-net guards named in the brief are small and correct.
- `.data('path-to')` → `.attr('data-path-to')`: same value for these strings. It agrees with the new attribute escaping.
- Old cached JS with the new HTML: the old `doTranslateLocalizationAllExpert` ignores the second argument. No error.
- No PHP 7+ syntax in the added PHP lines.

## Assessment

**Ready to merge?** With fixes.

The design is sound, the folder rules agree with OpenCart core, and I found no way out of the language folders through the save guard. Fix issue 1 before the merge: it is a one-line gate, and without it the change can break a catalog case that works today. Issue 2 is a two-word test change that protects the version rules.

Not verified: no store run and no build in this review, and no automated test of the JS and the controller.
