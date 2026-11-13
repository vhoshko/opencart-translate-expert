# 37-admin-localization-tab: review v2 (fix pass only)

Scope: the 7 fixes for the findings of the first review. Version `04.01.0169`. Source: `git diff HEAD -- sources tests` and the new file `tests/test_client_translate_expert_localization.php`.

## How I did the review

- I read the changed lines for each of the 7 fixes.
- I ran all 6 test scripts. All pass (localization: 348 passed, 0 failed).
- I ran `php -l` on the library, the three language files and the new test.
- I read the OpenCart error handlers from release tags with the core source probe.
- I ran a new probe: `docs/tasks/37-admin-localization-tab/tmp/review_v2_probe.php` (cases `nodisplay` and `display`).
- I ran one mutation in a copy under `tmp/`. I removed the copy after the run.

I did not run a build and I did not run a store. The JS has no automated test, so I checked fix 5 by reading only.

## Strengths

- Fix 1 is the minimum change. `getMainLanguageFileName()` (`client_localization_translate_expert.php:151-158`) has one gate for each column, and the comment gives the reason.
- Fix 1 has a direct test (`tests/...localization.php:299-303`). It runs on all 8 versions, so it also proves that OC 1.5 still uses `filename`.
- Fix 2 puts a version on each side of the `2.0`, `2.2` and `4.0` boundaries.
- Fix 3 handles the `mkdir()` race correctly: `!is_dir() && !@mkdir() && !is_dir()`. It compares the `file_put_contents()` result with `=== false`, so an empty file is not a failure.
- Fix 3 has the new string in all three language files. Error code 3007 has no other use in `sources/`.
- Fix 3 needs no JS change. `doSaveLocalizationExpert` already stops on `!data.success`, shows the message and closes the modal. Thus "Translate all" stops at the first failed file.
- Fix 4 agrees with the established fact: the OC 4 fixture has `default.php`, and the test expects the same path on the two sides.
- Fix 5 uses the same `[<extension>]` form as the PHP display path. The server permits only `[A-Za-z0-9_-]` in an extension name, so the new text adds no HTML risk to the modal.
- Fix 6 checks the roots, the two core folders, the file list and the read of the existing target file. The order before the save tests is necessary, and the comment says why.
- Fix 7: `tmp/` holds only probe scripts now.
- No PHP 7+ syntax in the changed library lines.

## Issues

### Critical (must fix)

None.

### Important (should fix)

**1. On OC 4.x, the save does not return error 3007. The core error handler stops the request first.**

`sources/upload/system/library/client_localization_translate_expert.php:108-111`

The fix uses the `@` operator and then reads the result. This works only if the active error handler ignores a suppressed warning. The OC 4 handler does not.

Verified in core:

- OC 4.0.2.3 `admin/controller/startup/error.php:27-62` and OC 4.1.0.0 `admin/controller/startup/error.php:31-62`: the handler has no `error_reporting()` check. If `config_error_display` is off, it sends `Location: error.html` and calls `exit()`. If the setting is on, it prints the warning as HTML and returns.
- `system/config/admin.php:29` (4.0.2.3) registers `startup/error` for each admin request.
- OC 2.0.3.1 `admin/index.php:56-59` and OC 3.0.3.8 `system/framework.php:18-21` have the check `error_reporting() === 0`.
- OC 1.5.6.4 `admin/index.php:59-88` has no check, but it only prints or logs. It does not stop the request.

Probe (same handler logic as 4.0.2.3, blocked folder):

- `nodisplay`: the handler runs for `mkdir(): File exists` and stops. The library returns nothing.
- `display`: the handler prints the warning. Then the library returns 3007. In a store, the HTML before the JSON makes the response invalid.

Result on OC 4.x: the user never gets the 3007 text. With `config_error_display` off (the correct setting for a live store), the JS gets the `error.html` page and shows "Internal error" plus the page text. OC 4.x is the only version with extension roots, which was the reason for finding 3.

This is not a regression. Before the fix, the unsuppressed warning went through the same handler. The JS does not show "Saved!" in this case. Thus the fix is correct on OC 1.5–3.0 and has no effect on OC 4.x.

A smaller effect of the same kind: on PHP 8, `error_reporting()` is not `0` under `@`. Thus the OC 2.0–3.0 handler logs the suppressed warning, and prints it if the display setting is on. The 3007 result still comes back.

Fix: do not let the warning occur. Two options:

- Check before the write. Find the nearest existing parent of `$fullFolder`. If it is not a writable folder, return `getSaveFailedResult()`. Before `file_put_contents()`, do the same check for an existing target (`is_file()` and `is_writable()`). Keep the `@` and the result checks as a safety net.
- Or put a local handler around the two calls: `set_error_handler(function () { return true; });` before them and `restore_error_handler();` after them. This is PHP 5.6 syntax.

Then make the test prove it: for the save-failure case, install a handler that throws on each warning with no `error_reporting()` check. This is what OC 4 does in effect.

If the team accepts the OC 4 limit, record it in the plan. The plan line "A save returns error 3007 … if `mkdir()` or `file_put_contents()` fails" is not true for OC 4.x today.

### Minor (nice to have)

**2. No test covers the `file_put_contents()` failure branch.** `tests/test_client_translate_expert_localization.php:446-454`. The test blocks `mkdir()` only. Mutation in a copy: I replaced lines 110-111 of the library with a bare `@file_put_contents(...)`. Result: 348 passed, 0 failed. Fix: add a case where the target path is an existing folder (for example, make the folder `<target>/dir.php/` and save `/dir.php`). This fails for root also.

**3. The test error handler hides the difference from finding 1.** `tests/test_client_translate_expert_localization.php:99-103`. The handler ignores a suppressed warning, as OC 2.0–3.0 do on PHP 7. OC 4.x does not ignore it. Thus the 4.0.2.3 and 4.1.0.0 runs of `testSaveFailure` pass for a case that fails in a store. The fix for finding 1 covers this.

**4. The message says "folder" but shows a file path.** `client_localization_translate_expert.php:143-147` and the three `error_localization_save_failed` strings. The text ends with "can write to this folder:" and the code adds `$fullPath`, which is the file. Fix: pass `dirname($fullPath)`, or change the text to "this file".

**5. The label of one test check is wrong on OC 4.** `tests/...localization.php:336`, `:366`. The text says "main file is renamed to the target language". On OC 4 the check proves that the path does not change. The check itself is correct. Fix: use a second label for OC 4.

### Checked, no finding

- Fix 1: a row with `filename` and VERSION ≥ 2.0 gives `directory` (2.0–2.1) or `code` (2.2+). This agrees with the established load rules.
- Fix 2: the 2.0.3.1 rows have `directory` and no `filename`. The 2.2.0.0 rows use `en-gb` folders and names. Both agree with the established facts.
- Fix 3: the 3007 message has the full server path. Only a user with the `modify` permission gets it, and error 3001 already shows a full path. No API key is near this code.
- Fix 3: a path whose parent is a file fails in `mkdir()` and writes nothing.
- Fix 5: a `core` root gives the same modal text as before. An old cached JS file has no effect on the server side.
- Fix 6: the fixture gives the source language `extension = 'ukrainian'` and the target `extension = ''`. This is the case that the first review asked for.
- Fix 6: the handler returns `false` for a suppressed warning on PHP 7 (`error_reporting()` is `0`) and on PHP 8 (the `E_WARNING` bit is off). Each warning without `@` still fails the run.
- Fix 7: `tmp/loc.orig.php` does not exist.
- No new AJAX action, library class or setting key came with the fixes. The fixes need no `build.sh` entry.

## Assessment

**Ready to merge?** With fixes.

Fixes 1, 2, 4, 5, 6 and 7 are clean. Fix 3 is correct on OC 1.5–3.0, but on OC 4.x the core error handler stops the request before the 3007 result, and the test handler hides this. Finding 1 is not a regression and causes no data loss, so the team can also accept it as a recorded limit.

Not verified: no store run and no build in this review, and no automated test of the JS.
