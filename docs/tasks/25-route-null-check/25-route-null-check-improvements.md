# 25-route-null-check — improvements

## 1. Permission prompts
- No permission prompts occurred. No allowlist change is necessary.

## 2. Skipped or improvised steps
- The user agreed to the stripped-down flow. The flow did not use the Explore agent or the checks-only sanity check.
- The plan checkpoint was short. The user approved the flow before the plan, and the diff was 2 lines.
- The "prove the test would have caught it" step did not apply. The repository has no JS test harness.

## 3. New steps that should exist
- `ote-build-test` has no JS syntax check. The orchestrator asked for `node --check` in the prompt. Proposal: if a `*.js` file under `sources/` changed and `node` is available, the agent runs `node --check` on the file.

## 4. Friction points
- The skill does not define the stripped-down flow. Each session must make up the phase list again. Proposal: add a short list to `dev-workflow`. The list is branch, short plan, fix, build and test, one review, then improvements.
- The checks-only and full-build agent removed `docs/tasks/<slug>/tmp/` after its unzip check. The plan uses this folder for throwaway scripts. Proposal: tell the agent to unzip into a subfolder of `tmp/` and remove only that subfolder.

## 5. Proposed edits to workflow skills
- `.claude/agents/ote-build-test.md`: add the `node --check` step for changed JS files.
- `.claude/agents/ote-build-test.md`: unzip into `docs/tasks/<slug>/tmp/unzip/` and remove only that subfolder.
- `.claude/skills/dev-workflow/SKILL.md`: add the stripped-down phase list.

This is the first time for each friction point (grep found no earlier record). No skill edits were made.
