# Project Workflow Config

## Repo

- GitHub: `vhoshko/opencart-translate-expert` (remote `origin`, default branch `main`)
- Changes go to `main` through squash-merged PRs. For this reason, commit titles end in `(#<PR>)`.

## Branch Convention

The pattern is `<prefix>/<issue-number>-<slug>`. If there is no issue, the pattern is `<prefix>/<slug>`. The prefixes are `feat`, `fix`, `refactor`, `chore`, `docs` and `test`.
Always make the branch from a fresh `origin/main`: `git fetch origin main && git checkout -b <branch> origin/main`.

## Session Artifacts

Base path: `docs/tasks/<slug>/`

| File | Purpose |
|------|---------|
| `<slug>_plan.md` | Living plan with checkboxes |
| `<slug>-review.md` | Independent review (v2, v3 and so on if the scope grows) |
| `<slug>-improvements.md` | Reflection after the task |
| `tmp/` | Throwaway scripts. Git ignores this folder, so never commit it |

If a task does one step of a PoC under `docs/pocs/**`, add a "Status: done in issue N" note to each changed section of the PoC document. Keep the old text as the record (task 57).

## Source Layout

| Path | What |
|------|------|
| `sources/upload/admin/controller/extension/module/client_translate_expert.php` | Admin routes, settings, AJAX actions. `// OC4_EVENT_START` … `// OC4_EVENT_END` wrap the OC4-only code |
| `sources/upload/admin/model/extension/module/client_translate_expert.php` | DB queries for table and column analysis |
| `sources/upload/system/library/translate_expert/translate_expert_core.php` | Main translation logic, table analysis, batch processing, language code mapping (reads the setting), format parameter fix |
| `sources/upload/catalog/controller/extension/module/client_translate_expert_cron.php` | Store-front cron link. Thin controller that calls `TranslateExpert::runCron()`. The secret is the only protection |
| `sources/upload/system/library/translate_expert/translate_expert_cron_helper.php` | Pure cron helpers (secret rules, request parameters, setting key). `tests/` tests it |
| `sources/upload/system/library/translate_expert/translate_expert_language_code_map.php` | Pure language code map helper (default JSON, parse, lookup, validation). `tests/` tests it |
| `sources/upload/system/library/translate_expert/translate_expert_i18n.php` | Pure header string helper (`document.te_i18n_global`). Reads the module language file in a function scope. `tests/` tests it |
| `sources/upload/system/library/translate_expert/translate_expert_google_client.php` | Google Translate API wrapper (`tests/` tests it). It does not map language codes and does not fix format parameters |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl` | Admin template (PHP syntax) for OC 1.5–3.0. OC 3.0 also ships the `.tpl` and renders it as PHP, because the controller sets `template_engine = template` at runtime for OC 3.0. The OCMOD XML of OC 3.0 patches the core `admin/view/template/common/header.twig` of OpenCart, and `build.sh` rewrites the PHP echo lines of the XML to Twig |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig` | Admin template for OC 4.0 only |
| `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/…` | i18n. Put every string in all three languages |
| `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml` | OCMOD injection (OC ≤ 3.0 only) |

Edit `sources/` only. `sources_release*/` and `releases/` are build output.

## Build & Test Commands

| Scope | Command | Notes |
|-------|---------|-------|
| Syntax check | `php -l <file>` | Local PHP is 8.2. It does **not** find syntax that breaks on 5.6 |
| Unit tests | `php tests/test_translate_expert_google_client.php` | No API key needed |
| Unit + integration tests | `php tests/test_translate_expert_google_client.php <GOOGLE_API_KEY>` | Use only a key that the user gives you |
| Core translate tests | `php tests/test_translate_expert_core_translate.php` | No DB and no API key needed. Any PHP warning fails a test |
| Library path tests | `php tests/test_translate_expert_library_paths.php` | Static. Checks the `translate_expert/` paths and the old names |
| Route filter unit tests | `php tests/test_translate_expert_route_filter.php` | No API key needed. Any PHP warning fails a test |
| Cron helper unit tests | `php tests/test_translate_expert_cron_helper.php` | No API key needed. Any PHP warning fails a test |
| Controller JSON guard tests | `php tests/test_client_translate_expert_ajax_guard.php` | No DB and no API key needed. Any PHP warning fails a test. It checks the error JSON of `runJsonAction` and the POST check |
| Header strings tests | `php tests/test_translate_expert_i18n.php` | No DB and no API key needed. Any PHP warning fails a test |
| JS helper tests | `cd tests/js && npm ci && node test_client_translate_expert_js_helpers.js` | Node and npm (allowed by the firewall). jsdom with jQuery 3.7.1 (npm) and 1.7.1 (`tests/js/vendor/`, the OC 1.5 file). `npm ci` is part of the command, so it runs each time |
| Language code map tests | `php tests/test_translate_expert_language_code_map.php`, `php tests/test_translate_expert_language_code_map_default.php`, `php tests/test_client_translate_expert_language_code_map_save.php` | No DB and no API key needed. Any PHP warning fails a test |
| Translate loop tests | `php tests/test_client_translate_expert_translate_loop.php` | No DB and no API key needed. Any PHP warning fails a test |
| Version rules tests | `php tests/test_client_translate_expert_version_rules.php` | No DB and no API key needed. Any PHP warning fails a test. It checks the version helpers of the admin controller and the model (token name, setting key, routes, template engine, flag URL) |
| Settings cards tests | `php tests/test_client_translate_expert_settings_cards.php` | Static check of the 4 collapsible cards in the `.tpl` and the `.oc4.twig`. No DB and no API key needed |
| Select height test | `php tests/test_client_translate_expert_select_height.php` | Static. No OpenCart, no DB and no API key needed. Checks `size="15"` of `#te-analization-table` in both templates and the `height: auto` CSS rule for multiple selects |
| OC 3.0 OCMOD XML test | `php tests/test_oc30_ocmod_xml.php` | No OpenCart, no DB and no API key needed. Runs the OC 3.0 `file_replace` lines of `build.sh` on a copy of the XML. Fails if `header.twig` has a PHP tag or lacks the 2 Twig lines |
| OC4 Twig name test | `php tests/test_oc4_twig_filters.php` | Static check. Fails on a filter, test, function or tag that Twig v3.4.2 (OC 4.0.2.x) or v3.18.0 (OC 4.1.0.x) does not define |
| Bump version | `/bump-version` | Changes the patch part of `_version.txt` (`MM.mm.pppp`). **Do it before every build** |
| Build per-version zips | `./build.sh` | Leaves zips in the root. Use the release build instead |
| Release (2 phases) | `/release phase1`, then `/release phase2` after the PR merge | Phase 1 bumps to a new minor or major version (patch `0100`), writes `docs/release/<tag>/` notes (en, uk), commits and opens the PR. Phase 2 tags the squash commit (no `v` prefix), builds without a bump and creates the GitHub release with the `.full.zip` asset |
| Release build | `./build.release.sh` | Puts the output in `releases/v<version>/`. **Run it after every change** (see `CLAUDE.md`) |

## OpenCart Core Source Probe

No local OpenCart source exists. To verify OpenCart core behavior for a version, read the file from GitHub:

```bash
gh api "repos/opencart/opencart/contents/upload/<path>?ref=<tag>" -H "Accept: application/vnd.github.raw" | grep -n "<pattern>"
```

- Use a release tag for `ref`, for example `1.5.6.4`, `2.0.3.1`, `2.3.0.2`, `3.0.3.8`, `4.0.2.3` or `4.1.0.0`.
- The module list controller is at `admin/controller/extension/module.php` on OC 1.5–2.2 and 4.x. On OC 2.3 and 3.0, it is at `admin/controller/extension/extension/module.php`.
- The Twig setup (`autoescape`) is in `system/library/template/twig.php` on OC 3.0 and 4.x.
- Use the probe in Phase 0 to verify external assumptions. Record the tag and the line number as evidence in the plan.

## Known Environment Gotchas

| Symptom | Cause | What to do |
|---|---|---|
| The OC 3.0 package has `<?{{ ... ; ?>` in the OCMOD XML, but the macOS build is correct (issue #98) | `sed` differs between GNU (Linux, the dev container) and BSD (macOS). In GNU `sed`, `\?` means "optional" and `\>` means "end of word". In BSD `sed`, `\?` is a literal `?` | In `build.sh` patterns, use `[?]` and `[$]`. Never use `\?`, `\+`, `\|`, `\<` or `\>`. `check_oc30_xml` and `tests/test_oc30_ocmod_xml.php` check the result. Read the changed lines in the built package, because a green build did not show this bug |
| A module works on OC 4.0 / PHP 8 but shows a white screen on OC 2.x / PHP 5.6 | A pre-4.0 package has PHP 7+ syntax (type hints, `??`, nullable types, arrow functions). `build.sh` removes `// OC4_EVENT_START` … `// OC4_EVENT_END` blocks **only from the admin controller**. It does not remove markers in the model, libraries or `install.php`. The local `php -l` (8.2) accepts the syntax | Keep every file PHP 5.6 compatible. Put PHP 7+ code only inside the marker blocks of the controller. Elsewhere, control OC4-only behavior at runtime with `version_compare(VERSION, '4.0', '>=')`, as `translate_expert_core.php` does. Before you build, check the added lines and new files for these features |
| A feature works on OC 2.3 but gives a 404 or fatal error on 2.2 and earlier | `build.sh` rewrites routes and class names with plain `sed` (`extension/module/` → `module/`, `ControllerExtensionModule` → `ControllerModule`). It does not rewrite a string in another shape | Write routes and class names in the OC 2.3 form. After the build, unzip the 2.2 or 1.5 package to a temp dir. Then grep for the new string |
| A new AJAX action or library class works on OC 1.5–2.3. On 4.0 it gives a 404 or fatal error | The 4.0 `new X(` namespace rewrites in `build.sh` are a **fixed list**, not a pattern. The runtime rules of the module (`tokenName()`, `settingKey()`, `extensionListRoute()`, `routeMethodSeparator()`, `needsPhpTemplateEngine()` in the admin controller, and `teRoute()` in the JS) need no list | For a new library class instantiated with `new`, add the matching line to the `new X(` list in `build.sh`. For a new AJAX method, write the route with `teRoute('<method>')` in the JS. For a new setting key, use `settingKey()` in the controller, and keep the bare name in the templates. After the build, grep the oc4.x package for the unqualified `new <Class>(` |
| The OC 4 admin page shows "Error: Could not load template extension/client_translate_expert/module/client_translate_expert!" | OC 4 `system/library/template/twig.php` shows each Twig `SyntaxError` with this text. An unknown filter, test, function or tag (for example `|string`, which Twig does not define) is a `SyntaxError`. `php -l` and the build do not compile Twig | Use only filters, tests, functions and tags that both Twig v3.4.2 (OC 4.0.2.x) and v3.18.0 (OC 4.1.0.x) define. Do not use `dump()`: OC 4.0.2.x does not load the Twig debug extension. `tests/test_oc4_twig_filters.php` checks this. Compare values with `in` and without a cast filter: Twig `in` compares `"15"` and `15` as equal |
| A UI change shows on OC ≤ 3.0 but not on 4.0, or the reverse | OC 1.5–3.0 render the `.tpl` as a PHP template. OC 4.0 uses its own `client_translate_expert.oc4.twig` | Change both template files, each in its own syntax. Never write Twig into the `.tpl` |
| `.zip` files or `OpenCartTranslateExpertClient-*` / `sources_release*` folders stay in the repo root | You ran `./build.sh` alone, or a build stopped before the end | Remove them. Use `./build.release.sh`, which removes these items itself |
| `sed: 1: "…": invalid command code`, or a replacement that does nothing, in a build script | BSD `sed -i` (macOS) needs `-i ''`. GNU `sed` does not | Use the `file_replace` helper in `build.sh`. The helper uses `$OSTYPE` to choose the correct form |
| `git diff` shows nothing or only some changes, but files changed | `git checkout <branch> -- <paths>` **stages** the files that it copies | Use `git diff HEAD` (staged and unstaged changes). Tell reviewers to do the same |
| `zsh: no matches found: --include=*.php`, or a `grep` that returns nothing and seems to show "this symbol is not in the codebase" | zsh expands `*.php` itself. It stops with an error if nothing in the cwd matches | Quote every glob: `grep -rn "X" --include="*.php" .`. An empty result from an **unquoted** glob is a failed command. Never use it as proof that something is absent |
| `unexpected EOF while looking for matching` from a long heredoc | Bash parses long heredoc bodies, or bodies with many quotes, in the wrong way | Write the file with the Write tool |
| An in-place `perl -pi` or `sed -i` edit of a PHP line leaves empty text where a `$name` was. Or a `grep` check of a PHP line finds nothing and stops a `&&` chain | Perl interpolates `$name` in the replacement. Each tool escapes a `$` in a double-quoted shell string in a different way | Use the Edit tool for a PHP line that contains `$`. For a check, use `grep -F` with the pattern in single quotes |
| A glob for a per-version zip under `releases/v<version>/` matches nothing, and zsh stops with "no matches found" | The release folder holds only the `.full.zip`. The per-version `.ocmod.zip` files and the `oc4.x` folder are inside it | Unzip the `.full.zip` to a temp dir first. Then unzip the per-version package from it. Use absolute paths and do not `cd` into `releases/`, because a `cd` moves the working directory of the session |
| A Python patch script reports that a multi-line match text is not found in a tab-indented file | The match text has spaces and the file has tab characters, or the reverse | Use the Edit tool for a multi-line change in a tab-indented file. Do not match with a typed copy in a script (task 93) |
| The scratchpad directory disappears during the session | It belongs to one session only | Put anything that must survive the session in `docs/tasks/<slug>/tmp/`. Commit anything that must stay for a long time |
| A SQL change has no real DB check before the store run. The unit tests use a stub DB, so they check only the SQL text | The tests replace the DB with `FakeDb`. The container has MariaDB (`/usr/sbin/mariadbd`), and a system instance runs on port 3306 with `/var/lib/mysql` | Do not use or stop the system instance. Start a throwaway instance with its data folder and socket in `docs/tasks/<slug>/tmp/` (`mariadb-install-db --datadir=...`, then `mariadbd --datadir=... --socket=... --skip-networking`). Run the real library method against it. Stop the instance and remove the data folder after the probe |
| A Docker store run on the host tests old code, or no store run is possible before the review | The host checkout of the user is a separate folder, not the container `/workspace`. Changes in `/workspace` do not reach the host until they are pushed | If a task needs a store run, plan a WIP push in Phase 1. Ask the user before the push. Tell the user to run `docker/rebuild-stores.sh` on the host. It does the `git pull` and `./build.release.sh` itself, because `releases/` is gitignored |
| A mutation run fails one check and then stops for each OC version. The later new checks never run (tasks 51 and 49) | `tests/test_client_translate_expert_localization.php` turns each PHP warning into an exception. An unexpected exception stops all later tests of that OC version | When you write a test, guard each array read that depends on the code under test with `isset`. Do not wait for the mutation run |
| A subagent stops with "You've hit your 5-hour limit" (HTTP 429) in the middle of its work (tasks 58 and 59) | The account usage limit. The agent keeps its saved state | After the reset, send a message with `SendMessage` to the same agent. Tell it to continue from where it stopped. Do not start a new agent, because a new agent does all the work again |
| A grep for a language key finds a use, but no code uses the key (task 58) | The key name is a substring of another key, for example `entry_analization_result` in `entry_localization_analization_result` | Before you remove a language key, use `grep -rnw "<key>" sources tests`. The `-w` option matches the full key name only |
| A JSON value that the admin page posts does not parse in the controller (task 79). The save fails with a format error | OpenCart `Request::clean()` escapes each POST value with `htmlspecialchars`. `{"a":"b"}` arrives as `{&quot;a&quot;:...` | Decode the value one time with `html_entity_decode($value, ENT_QUOTES, 'UTF-8')` before the parse. In the tests, post the value escaped with `htmlspecialchars($value, ENT_COMPAT, 'UTF-8')`. |

## Commit Message Format

```
<type>: <short description>

closes #<issue-number>
```

The types are `fix`, `feat`, `refactor`, `chore`, `docs` and `test`. Start the description in lowercase. Proper nouns and file names keep their case, for example `docs: update README`.

If the user did not give the GitHub issue number, ask for it. The only body line is `closes #N`, with no bullet points or other text. If there is no issue, omit this line.

Use a HEREDOC to pass the message. PRs are squash-merged, so GitHub adds ` (#<PR>)` to the title on merge. Do not add it yourself.
