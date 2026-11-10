---
description: Bump the module version in _version.txt. Increments the patch part (MM.mm.pppp) automatically, or accepts an explicit version.
argument-hint: [MM.mm.pppp]
allowed-tools: Bash, Read, Write, Skill
---

**Use skill:** `bump-version`

Pass `$ARGUMENTS` as the optional target version. If you omit it, the skill reads the current version from `_version.txt`. The skill then increments the patch part (the last four digits, kept zero-padded).
