# 87-do-workflow-models — review v2

Scope: `git diff HEAD` on branch `docs/87-do-workflow-models`, after the fixes for review 1. I read the full diff of the 4 changed files:

- `.claude/commands/do.md`
- `.claude/skills/dev-workflow/SKILL.md`
- `.claude/skills/requesting-code-review/SKILL.md`
- `.claude/skills/subagent-driven-development/SKILL.md`

I also read the full new Overview, Phase 0, Phase 4, Phase 5 and the dispatch table of `dev-workflow/SKILL.md`, and the flow diagram and the model table of `subagent-driven-development/SKILL.md`. Line numbers refer to the working tree.

Settled items that I did not examine again: the three defaults, no model question in Phase 0, implementation by the implementation model, fix-by-default with verified skips, the re-review gate text, the docs-only flow and the agent pins.

### Strengths

- Review 1 Important 1 is fixed everywhere. The apply rules in Phase 0 (`SKILL.md:74-76`), Phase 1 (`SKILL.md:181`), Phase 2 (`SKILL.md:246-252`), Phase 4 (`SKILL.md:310`), Phase 5 (`SKILL.md:350`) and the dispatch table (`SKILL.md:423-427`) compare the session model with "the planning model" or "the implementation model". Opus and Sonnet appear only as defaults and as agent pins. A grep for `opus|sonnet` in `.claude/` finds no hard-coded model in a rule.
- Review 1 Important 2 is fixed. `SKILL.md:339` again sends a finding that touches an external system back to the user.
- The unknown-session-model rule (`SKILL.md:71`) gives a safe result. A dispatch is correct for each session model, and the rule asks no question.
- The new skip reason "no action necessary" (`SKILL.md:335`) and the Phase 5 rule (`SKILL.md:380`) now agree. The Phase 5 rule points to Phase 4 step 3.
- The issue search before `gh issue create` (`SKILL.md:331`) is clear and in the correct order.
- The flow diagram of `subagent-driven-development/SKILL.md:43-44` now shows the decision, the fix, the verify loop and the re-review question. It agrees with line 109 of the same file.
- No line was lost. Each removed line has a replacement or is an intended removal. No cross-reference is broken: "see Phase 2" (`SKILL.md:75`), "see Phase 0" (`SKILL.md:181`) and "see Phase 4 step 3" (`SKILL.md:347`) all point to text that exists.
- The new text has no semicolons, no contractions and no Latin abbreviations. All new prose sentences have 25 words or fewer. The only longer lines are table rows and the Settings placeholder at `SKILL.md:189`, which are not sentences.
- The plan has ticked boxes and a Findings note for each review 1 finding.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. **`SKILL.md:333` against `SKILL.md:339` — two rules for a fix that touches an external system.** Line 333 lets the agent skip such a finding on its own ("The fix has a real risk for an external system"). Line 339 tells the agent to go back to the user before the fix. Both rules stop an automatic fix, so no unsafe action follows. But two agents can handle the same finding differently: one skips and reports, the other asks. Also, the `gh issue create` step at `SKILL.md:331` writes to GitHub with no question. This is what issue #87 asks for, but the text does not say that it is an exception to line 339.
   Fix (optional): change line 333 to "The fix has a real risk for an external system. Ask the user (see below)." Add to line 331: "This step needs no question to the user."

2. **`.claude/skills/subagent-driven-development/SKILL.md:60` — "Override upward for a hard architectural task."** This old line is not in the diff. It applies to each agent in the table, `ote-implementer` included. An agent can then read it as an allowance to dispatch `ote-implementer` with `model: opus` on its own decision. This does not agree with item 3 of issue #87 and with the settled rule that the implementation model implements. Inside `dev-workflow`, the implementation model applies, so the effect is small.
   Fix (optional): add "For `ote-implementer`, use the implementation model of `dev-workflow` Phase 0. Override it only if the user asks."

3. **`SKILL.md:337` — "The verify loop of Phase 5 covers each fix."** In the docs-only flow, a Markdown-only fix skips the verify loop (`SKILL.md:32`). The sentence is then not exactly true. The rule that it supports ("It needs another verify cycle" is not a reason) stays correct, because a docs-only fix has no verify cost. No action necessary.

4. **`.claude/commands/do.md:9` — "The skill uses Opus for the plan and the review, and Sonnet for the implementation."** The next sentence says "by default" for the findings rule, but this sentence does not say it for the models. The user can change the models (`SKILL.md:61`). Fix (optional): "By default, the skill uses Opus …".

5. **`SKILL.md:71` — "Then dispatch the subagent."** The sentence uses the singular, but the rule applies to two dispatches: the planning subagent and `ote-implementer`. The meaning is clear from line 74 and line 75. Fix (optional): "Then dispatch the planning subagent and `ote-implementer`."

### Assessment

**Ready to merge?** Yes.

The fixes for review 1 are correct and introduce no regression, contradiction or broken cross-reference. The 5 Minor findings are wording only and none touch the behavior of the workflow. Another review pass would only polish phrasing.
