# 68-runtime-version-rewrites: improvements

## Permission prompts
- None blocked the run.
- No prune candidates were found in this session.

## Steps skipped or improvised
- The plan used a baseline build from `origin/main` in a temporary worktree. The workflow has no step for this. A baseline compare is a good proof for any refactor of `build.sh`.
- The re-review was skipped. The review fixes changed tests and docs only.
- No store run was possible in the unattended flow (assumption A9).

## New steps that should exist
- For a change to `build.sh`, run a behavior compare: build the baseline, build the new code, then probe the helpers and the JS in each package. Add this to Phase 3 of the dev-workflow skill.
- When an implementer edits a `.claude/agents/*.md` file, tell the reviewer to read the full diff of that file. A bullet was lost in this task (finding I1).

## Friction points
- The investigation agent said 7 setting keys. The loop in `build.sh` has 10. The planner found the difference with a grep. Check each count in an investigation report.
- A test that calls only a helper does not prove that the init code calls the helper. A static check of the source closes this gap (finding I2).

## Proposed edits to the workflow skills
- `dev-workflow` Phase 3: add the behavior compare for a `build.sh` change.
- `dev-workflow` Phase 4: for a diff that edits `.claude/**`, tell the reviewer to check for lost lines.
