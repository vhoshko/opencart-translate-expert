# 1-support-opencart-4-1: improvements

## 1. Permission prompts hit
- Running a freshly npm-installed `@php-wasm/cli` binary (the workaround for the old container having no PHP) was blocked by the auto-mode classifier. That was correct, and the real fix was the new container image, which now has PHP 8.2 + zip. **No allowlist entry is suggested.**
- No other prompts. Nothing to prune: there is no project `.claude/settings.json` Bash allowlist yet.

## 2. Steps skipped or improvised
- **Re-review after fixes was skipped** under auto-approve. The fixes were whitespace, a `.gitignore` line and a doc sentence, and the first review found only wording-level issues.
- **No regression test.** The changed library code (`client_translate_expert*.php`) has no test harness. The guard for the `${}` deprecation was a strict lint run by hand.
- **Mid-task container swap.** The handoff plan (committed and pushed) carried the session across, and the investigation results were saved into it before the swap, so nothing was redone. The plan-as-handoff mechanism worked as designed.

## 3. New steps that should exist
- **Strict lint in `ote-build-test`'s standard sequence:** `php -d error_reporting=-1 -d display_errors=1 -l`. The checks-only sanity run reported "0 deprecated" because the CLI default `error_reporting` hides E_DEPRECATED, while the strict run immediately showed the two `${}` deprecations. OpenCart 4.1 displays E_DEPRECATED on screen by default, so this matters for every future change.
- **Phase 0 environment check:** `command -v php zip` before dispatching the sanity check, so a missing interpreter surfaces as an environment gotcha rather than a "BLOCKED" subagent report.

## 4. Friction points
- The old container had no PHP, which cost a full container rebuild and a session handoff.
- The OpenCart reference sources for the version comparison had to be sparse-cloned twice, once per container. Keeping them under `docs/tasks/<slug>/tmp/` (gitignored, but in the mounted workspace) rather than the session scratchpad would have avoided the second clone.

## 5. Proposed edits (not applied, awaiting your go-ahead)
- `.claude/agents/ote-build-test.md`: make the syntax step use `php -d error_reporting=-1 -d display_errors=1 -l` and fail on any `Deprecated` line.
- `.claude/project-workflows.md` → Known Environment Gotchas, add a row: "`php -l` reports clean but OpenCart 4.1 shows 'Deprecated: …' in AJAX responses | CLI default error_reporting hides E_DEPRECATED | lint with `-d error_reporting=-1`".
- `.claude/skills/dev-workflow/SKILL.md` Phase 0: before the sanity check, verify that `php` and `zip` exist. When comparing OpenCart versions, clone the reference sources into `docs/tasks/<slug>/tmp/` so they survive a session or container change.
