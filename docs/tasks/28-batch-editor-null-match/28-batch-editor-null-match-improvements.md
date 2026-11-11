# 28-batch-editor-null-match: improvements

## 1. Permission prompts
- No permission prompt occurred in this task. No new `.claude/settings.json` entry is necessary.
- No prune candidates were found in this task.

## 2. Skipped or improvised steps
- The task used the stripped-down flow (the user agreed). The Explore agent and the sanity check did not run. This was correct for a two-line fix.
- The "prove the test would have caught it" step did not apply, because no JS test harness exists. A `node` probe of the regex was the only evidence of the bug mechanism.
- The v2 review gave one Minor finding with "no action necessary" (the regex runs twice for each element). The workflow did not ask the user about it, because the finding was wording-level and matches the adjacent function.

## 3. New steps that should exist
- Before you choose the branch base, run `git fetch origin main`. Then compare with `git log origin/main`. The git status snapshot at session start was stale. It made a merged commit (#30) look unmerged, and this added a needless choice to the flow question.

## 4. Friction points
- The stale-base check above.
- Each subagent sends its report two times: one hand-back message and one task notification. The second message needs no action. It only adds noise.

## 5. Proposed edits to the workflow skills
- `dev-workflow` Phase 0, "Create the branch and task folder": before you discuss the branch base with the user, run `git fetch origin main` first.
- `dev-workflow` Phase 5: if a re-review gives only Minor findings marked "no action necessary", record them in the plan and recommend a stop. Do not open a fix-or-skip question for each one.
