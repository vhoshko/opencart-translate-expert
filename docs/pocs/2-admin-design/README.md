# PoC: new admin page design (issue #2)

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/2

Status: PoC. This folder contains only Markdown. No file under `sources/upload/**` changes in this task.

## Goal

1. Give a new design concept for all tabs of the module admin page.
2. Make the concept apply to OpenCart 1.5–4.1.
3. Record all AJAX endpoints that return HTML. Give a JSON format for each of them, so that the front end makes the HTML elements of the new design.

## Files

| File | Content |
|---|---|
| [design-concept.md](design-concept.md) | Design principles, the `te-*` component layer, the mapping for each OpenCart version, ASCII mock-ups for all tabs and for the progress panel |
| [endpoints-json.md](endpoints-json.md) | Inventory of the 10 AJAX endpoints, the current HTML fields, the proposed JSON formats, the front-end rendering rules and the migration order |

## Main decisions

1. **One component layer for all versions.** The module CSS defines `te-*` components under the `.te-app` root. The components use CSS flex and grid. They do not use the Bootstrap grid. Then the same JS renderer works with BS3 (OC 1.5–3.0) and BS5 (OC 4.x).
2. **The templates keep only the host frame.** The `.tpl` and the `.oc4.twig` keep the tabs, the form controls and the modal of their Bootstrap version. All dynamic content comes from JS renderers.
3. **The server sends data, not markup.** Each endpoint returns JSON data only. The front end makes DOM nodes and puts text in with `.text()`. This removes the inline `onclick` handlers and the unescaped values in the server HTML.
4. **Progress shows inline, not in a modal.** Each long operation shows a progress panel at the top of its tab. The panel has a stop button and a log. The result stays on the page when an error occurs. The module page uses the modal only for the short detail view of a column.
5. **One state object in JS.** The counters (characters, time, request number) are in a JS object. The front end does not read them back from the DOM with `parseInt(.html())`.

## Out of scope for this task

- Changes to the templates, `client_translate_expert.js`, the CSS and the PHP libraries.
- The change of the endpoints to JSON. The next tasks do this work.

## Next tasks (proposed order)

1. Add the `te-*` CSS layer and the JS render helpers. Do not change the behavior.
2. Change `translatedCharCountInfo` and `removeDataFromDb` to JSON data (small endpoints).
3. Change `analizeTableDetail` and `analize` to JSON data, and make the "Translate site" tab with the new design. (done in issue 58)
4. Change `analizeLocalization` to JSON data, and make the two localization tabs with the new design. (done in issue 59)
5. Make the "Settings", "Debug" and "About" tabs with the new design.
6. Fix the out-of-scope items of `endpoints-json.md`, section 6, as one separate task after all other tasks:
   - Change `removeDataFromDb` and `resetCache` from GET to POST, because they change data.
   - Show a success message after `resetCache`.
   - Return a JSON error, not an OpenCart HTML error page, when the model re-throws a PHP exception.

Each step must keep OpenCart 1.5–4.1 working. Each step must pass the tests and the build.
