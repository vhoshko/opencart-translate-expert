# Review: PoC admin design (issue #2)

Scope: `docs/pocs/2-admin-design/{README,design-concept,endpoints-json}.md`. Checked against `sources/upload/**` on the working tree (commit `ef275eb`). No files other than this review changed.

## Strengths

- The endpoint inventory is correct. All 10 AJAX methods exist at the cited CTRL lines (419, 507, 536, 560, 599, 630, 648, 685, 698, 768). The 12 `addHeader` calls are correct. The 5 HTML-returning endpoints (#3, #4, #5, #6, #8) are the right set. `translate`, `translateTable` and `saveLocalizationFile` have no HTML fields.
- The cited JS and CORE line references are accurate. These are JS:878, 1008, 1054, 1101 and 983-1026, CORE:98-135, 132-133 and 483-518, and LOC:365 (stray `</div>`). The test lines 411, 445, 513 and 524 are also accurate.
- The "one target language" claim is correct. `analize()` filters with `if ($languageIdTo != $langIdTo) continue;`.
- The modal claim is correct. The source JS already feature-detects `bootstrap.Modal` (JS:593-620).
- The `?v=` cache-bust claim is correct (CTRL:925-926 for OC4, `OpenCartTranslateExpertClient.ocmod.xml:30-31` for OCMOD).
- The proposed JSON can be built on PHP 5.6. It uses plain arrays, ints and strings. The `keys[]` array fixes the ordering problem of the keyed `toTranslate` object.
- The grep claim that only CORE, LOC, CTRL, JS and the localization test read the HTML fields is correct.
- The 6 tab ids match both `.tpl` and `.oc4.twig`. The settings, cron, debug and localization mock-ups map to real controls.
- The (unconfirmed) items resolve as follows:
  - `totalFiles`: CONFIRMED known. `analizeLocalization()` builds the full `$analize` list and then slices it. `count($analize)` is available in batch 0.
  - `deletedRows`: `removeDataFromTable()` (`client_translate_expert.php:927`) discards the query result. The code can call `$db->countAffected()` after it with no extra query. OC 1.5 support is not verified here (no core probe was run).

## Issues

### Critical (must fix)

None. This is a docs-only PoC, and no defect breaks the result.

### Important (should fix)

1. **`analizeLocalization` JSON loses data that the UI needs** (`endpoints-json.md` 3.4).
   - `keys[]` has `key`, `textFrom`, `textTo` and `expression`. It drops `full_text`. The current JS uses `value.full_text` (JS:216) to copy expression statements into the target file. The server uses `full_text` to put the `<b>` highlight in `contentFrom` (`highlightToTranslate`, LOC:399). The doc says the front end does the highlight, but it gives no data for it. Add `fullText` to each key.
   - Dropping `html` also drops the totals block. The server computes `fileCount`, `needToTranslateFileCount`, `needToTranslateValueCount` and `needToTranslateCharCount` over ALL files (`calcAnalizeTotals`, LOC:370). It shows them in batch 0 and uses `needToTranslateFileCount > 0` to decide whether to show "Translate all". A front end that sums per-batch data gets these totals only at the end. Fix: add a `totals` object to batch 0, or document the summing and the late "Translate all" button. Note 4 ("The front end makes the totals") hides this.
   - `textTo` in `keys[]` is always empty. A key is in `toTranslate` only when it is missing in the target. Remove the field or say it is always empty.

2. **Wrong claim: BS5 has no `col-xs-*`, so the columns are "full width on OC 4.x"** (`design-concept.md` section 1, Grid row, and `endpoints-json.md` section 2, item 2). The same server HTML also writes `col-sm-*`, `col-md-*` and `col-lg-*` (CORE:98-162). BS5 defines these classes. The columns are only full width below 576 px. The layout problem is real, but it is smaller than the text says. Reword it.

3. **Wrong claim: "On OC 4.x, `build.sh` rewrites the image path today"** (`endpoints-json.md` 3.0, item 5). The `build.sh` image rewrite (lines ~400-410) covers the translate icon (`view/image/translate_expert_client/`) and the `heading_title` icon. It does not cover language flags. The flag path comes from `getLangImgUrl()` (CORE:820):
   - OC before 2.2: `view/image/flags/<name>`.
   - OC 2.2 and later: `language/<code>/<code>.png`.

   The example `"image": "language/uk-ua/uk-ua.png"` is only correct for OC 2.2+. Define `image` as "the value of `getLangImgUrl()`, relative to the admin folder". Also check how OC 4.x resolves it. The OC4 JS calls with a `../extension/...` base for the module images.

4. **No escaping/entity contract for the text fields** (`analizeTableDetail` `textFrom`, `textTo`, and `analizeLocalization` `contentFrom`, `contentTo`, `textFrom`).
   - OC stores most descriptions HTML-entity-encoded, because `request->clean` applies `htmlspecialchars`.
   - The current `analizeTableDetail` inserts the values unescaped.
   - With `.text()`, the user sees literal `&lt;p&gt;`.
   - The controller `translate()` and `saveLocalizationFile()` call `html_entity_decode()` on input. A front end that sends `textFrom` or `contentTo` from JSON must know if the value is raw or encoded.

   Add one rule to 3.0: "Send the raw database or file value. The front end shows it as text." Also say which endpoints decode on input.

5. **`te_i18n` does not reach the header char-count item** (`design-concept.md` section 12 and section 5 note 1; `endpoints-json.md` 3.5).
   - The header item shows on ALL admin pages. The JS is loaded by the OCMOD or event header (`client_translate_expert_header_html`), not by the module template.
   - Section 12 says the module template writes `document.te_i18n`. Other admin pages never get it.
   - Today the labels and the confirm text come inside `info`. The new design removes `info`, so the header item has no labels.
   - Also, `te-*` styles are scoped under `.te-app`. The header item is outside `.te-app`.

   Fix: add `te_i18n` for the small header set to the header injection (OCMOD `client_translate_expert_header_html` and the OC4 `eventHeaderViewAfter`). Or keep a small server string for the header. Style the header item with its own scope.

6. **Empty-result edge case in `analizeTableDetail`** (3.1). Today the source and target flags come from the first row (`$textToTranslateRows[0]`, CORE:114-116). With zero rows, the proposed JSON has no source of `languageFrom`/`languageTo` in the example. State that both objects are always present and come from the request ids, and that `idColumns` can be `[]`.

### Minor (nice to have)

1. **Inconsistent language bar** (`design-concept.md` section 5). The `te-langbar` is above the tab row, but the note says "only on the 3 translation tabs". A bar above the tab row shows on every tab unless JS toggles it. Put the bar inside each translation tab, or state that JS toggles it. Also note this changes behavior. Today each of the 3 tabs has its own independent selects (`te-analization-language-*`, `te-localization-language-*`, `te-admin-localization-language-*`). The "Remove translations" action uses only the "to" language.
2. **README decision 4 versus design 7.3/7.4 and the modal.** README says "Progress shows inline, not in a modal". Design 7.3 keeps the modal for the detail view. The module JS also uses the modal on OTHER admin pages for editor translate (`showTranslateExpertModal`). Say that the modal stays for those pages.
3. **State object does not cover multi-table translate.** "Translate all" runs `doTranslateTableExpertRecursive` over `document.tablesToTranslate`, and the stats line reads `#statusCurrent .statusTranslatedCharCount` from the DOM (JS:794). The rule "no counters in the DOM" (README decision 5) must include the translate loop, not only analysis. Add `translate` state to section 4, item 3 of `endpoints-json.md`. Also, `js_const_statistic_html` is an HTML template in a language string. List it next to `entry_server_translation_char_count_info` as server-supplied HTML.
4. **JSON types.** The examples use `"done": 0` and `"success": 1`. The PHP returns booleans in some places: `analizeLocalization` `done` is a bool, and `removeDataFromDb` and `translatedCharCountInfo` give `success: true`. The rule "use 0 in all endpoints" for errors is stated only for `removeDataFromDb`. Add one rule for all booleans (`success`, `done`, `expression`, `targetExists`). The proposed `timeInSec` for `removeDataFromDb` is new. The code has a formatted string (`number_format`), so cast it to float.
5. **Mock-up detail gaps.**
   - "Try a translation" is shown with 2 fixed languages and buttons. The real field is one textarea for each store language, and the JS adds the translate buttons.
   - The Debug mock omits the "View" button state (log not loaded) and the log warning alert.
   - Per-table "Translate" and the "Translate all" button need the confirmation text. Keep the confirmation in the mock-up notes.
6. **Stale or partial paths.** `OpenCartTranslateExpertClient.ocmod.xml:30-31` is `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml`. Give the full path in the short-name list. The line numbers will go stale, so say "commit `ef275eb`" near each block, as the header already does.
7. **Host stack table (unverified).** The OC 3.0 "BS 3.3.5", OC 4 "jQuery 3.7.1, BS 5.3, FA 6" values are not checked against core. Run the OpenCart Core Source Probe before the table is used for decisions. For OC 1.5, `build.sh` line 125 confirms the module page loads jQuery 2.1.1, BS and FA only when the URL contains `module/client_translate_expert`. On other OC 1.5 admin pages, only jQuery 1.7.1 is present. Keep the header item free of BS3 classes there, and use only jQuery 1.7-safe calls (`.on`, `.text`).
8. **Writing style.** The three files follow the ASD-STE100 rules well. No semicolons or contractions were found. No clear violations to fix.

## Assessment

**Ready to merge?** With fixes.

The inventory, the line references and the main design are accurate and usable. Before this PoC drives the next tasks, fix Important items 1, 4 and 5. Items 1 and 4 lose data that the localization UI needs, and item 5 leaves the header item without labels. Correct the two false statements in Important items 2 and 3, so later work does not rely on them.
