# Review: store description files (issue 83)

Files: `docs/store/description.en.md`, `docs/store/description.uk.md`.

## Strengths

- Both files have the same 10 headings in the same order (H1, 9 H2, no H3). Facts are the same in both.
- The Ukrainian H1 matches the required text.
- Checked and true in the code:
  - Free limit `500*1000` and `$20` for each 1 000 000 characters (controller lines 766-768).
  - Secret length 16 to 128 (`SECRET_MIN_LENGTH`, `SECRET_MAX_LENGTH`).
  - Cron limit 15 minutes (`CRON_TIME_LIMIT = 900`).
  - Default map `ua`, `rs`, `ge`, `cz` (`DEFAULT_JSON`).
  - Cron fields `done`, `rows`, `chars`, `table`, `error` and the five error names.
  - Details limit 100 (`TRANSLATION_ANALITIC_SQL_LIMIT`, `analizeTableDetail`).
  - Five product filters, `both`, `only_empty` and `same_value`, the Swap button, deep analysis, Remove translation with filters, and the maintenance note (cron help text).
  - Editors Summernote, CKEditor, GrapesJS, and the Simple, Batch editor and Handy Product Manager pages (JS lines 60 to 147).
  - vQmod for OC 1.5 and the PHP and OpenCart versions (README).
  - Keep-source text when Google returns an empty text, and the `%d` and `%s` handling.
- No price of the module, no video, no screenshot URL, no $300 bonus, no Google price as fact, no "from version 2.0", no speed claim, no claim of automatic translation on product save.
- Markdown is plain. The only HTML is the end comment. The 15 listed image names exist in `images/`.

## Issues

### Critical

None.

### Important

1. Missed feature: a manual Reset button for the character count exists. `client_translate_expert.js:1325` renders `te-js-reset-char-count` ("Reset", with a confirm text) in the usage summary of the module page (`teRenderSummaryCount`, used at line 591). Only the header counter lost its button (issue 74). Both files say only "A monthly reset is optional". The text is not false, but it hides this feature. Add one sentence, for example "A Reset button on the module page clears the counter by hand." Do the same in the uk file. The caller's note says the code lacks a manual reset button. The code has one on the module page, so the text must not say that no button exists.
2. Missed features (true, not in the text):
   - "Clear selection" button (`button_clear_selection`).
   - Warning when the cron link is too long (`warning_cron_link_long`).
   - The About tab.
   - The cron link filters: the link uses the filters selected on the Website content tab, and it can be copied from a collapsed card there. The text says only "Copy the cron link from the Website content tab". Add that the link uses the selected tables, mode and filters.
   - A cron call repeats until `done` is `true`. The text does not say this, and it matters for a first-time user. Add it.

### Minor

1. Label mismatch: both files say "Show and Hide buttons" (uk: "Показати ключ" and "Сховати ключ" are used in uk, so uk is correct). The en-gb labels are "Show key" and "Hide key". Write "Show key and Hide key buttons" in the en file.
2. The en file calls the setting "Excluded routes" (card title, correct). The field label is "Do not load the module on these pages". This is acceptable. uk uses the card title "Виключені маршрути" (correct).
3. The line "HTML texts stay HTML: the module sends them to Google as HTML" is true only if the text contains `</` (`translate_expert.php:464-466`). Other texts go as `text`. Suggest: "The module detects HTML texts and sends them as HTML."
4. The "Debug tab ... reset of the translation cache" line is correct. The card is "Cache" and the action is "Reset translation cache". Both files also say the Debug tab "can reset this memory" (same-translations memory). The cache reset calls `uninstall()` of the model, which removes the saved translation data. Confirm in the text that this includes the same-value memory. This was not fully verified (I did not read `uninstall()`). Mark as unverified.
5. "Works with OpenCart 1.5 to 4.1" and the Compatibility list agree. Fine.
6. The image comment lists 15 names and the range `500x500_1.png .. 500x500_8.png`. `images/` holds 18 files, not 17. The one not listed is `TranslateExpert_ru_710x380.png`. It exists and is not an error. The established count of 17 is off by one.
7. The uk file mixes "Вашого" (capital) with the neutral tone elsewhere. Style only.
8. The Cron sentence "Each call translates one batch and returns JSON with ..." omits `message` and `details`, which the answer has on `translate_failed`. Optional.

## Claims I could not fully verify

- "Only active products ... quantity above 0": the label in the code is "quantity > 0". Verified by label only. The SQL was not read.
- "PHP expressions in the values stay unchanged": the localization library marks `expression` values (line 256). The skip is likely, but I did not read the translate path.
- "Maintenance mode blocks the link": the cron help text says so. No code in `sources/` checks it. The claim relies on OpenCart core. Not verified with the core probe.
- "Google can give new accounts a free trial credit": external fact, worded safely.
- Translate-button support on OC 1.5 to 3.0 is not stated, so nothing to check.

## Assessment

**Ready to merge?** With fixes. The facts match the code and the two files are consistent. Fix the missing manual Reset button (important 1) and the "Show key" label (minor 1). The other points are optional.
