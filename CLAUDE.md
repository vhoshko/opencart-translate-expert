# CLAUDE.md

This file gives guidance to Claude Code (claude.ai/code) for work on the code in this repository.

## Project Overview

OpenCart Translate Expert Client is an OpenCart admin module (OCMOD extension). It gives translation management for e-commerce stores. It uses the Google Cloud Translation API to translate product data, categories, descriptions and language files. It supports OpenCart 1.5–4.1 on PHP 5.6/7.0/7.1+/8.x. OC 4.0 needs PHP 8.0+. OC 4.1 needs PHP 8.1+.

Website: https://translator.codeguild.com.ua/

## Writing style (ASD-STE100)

Use ASD-STE100 Simplified Technical English (STE) writing rules for:

- All answers in this repository
- New plan, review and improvements files under `docs/tasks/**`
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

**Before you run a build script, bump the patch version in `_version.txt`.** The format is `MM.mm.pppp`, for example `04.01.0101` → `04.01.0102`. Every build run needs a new patch version. Never build twice with the same version number.

**Never leave `.zip` files or build artifact folders in the repo root.** The build scripts remove these items themselves. If leftover zips or `OpenCartTranslateExpertClient-*` folders are in the root, remove them.

## Tests

```bash
# Unit tests only (no API key needed)
php tests/test_vh_google_translator.php

# Unit + integration tests
php tests/test_vh_google_translator.php YOUR_GOOGLE_API_KEY

# Route filter unit tests (no API key needed)
php tests/test_client_translate_expert_route_filter.php

# Cron helper unit tests (no API key needed)
php tests/test_client_translate_expert_cron_helper.php

# Translate loop tests (row cap, empty results, duplicate texts, time limits, admin batch; no DB, no API key)
php tests/test_client_translate_expert_translate_loop.php

# OC4 Twig name check: filters, tests, functions, tags (static, no Twig needed)
php tests/test_oc4_twig_filters.php

# Localization scope tests: catalog and admin language folders, OC4 extension roots, save guard (temp folders, no DB, no API key)
php tests/test_client_translate_expert_localization.php
```

The `VHGoogleTranslator` tests cover language code mapping, format parameter preservation, batch processing and API integration.

The `ClientTranslateExpertRouteFilter` tests cover the excluded-routes rules. Any PHP warning fails these tests.

The `ClientTranslateExpertCronHelper` tests cover the cron secret rules, the request parameters and the setting key prefix. Any PHP warning fails these tests.

The OC4 Twig name test reads each `.twig` file under `sources/`. It fails on a filter, test, function or tag that Twig v3.4.2 (OpenCart 4.0.2.x) or Twig v3.18.0 (OpenCart 4.1.0.x) does not define. Such a name breaks the OC4 admin page with "Could not load template". It also fails on syntax that only Twig v3.18.0 accepts (`has some`, `has every`, `xor`, the `...` spread, `name: value` arguments) and on an unclosed comment, block, string or `verbatim` block. The test skips comments and `verbatim` blocks, and it checks string interpolation.

## Architecture

The module follows the OpenCart MVC pattern. Library classes hold the core logic:

```
Controller: sources/upload/admin/controller/extension/module/client_translate_expert.php
    → handles admin UI routes, settings, AJAX actions
Model: sources/upload/admin/model/extension/module/client_translate_expert.php
    → database queries for table/column analysis
Libraries (sources/upload/system/library/):
    client_translate_expert.php         → thin wrapper delegating to core
    client_translate_expert_core.php    → main translation logic, table analysis, batch processing
    client_localization_translate_expert.php → OpenCart language file parsing/writing
    vh_google_translator.php            → Google Translate API wrapper with language code mapping
    client_translate_expert_route_filter.php → excluded-routes check (OCMOD header op and OC4 header events)
    client_translate_expert_cron_helper.php → cron secret rules and request parameter parsing (no OpenCart dependencies)
Store-front (catalog):
    sources/upload/catalog/controller/extension/module/client_translate_expert_cron.php
    → cron link: thin controller, passes GET to LibraryClientTranslateExpert::runCron() (secret, lock, one translation batch)
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
