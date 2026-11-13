# 50-module-language-keys: improvements

## 1. Permission prompts

- No permission prompt stopped the work. Auto mode was active.
- No change to `.claude/settings.json` is necessary for this task.

## 2. Skipped or improvised steps

- The Explore agent was skipped. The issue gave exact lines, and 4 greps verified each claim. The plan Context records the greps.
- The mutation check was done test-first. The new key-set test ran on the old language files before the fix and gave 4 failures. A separate disable-and-restore cycle was then not necessary.
- The sanity check came before the plan, and it was green.

## 3. New steps that should exist

- If the issue has exact `path:line` claims, verify them with greps in Phase 0. Skip the Explore agent. This took 4 tool calls in this task.

## 4. Friction points

- **Repeated (tasks 44 and 50): uncommitted changes from an earlier task at session start.** In task 50, the user chose "reset all changes". The reset removed 3 new rows in the gotchas table of `.claude/project-workflows.md` and the task-36 improvements file that those rows point to. A copy exists only in the session scratchpad, and the scratchpad does not survive the session.
- The issue gave line numbers for the branch before the merge (`3587400`). After the squash merge (`a684ee8`), the line numbers were the same, so this caused no problem.
- `docs/tasks/<slug>/tmp/` was not present after the review. A subagent removed it, or the reviewer did not make it. No probe needed it.

## 5. Proposed edits to the workflow skills

- `dev-workflow` Phase 0 (fix for the repeated friction above). If the uncommitted changes include edits to `.claude/**` or `docs/tasks/**`, show `git diff --stat` and the new gotcha rows before the question. Then offer these options:
  - Commit them on a separate branch.
  - Carry them over.
  - Stash them.
  - Reset them.
  Before a reset, save `git diff` and the untracked files to `docs/tasks/<slug>/tmp/`, not to the scratchpad.
- No other skill edit is necessary.
