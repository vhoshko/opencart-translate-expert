# 24-char-count-reset review

Scope: `git diff HEAD` on branch `fix/24-char-count-reset`. The diff has two files: `_version.txt` (04.01.0119 → 04.01.0120) and `sources/upload/admin/controller/extension/module/client_translate_expert.php`.

Checks that the reviewer ran:

- `php -l` on the admin controller: no syntax errors.
- `php tests/test_vh_google_translator.php`: 24 passed, 0 failed, 6 skipped (no API key).
- `php tests/test_client_translate_expert_route_filter.php`: 40 passed, 0 failed.

The reviewer did not run a build and did not test in a running store.

### Strengths

- The fix removes the root cause. `index()` now sends the stored values of both keys to `editSetting()` (`client_translate_expert.php:50-53`).
- The change is small. It replaces two `isset` blocks with four lines and touches no other code path.
- The code ignores a POST value for the two keys. A crafted settings POST can no longer change the counter or the reset month.
- The code uses a plain ternary, not `??`. It is outside the `OC4_EVENT` markers and it stays PHP 5.6 compatible.
- Both keys are already in the fixed lists at `build.sh:127` and `build.sh:394`. The build needs no new entry for OC 3.0 and 4.x.
- The fallback values are safe. A stored count of `"0"` gives `0`. `date('m')` gives `"01"` to `"12"`, so a stored month is never falsy.
- The other writers (`resetCharCount()`, `storeTranslatedCharCount()`, `resetCharCountOnStartMonth()`) stay unchanged. The manual reset and the monthly reset work as before.
- The comment at `client_translate_expert.php:48-49` gives the reason for the code, not only its function.
- The change has no effect on templates, language files, `install.php` or Google API character usage.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. `client_translate_expert.php:50-62`: a concurrent translation can lose its count.
   - `index()` reads the count from the config. OpenCart loads the config at the start of the request.
   - `editSetting()` deletes all rows of the group and inserts them again.
   - If a translation request writes the count with `editSettingValue()` between these two moments, the save writes the older count back.
   - The effect is a small undercount, not a reset to 0. The interval is milliseconds. Before the fix, each save lost the full count, so this is a clear improvement.
   - A possible fix is to remove the two keys from the POST and to write the other keys one at a time. This is a larger change than the issue needs. The reviewer recommends no action in this task.

2. No test covers the change, and the issue says that nobody reproduced the bug in a running store.
   - The plan gives the reason: no test harness covers the admin controller. The reviewer accepts this reason.
   - Before the merge, do the three steps from the issue section "Verification" in one store. The best store is OC 3.0 or 4.x, because these packages use the `module_` prefix. After the save, the count must stay more than 0.

3. `client_translate_expert.tpl:76` and `client_translate_expert.oc4.twig:76`: the `<div>` has a `name` attribute. A `<div>` is not a form field, so the attribute has no function. It caused the wrong assumption behind this bug. Remove it in a later cleanup, in both templates. This is not part of the diff.

### Assessment

**Ready to merge?** Yes.

The diff matches the plan and fixes both keys on all OpenCart versions without new syntax, routes, setting keys or strings. The remaining items are a narrow race that existed before the fix in a worse form, and a manual check in one store.
