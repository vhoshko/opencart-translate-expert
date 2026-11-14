# AJAX endpoints: inventory and JSON formats

This file records all AJAX endpoints of the module admin page. It shows which endpoints return HTML. For each of these endpoints, it gives a JSON format with data only.

Short path names:

- CTRL: `sources/upload/admin/controller/extension/module/client_translate_expert.php`
- CORE: `sources/upload/system/library/client_translate_expert_core.php`
- LOC: `sources/upload/system/library/client_localization_translate_expert.php`
- LIB: `sources/upload/system/library/client_translate_expert.php`
- JS: `sources/upload/admin/view/javascript/client_translate_expert.js`
- LANG: `sources/upload/admin/language/en-gb/extension/module/client_translate_expert.php`

The line numbers are from the `main` branch on 14.11.2026 (commit `ef275eb`).

## 1. Inventory

All endpoints set `Content-Type: application/json; Charset=UTF-8` (12 `addHeader` calls in CTRL). The JS calls them with `$.get` or `$.post`, with no `dataType`. jQuery parses the JSON because of the header.

| # | Method (CTRL line) | HTTP | Response now | HTML fields | JS caller |
|---|---|---|---|---|---|
| 1 | `translate` (419) | POST | JSON | none | `doTranslateExpert`, `doTranslateLocalization` |
| 2 | `translateTable` (507) | POST | JSON | none | `doTranslateTableExpertRecursive` |
| 3 | `analizeTableDetail` (536) | POST | JSON with HTML | `result` | `analizeTableDetail` |
| 4 | `analize` (560) | GET | JSON with HTML | `analizeHeadInfo`, `result[].headerHTML`, `result[].columns[].html`, `timeInSecInfo` | `makeAnalizeRequest` |
| 5 | `removeDataFromDb` (599) | GET | JSON with HTML | `body`, `timeInSecInfo` | `makeRemovingDataFromDbRequest` |
| 6 | `analizeLocalization` (630) | GET | JSON with HTML | `html`, `detailsHtml` | `makeLocalizationAnalizeRequest` |
| 7 | `saveLocalizationFile` (768) | POST | JSON | none | `doSaveLocalizationExpert` |
| 8 | `translatedCharCountInfo` (648) | GET | JSON with HTML | `info` | `showTranslatedCharCount` |
| 9 | `resetCharCount` (685) | GET | JSON | none | `resetTranslationCharCount` |
| 10 | `resetCache` (698) | GET | JSON | none | `resetTranslationCache` |

`downloadDebugLog` (791) and `clearDebugLog` (812) are not AJAX. They are plain links (a file download and a redirect).

5 endpoints return HTML: #3, #4, #5, #6 and #8.

## 2. Problems of the current HTML responses

1. **The server and the design are coupled.** A new design needs changes in CORE and LOC, not only in the templates and the JS.
2. **BS3 grid on OC 4.x.** CORE builds classes such as `col-xs-3 col-sm-3 col-md-3 col-lg-3` (CORE:98-135 and the `$nameColClass` and `$langColClass` values in `analize()`). BS5 does not have `col-xs-*`, so on OC 4.x the columns are full width below 576 px. The `col-sm-*` and larger classes work on BS5.
3. **Re-parse of the DOM for each batch.** JS:1008 runs `$('#analizeBody').html($('#analizeBody').html() + bodyHtml)`. JS:878 runs `$table.html($table.html() + data.detailsHtml)`. Each batch serializes and parses the full result again. This also removes the text that the user typed into a textarea of a file row while the next batches load.
4. **Counters in the DOM.** The JS reads the totals back with `parseInt($(id).html())` (JS:983-1026).
5. **Inline event handlers.** `buildHtmlTranslateTableButton` (CORE:483-518) and `buildAnalizeDetailedHtml` (LOC:288-368) write `onclick="..."` with arguments in a JS string. The language string `entry_server_translation_char_count_info` (LANG:88-92) also has an inline `onclick`.
6. **Values without escaping.** `analizeTableDetail` writes `text_from` and `text_to` from the database into the HTML without escaping (CORE:132-133).
7. **An unmatched closing tag.** `buildAnalizeDetailedHtml` starts with `$html = ''` and ends with `$html .= '</div>'` (LOC:365). Each batch appends one more `</div>`.
8. **The `.fail()` handlers clear the results.** JS:1054 and JS:1101 run `$('#te-analization-result').html(data)` with the jqXHR object. This clears the partial results.
9. **Data sent two times.** `analizeLocalization` sends the full file text in `analize[]` and again, escaped, in `detailsHtml`.

## 3. Proposed JSON formats

### 3.0 Common rules

1. **No HTML in a response.** All values are data: numbers, strings, booleans, arrays and objects. Strings are plain text.
2. **Numbers are numbers.** Send counts as integers. Do not send formatted strings such as `1`234`. The front end formats the numbers.
3. **Labels come from the front end.** The front end gets the labels from `document.te_i18n` (see `design-concept.md`, section 12). The server sends a label only if the text is dynamic (for example a progress message).
4. **Flags are integers.** Send `success` and `done` as `0` or `1` in all endpoints. Send `timeInSec` as a number. Today some endpoints send `true` or `false`, and `removeDataFromDb` sends the time inside an HTML string.
5. **Text is decoded, and the front end inserts it as text.** OpenCart stores the database values with HTML entities (for example `&lt;p&gt;`). The server calls `html_entity_decode($value, ENT_QUOTES, 'UTF-8')` on each database text before it sends it. The front end inserts the text with `.text()` or `.val()`. The user then sees the real value, with the HTML tags as text. Language file contents (`contentFrom`, `contentTo`, `fullText`) are raw file text. The server sends them with no change. The current code also decodes on input: `translate` and the filters in CTRL call `html_entity_decode()` (CTRL:421-439, 482).
6. **Same error format for all endpoints.** Keep the current fields, because the front end and the tests already use them:

   ```json
   {
     "success": 0,
     "error_code": 3003,
     "message": "Plain text message",
     "additional_message": "Plain text details"
   }
   ```

   `error_code` and `additional_message` are optional.
7. **Language objects have one shape.** Use the same object everywhere:

   ```json
   { "language_id": 2, "code": "uk-ua", "name": "Українська", "image": "language/uk-ua/uk-ua.png" }
   ```

   The server makes `image` with the current `getLangImgUrl()` (CORE:820-826). The value depends on the OpenCart version: `view/image/flags/<image>` below OC 2.2, and `language/<code>/<code>.png` from OC 2.2. The example above is the OC 2.2+ form. The front end uses the value as it is.
8. **The request does not change.** Each endpoint keeps its route, its HTTP method and its request parameters. Only the response changes.

### 3.1 `analizeTableDetail`

Now: `{ "success": 1, "result": "<HTML>" }`. The HTML is a BS3 grid with a head row, the ID columns, `text_from` and `text_to` (CORE:98-135).

Proposed:

```json
{
  "success": 1,
  "table": "product_description",
  "column": "description",
  "mode": "both",
  "languageFrom": { "language_id": 1, "code": "en-gb", "name": "English", "image": "..." },
  "languageTo":   { "language_id": 2, "code": "uk-ua", "name": "Українська", "image": "..." },
  "idColumns": ["product_id"],
  "rows": [
    { "ids": { "product_id": 42 }, "textFrom": "Red cotton t-shirt", "textTo": "" },
    { "ids": { "product_id": 43 }, "textFrom": "Blue jeans", "textTo": "Blue jeans" }
  ]
}
```

Notes:

1. `idColumns` keeps the order of the ID columns. The current code takes the first `count($row) - 7` keys of each row as ID columns (CORE:116-117).
2. `mode` is the key. The front end shows the label `value_analization_mode_<mode>` from `te_i18n`.
3. The front end shows "identical" when `textFrom` equals `textTo`, and "empty" when `textTo` is empty.
4. **Zero rows.** The current code takes the source language from row 0 (CORE:114-115). If there are no rows, the server must still send `languageFrom` and `languageTo`. Make them from the request parameters `langIdFrom` and `langIdTo` and the store language list. Then `rows` is an empty array.
5. `textFrom` and `textTo` are decoded text (see rule 5 in section 3.0).

### 3.2 `analize`

Now: a mix of data and HTML (CORE:269-282). The JS uses the data fields for the counters and the HTML fields for the layout. The `tables` field is not used by the JS.

Proposed:

```json
{
  "success": 1,
  "done": 0,
  "target": { "language_id": 2, "code": "uk-ua", "name": "Українська", "image": "..." },
  "globalCharCount": 645210,
  "timeInSec": 12.4,
  "tables": [
    {
      "table": "product_description",
      "charCount": 612000,
      "columns": [
        { "column": "name", "charCount": 12000 },
        { "column": "description", "charCount": 600000 }
      ]
    }
  ],
  "lastProcessedTable": "product_description",
  "lastProcessedColumn": "description",
  "lastProcessedOffset": 0
}
```

Notes:

1. **One target language.** The current `analize()` shows counts only for `languageIdTo`. So the proposed format has one `charCount` for each table and column, plus one `target` object. If later tasks need more target languages, add a `languageCharCounts` object keyed by `language_id`.
2. **Counts for this batch only.** `globalCharCount`, `charCount` and `timeInSec` are the values of this batch, as now. The front end adds them to its state object. A table can come in more than one batch (deep analysis with `continue_offset`). Then the front end adds the counts of the new batch to the existing table row.
3. **Actions come from the front end.** The front end makes the "Details" and "Translate" buttons for each row. It uses the request filters from its own state and `table` and `column` from the response. The server does not send `onclick` strings.
4. **Removed fields:** `analizeHeadInfo`, `headerHTML`, `html`, `timeInSecInfo`, the unused `tables` field with the raw `tablesResult` objects, and `globalLanguageCharCounts` (replaced by `target` and `globalCharCount`).
5. **Name conflict.** The current response has a field `tables` that is not used. The proposed format uses `tables` for the result list. Remove the old field in the same change.

### 3.3 `removeDataFromDb`

Now: `{ "success": true, "headerHTML": "<plain text>", "body": "line<br>line<br>...", "timeInSecInfo": "<HTML>" }`. The `body` is `implode('<br>', $results)` (CORE:341).

Proposed:

```json
{
  "success": 1,
  "target": { "language_id": 2, "code": "uk-ua", "name": "Українська", "image": "..." },
  "steps": [
    { "type": "start" },
    { "type": "table", "table": "product_description", "deletedRows": 120 },
    { "type": "column", "table": "product_description", "column": "description", "deletedRows": 120 },
    { "type": "finish" }
  ],
  "timeInSec": 0.8
}
```

Notes:

1. The front end shows each step as one log line, with a label from `te_i18n`.
2. `deletedRows` is optional. The review found that `removeDataFromTable()` does not use the query result, and `$db->countAffected()` can give the count after the query with no extra query. This is not checked on OC 1.5 (unconfirmed). If OC 1.5 does not give the count, send the step without it.
3. The error format stays `{ "success": 0, "message": "..." }`. The current error uses `success: false`. Use `0` in all endpoints.

### 3.4 `analizeLocalization`

Now: `analize[]` (data), `html` (totals block and an empty table, used only for batch 0) and `detailsHtml` (the file rows, LOC:288-368).

Proposed:

```json
{
  "success": 1,
  "done": 0,
  "nextFileIndex": 10,
  "totals": {
    "fileCount": 212,
    "needToTranslateFileCount": 37,
    "needToTranslateValueCount": 410,
    "needToTranslateCharCount": 18250
  },
  "languageFrom": { "language_id": 1, "code": "en-gb", "name": "English", "image": "..." },
  "languageTo":   { "language_id": 2, "code": "uk-ua", "name": "Українська", "image": "..." },
  "files": [
    {
      "root": "ext:opencart",
      "rootName": "opencart",
      "pathFrom": "extension/opencart/catalog/language/en-gb/payment/cod.php",
      "pathTo": "extension/opencart/catalog/language/uk-ua/payment/cod.php",
      "targetExists": false,
      "keys": [
        { "key": "text_title", "text": "Cash On Delivery", "fullText": "$_['text_title'] = 'Cash On Delivery';", "expression": 0 }
      ],
      "contentFrom": "<?php\n$_['text_title'] = 'Cash On Delivery';\n",
      "contentTo": ""
    }
  ]
}
```

Notes:

1. `files[]` replaces `analize[]`. The fields come from the current `analize[]` items: `root`, `rootName`, `pathFrom`, `pathTo`, `targetExists`, `contentFrom`, `contentTo` and `toTranslate`.
2. `keys[]` replaces the `toTranslate` object. The current object is keyed by the language key and has `text`, `full_text` and an optional `expression` (LOC:456-531). Keep all 3 values as `text`, `fullText` and `expression`. Use an array, so that the order stays the same in all PHP versions and in JSON.
3. `fullText` is necessary. The JS copies it as it is for an `expression` key (JS:206). The front end also uses it to highlight the keys in `contentFrom`. Today the server does this highlight with `<b>` (LOC:399-407).
4. There is no target text for each key. A key is in `keys[]` because the target file does not have a translation for it.
5. `totals` replaces the totals block of `html` (`buildAnalizeHtml`, LOC:243-286). The server computes it over all files in the first batch, as now. Send `totals` only when the request has `nextFileIndex` = 0. The review found that `count($analize)` is known before the batch slice, so `totals.fileCount` also gives the progress panel its maximum.
6. **Removed fields:** `html`, `detailsHtml`. The front end makes the totals and the rows.
7. The full text goes only one time, in `contentFrom` and `contentTo`. The save action needs the text, because the front end changes the target file text.
8. **Test impact.** `tests/test_client_translate_expert_localization.php` reads `$res->detailsHtml` (lines 411, 445, 513) and `$res->html` (line 524). The change of this endpoint must change these checks to the new `files[]` fields.

### 3.5 `translatedCharCountInfo`

Now: the data fields and the field `info`. `info` is the language string `entry_server_translation_char_count_info` with placeholders filled. It contains a `<div>` with an inline `onclick=confirm()...resetTranslationCharCount()` (LANG:88-92).

Proposed:

```json
{
  "success": 1,
  "charCount": 300000,
  "monthFreeLimit": 500000,
  "priceFor20MInUSD": 20,
  "costOverFreeLimit": 0
}
```

Notes:

1. The data fields exist now (CTRL:655-679). Only `info` is HTML.
2. **Removed fields:** `info`, `color`, `charCountDisplay`, `monthFreeLimitDisplay`, `costOverFreeLimitDisplay`. The front end formats the numbers and selects the `te-meter` state (`ok` if `charCount` <= `monthFreeLimit`, `over` if not).
3. The same data fills the header item on other admin pages and the `te-summary` bar on the module page.
4. The header item needs labels on all admin pages. Without `info`, the labels and the reset confirmation come from `document.te_i18n_global`, which the header injection writes (see `design-concept.md`, section 12).
5. The language string `entry_server_translation_char_count_info` is then not used. Remove it from the 3 language files in the same change.

## 4. Front-end rendering rules

1. **Make DOM nodes.** Use `$('<td>').text(value)` or `document.createElement`. Do not join HTML strings with values.
2. **Bind handlers in JS.** Use `$(root).on('click', '.te-js-translate', handler)` with `data-*` attributes. Do not write `onclick` attributes.
3. **Keep one state object for each operation.** Example for the analysis:

   ```js
   var state = {
     filters: { mode: 'both', tables: ['product_description'], langIdFrom: 1, langIdTo: 2 },
     target: null,
     tables: {},          // keyed by table name
     globalCharCount: 0,
     timeInSec: 0,
     requestNumber: 0,
     running: false
   };
   ```

   Each batch changes the state. Then one render function updates the changed rows and the totals. The table translation loop (`doTranslateTableExpertRecursive`) uses the same pattern. It keeps `translatedCharCount` in its state and does not read it from the DOM (now JS:794).
4. **Append, do not re-parse.** Add new rows with `append()`. Update a changed row in place.
5. **Keep the results on an error.** A `.fail()` handler shows the error in `te-alerts`. It does not clear the result area.
6. **Show a non-JSON answer as text.** If the response is a string (for example the OpenCart login page), show a short message. Put the text in the "Details" block with `.text()`.
7. **One running operation for each tab.** Disable the start button while `state.running` is true.

## 5. Migration

The JS, the CSS and the PHP are in the same OCMOD package. The admin header loads the JS and the CSS with `?v=<module version>` (`OpenCartTranslateExpertClient.ocmod.xml:30-31`, CTRL:925-926). So a new package gives a new JS URL, and the browser does not use the old JS from its cache.

For this reason, each endpoint can change in one step, together with its JS caller. A transition period with old and new fields is not necessary.

No other code reads the HTML fields. A search for `headerHTML`, `detailsHtml`, `analizeHeadInfo`, `timeInSecInfo` and `charCountDisplay` in `sources/`, `tools/` and `tests/` finds only CORE, LOC, CTRL, JS and `tests/test_client_translate_expert_localization.php`.

Proposed order (also in `README.md`):

1. `translatedCharCountInfo`: smallest change, the data fields exist now.
2. `removeDataFromDb`: small change, one log list.
3. `analizeTableDetail`: one modal view.
4. `analize`: the main table of the "Website content" tab.
5. `analizeLocalization`: the two localization tabs and the test changes.

For each step:

1. Change the endpoint and its JS caller together.
2. Check the result on OC 1.5, 2.3, 3.0 and 4.x.
3. Add the new route, class or setting names to the fixed rewrite lists in `build.sh`, if the step adds a name.
4. Run the tests and the release build.

## 6. Out of scope (found during the inventory)

These items are not about HTML in the responses. Record them for later tasks:

1. `removeDataFromDb` (#5) and `resetCache` (#10) change data, but they use GET.
2. `resetCache` (#10) gives no success message to the user.
3. The model (`admin/model/extension/module/client_translate_expert.php`) re-throws exceptions. Then a PHP exception gives an OpenCart HTML error page, not a JSON error.
