# 50-module-language-keys

## Settings

- Planning model: Opus 5.5 (session model, inline plan).
- Review model: Sonnet 5.5 (pass `model: sonnet` to `ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: not agreed (the workflow does not commit).
- Auto-approve for review findings: not granted. Ask the user for each finding.
- Branch `fix/50-module-language-keys` from `origin/main` (`a684ee8`, PR #48 merged). On user request, the session reset the old uncommitted changes. A backup is in the session scratchpad (`reset-backup/`).

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/50

The admin localization tab (#37, PR #48) shows `1 values not translated` for the module's own admin language file. The cause is the key `Error_name` in en-gb. The ru-ru and uk-ua files use `error_name`. The issue also lists 7 unused keys, dead `error_name` code and 6 hard-coded English UI strings. The language files, the templates, the JS and the library are in all packages, so the change affects all OC versions (1.5 to 4.x).

Investigation: the Explore agent was skipped. The issue gives exact lines, and greps verified each claim:

- `grep -rnwF` for each of the 7 keys in `sources/`, `build.sh` and `tests/`, with language files excluded: no reference.
- `error_name` reads: `admin/controller/extension/module/client_translate_expert.php:216-220`, `client_translate_expert.tpl:61-62`, `client_translate_expert.oc4.twig:61-62`. No code sets `$this->error['name']`.
- JS strings pattern: the controller sets `$data['js_const_*'] = $this->language->get('js_const_*')` (`controller:160-163`). The templates write `document.js_const_* = "..."` (`.tpl:418-424`, `.oc4.twig:409-415`).
- Hard-coded strings: `client_translate_expert.js:104` (`'Saved!'`), `:157`, `:171`, `:716` (`'Preparing detail info ...'`). `system/library/client_localization_translate_expert.php:330-331` (`Translate` and `Save` buttons). The library reads strings with `$this->_model->language->get()` (`:283-284`).
- Test stub `FakeLanguage::get()` returns the key (`tests/test_client_translate_expert_localization.php:141-145`).

## Approach

Remove the 7 unused keys from the three language files and remove the dead `error_name` code. Add 6 new keys to the three language files. Pass the 4 JS strings through the controller and the templates as `document.js_const_*` values, as the existing strings do. The library reads the 2 button labels with `language->get()` and escapes them with `htmlspecialchars()`. Add a test that loads the three real module language files and fails if the key sets differ.

New keys (the `button_localization_*` names do not collide with the OpenCart core `button_save`):

| Key | en-gb |
|---|---|
| `js_const_saved` | `Saved!` |
| `js_const_localization_translate_progress` | `Translation of localization file {currIndex} of {count}: {path}.` |
| `js_const_localization_translate_done` | `Translation of localization file {currIndex} of {count}: Done!` |
| `js_const_preparing_detail_info` | `Preparing detail info ...` |
| `button_localization_translate` | `Translate` |
| `button_localization_save` | `Save` |

Extra finding (not in the issue): en-gb `js_const_statistic_html` (`:143`) contains the Ukrainian word `Таблиця` in place of `Table`. The plan fixes this word too, if the user agrees.

## Assumptions

- ✅ verified: the 7 keys have no dynamic access. The only dynamic `get()` is `'value_analization_mode_'.$mode` (`client_translate_expert_core.php:108`), per the issue. A grep for `language->get(\$` and `get('...'.` confirms this in Step 1.
- ✅ verified: the `analizeLocalization` request loads the module language file. The library already reads module keys there (`client_localization_translate_expert.php:28`, `:283`).
- ✅ verified: the 4 JS strings run only on the module page, where the template sets `document.js_const_*`. The header JS gives only global functions. The tab buttons and the analysis call them.
- ❓ unverified: no live OC 4.x run of the localization analysis. The new key-set test covers the root cause statically. If the analysis compares something else, the line can still show a count.

## Steps

- [x] Code: remove 7 keys from en-gb, ru-ru, uk-ua. Remove the dead `error_name` block in the controller, the `.tpl` and the `.oc4.twig`.
  Findings: each language file now has 135 keys. A grep finds no `error_name` or `Error_name` outside the language files.
- [x] Code: add the 6 keys to the three language files. Set the 4 `js_const_*` values in the controller. Write them in the `.tpl` and the `.oc4.twig`. Use them in the JS. Use the 2 button keys in the library.
  Findings: the new keys follow `js_const_analization_process_is_started` in each file. The `.oc4.twig` uses `|raw`, as the other `js_const_*` lines do. No new string has a double quote, so the JS string literals stay valid. No `build.sh` rewrite list needs a change (no new AJAX method, library class or setting key).
- [x] Extra: replace `Таблиця` with `Table` in en-gb `js_const_statistic_html` (the user agreed).
- [x] Test: add a key-set check to `tests/test_client_translate_expert_localization.php`. Prove that it fails on the old files (mutation check).
  Findings: the test came first. On the old files it gave 4 failures (`missing: Error_name`, `extra: error_name` for ru-ru and uk-ua). After the fix: 371 passed, 0 failed. The check runs in the parent process, once, not for each OC version.
- [x] Bump version + build + test
  Findings: 04.01.0170 → 04.01.0171. Build green. All 6 test scripts pass (localization: 371). The 6 new keys are in the oc1.5, oc2.2, oc3.0 and oc4.x packages. No package contains `error_name`.
- [x] Independent review → 50-module-language-keys-review.md
  Findings: Sonnet 5.5. Ready to merge. No Critical or Important findings. 2 Minor findings.
- [x] Address review findings (the user picks which to fix or skip)
  Findings: Minor 1 (the test does not compare `{placeholders}` in values): the user chose skip. Minor 2 (`htmlspecialchars()` guard): no action necessary.
- [x] ~~Bump version + build + tests (verify loop)~~: not necessary (no fixes).
- [x] ~~Re-review?~~: not necessary (no unreviewed fixes). (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → -review-v2.md, v3, …)
- [x] Improvements pass → 50-module-language-keys-improvements.md

## Done

- Landed: the three module language files have the same 135 keys. `Error_name` and 6 other unused keys are gone. The dead `error_name` code is gone. The 4 JS strings and the 2 localization buttons use new language keys in en-gb, ru-ru and uk-ua. en-gb `js_const_statistic_html` says `Table`.
- Test: `checkModuleLanguageKeySets()` in `tests/test_client_translate_expert_localization.php` fails if the key sets differ. Localization tests: 371 passed.
- Version: 04.01.0171. Release: `releases/v04.01.0171/`.
- Not verified: a live run of the admin localization analysis on an OC 4.x store.
- Deferred: a `{placeholders}` check across languages (review Minor 1, skipped by the user).
