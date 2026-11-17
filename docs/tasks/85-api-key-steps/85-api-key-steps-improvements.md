# 85-api-key-steps improvements

## Permission prompts
- None noted.

## Steps skipped or improvised
- Skipped the Explore agent. The change touches 2 known files.
- Skipped the verify loop after the review fixes. Only Markdown changed.

## Friction points
- The planning subagent did not see the `/issue` command arguments. It wrote that the Ukrainian source text was missing. Put the user text in the brief of a planning subagent.
- The user chose Opus for planning and review. The workflow asks a second question to confirm. This took one extra turn.

## Proposed edits to the workflow skills
- In Phase 0, ask the model question once. If the user picks the same model for both, ask the confirmation in the same step.
