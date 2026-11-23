# CLAUDE.md

This file gives guidance to Claude Code (claude.ai/code) for work on the code in this repository.

## Project Overview

OpenCart Translate Expert Client is an OpenCart admin module (OCMOD extension). It gives translation management for e-commerce stores. It uses the Google Cloud Translation API to translate product data, categories, descriptions and language files. It supports OpenCart 1.5–4.1 on PHP 5.6/7.0/7.1+/8.x. OC 4.0 needs PHP 8.0+. OC 4.1 needs PHP 8.1+.

Website: https://translator.codeguild.com.ua/

## Writing style (ASD-STE100)

Use ASD-STE100 Simplified Technical English (STE) writing rules for:

- All answers in this repository
- New plan, review and improvements files under `docs/tasks/**`
- New PoC and design files under `docs/pocs/**`
- Changes to `CLAUDE.md` and `.claude/**`

The source is ASD-STE100 Issue 9 (January 2025), DOI https://doi.org/10.5281/zenodo.23666005. The document is free. ASD owns it, and its license is CC BY-NC-ND 4.0. Use the writing rules (Part 1) only. Do not do a dictionary check. Do not copy rule text into the repository.

Key rules:

- Procedure sentence: 20 words maximum (rule 5.1). Give one instruction per sentence (5.2).
- Descriptive sentence: 25 words maximum (6.3). Paragraph: 6 sentences maximum, one topic (6.5, 6.6).
- Write instructions in the imperative. If a condition applies, put it first: "If X, do Y" (5.3, 5.4).
- Use the active voice and simple tenses. Use "-ing" forms only as technical nouns (3.2, 3.5, 3.6).
- Keep noun clusters to 3 words maximum (2.1).
- Do not use semicolons, contractions or Latin abbreviations (8.1, 4.2, GR-6).
- Use a vertical list for a sequence of items or steps (4.3).
- Use one term for one item. Do not switch between synonyms (1.11, 9.4).
- Start a warning with the instruction. Then give the reason (7.2, 7.3).

You can use technical nouns and technical verbs. Project terms are technical nouns and verbs. These are the kinds of project terms:

- OpenCart and OCMOD terms
- File and folder paths
- Table and column names
- Code identifiers
- Commands
- Model names

Out of scope:

- Existing `docs/tasks/**` files (they are records, so do not change them)
- Source code comments
- UI copy and language files (en-gb, ru-ru, uk-ua)
- Code, commands and quoted literal strings
- Agent answers outside this repository

No automated check enforces these rules.

## Build

The build uses bash and `zip`. The build reads the version from `_version.txt`.

```bash
# Build all OC version zips
./build.sh

# Full release (bundled into releases/)
./build.release.sh
```

The build makes OCMOD `.zip` packages for each OpenCart version (1.5, 2.0–2.3, 3.0, 4.x) in the `releases/` directory. One 4.x package (`oc4.x` folder) covers OpenCart 4.0.2.x and 4.1.0.x.

**After you make changes, always run `./build.release.sh`. This command shows that the build succeeds.**

If a change touches only files under `docs/**`, run the unit tests. Do not bump the version and do not build.

**Before you run a build script, bump the patch version in `_version.txt`.** The format is `MM.mm.pppp`, for example `04.01.0101` → `04.01.0102`. Every build run needs a new patch version. Never build twice with the same version number.

**Never leave `.zip` files or build artifact folders in the repo root.** The build scripts remove these items themselves. If leftover zips or `OpenCartTranslateExpertClient-*` folders are in the root, remove them.

## Tests

```bash
# Unit tests only (no API key needed)
php tests/test_translate_expert_google_client.php

# Unit + integration tests
php tests/test_translate_expert_google_client.php YOUR_GOOGLE_API_KEY

# Core translate tests: configured language code map and format parameter fix in TranslateExpertCore (stub Google client, no DB, no API key)
php tests/test_translate_expert_core_translate.php

# Library path tests: each translate_expert/ path in sources/ exists, and no old library file or class name remains (static)
php tests/test_translate_expert_library_paths.php

# Route filter unit tests (no API key needed)
php tests/test_translate_expert_route_filter.php

# Cron helper unit tests (no API key needed)
php tests/test_translate_expert_cron_helper.php

# Translate loop tests (row cap, empty results, duplicate texts, time limits, admin batch, analize and detail JSON; no DB, no API key)
php tests/test_client_translate_expert_translate_loop.php

# Controller JSON guard tests: runJsonAction error JSON for Exception and Error, bad filter result, POST-only removeDataFromDb and resetCache (stubs, no DB, no API key)
php tests/test_client_translate_expert_ajax_guard.php

# Version rules tests: token name, setting key prefix, routes, template engine and flag URL helpers of the admin controller and the model (stubs, no DB, no API key)
php tests/test_client_translate_expert_version_rules.php

# Settings cards tests: toggle button, body id and start state of the 4 collapsible cards in the .tpl and the .oc4.twig (static, no DB, no API key)
php tests/test_client_translate_expert_settings_cards.php

# Donation info tests: wallet addresses in the README, store descriptions and language files, QR image files, template and whitelist keys (static, no DB, no API key)
php tests/test_client_translate_expert_donation_info.php

# OC4 Twig name check: filters, tests, functions, tags (static, no Twig needed)
php tests/test_oc4_twig_filters.php

# Language code map helper tests: default JSON, parse, lookup, normalize and its error codes (no OpenCart, no DB, no API key)
php tests/test_translate_expert_language_code_map.php

# Default map write tests: ensureLanguageCodeMapDefault and install() for each OC version (stub DB, no API key)
php tests/test_translate_expert_language_code_map_default.php

# Language code map controller tests: default write in index(), save guard, kept value, template data (stubs, no DB, no API key)
php tests/test_client_translate_expert_language_code_map_save.php

# Localization scope tests: catalog and admin language folders, OC4 extension roots, save guard, JSON analysis, batching, errors (temp folders, no DB, no API key)
php tests/test_client_translate_expert_localization.php

# Header strings tests: language file candidates, English fallback, JSON escaping (temp folders, no DB, no API key)
php tests/test_translate_expert_i18n.php

# JS helper tests: teT, teIcon, teBs, teAlerts, localization tabs in jsdom with jQuery 3.7.1 and 1.7.1 (Node, no browser, no OpenCart)
cd tests/js && npm ci && node test_client_translate_expert_js_helpers.js
```

The `TranslateExpertGoogleClient` tests cover batch processing, the skipped empty, blank, null and "0" texts, and API integration.

The core translate tests cover the language code mapping and the format parameter fix of `TranslateExpertCore` for a string and for a batch. Any PHP warning fails these tests.

The library path test reads each `translate_expert/` path in `sources/` and fails if the target file does not exist. It also fails on an old library file name or class name in `sources/`, `build.sh` and `tests/`.

The `TranslateExpertRouteFilter` tests cover the excluded-routes rules. Any PHP warning fails these tests.

The `TranslateExpertCronHelper` tests cover the cron secret rules, the request parameters and the setting key prefix. Any PHP warning fails these tests.

The `TranslateExpertI18n` tests cover the language file candidates, the English fallback, the global keys in the 3 module language files and the JSON escaping for a `<script>` block. Any PHP warning fails these tests.

The donation info test checks that each wallet address is the same in `README.md`, the store descriptions and the 3 language files. It checks that the QR images in `docs/donate/` are PNG files that the README uses. It checks that both templates and the controller whitelist use each `te_donate_*` key. It checks that no file in `sources/` uses the QR images. Any PHP warning fails the test.

The JS helper tests load the real `client_translate_expert.js` in jsdom. They cover the `teT` lookup and placeholders, the FA 4 and FA 6 icon classes, the Bootstrap adapter with BS5 and with no Bootstrap, and the `teAlerts` text-only rendering of JSON, non-JSON and jqXHR responses. They also cover the localization tabs: the analysis loop, the key list, the PHP literal helpers, translate and save for one file, "Translate all" with Stop, the translated state, the "only files to translate" filter, the edit of translated keys and a PHP round trip of the saved file (skipped if `php` is not in the PATH). `npm ci` installs the pinned dev dependencies (jsdom, jQuery 3.7.1) into `tests/js/node_modules/`, which git ignores. jQuery 1.7.1 is the OC 1.5 file in `tests/js/vendor/`.

The version rules tests call the `private static` helpers of the admin controller and the model with a version string. They cover each branch (OC 1.5, 2.2, 2.3, 3.0 and 4.x). Any PHP warning fails these tests.

The OC4 Twig name test reads each `.twig` file under `sources/`. It fails on a filter, test, function or tag that Twig v3.4.2 (OpenCart 4.0.2.x) or Twig v3.18.0 (OpenCart 4.1.0.x) does not define. Such a name breaks the OC4 admin page with "Could not load template". It also fails on syntax that only Twig v3.18.0 accepts (`has some`, `has every`, `xor`, the `...` spread, `name: value` arguments) and on an unclosed comment, block, string or `verbatim` block. The test skips comments and `verbatim` blocks, and it checks string interpolation.

## Architecture

The module follows the OpenCart MVC pattern. Library classes hold the core logic:

```
Controller: sources/upload/admin/controller/extension/module/client_translate_expert.php
    → handles admin UI routes, settings, AJAX actions
Model: sources/upload/admin/model/extension/module/client_translate_expert.php
    → database queries for table/column analysis
Libraries (sources/upload/system/library/translate_expert/):
    translate_expert.php                → TranslateExpert: thin wrapper delegating to core
    translate_expert_core.php           → TranslateExpertCore: main translation logic, table analysis, batch processing, language code mapping (reads the setting), format parameter fix
    translate_expert_language_code_map.php → TranslateExpertLanguageCodeMap: default map JSON, parse, lookup and validation of the language code map (no OpenCart dependencies)
    translate_expert_localization.php   → TranslateExpertLocalization: OpenCart language file parsing/writing
    translate_expert_google_client.php  → TranslateExpertGoogleClient: Google Translate API wrapper
    translate_expert_route_filter.php   → TranslateExpertRouteFilter: excluded-routes check (OCMOD header op and OC4 header events)
    translate_expert_cron_helper.php    → TranslateExpertCronHelper: cron secret rules and request parameter parsing (no OpenCart dependencies)
    translate_expert_i18n.php           → TranslateExpertI18n: header strings (document.te_i18n_global) from the module language file (no OpenCart dependencies)
Store-front (catalog):
    sources/upload/catalog/controller/extension/module/client_translate_expert_cron.php
    → cron link: thin controller, passes GET to TranslateExpert::runCron() (secret, lock, one translation batch)
Frontend:
    admin/view/javascript/client_translate_expert.js  → admin UI logic (tabs, AJAX calls, analysis)
    admin/view/template/extension/module/client_translate_expert.tpl → admin template
```

OCMOD adds the JS and CSS to the admin header through `OpenCartTranslateExpertClient.ocmod.xml`.

## Key Concepts

- **Translation modes**: `both` (empty + same-value), `only_empty`, `same_value`. The mode sets which DB fields the module translates
- **Table analysis**: scans OpenCart DB tables for translatable columns. It supports deep analysis with pagination
- **Character counting**: counts the Google API usage for each month. The reset is configurable
- **`translate_expert_same_translations` table**: stores hashes of fields where source equals target. This prevents a second translation of these fields
- **Multi-version support**: the build system makes a separate OCMOD package for each OpenCart version. The template and controller differ between versions

## i18n

Language files in `admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php`.

## Tools

Standalone scripts in `tools/` do batch operations outside the OpenCart admin:
- `translate_complex_column_values.php` — batch translate DB columns
- `find_wrong_translated_values.php` — find mistranslated values
- `replace_in_db.sql` — DB correction queries
