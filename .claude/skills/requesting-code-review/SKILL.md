---
name: requesting-code-review
description: Use when you complete tasks, implement major features, or before you merge, to verify that the work meets the requirements
---

# Requesting Code Review

Dispatch a code reviewer subagent to find issues before they cascade. Give the reviewer precise context for the evaluation. Never give the reviewer your session history.

**Core principle:** Review early and review often.

## When to Request Review

**Mandatory:**
- After all tasks in subagent-driven development (the per-task reviews there use `ote-spec-reviewer` and `ote-code-quality-reviewer` instead)
- After you complete a major feature
- Before you merge to main

**Optional but valuable:**
- When you are stuck (to get a fresh perspective)
- Before you refactor (baseline check)
- After you fix a complex bug

## How to Request

**1. Get git SHAs:**
```bash
BASE_SHA=$(git rev-parse HEAD~1)  # or origin/main
HEAD_SHA=$(git rev-parse HEAD)
```

**2. Dispatch `ote-code-reviewer`** (`Agent` tool, `subagent_type: ote-code-reviewer`).

The agent has its own review instructions and pins Opus. The dispatch prompt gives only
the information that is specific to this review:

- A brief summary of what you built
- What the work must do: the plan file path, the task text, or the requirements
- The git range (`BASE_SHA`..`HEAD_SHA`), or `git diff HEAD` if nothing is committed yet
- The path for the review: `docs/tasks/<slug>/<slug>-review.md` inside a dev-workflow task, otherwise a scratchpad path
- Anything that an earlier pass already settled, so that the reviewer does not discuss it again

**3. Act on feedback.** Use the same adjudication rule as `dev-workflow` Phase 4. Ask the user about each finding. Do not ask if the user gave standing auto-approve, in this conversation or in the `## Settings` block of the plan.
- Fix Critical issues first
- Fix Important issues before you continue
- Note Minor issues for later
- If the reviewer is wrong, push back and give your reasons

Agent definition: `.claude/agents/ote-code-reviewer.md`
