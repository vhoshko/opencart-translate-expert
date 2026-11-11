# 6-extension-name-icon

## Settings
- Planning model: Opus 5.5 (inline). Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: no (the workflow never commits).
- Review findings: ask the user for each finding (no standing auto-approve).

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/6 ("Update the extension name to be styled with image on the beginning"). The issue has no body.

The user scope (from chat):
- Show the icon at the start of the module name in Extensions > Modules and in the H1 of the settings page.
- Use plain text for the browser tab title, the breadcrumbs and the modal title.
- If possible, show a part of the name in color.

Affected OC versions: all (1.5, 2.0–2.3, 3.0, 4.x).

Key files:
- `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php:3` holds `heading_title` = `Translate Expert (Full)`.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:42` sets the document title from `heading_title`.
- Same controller, `:69` sets `$data['heading_title']`. The `.tpl:8` and `.oc4.twig:8` H1 and the `.tpl:325` and `.oc4.twig:316` modal titles use it.
- Same controller, `:228` and `:233` set the breadcrumb text from `heading_title`.
- `sources/upload/admin/view/image/translate_expert_client/translate-icon-32.png` is the 32x32 icon (black glyph). The JS and the core library already use it.
- `build.sh:386-390` rewrites the `view/image/translate_expert_client/` paths for the OC4 extension folder. The language files get no rewrite today.

## Approach
Put HTML in `heading_title` in the three language files. The HTML is an `<img>` of `translate-icon-32.png` with `height:1em`, then `Translate`, then `Expert` in a colored `<span>` (`#1e91cf`, the OpenCart admin blue), then `(Full)`. The image `src` is relative to the admin folder: `view/image/translate_expert_client/translate-icon-32.png`. This path works on OC 1.5–3.0 because the admin pages load from `admin/index.php`.

For OC4, add one `file_replace` per language file in `build.sh`. It changes the path to `../extension/client_translate_expert/admin/view/image/translate_expert_client/`, as the JS rewrite at `build.sh:388` does.

In the controller, add a local `$heading_title_text = strip_tags($this->language->get('heading_title'))`. Use it for `setTitle`, both breadcrumbs and a new `$data['heading_title_text']`. The modal titles in `.tpl` and `.oc4.twig` use `heading_title_text`. The H1 keeps `heading_title` with the icon and the color. `strip_tags` exists in PHP 5.6, so the change is PHP 5.6 compatible.

## Assumptions
- ✅ verified: the module list prints the name without escaping on all versions. Evidence from the OpenCart source on GitHub:
  - 1.5.6.4 `extension/module.tpl:30`, 2.0.3.1 `extension/module.tpl:45`, 2.3.0.2 `extension/extension/module.tpl:26` echo `$extension['name']` raw.
  - 3.0.3.8 `extension/extension/module.twig:29` prints `{{ extension.name }}`, and `system/library/template/twig.php:24` sets `'autoescape' => false`.
  - 4.0.2.3 and 4.1.0.0 `extension/module.twig:18` print `{{ extension.name }}`. `system/library/template/twig.php` sets `'autoescape' => false` (lines 110 and 107).
- ✅ verified: the module list takes the name from `heading_title`. 3.0.3.8 `extension/extension/module.php:160` and 4.0.2.3 `extension/module.php:88` (`$code . '_heading_title'`) show this.
- ❓ unverified: the OC4 core `add()` action (4.0.2.3 `extension/module.php:224`) stores `heading_title` as a module instance name. This module has no instances, so the list does not show an add button for it. If a user adds an instance by URL, the instance name contains the HTML. The effect is cosmetic only.
- ❓ unverified: I cannot run a live OpenCart admin here. A wrong relative image path gives a broken image icon, not an error. The post-build grep of the oc4.x and oc1.5 packages checks the path.

## Steps
- [x] Code: language files (en-gb, ru-ru, uk-ua) set the HTML `heading_title`.
  Findings: the `<img>` has `alt=""`, `height:1em`, `width:auto`, `vertical-align:-0.125em` and `margin-right:6px`. The icon then scales with the font size of the list row and of the H1.
- [x] Code: the controller uses `strip_tags` for the title and breadcrumbs, and sets `heading_title_text`.
- [x] Templates: the `.tpl` and `.oc4.twig` modal titles use `heading_title_text`.
- [x] Build: `build.sh` rewrites the image path in the three OC4 language files.
  Findings: the loop is next to the OC4 image path rewrites (after the `$OC4_LIB_CORE` line). It matches `src="view/image/translate_expert_client/`.
- [x] Bump version + build + test. Grep the oc4.x and oc1.5 packages for the image path.
  Findings: v04.01.0130. php -l and the PHP 5.6 grep pass. Tests: 24 pass (6 skipped, no key) and 40 pass. The oc4.x language files have the `../extension/…` src. The oc1.5 and oc3.0 language files have the `view/image/…` src. The icon file is in each package. The 1.5, 2.2, 3.0 and 4.x controllers set `heading_title_text`, and the modal titles use it.
- [x] Independent review → 6-extension-name-icon-review.md
  Findings: Fable 5.2. Ready to merge, 0 Critical, 0 Important, 5 Minor.
- [x] Address review findings (the user picks which to fix or skip)
  Findings:
  - Item 1 (list position): accepted by the user. Verified in the OpenCart source: 2.0.3.1 `extension/module.php:188`, 2.3.0.2 `extension/extension/module.php:179`, 3.0.3.8 and 4.x sort the list with `array_multisort` on the raw name. The module now shows at the top. OC 1.5.6.4 does not sort.
  - Item 2 (browser check): no code change. A manual check in a live admin is still open.
  - Item 3 (silent rewrite): fixed. After each OC4 `file_replace`, `build.sh` greps for the new src and stops with `exit 1` if it is absent. `build.release.sh` uses `set -euo pipefail`, so the release stops too.
  - Item 4 (same HTML in 3 files): skipped. OpenCart reads `heading_title` from each language file, so no shared place exists.
  - Item 5 (color): fixed. The user chose `#f56b00` (orange) in place of `#1e91cf`.
- [x] Bump version + build + tests (verify loop)
  Findings: v04.01.0131. Tests: 24 pass (6 skipped, no key) and 40 pass. All 21 language files in the packages have `#f56b00` and no `#1e91cf`. The OC4 guard did not fire.
- [x] Re-review? (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → -review-v2.md, v3, …)
- [x] Improvements pass → 6-extension-name-icon-improvements.md

No regression test: no test harness covers language files or the controller.

## Done
- Landed: `heading_title` in en-gb, ru-ru and uk-ua starts with the `translate-icon-32.png` icon, and "Expert" is in `#f56b00`. The browser title, breadcrumbs and modal title use `strip_tags` (`heading_title_text`). `build.sh` rewrites the icon path for OC4 and stops if the rewrite fails.
- Final version: 04.01.0131 (`releases/v04.01.0131/`).
- Gotcha: OC 2.0–4.x sort the module list by the raw name, so the module now shows at the top of the list. The user accepted this.
- Open: a manual check in a live admin (OC 1.5, 2.x or 3.0, and 4.x). The four workflow edits from the improvements file are applied.
