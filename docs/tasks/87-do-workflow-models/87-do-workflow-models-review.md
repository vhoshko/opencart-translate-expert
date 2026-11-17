# 87-do-workflow-models — review

Scope: `git diff HEAD` on branch `docs/87-do-workflow-models`. The diff changes 4 files:

- `.claude/commands/do.md`
- `.claude/skills/dev-workflow/SKILL.md`
- `.claude/skills/requesting-code-review/SKILL.md`
- `.claude/skills/subagent-driven-development/SKILL.md`

Requirements: issue #87 and `87-do-workflow-models_plan.md`. I read the full diff of each file and the full new `dev-workflow/SKILL.md`. Line numbers refer to the working tree.

### Strengths

- The diff applies each of the plan steps 2–11, and the text is close to the plan wording. No unrelated line was removed. Each removed line has a replacement or an intentional removal (the model question, the task 71 confirmation, the "ask per finding" default).
- The stale-wording greps of plan step 12 are clean. No "picks which", "which to fix", "ask per finding", "differs from the planning model" or "under auto-approve" text remains in `.claude/`. The only `auto-approve` hits are `SKILL.md:189` and `SKILL.md:223`, and both say "for an unattended run".
- The skip rule in Phase 4 step 3 (`SKILL.md:328-340`) is a clear vertical list. It defines "verified" (a command output, a file reference or an issue number). It keeps the useful old line that "It needs another verify cycle" is not a reason.
- The dispatch table (`SKILL.md:422-426`) agrees with Phase 1, Phase 2 and Phase 5. Phase 4 and the re-review gate (`SKILL.md:310`, `SKILL.md:366`) agree with the `ote-code-reviewer` Opus pin and with the overview rule at `SKILL.md:37`.
- The new text has no semicolons, no contractions and no Latin abbreviations. The new sentences are short.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. **`SKILL.md:74-75`, `SKILL.md:181`, `SKILL.md:246-250`, `SKILL.md:349`, `SKILL.md:422-426` — the apply rules hard-code Opus and Sonnet. The user override and the recorded Settings models then have no effect.**
   `SKILL.md:61` says "The user can change a model in the conversation". `SKILL.md:67` says "If an earlier session recorded other models in the `## Settings` block of the plan, use those models". But the rules that apply the models compare the session model with the literal names Opus and Sonnet, and dispatch with `model: opus`. Examples:
   - An older plan records "Planning model: Sonnet". `SKILL.md:74` still dispatches `model: opus`.
   - The user asks for Opus for the implementation in an Opus session. `SKILL.md:75` and `SKILL.md:250` still dispatch `ote-implementer` (Sonnet).

   Only the review rule (`SKILL.md:76`, `SKILL.md:310`) is written against "the model that the user chose". This breaks item 6 of issue #87 ("Keep the existing user escape hatch").
   Fix: write the rules against "the planning model" and "the implementation model" from Phase 0, with Opus and Sonnet as the defaults. For example: "If the session model is the planning model, write the plan inline. If not, dispatch `general-purpose` with `model: <planning model>`." For `ote-implementer`, pass `model:` only if the implementation model is not Sonnet. Use the same pattern in Phase 1, Phase 2, Phase 5 and the table.

2. **`SKILL.md:338` — a safety rule was lost. The old text said: "Always go back to the user for anything that changes the agreed approach, touches an external system or expands scope."**
   The new line keeps only "changes the agreed approach or expands scope". "Touches an external system" is now only a skip reason (`SKILL.md:333`). Under the new fix-by-default rule, the agent fixes a finding that touches an external system (for example the GitHub remote, a store, or a live Google API run) with no question to the user. The old rule asked the user in this case, also under standing auto-approve.
   Fix: add the external system to `SKILL.md:338`, for example: "If a fix changes the agreed approach, touches an external system or expands scope, go back to the user before you fix it." If the `gh issue create` step at `SKILL.md:331` is an intended exception, say so.

#### Minor (nice to have)

3. **`SKILL.md:75` against `SKILL.md:248-252` — two different rules for a Sonnet session.** `SKILL.md:75` says "If the session model is Sonnet, implement in the main context", with no size limit. Phase 2 and the table say that a larger change goes to `ote-implementer`. The old Phase 2 text said "You can dispatch" for a larger change. The new text says "dispatch", which is a silent change from optional to required.
   Fix: in `SKILL.md:75`, add "for a small change (see Phase 2)". Decide if "dispatch" for a larger change is intended. If it is, record it in the plan.

4. **`SKILL.md:379` against `SKILL.md:328` — the "no action necessary" exception applies only to a re-review.** A first review (Phase 4) can also mark a Minor finding "no action necessary". Step 3 then says "fix every finding", and the list of valid reasons does not name the reviewer mark. Two agents can decide the same finding differently.
   Fix: add the reviewer mark "no action necessary" to the list of valid reasons in step 3. Then make `SKILL.md:379` point to that list.

5. **`SKILL.md:71` — "If you do not know the session model, ask the user."** In an unattended run, this question stops the run. A dispatch is correct for each session model: a `general-purpose` subagent with `model: opus` for the plan and `ote-implementer` for the implementation. A question is then not necessary.
   Fix: "If you do not know the session model, dispatch the planning and implementation subagents."

6. **`SKILL.md:336` — "A verify cycle runs after Phase 5 in all cases."** This is not true for the docs-only flow. `SKILL.md:32` says to skip the verify loop if the review fixes change only Markdown. Also, the verify loop is part of Phase 5, not after it. The old text ("regardless") had the same problem, but the line was rewritten here.
   Fix: "Phase 5 runs a verify cycle after the fixes."

7. **`SKILL.md:331` — "Create that issue with `gh issue create` first."** The text does not tell the agent to search for an existing issue first. A second issue for the same topic is then possible. The task brief says "that issue must exist", which an existing issue also satisfies.
   Fix: "Search for an existing issue with `gh issue list --search`. If none exists, create one with `gh issue create`. Write its number in the plan."

8. **`.claude/skills/subagent-driven-development/SKILL.md:43-44` — the flow diagram has no fix step.** The diagram goes from "Present its findings … (fix all by default)" directly to "Stop". With the old "ask per finding" rule, a stop after the presentation was correct. With fix-by-default, the diagram must show the fixes, the verify loop and the re-review gate, or point to `dev-workflow` Phase 5. Line 108 says to fix, so the diagram and line 108 do not agree.
   Fix: change line 43 to "Fix the findings per dev-workflow Phase 4 and Phase 5 (fix all by default, verify loop, re-review question)".

9. **`SKILL.md:189` and `SKILL.md:223` — "unattended run" has no definition.** `SKILL.md` uses "unattended" elsewhere only for a cron job (`SKILL.md:157`). Older plans record "standing auto-approve" with no "for an unattended run" suffix. A strict reading of `SKILL.md:223` then does not match these plans. The user memory for an end-to-end run also expects the agent to decide the re-review gate itself, but `SKILL.md:362` says "Never skip it". This gap existed before this change, but the new term makes it more visible.
   Fix: add one sentence that defines the unattended run and the steps it covers (the plan checkpoint, and the re-review gate if the user agrees). Say that an older "standing auto-approve" entry has the same meaning.

10. **`87-do-workflow-models_plan.md` — the plan is not updated.** All step checkboxes are `[ ]`, and no step has a `Findings:` note. `SKILL.md:265` says to flip each checkbox when the step finishes. The Context also refers to "Settled decision 5", but the plan has no list of settled decisions.

### Assessment

**Ready to merge?** With fixes.

The fix-by-default rule and the model defaults are clear and consistent across the 4 files, and no stale text remains. But the apply rules hard-code Opus and Sonnet, so the user override and older Settings blocks do not work (issue item 6). Also, the external-system escalation was lost from the Phase 4 rule. Fix these two Important items before the merge.
