# 77-library-folders-google-client — review

Scope: `git diff HEAD` on branch `refactor/77-library-folders-google-client` (base `origin/main` 4f162b6, version 04.01.0211), plus the untracked tests `tests/test_translate_expert_core_translate.php` and `tests/test_translate_expert_library_paths.php`.

Probe scripts (all in `docs/tasks/77-library-folders-google-client/tmp/`):

- `mutate_core_translate.sh`: 11 mutations of temp copies of the library, then a run of the core translate test.
- `mutate_path_test.sh`: 4 broken include paths in a temp copy of `sources/`, then a run of the path test.
- `pipefail_probe.sh`: the `printf | grep -q` form of `check_package` under `set -euo pipefail` with a large listing.

## Strengths

- Behavior equivalence of `translateInternal()` holds. I compared `origin/main:sources/upload/system/library/vh_google_translator.php` and the old core with the new code:
  - String input: the old client mapped the codes, called `translateInternal()` and applied `fixFormatParameters($text, $res)` to the same `$text`. The new core does the same steps in the same order (`translate_expert_core.php:518-530`).
  - Array input: the old client applied the fix to `$toTranslateTexts[$pos]`, which is the original text after `array_values()`. The new core uses `array_values($text)` and the same `isTranslatableText()` guard. Thus the same positions get the same fix, and `''`, blank, `null` and `"0"` stay unchanged.
  - The result object shapes (`success`, `text` / `texts`) and the error codes 1000 and 1004 do not change. An exception in the client still skips the fix and goes to the same `catch` blocks.
  - The mapping applies to source and target, also for `$source === ''` (no match, `''` stays). It runs before the client call. The comparison stays loose (`==`), as before.
  - The text for the format fix is the text after `trimValue()` in `translate_expert.php:20`, as before. Production batch callers pass lists (`trimValue()` at `translate_expert_core.php:606-618` rebuilds a list), so the `array_values()` keys match.
- All include paths resolve. A probe of every `library/...php` string in `sources/` (PHP and XML, including the `i18n` file name with digits) finds 7 distinct targets, and all exist. The OCMOD XML (`:13`, `:16`, `:35`), the OC4 controller blocks (`:1026`, `:1042`), the model, the catalog cron and the 3 library includes all use `translate_expert/translate_expert_*.php`.
- `build.sh`:
  - The single `mv` of the folder (`build.sh:221`) is correct. The implementation keeps `mkdir -p "$EXT_DIR/system/library"` and does not make `translate_expert/` first. This deviation from plan step 5 is correct: with the target folder present, `mv` puts the folder inside it (`translate_expert/translate_expert/`).
  - `new TranslateExpert(` cannot match `new TranslateExpertLocalization(`, because `(` follows the name directly.
  - The controller rewrite `ControllerExtensionModuleClientTranslateExpert` and the route rewrite `extension/module/client_translate_expert` do not touch the FQCNs or the `client_translate_expert/system/library/...` paths. The OC 2.2 rewrites (`extension/module/`, `extension_module`) do not touch any library path.
  - In OC4, `TranslateExpertGoogleClient` and `TranslateExpertCore` resolve inside the shared namespace, so no `new X(` rewrite is necessary for them.
- PHP 5.6: the changed library code adds only `protected` and `protected static` methods, `self::` calls and short arrays. I found no PHP 7+ syntax. `php -l` (8.2) is clean on all changed PHP files.
- The core test kills 10 of 11 mutations: mapping removed for source or target, one code dropped, fix at the wrong position, no array fix, no string fix, no guard, 2 changes of the empty-text rule, and a removed `s` parameter. The empty-text rule also has direct tests in the client test (`"0"`, `null`, blank, no request when nothing is translatable).
- Docs: no line is lost in the diffs of `CLAUDE.md` and `.claude/**`. Each removed line has a replacement. `ote-build-test.md` now also lists the localization test, which was missing before. No old name remains outside `docs/tasks/**`, except the intended README note and the check patterns in `build.sh` and the path test. The changed `CLAUDE.md` and `.claude/**` lines follow STE.

## Issues

### Critical (must fix)

None.

### Important (should fix)

1. **The static path test passes when an include path has a wrong prefix.** `tests/test_translate_expert_library_paths.php:61`
   - What is wrong: the regex `translate_expert/(translate_expert[a-z_0-9]*\.php)` checks only the file name after `translate_expert/`. It does not check the part before it. A path without the folder (`library/translate_expert_i18n.php`) does not match the regex at all. The `$references >= 8` check (`:68`) does not see one lost path, because there are 20 references.
   - Proof: `mutate_path_test.sh` breaks one path in a copy of `sources/`. 3 of 4 mutations survive:
     - `xml_flat_i18n`: XML path `library/translate_expert_i18n.php`
     - `xml_no_library`: XML path `translate_expert/translate_expert_i18n.php`
     - `oc4_wrong_root`: OC4 controller path `client_translate_expert/system/translate_expert/...`
   - Only a wrong file name (`typo_name`) fails the test.
   - Why it matters: the OCMOD and vQmod header ops guard each include with `is_file()`. A wrong XML path removes the header items silently on all OC 1.5 to 3.0 stores. `check_package` also does not check the paths, so no check catches this error. This test exists to catch it (plan step 9, "❓ unverified" assumption on the `is_file` skip).
   - Fix:
     - Match the full include expression. For `DIR_SYSTEM . '<p>'`, require that `sources/upload/system/<p>` exists. For `DIR_EXTENSION . 'client_translate_expert/system/<p>'`, require that `sources/upload/system/<p>` exists.
     - Fail on any `library/translate_expert_[a-z0-9_]+\.php` without the folder.
     - Replace `>= 8` with a check for each file. For example, expect exactly 2 `DIR_SYSTEM` code paths in the OCMOD XML (`:16` route filter, `:35` i18n). Line 13 is a comment.

### Minor (nice to have)

2. **`check_package` pipes give wrong results under `set -euo pipefail` for a large listing.** `build.sh:44`, `build.sh:49`, `build.sh:53`
   - What is wrong: `printf '%s\n' "$listing" | grep -qxF ...` stops to read at the first match. If the listing is larger than the pipe buffer, `printf` gets `SIGPIPE`, and `pipefail` makes the pipeline fail.
     - `:44`: a false "is missing" error, which stops the build.
     - `:49`: a missed old flat file.
     - `:53`: the same risk for `grep -rIlE | grep -q .`. Also, a `grep` read error (exit 2) counts as "no match" there.
   - Proof: `pipefail_probe.sh` with a 1.2 MB listing and the match near the top gives the false "missing" result 20 of 20 times.
   - Impact today: none. The package listings are 1.6 KB to 2.8 KB (measured on the 04.01.0211 release). The risk becomes real if `sources_external/google-cloud-translate` comes back, because `build.sh:75-78` and `:223-225` still support that folder.
   - Fix: use here-strings and no pipes:
     - `grep -qxF "${libpath}/translate_expert/${name}.php" <<< "$listing"`
     - `grep -qE "^${libpath}/(client_|vh_)[a-z_]*\.php$" <<< "$listing"`
     - `grep -rqIE "$OLD_LIB_NAMES" "$dir" --exclude-dir=google-cloud-translate`

3. **One core test check does not test what its name says.** `tests/test_translate_expert_core_translate.php:136-138`
   - What is wrong: the check "batch: skipped text with a format mark is not changed" uses `'%d'`. `isTranslatableText('%d')` is true, so the client sends `'%d'` and it is not a skipped text. The identity stub hides this. A non-blank skipped text can only be `"0"`, so plan step 9 ("a skipped text with `%д` in it") cannot be met. Line 138 sets `answers` on an object that line 139 replaces, so the line has no effect.
   - Fix: rename the check, for example "batch: a lone format mark is translated and not changed by the fix". Alternatively, remove the check. Remove line 138.

4. **A stale label in the client integration test.** `tests/test_translate_expert_google_client.php` (`testTranslateWithLanguageAlias`)
   - What is wrong: the test now sends `'uk'` (a settled decision). The method name and the 3 messages still say "ua alias". The test is now the same as `testTranslateSingle` with other text.
   - Fix: rename the method and the messages, for example `testTranslateUkTarget` and "Translate to uk returns success". The alias check is now in the core test.

5. **No core test for an array with keys.** `tests/test_translate_expert_core_translate.php`
   - What is wrong: the `no_array_values` mutation (`$textOriginal = $text;` at `translate_expert_core.php:521`) survives, because all test inputs are lists. The production callers pass lists (see Strengths), so the mutation has no effect in production today.
   - Fix (optional): add one case with string keys, for example `array('a' => 'A %s.', 'b' => '')`. Expect `texts[0]` fixed and `texts[1] === ''`.

## Assessment

**Ready to merge?** With fixes.

The refactor keeps the behavior. The paths, the class rewrites and the OC4 build are correct, and the core test is strong. Fix finding 1 before the merge: it is the only check against a silent loss of the header items on OC 1.5 to 3.0, and it does not catch a wrong path prefix. Findings 2 to 5 are small and safe to fix in the same pass.
