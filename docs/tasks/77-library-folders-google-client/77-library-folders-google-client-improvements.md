# 77-library-folders-google-client: improvements

## Permission prompts
- No permission prompt came up that needs a new entry in `.claude/settings.json`.

## Steps that were skipped or improvised
- The planning subagent cannot ask the user. The design decisions (folder name, class names, where the empty-text rule goes) were open. The main context asked the user before the plan was written. This worked well.
- The user changed the scope during Phase 0 (one common folder, all 7 files renamed). The plan was written after the answers, so the plan had no rework. The issue text was out of date and was updated by request.
- The baseline comparison ran in a separate subagent in parallel with the review. This saved time and context.

## Friction
- The plan step 5 said to run `mkdir -p` for the target folder before `mv`. This nests the folder. A plan step that moves a folder must say "the target folder does not exist before `mv`".
- The first version of the path test passed with a wrong path prefix. The review found it with a mutation probe. A test for "a path must exist" must resolve the full path expression, not only the file name.
- `build.sh` in a baseline worktree leaves its zips in the worktree root and not in `releases/`. The baseline agent had to find them.
- A folder `docs/tasks/<slug>/tmp/zips` existed that no agent of this task made. The source is unknown.

## New steps that should exist
- In Phase 3, "Compare with a baseline": add the note that `build.sh` leaves its zips in the root of the worktree.
- In Phase 4, for a path or rename task: tell the reviewer to run a mutation probe on the path test (break one path in a temp copy).

## Proposed edits to the workflow skills
- `dev-workflow` Phase 0: add "If the task has open design decisions that the planning subagent cannot ask, ask them before the plan is written." Add them to the brief of the subagent as settled decisions.
- `dev-workflow` Phase 3: add the zip location note above.
