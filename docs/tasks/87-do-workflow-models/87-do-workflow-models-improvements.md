# Improvements: 87-do-workflow-models

## Permission prompts
- No new recurring prompt. The task needed no entry for `.claude/settings.json`.

## Steps skipped or improvised
- Phase 0 did not ask for models. The issue gave the defaults, and this task is the change that removes the question.
- I skipped the Explore agent. The task touched only the workflow files, and I had read the relevant sections.
- I skipped the verify loop after both fix passes. Only Markdown changed (docs-only flow).

## Friction
- The re-review gate says "Never skip it". The unattended run in `end-to-end-with-pr` memory does not say how to answer it. The text is old, so this task did not change it.
- The planning subagent could not know the plan format of the model rule that this task changes. The brief held the settled decisions, and this worked.

## Proposed edits
- Decide if the re-review gate needs a rule for an unattended run. Open a new issue if the user wants it.
- Add the "no action necessary" skip reason to `ote-code-reviewer.md`, so that the reviewer marks it in a fixed form. This is optional.
