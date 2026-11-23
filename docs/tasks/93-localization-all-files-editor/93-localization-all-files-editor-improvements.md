# Improvements: 93-localization-all-files-editor

## Permission prompts

- No permission prompt stopped the work.
- The front-end implementer tried `git checkout` on the JS file. The permission system denied it. Nothing was lost.

## Steps skipped or improvised

- I did not run the mutation check for the bug-fix rule on the feature as a whole. I ran it only for the 2 review fixes. For each fix, I disabled the fix and the new tests failed.
- I made the small review fixes in the main context. The session model is the implementation model.

## Friction

- A new save rule (baseline update) changed the meaning of old tests. Tests that expected "a second save sends the same content" had to change. Tests that save one file and then reuse it had to restore the analysis state.
- A Python patch script with tab characters in the match text failed to match. The Edit tool worked.

## New steps that should exist

- If a task changes what a save does to the in-memory state, ask the plan to list the old tests that depend on the old state. The implementer can then update them at the start.

## Proposed edits to the workflow skills

- Done on 23.11.2026, at the request of the user:
  - Phase 2 of `dev-workflow/SKILL.md` has a new rule. If a change alters the state in memory, put the tests that expect the old behavior in the plan Steps.
  - `project-workflows.md` has a new gotcha row about a patch script with tab characters. Use the Edit tool.
