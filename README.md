# OpenCart Translate Expert

An OpenCart admin module (OCMOD extension) that provides translation management for e-commerce stores. It integrates with the Google Cloud Translation API to translate product data, categories, descriptions, and language files directly from the OpenCart admin panel.

## Features

- Translate product data, categories, descriptions, and other database content via the Google Cloud Translation API
- Translate OpenCart language files
- Configurable translation modes: translate empty fields only, same-value fields only, or both
- Table analysis with deep scan support to detect translatable columns across the store's database
- Character usage tracking against the Google API, with a configurable monthly reset
- Supports OpenCart 1.5 through 4.1 (4.x: 4.0.2.x–4.1.0.x), across PHP 5.6, 7.0, 7.1+ and 8.x (OpenCart 4.0 requires PHP 8.0+, OpenCart 4.1 requires PHP 8.1+)

## Cron translation

The module can translate the database content from cron, without the admin page. In the module settings, set **Cron secret** (16 to 128 characters, letters, digits, `-` and `_`; the Generate button makes one) and **Rows per cron run**, then save. On the **Website content translation** tab, choose the languages, mode, table and product filters. Copy the **Cron link** and call it from cron:

```
*/10 * * * * wget -q -O - "<cron link>"
```

Each call translates one batch (up to the configured number of database rows) of all text columns of the selected table. If the table is "All tables", it goes through the tables in order. The answer is JSON: `{"done":false,"rows":100,"chars":5230,"table":"oc_product_description","error":""}`. If Google returns an empty translation for a text (for example `.`), the module keeps the source text as the translation. Errors: `forbidden` (HTTP 403), `disabled`, `bad_params`, `busy`, `translate_failed` (the answer has `message` and `details` with the cause). Only one run works at a time.

Run size: **Rows per cron run** controls how much one run translates. A run stops after 15 minutes at most. The PHP `max_execution_time` of the host and the timeout of the scheduler (for example the default read timeout of `wget`) still apply, so choose a bulk amount that finishes in time.

Limits: the secret is a part of the link, so it can appear in web server logs; the link does not work when the store is in maintenance mode; with "All tables" every run checks the finished tables again.

## Installation

Download the OCMOD package for your OpenCart version from the [releases](releases) directory (or build it yourself, see below), then install it through **Extensions > Installer** in the OpenCart admin panel, and enable it under **Extensions > Modules > Translate Expert (Full)**.

## Building from source

Requires `bash` and `zip`. The build version is read from [`_version.txt`](_version.txt).

```bash
# Build all OC version zips
./build.sh

# Full release (bundled into releases/)
./build.release.sh
```

The build scripts produce OCMOD `.zip` packages per supported OpenCart version (1.5, 2.0–2.3, 3.0, 4.x) in the `releases/` directory. The single 4.x package installs on both OpenCart 4.0.2.x and 4.1.0.x.

## Tests

```bash
# Unit tests only (no API key needed)
php tests/test_vh_google_translator.php

# Unit + integration tests
php tests/test_vh_google_translator.php YOUR_GOOGLE_API_KEY
```

### Test stores (Docker)

`docker/` runs OpenCart 2.3, 3.0, 4.0 and 4.1 stores locally and installs the built package into them:

```bash
./build.release.sh
docker compose -f docker/compose.yaml up -d --build
docker/install-module.sh all
```

See [docker/README.md](docker/README.md) for the details.

## Support

For consultation and support on module operation, open an issue on GitHub: https://github.com/vhoshko/opencart-translate-expert/issues

## License

See [LICENSE](LICENSE).
