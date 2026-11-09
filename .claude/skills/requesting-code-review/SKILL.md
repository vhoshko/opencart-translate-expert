---
name: requesting-code-review
description: Use when completing tasks, implementing major features, or before merging to verify work meets requirements
---

# Requesting Code Review

Dispatch a code reviewer subagent to catch issues before they cascade. The reviewer gets precisely crafted context for evaluation — never your session's history.

**Core principle:** Review early, review often.

## When to Request Review

**Mandatory:**
- After all tasks in subagent-driven development (per-task reviews there use `ote-spec-reviewer` and `ote-code-quality-reviewer` instead)
- After completing a major feature
- Before merge to main

**Optional but valuable:**
- When stuck (fresh perspective)
- Before refactoring (baseline check)
- After fixing complex bug

## How to Request

**1. Get git SHAs:**
```bash
BASE_SHA=$(git rev-parse HEAD~1)  # or origin/main
HEAD_SHA=$(git rev-parse HEAD)
```

**2. Dispatch `ote-code-reviewer`** (`Agent` tool, `subagent_type: ote-code-reviewer`).

The agent carries its own review instructions and pins Opus. The dispatch prompt supplies only
what is specific to this review:

- Brief summary of what you built
- What it should do — plan file path, task text, or requirements
- The git range (`BASE_SHA`..`HEAD_SHA`), or `git diff HEAD` when nothing is committed yet
- Where to write the review: `docs/tasks/<slug>/<slug>-review.md` inside a dev-workflow task, otherwise a scratchpad path
- Anything an earlier pass already settled, so it is not re-litigated

**3. Act on feedback**, under the same adjudication rule as `dev-workflow` Phase 4 — ask the user per finding unless they granted standing auto-approve, either in this conversation or in the plan's `## Settings` block:
- Fix Critical issues first
- Fix Important issues before proceeding
- Note Minor issues for later
- Push back if reviewer is wrong (with reasoning)

Agent definition: `.claude/agents/ote-code-reviewer.md`
