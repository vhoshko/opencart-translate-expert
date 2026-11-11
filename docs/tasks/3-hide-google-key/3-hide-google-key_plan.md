# 3-hide-google-key

## Settings

- Planning model: Opus 5.5 (inline).
- Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: no (the workflow never commits).
- Standing auto-approve for review findings: no.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/3 ("Update to hide google key `****` in editor with `show key` button"). The issue body is empty.

The settings form shows the Google Translate API key as plain text. A person who looks at the screen or at a screenshot can read the key. The change masks the key and adds a button that shows and hides it. All OpenCart versions (1.5–4.1) are affected.

Key files:

- `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:55` — key input (OC 1.5–3.0, Bootstrap 3)
- `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig:55` — key input (OC 4.x, Bootstrap 5)
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:163` — `button_*` strings go to `$data`
- `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php` — strings

## Approach

Change the key input to `type="password"` with `autocomplete="new-password"`. Put the input in an `input-group` with a "Show key" button. A small inline jQuery handler in each template toggles the input type between `password` and `text`. The handler also toggles the button label. The button holds both labels in `data-text-show` and `data-text-hide` attributes. The save flow does not change, because the input name and value do not change.

The masking is visual only. The key stays in the HTML source, because the "show key" button must be able to show it. A server-side mask (never send the key to the browser) is out of scope. It needs a save-flow change and an AJAX call to show the key.

No change to `build.sh` is necessary. The change adds no AJAX method, library class or setting key.

## Assumptions

- ✅ verified: OC 1.5 loads Bootstrap 3, jQuery and Font Awesome on the module page. Evidence: `build.sh:115` injects `oc15/te/bootstrap.css` and `jquery-2.1.1.min.js`.
- ✅ verified: jQuery is available to inline template scripts on all versions. Evidence: both templates already call `$('#te-analization-language-to').change()` in the inline script block (`.tpl:345`, `.oc4.twig:335`).
- ❓ unverified: `autocomplete="new-password"` stops the browser password manager from autofilling the admin login password into the key field. This is standard browser behavior, but I cannot test it here. If it is false, a browser can overwrite the key on save. A manual check in Chrome after install covers it.

## Steps

- [x] Code: controller sends `button_show_key` and `button_hide_key` to `$data`
  Findings: added after `button_view` in `index()`. No `build.sh` change is necessary.
- [x] Templates: `.tpl` (Bootstrap 3 `input-group-btn`) and `.oc4.twig` (Bootstrap 5 `input-group`), plus the inline toggle handler in each
  Findings: the handler sets the native `input.type` property and does not use `.attr('type', …)`. jQuery 1.8 and earlier throw when the `type` attribute of an input changes, and OC 1.5 core loads jQuery 1.7.
- [x] Language strings: en-gb, ru-ru, uk-ua
  Findings: the strings follow `entry_server_key` in each file.
- [x] Bump version + build + test
  Findings: version 04.01.0127. `php -l` passed. Tests passed: 24 (6 integration tests skipped, no key) and 40. In the oc3.0 and oc4.x packages, the build adds the `module_` prefix to the input id, the button id and both handler selectors in the same way, so they still match.
- [x] Independent review → 3-hide-google-key-review.md
  Findings: Fable 5.2. 0 Critical, 1 Important, 3 Minor.
- [x] Address review findings (the user picks which to fix or skip)
  Findings:
  - Important 1 (password manager autofill): kept `type="password"`. The user does a manual check in Chrome and Firefox after install. The assumption stays unverified.
  - Minor 1 (handler binds after `.change()`): fixed. The binding is now above the `.change()` call in both templates.
  - Minor 2 (`aria-pressed`, `aria-controls`): skipped. The button label changes already.
  - Minor 3 (handler duplicated in both templates): no action. The reviewer agrees with the duplication, because `client_translate_expert.js` does not get the `module_` rewrite.
- [x] Bump version + build + tests (verify loop)
  Findings: version 04.01.0128. `php -l` passed. Tests passed: 24 (6 skipped) and 40. In the built oc3.0 `.tpl` and oc4.x `.twig`, the handler is above `.change()`, and both ids carry `module_`.
- [x] Re-review? Findings: the user asked for a PR and no second review pass. (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → -review-v2.md, v3, …)
- [x] Improvements pass → 3-hide-google-key-improvements.md

The regression-test step does not apply. This is a feature, and no test harness covers templates.

## Update 2026-11-11

> **Superseded.** The `type="password"` input with `autocomplete="new-password"` (see Approach) did not work with LastPass. The user saw LastPass suggest a password for the key field and offer to save the key. LastPass ignores `autocomplete`. This also confirms review finding Important 1, so the ❓ assumption about `autocomplete="new-password"` is false.

New approach (option 2, approved by the user):

- The key input is `type="text"` with the class `te-key-masked`. A `<style>` rule next to the input sets `-webkit-text-security: disc` for that class. Password managers do not treat a text input as a password field.
- The input also has `autocomplete="off"`, `spellcheck="false"`, `data-lpignore="true"`, `data-1p-ignore="true"`, `data-bwignore="true"` and `data-form-type="other"`. These are the ignore attributes of LastPass, 1Password, Bitwarden and Dashlane.
- The handler toggles the class. If `CSS.supports('-webkit-text-security', 'disc')` is false, the handler changes the input to `type="password"` and toggles the type, as before.
- ✅ verified (web source): Firefox supports `-webkit-text-security` from version 114 (https://bugzil.la/1826629). Chrome, Edge and Safari support it too.
- ❓ unverified: LastPass shows no suggestion and no save prompt for the new input. The user must check this in the browser.

Steps:

- [x] Code: both templates changed as above.
- [x] Bump version + build + tests
  Findings: version 04.01.0129. Tests passed: 24 (6 skipped) and 40. In oc3.0 and oc4.x, all ids and selectors carry `module_`. In oc1.5 and oc2.2, they stay unprefixed. The class `te-key-masked` does not change.
- [x] Push to PR #32
  Findings: the user asked for a push. No independent review of this change ran.

## Done

- The Google API key input is a text input masked with `-webkit-text-security: disc`, with password manager ignore attributes, in the `.tpl` and the `.oc4.twig`. If the CSS is not supported, it falls back to `type="password"`. A "Show key" / "Hide key" button toggles it. The strings are in en-gb, ru-ru and uk-ua.
- Final version: 04.01.0129 (04.01.0128 before the update of 2026-11-11). One review pass (Fable 5.2). One Minor fix: the handler binds before `.change()`.
- Gotcha: the `module_` rewrite in `build.sh` changes the new ids and selectors too. They still match on OC 3.0 and 4.x.
- Follow-up: do a manual check in LastPass, Chrome and Firefox that no password suggestion and no save prompt shows. The update of 2026-11-11 has no independent review.
