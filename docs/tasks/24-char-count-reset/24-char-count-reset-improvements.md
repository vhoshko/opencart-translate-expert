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

## Update 11.11.2026: atomic counter writes
- The Docker test stores (`docker/`) made a real end-to-end and race test possible. Run SQL and race checks there before a review. Then the review can focus on edge cases.
- A race test must also start from an empty state. The first `race.sh` set the row to 0 first, so it did not cover the concurrent insert. Review v2 found this gap.
- To prove a race test, run the old pattern in the same setup. The old read-then-write pattern gave 135 of 1000.
- Proposed edit to `dev-workflow` Phase 3: if the Docker stores run, use them for checks that need a database. Keep the scripts in `docs/tasks/<slug>/tmp/`.
