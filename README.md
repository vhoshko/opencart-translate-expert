# OpenCart Translate Expert Client

An OpenCart admin module (OCMOD extension) that provides translation management for e-commerce stores. It integrates with the Google Cloud Translation API to translate product data, categories, descriptions, and language files directly from the OpenCart admin panel.

Website: https://translator.codeguild.com.ua/

## Features

- Translate product data, categories, descriptions, and other database content via the Google Cloud Translation API
- Translate OpenCart language files
- Configurable translation modes: translate empty fields only, same-value fields only, or both
- Table analysis with deep scan support to detect translatable columns across the store's database
- Character usage tracking against the Google API, with a configurable monthly reset
- Supports OpenCart 1.5 through 4.0, across PHP 5.6, 7.0, and 7.1+

## Installation

Download the OCMOD package for your OpenCart version from the [releases](releases) directory (or build it yourself, see below), then install it through **Extensions > Installer** in the OpenCart admin panel, and enable it under **Extensions > Modules > Translate Expert (Full)**.

For detailed, up-to-date installation and configuration instructions, see:
https://translator.codeguild.com.ua/uk-ua/translate-opencart-in-few-clicks

## Usage

See the usage guide on the website:
https://translator.codeguild.com.ua/uk-ua/translate-opencart-in-few-clicks#head4

## Building from source

Requires `bash` and `zip`. The build version is read from [`_version.txt`](_version.txt).

```bash
# Build all OC version zips
./build.sh

# Full release (bundled into releases/)
./build.release.sh
```

The build scripts produce OCMOD `.zip` packages per supported OpenCart version (1.5, 2.0–2.3, 3.0, 4.0) in the `releases/` directory.

## Tests

```bash
# Unit tests only (no API key needed)
php tests/test_vh_google_translator.php

# Unit + integration tests
php tests/test_vh_google_translator.php YOUR_GOOGLE_API_KEY
```

## Support

All users get free consultation and support on module operation via email: admin@codeguild.com.ua

## License

See [LICENSE](LICENSE).
