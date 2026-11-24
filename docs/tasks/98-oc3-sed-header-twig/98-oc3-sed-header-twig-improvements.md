# Improvements: 98-oc3-sed-header-twig

## Permission prompts

- No new recurring prompt. The probes used the scratchpad.

## Steps skipped or improvised

- The Explore agent was skipped. The session had already proved the root cause with a probe.
- No second review pass. The first review found only minor items, and tests cover the fixes.

## New steps that should exist

- If a task changes `build.sh` text rewrites, run the build on Linux and on macOS. The container has GNU `sed` only. BSD `sed` stays untested here.
- Add a "build output check" to Phase 3 for packages: open the built file that the rewrite changes and read the result. A green build did not show this bug.

## Friction points

- The release build ran in the Linux container and the maintainer builds on macOS. The two `sed` versions gave different packages from the same `build.sh`. No check compared them.
- The build agent said that `CLAUDE.md` may be outdated about `.tpl` in OC 3.0. This is a false alarm. The header file is `header.twig`, and the module page stays `.tpl`. The text in `project-workflows.md` is correct but easy to misread.

## Proposed edits to the workflow skills

1. Add a row to **Known Environment Gotchas** in `project-workflows.md`: "`sed` in `build.sh` differs between GNU and BSD. Use `[?]` and `[$]`, never `\?` or `\>`. `tests/test_oc30_ocmod_xml.php` checks this."
2. Add a line to Phase 3 of `dev-workflow`: after a build change, read the changed lines in the built package.
3. Add one sentence to `project-workflows.md`: "OC 3.0 patches `header.twig`, and the module page uses `.tpl`."
