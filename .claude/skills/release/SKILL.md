---
name: release
description: Prepare and publish a release of OpenCart Translate Expert. Phase 1 bumps the minor or major version, writes the en and uk release notes from the commits, commits, pushes and opens a PR. Phase 2 starts after the PR merge. It tags the squash commit, pushes the tag, builds and creates the GitHub release with the full zip as an asset.
---

# Release

The release has 2 phases. A merged PR separates them. Use `phase1` or `phase2` as `$ARGUMENTS`. If `$ARGUMENTS` is empty, do Phase 1. If `$ARGUMENTS` has another value, stop and ask the user.

This skill is an exception to two rules of `CLAUDE.md`:

- Phase 1 changes `_version.txt` and runs no build. Phase 2 builds the version that the PR set, and it does not bump the patch part.
- The skill commits, pushes and opens a PR in Phase 1. It pushes a tag and creates a release in Phase 2.

Write the skill text and the PR text in ASD-STE100 style, as `CLAUDE.md` says. The release notes are user-facing text. Write them in plain English and in Ukrainian.

## Phase 1 — Prepare the release PR

### 1. Check the start state

```bash
git fetch origin main --tags
git status --short
git describe --tags --abbrev=0 --match '[0-9][0-9].[0-9][0-9].[0-9][0-9][0-9][0-9]' origin/main
cat _version.txt; echo
```

- If the working tree has changes, stop and ask the user.
- If no tag exists, stop and ask the user for the start commit of the notes.
- Name the latest tag `PREV`. Name the version in `_version.txt` `CURRENT`.

### 2. Ask for the release type

Count the commits since `PREV`:

```bash
git rev-list --count PREV..origin/main
git log PREV..origin/main --format='%s'
```

Ask the user: major or minor release? Suggest minor. Suggest major only if the release has many changes. Examples of many changes: a breaking change, a new supported OpenCart version, or a large new area.

### 3. Compute the new version

The format is `MM.mm.pppp`. The patch part always starts at `0100`.

- Minor release: increment `mm`. Keep `MM`. Set `pppp` to `0100`. Example: `04.01.0225` becomes `04.02.0100`.
- Major release: increment `MM`. Set `mm` to `00`. Set `pppp` to `0100`. Example: `04.01.0225` becomes `05.00.0100`.

Keep each part zero-padded. Name the result `NEW`. The tag has the same text as `NEW`. The tag has no `v` prefix.

If the tag `NEW` exists, stop and tell the user.

### 4. Create the branch

```bash
git checkout -b chore/release-NEW origin/main
```

If the branch exists, stop and ask the user. If the user gave an issue number for the release, use it in the branch name and in the commit.

### 5. Bump the version

Run the `bump-version` skill with `NEW` as the explicit version. The skill writes `_version.txt`.

### 6. Write the release notes

Use the commit titles from `git log PREV..origin/main --format='%s'`. Remove the PR number suffix when you group the items. Group the items under these headings, and drop an empty heading:

- New features (`feat`)
- Fixes (`fix`)
- Other changes (all other commits, for example `refactor`, `chore`, `docs`, `test`, `build` and `ci`, and commits with no type)

Create 2 files:

- `docs/release/NEW/release-notes.en.md` in English
- `docs/release/NEW/release-notes.uk.md` in Ukrainian

Start each file with the title `# Release NEW` and the release date in the form `DD.MM.YYYY`. Use the date of Phase 1. Tell the user to change it if the publish date is different. Write each item as one short line. The 2 files must have the same items in the same order. Show both files to the user.

### 7. Commit, push and open the PR

```bash
git add _version.txt docs/release/NEW
git commit -F - <<'EOF_MSG'
chore: prepare release NEW

Co-Authored-By: <the attribution line that the session gives>
EOF_MSG
git push -u origin chore/release-NEW
```

Open the PR with `gh pr create`. Use the title `chore: prepare release NEW`. List the previous tag, the new tag and the 2 notes files in the body.

### 8. Stop

Tell the user the PR URL. Tell the user to run `/release phase2` after the squash merge. Do not tag. Do not build.

## Phase 2 — Publish the release

Start this phase only after the user says that the PR is merged. Do not check the PR state yourself.

### 1. Update main and find the release commit

```bash
git status --short
```

If the working tree has changes, stop and ask the user. Then do these commands:

```bash
git fetch origin main --tags
git checkout main
git pull --ff-only origin main
git log -1 --format=%H --diff-filter=A -- 'docs/release/*/release-notes.en.md'
```

- The last command prints the squash commit of the release PR. Name it `SHA`. If it prints nothing, stop. The PR is not merged, or the user is on the wrong branch.
- Read the version from that commit: `git show SHA:_version.txt`. Name it `NEW`.
- If `docs/release/NEW/release-notes.en.md` or `docs/release/NEW/release-notes.uk.md` is missing at `SHA`, stop.
- If the tag `NEW` exists, stop and tell the user.
- Show `git log -1 --oneline SHA`.
- Later commits can exist on `main` after `SHA`. The tag and the build use `SHA`, not the tip of `main`.

### 2. Ask for confirmation

Show the user:

- The tag `NEW` and the commit `SHA`
- The 2 notes files
- The asset name that the build will make

Ask: create the tag, push it and publish the release? Do not continue without a clear yes. The tag and the release are public.

### 3. Tag and push

```bash
git tag -a NEW SHA -m "NEW"
git push origin NEW
```

### 4. Build

Run `git checkout SHA`. The build then uses the release commit only.

Run `./build.release.sh`. Do not bump the version. Do not run `/bump-version`. This is the exception to the build rule in `CLAUDE.md`.

If the build fails, stop and show the error. Do not delete the tag. Ask the user what to do.

The asset is `releases/vNEW/OpenCartTranslateExpertClient-vNEW-<date>.full.zip`. The file name has the build date. Find it:

```bash
ls releases/vNEW/OpenCartTranslateExpertClient-vNEW-*.full.zip
```

Exactly one file must match. If not, stop.

### 5. Create the GitHub release

Write a notes file in the scratchpad. Put the English notes first. Then put the Ukrainian notes.

```bash
gh release create NEW "<asset path>" --title "NEW" --verify-tag --notes-file <notes file> -R vhoshko/opencart-translate-expert
```

### 6. Check and report

```bash
gh release view NEW -R vhoshko/opencart-translate-expert --json url,assets
```

Run `git checkout main`, so the repository is not on a detached HEAD. Report the release URL and the asset name. Check the repository root. If `.zip` files or `OpenCartTranslateExpertClient-*` folders are there, remove them.
