---
name: dev-workflow
description: Use when you run an end-to-end coding task. Orchestrates the setup, planning, implementation, build, test and review phases, and never commits.
---

# Dev Workflow

## Overview

This workflow orchestrates a coding task end-to-end through 7 phases. Each phase must pass before the next phase starts. The workflow never commits, pushes or creates PRs. It stops after the review and the improvements.

For a trivial task (a single file, under 20 lines, no schema or build-script impact), propose a stripped-down flow. Proceed only if the user agrees.

The stripped-down flow has these steps:

1. Ask for the planning and review models (Phase 0).
2. Create the branch and the task folder (Phase 0). Do not dispatch the Explore agent or the sanity check.
3. Write a short plan with Settings, Context, Approach, Assumptions and Steps (Phase 1).
4. Implement the change in the main context (Phase 2).
5. Dispatch `ote-build-test` in full mode (Phase 3).
6. Dispatch one independent review (Phase 4). If fixes follow, do Phase 5 as usual.
7. Write the improvements file and fill the Done block (Phases 6 and 7).

Project-specific config (repo, commands, source layout, conventions, gotchas) is in `.claude/project-workflows.md`. The definitions of the subagents that this workflow dispatches are in `.claude/agents/`. Each subagent pins its own model. Dispatch by `subagent_type`. Pass `model:` only to override.

### Context budget principle

**Protect the main conversation context.** Most phases produce verbose output (file reads, build logs, test output, diffs). The user does not need to see this output in full. Delegate these phases to subagents. The main context then holds only summaries, user decisions and phase transitions.

The main conversation is a control plane. The subagents are the data plane.

## Phase Flow

```
Setup → Plan → Implement → Build+Test → Independent review → Fixes+Verify → Done → Improvements
```

**Gates:** Each phase must be green before the next phase starts. If a phase is red, surface it and ask before you proceed.

**After any code changes (implementation or review fixes):** ask the user if they want another iteration of independent review before you proceed.

## Phase 0 — Setup

Parse the input. Then create the branch and the task folder.

### Model selection

Ask this first, before anything else. Do not ask if one of these is true:
- The user already answered in this conversation.
- An earlier session on the same task recorded the answer in the `## Settings` block of the plan.

Ask the user for two models:

1. **Model for planning (Phase 1).** Default: the current session model. With this default, you write the plan inline in the main conversation, with no subagent.
2. **Model for independent review (Phase 4, and any re-review passes in Phase 5).** Default: a different model from the planning model. The review is then an independent perspective, and the same model does not check its own work.

   `ote-code-reviewer` pins Opus. Opus is the default when planning used another model. If planning also used Opus, pick a different review model and pass it explicitly.

Keep both choices through Phase 0. Write them into the `## Settings` block of the plan when Phase 1 creates the file. The choices apply for the rest of the task, across sessions, unless the user changes them.

**To apply the choice:**
- **Planning:** If the planning model is the current session model, write the plan inline as usual. If the user chose a different model, dispatch it with `Agent` (`subagent_type: Plan`, `model: <chosen>`) to produce the plan content. Then bring the content into the main conversation for the Phase 1 checkpoint. Planning subagents do not talk to the user directly.
- **Independent review:** `ote-code-reviewer` pins Opus. Pass `model: <chosen>` on Phase 4 and Phase 5 re-review dispatches only when the chosen model differs from that pin.

### Input parsing

The input can contain any combination of these items:
- A task description (free text)
- A GitHub issue URL or `#<n>`
- A pasted issue body or chat snippet

If the input has an issue reference, fetch the issue:
```bash
gh issue view <url-or-number> -R vhoshko/opencart-translate-expert
```

### Restate the task

Summarize the task in 2–4 sentences. State what the task asks, why, and which OpenCart versions it affects (all of them, ≤ 3.0 only, or 4.0 only). **If anything is ambiguous, ask before you go further.**

### Choose a slug

- With an issue: `<issue-number>-<kebab-title>` (for example `4-cron-translation`)
- Without an issue: `<kebab-slug>`

Show the slug to the user. The user can override it.

### Create the branch and task folder

Before you discuss the branch base with the user, run `git fetch origin main`. Then check `git log origin/main`. The git status at session start is a snapshot, and it can be stale. A stale snapshot can show a merged commit as unmerged.

```bash
git fetch origin main
git checkout -b <prefix>/<slug> origin/main
mkdir -p docs/tasks/<slug>/tmp
```

If the working tree has uncommitted changes unrelated to this task, **stop and ask**. Do this before you create the branch. Untracked files carry over a `checkout -b` anyway. Stash only if the user agrees, and include untracked files:
```bash
git stash push -u -m "<slug>: <one-line description>" -- <paths>
```
Record the stash in the Done block of the plan. Never use `git checkout --` or `git restore` as a shortcut.

### Investigation subagent

**Dispatch an Explore agent** (`subagent_type=Explore`) to map the problem across `sources/`. The root cause can span the controller, the model, the core library, the JS and the templates. Let the agent investigate freely.

Give the agent these items:
- The task description or issue body
- The source layout table from `project-workflows.md`
- An instruction to return these items:
  - The root cause analysis
  - The key files as `path:line`
  - The data flow (JS → AJAX action → controller → core library → model / Google API)
  - The OC versions that run the touched code
  - Any design corrections it finds

The main context receives only the summary of the agent. It does not receive hundreds of lines of source code. Use this summary to write the plan.

**Verify the facts of the agent before you build on them.** Weigh the *conclusions* of the agent as input. Check its *factual claims about the codebase* directly with a grep or a file read. Examples of such claims are "only X calls Y" and "this setting is never read". Check each claim before a design decision depends on it. One grep costs less than a design built on a wrong premise.

If two agents disagree on a fact, neither agent is authoritative. Look at the code yourself.

**Skip the investigation subagent** if the task is trivially locatable (for example "fix the typo in file X line Y"). Read the relevant file directly.

### Verify external assumptions (before planning, not after)

Some tasks touch a system that you do not control. Examples are the Google Cloud Translation API and OpenCart core behavior on a version that you cannot run. For such a task, ask this question before you write the plan:

> What does this plan assume that system does, and what is the cheapest way to find out?

Then find out. Use a throwaway probe, a single live call or the OpenCart source for that version. Write the answer down. The answer is evidence, and it outlives the session. A live Google API call needs a key that the user supplies. Never look for a key yourself.

Record each load-bearing assumption in the `## Assumptions` section of the plan. Mark each assumption as verified or not. Add the evidence, or the reason that you could not check it.

**Skip** this step in two cases. The task touches nothing external. Or code that demonstrably works in production already establishes the behavior. In that case, cite that code as the evidence.

### Sanity check (subagent, background)

Dispatch `ote-build-test` (`subagent_type: ote-build-test`) in the background. Do this **before you change anything**. Put **"checks only"** and the list of files that the task will touch in the prompt.

In checks-only mode, the agent runs `php -l` and the unit tests. It does not bump `_version.txt` and does not build, because every build uses up a version number. It reports only pass or fail. If the check fails on code that you did not write, stop and surface the failure.

## Phase 1 — Plan

Use the planning model from Phase 0. If it matches the session model, plan inline in the main context. Otherwise use a `Plan` subagent (see Phase 0). Write the plan to `docs/tasks/<slug>/<slug>_plan.md`.

Write the plan in ASD-STE100 Simplified Technical English. See **Writing style (ASD-STE100)** in `CLAUDE.md`.

```markdown
# <slug>

## Settings
<planning model · review model · build, tests, commit, push, PR, as agreed with the user,
 and whether the user granted standing auto-approve for review findings.
 Record this here. A later session that picks up this task then does not have to ask again.>

## Context
<task summary, issue link, why this matters, affected OC versions, key files as path:line from the investigation agent>

## Approach
<one short paragraph; call out design corrections explicitly>

## Assumptions
<one line per load-bearing assumption, each marked:
 - ✅ verified: how, with the evidence (probe output, a run, code that proves it)
 - ❓ unverified: why not, and what breaks if it is false
 Drop the section only if the task assumes nothing outside the code that changes.>

## Steps
- [ ] Code: <task-specific steps>
- [ ] Templates: .tpl and .oc4.twig (drop if no UI change)
- [ ] Language strings: en-gb, ru-ru, uk-ua (drop if no new strings)
- [ ] Bump version + build + test
- [ ] Independent review → <slug>-review.md
- [ ] Address review findings (the user picks which to fix or skip)
- [ ] Bump version + build + tests (verify loop)
- [ ] Re-review? (unreviewed fixes exist. Ask the user. If yes, repeat from the review step → -review-v2.md, v3, …)
- [ ] Improvements pass → <slug>-improvements.md

## Done
<fill at the end>
```

Adapt the template. Drop the steps that do not apply. If you skip a standard step, explain why and confirm with the user.

**Plan review checkpoint:** Paste Context, Approach, Assumptions and Steps in chat. Ask the user to approve. Do not start Phase 2 until the user acknowledges the plan.

**Do not ask for approval of a plan that has unverified load-bearing assumptions.** Verify the assumptions first. Or say plainly which assumptions are unverified and what fails if one is wrong. The user then approves a known risk and not an unknown risk. "Approved" does not make an assumption true.

**Plan updates during development:** The plan is an audit trail. It must be able to record that it was wrong.

- Use `~~strikethrough~~` for removed lines. Add `## Update <date>` sections. Do not rewrite silently.
- If evidence kills an approach, leave the approach in place under a `> **Superseded.**` banner. State what disproved it and where the replacement is. A plan that silently deletes its dead branches teaches the next reader nothing. The next reader can then retry the same dead end.
- Add an inline `Findings:` note to each step when the step completes. Cover gotchas, decisions and deferrals. If you write them at the end, you write them from memory.

## Phase 2 — Implement

Follow the conventions in `project-workflows.md` and `CLAUDE.md`. Put throwaway scripts in `docs/tasks/<slug>/tmp/`.

The non-negotiables for this repo:
- Edit `sources/` only.
- Keep every file PHP 5.6 compatible. Put PHP 7+ syntax only between `// OC4_EVENT_START` and `// OC4_EVENT_END` **in the admin controller**. This is the one file whose marker blocks `build.sh` strips. In other files, gate OC4-only behavior at runtime with `version_compare(VERSION, '4.0', '>=')`.
- Write routes and class names in the OC 2.3 form. `build.sh` rewrites them generically for OC 1.5–2.2.
- A new AJAX method, library class or `client_translate_expert_*` setting key also needs an entry in the fixed OC 3.0/4.0 rewrite lists of `build.sh` (see `project-workflows.md`).
- Put UI changes in both the `.tpl` (PHP template, OC 1.5–3.0) and the `.oc4.twig` (Twig, OC 4.0). Put new strings in all three language files.

**For small changes (1–3 files, under 50 lines):** Implement directly in the main context. A subagent dispatch costs more than it saves.

**For larger changes:** You can dispatch `ote-implementer`. Give it these items:
- The approved plan
- The investigation summary
- An instruction to make the changes and report what it did

**Update the plan continuously.** When a step finishes, flip its checkbox to `[x]` at once. Add an inline `Findings:` note. Record gotchas, decisions and deferrals. The plan is a live audit trail.

## Phase 3 — Build + Test (subagent)

**Dispatch `ote-build-test`** (`subagent_type: ote-build-test`). The agent runs the syntax check, the PHP-compatibility grep, the tests, the version bump and the release build together. This keeps all verbose output out of the main context.

Give the agent these items:
- The list of edited files
- A Google API key for integration tests, only if the user supplied one for this task
- Any route or class-name strings, AJAX methods, library classes or setting keys that the diff added. The agent can then check them in the OC 1.5, 2.2, 3.0 and 4.0 packages.

The agent owns the sequence. Its definition in `.claude/agents/ote-build-test.md` lists the steps. The agent reports pass or fail, error details, the test count, the version bump and the release path. The main context sees only this summary.

### Prove the test would have caught it (bug fixes only)

A green suite shows that the code works **now**. It does not show that the new test would have failed **before** the fix. A test that passes either way is not a regression test. It is a test of current behavior that looks like a regression test.

On a bug-fix task, do these steps when the suite is green:

1. Copy the changed files byte-for-byte.
2. Disable the fix (not the test).
3. Run the suite again.
4. Confirm that the new test is among the failures, **and that it fails on the assertion you expect**.
5. Restore the fix from the copies. Do not retype the code.
6. Verify the restore with `git diff --stat` and a grep for whatever you used to disable the fix. Do this before you move on.

The test scripts have no filter, so each script always runs its whole suite. Run the script inline (`php tests/test_vh_google_translator.php` or `php tests/test_client_translate_expert_route_filter.php`). Or run it through `ote-build-test` in **checks-only** mode. Never run a full build for this check, because a full build uses up a version number.

This applies only where a test exists. Today the tests are for `VHGoogleTranslator` (`tests/test_vh_google_translator.php`) and `ClientTranslateExpertRouteFilter` (`tests/test_client_translate_expert_route_filter.php`). For code without a test harness, say so in the Findings of the plan. Do not claim regression coverage.

## Phase 4 — Independent review (subagent)

Dispatch `ote-code-reviewer` (`subagent_type: ote-code-reviewer`). The agent pins Opus. Pass `model: <review model chosen in Phase 0>` only when Phase 0 chose another model. Give the agent these items:
- The task description
- The diff (`git diff origin/main...HEAD` plus untracked files, or `git diff HEAD` if nothing is committed)
- The full path to write the review to: `docs/tasks/<slug>/<slug>-review.md`
- Repo access: the agent inspects the repo and writes that one file, nothing else

The reviewer must NOT see your reasoning.

When the agent returns, do these steps:
1. Read the review file.
2. Synthesize the findings for the user. Give a **fix-cost estimate** for each finding.
3. Decide each finding: **fix or skip**.
   - Default: ask the user for each finding. Do not auto-apply anything.
   - Decide the findings yourself only when the user said to decide review findings without a question to the user. The user can say this in this conversation. Or an earlier session on the same task can record it in the `## Settings` block of the plan. Report each decision with its reasoning. This is the only standing auto-approve rule. Always go back to the user for anything that changes the agreed approach, touches an external system or expands scope.
   - **Under auto-approve, prefer to fix a finding.** Fix a finding that is technically correct and cheap to apply. Skip a finding only for an actual reason:
     - Real cost disproportionate to the fix
     - Genuine scope creep
     - An external-system risk
     - A design trade-off that only the user can pick

     "It needs another verify cycle" is not a reason by itself. A verify cycle runs after Phase 5 regardless, so a cheap local fix adds no cost.
4. Proceed to Phase 5 with those decisions.

**Scope expansion:** Update the plan with `## Update <date>`. Dispatch a targeted second review that writes to `<slug>-review-v2.md`. Tell the agent to skip the sections that are already approved.

## Phase 5 — Post-review fixes + verify loop

Do this phase when every review finding has a decision, from the user or from you under auto-approve.

**Apply fixes:**
- For small fixes (1–3 findings, under 20 lines), apply them directly in the main context.
- For larger fix sets, dispatch `ote-implementer`. Give it the specific findings to fix (numbered, with the review text) and the current code state. Tell it to apply fixes only, without a build or tests.

**Verify loop.** Always dispatch `ote-build-test` after fixes. Use the same sequence as in Phase 3. Include a fresh version bump, because every build needs a new patch version.

Quote the pass or fail result, the test count and the new version in chat.

**If the build or tests fail:** Surface the errors. Ask the user how to proceed (fix and run the verify loop again, or skip).

**Re-review gate: required after every fix pass.**
No independent reviewer has seen the fixes that you just applied. This is a hard checkpoint. Never skip it, even if the changes look trivial.

1. Tick the `[ ] Re-review?` step in the plan.
2. Report the build/test result. Then ask explicitly: **"Fixes applied and build is green. Want another independent review pass?"**
   - If the user says **yes**, add `[ ] Independent review → <slug>-review-v2.md` to the plan. Go back to Phase 4. Dispatch `ote-code-reviewer` again with the review model from Phase 0. Pass `model:` only if it differs from the Opus pin of the agent. Or ask the user if they want to switch models for this pass. Increment the version suffix for each pass (v2, v3, …).
   - If the user says **no**, proceed to Phase 6.

**Every re-review brief must carry an explicit already-settled list.** Name what earlier passes examined and approved. This includes the design decisions, the mechanisms and the specific concerns that earlier passes already checked. Tell the reviewer plainly not to re-litigate them.

Without this list, a fresh reviewer derives the whole design again. It spends its attention on ground that is already covered. It buries the new code in restated old findings. With the list, the pass focuses on what changed.

Also give the reviewer the facts that are established so far, in the brief. This is important for anything measured against an external system. The reviewer must treat these facts as ground truth and must not question them again.

Do not restate the rest. `ote-code-reviewer.md` holds these standing instructions: scope exclusions, `git diff HEAD` for uncommitted work, and "these are clean is a valid outcome".

**Stop when the passes find nothing important.** Track what each pass returns. If the findings drop to wording and none touch correctness, another pass only polishes phrasing. Say so and recommend that the user stops. Do not loop by reflex.

If a re-review gives only Minor findings that the reviewer marks "no action necessary", do not ask a fix-or-skip question for each one. Record the findings in the plan. Then recommend a stop. The user can still ask for a fix.

**A fix can introduce a regression.** This is the reason for the gate: a fix that closes one finding was never reviewed itself. Treat a fix pass exactly like the original change. Do not rubber-stamp it.

## Phase 6 — Mark Done

Update the plan. Tick the checkboxes. Fill in the **Done** block (3–5 lines). Cover what landed, the final version number, gotchas and deferred follow-ups.

**Do not mark Done until Phase 7 is complete.**

## Phase 7 — Improvements

Write `docs/tasks/<slug>/<slug>-improvements.md`.

Write the improvements file in ASD-STE100 Simplified Technical English. See **Writing style (ASD-STE100)** in `CLAUDE.md`.

Cover these items:

1. Permission prompts that you hit. Suggest `.claude/settings.json` entries for recurring low-risk prompts. Also flag entries to **prune**. The allowlist collects single-use rules that are pinned to the paths of one session. These rules can never match again, and they bury the rules that matter.
2. Steps that you skipped or improvised
3. New steps that should exist
4. Friction points
5. Proposed edits to the workflow skills

**Act on friction that you see twice. Do not report it again.** Before you write the file, grep earlier improvements files for a keyword from the friction. Do not read the files. Use `grep -rl "<keyword>" docs/tasks --include="*improvements*.md"`, quoted so zsh does not abort when no files exist yet.

If the friction came up before, do one of two things. Fix it in the skill. Or promote it to the **Known Environment Gotchas** table in `project-workflows.md`. There is no third option.

Surface a summary of 3–5 bullets in chat. Do not edit skills without the go-ahead of the user.

## Subagent dispatch summary

| Phase | Subagent? | Type | Why |
|-------|-----------|------|-----|
| 0 — Investigation | Yes (Explore) | Foreground | Source files stay out of the main context |
| 0 — Sanity check | Yes | Background · `ote-build-test` (checks only) | Test output is noise. This step does not build, so it does not use a version number |
| 0 — Verify external assumptions | No | Inline | You must judge what to probe and how to read the answer. The result shapes the plan |
| 1 — Plan | Only if the user chose a non-default planning model | Foreground (`Plan`) | Otherwise you need the user, so you write the plan inline |
| 2 — Implement | Sometimes | Foreground · `ote-implementer` | Use it only for large changes (more than 3 files or 50 lines) |
| 3 — Build+Test | Yes | Foreground · `ote-build-test` | Build and test logs are the biggest context waste |
| 4 — Independent review | Yes | Foreground · `ote-code-reviewer` | Fresh eyes, no reasoning context |
| 5 — Apply fixes | Sometimes | Foreground · `ote-implementer` | Use it only for large fix sets |
| 5 — Verify loop | Always | Foreground · `ote-build-test` | Same as Phase 3. Run it after every fix pass |
| 5 — Re-review (if the user says yes) | Yes | Foreground · `ote-code-reviewer` | Fresh eyes on the post-fix diff. This pass goes back to Phase 4 |

## Notes

- Use the phase headers as checkpoints. Pause and surface progress after Plan, Implement and Independent Review.
- If unrelated uncommitted changes exist, stop and ask (see Phase 0). If the user agrees to stash, use `git stash push -u -m "<slug>: <desc>" -- <paths>`. Do not discard anything.
- For a plan with many independent tasks, Phase 2 can run under the `subagent-driven-development` skill instead (an implementer plus two reviewers for each task). Phases 3–7 still apply afterward.
- For genuinely trivial tasks (around 5 minutes), propose the stripped-down flow (see Overview). Proceed only if the user agrees.
- **Never poll for subagent completion.** Do not use `ListAgents` loops, sleep-waits or background waiter commands. End the turn. The harness resumes the session when the agent reports back.
- **Dispatch independent subagents in one message** so that they run concurrently. Before you send an investigation agent, decide what else you need to know about the same code. Put it in the same brief. This prevents a second round trip.
- **A task that runs across days gets a fresh session for each phase.** The plan file is the handoff. Re-read it when the new session starts. It works as a handoff only if you **commit** it before the session ends. This workflow never commits on its own, so ask the user.
- **Context budget:** If you doubt whether to use a subagent, use one. A subagent dispatch costs far less than a context that runs out in the middle of a task.
