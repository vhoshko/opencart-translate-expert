# 22-ste-agent-instructions: independent review

Date: 10.11.2026. Reviewer model: Fable 5.2.

Scope: `git diff origin/main -- CLAUDE.md .claude` (14 files). The reviewer compared each file with its `origin/main` version. The reviewer did not measure sentence length, code blocks or frontmatter again (established facts in the brief).

Limit of this review: no PDF tool is available in this environment. The reviewer did not compare the house rule with the text of the standard word for word. See the note in Strengths.

### Strengths

- The rewrite keeps the instructions. The reviewer found no lost gate, no lost "never" rule, no lost number and no lost example in the 14 files.
- The literal strings that other files match on are consistent:
  - "checks only" and "no build" (`dev-workflow` line 128, `ote-build-test.md` line 25)
  - `Findings:`, `## Update <date>` and `> **Superseded.**` (`dev-workflow` lines 181–183, 203, 259)
  - `[ ] Re-review?` and the exact question "Fixes applied and build is green. Want another independent review pass?" (`dev-workflow` lines 166, 278, 279)
  - The status words DONE, DONE_WITH_CONCERNS, BLOCKED and NEEDS_CONTEXT
  - "Spec compliant" and "Issues found"
  - The file-name patterns `<slug>_plan.md`, `<slug>-review.md`, `-review-v2.md` and `<slug>-improvements.md`
- The frontmatter `description` values keep their trigger phrases. The changes are verb forms only ("Use when running" became "Use when you run"). The `ote-spec-reviewer` description is unchanged.
- The house rule in `CLAUDE.md` (lines 11–50) covers the three scope items of the issue and the out-of-scope items. It cites the source and rule numbers. It has no reference to the local `tmp/` checklist. Its bullets are short summaries, and their wording differs from the paraphrased checklist.
- Each of the 5 agent files has the pointer line. The plan template, Phase 4, Phase 7 and `issue.md` have a pointer too.
- Long sentences became vertical lists where the content is a list (`ote-build-test.md` Step 1, `issue.md` section 1, the BLOCKED cases in `subagent-driven-development`). This makes the instructions easier to follow.
- The ambiguous original text in `dev-workflow` about `Skip` cases and friction "seen twice" is now explicit.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

**1. `CLAUDE.md:66` — the build rule is narrower than the original.**

- Original: "Always run `./build.release.sh` after making changes to verify the build succeeds."
- New: "After you change the code, always run `./build.release.sh`."

"Changes" became "the code". The rule no longer applies to templates, language files, the OCMOD XML or build scripts in a strict reading. The rule now also disagrees with `.claude/project-workflows.md:48`, which says "Run it after every change". The checks-only decision for this task is a decision for this task only. It is not a decision to change the rule.

Fix: write "After you make changes, always run `./build.release.sh`." If the user wants a Markdown exception, add it as a separate, explicit sentence in both files.

**2. `.claude/skills/bump-version/SKILL.md:55` — a description became a command to commit.**

- Original: "Do not commit, branch or build as part of this skill. The version bump is committed together with the change it ships."
- New: "Do not commit, branch or build in this skill. Commit the version bump together with the change that it ships."

The original second sentence gives information. The new sentence is an imperative. It follows "Do not commit" directly, so the two sentences conflict. `dev-workflow` and `ote-build-test` use this skill, and `dev-workflow` never commits.

Fix: "The version bump goes into the same commit as the change that it ships."

**3. `.claude/skills/dev-workflow/SKILL.md:220-229` — the copy step comes after the step that needs it.**

The new numbered list starts with "1. Disable the fix (not the test)." The instruction "Before you disable the fix, copy the files byte-for-byte" is two paragraphs later (line 229). An agent that does the steps in sequence disables the fix before it reads the copy instruction. The original text had the same information in one paragraph. The new structure makes the sequence error more probable. A lost fix is the risk.

Fix: put the full sequence in the numbered list:

1. Copy the changed files byte-for-byte.
2. Disable the fix (not the test).
3. Run the suite again.
4. Confirm that the new test is among the failures, and that it fails on the assertion you expect.
5. Restore the fix from the copies. Do not retype the code.
6. Verify the restore with `git diff --stat` and a grep for whatever you used to disable the fix.

#### Minor (nice to have)

**4. "-ing" forms used as verbs (rule 3.5).** Clear cases:

- `.claude/agents/ote-implementer.md:15` — "Asking before you start costs much less than asking after." Fix: "A question before you start costs much less than a question after."
- `.claude/agents/ote-implementer.md:25` — "Asking always costs less than a wrong assumption". Fix: "A question always costs less than a wrong assumption".
- `.claude/skills/dev-workflow/SKILL.md:198` — "Consider dispatching `ote-implementer` with these items". Fix: "You can dispatch `ote-implementer`. Give it these items:".
- `.claude/skills/dev-workflow/SKILL.md:249` — "without asking". Fix: "without a question to the user".
- `.claude/skills/dev-workflow/SKILL.md:291` — "stop finding things that matter". Fix: "Stop when the passes find nothing important."
- `.claude/agents/ote-spec-reviewer.md:39` — "Verify by reading code, not by reading the report." The rewrite did not change this line. Fix: "To verify, read the code. Do not rely on the report."
- `.claude/agents/ote-implementer.md:55` — heading "Self-review before reporting". The rewrite did not change it. Fix: "Self-review before the report".

**5. Spelling is not consistent (rules 1.14 and 1.11).** The rewrite changed two words to American spelling and left the others:

- American: "behavior" (`.claude/commands/issue.md:45`, `.claude/project-workflows.md:54`), "afterward" (`.claude/agents/ote-build-test.md:89`)
- British: "behaviour" (`ote-code-quality-reviewer.md:46`, `ote-code-reviewer.md:48` and `:66`, `ote-implementer.md:34` and `:61`, `dev-workflow` lines 116, 124, 191, 218), "catalogues" (`ote-code-quality-reviewer.md:44`), "judgement" (`dev-workflow:327`), "afterwards" (`dev-workflow:340`), "licence" (`CLAUDE.md:19`)

Fix: use one spelling in all 14 files. Rule 1.14 gives American spelling as the default.

**6. `.claude/skills/bump-version/SKILL.md:8` — a statement became a prohibition.**

- Original: "Nothing else needs editing."
- New: "Do not edit anything else."

The original says that no other file holds the version. The new text forbids other edits. Fix: "No other file needs a change."

**7. `.claude/commands/issue.md:61` — "reproduce" became "cause".**

- Original: "`### Verification` (the steps that reproduce it)"
- New: "`### Verification` (the steps that cause the problem)"

Steps that cause a problem are a root cause. The section is for steps that show the problem again. Fix: "(the steps that show the problem again)".

**8. `.claude/agents/ote-build-test.md:90` — the condition is lost.**

- Original: "If there are leftover … files … or … folders, remove them and say that you did."
- New: "Remove these leftover items and say that you removed them:"

If the root is clean, the new sentence still tells the agent to say that it removed items. Fix: "If one of these leftover items is in the root, remove it. Say that you removed it:".

**9. `.claude/agents/ote-code-quality-reviewer.md:33` — the word "fixed" is lost.**

- Original: "its entry in `build.sh`'s fixed 3.0/4.0 lists"
- New: "an entry in the `build.sh` 3.0/4.0 lists"

All other files keep "fixed lists" for this concept. Fix: "the fixed 3.0/4.0 lists in `build.sh`".

**10. "the team" is a new actor.** The rewrite uses "the team" to remove the passive voice in four places: `ote-code-reviewer.md:18`, `ote-code-quality-reviewer.md:10`, `ote-spec-reviewer.md:12` and `:35`. There is no team in this workflow. In `ote-spec-reviewer.md`, the task or the user makes the request, and the implementer builds. Fix: "what the task requested", "what the implementer built", "Is everything that the task requires present?".

**11. `.claude/skills/requesting-code-review/SKILL.md:34` — "pins Opus" became "uses Opus".** `dev-workflow` and `subagent-driven-development` use "pins" for this fact (one term for one item, rule 1.11). "Pins" also tells the reader that an override is possible. Fix: keep "pins Opus".

**12. `.claude/skills/dev-workflow/SKILL.md:44` — a default description became an unconditional command.**

- Original: "Default: the current session model. The plan is written inline in the main conversation, with no subagent."
- New: "Default: the current session model. Write the plan inline in the main conversation, with no subagent."

Line 52 tells the agent to dispatch a `Plan` subagent when the user chooses a different model. Fix: "With this default, you write the plan inline in the main conversation, with no subagent."

**13. `.claude/skills/dev-workflow/SKILL.md:240` — the dispatch brief repeats a standing instruction.** The new bullet tells the agent to put the STE line in the Phase 4 brief. `ote-code-reviewer.md:8` already has this line. Line 289 of the same skill says "Do not restate the rest", and `subagent-driven-development` lists repeated standing instructions as a red flag. The plan asked for pointers in the agent files and next to the templates only. Fix: remove the bullet, or keep it and accept the repeat. The behavior is the same in both cases.

**14. House rule details, `CLAUDE.md`.**

- Line 28: the citation "(8.1, 4.2)" does not cover Latin abbreviations. That item is general recommendation GR-6. Fix: "(8.1, 4.2, GR-6)".
- Line 33: "Technical nouns and technical verbs are allowed." This is passive (rule 3.6). Fix: "You can use technical nouns and technical verbs."
- Line 17: "Changes to `CLAUDE.md` and `.claude/**`" is not in the issue scope list. It is a sensible addition, because it keeps the 14 files in STE. No change is necessary. The user can confirm it.

**15. Passive voice where the agent is known (rule 3.6).** Clear cases that the rewrite left or added:

- `ote-code-quality-reviewer.md:10` — "You are dispatched only after spec compliance passes." Fix: "The workflow dispatches you only after spec compliance passes."
- `ote-build-test.md:77` — "That version was built before." Fix: "A build used that version before." (`bump-version/SKILL.md:51` already has this form.)
- `dev-workflow/SKILL.md:14` and `subagent-driven-development/SKILL.md:77` — "are defined in `.claude/agents/`". Fix: "The definitions of the agents are in `.claude/agents/`."
- `dev-workflow/SKILL.md:283` — "the specific concerns that were already checked". Fix: "that earlier passes already checked".
- `subagent-driven-development/SKILL.md:101` — "before the spec compliance review is approved". Fix: "before the spec compliance review passes".
- `dev-workflow/SKILL.md:326` and `:328` — table cells "no version is used up" and "was chosen". The rewrite did not change the dispatch summary table (lines 323–334).

### Assessment

**Ready to merge?** With fixes.

The rewrite keeps the meaning of the 14 files well, and the cross-file literal strings and trigger phrases are intact. Fix findings 1 to 3 before the merge, because each one changes what an agent does (the build gate, a commit command, and the sequence of a destructive step). Findings 4 to 15 are STE polish and small wording drift. They are cheap, but none of them blocks the merge.
