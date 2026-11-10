# 19-docker-uk-ua-language: improvements

## 1. Permission prompts
None worth an allowlist entry. The commands were one-off greps, `sh -n`/`bash -n` and git.

## 2. Steps skipped or improvised
- **Commits and pushes mid-workflow.** The workflow never commits, but live verification needed the change on the user's host. The user's host checkout is **not** the container's `/workspace` (the global CLAUDE.md says changes in `/workspace` are "visible to the user on the host immediately", which was not true here). The first live run therefore tested the old entrypoint and showed only `en-gb`. Fix used: with the user's OK, push a WIP branch and have them check it out.
- **Re-review gate.** After the fix pass the user moved straight to retest and the PR. A second review was not requested. The later `docker/rebuild-stores.sh` was not independently reviewed either.
- **Planning and review on the same model** (Fable 5.1 for both), at the user's request.

## 3. New steps that should exist
- **Docker/host-side changes:** before asking the user to test on their host, ask whether the host checkout is the same folder as `/workspace`. If not, plan the hand-off (push a WIP branch) up front instead of finding out from a failed test.
- **Shell-embedded PHP:** test the snippet exactly as the shell will run it (heredoc → `php` on stdin), not only as a file with `php -l`. Here that caught `STDERR` being undefined on stdin.

## 4. Friction points
- The first awk extraction of the heredoc missed the tab-indented `php <<'PHP'` line, so the test ran an empty file and "passed" (exit 0). A test that does nothing looks green. Check that the extracted snippet is non-empty.
- The version number collides with `feat/1-support-opencart-4-1` (both use 04.01.0111 at some point). Parallel branches each bump `_version.txt`, so whichever merges second always conflicts.

## 5. Proposed edits to the workflow skills
- `dev-workflow` Phase 3/5: add "If verification runs on the user's host, confirm the host sees this working tree; otherwise push a WIP branch (ask first)".
- `.claude/project-workflows.md` gotchas: add "Host checkout may differ from `/workspace`. Host-side tests run old code" and "PHP read from stdin has no `STDERR`/`STDIN` constants; use `php://stderr`".
- Global CLAUDE.md: the "Filesystem changes … visible to the user on the host immediately" line did not hold in this session; the user should confirm which setup is current.

None of these edits were made; they await the user's go-ahead.
