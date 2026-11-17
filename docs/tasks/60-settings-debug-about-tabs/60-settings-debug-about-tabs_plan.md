# 60-settings-debug-about-tabs

## Settings

- Planning model: Opus (session model). Review model: Opus (the user chose it, same as the pin of `ote-code-reviewer`).
- Build, tests, commit, push and PR: yes. The user chose the end-to-end flow with a PR.
- Auto-approve: yes. Decide review findings and the re-review gate without a question. Apply the improvements to `.claude/**`.
- Investigation: done inline with greps and file reads. The task changes templates, CSS and language files only.

## Context

- Issue: https://github.com/vhoshko/opencart-translate-expert/issues/60 (depends on #59, merged as #65).
- PoC: `docs/pocs/2-admin-design/design-concept.md`, sections 5, 6, 9 and 10.
- Affected OC versions: all (1.5–4.1). `.tpl` for 1.5–3.0 and `.oc4.twig` for 4.x.
- Key files:
  - `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl` (`#tab-general` line 64, `#tab-debug` line 296, `#tab-about` line 367)
  - `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig` (lines 64, 294, 360)
  - `sources/upload/admin/controller/extension/module/client_translate_expert.php:198` (list of `te_*` text keys for the template)
  - `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php` (tab names at line 86–92)
  - `sources/upload/admin/view/stylesheet/client_translate_expert.css` (`te-card`, `te-form`, `te-field` exist)
- JS depends on these ids. Keep them: `input-client_translate_expert_key`, `button-client_translate_expert_key_toggle`, `te-cron-secret`, `te-cron-secret-generate`, `te-cron-secret-unsaved`, `te-cron-bulk-amount`, `reset_translation_cache`.
- The `test[<id>][translation]` textareas have no JS and no server code. Keep them as they are. Only collapse them.

## Approach

Wrap each of the 3 tabs in `.te-app` and build them from `te-card`, `te-form` and `te-field`. Write the same markup in the `.tpl` and the `.oc4.twig`, with host form control classes inside the cards.
Settings gets 5 cards. The "Try a translation" card is collapsed with the existing `te-js-collapse` toggle.
Debug shows the versions as plain text in one `te-card`. The cache, the debug mode and the log get their own cards.
About puts `text_about_page` in one `te-card`.
New card titles are new `te_*` language keys in 3 languages. The controller passes them to the template.
Tab names get shorter values. The keys stay.

## Assumptions

- ✅ verified: no new route, library class or setting key. No change to `build.sh`. (Only templates, language strings and one controller key list.)
- ✅ verified: the existing JS uses ids and not the layout (grep of `te-cron-*` and key toggle). Keeping the ids keeps the JS working.
- ✅ verified: `.te-collapse-toggle` and `.te-js-collapse` are global handlers under `.te-app` (`client_translate_expert.js:1264`).
- ❓ unverified: the visual result on a real store. No browser is available in the container. The markup copies the tab that #64 shipped.

## Steps

- [x] Language strings: new card titles, shorter tab names (en-gb, ru-ru, uk-ua)
- [x] Controller: pass the new `te_*` keys to the template
- [x] Templates: `.tpl` and `.oc4.twig` for Settings, Debug and About
- [x] CSS: small additions if needed (plain text version row, log textarea)
- [x] PoC: add a "Status: done in issue 60" note to sections 6, 9 and 10
- [x] Bump version + build + test
- [x] Independent review → 60-settings-debug-about-tabs-review.md
- [x] Address review findings (auto-approve)
- [x] Bump version + build + tests (verify loop)
- [x] Re-review? (auto-approve: decide)
- [x] Improvements pass → 60-settings-debug-about-tabs-improvements.md

## Done

- Settings, Debug and About tabs now use `te-card` blocks in the `.tpl` and the `.oc4.twig`. Tab names are shorter. Final version: 04.01.0196.
- Review (Opus): 0 critical, 0 important, 6 minor. Fixed: the lost `(int)` and `htmlspecialchars()` in the `.tpl`, the `te-radio-group` class, a dead CSS rule and the About card title in ru-ru and uk-ua. Skipped: the unused `entry_try_translate` load and the select indentation.
- No re-review: the fixes are small and the build is green.
- Deferred: the "Reset cache" success message (README step 6). No visual check on a real store.
