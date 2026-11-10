---
name: subagent-driven-development
description: Use when you execute implementation plans with independent tasks in the current session
---

# Subagent-Driven Development

Write all output, including files under `docs/tasks/**`, in ASD-STE100 Simplified Technical English. See **Writing style (ASD-STE100)** in `CLAUDE.md`.

Execute the plan. Dispatch a fresh subagent for each task. After each task, run a two-stage review: first the spec compliance review, then the code quality review.

**Why subagents:** You delegate tasks to specialized agents with isolated context. Write their instructions and context precisely. Then the agents stay focused and succeed at their task.

A subagent must never inherit the context or history of your session. Construct exactly what it needs. This also preserves your own context for coordination work.

**Core principle:** A fresh subagent for each task, plus a two-stage review (spec then quality), gives high quality and fast iteration.

**When to use it:** Use it inside `dev-workflow`, as an alternative to Phase 2, when the approved plan has several independent tasks. Setup, planning, build/test, final review and improvements still follow `dev-workflow`.

**Continuous execution:** Do not pause to check in with the user between tasks. Execute all tasks from the plan without a stop. Stop only in these cases:
- A BLOCKED status that you cannot resolve
- An ambiguity that genuinely prevents progress
- All tasks are complete

## Process

```
Read plan → Extract all tasks → Create TodoWrite
  ↓
For each task:
  → Dispatch ote-implementer
  → Handle status (DONE / DONE_WITH_CONCERNS / NEEDS_CONTEXT / BLOCKED)
  → Dispatch ote-spec-reviewer
  → Fix spec issues if any → re-review
  → Dispatch ote-code-quality-reviewer
  → Fix quality issues if any → re-review
  → Mark task complete
  ↓
After all tasks:
  → Dispatch ote-build-test (full mode: bump version + release build)
  → Dispatch ote-code-reviewer for the entire implementation
      → writes docs/tasks/<slug>/<slug>-review.md
  → Present its findings to the user per dev-workflow Phase 4 (ask per finding)
  → Stop: report to the user (never commit or push without being asked)
```

## Model Selection

Each agent pins the least powerful model that can handle its role. The pin is in the definition of the agent under `.claude/agents/`. Dispatch by `subagent_type`, and the model follows. Pass `model:` only to override deliberately.

| Role | Agent | Model |
|------|-------|-------|
| Implementation | `ote-implementer` | Sonnet |
| Spec compliance review | `ote-spec-reviewer` | Sonnet |
| Code quality review | `ote-code-quality-reviewer` | Opus |
| Final review of the whole change | `ote-code-reviewer` | Opus |
| Syntax check, tests, version bump, release build | `ote-build-test` | Haiku |

Override downward for a genuinely mechanical task. Override upward for a hard architectural task. When you override, say why.

## Handling Implementer Status

**DONE:** Proceed to the spec compliance review.

**DONE_WITH_CONCERNS:** Read the concerns before you proceed. If the concerns are about correctness or scope, address them first. If the concerns are observations, note them and proceed.

**NEEDS_CONTEXT:** Provide the missing context and dispatch the agent again.

**BLOCKED:** Assess the cause and act:
- Context problem: provide more context.
- Reasoning problem: use a more capable model.
- Task too large: break the task up.
- Plan wrong: escalate to the user.

## Agents

The definitions of the agents are in `.claude/agents/`. Dispatch them with the `Agent` tool by `subagent_type`.

- `ote-implementer` — implements one task
- `ote-spec-reviewer` — verifies the implementation matches the spec
- `ote-code-quality-reviewer` — verifies that the code is well built, after spec compliance passes
- `ote-code-reviewer` — reviews the whole change after every task is done

Each agent carries its own standing instructions. The dispatch prompt needs only what is specific to this task:
- The full task text
- The scene-setting context
- The report of the implementer
- The git range

`ote-code-reviewer` needs one more item: the path to write its review to. It reports through that file and not inline.

## Red Flags

**Never:**
- Skip reviews (spec compliance OR code quality)
- Proceed with unfixed issues
- Dispatch multiple implementation subagents in parallel (conflicts)
- Make a subagent read the plan file (provide the full text instead)
- Paste the standing instructions of an agent again into the dispatch prompt — they are in `.claude/agents/`
- Skip scene-setting context
- **Start the code quality review before the spec compliance review passes**
- Move to the next task while either review has open issues

**If a subagent asks questions:** Answer clearly and completely.

**If a per-task reviewer (spec or quality) finds issues:** The implementer fixes the issues. The reviewer reviews again. Repeat until the reviewer approves. Do not ask the user here. These loops keep the task inside its approved spec.

**If the final `ote-code-reviewer` finds issues:** Do not auto-fix. Ask the user for each finding, as in `dev-workflow` Phase 4, unless the user granted standing auto-approve.
