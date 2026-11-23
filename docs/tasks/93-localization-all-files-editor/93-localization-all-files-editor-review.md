# Review: 93-localization-all-files-editor

Scope: `git diff HEAD` on branch `feat/93-localization-all-files-editor` (library, JS, CSS, controller i18n whitelist, 3 language files, PHP and JS tests, `CLAUDE.md`). The review skips `_version.txt` and `releases/`.

Probe scripts (keep them for the fix pass):

- `docs/tasks/93-localization-all-files-editor/tmp/analyze_item.php`: runs the real `analizeLocalizationFile()` and `buildAnalizeFileItem()` on 2 files and prints the file item as JSON.
- `docs/tasks/93-localization-all-files-editor/tmp/roundtrip.js`: real PHP item, then the real JS editor in jsdom (jQuery 3.7.1 and 1.7.1), then a click on "Save file", then a PHP `include` of the saved content.
- `docs/tasks/93-localization-all-files-editor/tmp/crlf.js`: a target file with CRLF line endings and a multi-line value, with unchanged textareas.
- Fixtures: `docs/tasks/93-localization-all-files-editor/tmp/fx/`.

Test runs: all 16 PHP test scripts pass. The JS helper tests pass (795 passed, 0 failed).

### Strengths

- The server change is additive. `analizeLocalizationFile()` keeps `toTranslate` and adds `allKeys`. `charCount` and all old totals still come from `toTranslate` only, so the Google character estimate does not change. The only caller of `analizeLocalizationFile()` is `analizeLocalization()` in the same class (`translate_expert_localization.php:62`). Nothing in `tools/`, the controller or the model reads the result shape.
- The key order is the source order, and a duplicate target key gives the last statement. This matches the PHP "last assignment wins" rule, so `lastIndexOf(targetFullText)` changes the effective statement.
- `teLocBuildFile()` uses `substring` slicing, not `String.replace`. The probe confirms that `$&`, `$'`, `` $` ``, `$1`, `$$`, backslashes and quotes go to disk literally and PHP reads them back unchanged.
- The probe round trip passes for these cases with both jQuery versions:
  - A key from an included file (inlined in the merged `contentTo`).
  - A double-quoted target with `\"`, `\$` and `\n`. The unchanged value gives no change. An edit writes a single-quoted statement.
  - A multi-line single-quoted value with LF line endings.
  - A duplicate target key with a commented copy between the 2 statements. Only the last real statement changes.
  - An expression on the source side and on the target side. Both stay byte-identical, and the editor shows read-only code.
  - A missing key appended at the end.
- All new DOM code uses `teEl()` with text, `.val()` and `document.createTextNode()`. No HTML string goes into the DOM. The filter checkbox has a `label` with `for`, and the Action column has a `th`.
- The filter is one class on the result container plus one CSS rule. Rows of later batches, detail rows (also when expanded) and root group rows follow it with no extra code. A new analysis rebuilds the checkbox unchecked. `teLocSetBusy()` does not touch it. Nothing goes to `localStorage`, `sessionStorage` or cookies.
- The 4 new strings are in en-gb, ru-ru and uk-ua (231 keys in each file) and in the `$te_i18n` whitelist. No template change is necessary, because the JS builds the table and the filter.
- PHP 5.6: the library change uses `array()`, `is_array()` and `!empty()` only. JS: only `var`, `$.isArray`, `.prop`, `.on` and `toggleClass(cls, bool)`. All are in jQuery 1.7.1. No new route or library class, so `build.sh` needs no change.
- The JS and PHP tests cover the plan list: the translated badge, the hidden buttons, the filter (later batch, root rows, busy state, reset), the in-place replace, the duplicate key, the "not found" append, the expression rule and a PHP round trip.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. `sources/upload/admin/view/javascript/client_translate_expert.js:2252-2253` (`teLocBuildFile()`): an unchanged value that contains a CR character is saved as an edit. This breaks the rule "blank or unchanged textarea = no change" and the issue rule "Do not change the keys that the user did not edit".
   - Cause: the HTML `textarea` API value normalizes CRLF and CR to LF. jsdom and browsers do this. `teLocRenderDiff()` puts `teLocUnescape(targetText, ...)` into the textarea, but `.val()` returns the value without CR. The compare at `:2253` then sees a difference.
   - Measured with the probes:
     - Target `$_['crlf'] = "r1\r\nr2";`: an unchanged editor gives a POST (no "Nothing to save"). The statement becomes `$_['crlf'] = 'r1<LF>r2';`. PHP reads `"r1\nr2"`, so the CR is lost (`roundtrip.js`).
     - A target file with CRLF line endings and a multi-line single-quoted value (`crlf.js`): unchanged textareas give a rewritten statement with LF inside a CRLF file. The result is not `null`.
   - Impact: Windows-edited language files are common. Each "Save file", "Translate and save file" and "Translate all" silently rewrites each multi-line or `\r` value of the file. The user did not edit these values.
   - Fix: normalize the line breaks on both sides before the compare, for example `function teLocNl(s) { return String(s).replace(/\r\n?/g, '\n'); }` and `teLocNl(values[k.key]) !== teLocNl(teLocUnescape(k.targetText, teLocQuote(k.targetFullText)))`. A real edit of such a value still loses the CR. This is acceptable, because the textarea cannot show a CR. Add a JS test with a CRLF multi-line target and a `"\r\n"` double-quoted target, both unchanged, and expect `null`.

#### Minor (nice to have)

1. `sources/upload/admin/view/javascript/client_translate_expert.js:2339` (`teLocBadge()`): a source file with no keys gets the "translated" badge also when the target file does not exist (`targetExists` = 0). The server counts it in `translatedFileCount` (`translate_expert_localization.php:313`). Such a file has nothing to translate, so "translated" is defensible, and "Save file" correctly reports "Nothing to save". If the team wants the issue rule "a file whose target file does not exist" to be visible, check `targetExists` before the todo count only for a file with keys. No change is also acceptable.
2. `sources/upload/admin/view/javascript/client_translate_expert.js:2238-2264`: each save builds the file again from the `contentTo` of the analysis. After a save, `contentTo`, `targetText` and `targetFullText` are stale. A second save is still correct, because all textareas keep their values. But if the user clears an edited textarea before the second save, the blank textarea means "no change" against the old value, so the file goes back to the old value. This is an edge case. A note in the code is sufficient.

### Probe answers to the brief

1. Round trip (jsdom + real PHP): passes for `$&`, `$'`, backslashes, quotes, multi-line LF values, double-quoted targets, duplicate target keys and included keys. It fails only for values with CR (Important 1).
2. Filter: correct for later batches, group rows (the class goes off when a later todo file of the same root comes, also from a later batch), detail rows and expanded rows. A new analysis resets the checkbox to off and removes `te-loc-only-todo`.
3. "Translate all": the queue skips files with no missing keys. `teLocRefreshTotal()` runs after each save and in `teLocEndRun()` (done, stopped and error), so the total state and the "Translate all" visibility are correct after Stop. `teLocSetBusy()` does not include the filter.
4. Badge order: saved > translated > missing > partial, as in the plan. `te_loc_no_files` ("All files are translated.") shows only after a completed analysis with no file that has missing keys. The list still shows all files.
5. `analizeLocalizationFile()`: one caller in the same class. No caller in the controller, the model, `tools/` or the tests depends on the old shape. The change is additive.
6. PHP 5.6, `build.sh` and jQuery 1.7.1: no problem found. The probe runs the full editor flow with jQuery 1.7.1.
7. Language files: the key sets are the same (231 each, and the PHP key set test passes). The 4 keys are in the `$te_i18n` whitelist.
8. XSS and accessibility: text nodes only. The checkbox has a label. No problem found.

### Assessment

**Ready to merge?** With fixes. The design and the in-place replace are correct, and the round trip is clean for all probed literal forms. But the textarea line-break normalization makes the module rewrite unedited values that contain a CR, which breaks a stated requirement on common Windows-edited files.
