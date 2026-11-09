# 15-docker-test-environment — improvements

No earlier `*improvements*.md` exists in `docs/tasks/` (only `opencart-4.0-support/research.md`), so nothing below is a repeat. Each item is reported once here; if one comes up again, it goes into the skill or the Known Environment Gotchas table.

## 1. Permission prompts

- **Safety check on `rm -rf` text inside a heredoc.** A `python3 - <<'EOF'` edit of `install-module.sh` was blocked because the replacement text contained the script's own `trap 'rm -rf "$WORK"' EXIT` line. Nothing was deleted. Workaround: edit shell scripts that contain `rm -rf` with the Edit tool, not a Python/sed heredoc.
- **Suggested additions** to `.claude/settings.json`. These are low risk, local only, and recur on every Docker test run:
  - `Bash(docker compose -f docker/compose.yaml *)`
  - `Bash(docker/install-module.sh *)`
- **Prune** from `.claude/settings.local.json`:
  - `Bash(bash /Users/macbook/VH/vh-repos/opencart-translate-expert/build.release.sh)` points at an old checkout path (`VH/vh-repos`) and can never match again.
  - `Bash(./build.sh)` and `Bash(bash build.sh)` are probably redundant, since CLAUDE.md says to prefer `./build.release.sh`.

## 2. Steps skipped or improvised

- **Regression-test proof (Phase 3)** doesn't apply: this isn't a bug fix and there is no test harness. Replaced with **negative tests against the live stores**: deliberately break the thing each smoke check guards, confirm the check fails with exit 1, then restore. The first negative test was vacuous, because the update path overwrote the broken file before checking. That led to the `-c` (checks only) flag.
- **Templates and Language steps** were dropped (no UI change).
- **The version bump and release build ran three times** (0106, 0107, 0108) although `sources/` never changed. CLAUDE.md requires it after every change, and the packages were needed for the end-to-end runs. But three version numbers were used up on tooling-only edits.

## 3. New steps that should exist

- **For tooling that contains checks:** each check needs a negative test that makes it fail, and the result goes into the plan. Two such tests caught real problems this task:
  - the login page passed the original "page renders" check;
  - the reviewer's suggested marker didn't exist in the 3.0/4.0 builds.
- **Verify reviewer claims about build output against a built package**, the same way the skill already says to verify investigation-agent claims. Both review passes contained one factual error each:
  - v1 said install.json shouldn't land in the OC4 extension folder, but OpenCart's real installer puts it there;
  - v1's suggested marker ignored `build.sh`'s `module_` prefix.

## 4. Friction points

- **The Explore agent was wrong on an external fact.** It said `oc_extension_install` is only needed for the Installer page. OC4 startup actually autoloads extensions from it (`admin/controller/startup/extension.php`). It was caught because the skill says to verify facts before designing on them. Having the OpenCart release zips downloaded in the scratchpad made checks like that cheap.
- **`build.sh` also renames form field ids.** It adds `module_` to settings for 3.0/4.0, and that also changes ids such as `input-module_client_translate_expert_key`. Anything that greps rendered admin HTML must accept both forms. First occurrence, so it's reported here rather than added to the gotchas table.
- **The workflow assumes `sources/` changes.** The `ote-build-test` agent, the PHP 5.6 scan and the per-version rewrite checks all had nothing to do. The agent handled that correctly, but each run still used up a version number.

## 5. Proposed edits to the workflow skills (not applied)

- `dev-workflow` Phase 3, under "Prove the test would have caught it": add a paragraph for check scripts and tooling, saying "make each check fail once on purpose and record it", as in section 3.
- `dev-workflow` Phase 4, step 1: add "verify factual claims in the review (especially about build output or external systems) before deciding fix/skip".
- `CLAUDE.md` / `project-workflows.md`: decide whether changes confined to `docker/`, `docs/` or `README.md` need a version bump and build at all. **This is the user's call.** The current rule is unconditional.
- `project-workflows.md` Source Layout: add a row for `docker/` (test stores, not shipped in any package).
