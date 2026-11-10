---
name: bump-version
description: Bump the OpenCart Translate Expert version in _version.txt. Increments the patch part (MM.mm.pppp, for example 04.01.0105 → 04.01.0106) automatically if no version is given. Required before every build.
---

# Bump Version

`_version.txt` is the only place that holds the version. At build time, `build.sh` puts the version into the OCMOD XML (`{client_version}`), the controller and the package names. No other file needs a change.

## Input

`$ARGUMENTS` can be one of these:
- **Empty.** Increment the **patch** part (the last four digits) by 1. Keep it zero-padded.
- **A version string** like `04.02.0000`. The string must match `^\d{2}\.\d{2}\.\d{4}$`. Bump to exactly that version.

Refuse a string that does not match the format. Refuse a version that is not higher than the current version.

## Steps

### 1. Read the current version

```bash
cat _version.txt
```

Trim the whitespace. This value is the source of truth.

### 2. Determine the new version

- If `$ARGUMENTS` is a valid version, use it.
- Otherwise, split the version on `.`. Increment the third part as an integer. Pad it again to four digits (`0105` → `0106`, `0999` → `1000`).
- If the third part would exceed `9999`, stop. Ask the user which minor or major version to use.

Echo `Bumping A → B` and continue immediately. Do not ask for confirmation.

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

If `releases/v<new version>/` exists, warn that a build used this version before. Every build must use a new version.

Report: `Version bumped to <new version>`.

Do not commit, branch or build in this skill. The version bump goes into the same commit as the change that it ships.
