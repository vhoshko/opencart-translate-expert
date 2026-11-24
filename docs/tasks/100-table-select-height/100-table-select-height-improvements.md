# Improvements: 100-table-select-height

## Permission prompts

- No new recurring prompt.

## Steps skipped or improvised

- The Explore agent was skipped. Two greps found the select and the CSS.
- The re-review pass was skipped because the user asked to continue.

## New steps that should exist

- If the user pastes theme CSS or a screenshot after the build, record it in the plan as an update. Then run the verify loop on files that nobody edits.

## Friction points

- I edited files while `ote-build-test` ran. The build then mixed old and new files (the test count showed 8 and the CSS had the new rule). One more build was needed. Do not edit `sources/`, `tests/` or `docs/` during a build.
- A regex for an HTML tag broke on the `?>` inside a `.tpl` tag. Match the tag on one line, not up to the first `>`.

## Proposed edits to the workflow skills

1. Add a row to Known Environment Gotchas in `project-workflows.md`: "A build agent runs and files change. The package mixes old and new files. Wait for the notification before any edit."
2. Add one sentence to Phase 3 of `dev-workflow`: "Do not edit the repo while `ote-build-test` runs."
