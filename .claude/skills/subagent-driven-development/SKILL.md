---
name: subagent-driven-development
description: Use when executing implementation plans with independent tasks in the current session
---

# Subagent-Driven Development

Execute plan by dispatching fresh subagent per task, with two-stage review after each: spec compliance review first, then code quality review.

**Why subagents:** You delegate tasks to specialized agents with isolated context. By precisely crafting their instructions and context, you ensure they stay focused and succeed at their task. They should never inherit your session's context or history — you construct exactly what they need. This also preserves your own context for coordination work.

**Core principle:** Fresh subagent per task + two-stage review (spec then quality) = high quality, fast iteration

**When to use it:** inside `dev-workflow`, as an alternative to Phase 2 when the approved plan has several independent tasks. Setup, planning, build/test, final review and improvements still follow `dev-workflow`.

**Continuous execution:** Do not pause to check in with the user between tasks. Execute all tasks from the plan without stopping. The only reasons to stop are: BLOCKED status you cannot resolve, ambiguity that genuinely prevents progress, or all tasks complete.

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

Each agent pins the least powerful model that can handle its role, in its own definition under `.claude/agents/`. Dispatch by `subagent_type` and the model follows; pass `model:` only to override deliberately.

| Role | Agent | Model |
|------|-------|-------|
| Implementation | `ote-implementer` | Sonnet |
| Spec compliance review | `ote-spec-reviewer` | Sonnet |
| Code quality review | `ote-code-quality-reviewer` | Opus |
| Final review of the whole change | `ote-code-reviewer` | Opus |
| Syntax check, tests, version bump, release build | `ote-build-test` | Haiku |

Override downward for a genuinely mechanical task, or upward for a hard architectural one — but say why when you do.

## Handling Implementer Status

**DONE:** Proceed to spec compliance review.

**DONE_WITH_CONCERNS:** Read concerns before proceeding. If about correctness/scope, address first. If observations, note and proceed.

**NEEDS_CONTEXT:** Provide missing context and re-dispatch.

**BLOCKED:** Assess: context problem → more context; reasoning problem → more capable model; task too large → break it up; plan wrong → escalate to user.

## Agents

Defined in `.claude/agents/`; dispatch with the `Agent` tool by `subagent_type`.

- `ote-implementer` — implements one task
- `ote-spec-reviewer` — verifies the implementation matches the spec
- `ote-code-quality-reviewer` — verifies it is well built, once spec compliance passes
- `ote-code-reviewer` — reviews the whole change once every task is done

Each agent carries its own standing instructions, so the dispatch prompt only needs what is
specific to this task: the full task text, scene-setting context, the implementer's report,
and the git range. `ote-code-reviewer` needs one thing more — the path to write its review
to — because it reports through that file rather than inline.

## Red Flags

**Never:**
- Skip reviews (spec compliance OR code quality)
- Proceed with unfixed issues
- Dispatch multiple implementation subagents in parallel (conflicts)
- Make subagent read plan file (provide full text instead)
- Re-paste an agent's standing instructions into the dispatch prompt — they live in `.claude/agents/`
- Skip scene-setting context
- **Start code quality review before spec compliance is approved**
- Move to next task while either review has open issues

**If subagent asks questions:** Answer clearly and completely.

**If a per-task reviewer (spec or quality) finds issues:** Implementer fixes → reviewer reviews again → repeat until approved. No need to ask the user here; these loops keep the task inside its approved spec.

**If the final `ote-code-reviewer` finds issues:** do not auto-fix. Ask the user per finding, as in `dev-workflow` Phase 4, unless they granted standing auto-approve.
