# 4-cron-translation

## Settings
- Planning model: Fable 5.2 (Plan subagent). Review model: Fable 5.2 (user's choice; same as planning, so the review is less independent than usual).
- Build + tests run by the workflow. No commit, push or PR unless the user asks.
- Review findings: ask the user per finding (no standing auto-approve).
- Update 2026-11-11 (user): from review v4 on, decide findings without asking ("fix all what you think we should fix"); then commit, push and open a PR when ready.

## Context
- Issue: https://github.com/vhoshko/opencart-translate-expert/issues/4. Branch `feat/4-cron-translation`.
- Goal: a cron URL translates the next batch of DB rows for the selected analysis filters. Language files are out of scope.
- Affected OC versions: 1.5, 2.0–2.3, 3.0, 4.0, 4.1 (all packages).
- User decisions: store-front (catalog) controller protected by a secret; bulk amount = DB rows per run; all text columns of the selected table (or every table in order for "All tables"); secret minimum 16 characters.
- Key files (`S/` = `sources/upload/`):
  - `S/system/library/client_translate_expert.php:42-151` `translateTable` (one table, 10 s budget, no row cap), `:168-230` `translateTableTexts`, `:495-601` `getTextToTranslateRows`.
  - `S/system/library/client_translate_expert_core.php:16-35` (key prefix at runtime), `:514` `getTables`, `:802-810` `getLanguages`.
  - `S/admin/model/extension/module/client_translate_expert.php:134-229` counter SQL, `:233` `getLibrary`.
  - `S/admin/controller/extension/module/client_translate_expert.php:51-71` save, `:75-130` language vars, `:247-283` setting load, `:424-445` `translateTable`, `:602-622` counter calls.
  - `S/admin/view/template/extension/module/client_translate_expert.tpl:52-64,177-182` and `.oc4.twig:52-63,173-178`.
  - `S/admin/view/javascript/client_translate_expert.js:818-826` filter selectors, `:1100` `initModule`.
  - `build.sh:60-89` (2.2 and lower), `:127-136` and `:402-406` (setting key lists), `:153-425` (OC4 layout and namespaces).

## Approach
A thin store-front controller passes `$this->request->get` to one new library method, `LibraryClientTranslateExpert::runCron()`. The library does all the work: status check, secret check, parameter check, lock, batch translation, character count. The admin page saves two new settings and shows the link.

- **Shared batch logic (library).** Move the loop body of `translateTable` into a protected method driven by a budget (`rowCap`, `rowsDone`, `charsDone`, `deadline`). Public `translateTable` keeps its signature and result shape (`rowCap = 0`, no cap). New `translateNextBatch($filters, $rowCap)` handles one table, or walks `getTables()` in order when the table filter is empty.
- **Row cap.** Before a group is queued: stop if `rowsQueued > 0 && rowsQueued >= rowCap`, flush the pending Google batch, return `done = false`. `rowsQueued` grows by `value_count`. At least one group always runs.
- **Time budget.** 10 s, as in the admin flow, applied after the first written batch.
- **Empty Google result guard.** A trimmed-empty translation is not written; the group is skipped for the rest of the run and not counted against the cap.
- **Languages.** `runCron` loads languages with its own SQL (all languages, keyed by `code`), because the catalog language model returns enabled languages only.
- **Character count (shared).** Move the counter SQL from the admin model into the core library with runtime `module_` key names (as `core:28-32`). Admin model methods delegate. Cron counts characters even when a later batch fails.
- **Pure helper.** New `S/system/library/client_translate_expert_cron_helper.php` (`ClientTranslateExpertCronHelper`, static, no OpenCart dependency): `isValidSecret`, `secretsMatch` (`hash_equals` with fallback), `parseParams`, `settingKey`.
- **Setting keys.** `client_translate_expert_cron_secret`, `client_translate_expert_cron_bulk_amount` (default 100, clamped 1–100000).
- **URL parameters.** `route`, `secret`, `langIdFrom`, `langIdTo`, `mode`, `table`, `product_status`, `product_quantity`, `product_category_id`, `stock_status`. No `token` in any new name (build.sh 3.0 rewrite).
- **Secret rule.** `^[A-Za-z0-9_-]{16,128}$`, compared in constant time; never logged.
- **Response.** JSON `{done, rows, chars, table, error}` with `Cache-Control: no-store`. Errors: `forbidden` (403, same for missing/wrong/short secret), `disabled`, `bad_params`, `busy`, `translate_failed`.
- **Lock.** `GET_LOCK('te_cron_' . md5(DB_DATABASE . DB_PREFIX), 0)`; not 1 → `busy`; released on every path.
- **Cron link.** Admin controller builds the base from `HTTPS_CATALOG` (if defined and the request is HTTPS) else `HTTP_CATALOG`, plus the route and the saved secret; templates put it in `data-cron-url`, JS appends the filters.
- **Known limits (documented, not fixed):** "All tables" rescans finished tables on each run (a per-table link is faster on big stores); the secret appears in web server access logs; maintenance mode blocks the store-front route; a text Google keeps returning empty is retried (and paid for) each run.

## Assumptions
- ✅ build.sh rewrites and fixed lists as described (read `build.sh:118-150`, `:350-412`).
- ✅ The `translateTable` path uses only `->db`, `->config` and `getLanguages()` of the passed object (grep of both library files, by the plan agent).
- ✅ OC 4.0/4.1 catalog `startup/extension.php` registers `Opencart\Catalog\Controller\Extension\<Ext>` → `DIR_EXTENSION/<ext>/catalog/controller/` for installed extensions (docker `ote-test-oc40-1:22`, `oc41-1:24`).
- ✅ OC 1.5.6.4 and 2.2.0.0 resolve route `module/client_translate_expert_cron` to `catalog/controller/module/client_translate_expert_cron.php`, class `'Controller' . preg_replace('/[^a-zA-Z0-9]/', '', $route)` (opencart/opencart `system/engine/action.php` at both tags, lines 27 and 30).
- ✅ `HTTPS_CATALOG` is **not** defined in OC 4.0/4.1 admin `config.php` (only `HTTP_CATALOG`); defined on 2.3/3.0. Guard with `defined()`.
- ✅ SEO URL startup on 2.3/3.0/4.0/4.1 only rewrites `_route_`; an explicit `index.php?route=` is left alone (docker `catalog/controller/startup/seo_url.php`).
- ✅ `GET_LOCK` works for a user with only DB-level privileges (`opencart@%`, MariaDB 10.6: returned 1).
- ✅ Catalog maintenance mode blocks non-`api` routes unless an admin is logged in (docker 4.0/4.1 `startup/maintenance.php:29`).
- ❓ OC 1.5 / 2.0–2.2 store-front behaviour beyond routing (settings load into config, `response->addHeader`) — no docker store; standard OpenCart behaviour. If wrong, cron fails on those versions only.

## Steps
- [x] `S/system/library/client_translate_expert_cron_helper.php` (new) + `tests/test_client_translate_expert_cron_helper.php` (new): secret rules, `secretsMatch`, `parseParams`, `settingKey` for 2.3/3.0/4.1.
- [x] `client_translate_expert_core.php`: counter methods with runtime key names; admin model `:134-229` delegates.
- [x] `client_translate_expert.php`: include helper; budget refactor of `translateTable` (admin behaviour unchanged); empty-result guard; `translateNextBatch`; `runCron`; `getCronSecretState`.
- [x] `S/catalog/controller/extension/module/client_translate_expert_cron.php` (new): `index()` → JSON, 403 for `forbidden`, `no-store`.
- [x] Admin controller: trim secret and clamp bulk on save; load pairs; `$data['te_cron_url']`; new language vars.
- [x] Templates `.tpl` + `.oc4.twig`: settings rows (bulk amount; secret + Generate) and the cron link panel before the Analyze row (error text when the saved secret is invalid).
- [x] JS: Generate handler (`crypto.getRandomValues`, 32 hex); `updateTranslateExpertCronLink()` bound to the filter controls.
- [x] Language strings en-gb, ru-ru, uk-ua (entries, help with a crontab example and the maintenance-mode note, error text).
- [x] build.sh: 2.2-and-lower catalog move/rename; new keys in both setting lists; OC4 catalog controller placement, namespaces, helper in the library list, cleanup of `upload/catalog`.
- [x] Docs: cron section in README files; new test command and catalog controller in `CLAUDE.md` / `.claude/project-workflows.md`.
- [x] Manual PHP 5.6 syntax check of new/changed lines.
  Findings (implementation, ote-implementer): php -l clean; cron helper test 46/0, route filter 40/0, google translator 24/0/6 skipped. Deviations from the brief: secret checked before the enabled check (no state leak); added a duplicate guard per pending batch (offset reset re-queued unwritten groups → double translation/counting, also in the admin flow); empty-result guard also protects the admin flow from overwriting with empty text; unknown language ids → `bad_params`; catalog controller reuses the library's runtime path switch (no build.sh require rewrite); debug key picked with `version_compare` in the controller (the helper is namespaced on OC4); chars counted even when an exception interrupts a run. JS ids `te-cron-secret` / `te-cron-bulk-amount` avoid the 3.0 key loop. No automated test covers the row cap, empty guard or runCron — the docker smoke test is the only runtime check.
- [x] Bump version + build + test, plus package greps (oc1.5/2.2 catalog path and class, oc3.0 `module_` keys and no `user_user_token`/`module_module_`, oc4.x namespaces and route).
- [x] Docker smoke test on oc23/oc30/oc40/oc41: forbidden/bad_params/busy responses, link updates in admin, manual translation and char count still work.
  Findings (build): 04.01.0132, release `releases/v04.01.0132/`. php -l and PHP 5.6 grep clean; tests 24/0/6 skipped, 40/0, 46/0. Package greps pass for oc1.5–2.2 (`module/` form), 2.3, 3.0 (`module_` keys, no `user_user_token`/`module_module_`), 4.x (namespaces, route). The build makes one 4.x package for 4.0 and 4.1.
  Findings (smoke, scripts and outputs in `tmp/`): on all four stores, missing/wrong/15-char secret → 403 `forbidden`; bad mode, unknown table, same languages → `bad_params`; held lock → `busy`; status 0 → `disabled`; no-key run → `translate_failed` with no PHP errors; `no-store` + JSON headers; secret never in logs; admin page renders the link (empty for a short saved secret); settings POST stores trimmed secret and bulk without losing other keys. Not covered: a successful translation (no Google key), so the row cap and `done:true` are untested at runtime. Observation: on a Google error the cron `message` is the generic "Internal error." — the real cause is in `additional_message`, which the response drops (`client_translate_expert.php:150`).
- [x] Independent review → 4-cron-translation-review.md
  Findings: ready to merge with fixes; 0 critical, 2 important, 6 minor. Reviewer simulated the loop against HEAD and the new code (harness copied to `tmp/harness*.php`).
- [x] Address review findings (user picks which to fix or skip)
  Decisions (user, 2026-11-11): fix 1–7 (row cap stall on empty results, loop test, Throwable catch, double count on addCharCount failure, single bulk clamp, skipped count in response, pass additional_message). 8 (lower admin char count after duplicate fix) → release-notes line only.
- [x] Bump version + build + tests (verify loop)
  Findings: fixes 1–7 applied by ote-implementer; new `tests/test_client_translate_expert_translate_loop.php` (45 checks). Proof: with fix 1 disabled the loop test fails 6 checks (the 120-empty-groups and value_count-500 stall scenarios); restored byte-for-byte, 45/45. Build 04.01.0133 green: php -l clean, tests 24/0/6, 40/0, 46/0, 45/0; package greps pass on all versions. Exception message and `details` are passed through `hideSecret()`. Release note for #8: admin char count is lower for the same work because duplicate sends are gone.
- [x] Re-review? (unreviewed fixes exist; ask the user, and if yes repeat from the review step → -review-v2.md, v3, …)
- [x] Independent review → 4-cron-translation-review-v2.md (fixes 1–7 only)
  Findings: ready to merge; 0 critical, 0 important, 5 minor. Reviewer harness copied to `tmp/harness_v2.php`. Decision (user, 2026-11-11): fix all five (time check in the cap path, exception-path response data, redact before logging, runCron/failure/stall-assertion tests, JSON_PARTIAL_OUTPUT_ON_ERROR).
- [x] Address review v2 findings
  Findings: fixes 1–4 applied by ote-implementer (66-check loop test; time-check test fails with the fix disabled, restored with `cmp`). Fix 5 was reported done but was not in the file — caught by a remark in the build report, then applied in the main context and checked with a PHP probe (invalid UTF-8 → `null` field instead of `false`).
- [x] Bump version + build + tests (verify loop v2)
  Findings: 04.01.0134 green but missing fix 5; 04.01.0135 green with it: php -l and PHP 5.6 scan clean; tests 24/0/6, 40/0, 46/0, 66/0; cron controller with `JSON_PARTIAL_OUTPUT_ON_ERROR` in all 7 packages.
- [x] Independent review → 4-cron-translation-review-v3.md (review-v2 fixes, user request after Done)
  Findings: ready to merge; 0 critical, 0 important, 7 minor. Item 1: the v2 10 s cap-path check can stop a run before its first write, repeatedly.
## Update 2026-11-11 — cron time limit
> **Superseded.** The 10 s per-run budget for cron (and the review-v2 cap-path time check) is replaced: the 10 s check can starve a run before its first write (review v3, item 1), and the user rejected short limits ("30 seconds is too slow … no limits at all, or something like 15 minutes").
- Decision (user): admin keeps 10 s per request; cron uses a 15-minute safety limit, the bulk amount is the real control; cron calls `set_time_limit` where allowed; characters are counted after every batch so a host kill does not lose the count; drop the cap-path 10 s check; document host PHP time limit / scheduler timeout.
- Empty Google results: confirmed theoretical — the source query already excludes empty/whitespace-only texts (`client_translate_expert.php:829`); the guard stays as a safety net, no further design around it.
- Also fix v3 items 2–7.
- [x] Apply the time-limit change and v3 items 2–7
  Findings: `CRON_TIME_LIMIT = 900`, budget `timeLimit` (admin 10, cron 900) used by both generic checks; cap-path 10 s check removed; `set_time_limit(0)` / `ignore_user_abort(true)` behind `function_exists`; per-batch `addBatchChars()` counts only for cron, end-of-run add and `$charsCounted` removed; v3 items 2–7 applied. Every claimed change grep-verified in the main context. Loop test 107/0; mutations (per-batch counting off → 10 fails; cron limit 10 → 4 fails), restored with `cmp`. Remaining known edges: the 900 s check runs once per 100-row page, so a page of only empty results at >9 s per Google call can overrun; a batch whose DB write throws is sent to Google but not counted (pre-existing).
- [x] Bump version + build + tests (verify loop v3)
  Findings: 04.01.0136 green: php -l clean; tests 24/0/6, 40/0, 46/0, 107/0; all 90 package PHP files lint; cron controller correct in all 7 packages; OC4 library qualifies `\Exception`/`\Throwable`. The build agent flagged `catch (\Throwable)` and `finally` outside OC4 markers under its standing rule; both are valid PHP 5.6 syntax (5.5+ for `finally`; any class name parses in `catch`).
- [x] Improvements pass → 4-cron-translation-improvements.md

## Update 2026-11-11 — empty Google results
> **Superseded.** The empty-result guard (skip the group for the run, subtract it from the row cap, report `skipped`) is replaced by the user's design: an empty translation for non-empty source text is treated as if Google returned the source text — the source is written as the translation and the same-translation hash row is stored, exactly like the existing "translated == source" path. The row is then done in every mode, so nothing is retried, the cap needs no adjustment, and the `skipped` response field is removed.
- [x] Replace skip logic with "empty → source text" (admin and cron), remove `skipped`, update tests and docs
  Findings: library change and README done in the main context; loop test rewritten by ote-implementer (127/0) and grep-verified. Mutation (`continue` instead of using the source) fails the empty-result tests (e.g. first run 60 rows instead of 100; 120-empty case never finishes); restored with `cmp`. Stub models the only_empty exclusion, not the same_value hash exclusion (the real SQL does both).
- [x] Bump version + build + tests (verify loop v4)
  Findings: 04.01.0137 green: tests 24/0/6, 40/0, 46/0, 127/0; 90 package PHP files lint; no `skipped`/`batchSkipKey` in any package; cron controller correct in all 7 packages.
- [x] Independent review → 4-cron-translation-review-v4.md (time-limit change, v3 fixes, empty → source)
  Findings: ready to merge with fixes; 0 critical, 1 important, 4 minor. Changes A–D confirmed correct with a real-SQL probe (`tmp/v4/probe_sql_v4.php`, temporary tables in the docker DB). Decisions (auto-approve per Settings): fix Important 1 (duplicate guard could leave a row while reporting `done:true` — same text in a no-target-row group and an empty-target-row group); fix 2 (RELEASE_LOCK in its own try); skip 3 (response `chars` deliberately equals what the counter stored); fix 4 (CLAUDE.md wording); fix 5 partly (test for the time check after the final flush; the same_value stub gap stays recorded).
- [x] Apply review v4 fixes + tests
  Findings: duplicate fix first trusted the flag alone and broke one stub test (the stub fills both twin groups on one write); changed to re-query and set `done=false` only if rows remain. Tests by ote-implementer (155/0), grep-verified; mutations: duplicate check off → 2 fails, unprotected RELEASE_LOCK → 5, final-flush time check removed → 2; restored with `cmp`.
- [x] Bump version + build + tests (verify loop v5)
  Findings: 04.01.0138 green: tests 24/0/6, 40/0, 46/0, 155/0; 90 package PHP files lint; cron controller correct in all 7 packages. Flagged bare `client_translate_expert_debug_status` in the 3.0/4.x cron controller is the unreachable pre-3.0 branch of a runtime `version_compare` switch.
- [x] Commit, push, PR

## Done
- Landed: store-front cron endpoint (`runCron` in the library, thin catalog controller) for DB translation on OC 1.5–4.1; bulk amount (rows per run) and cron secret settings with a Generate button; a live cron link in the admin built from the analysis filters, with an error instead of the link for a missing or short saved secret; character counting moved into the core library; a duplicate-send fix and an empty-result guard that also help the admin flow; two new test files (cron helper 46, translate loop 155).
- Final version: 04.01.0138 (`releases/v04.01.0138/`). Four independent reviews (Fable 5.2): 2 important + 6 minor; 5 minor; 7 minor; 1 important + 4 minor — all fixed except the release-note item and v4 item 3 (kept `chars` equal to the stored count). Cron runs use a 15-minute safety limit and count characters per batch; admin keeps 10 s per request. An empty Google result keeps the source text and stores a same-translation record (user design).
- Release note: the admin character count is lower for the same work, because duplicate sends to Google are gone.
- Gotchas: no Google key, so no successful real translation ran — the row cap and the loop are covered by stub tests only; the docker smoke test ran on 04.01.0132. Known edges: the 900 s check runs once per 100-row page; a batch whose DB write throws is not counted (pre-existing); the loop-test stub does not model the same_value hash exclusion. A fix-agent report claimed a change it had not made (see improvements).
- Deferred: a saved position for "All tables" on big stores; one real cron run with a Google key on a docker store.
