# 2-admin-design-poc

## Settings

- Planning model: Opus 5.5 (inline in the main conversation).
- Review model: Sonnet 5.5 (pass `model: sonnet` to `ote-code-reviewer`).
- Build: checks only (`php -l` and unit tests). No version bump and no release build, because the task changes no file under `sources/`. The user chose this on 14.11.2026.
- Commit, push, PR: requested by the user after Phase 7 on 14.11.2026 ("push to the same PR"). One PR holds the PoC commit and the workflow improvements commit.
- Standing auto-approve: yes. The user said "turn on autoapprove" on 14.11.2026. The plan checkpoint and the review findings continue without a question to the user.
- PoC content: design concept with ASCII mock-ups, plus the endpoint inventory and the JSON formats.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/2

The admin page of the module needs a new design. Before we change the module, we need a PoC as Markdown files in `docs/pocs/`. The PoC must not change files under `sources/upload/**`. The design must apply to OpenCart 1.5–4.1.

Some AJAX endpoints return HTML fragments. The front end inserts them as they are, or concatenates them with the existing HTML. The PoC must record these endpoints and give a JSON format for each. The change of the endpoints goes into the next tasks.

Investigation (Explore agent, facts checked with greps):

- 2 template sources: `.tpl` (BS3 markup, OC 1.5–3.0) and `.oc4.twig` (BS5 markup, OC 4.x). Both have 6 tabs: `#tab-general`, `#tab-translate-site`, `#tab-localization`, `#tab-admin-localization`, `#tab-debug`, `#tab-about`. One shared modal `#translateExpertModal`.
- 10 AJAX endpoints in `admin/controller/extension/module/client_translate_expert.php`. All set `Content-Type: application/json`.
- Endpoints with HTML in the JSON:
  - `analizeTableDetail`: `result` is HTML (`client_translate_expert_core.php:98-135`).
  - `analize`: `analizeHeadInfo`, `result[].headerHTML`, `result[].columns[].html`, `timeInSecInfo` (`client_translate_expert_core.php:269-282`, `buildHtmlTranslateTableButton` 483-518).
  - `removeDataFromDb`: `body` is `implode('<br>', ...)` (`client_translate_expert_core.php:341`), `timeInSecInfo`.
  - `analizeLocalization`: `html`, `detailsHtml` (`client_localization_translate_expert.php:76`, `buildAnalizeHtml` 243-286, `buildAnalizeDetailedHtml` 288-368).
  - `translatedCharCountInfo`: `info` (language string `entry_server_translation_char_count_info`).
- `client_translate_expert.js:1054` and `:1101` are `.fail()` handlers. They call `.html(jqXHR)`, which clears `#te-analization-result`.
- Host stacks: OC 1.5 has no Bootstrap (the module bundles BS 3.3.5, FA 4.4, jQuery 2.1.1). OC 2.x–3.0 have BS3 and jQuery 2.1.1. OC 4.0/4.1 have BS 5.3 and jQuery 3.7.1.
- The server HTML uses BS3 `col-xs-*`. BS5 does not have this class.

## Approach

Write 3 Markdown files in `docs/pocs/2-admin-design/`:

1. `README.md`: the goal, the scope, the file index and the decisions for the next tasks.
2. `design-concept.md`: the design principles, a small component layer `te-*` that does not depend on the Bootstrap version, ASCII mock-ups for all 6 tabs and for the progress panel, and the mapping for each OpenCart version.
3. `endpoints-json.md`: the inventory of all 10 endpoints, the current response shapes with the HTML fields, the proposed JSON format for each HTML endpoint, the front-end rendering rules and a migration order.

Design correction: the PoC proposes one module CSS layer, scoped under `.te-app`, with flex and grid layout. Then the JS renderers do not depend on BS3 or BS5 grid classes. The templates keep only the host frame (tabs, modal) of each Bootstrap version.

## Assumptions

- ✅ Host stacks for each OpenCart version: the Explore agent checked the OpenCart sources on GitHub. Font Awesome versions for OC 2.0–2.3 are not checked. The PoC uses only icons that FA 4.4 and FA 6 both have, or it uses no icons.
- ✅ All endpoints already send `Content-Type: application/json` (12 `addHeader` calls in the controller). So a change to pure JSON does not need a change of the transport.
- ❓ CSS flex and grid work in the browsers of the admin users. Not checked, because we have no usage data. If this is false on old browsers, the layout falls back to stacked blocks. This is acceptable for a PoC.

## Steps

- [x] Write `docs/pocs/2-admin-design/README.md`
- [x] Write `docs/pocs/2-admin-design/design-concept.md`
  Findings: the first mock-up of the analysis table had more than one language column. `analize()` keeps only `languageIdTo` (`if ($languageIdTo != $langIdTo) continue;`), so the mock-up now has one count column. The free monthly limit is 500 000 characters (CTRL:655), so the summary mock-up uses this value.
- [x] Write `docs/pocs/2-admin-design/endpoints-json.md`
  Findings: `tests/test_client_translate_expert_localization.php` reads `detailsHtml` (lines 411, 445, 513) and `html` (line 524). The PoC records this test impact. The JS and the CSS load with `?v=<version>`, so each endpoint can change in one step with its JS caller. `deletedRows` in `removeDataFromDb` and `totalFiles` in `analizeLocalization` are marked unconfirmed.
- [x] Checks only: `php -l` and unit tests (no bump, no build). Sanity run before the change: 6 scripts pass, 1076 assertions, 0 failed
  Findings: after the change, the 6 scripts pass again (1076 assertions, 0 failed, 6 integration tests skipped with no API key). Only `docs/pocs/` and `docs/tasks/2-admin-design-poc/` are new. `_version.txt` stays `04.01.0175`.
- [x] Independent review → `2-admin-design-poc-review.md`
  Findings: verdict "With fixes". 0 Critical, 6 Important, 6 Minor. The reviewer confirmed the inventory of 10 endpoints, the 5 HTML endpoints and the line references. I checked the Important claims in the code before the fixes (JS:206 `full_text`, LOC:243-270 totals, CORE:820-826 `getLangImgUrl()`, CTRL:421-482 `html_entity_decode()`).
- [x] Address review findings (auto-approve)
  Findings: all fixed, except 1 Minor finding:
  - I1 `analizeLocalization` loses data: fixed. `keys[]` has `text`, `fullText` and `expression`. `textTo` is removed. A `totals` object for batch 0 replaces the `totalFiles` proposal.
  - I2 the `col-xs-*` "full width" claim: fixed in both files (full width only below 576 px).
  - I3 the image path claim: fixed. The server keeps `getLangImgUrl()`, with the 2 forms for each version.
  - I4 the escaping contract: added as rule 5 in section 3.0.
  - I5 the labels of the header item: added `document.te_i18n_global` through the header injection and the `te-header-count` root class.
  - I6 zero rows in `analizeTableDetail`: added note 4.
  - Minor findings: the place of the langbar, the modal in README decision 4, the state of the translation loop and `js_const_statistic_html`, the integer flags, the "Try a translation" rows and the Debug "View" link: fixed.
  - Minor finding skipped: the host-stack versions for OC 3.0 and 4.x were not checked again. The Explore agent checked them against the OpenCart sources on GitHub. Only the reviewer did not do a probe again.
  - Unconfirmed items: `totalFiles` is confirmed by the reviewer (now `totals.fileCount`). `deletedRows` is feasible, but it is not checked on OC 1.5.
- [x] Checks only (verify loop)
  Findings: the 6 scripts pass (1076 assertions, 0 failed, 6 skipped). `_version.txt` stays `04.01.0175`. Only `docs/pocs/` and `docs/tasks/2-admin-design-poc/` are new.
- [x] Re-review? (ask the user)
- [x] Improvements pass → `2-admin-design-poc-improvements.md`

Dropped steps: templates, language strings and version bump + build. The task changes no file under `sources/`.

## Done

- Landed (uncommitted, branch `docs/2-admin-design-poc`): `docs/pocs/2-admin-design/README.md`, `design-concept.md` and `endpoints-json.md`. No file under `sources/`, `tests/` or `tools/` changed.
- Version: `_version.txt` stays `04.01.0175`. No build was run (the user chose checks only). Tests: 6 scripts, 1076 assertions, 0 failed.
- Review: 1 pass (Sonnet 5.5). The user declined a v2 pass. All Important findings are fixed.
- Deferred: the change of the 5 HTML endpoints to JSON and the new templates go into the next tasks (see `README.md`, "Next tasks"). `deletedRows` on OC 1.5 is not checked. GET for the destructive endpoints and the HTML error pages from model exceptions are recorded as out of scope.

