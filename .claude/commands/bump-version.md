---
description: Bump the module version in _version.txt. Auto-increments the patch part (MM.mm.pppp), or you can pass an explicit version.
argument-hint: [MM.mm.pppp]
allowed-tools: Bash, Read, Write, Skill
---

**Use skill:** `bump-version`

Pass `$ARGUMENTS` as the optional target version. If it is omitted, the skill reads the current version from `_version.txt` and increments the patch part (the last four digits, kept zero-padded).
