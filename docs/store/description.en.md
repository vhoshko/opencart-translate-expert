# Translate Expert: bulk Google Translate for OpenCart

Translate Expert is an OpenCart admin module. It uses the Google Cloud Translation API to translate the content of your store into any language that Google supports. It finds the texts that are not translated, shows how many characters they have, and translates them in bulk from the admin panel or from cron.

## Key features

- Bulk translation of database content: products, categories, articles and other multilingual tables.
- Analysis of untranslated and identical texts before you translate, with the number of characters.
- Translation and manual edit of language files (catalog and admin).
- A Translate button next to each multilingual field in the admin pages.
- Scheduled translation with a cron link.
- Character usage counter with a monthly reset and a cost estimate.
- Works with OpenCart 1.5 to 4.1.

## Website content translation

- Choose the source language and the target language. A button swaps them.
- Choose what to translate:
  - `both`: empty values and values that are the same as the source.
  - `only_empty`: empty values only.
  - `same_value`: values that are the same as the source only.
- Choose one table, many tables, or all tables. Select all and Clear selection buttons help with long lists. The analysis lists only the tables that have a `language_id` column and text columns.
- Deep analysis (on by default) shows the characters for each table and each column.
- Details show the first 100 values of a column, with the source text and the target text.
- Translate one table, or use Translate all. Stop ends a running job.
- Product filters under More filters: only active products, products with quantity above 0, category, stock status and manufacturer.
- The module remembers the fields where the translation is the same as the source. It does not offer these fields again. The Debug tab can reset this memory.
- Texts that contain HTML tags go to Google as HTML, so the tags keep their structure.
- Placeholders such as `%d` and `%s` stay correct after translation.
- If Google returns an empty text, the module keeps the source text.
- Remove translation clears the translated values of one target language for the selected tables and filters. Make a backup before you use it.

## Language files

- Two tabs: Catalog files and Admin files.
- The analysis shows which files have missing or partly translated values, and the key list of each file.
- Translate one file, edit it by hand and save it, or use Translate all. Stop ends a running job.
- On OpenCart 4.x the module also scans the language folders of extensions.
- PHP expressions in the values stay unchanged.

## Translate button in admin pages

- A Translate button appears next to each multilingual field in the admin pages.
- It works with plain inputs, text areas and these editors: Summernote, CKEditor and GrapesJS.
- It also works with the pages of Simple, the Batch editor and Handy Product Manager.
- The Excluded routes setting lets you switch the module off on pages that you name. One route on each line, `*` at the end matches a prefix.

## Scheduled translation (cron)

- Set a secret (16 to 128 characters, the Generate button makes one) and the number of rows for each run.
- Copy the cron link from the Website content tab and call it from cron, for example with `wget`.
- The cron link uses the tables, mode and filters that you select on the Website content tab.
- Each call translates one batch and returns JSON with `done`, `rows`, `chars`, `table` and `error`.
- Call the link again until `done` is `true`.
- Errors: `forbidden`, `disabled`, `bad_params`, `busy` and `translate_failed`.
- Only one run works at a time. A run stops after 15 minutes at most.
- The link does not work when the store is in maintenance mode.

## Settings and tools

- Connection: enter the Google API key. The Show key and Hide key buttons help you check it.
- Character usage: the module counts the characters that it sends to Google. A monthly reset is optional, and a Reset button on the module page clears the counter by hand. An option shows the counter in the header of all admin pages.
- Cost estimate: the counter uses 500 000 free characters for each month and $20 for each 1 000 000 characters above that. These are fixed values of the module. Check the current Google Cloud Translation price list.
- Language codes: map the language codes of your store to Google codes. Defaults: `ua` to `uk`, `rs` to `sr`, `ge` to `ka`, `cz` to `cs`.
- Try a translation: a test area for each store language.
- Debug tab: module, OpenCart and PHP versions, reset of the translation cache, debug mode and the log (view, download, clear).
- The module interface has English, Russian and Ukrainian.

## How to get a Google Cloud Translation API key

1. Sign in to your Google account.
2. Go to Google Cloud Platform: https://console.cloud.google.com/
3. Create a project or choose one.
4. In the search box, enter "Cloud Translation API" and select it.
5. Enable "Cloud Translation API".
6. You may need to enable billing. A new user can get a 300 USD credit to try Google Cloud Platform. The offer can change, so check the current offer.
7. Go to "Credentials" and create an API key. At the top, select "Create credentials", then select "API key".
8. Enter the key in the module settings, in the Connection card.

Google prices and rules can change. Check them in your Google Cloud account.

A separate article and a video tell how to get the key:

- Article: https://support.demollc.pw/133/poluchenie-klyucha-api-dlya-google-translate/
- Video: https://www.youtube.com/watch?v=5hTlSGD4_zk

## Compatibility

- OpenCart 1.5, 2.0, 2.1, 2.2, 2.3, 3.0, 4.0.2.x and 4.1.0.x.
- PHP 5.6, 7.0, 7.1 and above, and 8.x. OpenCart 4.0 needs PHP 8.0 or above. OpenCart 4.1 needs PHP 8.1 or above.
- OpenCart 1.5 needs vQmod. Other versions use OCMOD.
- The package has one install file for each OpenCart version.

## Support

The About tab of the module shows the module version and the support link. Support is through GitHub Issues: https://github.com/vhoshko/opencart-translate-expert/issues

## Donate

If the module is useful to you, you can support its development with a crypto donation.

- Tron
  - TKB4TNF9GRCcJQHavDCaMbFQVsEp322BhU
  - Accepts: TRX and USDT (TRC-20) on the Tron network.
  - Send only TRX or USDT (TRC-20) on the Tron network. Do not use ERC-20 or BEP-20.
- Bitcoin
  - bc1qr38nutpnjeqygs8tnsypw32hqtgpe0wnuf3jnv
  - Accepts: BTC.
  - Send only BTC on the Bitcoin network. This is a native SegWit address (it starts with bc1).
- Base
  - 0x5706F582478CE6C15Ef6489B103AC018280B0540
  - Accepts: ETH and tokens on the Base network.
  - Send only on the Base network (an Ethereum L2). This is an EVM address, so the same string is valid on other EVM networks. A transfer on Ethereum mainnet or on another EVM network may not arrive at the Base wallet.

QR codes: https://github.com/vhoshko/opencart-translate-expert#donate

<!--
Main images of the module (1024x1024, same design):
main-image.en.png (English), main-image.uk.png (Ukrainian)

Screenshots of the module tabs (OpenCart 4.1.0.4) are in docs/store/screenshots/en/ (English interface) and docs/store/screenshots/uk/ (Ukrainian interface), same file names:
01-settings.png, 02-website-content.png, 03-catalog-files.png, 04-admin-files.png, 05-debug.png, 06-about.png
-->
