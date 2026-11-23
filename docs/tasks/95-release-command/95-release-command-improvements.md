# Improvements: 95-release-command

## Permission prompts

None found.

## Skipped or improvised steps

- No investigation subagent. The task touched only new tooling files.
- The plan was written inline by the Sonnet session. The workflow asks for Opus.
- The flow was docs-only: checks-only run, no bump, no build.

## Friction

- The skill text was edited outside the session while the review ran. The Edit tool then needed a fresh read.

## Proposed edits

- Add a dry-run option to the release skill, so that Phase 2 can be tried with no tag and no release.
- Test the release flow on a throwaway repository before the first real release.
