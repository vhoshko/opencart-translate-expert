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

## Source Layout

| Path | What |
|------|------|
| `sources/upload/admin/controller/extension/module/client_translate_expert.php` | Admin routes, settings, AJAX actions. `// OC4_EVENT_START` … `// OC4_EVENT_END` wrap the OC4-only code |
| `sources/upload/admin/model/extension/module/client_translate_expert.php` | DB queries for table and column analysis |
| `sources/upload/system/library/client_translate_expert_core.php` | Main translation logic, table analysis, batch processing |
| `sources/upload/catalog/controller/extension/module/client_translate_expert_cron.php` | Store-front cron link. Thin controller that calls `LibraryClientTranslateExpert::runCron()`. The secret is the only protection |
| `sources/upload/system/library/client_translate_expert_cron_helper.php` | Pure cron helpers (secret rules, request parameters, setting key). `tests/` tests it |
| `sources/upload/system/library/vh_google_translator.php` | Google Translate API wrapper (`tests/` tests it) |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl` | Admin template (PHP syntax) for OC 1.5–3.0. OC 3.0 also ships the `.tpl` and renders it as PHP, because `build.sh` makes the controller set `template_engine = template` |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig` | Admin template for OC 4.0 only |
| `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/…` | i18n. Put every string in all three languages |
| `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml` | OCMOD injection (OC ≤ 3.0 only) |

Edit `sources/` only. `sources_release*/` and `releases/` are build output.

## Build & Test Commands

| Scope | Command | Notes |
|-------|---------|-------|
| Syntax check | `php -l <file>` | Local PHP is 8.2. It does **not** find syntax that breaks on 5.6 |
| Unit tests | `php tests/test_vh_google_translator.php` | No API key needed |
| Unit + integration tests | `php tests/test_vh_google_translator.php <GOOGLE_API_KEY>` | Use only a key that the user gives you |
| Route filter unit tests | `php tests/test_client_translate_expert_route_filter.php` | No API key needed. Any PHP warning fails a test |
| Cron helper unit tests | `php tests/test_client_translate_expert_cron_helper.php` | No API key needed. Any PHP warning fails a test |
| Translate loop tests | `php tests/test_client_translate_expert_translate_loop.php` | No DB and no API key needed. Any PHP warning fails a test |
| OC4 Twig filter test | `php tests/test_oc4_twig_filters.php` | Static check. Fails on a filter that Twig v3.4.2 (OC 4.0.2.x) or v3.18.0 (OC 4.1.0.x) does not define |
| Bump version | `/bump-version` | Changes the patch part of `_version.txt` (`MM.mm.pppp`). **Do it before every build** |
| Build per-version zips | `./build.sh` | Leaves zips in the root. Use the release build instead |
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
| A module works on OC 4.0 / PHP 8 but shows a white screen on OC 2.x / PHP 5.6 | A pre-4.0 package has PHP 7+ syntax (type hints, `??`, nullable types, arrow functions). `build.sh` removes `// OC4_EVENT_START` … `// OC4_EVENT_END` blocks **only from the admin controller**. It does not remove markers in the model, libraries or `install.php`. The local `php -l` (8.2) accepts the syntax | Keep every file PHP 5.6 compatible. Put PHP 7+ code only inside the marker blocks of the controller. Elsewhere, control OC4-only behavior at runtime with `version_compare(VERSION, '4.0', '>=')`, as `client_translate_expert_core.php` does. Before you build, check the added lines and new files for these features |
| A feature works on OC 2.3 but gives a 404 or fatal error on 2.2 and earlier | `build.sh` rewrites routes and class names with plain `sed` (`extension/module/` → `module/`, `ControllerExtensionModule` → `ControllerModule`). It does not rewrite a string in another shape | Write routes and class names in the OC 2.3 form. After the build, unzip the 2.2 or 1.5 package to a temp dir. Then grep for the new string |
| A new AJAX action, library class or setting works on OC 1.5–2.3. On 4.0 it gives a 404 or fatal error, or reads an empty setting (settings: also on 3.0) | The 3.0/4.0 rewrites in `build.sh` are **fixed lists**, not patterns. The lists hold the `/` → `.` method separator for each named controller and JS method, `new X(` namespacing for each named library class, and the `module_` prefix for seven named `client_translate_expert_*` setting keys | If you add one of these items, add the matching line to the list in `build.sh`. After the build, grep the oc4.x package for the form that the build did not rewrite. For settings, also grep the oc3.0 package |
| The OC 4 admin page shows "Error: Could not load template extension/client_translate_expert/module/client_translate_expert!" | OC 4 `system/library/template/twig.php` shows each Twig `SyntaxError` with this text. An unknown filter (for example `|string`, which Twig does not define) is a `SyntaxError`. `php -l` and the build do not compile Twig | Use only filters that both Twig v3.4.2 (OC 4.0.2.x) and v3.18.0 (OC 4.1.0.x) define. `tests/test_oc4_twig_filters.php` checks this. Compare values with `in` and without a cast filter: Twig `in` compares `"15"` and `15` as equal |
| A UI change shows on OC ≤ 3.0 but not on 4.0, or the reverse | OC 1.5–3.0 render the `.tpl` as a PHP template. OC 4.0 uses its own `client_translate_expert.oc4.twig` | Change both template files, each in its own syntax. Never write Twig into the `.tpl` |
| `.zip` files or `OpenCartTranslateExpertClient-*` / `sources_release*` folders stay in the repo root | You ran `./build.sh` alone, or a build stopped before the end | Remove them. Use `./build.release.sh`, which removes these items itself |
| `sed: 1: "…": invalid command code`, or a replacement that does nothing, in a build script | BSD `sed -i` (macOS) needs `-i ''`. GNU `sed` does not | Use the `file_replace` helper in `build.sh`. The helper uses `$OSTYPE` to choose the correct form |
| `git diff` shows nothing or only some changes, but files changed | `git checkout <branch> -- <paths>` **stages** the files that it copies | Use `git diff HEAD` (staged and unstaged changes). Tell reviewers to do the same |
| `zsh: no matches found: --include=*.php`, or a `grep` that returns nothing and seems to show "this symbol is not in the codebase" | zsh expands `*.php` itself. It stops with an error if nothing in the cwd matches | Quote every glob: `grep -rn "X" --include="*.php" .`. An empty result from an **unquoted** glob is a failed command. Never use it as proof that something is absent |
| `unexpected EOF while looking for matching` from a long heredoc | Bash parses long heredoc bodies, or bodies with many quotes, in the wrong way | Write the file with the Write tool |
| An in-place `perl -pi` or `sed -i` edit of a PHP line leaves empty text where a `$name` was. Or a `grep` check of a PHP line finds nothing and stops a `&&` chain | Perl interpolates `$name` in the replacement. Each tool escapes a `$` in a double-quoted shell string in a different way | Use the Edit tool for a PHP line that contains `$`. For a check, use `grep -F` with the pattern in single quotes |
| A glob for a per-version zip under `releases/v<version>/` matches nothing, and zsh stops with "no matches found" | The release folder holds only the `.full.zip`. The per-version `.ocmod.zip` files and the `oc4.x` folder are inside it | Unzip the `.full.zip` to a temp dir first. Then unzip the per-version package from it. Use absolute paths and do not `cd` into `releases/`, because a `cd` moves the working directory of the session |
| The scratchpad directory disappears during the session | It belongs to one session only | Put anything that must survive the session in `docs/tasks/<slug>/tmp/`. Commit anything that must stay for a long time |
| A SQL change has no real DB check before the store run. The unit tests use a stub DB, so they check only the SQL text | The tests replace the DB with `FakeDb`. The container has MariaDB (`/usr/sbin/mariadbd`), and a system instance runs on port 3306 with `/var/lib/mysql` | Do not use or stop the system instance. Start a throwaway instance with its data folder and socket in `docs/tasks/<slug>/tmp/` (`mariadb-install-db --datadir=...`, then `mariadbd --datadir=... --socket=... --skip-networking`). Run the real library method against it. Stop the instance and remove the data folder after the probe |
| A Docker store run on the host tests old code, or no store run is possible before the review | The host checkout of the user is a separate folder, not the container `/workspace`. Changes in `/workspace` do not reach the host until they are pushed | If a task needs a store run, plan a WIP push in Phase 1. Ask the user before the push. Tell the user to run `git pull` and `./build.release.sh` on the host, because `releases/` is gitignored |

## Commit Message Format

```
<type>: <short description>

closes #<issue-number>
```

The types are `fix`, `feat`, `refactor`, `chore`, `docs` and `test`. Start the description in lowercase. Proper nouns and file names keep their case, for example `docs: update README`.

If the user did not give the GitHub issue number, ask for it. The only body line is `closes #N`, with no bullet points or other text. If there is no issue, omit this line.

Use a HEREDOC to pass the message. PRs are squash-merged, so GitHub adds ` (#<PR>)` to the title on merge. Do not add it yourself.
