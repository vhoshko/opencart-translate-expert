# 24-char-count-reset: improvements

## Permission prompts
- No new prompts came up in this task.
- Prune this rule from `.claude/settings.local.json`: `Bash(bash /Users/macbook/VH/vh-repos/opencart-translate-expert/build.release.sh)`. The path is from an old checkout location, so the rule can never match again. `Bash(./build.release.sh *)` already covers the release build.

## Skipped or improvised steps
- The workflow skipped the Explore investigation and the background sanity check. The issue gave the exact lines, and a direct read confirmed them in one call. The full build in Phase 3 ran the same checks.
- The workflow wrote no regression test. No test harness covers the admin controller.

## New steps that should exist
- Tell the reviewer that `docs/tasks/*/tmp/` is git-ignored. The reviewer of this task flagged the `tmp` folder as a possible commit risk. Add one line to the review brief, or to `ote-code-reviewer.md`.

## Friction points
- Review finding 1 (a translation during a save can lose its count) looked like a local fix. In fact it needs a redesign of the counter storage. The reviewer marked it Minor and "no action", but the fix-or-skip question still offered it as a fix. Before you offer a fix, check the real cost of each finding against the code.
- The settings-save path and `storeTranslatedCharCount()` both do a read-modify-write of the counter from the config snapshot of the request. No issue tracks this. The user chose not to open one.

## Proposed edits to the workflow skills
- `dev-workflow` Phase 4, step 2: for each finding, check the fix cost in the code before you show the fix-or-skip question. Do not copy the cost from the reviewer.
- `ote-code-reviewer.md`: add "`docs/tasks/*/tmp/` is git-ignored. Do not flag it."
