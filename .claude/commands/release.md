---
description: Prepare and publish a release. phase1 bumps the minor or major version, writes the en and uk notes, commits and opens a PR. phase2 (after the merge) tags, builds and creates the GitHub release.
argument-hint: [phase1 | phase2]
allowed-tools: Bash, Read, Write, Edit, Grep, Glob, Skill
---

**Use skill:** `release`

Pass `$ARGUMENTS` as the phase. If you omit it, the skill does Phase 1. The skill commits and pushes in Phase 1. In Phase 2 the skill asks the user to confirm before it pushes the tag and creates the release.
