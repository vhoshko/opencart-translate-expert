# 95-release-command review

Scope: `git diff HEAD` (`CLAUDE.md`, `.claude/project-workflows.md`) and the new files `.claude/skills/release/SKILL.md` and `.claude/commands/release.md`. Requirements: GitHub issue #95 and `95-release-command_plan.md`.

Settled and not argued again: the two-phase design, the tag without the `v` prefix, the patch part `0100`, no build in Phase 1, no patch bump in Phase 2.

Facts checked on 23.11.2026:

- The tag `04.01.0103` is an ancestor of `origin/main`. `git rev-list --count 04.01.0103..origin/main` gives 46.
- The tag `04.01.0103` is a lightweight tag. The GitHub release `04.01.0103` has the name `04.01.0103` and one asset `OpenCartTranslateExpertClient-v04.01.0103-20260921.full.zip`.
- `build.release.sh` removes old zips in `releases/v<version>/` before the build. Thus one `.full.zip` remains after each build.
- `releases/` and `sources_release*/` are in `.gitignore`. The build does not make the tree dirty.
- `gh release create` has the flag `--verify-tag`. Without this flag, `gh` creates a missing tag from the default branch.
- Every feature PR also changes `_version.txt` (patch bump in the same commit). `git log -- _version.txt` shows this.

### Strengths

- The skill covers all steps of the issue in the same order. It covers the version calculation with both examples from the issue (`04.02.0100` and `05.00.0100`).
- The skill uses `bump-version` with an explicit version. This keeps one owner for the `_version.txt` write and the "no trailing newline" rule.
- The asset path agrees with `build.release.sh` (`releases/vNEW/OpenCartTranslateExpertClient-vNEW-<date>.full.zip`). The "exactly one file must match" check is correct.
- The tag and release names agree with the existing release `04.01.0103` (no `v`, title equal to the tag).
- Good guards: dirty tree, missing tag, existing tag `NEW` in both phases, missing notes files, explicit "yes" before public actions, no tag delete on a build failure.
- The `CLAUDE.md` exception is short, limited to `/release`, and says "Do not use this exception for other work". It also covers the "always run `./build.release.sh`" rule, because it says Phase 1 runs no build.
- The text follows ASD-STE100 well. Sentences are short, instructions are imperative, and conditions come first. I found no semicolons, contractions or Latin abbreviations.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. `.claude/skills/release/SKILL.md:85` — `git commit` has no message argument. In the agent shell, this opens an editor. The command then hangs or fails. Give the message in the command, for example `git commit -F -` with a heredoc, or `git commit -m "chore: prepare release NEW" -m "Co-Authored-By: ..."`.

2. `.claude/skills/release/SKILL.md:116-119` — Phase 2 reads `NEW` from `_version.txt` at the tip of `main`. It takes `SHA` from the last commit that changes `_version.txt`. Every feature PR changes `_version.txt`. If a feature PR merges after the release PR and before Phase 2, `NEW` becomes a value like `04.02.0101`. The notes check then stops with the wrong reason ("The PR is not merged"). The release can then not continue without manual work. Find the release commit from the notes folder, not from `_version.txt`. Example:
   - `SHA=$(git log -1 --format=%H --diff-filter=A origin/main -- 'docs/release/*/release-notes.en.md')`
   - `NEW=$(git show "$SHA":_version.txt)`
   - Check that `docs/release/NEW/` exists in `SHA`.

   Alternative: ask for the PR number (Phase 1 prints it) and use `gh pr view <n> --json state,mergeCommit`. This also confirms the merge at low cost. Line 105 ("Do not check the PR state yourself") forbids this check. The issue does not ask for that limit.

3. `.claude/skills/release/SKILL.md:110-120` — The dirty-tree check comes after `git checkout main` and `git pull`. With local changes, `git checkout` can fail or carry the changes to `main`. Move `git status --short` and the "stop if not clean" rule to the start of Phase 2, before the checkout.

#### Minor (nice to have)

4. `.claude/skills/release/SKILL.md:160` — Add `--verify-tag` to `gh release create`. If the tag push failed and the agent missed the failure, `gh` creates a new tag at the tip of `main`. That tag can point to a different commit than `SHA`.

5. `.claude/skills/release/SKILL.md:24` — `git tag --sort=-v:refname | head -1` takes the highest tag of any name, also a tag that is not on `main`. Use a tag that is reachable from `origin/main` and has the version format. Example: `git describe --tags --abbrev=0 --match '[0-9][0-9].[0-9][0-9].[0-9][0-9][0-9][0-9]' origin/main`.

6. `.claude/skills/release/SKILL.md:132-145` — The tag is public before the build. If the build fails, a tag without a release stays on GitHub. The order follows the issue (tag push in step 5, build in step 6). The build does not use the tag. If you can change the order, build first and then push the tag. If you keep the order, no change is necessary.

7. `.claude/skills/release/SKILL.md:141` — After `git checkout SHA`, the repository stays on a detached HEAD. Add a last step: `git checkout main`.

8. `.claude/skills/release/SKILL.md:57` — If the branch `chore/release-NEW` exists locally or on `origin` (for example after a failed run), `git checkout -b` fails. Add a rule: if the branch exists, stop and ask the user.

9. `.claude/skills/release/SKILL.md:72` — "Other changes" lists only `refactor`, `chore`, `docs` and `test`. A commit with another type (`build`, `ci`, `perf`) or with no type has no group. Write "all other commits".

10. `.claude/skills/release/SKILL.md:8` — The skill defines `phase1`, `phase2` and empty. A different value (for example `phase 2` or `2`) has no rule, and the agent can do Phase 1 by mistake. Add: "If `$ARGUMENTS` has a different value, stop and ask the user."

11. `.claude/skills/release/SKILL.md:79` — Phase 1 writes the release date. The publish date in Phase 2 can be different. This is acceptable, but you can say that the date is the date of the PR.

### Assessment

**Ready to merge?** With fixes. The flow agrees with the issue and with `build.release.sh`, and the `CLAUDE.md` exception is correct. Fix items 1 to 3: the `git commit` call blocks in a non-interactive shell, and Phase 2 finds the wrong version if a feature PR merges before Phase 2.
