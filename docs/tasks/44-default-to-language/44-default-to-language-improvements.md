# 44-default-to-language — improvements

## 1. Permission prompts

- The agent hit no permission prompts in this task.
- The agent did not check the allowlist for prune candidates.

## 2. Skipped or improvised steps

- The user rejected the Phase 0 question dialog. The dialog had four questions: flow, models, leftovers and open questions. Then the user said "continue". The agent used the recommended defaults and recorded them in the Settings block of the plan.
- The agent did not run the sanity check. The stripped-down flow skips it.
- The agent left the task-36 leftovers (`.claude/project-workflows.md`, the task-36 improvements file) in the working tree. It did not stash them. They are not part of this task.
- The agent wrote a PHP probe (`tmp/probe.php`) for the `.tpl` selection logic. No unit test covers templates.

## 3. New steps that should exist

- None. The stripped-down flow was sufficient for a 2-file template change.

## 4. Friction points

- The session started on the merged task-36 branch with uncommitted task-36 files. The skill says "stop and ask" for unrelated changes. When the user does not answer, the skill gives no default. Leaving the files in place was safe, because `checkout -b` carries them over and the task diff does not include them.
- The issue text gave the user decision ("FE only") only as a message during the turn. The agent had to put this decision into the plan by hand.

## 5. Proposed edits to the workflow skills

- `dev-workflow` Phase 0: if the user declines the setup questions and says to continue, use the recommended defaults. Record them in the Settings block and tell the user in one line.
- `dev-workflow` Phase 0: for leftovers that belong to a merged task, the default is "leave in the working tree, do not stash". Ask only if a file overlaps the files of the new task.
