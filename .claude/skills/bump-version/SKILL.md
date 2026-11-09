---
name: bump-version
description: Bump the OpenCart Translate Expert version in _version.txt. Auto-increments the patch part (MM.mm.pppp, e.g. 04.01.0105 → 04.01.0106) if no version is given. Required before every build.
---

# Bump Version

`_version.txt` is the only place the version lives. `build.sh` injects it into the OCMOD XML (`{client_version}`), the controller and the package names at build time. Nothing else needs editing.

## Input

`$ARGUMENTS` may be:
- **Empty.** Increment the **patch** part (the last four digits) by 1, keeping it zero-padded.
- **A version string** like `04.02.0000`, which must match `^\d{2}\.\d{2}\.\d{4}$`. Bump to exactly that version. Refuse anything that does not match the format, and refuse a version that is not higher than the current one.

## Steps

### 1. Read the current version

```bash
cat _version.txt
```

Trim whitespace. This is the source of truth.

### 2. Determine the new version

- If `$ARGUMENTS` is a valid version, use it.
- Otherwise split on `.`, increment the third part as an integer, and re-pad it to four digits (`0105` → `0106`, `0999` → `1000`). If it would exceed `9999`, stop and ask the user which minor or major version to move to.

Echo `Bumping A → B` and proceed immediately. No confirmation is needed.

### 3. Write it

```bash
printf '%s' "<new version>" > _version.txt
```

The file has no trailing newline. Keep it that way.

### 4. Verify

```bash
cat _version.txt; echo
ls releases/ | grep "v<new version>" || true
```

If `releases/v<new version>/` already exists, warn that this version has been built before. Every build must use a fresh version.

Report: `Version bumped to <new version>`.

Do not commit, branch or build as part of this skill. The version bump is committed together with the change it ships.
