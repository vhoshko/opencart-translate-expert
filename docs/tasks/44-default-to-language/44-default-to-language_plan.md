# 44-default-to-language

## Settings

- Flow: stripped-down (2 template files, under 10 lines, no schema or build-script impact).
- Planning model: Opus 5.5 (session model, inline).
- Review model: Sonnet (`model: sonnet` on `ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: no (the workflow never commits).
- Standing auto-approve for review findings: no.
- The user rejected the setup question dialog and said "continue". The agent then used the recommended defaults above.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/44

The analysis tab and the Localization files tab each have a "from" select and a "to" select. The templates mark an option as selected only if it matches `te_langIdFrom` or `te_langIdTo`. The controller sets these values from the session, or `null` (`admin/controller/extension/module/client_translate_expert.php:324-325`). With no session value, the browser shows the first language in both selects. Then "from" and "to" are the same language.

The user said: "it should be only FE change". The controller does not change.

Affected OC versions: all (`.tpl` for OC 1.5–3.0, `.oc4.twig` for OC 4.x).

Key lines:

- `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:158-161` (analysis "to"), `:256-259` (Localization files "to")
- `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig:158-161`, `:252-255`
- `sources/upload/admin/view/javascript/client_translate_expert.js:1285-1286`: the cron link builder reads the select values from the DOM. A template default thus also goes into the cron link.

Investigation agent skipped: 3 greps (`te_langId`, `language-to|language-from` in the templates and the JS) found all usages.

## Approach

Change only the two "to" selects in each template. If `te_langIdTo` is empty, mark the option at loop position 1 (the second language) as selected. If `te_langIdTo` has a value, keep the current match. The "from" selects do not change, so the browser keeps the first language as "from".

`getLanguages()` returns an array with language codes as keys, so the `.tpl` uses its own counter. The `.oc4.twig` uses `loop.index0`.

Open questions from the issue, with the decisions:

- One-language store: "to" shows the only language. No other choice exists.
- Both tabs keep the shared session keys `te_langIdFrom` and `te_langIdTo`. No per-tab state.
- The default comes from the template, not from the controller (user decision).

## Assumptions

- ✅ verified: `te_langIdTo` is `null` when the session has no value (`client_translate_expert.php:325`).
- ✅ verified: the cron link and the AJAX actions read `$('#te-...-language-to').val()` from the DOM (`client_translate_expert.js:796`, `:854`, `:1286`). The new default reaches them with no JS change.
- ❓ unverified: the browser behavior is not seen in a real store. No browser exists in the container. If the assumption is false, the `selected` attribute still sets the option, so the risk is low.

## Steps

- [x] Templates: .tpl and .oc4.twig, both "to" selects
  Findings: the `.tpl` uses a `$te_langIndex` counter because `getLanguages()` has code keys. The `.oc4.twig` uses `loop.index0` and `is empty`. In `empty(...) ? a == 1 : b == c`, PHP gives `==` a higher precedence than `?:`, so the condition is correct on PHP 5.6. The probe `tmp/probe.php` shows: no session value → second language, a stored value → that language, a one-language store → no `selected` attribute, so the browser shows the only language. `php tests/test_oc4_twig_filters.php`: 29 passed.
- [x] Bump version + build + test
  Findings: 04.01.0164 → 04.01.0165. `./build.release.sh` passed. Tests: 393 passed, 0 failed, 6 skipped (integration tests, no key). The oc1.5 and oc3.0 packages contain `te_langIndex`. The oc4.x package contains `loop.index0 == 1`.
- [x] Independent review → 44-default-to-language-review.md
  Findings: Sonnet review is clean. No Critical, Important or Minor findings.
- [x] ~~Address review findings~~: not necessary (no findings).
- [x] ~~Bump version + build + tests (verify loop)~~: not necessary (no fixes).
- [x] ~~Re-review?~~: not necessary (no unreviewed fixes).
- [x] Improvements pass → 44-default-to-language-improvements.md

## Done

- Landed: if the session has no `te_langIdTo`, the "to" select on the analysis tab and on the Localization files tab selects the second language. The change is in the `.tpl` and the `.oc4.twig`. The controller and the JS do not change.
- Final version: 04.01.0165. Release: `releases/v04.01.0165/`.
- Gotcha: `getLanguages()` has code keys, so the `.tpl` needs a counter. A stored ID that is not in the list still selects nothing (unchanged behavior).
- Not done: a check in a real browser on the OC 4.0 Docker store (`en-gb` and `uk-ua`).
- Not committed. The task-36 leftovers stay uncommitted in the working tree.
