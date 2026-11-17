# 87-do-workflow-models

## Settings
- Planning model: Opus. Implementation model: Sonnet (the session model). Review model: Opus (`ote-code-reviewer` pin). The user set these models for this task.
- Review findings: fix every finding. Skip a finding only with a correct and verified reason. Write each skip, its reason and its evidence in the Findings of the plan and in the report to the user.
- Docs-only task (`.claude/**` and `docs/**` only). Run `ote-build-test` in checks-only mode before and after the change. Do not bump `_version.txt`. Do not build.
- No commit, no push and no PR. Do these only if the user asks later.
- Branch: `docs/87-do-workflow-models`.

## Context
Issue #87: https://github.com/vhoshko/opencart-translate-expert/issues/87.

The `/do` workflow does not set the split of work between models. The user wants this split:
- Opus writes the plan.
- Sonnet does the implementation.
- Opus does the independent review.

The issue also changes the rule for review findings. The new default is to fix every finding. A skip needs a correct and verified reason.

The change touches no file under `sources/`. All OC versions stay unchanged.

Files to change (line numbers verified on 17.11.2026 with a file read):
- `.claude/skills/dev-workflow/SKILL.md` (436 lines):
  - Overview, stripped-down flow: lines 20 and 23.
  - Phase 0 "Model selection": lines 59-78.
  - Phase 1 intro: line 183. Settings template: lines 190-193. Steps template: line 213.
  - Phase 1 auto-approve checkpoint: line 226.
  - Phase 2 implement: lines 249-251.
  - Phase 4 dispatch: line 309. Phase 4 step 3 and step 4: lines 327-337.
  - Phase 5 intro: line 343. Phase 5 apply fixes: lines 346-347. Minor findings rule: line 376.
  - Dispatch table: lines 419, 420 and 423.
- `.claude/commands/do.md` (9 lines): line 9.
- `.claude/skills/subagent-driven-development/SKILL.md`: line 44 (flow diagram) and line 108.
- `.claude/skills/requesting-code-review/SKILL.md`: line 43.

Places checked with no change needed:
- `.claude/agents/ote-implementer.md:4` pins `model: sonnet`. `.claude/agents/ote-code-reviewer.md:4` pins `model: opus`. The pins already match the new defaults.
- `.claude/agents/*.md` contain no "auto-approve" or "which to fix" text (grep result is empty).
- `.claude/project-workflows.md` contains no model default, no auto-approve rule and no fix-or-skip rule (grep result is empty).
- `CLAUDE.md` contains no model default and no fix-or-skip rule.
- `SKILL.md:363` (re-review model) stays. The user settled this in the planning brief.
- `subagent-driven-development/SKILL.md:47-58` (model table) already says Sonnet for `ote-implementer` and Opus for `ote-code-reviewer`.

Greps used:
```bash
grep -rniE "auto-approve|picks which|which to fix|fix or skip|fix-or-skip|planning model|review model|session model|ask the user for each|for each finding|opus|sonnet" .claude CLAUDE.md
grep -nE "auto-approve|model|inline|main context|fix or skip|fix-or-skip|picks which|ask the user|Ask the user" .claude/skills/dev-workflow/SKILL.md
```

Out of scope: the user auto-memory file `end-to-end-with-pr.md` is outside the repository. Do not change it.

## Approach
Set fixed model defaults in Phase 0. Phase 0 does not ask the user for models any more. It states the defaults, and the user can change them in the conversation.

The plan uses Opus. If the session model is not Opus, a `general-purpose` subagent with `model: opus` writes the plan file. If the session model is Opus, plan inline. The implementation always uses Sonnet. If the session model is Sonnet, implement in the main context. If not, dispatch `ote-implementer`, also for small changes and for Phase 5 fixes.

The review findings rule changes to "fix by default". A skip needs a verified reason. "Standing auto-approve" stays only for the plan checkpoint and the other user questions of an unattended run. It no longer controls the review findings.

## Assumptions
- ✅ verified: `ote-implementer` pins Sonnet and `ote-code-reviewer` pins Opus. Evidence: `.claude/agents/ote-implementer.md:4`, `.claude/agents/ote-code-reviewer.md:4`.
- ✅ verified: `subagent_type: Plan` has no Write tool. Evidence: the agent type list of the harness ("All tools except ... Edit, Write"), and `SKILL.md:77` (task 59).
- ❓ unverified: the main context can know its session model. The system prompt of the harness names the model, but no file in the repo proves it. If this is false, the workflow cannot pick inline or subagent. Mitigation: the new text tells the agent to ask the user if it does not know the session model.

## Steps

Do each edit with the Edit tool. Write all new text in ASD-STE100. Keep task references (task 59, task 77) where the text stays.

- [x] 1. Baseline: dispatch `ote-build-test` in checks-only mode. Record the test count.

- [x] 2. `SKILL.md` Overview, stripped-down flow:
  - Line 20: replace "Ask for the planning and review models (Phase 0)." with "State the default models (Phase 0)."
  - Line 23: replace "Implement the change in the main context (Phase 2)." with "Implement the change with Sonnet (Phase 2)."

- [x] 3. `SKILL.md` Phase 0 "Model selection", lines 59-78: replace the whole subsection with this content:
  - Intro: "Use these default models. Do not ask the user for models. State the defaults in chat when the task starts. The user can change a model in the conversation."
  - Item 1: "**Plan (Phase 1): Opus.**"
  - Item 2: "**Implementation (Phase 2 and Phase 5 fixes): Sonnet.**"
  - Item 3: "**Independent review (Phase 4 and each re-review in Phase 5): Opus.** `ote-code-reviewer` pins Opus."
  - Keep the rule: "If an earlier session recorded other models in the `## Settings` block of the plan, use those models."
  - Keep the rule: write the models into `## Settings` when Phase 1 creates the file. They apply across sessions unless the user changes them.
  - Add: "If you do not know the session model, ask the user."
  - "**To apply the models:**" bullets:
    - Planning: "If the session model is Opus, write the plan inline. If not, dispatch `Agent` (`subagent_type: general-purpose`, `model: opus`)." Keep the rest of line 77 unchanged from "Tell it to write the plan file" to "(task 77)".
    - Implementation: "If the session model is Sonnet, implement in the main context. If not, dispatch `ote-implementer`, which pins Sonnet. Do this also for a small change."
    - Independent review: "Dispatch `ote-code-reviewer` with no `model:`. Pass `model:` only if the user chose a model other than Opus."
  - Remove: old lines 65-72 (the question for two models, the "differs from the planning model" default and the task 71 confirmation).

- [x] 4. `SKILL.md` Phase 1:
  - Line 183: replace with "Use the planning model from Phase 0. If the session model is Opus, plan inline in the main context. If not, use a `general-purpose` subagent with `model: opus` that writes the plan file (see Phase 0). Write the plan to `docs/tasks/<slug>/<slug>_plan.md`."
  - Lines 190-193 (Settings template): replace the placeholder with "<planning model · implementation model · review model · review findings rule (fix all, skip only with a verified reason) · build, tests, commit, push, PR, as agreed with the user · standing auto-approve for an unattended run, if the user granted it. Record this here. A later session that picks up this task then does not have to ask again.>"
  - Line 213: replace "(the user picks which to fix or skip)" with "(fix all. Skip only with a verified reason, and write it in Findings)".
  - Line 226: keep the rule. Change "standing auto-approve" to "standing auto-approve for an unattended run" so that the term does not point to the review findings.

- [x] 5. `SKILL.md` Phase 2, lines 249-251: replace the "small changes" and "larger changes" paragraphs with:
  - "Implement with Sonnet (see Phase 0)."
  - "If the session model is Sonnet and the change is small (1–3 files, under 50 lines), implement directly in the main context."
  - "If the session model is not Sonnet, dispatch `ote-implementer` for each change, also a small change."
  - "For a larger change, dispatch `ote-implementer`. Give it these items:" (keep the 3 items at lines 252-254).
  - Keep lines 256-264 unchanged.

- [x] 6. `SKILL.md` Phase 4:
  - Line 309: replace the model sentences with "The agent pins Opus, which is the default review model. Pass `model:` only if the user chose another review model."
  - Lines 327-336 (step 3): replace with this rule set:
    - "3. Decide each finding. **Default: fix every finding.** Do not ask the user for each finding."
    - "Skip a finding only with a correct and verified reason. Verified means a command output, a file reference or an issue number that the user can check."
    - Valid reasons, as a vertical list:
      - "The fix belongs in another issue. Create that issue with `gh issue create` first. Write its number in the plan."
      - "The finding is factually wrong. Give the evidence."
      - "The fix has a real risk for an external system."
      - "The fix is a design trade-off that only the user can pick. Ask the user."
      - "Another reason that you document with evidence."
    - "\"It needs another verify cycle\" is not a reason. A verify cycle runs after Phase 5 in all cases."
    - "Write each skip, its reason and its evidence in the `Findings:` note of the plan and in the report to the user."
    - "If a finding changes the agreed approach or expands scope, go back to the user before you fix it."
    - "The user can change any fix or skip decision in the conversation."
  - Line 337: keep "Proceed to Phase 5 with those decisions." Add before it: "Report the decisions to the user. Do not wait for an answer, unless a finding goes back to the user."

- [x] 7. `SKILL.md` Phase 5:
  - Line 343: replace "from the user or from you under auto-approve" with "(see Phase 4 step 3)".
  - Line 346: replace with "For small fixes (1–3 findings, under 20 lines): if the session model is Sonnet, apply them in the main context. If not, dispatch `ote-implementer`."
  - Line 347: keep.
  - Line 376: replace "do not ask a fix-or-skip question for each one" with "do not fix them by default. The reviewer mark \"no action necessary\" is the documented reason". Keep the rest of the paragraph.

- [x] 8. `SKILL.md` dispatch table:
  - Line 419 (1 — Plan): Subagent? "If the session model is not Opus". Why: "Opus writes the plan. If the session model is Opus, write the plan inline".
  - Line 420 (2 — Implement): Subagent? "If the session model is not Sonnet, or for a large change". Why: "Sonnet implements. `ote-implementer` pins Sonnet".
  - Line 423 (5 — Apply fixes): Subagent? "If the session model is not Sonnet, or for a large fix set". Why: "Same rule as Phase 2".

- [x] 9. `.claude/commands/do.md`, line 9: add one sentence after the first sentence: "The skill uses Opus for the plan and the review, and Sonnet for the implementation. It fixes all review findings by default."

- [x] 10. `.claude/skills/subagent-driven-development/SKILL.md`:
  - Line 44: replace "(ask per finding)" with "(fix all by default)".
  - Line 108: replace with "**If the final `ote-code-reviewer` finds issues:** Use the fix-by-default rule of `dev-workflow` Phase 4. Skip a finding only with a verified reason."

- [x] 11. `.claude/skills/requesting-code-review/SKILL.md`, line 43: replace the 2 sentences after "Use the same adjudication rule as `dev-workflow` Phase 4." with "Fix every finding by default. Skip a finding only with a verified reason, and report it." Keep the 4 bullets below. In the bullet "Note Minor issues for later", change the text to "Fix Minor issues too, unless a verified reason applies".

- [x] 12. Verify stale wording. Run these greps. Each result must be empty, or show only the new text:
  ```bash
  grep -rnE "picks which|which to fix|ask per finding|Ask the user about each finding|ask the user for each finding|differs from the planning model|Ask for the planning and review models|non-default planning model|under auto-approve|auto-approve for review findings" .claude
  grep -rnE "auto-approve" .claude
  grep -rnE "main context" .claude/skills/dev-workflow/SKILL.md
  ```
  Check each `auto-approve` hit: it must refer only to the plan checkpoint or an unattended run. Check each "main context" hit for implementation: it must have the Sonnet condition.

- [x] 13. Read the full `git diff` of each changed file. Check that no line was lost by mistake (task 68). Check the ASD-STE100 rules on the new text: sentence length, no semicolons, no contractions.

- [x] 14. Dispatch `ote-build-test` in checks-only mode. Compare the test count with step 1.

- [x] 15. Independent review → `87-do-workflow-models-review.md`. Tell the reviewer to read the full diff of each `.claude/**` file.

- [x] 16. Address review findings (fix all. Skip only with a verified reason, and write it in Findings). If the fixes change only Markdown, skip the verify loop (docs-only flow).

- [x] 17. Re-review? (unreviewed fixes exist. Ask the user. If yes, repeat from step 15 → `-review-v2.md`, v3, …)

- [x] 18. Improvements pass → `87-do-workflow-models-improvements.md`.

## Done
The `/do` workflow now uses Opus for the plan, Sonnet for the implementation and Opus for the review. It fixes every review finding unless a verified reason exists. Four `.claude/**` files changed. Two reviews ran (10 and 5 findings, 1 skip with a reason). Unit tests pass (16 scripts). No version bump, no build, no commit. Open: the re-review gate for an unattended run (see the improvements file).

## Findings (review 1, 17.11.2026)
Review: `87-do-workflow-models-review.md`. Result: 0 Critical, 2 Important, 8 Minor. All 10 findings fixed. No skip.
- Important 1: the rules named Opus and Sonnet in the text. Fixed: the rules use "planning model", "implementation model" and "review model". Opus and Sonnet are the defaults only.
- Important 2: the external-system rule was lost. Fixed in Phase 4 step 3: a finding that touches an external system goes back to the user.
- Minor 3: the Phase 0 implementation bullet now points to the size rule of Phase 2.
- Minor 4: Phase 4 step 3 has a new skip reason, "no action necessary". Phase 5 refers to it.
- Minor 5: an unknown session model leads to a dispatch. No question to the user.
- Minor 6: the verify-cycle sentence now reads "The verify loop of Phase 5 covers each fix".
- Minor 7: the text tells the agent to search for an existing issue first.
- Minor 8: the flow diagram of `subagent-driven-development` has a fix and verify step.
- Minor 9: "unattended run" has a definition in the Settings template. The re-review gate stays as before: the text is old and this task does not change it (see `git show origin/main:.claude/skills/dev-workflow/SKILL.md`). The user decides if a later issue is needed.
- Minor 10: the plan has ticked boxes and these Findings. The wrong reference is fixed.

## Findings (review 2, 17.11.2026)
Review: `87-do-workflow-models-review-v2.md`. Result: 0 Critical, 0 Important, 5 Minor. The reviewer recommends a stop.
- Minor 1, 2, 4 and 5: fixed (external-system skip asks the user, `gh issue create` is not such a case, no upward override for `ote-implementer`, "By default" in `do.md`, both dispatches named).
- Minor 3: skipped. Reason: the reviewer marks it "no action necessary". Evidence: review v2, finding 3. The docs-only flow (SKILL.md line 32) already names the Markdown-only exception.
