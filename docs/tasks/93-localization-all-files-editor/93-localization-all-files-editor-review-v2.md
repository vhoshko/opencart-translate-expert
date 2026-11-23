# Review v2: issue #93, localization editor for all files

Scope: the changes after review v1. These are the fix of v1 Important 1 (CR/CRLF compare) and the new saved baseline (`teLocNoteApplied()`, `teLocApplySaved()`, the third parameter `applied` of `teLocBuildFile()`, the call in `teLocSaveFile()`). The topics that review v1 settled are not reviewed again.

Probe: `docs/tasks/93-localization-all-files-editor/tmp/baseline_v2.js` with the new fixture `tmp/fx/src_v2.php` (the v1 source plus a missing expression key `mexpr` and a second missing literal key `miss2`). The probe uses the real PHP analysis item, the real editor in jsdom (jQuery 3.7.1 and 1.7.1) and a PHP `include` of each saved content. Result: 72 checks pass, 0 fail. The JS suite gives 811 passed, 0 failed.

### Strengths

- `teLocNormalizeEol()` (`client_translate_expert.js:2237`) fixes v1 Important 1 at the correct point. Only the compare uses it, so the written value is not changed. The two new tests (`test_client_translate_expert_js_helpers.js:1245-1252`) cover a double-quoted `\r\n` target and a multi-line value in a CRLF file.
- The baseline is built from the same data that built the request. `teLocBuildFile()` records each written statement while it builds the content, and `teLocApplySaved()` uses exactly that content and these statements. Nothing is parsed again, so the baseline cannot differ from the POSTed file.
- The apply runs only on `success` and only if `teLocState[scope] === state` (`:2740-2749`). A failed save and a save that ends after a new analysis do not change any state.
- `teLocBuildFile()` without the third parameter has the same result as before. `teLocNoteApplied()` ignores a missing array, so the existing callers and tests do not change.
- The v1 Minor 2 problem is gone. After a save, a cleared textarea is "no change" against the saved value, not against the old value.

### Probe answers to the brief

(a) Baseline correctness. The probe does save 1, an unchanged save, edit and save 3, a failed save, a retry, save 5 and an edit of one key (save 6). Each check passes in the two jQuery versions:

- A new literal key (`miss`, `miss2`) is appended one time. The next edit replaces the appended line. It does not add a second line.
- A replaced translated key (`a`, `ml`, `inc`) and a key that the analysis did not find in `contentTo` (the appended case) keep one statement for each key.
- For the duplicate key (`dup`), the first statement and the comment stay. Only the last statement changes (`two` → `three` → `four`).
- The expression key copied from the source (`mexpr`) gets `translated` = 1, `targetExpression` = 1 and `targetText` = `''`. Later saves do not touch it.
- For each written literal, `teLocUnescape(targetText, teLocQuote(targetFullText))` gives the textarea value. The test values have `'`, `"`, `\`, `\\`, `$&`, `$1`, `$x`, LF and a CR that the textarea removes. Each `targetFullText` is in the new `contentTo`.
- The PHP `include` of each saved content gives the textarea values. The keys that the user did not edit (`b`, `c`, `dq`, `expr`, `tex`) keep their statement text.
- An edit after a save changes only the statement of the edited key (save 6 = save 5 with one statement replaced).

(b) Side effects of `translated` = 1 after a save:

- `teLocBadge()` shows "saved" first, so the badge is the same as before.
- `teLocHasUnsaved()`, the "Translate all" queue and `teLocRefreshTotal()` already skipped a file with `saved` = 1. The result is the same as before.
- `teLocHasKeys()` (`te_loc_no_files`) runs only at the end of an analysis, which makes a new state. The save has no effect on it.
- `state.totals`, the totals box, the key count cell, the source `<pre>` marks and the `te-loc-done` classes are rendered one time from the analysis. Before this change, they also did not update after a save.
- "Translate file" in the editor bar after a save: the button stays, but a click sends no request and shows nothing. The reason is that `teLocTranslateFile()` skips the keys with `translated` = 1 (`:2693`). See Minor 1. Before this change, the same click translated the keys that were missing at analysis time again. That replaced the textarea values that the user had saved, and spent Google characters again. So the new behavior is not a functional regression. The per-key translate button still works, because `onlyKey` skips the `translated` check.
- "Translate and save file" in the row after a save translates nothing and then shows "Nothing to save", or saves the textarea edits. This is correct.

(c) Test edits. The changed test at `:1297` ("a second save with no edit sends nothing") is the correct expectation for the new rule. The three restores (`f0snap` `:1292-1301`, `keysAtStart` `:1411`/`:1440`, `efSnap` `:1592-1596`) put back the analysis state. The tests after them check other mechanics (the Stop and save-error paths of "Translate all", and "Translate file" with only the missing keys on a reloaded `ef`), and these tests need keys with `translated` = 0. Each restore has a comment, and the baseline checks before each restore are real assertions. So the restores hide no problem. The `f0snap` restore is not necessary, because no later check uses `f0`. It does no harm.

(d) Fail path and race. A save with `success` 0 and a save with an XHR reject do not change `keys`, `contentTo` or `targetExists`. A retry sends the same content. A save that ends after a new analysis does not change the new state. The probe checks each of these items. The JS suite does not assert them (see Minor 2).

(e) The real PHP round trip (save, edit, save again, `include`) passes for all values. See (a).

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. `sources/upload/admin/view/javascript/client_translate_expert.js:2744-2748` (`teLocSaveFile()`): after a save, the file can have no key with `translated` = 0. But the editor-bar "Translate file" button (`:2459`) and the row "Translate and save file" button (`:2405`) stay. A click on "Translate file" then does nothing and shows no message. The plan rule at plan line 62 says that a fully translated file has neither button. Also the key count cell (`:2401`) keeps the count of the analysis.
   - Fix (optional): if `teLocTodoCount(file) === 0` after `teLocApplySaved()`, remove `.te-js-loc-translate` from the detail row and `.te-js-loc-translate-save` from the file row, and set the count cell to 0. Another fix: if `teLocTranslateFile()` has no text, show an info alert. No change is also acceptable, because the click is harmless.
2. `tests/js/test_client_translate_expert_js_helpers.js:1310-1312` and `:1325-1337`: the fail test and the race test check the alert, the badge and "no exception" only. They do not assert that the baseline stays unchanged. The code is correct (the probe checks it). But a mutation that moves `teLocApplySaved()` before the `success` check, or out of the `teLocState[scope] === state` guard, does not fail a test. Add 2 assertions: after `success: 0`, `file.contentTo` and `JSON.stringify(file.keys)` are the same as before. After the race, the old file object has `saved` = 0 or the new state has no change.
3. `sources/upload/admin/view/javascript/client_translate_expert.js:2862-2914`: each button disables only itself. If the user clicks "Save file" and then "Translate and save file" of the same file before the first answer, two saves are in flight. Both start from the same baseline. The baseline then comes from the answer that arrives last, which is not always the file that the server wrote last. This was possible before this change, and it needs two fast clicks on different buttons. A later save rewrites the file from the textareas, so the risk is low. No change is necessary. If the team wants a fix, disable the 3 buttons of the file index while a save of this file is in flight.

### Assessment

**Ready to merge?** Yes. The CR/CRLF compare fix and the saved baseline are correct for each written-key case. The probe checks them with a real PHP round trip over several save and edit cycles, and checks the fail and race paths. The remaining items are UI polish and test hardening.
