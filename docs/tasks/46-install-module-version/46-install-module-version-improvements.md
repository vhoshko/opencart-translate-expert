# 46-install-module-version improvements

## 1. Permission prompts
No permission prompts occurred. Auto mode was on. No allowlist change is necessary.

## 2. Skipped or improvised steps
- The stripped-down flow was used, with the user's approval. No Explore agent and no sanity check ran. The Context section of the plan records the greps.
- The task changed no file under `sources/`. The build and the version bumps (04.01.0166, 04.01.0167) ran only because `CLAUDE.md` requires a build after each change. For a change to `docker/` only, a checks-only run would be sufficient.
- The re-review was skipped under auto-approve. The first review found one Minor issue only. A probe verified the one-line fix.
- The user asked for a commit, a push and a PR at the end. This overrides the default "never commits" rule of the workflow.

## 3. New steps that should exist
- Add a rule for tasks that change only `docker/`, `tools/` or docs: run the build in checks-only mode and do not bump the version. Every full build uses up a patch number for a package with no change. This needs a decision from the user, because `CLAUDE.md` says "always run `./build.release.sh`".

## 4. Friction points
- A probe started with `cd <task>/tmp && ...`. The reviewer had removed `tmp/` after its own probe, so the `cd` failed. The next lines ran in the repo root. They overwrote `_version.txt` and then deleted it. The file was restored from the release folder name (`04.01.0166`). This is the second occurrence (task 3 had a `cd` issue too). The rule is now in the dev-workflow skill (Phase 2).
- The reviewer brief said "remove the fake layout after". The reviewer removed the whole `tmp/` folder. Tell the reviewer to remove only its own subfolder.
- The build agent reported "390 passed" in the verify loop, but its per-file counts add up to 393. Read the per-file counts, not the total.

## 5. Proposed edits to the workflow skills
- Done: the dev-workflow skill, Phase 2, now requires absolute paths and `set -e` in probes.
- Proposed: in `ote-code-reviewer.md`, say "remove only the subfolder you made under `tmp/`, never `tmp/` itself".
- Proposed: in `ote-build-test.md`, compute the total from the per-file counts.
