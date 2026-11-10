# 22-ste-agent-instructions: independent review, pass 2

Date: 10.11.2026. Reviewer model: Fable 5.2.

Scope: the fixes for the 15 findings of pass 1, in `git diff origin/main -- CLAUDE.md .claude` (14 files). The reviewer read each fixed location in the current files and compared it with the `origin/main` text. The reviewer did not measure sentence length, code blocks or frontmatter again (established facts in the brief). The reviewer did not examine the settled decisions again.

### Strengths

- All 3 important findings are fixed correctly:
  - `CLAUDE.md:66` says "After you make changes" again. It agrees with `.claude/project-workflows.md:48`.
  - `.claude/skills/bump-version/SKILL.md:55` is a statement again. It does not conflict with "Do not commit".
  - `.claude/skills/dev-workflow/SKILL.md:222-227` is one numbered list in the correct sequence: copy, disable, run, confirm, restore, verify. The copy step is first. The list keeps "(not the test)", "**and that it fails on the assertion you expect**", "Do not retype the code" and the `git diff --stat` check. The paragraph after the list (line 229) keeps the "no filter" fact, the inline command, the **checks-only** option and the "Never run a full build" rule. The Markdown list is correct.
- The spelling sweep (finding 5) changed prose only. The reviewer examined each diff line that contains "behavior", "catalogs", "judge", "afterward" or "license". No code span, path, identifier or quoted literal changed. The `en-gb` language codes are intact. A search for other British forms in the 14 files found none.
- "the team" is gone from all 14 files. The replacements name the correct actor: "What the implementer built" (`ote-code-reviewer.md:18`), "what the task requested and what the implementer says it built" (`ote-spec-reviewer.md:12`), "Is everything that the task requires present?" (`ote-spec-reviewer.md:35`).
- The dispatch summary table (`dev-workflow/SKILL.md:322-333`) has 4 cells in each row and renders correctly. The new cell texts keep the meaning of the original cells.
- The fixes for findings 4, 6, 7, 8, 9, 11, 12 and 14 match the suggested text or an equal text. The fixes caused no meaning drift and no broken literal string. `ote-build-test.md:90` has its condition again. `dev-workflow/SKILL.md:44` is conditional on the default again.
- The Phase 4 brief has no STE bullet (finding 13). The pointers in Phase 1 and Phase 7 are still there.
- Unit tests: 24 passed, 0 failed, 6 skipped.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

**1. Minor — `.claude/skills/dev-workflow/SKILL.md:327` — one part of finding 15 is not fixed.**

Pass 1 named two passive table cells: "no version is used up" and "was chosen". The first cell is fixed (line 325). The second cell still says "Only if a non-default planning model was chosen". This is an STE item only (rule 3.6). It does not change what an agent does.

Fix: "Only if the user chose a non-default planning model".

### Assessment

**Ready to merge?** Yes.

The fixes for the 15 findings are correct, and 14 are complete. The fixes introduced no meaning drift, no broken literal string and no broken list or table. The one open item is a passive table cell, and it is optional. The findings are now at wording level and none touch correctness. Another review pass has no value.
