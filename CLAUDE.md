# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

OpenCart Translate Expert Client — an OpenCart admin module (OCMOD extension) that provides translation management for e-commerce stores. Integrates with Google Cloud Translation API to translate product data, categories, descriptions, and language files. Supports OpenCart 1.5–3.0 across PHP 5.6/7.0/7.1+.

Website: https://translator.codeguild.com.ua/

## Build

Build uses bash and `zip`. Version is read from `_version.txt`.

```bash
# Build all OC version zips
./build.sh

# Full release (bundled into releases/)
./build.release.sh
```

Build produces OCMOD `.zip` packages per OpenCart version (1.5, 2.0–2.3, 3.0) in the `releases/` directory.

## Tests

```bash
# Unit tests only (no API key needed)
php tests/test_vh_google_translator.php

# Unit + integration tests
php tests/test_vh_google_translator.php YOUR_GOOGLE_API_KEY
```

Tests cover `VHGoogleTranslator`: language code mapping, format parameter preservation, batch processing, and API integration.

## Architecture

Follows OpenCart MVC pattern with library classes for core logic:

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
Frontend:
    admin/view/javascript/client_translate_expert.js  → admin UI logic (tabs, AJAX calls, analysis)
    admin/view/template/extension/module/client_translate_expert.tpl → admin template
```

OCMOD injects JS/CSS into the admin header via `OpenCartTranslateExpertClient.ocmod.xml`.

## Key Concepts

- **Translation modes**: `both` (empty + same-value), `only_empty`, `same_value` — controls which DB fields get translated
- **Table analysis**: scans OpenCart DB tables for translatable columns, supports deep analysis with pagination
- **Character counting**: tracks Google API usage per month with configurable reset
- **`translate_expert_same_translations` table**: stores hashes of fields where source equals target to avoid re-translating
- **Multi-version support**: build system generates separate OCMOD packages per OpenCart version, with version-specific template/controller differences

## i18n

Language files in `admin/language/{en-gb,ru-ru,uk-ua}/extension/module/client_translate_expert.php`.

## Tools

Standalone scripts in `tools/` for batch operations outside the OpenCart admin:
- `translate_complex_column_values.php` — batch translate DB columns
- `find_wrong_translated_values.php` — find mistranslated values
- `replace_in_db.sql` — DB correction queries
