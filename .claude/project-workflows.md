# Project Workflow Config

## Repo

- GitHub: `vhoshko/opencart-translate-expert` (remote `origin`, default branch `main`)
- Changes land on `main` through squash-merged PRs, so commit titles end in `(#<PR>)`.

## Branch Convention

The pattern is `<prefix>/<issue-number>-<slug>`, or `<prefix>/<slug>` if there is no issue. Prefixes: `feat`, `fix`, `refactor`, `chore`, `docs`, `test`.
Always branch from a fresh `origin/main`: `git fetch origin main && git checkout -b <branch> origin/main`.

## Session Artifacts

Base path: `docs/tasks/<slug>/`

| File | Purpose |
|------|---------|
| `<slug>_plan.md` | Living plan with checkboxes |
| `<slug>-review.md` | Independent review (v2, v3 and so on for scope expansion) |
| `<slug>-improvements.md` | Post-task reflection |
| `tmp/` | Throwaway scripts. Never committed (gitignored) |

## Source Layout

| Path | What |
|------|------|
| `sources/upload/admin/controller/extension/module/client_translate_expert.php` | Admin routes, settings, AJAX actions. OC4-only code is wrapped in `// OC4_EVENT_START` … `// OC4_EVENT_END` |
| `sources/upload/admin/model/extension/module/client_translate_expert.php` | DB queries for table and column analysis |
| `sources/upload/system/library/client_translate_expert_core.php` | Main translation logic, table analysis, batch processing |
| `sources/upload/system/library/vh_google_translator.php` | Google Translate API wrapper (covered by `tests/`) |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl` | Admin template (PHP syntax) for OC 1.5–3.0. 3.0 also ships the `.tpl` and renders it as PHP (`build.sh` makes the controller set `template_engine = template`) |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig` | Separate admin template for OC 4.0 |
| `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/…` | i18n. Every string goes in all three |
| `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml` | OCMOD injection (OC ≤ 3.0 only) |

Only ever edit `sources/`. `sources_release*/` and `releases/` are build output.

## Build & Test Commands

| Scope | Command | Notes |
|-------|---------|-------|
| Syntax check | `php -l <file>` | Local PHP is 7.4. It does **not** catch syntax that breaks on 5.6 |
| Unit tests | `php tests/test_vh_google_translator.php` | No API key needed |
| Unit + integration tests | `php tests/test_vh_google_translator.php <GOOGLE_API_KEY>` | Only with a key the user supplies |
| Bump version | `/bump-version` | Patch part of `_version.txt` (`MM.mm.pppp`). **Required before every build** |
| Build per-version zips | `./build.sh` | Leaves zips in the root. Prefer the release build |
| Release build | `./build.release.sh` | Output goes to `releases/v<version>/`. **Run after every change** (see `CLAUDE.md`) |

## Known Environment Gotchas

| Symptom | Cause | What to do |
|---|---|---|
| A module that works on OC 4.0 / PHP 8 white-screens on OC 2.x / PHP 5.6 | PHP 7+ syntax (type hints, `??`, nullable types, arrow functions) shipped to a pre-4.0 package. `build.sh` strips `// OC4_EVENT_START` … `// OC4_EVENT_END` blocks **only from the admin controller**. Markers in the model, libraries or `install.php` are not stripped, and the local `php -l` (7.4) accepts the syntax | Keep every file PHP 5.6 compatible. Put PHP 7+ code only inside the controller's marker blocks, and gate OC4-only behaviour elsewhere at runtime (`version_compare(VERSION, '4.0', '>=')`, as `client_translate_expert_core.php` does). Check the added lines and new files for these features before building |
| A feature works on OC 2.3 but 404s or fatals on 2.2 and earlier | `build.sh` rewrites routes and class names with plain `sed` (`extension/module/` → `module/`, `ControllerExtensionModule` → `ControllerModule`). A string written in another shape is not rewritten | Write routes and class names in the OC 2.3 form. After building, unzip the 2.2 or 1.5 package to a temp dir and grep for the new string |
| A new AJAX action, library class or setting works on OC 1.5–2.3 but 404s, fatals or reads an empty setting on 4.0 (settings: also on 3.0) | `build.sh`'s 3.0/4.0 rewrites are **fixed lists**, not patterns: the `/` → `.` method separator for each named controller and JS method, `new X(` namespacing for each named library class, and the `module_` prefix for seven named `client_translate_expert_*` setting keys | When adding one of these, add the matching line to the list in `build.sh`. After building, grep the oc4.0 (and, for settings, oc3.0) package for the un-rewritten form |
| A UI change shows up on OC ≤ 3.0 but not on 4.0, or the reverse | OC 1.5–3.0 render the `.tpl` as a PHP template. OC 4.0 uses its own `client_translate_expert.oc4.twig` | Apply template changes to both files, each in its own syntax. Never write Twig into the `.tpl` |
| `.zip` files or `OpenCartTranslateExpertClient-*` / `sources_release*` folders left in the repo root | `./build.sh` run on its own, or a build that aborted partway | Remove them. Prefer `./build.release.sh`, which cleans up after itself |
| `sed: 1: "…": invalid command code` or a no-op replacement in a build script | BSD `sed -i` (macOS) needs `-i ''`, while GNU `sed` does not | Use the `file_replace` helper in `build.sh`, which branches on `$OSTYPE` |
| `git diff` looks empty or partial although files changed | `git checkout <branch> -- <paths>` **stages** what it copies | Use `git diff HEAD` (staged and unstaged), and tell reviewers the same |
| `zsh: no matches found: --include=*.php`, or a `grep` that returns nothing and reads as "this symbol is not in the codebase" | zsh expands `*.php` itself and aborts when nothing in the cwd matches | Quote every glob: `grep -rn "X" --include="*.php" .`. Treat an empty result from an **unquoted** glob as a failed command, never as evidence that something is absent |
| `unexpected EOF while looking for matching` from a long heredoc | Bash mis-parses long or quote-heavy heredoc bodies | Write the file with the Write tool instead |
| The scratchpad directory vanishes mid-session | It is session-scoped | Anything that must survive the session goes in `docs/tasks/<slug>/tmp/`, and anything that must be durable gets committed |

## Commit Message Format

```
<type>: <short description>

closes #<issue-number>
```

Types: `fix`, `feat`, `refactor`, `chore`, `docs`, `test`. Start the description in lowercase. Proper nouns and file names keep their case (e.g. `docs: update README`).
Ask for the GitHub issue number if it was not provided. The only body line is `closes #N`, with no bullet points or other text. Drop it if there is no issue.
Use a HEREDOC to pass the message. PRs are squash-merged, so GitHub appends ` (#<PR>)` to the title on merge. Don't add it yourself.
