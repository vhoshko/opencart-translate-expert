# Design concept

This file gives the new design concept for the module admin page. It covers all 6 tabs, the progress panel, the alerts and the mapping for each OpenCart version.

The mock-ups use ASCII. They show the layout and the order of the elements. They do not show the final colors or sizes.

## 1. Current state (summary)

| Item | Current state |
|---|---|
| Templates | 2 sources: `client_translate_expert.tpl` (BS3 markup, OC 1.5–3.0) and `client_translate_expert.oc4.twig` (BS5 markup, OC 4.x) |
| Tabs | `#tab-general`, `#tab-translate-site`, `#tab-localization`, `#tab-admin-localization`, `#tab-debug`, `#tab-about` |
| Dynamic content | The server builds HTML in `client_translate_expert_core.php` (40 lines with tags) and `client_localization_translate_expert.php` (30 lines with tags). The JS inserts it with `.html()` |
| Progress | One shared modal `#translateExpertModal` for all long operations. The modal title is the same for all operations |
| Counters | The JS reads the totals back from the DOM with `parseInt($(id).html())` |
| Errors | The JS inserts `.alert.alert-danger` before `#te-analization-mode`, also when the user is on a different tab |
| Grid | The server HTML uses BS3 classes such as `col-xs-3 col-sm-3 col-md-3 col-lg-3`. BS5 (OC 4.x) does not have `col-xs-*`, so on OC 4.x these columns are full width below 576 px. The layout of the result depends on the Bootstrap version |

## 2. Design principles

1. **One look on all versions.** The module looks the same on OC 1.5, 2.x, 3.0 and 4.x. Only the host frame (header, menu, tabs) follows the OpenCart version.
2. **Task first.** Each tab starts with the main task of the tab. Settings and filters that are not frequently used go into a collapsed "More filters" block.
3. **One language pair.** The tabs "Website content", "Catalog files" and "Admin files" use the same language pair. Show the pair once, in a bar above the tabs. The current code already keeps one pair in the session keys `te_langIdFrom` and `te_langIdTo`.
4. **Visible progress.** A long operation shows a progress panel in its tab. The user can see the result table under the panel while the operation runs.
5. **Keep the results.** An error does not clear the results. The error shows in the alert area of the tab.
6. **Data in, text out.** The front end gets JSON data. It puts values into the DOM as text. It never inserts server text as HTML.
7. **Keyboard and screen reader.** Each button has a text label. Each icon has a text alternative. The progress panel uses `role="status"` and `aria-live="polite"`.

## 3. The `te-*` component layer

The module CSS (`admin/view/stylesheet/client_translate_expert.css`) defines the components. All components are under the root element `.te-app`. Then the module CSS does not change other admin pages.

The components use CSS flex and grid. They do not use the Bootstrap grid classes. Then one JS renderer works on BS3 and on BS5.

| Component | Use | Main parts |
|---|---|---|
| `te-app` | Root of the module page content | Sets the font size, the spacing scale and the color variables |
| `te-summary` | Summary bar at the top of the page | Module status, API key status, monthly character meter, cost |
| `te-langbar` | Language pair bar | "From" select, swap button, "To" select |
| `te-card` | Block with a title and a body | `te-card__head`, `te-card__body`, `te-card__foot` |
| `te-form` | Form grid: label column and field column | `te-field`, `te-field__label`, `te-field__control`, `te-field__help` |
| `te-toolbar` | Row of actions | Primary action left, secondary actions right |
| `te-progress` | Progress panel of a long operation | Title, bar, counters, stop button, log |
| `te-table` | Result table | Sticky head, group rows, expandable rows, number columns right-aligned |
| `te-meter` | Small usage bar | Used value, limit, percent, color state `ok`, `warn`, `over` |
| `te-badge` | Small status label | States `ok`, `warn`, `error`, `muted` |
| `te-alerts` | Alert area at the top of each tab | `te-alert--error`, `te-alert--info`, `te-alert--success`, a "Details" toggle for long text |
| `te-diff` | Source and target text side by side | Source (read-only), target (editable), key name |

### Color variables

Use CSS custom properties on `.te-app`:

```css
.te-app {
  --te-gap: 12px;
  --te-radius: 4px;
  --te-border: #dcdfe4;
  --te-muted: #6b7280;
  --te-ok: #1f8f4e;
  --te-warn: #b7791f;
  --te-error: #c53030;
  --te-accent: #1e6fd9;
}
```

The host theme sets the button and the input colors. The module uses the variables only for its own components (meter, badges, progress, table borders).

## 4. Mapping for each OpenCart version

| OpenCart | Host stack | Template | Host frame from the template | Module components |
|---|---|---|---|---|
| 1.5 | jQuery 1.7.1, no Bootstrap. The module page loads its bundled BS 3.3.5, FA 4.4 and jQuery 2.1.1 | `.tpl` | BS3 tabs and modal | `te-*` |
| 2.0–2.3 | jQuery 2.1.1, BS3 | `.tpl` | BS3 `panel`, `nav-tabs`, `data-toggle` | `te-*` |
| 3.0 | jQuery 2.1.1, BS 3.3.5, FA 4.7 | `.tpl` (rendered with `template_engine=template`) | BS3 | `te-*` |
| 4.0 / 4.1 | jQuery 3.7.1, BS 5.3, FA 6 | `.oc4.twig` | BS5 `card`, `nav-tabs`, `data-bs-toggle` | `te-*` |

Rules:

1. The template writes the host frame only: the page header, the tabs, the form controls with the host classes and the modal.
2. The JS renderers write `te-*` markup only. They do not write `col-*`, `panel` or `card` classes.
3. For a Bootstrap feature in JS (tabs, modal, collapse), use one small adapter. The current modal code already does this: it uses `bootstrap.Modal` if it exists, and the jQuery `.modal()` plugin if not.
4. Use icons only through one helper, `teIcon(name)`. The helper gives `fa fa-<name>` on FA 4 and `fa-solid fa-<name>` on FA 6. If an icon name is not the same in FA 4 and FA 6, the helper maps it. Each icon also has a text label.

## 5. Page frame

```
+--------------------------------------------------------------------------+
| OpenCart header and menu (host)                                           |
+--------------------------------------------------------------------------+
| Translate Expert                                  [ Save ]  [ Cancel ]   |
+--------------------------------------------------------------------------+
| te-summary                                                                |
|  Status: [ok] Enabled   API key: [ok] Set   This month: 300 000 / 500 000 |
|  [############--------] 60 %   Cost over free limit: $0.00   [Reset]      |
+--------------------------------------------------------------------------+
| [Settings] [Website content] [Catalog files] [Admin files] [Debug] [About]|
+--------------------------------------------------------------------------+
| te-langbar (under the tab row, visible only on the 3 translation tabs)    |
|  Translate from [ English    v ]  [<->]  to [ Ukrainian  v ]              |
+--------------------------------------------------------------------------+
| te-alerts (alerts of the active tab)                                      |
| ...tab content...                                                         |
+--------------------------------------------------------------------------+
```

Notes:

1. `te-summary` replaces the character count item that the JS adds to the header menu on the module page. On other admin pages, the header item stays. The header item is outside `.te-app`, so it uses its own root class `te-header-count`. The module template does not load on other admin pages, so the labels of the header item come from the header injection (see section 12).
2. The `te-langbar` is one element under the tab row. The JS shows it when the active tab is "Website content", "Catalog files" or "Admin files". It hides it on the other tabs.
3. The data for `te-summary` comes from `translatedCharCountInfo` as JSON (see `endpoints-json.md`, section 3.5).
4. The "Reset" button opens a confirmation. The current code uses an inline `onclick=confirm()` in a language string. The new design uses a JS handler.
5. The tab names are shorter. The language files keep the current keys (`tab_general`, `tab_translate_site`, `tab_localization`, `tab_admin_localization`, `tab_debug`, `tab_about`). Only the values change.

## 6. Tab "Settings" (`#tab-general`)

```
+-- te-card: Connection -------------------------------------------------+
| Status            [ Enabled  v ]                                        |
| Google API key    [ ****************************  ] [Show]              |
|                   help: where to get the key                            |
+------------------------------------------------------------------------+
+-- te-card: Character usage ---------------------------------------------+
| Characters this month   1 234 567 (read-only)                           |
| [x] Show the character count on all admin pages                         |
| [x] Reset the count at the start of each month                          |
+------------------------------------------------------------------------+
+-- te-card: Scheduled translation (cron) --------------------------------+
| Rows for each run   [ 100 ]                                             |
| Cron secret         [ ab12cd34...       ] [Generate]  (unsaved)         |
+------------------------------------------------------------------------+
+-- te-card: Excluded routes ---------------------------------------------+
| [ textarea, one route for each line                               ]     |
+------------------------------------------------------------------------+
+-- te-card: Try a translation (collapsed) -------------------------------+
| One row for each store language:                                       |
| <language> [ textarea                                ] [Translate]      |
+------------------------------------------------------------------------+
```

Notes:

1. Group the fields by topic in cards. The current tab is one long form.
2. The cron link stays in the "Website content" tab, because it depends on the filters of that tab.
3. The "Try a translation" card is collapsed by default.

## 7. Tab "Website content" (`#tab-translate-site`)

### 7.1 Before an analysis

```
+-- te-card: What to translate -------------------------------------------+
| Mode     (o) Empty and identical values                                 |
|          ( ) Empty values only                                          |
|          ( ) Identical values only                                      |
| Tables   [ product_description, category_description ...  ] [All][None] |
|          help: 3 of 25 selected, maximum 10                             |
| [ ] Deep analysis                                                       |
| > More filters (collapsed)                                              |
|   Product status [x] enabled only    Quantity [x] > 0 only              |
|   Categories [ ... ]  Stock status [ ... ]  Manufacturers [ ... ]       |
| te-toolbar: [ Analyze ]                         [ Remove translations ] |
+------------------------------------------------------------------------+
+-- te-card: Cron link (collapsed) ---------------------------------------+
| https://shop/index.php?route=...&secret=...            [Copy]           |
| help / error / "link is too long" message                               |
+------------------------------------------------------------------------+
```

Notes:

1. Use radio buttons for the mode, because there are only 3 options.
2. "Remove translations" is a dangerous action. Show it on the right side, with the danger style, and keep the confirmation.

### 7.2 During an analysis

```
+-- te-progress (role=status) --------------------------------------------+
| Analysis in progress: product_description.description                   |
| [###############------------------]  request 14   table 3 of 7          |
| Characters found: 845 210     Time: 12.4 s                     [ Stop ] |
| > Log (collapsed)                                                       |
+------------------------------------------------------------------------+
+-- te-table: Results (rows are added while the analysis runs) ----------+
| Table / column                       | Characters (uk-ua) | Actions          |
|--------------------------------------+--------------------+------------------|
| v product_description                |            612 000 | [Translate]      |
|     name                             |             12 000 | [Details] [Tr]   |
|     description                      |            600 000 | [Details] [Tr]   |
| > category_description               |             33 210 | [Translate]      |
|--------------------------------------+--------------------+------------------|
| Total                                |            645 210 | [Translate all]  |
+------------------------------------------------------------------------+
```

Notes:

1. The table has one count column, for the target language of the `te-langbar`. The current `analize()` also returns counts only for `languageIdTo` (`client_translate_expert_core.php`, the `if ($languageIdTo != $langIdTo) continue;` checks). The column head comes from `target` in the JSON (see `endpoints-json.md`, section 3.2).
2. Each column row has a "Details" link (opens the detail view) and a "Translate" button.
3. A table row expands and collapses its column rows.
4. The JS adds new rows with `append()` of DOM nodes. It does not re-parse the table.
5. The total row and the progress counters come from the JS state object.

### 7.3 Detail view of a column

The detail view uses the modal, because it is a short look-up. The modal title names the table and the column.

```
+-- modal: product_description.description -------------------------------+
| ID   | Source (en-gb)                 | Target (uk-ua)                   |
|------+--------------------------------+----------------------------------|
| 42   | Red cotton t-shirt ...         | (empty)                          |
| 43   | Blue jeans ...                 | Blue jeans ...  [identical]      |
|                                                        [ Close ]        |
+------------------------------------------------------------------------+
```

### 7.4 Translation of a column

The translation uses the same `te-progress` panel as the analysis. The panel shows the column name, the translated characters and the stop button. When the translation is done, the column row shows a `te-badge` with the state `ok`.

The current translation loop builds its modal from the language string `js_const_statistic_html`, which is HTML. It reads its counters back from the DOM (JS:794). The new design replaces this string with the `te-progress` component and keeps the counters in the JS state object (see `endpoints-json.md`, section 4).

## 8. Tabs "Catalog files" and "Admin files"

Status: done in issue 59. The text below is the plan. These notes give the state after issue 59.

- The key list is the main view. The source file is in a collapsed "Source file" block. The JS highlights the keys with text nodes.
- The table shows only files with at least 1 key.
- The JS builds the saved file with single-quoted literals. A new file starts with `<?php`.
- "Translate all" runs in the `te-progress` panel. The Stop button works. Each tab has its own run. The languages stay as they were at the start of the analysis.
- There is no "Source folder" line.
- "Translate file" and "Save file" are in the expanded key list.

The two tabs have the same layout. The tab `#tab-localization` uses the scope `catalog`. The tab `#tab-admin-localization` uses the scope `admin`.

```
+-- te-card: Language files ----------------------------------------------+
| Source folder: catalog/language/en-gb   Target: catalog/language/uk-ua  |
| te-toolbar: [ Analyze files ]                    [ Translate all ]      |
+------------------------------------------------------------------------+
+-- te-progress (only while the analysis runs) ---------------------------+
| Files 30 of 212   [####-------------------]                     [Stop]  |
+------------------------------------------------------------------------+
+-- te-table: Files -----------------------------------------------------+
| File                                  | Keys to translate | State      |
|---------------------------------------+-------------------+------------|
| > account/login.php                   |                 4 | [missing]  |
| v checkout/cart.php                   |                 2 | [partial]  |
|   te-diff                                                              |
|   key             | en-gb                 | uk-ua                      |
|   text_empty      | Your cart is empty!   | [ textarea              ]  |
|   button_update   | Update                | [ textarea              ]  |
|   [ Translate file ]  [ Save file ]                                    |
| > extension/opencart/module/... (OC 4.x extension root)                 |
+------------------------------------------------------------------------+
```

Notes:

1. The file table shows one row for each file with keys to translate. The state badge is `missing` (no target file), `partial` (some keys) or `ok` (saved).
2. The expanded row shows a `te-diff` list of the keys, not the whole file. The full file text stays in the JS state, because the save action needs it. A key with `expression` set (the value is PHP code, not one string) shows as read-only. The translation keeps its source statement (`fullText`) as it is, as the current code does (JS:206).
3. The card above the table shows the totals of the first batch: file count, files to translate, values to translate and characters to translate. "Translate all" shows only if there are files to translate.
4. The JS makes the row structure. It does not need `parent().parent().next()` chains. Each row has a `data-index` attribute that points into the state array.
5. On OC 4.x, the extension roots show as a group heading with the root name (`rootName`).

## 9. Tab "Debug" (`#tab-debug`)

```
+-- te-card: Versions ----------------------------------------------------+
| Module 04.01.xxxx    OpenCart 4.0.2.3    PHP 8.1.27                     |
+------------------------------------------------------------------------+
+-- te-card: Cache -------------------------------------------------------+
| Remove the cache of identical translations.          [ Reset cache ]   |
| (success message shows in te-alerts)                                    |
+------------------------------------------------------------------------+
+-- te-card: Debug log ---------------------------------------------------+
| Debug mode  (o) On  ( ) Off                                             |
| [ View ]  [ Download ]  [ Clear ]                                       |
| [ read-only textarea with the log                                ]      |
+------------------------------------------------------------------------+
```

Notes:

1. Show the versions as plain text, not as `<strong class="form-control">`.
2. "Reset cache" shows a success message. The current code gives no feedback.

## 10. Tab "About" (`#tab-about`)

No change to the content. Put the text of `text_about_page` in a `te-card`.

## 11. Alerts

```
+-- te-alerts -------------------------------------------------------------+
| [x] Google API error: API key not valid.                  [Details] [x] |
|     Details (collapsed): additional_message, HTTP status, route         |
+------------------------------------------------------------------------+
```

Rules:

1. Each tab has its own `te-alerts` area. An alert shows in the tab that started the operation.
2. The JS puts the message in as text.
3. If the response is not JSON (for example the OpenCart login page or a PHP error page), show one short message: "The server did not send a JSON answer." Put the response text in the "Details" block as text, cut to 2000 characters.
4. A `.fail()` handler does not clear the results.

## 12. Strings for the front end

The JS renderers need many labels (column heads, badges, buttons). The current code gives labels to the JS through 11 separate `document.js_const_*` globals.

Proposal:

1. The controller makes one array `te_i18n` from the language file.
2. The template writes it once as `document.te_i18n = { ... };` with JSON encoding.
3. The JS uses one helper, `teT(key, params)`, that replaces `{name}` placeholders.

Then the English strings in the JS (`title="Translate"`, `'Internal error, more info in console log!'`) can move to the language files.

`document.te_i18n` exists only on the module page, because only the module template writes it. The header character count item and the translate buttons show on all admin pages. For these items, the header injection (the OCMOD patch of `common/header` on OC 1.5–3.0 and `eventHeaderViewAfter` on OC 4.x) writes a second, small object `document.te_i18n_global`. It has only the labels of these items, for example the count label, the reset label and the reset confirmation. The header injection writes 3 `document.js_const_*` globals today, so this is the same mechanism.
