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
| `sources/upload/system/library/vh_google_translator.php` | Google Translate API wrapper (`tests/` tests it) |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl` | Admin template (PHP syntax) for OC 1.5–3.0. OC 3.0 also ships the `.tpl` and renders it as PHP, because `build.sh` makes the controller set `template_engine = template` |
| `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig` | Admin template for OC 4.0 only |
| `sources/upload/admin/language/{en-gb,ru-ru,uk-ua}/…` | i18n. Put every string in all three languages |
| `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml` | OCMOD injection (OC ≤ 3.0 only) |

Edit `sources/` only. `sources_release*/` and `releases/` are build output.

## Build & Test Commands

| Scope | Command | Notes |
|-------|---------|-------|
| Syntax check | `php -l <file>` | Local PHP is 7.4. It does **not** find syntax that breaks on 5.6 |
| Unit tests | `php tests/test_vh_google_translator.php` | No API key needed |
| Unit + integration tests | `php tests/test_vh_google_translator.php <GOOGLE_API_KEY>` | Use only a key that the user gives you |
| Bump version | `/bump-version` | Changes the patch part of `_version.txt` (`MM.mm.pppp`). **Do it before every build** |
| Build per-version zips | `./build.sh` | Leaves zips in the root. Use the release build instead |
| Release build | `./build.release.sh` | Puts the output in `releases/v<version>/`. **Run it after every change** (see `CLAUDE.md`) |

## Known Environment Gotchas

| Symptom | Cause | What to do |
|---|---|---|
| A module works on OC 4.0 / PHP 8 but shows a white screen on OC 2.x / PHP 5.6 | A pre-4.0 package has PHP 7+ syntax (type hints, `??`, nullable types, arrow functions). `build.sh` removes `// OC4_EVENT_START` … `// OC4_EVENT_END` blocks **only from the admin controller**. It does not remove markers in the model, libraries or `install.php`. The local `php -l` (7.4) accepts the syntax | Keep every file PHP 5.6 compatible. Put PHP 7+ code only inside the marker blocks of the controller. Elsewhere, control OC4-only behavior at runtime with `version_compare(VERSION, '4.0', '>=')`, as `client_translate_expert_core.php` does. Before you build, check the added lines and new files for these features |
| A feature works on OC 2.3 but gives a 404 or fatal error on 2.2 and earlier | `build.sh` rewrites routes and class names with plain `sed` (`extension/module/` → `module/`, `ControllerExtensionModule` → `ControllerModule`). It does not rewrite a string in another shape | Write routes and class names in the OC 2.3 form. After the build, unzip the 2.2 or 1.5 package to a temp dir. Then grep for the new string |
| A new AJAX action, library class or setting works on OC 1.5–2.3. On 4.0 it gives a 404 or fatal error, or reads an empty setting (settings: also on 3.0) | The 3.0/4.0 rewrites in `build.sh` are **fixed lists**, not patterns. The lists hold the `/` → `.` method separator for each named controller and JS method, `new X(` namespacing for each named library class, and the `module_` prefix for seven named `client_translate_expert_*` setting keys | If you add one of these items, add the matching line to the list in `build.sh`. After the build, grep the oc4.x package for the form that the build did not rewrite. For settings, also grep the oc3.0 package |
| A UI change shows on OC ≤ 3.0 but not on 4.0, or the reverse | OC 1.5–3.0 render the `.tpl` as a PHP template. OC 4.0 uses its own `client_translate_expert.oc4.twig` | Change both template files, each in its own syntax. Never write Twig into the `.tpl` |
| `.zip` files or `OpenCartTranslateExpertClient-*` / `sources_release*` folders stay in the repo root | You ran `./build.sh` alone, or a build stopped before the end | Remove them. Use `./build.release.sh`, which removes these items itself |
| `sed: 1: "…": invalid command code`, or a replacement that does nothing, in a build script | BSD `sed -i` (macOS) needs `-i ''`. GNU `sed` does not | Use the `file_replace` helper in `build.sh`. The helper uses `$OSTYPE` to choose the correct form |
| `git diff` shows nothing or only some changes, but files changed | `git checkout <branch> -- <paths>` **stages** the files that it copies | Use `git diff HEAD` (staged and unstaged changes). Tell reviewers to do the same |
| `zsh: no matches found: --include=*.php`, or a `grep` that returns nothing and seems to show "this symbol is not in the codebase" | zsh expands `*.php` itself. It stops with an error if nothing in the cwd matches | Quote every glob: `grep -rn "X" --include="*.php" .`. An empty result from an **unquoted** glob is a failed command. Never use it as proof that something is absent |
| `unexpected EOF while looking for matching` from a long heredoc | Bash parses long heredoc bodies, or bodies with many quotes, in the wrong way | Write the file with the Write tool |
| The scratchpad directory disappears during the session | It belongs to one session only | Put anything that must survive the session in `docs/tasks/<slug>/tmp/`. Commit anything that must stay for a long time |

## Commit Message Format

```
<type>: <short description>

closes #<issue-number>
```

The types are `fix`, `feat`, `refactor`, `chore`, `docs` and `test`. Start the description in lowercase. Proper nouns and file names keep their case, for example `docs: update README`.

If the user did not give the GitHub issue number, ask for it. The only body line is `closes #N`, with no bullet points or other text. If there is no issue, omit this line.

Use a HEREDOC to pass the message. PRs are squash-merged, so GitHub adds ` (#<PR>)` to the title on merge. Do not add it yourself.
