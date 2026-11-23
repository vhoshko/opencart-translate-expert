# 95-release-command

## Settings

- Planning model: Opus (inline plan, session model Sonnet wrote it from the issue text)
- Implementation model: Sonnet
- Review model: Opus (`ote-code-reviewer`)
- Review findings rule: fix all. Skip only with a verified reason.
- Flow: tooling change under `.claude/**` and `CLAUDE.md`. No package content changes. Use the docs-only flow: checks-only run, no version bump, no build.
- Standing auto-approve: yes. The user said "auto approve mode". Do not ask questions between phases.
- Commit, push and PR: not requested for this task. The workflow stops after the review.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/95

The repository has no command that prepares a full release. `build.release.sh` only builds. `bump-version` only increments the patch part.

Facts checked on 23.11.2026:

- `_version.txt` holds `04.01.0225`.
- The only git tag is `04.01.0103`. It has no `v` prefix.
- `releases/` is in `.gitignore`. The build output is not in git.
- `.claude/commands/<name>.md` is a thin wrapper that points to `.claude/skills/<name>/SKILL.md`.
- `bump-version` accepts an explicit version and refuses a version that is not higher than the current one.
- Pull requests use a squash merge.

Affected OpenCart versions: none. The module code does not change.

## Approach

Add a `release` skill and a `/release` command in the same pattern as `bump-version`. The skill has 2 phases. Phase 1 makes the version change and the notes. It commits and opens the PR. Phase 2 starts after the user says that the PR is merged. It asks for confirmation. Then it tags, pushes, builds without a patch bump, creates the GitHub release and attaches the `.full.zip`. Add an exception to `CLAUDE.md` for the version rule.

## Assumptions

- ✅ verified: `bump-version` takes an explicit version `MM.mm.pppp`. Evidence: `.claude/skills/bump-version/SKILL.md` section "Input".
- ✅ verified: the build puts the asset at `releases/v<version>/OpenCartTranslateExpertClient-v<version>-<date>.full.zip`. Evidence: `build.release.sh` lines 8 and 21.
- ❓ unverified: `gh release create <tag> <file>` uploads the asset in one call. This is standard `gh` behavior. No release was created in this task, because the action is public.

## Steps

- [x] Code: write `.claude/skills/release/SKILL.md`
- [x] Code: write `.claude/commands/release.md`
- [x] Docs: add the exception to `CLAUDE.md` and `.claude/project-workflows.md`
- [x] Checks-only run (docs-only flow, no bump, no build)
- [x] Independent review → 95-release-command-review.md
- [x] Address review findings
- [x] Re-review? (not asked in auto-approve mode unless fixes touch logic)
- [x] Improvements pass → 95-release-command-improvements.md

## Done

Added the `/release` command and the `release` skill (2 phases). Added the version-rule exception to `CLAUDE.md` and a row to `project-workflows.md`. Checks-only run passed. The review found 3 Important and 8 Minor findings. All were fixed except one, with a reason in the update below. No build, no version bump, no commit. No live release was created, so the `gh release create` flow is untested.

## Update (review fixes)

Fixed all review findings in `.claude/skills/release/SKILL.md`. Skipped one: the build runs after the tag push. The order follows issue #95, and the skill already says to keep the tag if the build fails.
