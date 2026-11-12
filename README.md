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

Download the `.full.zip` from the [releases](releases) directory (or build it yourself, see below) and unzip it. It holds one package per OpenCart version:

| OpenCart | Package |
|----------|---------|
| 1.5.x    | `OpenCartTranslateExpertClient-oc1.5-….ocmod.zip` (needs vQmod) |
| 2.0.x    | `OpenCartTranslateExpertClient-oc2.0-….ocmod.zip` |
| 2.1.x    | `OpenCartTranslateExpertClient-oc2.1-….ocmod.zip` |
| 2.2.x    | `OpenCartTranslateExpertClient-oc2.2-….ocmod.zip` |
| 2.3.x    | `OpenCartTranslateExpertClient-oc2.3-….ocmod.zip` |
| 3.0.x    | `OpenCartTranslateExpertClient-oc3.0-….ocmod.zip` |
| 4.0.2.x, 4.1.0.x | `client_translate_expert.ocmod.zip` in the `…-oc4.x-…` folder |

The [Docker test stores](docker/README.md) install the module with these same steps (`docker/install-module.sh`).

### OpenCart 2.0 – 3.0

Install:

1. Unzip the package for your version. Copy the contents of its `upload/` folder to the root folder of the store (the folder with `index.php`, `admin/` and `system/`). Keep the folder structure.
2. In the admin, open **Extensions > Modifications** and click **Refresh**. This applies `system/OpenCartTranslateExpertClient.ocmod.xml` to the admin header.
3. Open the module list (2.0–2.2: **Extensions > Modules**; 2.3 and 3.0: **Extensions > Extensions**, type **Modules**). Find **Translate Expert (Full)** and click **Install**, then **Edit**.

Update: copy the `upload/` contents of the new package over the old files, then click **Refresh** in **Extensions > Modifications** again. Do not uninstall the module: an uninstall deletes its settings.

### OpenCart 1.5

OpenCart 1.5 has no OCMOD. The module changes the admin header with [vQmod](https://github.com/vqmod/vqmod) instead, so install vQmod first:

1. Download `vqmod-2.6.x-opencart.zip` from the [vQmod releases](https://github.com/vqmod/vqmod/releases) and copy its `vqmod/` folder to the root folder of the store.
2. Make `vqmod/`, `index.php` and `admin/index.php` writable by the web server. Open `http://<your store>/vqmod/install/` in a browser. It shows "VQMOD HAS BEEN INSTALLED ON YOUR SYSTEM!". Then delete the `vqmod/install/` folder.

If vQmod is already installed, skip these steps.

Install the module:

1. Unzip `OpenCartTranslateExpertClient-oc1.5-….ocmod.zip`. Copy the contents of its `upload/` folder to the root folder of the store. This puts `vqmod/xml/OpenCartTranslateExpertClient.xml` in place.
2. Delete the file `vqmod/mods.cache` and the `vq2-*` files in `vqmod/vqcache/`, if they exist. vQmod then applies its XML files again on the next page load. Without this step, vQmod can keep its old caches: it reuses them while they are not older than the newest XML file, and many copy tools keep the old file dates.
3. In the admin, open **Extensions > Modules**. Find **Translate Expert (Full)** and click **Install**, then **Edit**.

Update: copy the `upload/` contents of the new package over the old files, then delete `vqmod/mods.cache` and the `vq2-*` files in `vqmod/vqcache/` again. Do not uninstall the module: an uninstall deletes its settings. Packages before 04.01.0147 put the XML in `system/OpenCartTranslateExpertClient.ocmod.xml`, where vQmod does not read it. You can delete that file.

If the admin header does not load the module (no `client_translate_expert.js` in the page source), first delete `vqmod/mods.cache` and the `vq2-*` files in `vqmod/vqcache/`, and reload the page. If that does not help, look in `vqmod/logs/` for an error about `OpenCartTranslateExpertClient.xml`.

### OpenCart 4.0 / 4.1

Install:

1. In the admin, open **Extensions > Installer**. Upload `client_translate_expert.ocmod.zip` (from the `…-oc4.x-…` folder) and click **Install** in the list of installed extensions.
2. Open **Extensions > Extensions**, type **Modules**. Find **Translate Expert (Full)** and click **Install**, then **Edit**.

Update: unzip the new `client_translate_expert.ocmod.zip` and copy its contents over the files in `extension/client_translate_expert/` on the server. First delete the old contents of that folder, so that files a newer version removed do not stay. Do not uninstall the module: an uninstall deletes its settings.

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

`docker/` runs OpenCart 1.5, 2.0, 2.1, 2.2, 2.3, 3.0, 4.0 and 4.1 stores locally and installs the built package into them:

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
