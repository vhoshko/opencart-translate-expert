# 83-store-description

## Settings
- Planning model: Opus. Review model: Sonnet (independent, different from the planning model).
- Docs-only task. Run the checks-only tests before and after the change. Do not bump `_version.txt`. Do not build.
- Before the review, check each number, field name and feature claim in the 2 files against the code with grep or a file read.
- After Phase 7: commit, push and PR. The user gave standing auto-approve for the review findings.

## Context
Issue #83: https://github.com/vhoshko/opencart-translate-expert/issues/83. The module has no current text for an extension store page. The old hand-written Ukrainian text is out of date. Write 2 files with the same structure and content: `docs/store/description.en.md` and `docs/store/description.uk.md`. The Ukrainian H1 is the name "Масовий автоматичний переклад текстів, товарів, категорій, статей тощо з Google translate API - елітий модуль". The files are not in the OCMOD packages, so all OC versions stay unchanged. Investigation: direct greps and file reads (no Explore agent). Paths below are relative to `sources/upload/`. Module version at planning time: 04.01.0216.

Verified features (evidence as path:line):
1. Settings tab, Connection card: status and Google API key with Show/Hide (`admin/view/template/extension/module/client_translate_expert.tpl:67-90`, `admin/language/en-gb/extension/module/client_translate_expert.php:24-26`).
2. Character usage card: count, "show in the admin header" option, monthly reset option (`.tpl:94-110`). The month reset runs on each count read or write (`admin/controller/extension/module/client_translate_expert.php:828-835`). The UI has no manual reset button (`resetTranslationCharCount()` in `admin/view/javascript/client_translate_expert.js:639` has no caller).
3. Header counter on all admin pages: "count / 500 000 (cost)". The cost estimate uses fixed values 500 000 free and $20 for each 1 000 000 (`controller:766-768`, `js:1291`).
4. Website content tab: languages "Translate from" / "to" with Swap (`.tpl:53`). 3 modes `both`, `only_empty`, `same_value` (`.tpl:198-202`, `system/library/translate_expert/translate_expert.php:819-825`). Multi-select of tables, up to 100 items (`translate_expert_cron_helper.php:13`).
5. Analysis: only tables with a `language_id` column and text columns (`varchar`, `*text`) (`translate_expert.php:520-539`). Deep analysis checkbox, on by default (`.tpl:217-221`, `js:1849-1874`). Result table: characters per table and column, Translate per table, Translate all, Details (first 100 values) and Stop (`en-gb lang:171-206`).
6. Product filters under "More filters": only active, quantity > 0, category, stock status, manufacturer (`.tpl:229-276`, `admin/model/extension/module/client_translate_expert.php:25-64`).
7. Same-value memory: table `translate_expert_same_translations` holds MD5 hashes (`translate_expert_core.php:11,258-266,974`). If Google returns an empty text, the module keeps the source text (`translate_expert.php:500-508`).
8. HTML: the module sends `format=html` if a text has `</`, else `text` (`translate_expert.php:459-466`). `%d` and `%s` stay correct after translation (`translate_expert_core.php:481-500`).
9. Remove translation: clears target language values for the selected tables and filters, with a backup warning (`translate_expert_core.php:189-240`, `.tpl:282`, `en-gb lang:65-67`).
10. Catalog files and Admin files tabs: analysis, missing/partial/saved state, key list, translate one file, save, "Translate all" with Stop (`.tpl:303-336`, `en-gb lang:207-234`, `translate_expert_localization.php:17,88,173-175`). OC 4.x also scans extension language folders (`translate_expert_localization.php:178-240`). PHP expressions stay unchanged (`en-gb lang:226`, `translate_expert_localization.php:377,426`).
11. Translate button next to each multilingual field in admin pages: inputs, textareas, Summernote, CKEditor, GrapesJS (`js:142-166,248-338,492-520`). Extra adapters: Simple, Batch editor, Handy Product Manager, Unishop settings (`js:41-140,179`). OC 1.5-3.0 load it with the OCMOD header op (`system/OpenCartTranslateExpertClient.ocmod.xml:9-60`, vQmod on OC 1.5 per `README.md`). OC 4.x uses 2 header events (`controller:980-1000,1024-1066`).
12. Excluded routes card: one route on each line, `*` at the end matches a prefix, full URL accepted (`en-gb lang:76-77`, `system/library/translate_expert/translate_expert_route_filter.php`).
13. Language codes card: store code to Google code pairs, defaults `ua→uk`, `rs→sr`, `ge→ka`, `cz→cs`, 100 pairs max, 20 characters max (`translate_expert_language_code_map.php:8`, `en-gb lang:237-253`).
14. Cron card and Cron link: secret 16-128 characters with Generate, rows per run 1-100000 (default 1000), one batch per call, JSON answer, errors `forbidden` (403), `disabled`, `bad_params`, `busy`, `translate_failed`, one run at a time, 900 s limit (`translate_expert_cron_helper.php:9-12`, `translate_expert.php:13,99-170`, `catalog/controller/extension/module/client_translate_expert_cron.php`, `README.md` "Cron translation").
15. Try a translation card: one text area for each store language (`.tpl:174-186`).
16. Debug tab: module, OpenCart and PHP versions, Reset translation cache (clears the same-value table), debug mode, view, download and clear of the log (`.tpl:339-401`, `controller:796-812,893-930`).
17. About tab: support through GitHub Issues (`en-gb lang:14`, `.tpl:402-410`).
18. UI languages: en-gb, ru-ru, uk-ua (`admin/language/*`). Versions: OC 1.5-4.1, PHP 5.6/7.0/7.1+/8.x, OC 4.0 needs PHP 8.0+, OC 4.1 needs PHP 8.1+ (`CLAUDE.md`, `README.md` Installation table: 7 packages).

## Approach
Write the English file first from the verified list above. Then write the Ukrainian file as a translation with the same headings, order and bullets. Each claim must map to one item of the list. Old-text claims stay only if the code supports them (items 3, 8, 11). Google prices stay only as the module's built-in estimate, with a note to check the current Google price list.

Section outline (H1 + H2, same order in both files):
1. H1: module name (en: "Translate Expert: bulk Google Translate for OpenCart" or similar, uk: the given name verbatim).
2. Short intro (2-3 sentences): what the module does.
3. H2 "Key features" (short bullets).
4. H2 "Website content translation" (items 4-9).
5. H2 "Language files" (item 10).
6. H2 "Translate button in admin pages" (items 11-12).
7. H2 "Scheduled translation (cron)" (item 14).
8. H2 "Settings and tools" (items 1-3, 13, 15-16).
9. H2 "Google Cloud Translation API key" (steps, see Assumptions).
10. H2 "Compatibility" (item 18 and the package list).
11. H2 "Support" (item 17).
12. HTML comment at the end: list of candidate screenshots from `images/` (see Assumptions).

Style rules for the store text:
- Plain Markdown only. No HTML tags, except one `<!-- -->` comment for the screenshot list.
- H1 once, H2 for sections. No H3 or deeper.
- Short bullets, one fact for each bullet. Use the UI labels of the en-gb and uk-ua language files.
- No hype claims that the code cannot support, for example "the whole site in 2-3 minutes", "fastest", "best". If a speed example is necessary, mark it as an example and do not give a time.
- Do not use ASD-STE100 rules for the store text (it is UI-style copy), but keep sentences short.

Claims that must NOT appear: a module price or "one-time price", video links, screenshot URLs, demo store links, the $300 new-user bonus as a fact, current Google prices as a fact, "from version 2.0", any speed or time-for-site claim, features that the code does not have (a manual reset button for the char count, automatic translation on product save).

## Assumptions
- ❓ unverified: Google free quota 500 000 characters for each month and $20 for each 1 000 000. The plan does not check the web. Present them only as the module's built-in estimate (`controller:766-767`) and add "check the current Google Cloud Translation price list".
- ❓ unverified: the $300 new-user credit. Do not state it as a fact. If the writer mentions credit, use "Google can give new accounts a free trial credit; check the current offer".
- ❓ unverified: Google console steps (create a project, enable Cloud Translation API, create an API key, set billing). Write them as generic steps with no button names that can change, and no links except `https://console.cloud.google.com/`.
- ✅ verified: `LICENSE` is MIT. The old "one-time price" claim conflicts with it. Do not mention a price. Report the conflict to the user in the final summary.
- ✅ verified: `images/` holds 17 files (`500x500_1..8.png`, `TranslateExpert_en_710x380.png`, `TranslateExpert_ru_380x380.png`, `TranslateExpert_ru_710x380.png`, `translate_expert_opencart_10_.png` .. `_17.png`). Their content can be out of date. Reference none by URL. Put the file names only in the end comment as candidates.
- ✅ verified: the Ukrainian name contains "елітий" (likely a typo of "елітний"). Use it verbatim, as the issue says. Report it to the user.
- ✅ verified: maintenance mode blocks the cron link (`README.md` "Cron translation", OpenCart core behavior, not module code).

## Steps
- [x] Write `docs/store/description.en.md` with the outline and the style rules.
- [x] Write `docs/store/description.uk.md` with the same headings, order and facts. Use the uk-ua UI labels (`admin/language/uk-ua/extension/module/client_translate_expert.php`).
- [x] Check each claim against the code. Findings: the Unishop claim removed (the code has only a `uni_set` name pattern). The image list fixed (no `TranslateExpert_ru_710x380.png`, no `translate_expert_opencart_14.png`). Numbers checked: 500 000, $20, 16, 128, 1000, 100000, 900 s. For each number (500 000, 20, 100, 16, 128, 100000, 1000, 900 or 15 minutes, 20 characters), grep the source. For each UI label, grep the en-gb and uk-ua language files. Compare the 2 files section by section. Record the result in the Done block.
- [ ] Run the checks-only tests (`ote-build-test`, "checks only", no bump, no build).
- [x] Independent review (Sonnet) → 83-store-description-review.md (0 critical, 1 important, minor)
- [x] Address the review findings: fixed all (Reset button on the module page, Clear selection, cron link scope and done loop, About tab, Show key label, HTML wording, image list). The plan was wrong: a manual Reset button exists (`js:1325`).
- [x] Checks-only tests: passed before and after the change (only Markdown changed after the review)
- [x] Re-review gate: skipped. The fixes add facts that the code shows, and the review had no critical finding
- [x] Improvements pass → `83-store-description-improvements.md`.
- [ ] Commit, push and PR (`docs: add store description files in en and uk`, body `closes #83`).

## Done
The 2 files in `docs/store/` describe the module for a store page. The review found one wrong plan claim (the manual Reset button exists). Open points for the user: the old text claims (price, $300 credit, speed, videos) are not in the new files. The word "елітий" stays as in the issue. No build and no version bump.

## Update 17.11.2026 (images)
The user chose 2 generated main images (Gemini, 1024x1024, en and uk). They are in `docs/store/main-image.en.png` and `docs/store/main-image.uk.png`. The old `images/` folder (18 files) is removed. No code, build script or README used it. The end comment of both description files now names the 2 new images. This replaces the candidate list in the Assumptions and in the Steps above.

## Update 17.11.2026 (screenshots)
The user added 6 screenshots of the module tabs (OpenCart 4.1.0.4, English UI, local test store). They are in `docs/store/screenshots/` (`01-settings.png` to `06-about.png`). Known defects for a retake: a broken flag image in "Try a translation" (01), a stray highlight on one cell (03), a cropped side menu (03, 04, 06), a different width in each file and a partial cron secret in the cron link (02).

## Update 17.11.2026 (Ukrainian screenshots)
The user added 6 screenshots of the Ukrainian interface. The files now live in `docs/store/screenshots/en/` and `docs/store/screenshots/uk/` with the same names. The earlier English defects (broken flag, cropped side menu in 03, different widths) are also in the Ukrainian set. The Ukrainian settings and website content shots show the cron secret.
