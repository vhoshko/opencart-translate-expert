# Review: 59-json-analize-localization

Reviewer: Fable 5.1. Range: `git diff HEAD` on `feat/59-json-analize-localization` (uncommitted, version 04.01.0186).

Short names: CTRL, LOC, JS, TPL, TWIG, LANG as in the plan.

Checks that I ran:

- `php tests/test_client_translate_expert_localization.php`: 1021 passed. `php tests/test_oc4_twig_filters.php`: 29 passed. `php tests/test_client_translate_expert_i18n.php`: 34 passed. `node tests/js/test_client_translate_expert_js_helpers.js`: 543 passed. `php -l` on LOC and CTRL: clean.
- Key parity of the 3 LANG files: equal key sets. Each new `te_loc_*` key has a user in CTRL, JS or the templates. No removed key has a user left in `sources/` or `build.sh` (`grep -rnw`).
- ES5: no `=>`, `let`, `const` or template literal in the JS. CORE and `build.sh` are unchanged. `getLangImgUrl` is unchanged.
- Round-trip probe: `docs/tasks/59-json-analize-localization/tmp/probe_roundtrip.js` runs the real `teLocUnescape`, `teLocQuote`, `teLocDecodeEntities` and `teLocBuildFile` in jsdom on 15 cases. `probe_roundtrip.php` applies the OpenCart request clean (`htmlspecialchars` with `ENT_COMPAT`), the CTRL `html_entity_decode`, `php -l`, LOC `parseLocalizationFile` and an `include`. Run: `NODE_PATH=tests/js/node_modules node docs/tasks/59-json-analize-localization/tmp/probe_roundtrip.js && php docs/tasks/59-json-analize-localization/tmp/probe_roundtrip.php`. Results: 13 cases pass. 2 cases show the Important findings 1 and 2 below.
- OpenCart core evidence: `gh api` raw fetch of `upload/catalog/language/en-gb/checkout/checkout.php` at tag `3.0.3.8`, line 8: `'Step %s: Account &amp; Billing Details'`. GitHub code search on `opencart/opencart`: `&amp;` in 3 admin language files, `&lt;`, `&nbsp;` and `?>` in 0 language files.

## Strengths

- The LOC response matches the plan to the letter: `success`, `done`, `targetExists` and `expression` are `0` or `1`, `nextFileIndex` and the totals are ints, `totals` is absent for a later batch, and a file item has exactly 8 fields. `buildAnalizeFileItem` (LOC:248-266) is small and readable. The 3 HTML builders are gone.
- CTRL `analizeLocalization` (CTRL:623-636) casts the 3 ints, rejects a non-positive language id with `badFilterResponse()`, floors `nextFileIndex` at 0 and writes ints to the session. The PHP 8 `TypeError` of `array_slice` on a non-numeric index is gone.
- XSS discipline is complete. Every server or Google value reaches the DOM through `teEl` text, `teText`, a jQuery attribute or a text node: the file label, the key names, the source text, the expression `fullText`, the root name, the alert messages, the progress counters and log, and the highlighted source (`teLocRenderSource`, JS:2124-2151, builds a fragment of text nodes and `mark` elements). `teLocDecodeEntities` (JS:2024-2028) uses a detached textarea, and the JS test shows that a `<script>` or `onerror` in the Google text does not run.
- The analysis loop (JS:2289-2334) has the 4 branches in the planned order, and the JS test has one check for each branch: done, continue, stop, `success` 0, `.fail` with a jqXHR, non-JSON, no growth. The rows stay after an error. The two scopes have separate state and the test shows the isolation.
- The file builder starts from the server `contentTo` on each save, so a second save does not duplicate statements (JS test "the same input gives the same output"). Expression keys keep their `fullText`. The probe shows that single-quoted escapes, double-quoted escapes, `$`, braces, a `?>` inside a new value, multi-line expressions, keys with `-` and `.`, Cyrillic and emoji all round-trip through the request clean, `html_entity_decode`, `php -l`, `parseLocalizationFile` and `include`.
- The PHP tests are real: `testBatching` writes 12 files and checks the slice, the absent `totals` key (`property_exists`), the string index, the negative index and the index past the end. `testAnalysisErrors` checks 3003, 3002 for each side, the error shape and 3001 with a third language. `testConcatenatedValueWithoutTarget` checks the totals delta of an expression file.
- TPL and TWIG have the same structure with the BS3 and BS5 button classes. The `js_const_*` lines are removed from both script blocks. The CSS removes the old rules and keeps every new rule under `.te-app`.
- The i18n is complete: 26 new keys in all 3 LANG files, `tab_localization` and `tab_admin_localization` renamed, 16 dead keys removed after a word-boundary grep.

## Issues

### Critical (must fix)

None.

### Important (should fix)

#### 1. `teLocDecodeEntities` decodes entities that the source value holds on purpose

JS:2024-2028 and JS:2380. The JS sends the source text to Google with `format: 'html'` and decodes every entity of the answer. Google keeps the entities of the input. So an entity that the source file holds on purpose becomes a raw character in the target file.

Failure scenarios:

- The module's own `admin/language/en-gb/extension/module/client_translate_expert.php:33` holds `<code>... "&lt;link&gt;"</code>`. A store admin translates the Admin files to a new language. The saved file holds `"<link>"`. The browser renders a `link` element, and the help text loses the placeholder.
- OC 3.0.3.8 `catalog/language/en-gb/checkout/checkout.php:8` holds `Account &amp; Billing Details`. The saved file holds `Account & Billing Details`. This renders the same, but the value form changes without a reason.
- A source `&nbsp;` becomes a U+00A0 character in the PHP file (probe case "html and entities in text"). An editor does not show the difference.

The old flow kept the Google text as it was, so this is a regression of the value form. CORE has the rule for the DB flow: `decodeHtml` and `encodeHtml` (CORE:637-667) keep the entity form of the original when the original holds `&lt;`, `&amp;`, `&quot;` or `&#39;`.

Fix: in `teLocTranslateFile`, decode the Google text only when the source `text` holds no entity. For example: `var keep = /&[#\w]+;/.test(k.text); value = keep ? data.texts[j] : teLocDecodeEntities(data.texts[j]);`. Keep the source key with each sent text (a parallel array of `keys` already exists). Add a JS test: a source with `&lt;b&gt;` and a Google answer with `&lt;b&gt;` and `&#39;` gives a textarea value with `&lt;b&gt;` as it is. Also add a case to the probe.

#### 2. `teLocBuildFile` detects the close tag with `lastIndexOf('?>')` and writes a file that does not parse

JS:2046-2052. The rule `base.lastIndexOf('?>') > base.lastIndexOf('<?php')` takes a `?>` inside a string literal as a close tag. The JS then adds `\n\n<?php\n` inside PHP mode. The result is a parse error (probe case "existing target with ?> inside a literal": `syntax error, unexpected token "<"`). The old JS had the same rule, but the old flow showed the whole file in a textarea before the save. The new flow saves without a view of the result.

Failure scenario: the target file holds `$_['help_x'] = 'Close the block with ?> on its own line';`. The user saves one new key. The module writes a file with a parse error to the live store. Every page that loads this language file stops with a fatal error. The module can make its own trigger: a new file with a value that holds `?>` saves correctly (probe case "close tag inside the value"). A later save of the same file, with a new missing key, reads this file as `contentTo` and breaks it.

A second edge of the same lines: a target file that holds only whitespace gives `head = '\n\n<?php\n'`. The file starts with 2 blank lines, and PHP sends them to the browser before the headers.

OC core language files hold no `?>` in a value (GitHub search: 0 hits), so the trigger is rare. The result is a broken store page, so the fix is cheap and worth it.

Fix: trim first and test the end of the file only. For example:

```js
var trimmed = base.replace(/\s*$/, '');
if (trimmed === '') {
	head = '<?php\n';
} else if (trimmed.indexOf('<?php') === -1 || /\?>$/.test(trimmed)) {
	head = trimmed + '\n\n<?php\n';
} else {
	head = trimmed + '\n\n';
}
```

A language file that ends in HTML after a `?>` does not exist in practice, so a `?>` at the end of the trimmed text is the only close tag that matters. Add 2 JS tests: a `?>` inside a literal gives no second `<?php`, and a whitespace-only target gives `<?php\n` first. Add the 2 cases to the probe.

#### 3. "Translate all" sends saved files to Google a second time

JS:2418-2431. The queue holds every file with keys. The `saved` flag is not read. After one "Translate all" run, the button stays visible and enabled (`teLocEndRun`, JS:2239). A second click sends every file to Google again and saves the same content again. With `needToTranslateCharCount` of 18 000 characters (the plan example), each extra click costs 18 000 characters. The old flow had the same behavior, but the new state knows `saved`.

Fix: skip `state.files[i].saved` in the queue of `teLocTranslateAllStart`. In `teLocEndRun`, hide "Translate all" when no unsaved file with keys remains (extend `teLocHasKeys` with a `!saved` condition). Add a JS test: after a run, the second click sends no request.

### Minor (nice to have)

#### 4. The save handler reads the new state after a save that overlaps a new analysis

JS:2529. `teLocSaveFile` guards the badge update with `teLocState[scope] === state` (JS:2406). The click handler then reads `teLocState[scope].files[index].pathTo`. "Save file" and "Analyze files" are both enabled at the same time (a single save does not set `running`). If the user clicks Save and then Analyze before the answer, `files[index]` of the new state can be undefined and the handler throws a `TypeError`. The file is saved, so the harm is a missing success alert and a console error. Fix: let `teLocSaveFile` pass the file to `done(saved, file)`, or capture `file.pathTo` before the request. The guard at JS:2406 is proportionate for a case that nobody has observed. Keep it as it is.

#### 5. The per-file buttons are enabled during the analysis

JS:2215-2219 and JS:2178-2179. `teLocSetBusy(scope, true)` runs before the rows exist. `teLocAppendFile` adds "Translate file" and "Save file" enabled. The click handlers return at `teLocIsRunning`, so nothing breaks, but the buttons look active. Fix: in `teLocRenderDiff`, add `'disabled': state.running ? 'disabled' : null` to the 2 `teSiteButton` calls. `teEl` skips a `null` attribute.

#### 6. `teLocUnescape` for a double-quoted literal misses the rare PHP escapes

JS:1997. The regex handles `\" \\ \n \t \r \$`. PHP also has `\v`, `\e`, `\f`, `\0`-`\777`, `\xHH` and `\u{...}`. A double-quoted language value with one of these is rare. The source text that goes to Google then holds the escape as text. No file breaks. Add the cases when a user reports one.

#### 7. The char total counts the raw literal, the JS sends the unescaped text

LOC:286. `calcAnalizeTotals` counts `html_entity_decode(trim($text))` of the raw literal content. The JS sends `teLocUnescape(text)`. A `\'` counts as 2 characters in the total and as 1 for Google. The total is a small overestimate. This existed before this task. Fix only together with a `stripslashes`-like step, or leave it.

#### 8. Cosmetic items

- JS:2443: `teT('te_loc_translating', { path: '' })` gives the title "Translation: " for a moment before the first file. `teLocTranslateAllNext` replaces it at once. A neutral start title avoids the empty placeholder.
- `tests/test_client_translate_expert_localization.php:409`: `checkResponseShape` runs for the admin analysis only. One more call in `testCatalogAnalysis` costs one line.
- JS:2124-2151: when the source file holds the same statement text 2 times, the mark goes on the first one. The parser keeps the last one. This is a highlight only.

## Assessment

**Ready to merge?** With fixes.

Findings 1 and 2 need a fix before the merge. Both are in `teLocTranslateFile` and `teLocBuildFile`, both have a concrete trigger that the probe shows, and both change the content that the module writes to a live store. Finding 3 is a cheap guard of the Google character usage. The server side, the templates, the i18n, the XSS discipline and the tests are clean. The probe files under `docs/tasks/59-json-analize-localization/tmp/` are ready for the fix pass: add the new cases there and rerun.
