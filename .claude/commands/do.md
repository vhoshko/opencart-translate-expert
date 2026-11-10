---
description: Run a coding task end-to-end — clarify, plan, implement, test, independent review, then stop (never commits).
argument-hint: <task description | URL | paste>
allowed-tools: Bash, Read, Edit, Write, Grep, Glob, Agent, Skill, TodoWrite, ToolSearch
---

**Use skill:** `dev-workflow`

Pass `$ARGUMENTS` as the task input. The skill controls the full workflow: setup, plan, implement, build, test, independent review, and improvements. The skill never commits, pushes, or creates PRs.
