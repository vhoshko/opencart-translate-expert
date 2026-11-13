# 51-localization-concat-values

## Settings

- Planning model: Opus 5.5 (inline). Review model: Sonnet 5.5 (`model: sonnet` on `ote-code-reviewer`).
- Standing auto-approve: the user said "turn on auto approve mode, auto select recommended options". Claude decides the review findings and picks the recommended options.
- Build, tests, commit, push and PR: the user said "finish when you create PR". At the end, commit, push and open a PR to `main`.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/51

In an OC 1.5 store, the localization analysis shows `/common/footer.php: Fully translated`. The source value uses PHP concatenation (`'...' . date('Y') . '...'`). The target file does not exist. The file has no "Translate" button, the target editor is empty, and "Save" gives `saveLocalizationFile: emty parameters is not allowed`.

The bug affects all OC versions and both localization tabs (catalog and admin), because both tabs use the same parser.

Key files (all in `sources/upload/system/library/client_localization_translate_expert.php`):

- `parseLocalizationFile()` `:434-457`: two regexes that match single-literal values only.
- `analizeLocalizationFile()` `:387-412`: a key goes to `toTranslate` only if the source parse finds it.
- `analizeLocalization()` `:57`: a missing target file gives `contentTo = ''`, with no flag.
- `buildAnalizeDetailedHtml()` `:315-317`, `:331`: "Fully translated" text and the "Translate" button.
- `saveLocalizationFile()` `:86-90`: empty content gives "Internal error." and the typo "emty".
- JS `doTranslateLocalization()` (`admin/view/javascript/client_translate_expert.js:179`): sends `value.text` of each `toTranslate` entry to Google and appends `$_['key'] = "<translation>";`.

Investigation: no Explore agent. A read of the library, the JS translate and save flow and the two controller actions found all the code that the task touches.

## Approach

1. Replace the two regexes with a `token_get_all()` parser. The parser finds each top-level `$_['key'] = <expression>;` statement. `full_text` is the exact source text of the statement. If the expression is one literal string, `text` is the raw string content (same as the old regex). Otherwise, the entry gets `expression => 1` and `text => ''`. If the tokenizer extension is not available, the parser uses the old regexes.
2. Open question 1 of the issue: copy a statement with an expression unchanged into the target, for a manual edit. This is the recommended option. Google translates sentence parts badly, and the copy keeps the PHP code valid. The analysis keeps the key in `toTranslate`, so the file shows "N values not translated" and the "Translate" button. The info line also shows how many values have PHP code and need a manual check.
3. JS: `doTranslateLocalization()` appends `full_text` for an `expression` entry and sends no text to Google for it. If no text remains to translate, it does not call the `translate` action.
4. The analysis result gets `targetExists`. If the target file does not exist, the info line says so.
5. `saveLocalizationFile()`: empty or whitespace-only content gives the clear message `error_localization_save_empty` ("The file is empty. Nothing to save."). The other empty-parameter message gets the typo fix.
6. Tests in `tests/test_client_translate_expert_localization.php`.

## Assumptions

- ✅ verified: `token_get_all()` gives the issue footer statement as `T_VARIABLE $_`, `[`, `T_CONSTANT_ENCAPSED_STRING`, `]`, `=`, expression tokens, `;`. Probe `tmp/probe.php` on PHP 8.2. These token names exist with the same meaning on PHP 5.6 (tokenizer has had them since PHP 4).
- ❓ unverified: the tokenizer extension is present on all customer servers. It is enabled by default, but a host can disable it. Mitigation: a fallback to the old regexes when `token_get_all` does not exist.
- ❓ unverified, not load-bearing: the copied English statement shows in the target store until a person edits it. The fix does not depend on how OpenCart merges the default language.

## Steps

- [x] Code: tokenizer parser with regex fallback in `parseLocalizationFile()`
  Findings: the parser accepts a statement only at a top-level statement start, so `$x = $_['a'] = ...` and `$_['a']['b'] = ...` are not keys. Keys must match `[\w\-\.]+`. A statement that ends with `?>` and no `;` is skipped (OC language files always use `;`). Commented-out statements are no longer keys. The old regexes matched them.
- [x] Code: `targetExists` in the analysis, info line for a missing target and for values with PHP code
- [x] Code: `saveLocalizationFile()` clear empty-content message and typo fix
- [x] Code: JS `doTranslateLocalization()` copies `expression` statements and skips an empty Google call
- [x] Language strings: en-gb, ru-ru, uk-ua
- [x] Tests: concatenated value with no target file, literal parity, commented-out statement, empty save message
- [x] Prove the regression test fails without the fix
  Findings: mutation `if (true || !function_exists('token_get_all'))` (regex parser only). Result: 467 passed, 40 failed. The failures are "concatenated key without a target file needs attention", "concatenated key is marked as an expression", "footer has the Translate button", "info line shows the values with PHP code" and the parser keys check, in all 8 OC versions. The restore gives 587 passed. The JS change has no test harness.
- [x] Bump version + build + test
  Findings: 04.01.0172. All 6 test scripts pass (localization 587/0). The oc1.5 and oc4.x packages contain `token_get_all` and `error_localization_save_empty`.
- [x] Independent review → 51-localization-concat-values-review.md
- [x] Address review findings (auto-approve)
  Findings: verdict "ready to merge", no Critical or Important findings. Fixed M1: the statement-start check also accepts `)`, `:` and `T_ELSE`, with a test. Skipped M2 (expression values add 0 characters: correct, nothing goes to Google), M3 (missing-target note on a file with no keys: true information), M4 (no JS test harness exists, and the parity test covers the fallback parser) and M5 (reviewer marks it correct).
- [x] Bump version + build + tests (verify loop)
  Findings: 04.01.0173. All 6 test scripts pass (localization 595/0).
- [x] Re-review? (auto-approve: decide by the findings)
  Findings: no re-review. The fix pass is one widened condition plus its test, and the first pass found only Minor findings.
- [x] Improvements pass → 51-localization-concat-values-improvements.md
- [x] Commit, push, PR

## Done

- Landed: a `token_get_all()` parser for language files (regex fallback), copy-unchanged handling for values with PHP code, a missing-target note in the info line, and a clear empty-content message on Save.
- Final version: 04.01.0173.
- Gotcha: an unexpected exception in the localization test stops the rest of the child run. Guard each array read that depends on the code under test.
- Deferred: translation of only the literal parts of a concatenated value (issue open question 1, the other option). No JS test harness exists for `doTranslateLocalization()`.
