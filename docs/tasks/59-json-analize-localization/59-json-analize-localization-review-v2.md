# Review v2: 59-json-analize-localization

Reviewer: Fable 5.1. Range: `git diff HEAD` on `feat/59-json-analize-localization` (uncommitted, version 04.01.0187).

Scope: the fix pass after the first review. Short names: CTRL, LOC, JS, LANG as in the plan.

Checks that I ran:

- `node tests/js/test_client_translate_expert_js_helpers.js`: 569 passed. `php tests/test_client_translate_expert_localization.php`: 1077 passed.
- Round-trip probe: `probe_roundtrip.js` and `probe_roundtrip.php` under `docs/tasks/59-json-analize-localization/tmp/`. 20 cases pass, 0 failures.
- New probe `tmp/probe_unescape.js` and `tmp/probe_unescape.php`: runs the real `teLocUnescape` on 21 double-quoted PHP escapes and compares the bytes with a PHP `include` of the same literal. 17 cases match. 4 cases differ (Minor finding 1). Run: `NODE_PATH=tests/js/node_modules node docs/tasks/59-json-analize-localization/tmp/probe_unescape.js && php docs/tasks/59-json-analize-localization/tmp/probe_unescape.php`.
- New probe `tmp/probe_revert.js` (with `probe_revert_fix6_old.txt`): reverts one fix at a time in a copy of the JS, redirects `fs.readFileSync` of the test suite to the copy, and runs the suite. Fixes 1, 2, 3, 4, 5 and 6 each make at least one test fail when reverted (4, 16, 1, 2, 2 and 8 tests). Fix 8a has no test (Minor finding 2). Run: `NODE_PATH=tests/js/node_modules node docs/tasks/59-json-analize-localization/tmp/probe_revert.js`.
- CTRL:195: `te_loc_translate_all` is in the `te_i18n` list. LANG: the key exists in `en-gb`, `ru-ru` and `uk-ua`.
- `tests/test_client_translate_expert_localization.php:481`: `checkResponseShape($res, 'catalog')` runs in `testCatalogAnalysis`.

## Strengths

- Fix 1 (JS:2034-2041) follows the CORE rule: the Google answer stays verbatim when the source text holds an entity. The test covers both directions and the `&#39;` that Google adds. The probe case "source with entities keeps the Google value verbatim" shows the saved file holds `&lt;b&gt; x &amp; y &#39;z`, and the file parses.
- Fix 2 (JS:2064-2072) tests only the end of the trimmed content. A `?>` inside a literal gives no second `<?php` (probe case passes with `php -l`). A whitespace-only target starts with `<?php`. A `?>` at the end of a `//` comment also closes PHP mode in PHP, and the rule handles it right. A target with only `<?php` gives `<?php\n\n` and the lines, which is valid.
- Fix 3 (JS:2245-2252, 2263, 2451-2455) reads `saved`. After a full run, "Translate all" is hidden and a forced start sends no request. After a failed save, the file stays unsaved, so the button shows again and a new run takes only that file.
- Fix 4 (JS:2434, 2541): the handler reads the file that `teLocSaveFile` captured, so a new analysis during a save does not throw. The test "save, then a new analysis before the answer" covers it.
- Fix 5 (JS:2218-2219): the buttons render disabled while `state.running`. `teLocEndRun` runs `teLocSetBusy(scope, false)` for every result, also `error`, so the rows of an error run get enabled buttons.
- Fix 6 (JS:1994-2019) is one pass, so `\\n` stays `\n` and `\\101` stays `\101`. The probe shows a match with PHP for `\v \e \f`, octal up to 3 digits, `\400` overflow (masked to 0 like PHP), `\xZ`, `\x4`, `\u{41}`, `\u{1F600}`, `\u{10FFFF}`, `\q`, `\'`, `\$`, `\8`, a lone `\u` and a trailing `\\`.
- The reverted variants show that the new tests have teeth. No test passes by accident.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

#### 1. `teLocUnescape` reads a byte escape above `\x7F` as a code point

JS:2008-2013. PHP strings are byte strings. `"\xD0\x9F"` in a PHP file gives the 2 UTF-8 bytes of `П`. The JS gives `String.fromCharCode(0xD0)` and `String.fromCharCode(0x9F)`, which is `Ð` and a control character. The probe shows 4 diffs: `\xD0\x9F`, `\320\237`, `\xE9` and `\777` (PHP byte `ff`, JS `U+00FF`). The text sent to Google is wrong for such a value, and the saved translation is a translation of wrong text. Before the fix, the raw escape went to Google as text, so the outcome is not worse than before. No OC core language file uses a byte escape, so the trigger is rare.

Fix: collect each run of `\xHH` and `\ooo` escapes as bytes and decode the run as UTF-8. ES5 has no `TextDecoder`, but `decodeURIComponent` does the same work:

```js
// Replace the hex and octal branches with one pass over a run of byte escapes:
text = text.replace(/(?:\\(?:x[0-9A-Fa-f]{1,2}|[0-7]{1,3}))+/g, function(run) {
	var pct = run.replace(/\\x([0-9A-Fa-f]{1,2})|\\([0-7]{1,3})/g, function(m, hex, oct) {
		var b = hex ? parseInt(hex, 16) : parseInt(oct, 8) & 255;
		return '%' + (b < 16 ? '0' : '') + b.toString(16).toUpperCase();
	});
	try {
		return decodeURIComponent(pct);
	} catch (e) {
		return pct.replace(/%([0-9A-F]{2})/g, function(m, h) { return String.fromCharCode(parseInt(h, 16)); });
	}
});
```

The run must be collected before the single-escape pass, and the single-escape pass must then skip `\x` and octal. A simpler route is to keep the current one-pass regex and buffer the bytes in a closure: push each hex or octal byte to an array, and flush the array as UTF-8 when the next match is not a byte escape or at the end. Add 2 cases to the test: `\xD0\x9F` gives `П` and `\xE9` alone gives `é` (the fallback). The same `\u{D800}` is a lone surrogate in the JS and 3 bytes in PHP, but a surrogate in a language file is not a real case. Leave it.

#### 2. No test covers the start title of "Translate all"

JS:2464. `teProgress.start(ids.progress, teT('te_loc_translate_all'))` replaced the empty placeholder. The revert probe shows that no test fails when the old call comes back. The test "run: translate request of the first file, panel title with the file" reads the title after `teLocTranslateAllNext` replaced it. One check in the translate-all block before the first response, or with an empty queue, covers it. Cosmetic.

#### 3. A target that ends in HTML after a `?>` gets the statements in HTML mode

JS:2068. The first review settled this: such a language file does not exist in practice, and the end-of-file test is the only rule that cannot break a valid file. A `?>` at the end of a `//` comment with text after it falls in the same class. No action. This note records the accepted limit.

#### 4. After a failed save, a new "Translate all" sends the texts of the failed file to Google a second time

JS:2451-2455 and JS:2384-2398. The textareas of the failed file already hold the Google answer. The new run calls `teLocTranslateFile` again before the save. A user can also change a textarea between the runs, so a skip rule needs care. The cost is one file of characters per failed save. No action.

## Assessment

**Ready to merge?** Yes.

The 8 fixes close the findings of the first review. Each code fix has a test that fails without it, and the round-trip probe shows that the saved files parse. The 4 Minor items are rare inputs or a test polish. None changes the content that the module writes for a real OpenCart language file.
