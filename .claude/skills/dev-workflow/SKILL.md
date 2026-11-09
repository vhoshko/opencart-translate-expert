---
name: dev-workflow
description: Use when running an end-to-end coding task. Orchestrates the setup, planning, implementation, build, test and review phases, and never commits.
---

# Dev Workflow

## Overview

Orchestrates a coding task end-to-end through 7 phases. Each phase must pass before the next begins. The workflow never commits, pushes or creates PRs. It stops after review and improvements. For trivial tasks (a single file, under 20 lines, no schema or build-script impact), propose a stripped-down flow and proceed only if the user agrees.

Project-specific config (repo, commands, source layout, conventions, gotchas) lives in `.claude/project-workflows.md`. The subagents this workflow dispatches are defined in `.claude/agents/`. Each pins its own model, so dispatch by `subagent_type` and pass `model:` only to override.

### Context budget principle

**Protect the main conversation context.** Most phases produce verbose output (file reads, build logs, test output, diffs) that the user doesn't need to see in full. Delegate these to subagents so the main context holds only summaries, user decisions and phase transitions. The main conversation is a control plane, and subagents are the data plane.

## Phase Flow

```
Setup → Plan → Implement → Build+Test → Independent review → Fixes+Verify → Done → Improvements
```

**Gates:** Each phase must be green before the next. If a phase is red, surface it and ask before proceeding.

**After any code changes (implementation or review fixes):** ask the user whether they want another iteration of independent review before proceeding.

## Phase 0 — Setup

Parse the input, then create the branch and the task folder.

### Model selection

Ask this first, before anything else, unless it was already answered earlier in this conversation or recorded in the plan's `## Settings` block by an earlier session on the same task:

1. **Model for planning (Phase 1).** Default: the current session model. The plan is written inline in the main conversation, with no subagent.
2. **Model for independent review (Phase 4, and any re-review passes in Phase 5).** Default: a different model from the one used for planning, so the review is a genuinely independent perspective rather than the same model checking its own work. `ote-code-reviewer` pins Opus, which serves as that default whenever planning used something else. If planning also used Opus, pick a different model for review and pass it explicitly.

Carry both choices through Phase 0, then write them into the plan's `## Settings` block when Phase 1 creates the file. They apply for the rest of the task, across sessions, unless the user changes them.

**Applying the choice:**
- **Planning:** if the chosen planning model is the current session model, write the plan inline as usual. If a different model was chosen, dispatch it via `Agent` (`subagent_type: Plan`, `model: <chosen>`) to produce the plan content, then bring it into the main conversation for the Phase 1 checkpoint below. Planning subagents don't talk to the user directly.
- **Independent review:** `ote-code-reviewer` pins Opus. Pass `model: <chosen>` on Phase 4 and Phase 5 re-review dispatches only when the chosen model differs from that pin.

### Input parsing

The input may contain any combination of:
- A task description (free text)
- A GitHub issue URL or `#<n>`
- A pasted issue body or chat snippet

If an issue reference is present, fetch it:
```bash
gh issue view <url-or-number> -R vhoshko/opencart-translate-expert
```

### Restate the task

Summarize in 2–4 sentences what's being asked, why, and which OpenCart versions it affects (all of them, ≤ 3.0 only, or 4.0 only). **If anything is ambiguous, ask before going further.**

### Choose a slug

- With an issue: `<issue-number>-<kebab-title>` (e.g. `4-cron-translation`)
- Without one: `<kebab-slug>`

Show the slug and let the user override it.

### Create the branch and task folder

```bash
git fetch origin main
git checkout -b <prefix>/<slug> origin/main
mkdir -p docs/tasks/<slug>/tmp
```

If the working tree has uncommitted changes that are unrelated to this task, **stop and ask** before creating the branch. Untracked files carry over a `checkout -b` anyway, so stash only if the user agrees, and include untracked files:
```bash
git stash push -u -m "<slug>: <one-line description>" -- <paths>
```
Note the stash in the plan's Done block. Never use `git checkout --` or `git restore` as a shortcut.

### Investigation subagent

**Dispatch an Explore agent** (`subagent_type=Explore`) to map the problem across `sources/`. The root cause may span the controller, the model, the core library, the JS and the templates, so investigate freely.

Give the agent:
- The task description or issue body
- The source layout table from `project-workflows.md`
- An instruction to return the root cause analysis, key files as `path:line`, the data flow (JS → AJAX action → controller → core library → model / Google API), which OC versions the touched code runs on, and any design corrections it spots

The main context receives only the agent's summary, not hundreds of lines of source code. Use this summary to write the plan.

**Verify the agent's facts before building on them.** Its *conclusions* are input you weigh. Its *factual claims about the codebase*, such as "only X calls Y" or "this setting is never read", get checked directly with a grep or a file read before any design decision rests on one. One grep is cheaper than a design built on a wrong premise. When two agents disagree on a fact, neither is authoritative, so go and look.

**Skip the investigation subagent** if the task is trivially locatable (e.g. "fix the typo in file X line Y"). Just read the relevant file directly.

### Verify external assumptions (before planning, not after)

If the task touches a system you do not control, such as the Google Cloud Translation API or OpenCart core behaviour on a version you cannot run, ask before writing the plan:

> What does this plan assume that system does, and what is the cheapest way to find out?

Then actually find out, with a throwaway probe, a single live call or the OpenCart source for that version. Write the answer down. It is evidence, and it outlives the session. A live Google API call needs a key that the user supplies. Never go looking for one.

Record each load-bearing assumption in the plan's `## Assumptions` section, marked verified or not, with the evidence or the reason it could not be checked.

**Skip** when the task touches nothing external, or when the behaviour is already established by code that demonstrably works in production. In that case, cite that code as the evidence.

### Sanity check (subagent, background)

Dispatch `ote-build-test` (`subagent_type: ote-build-test`) in the background, with **"checks only"** in the prompt and the list of files the task will touch, **before you change anything**. In checks-only mode it runs `php -l` and the unit tests, and it does not bump `_version.txt` or build, because every build uses up a version number. It reports only pass or fail. If the check fails on code you didn't write, stop and surface it.

## Phase 1 — Plan

Use the planning model chosen in Phase 0: inline in the main context if it matches the session model, otherwise via a `Plan` subagent (see Phase 0). Write `docs/tasks/<slug>/<slug>_plan.md`:

```markdown
# <slug>

## Settings
<planning model · review model · build, tests, commit, push, PR, as agreed with the user,
 plus whether the user granted standing auto-approve for review findings.
 Recorded here so a later session picking this task up does not have to ask again.>

## Context
<task summary, issue link, why this matters, affected OC versions, key files as path:line from the investigation agent>

## Approach
<one short paragraph; call out design corrections explicitly>

## Assumptions
<one line per load-bearing assumption, each marked:
 - ✅ verified: how, with the evidence (probe output, a run, code that proves it)
 - ❓ unverified: why not, and what breaks if it turns out false
 Drop the section only if the task genuinely assumes nothing outside the code being changed.>

## Steps
- [ ] Code: <task-specific steps>
- [ ] Templates: .tpl and .oc4.twig (drop if no UI change)
- [ ] Language strings: en-gb, ru-ru, uk-ua (drop if no new strings)
- [ ] Bump version + build + test
- [ ] Independent review → <slug>-review.md
- [ ] Address review findings (user picks which to fix or skip)
- [ ] Bump version + build + tests (verify loop)
- [ ] Re-review? (unreviewed fixes exist; ask the user, and if yes repeat from the review step → -review-v2.md, v3, …)
- [ ] Improvements pass → <slug>-improvements.md

## Done
<filled at the end>
```

Adapt the template and drop steps that don't apply. If you skip a standard step, explain why and confirm with the user.

**Plan review checkpoint:** paste Context, Approach, Assumptions and Steps in chat and ask the user to approve. Do not start Phase 2 until they acknowledge it.

**Do not ask for approval of a plan whose load-bearing assumptions are still unverified.** Verify them first, or say plainly which are unverified and what falls over if one is wrong, so the user is approving a known risk rather than an unknown one. "Approved" does not make an assumption true.

**Plan updates during development:** the plan is an audit trail, so it has to be able to record being wrong.

- Use `~~strikethrough~~` for removed lines, and add `## Update <date>` sections. Don't silently rewrite.
- When evidence kills an approach, leave it in place under a `> **Superseded.**` banner saying what disproved it and where the replacement is. A plan that quietly deletes its dead branches teaches the next reader nothing and invites them to retry the same dead end.
- Add an inline `Findings:` note on each step as it completes, covering gotchas, decisions and deferrals. Writing them at the end means writing them from memory.

## Phase 2 — Implement

Follow the conventions in `project-workflows.md` and `CLAUDE.md`. Throwaway scripts go in `docs/tasks/<slug>/tmp/`.

The non-negotiables for this repo:
- Edit `sources/` only.
- Every file stays PHP 5.6 compatible. PHP 7+ syntax goes only between `// OC4_EVENT_START` and `// OC4_EVENT_END` **in the admin controller**, the one file whose marker blocks `build.sh` strips. Elsewhere, gate OC4-only behaviour at runtime with `version_compare(VERSION, '4.0', '>=')`.
- Write routes and class names in the OC 2.3 form. `build.sh` rewrites them generically for OC 1.5–2.2.
- A new AJAX method, library class or `client_translate_expert_*` setting key also needs its entry in `build.sh`'s fixed OC 3.0/4.0 rewrite lists (see `project-workflows.md`).
- UI changes go in both the `.tpl` (PHP template, OC 1.5–3.0) and the `.oc4.twig` (Twig, OC 4.0). New strings go in all three language files.

**For small changes (1–3 files, under 50 lines):** implement directly in the main context. The overhead of dispatching a subagent isn't worth it.

**For larger changes:** consider dispatching `ote-implementer` with:
- The approved plan
- The investigation summary
- An instruction to make the changes and report what was done

**Update the plan continuously.** The moment a step finishes, flip its checkbox to `[x]`, add an inline `Findings:` note, and capture gotchas, decisions and deferrals. The plan is a live audit trail.

## Phase 3 — Build + Test (subagent)

**Dispatch `ote-build-test`** (`subagent_type: ote-build-test`) to run the syntax check, the PHP-compatibility grep, the tests, the version bump and the release build together. This keeps all verbose output out of the main context.

Give the agent:
- The list of edited files
- A Google API key for integration tests, only if the user supplied one for this task
- Any route or class-name strings, AJAX methods, library classes or setting keys the diff added, so it can check them in the OC 1.5, 2.2, 3.0 and 4.0 packages

The agent owns the sequence, and its definition in `.claude/agents/ote-build-test.md` spells it out. It reports back pass or fail, error details, the test count, the version bump and the release path. The main context sees only that summary.

### Prove the test would have caught it (bug fixes only)

A green suite shows the code works **now**. It says nothing about whether the test you just wrote would have failed **before** the fix. A test that passes either way is not a regression test. It is a test of current behaviour dressed up as one.

So on a bug-fix task, once the suite is green, disable the fix (not the test). Re-run the suite and confirm that the new test is among the failures, **and that it fails on the assertion you expect**. The test script has no filter, so it always runs the whole suite. Run it inline (`php tests/test_vh_google_translator.php`) or through `ote-build-test` in **checks-only** mode, never a full build, which would use up a version number. Then restore the fix. Copy the files byte-for-byte first and restore from those copies rather than retyping, then verify the restore with `git diff --stat` and a grep for whatever you used to disable the fix before moving on.

This only applies where a test exists. Today that is `VHGoogleTranslator` (`tests/test_vh_google_translator.php`). For code without a test harness, say so in the plan's Findings rather than claiming regression coverage.

## Phase 4 — Independent review (subagent)

Dispatch `ote-code-reviewer` (`subagent_type: ote-code-reviewer`). The agent pins Opus; pass `model: <review model chosen in Phase 0>` only when Phase 0 chose something else. Give it:
- The task description
- The diff (`git diff origin/main...HEAD` plus untracked files, or `git diff HEAD` if nothing is committed)
- The full path to write the review to: `docs/tasks/<slug>/<slug>-review.md`
- Repo access: it inspects the repo and writes that one file, nothing else

It must NOT see your reasoning.

When the agent returns:
1. Read the review file.
2. Synthesize the findings for the user, with a **fix-cost estimate** per finding.
3. Decide each finding: **fix or skip**.
   - Default: ask the user per finding, and don't auto-apply anything.
   - Only when the user has said to decide review findings without asking (in this conversation, or recorded in the plan's `## Settings` block by an earlier session on the same task) do you decide them yourself, and report each decision with its reasoning. That, and nothing else, is the standing auto-approve rule. Anything that changes the agreed approach, touches an external system or expands scope still goes back to the user.
   - **Under auto-approve, lean toward fixing rather than skipping.** A finding that is technically correct and cheap to apply gets fixed. Skipping needs an actual reason: real cost disproportionate to the fix, genuine scope creep, an external-system risk, or a design trade-off only the user can pick. "It needs another verify cycle" is not that reason by itself, because a verify cycle runs after Phase 5 regardless, so a cheap local fix rides along for free.
4. Proceed to Phase 5 with those decisions.

**Scope expansion:** update the plan with `## Update <date>`. Dispatch a targeted second review that writes to `<slug>-review-v2.md`, and brief the agent to skip sections that are already approved.

## Phase 5 — Post-review fixes + verify loop

Once every review finding has been decided, by the user or by you under auto-approve:

**Apply fixes:**
- For small fixes (1–3 findings, under 20 lines), apply them directly in the main context.
- For larger fix sets, dispatch `ote-implementer` with the specific findings to fix (numbered, with the review text), the current code state, and an instruction to apply fixes only, without building or testing.

**Verify loop.** Always dispatch `ote-build-test` after fixes, with the same sequence as Phase 3. That includes a fresh version bump, because every build needs a new patch version.

Quote the pass or fail result, the test count and the new version in chat.

**If the build or tests fail:** surface the errors and ask the user how to proceed (fix and re-run the verify loop, or skip).

**Re-review gate: required after every fix pass.**
The fixes just applied were never seen by an independent reviewer. This is a hard checkpoint. Do not skip it, even if the changes look trivial.

1. Tick the `[ ] Re-review?` step in the plan.
2. Report the build/test result, then ask explicitly: **"Fixes applied and build is green. Want another independent review pass?"**
   - If **yes**, add `[ ] Independent review → <slug>-review-v2.md` to the plan and go back to Phase 4. Dispatch `ote-code-reviewer` again with the review model chosen in Phase 0, passing `model:` only if it differs from the agent's Opus pin, or ask whether the user wants to switch models for this pass. Increment the version suffix for each pass (v2, v3, …).
   - If **no**, proceed to Phase 6.

**Every re-review brief must carry an explicit already-settled list.** Name what earlier passes examined and approved (the design decisions, the mechanisms, the specific concerns already checked) and say plainly not to re-litigate them. Without that list, a fresh reviewer re-derives the whole design from scratch, spends its attention on ground already covered, and buries the new code in restated old findings. With it, the pass focuses on what actually changed.

Also give the reviewer, in the brief, the facts established so far, especially anything measured against an external system, as ground truth it should not question again.

Do not restate the rest. Scope exclusions, `git diff HEAD` for uncommitted work and "these are clean is a valid outcome" are standing instructions in `ote-code-reviewer.md`.

**Stop when the passes stop finding things that matter.** Track what each pass returns. When the findings drop to wording and none touch correctness, another pass is only polishing phrasing, so say so and recommend stopping rather than looping by reflex.

**A fix can introduce a regression.** That is the whole reason this gate exists: a fix written to close one finding has itself never been reviewed. Treat a fix pass exactly like the original change, not as a rubber stamp.

## Phase 6 — Mark Done

Update the plan: tick the checkboxes and fill in the **Done** block (3–5 lines covering what landed, the final version number, gotchas and deferred follow-ups).

**Do not mark Done until Phase 7 is complete.**

## Phase 7 — Improvements

Write `docs/tasks/<slug>/<slug>-improvements.md`:

1. Permission prompts hit. Suggest `.claude/settings.json` entries for recurring low-risk ones. Also flag entries worth **pruning**: the allowlist accumulates single-use rules pinned to one session's paths, which can never match again and bury the rules that matter.
2. Steps skipped or improvised
3. New steps that should exist
4. Friction points
5. Proposed edits to the workflow skills

**Friction seen twice gets acted on, not reported again.** Before writing the file, grep earlier improvements files for a keyword from the friction (`grep -rl "<keyword>" docs/tasks --include="*improvements*.md"`, quoted so zsh doesn't abort when none exist yet) rather than reading them. If it has come up before, it is either fixed in the skill or promoted to the **Known Environment Gotchas** table in `project-workflows.md`. There is no third option.

Surface a 3–5 bullet summary in chat. Don't edit skills without the user's go-ahead.

## Subagent dispatch summary

| Phase | Subagent? | Type | Why |
|-------|-----------|------|-----|
| 0 — Investigation | Yes (Explore) | Foreground | Keeps source reads out of the main context |
| 0 — Sanity check | Yes | Background · `ote-build-test` (checks only) | Test output is noise. No build, so no version is used up |
| 0 — Verify external assumptions | No | Inline | Needs judgement about what to probe and how to read the answer. The result shapes the plan |
| 1 — Plan | Only if a non-default planning model was chosen | Foreground (`Plan`) | Otherwise it needs user interaction, so it stays inline |
| 2 — Implement | Sometimes | Foreground · `ote-implementer` | Only for large changes (more than 3 files or 50 lines) |
| 3 — Build+Test | Yes | Foreground · `ote-build-test` | Build and test logs are the biggest context waste |
| 4 — Independent review | Yes | Foreground · `ote-code-reviewer` | Fresh eyes, no reasoning context |
| 5 — Apply fixes | Sometimes | Foreground · `ote-implementer` | Only for large fix sets |
| 5 — Verify loop | Always | Foreground · `ote-build-test` | Same as Phase 3. Always runs after fixes |
| 5 — Re-review (if the user says yes) | Yes | Foreground · `ote-code-reviewer` | Fresh eyes on the post-fix diff. Cycles back to Phase 4 |

## Notes

- Use the phase headers as checkpoints. Pause and surface progress after Plan, Implement and Independent Review.
- If uncommitted unrelated changes exist, stop and ask (see Phase 0). If the user agrees to stash, use `git stash push -u -m "<slug>: <desc>" -- <paths>` rather than discarding anything.
- For a plan with many independent tasks, Phase 2 can run under the `subagent-driven-development` skill instead (an implementer plus two reviewers per task). Phases 3–7 still apply afterwards.
- For genuinely trivial tasks (around 5 minutes), propose a stripped-down flow and proceed only if the user agrees.
- **Never poll for subagent completion.** No `ListAgents` loops, no sleep-waits, no background waiter commands. End the turn, and the harness resumes the session when the agent reports back.
- **Dispatch independent subagents in one message** so they run concurrently. Before sending an investigation agent, work out what else you will need to know about the same code and put it in the same brief instead of making a second round trip.
- **A task that runs across days gets a fresh session per phase.** The plan file is the handoff, so re-read it when the new session starts. It only works as a handoff if it is **committed** before the session ends. This workflow never commits on its own, so ask.
- **Context budget:** when in doubt about whether to use a subagent, use one. A subagent dispatch costs far less than running out of context mid-task.
