# 46-install-module-version

## Settings
- Planning model: Opus 5.5 (inline). Review model: Sonnet 5.5.
- Flow: stripped-down (one shell script, about 10 lines, no `sources/` change).
- Auto-approve: the user granted standing auto-approve for the recommended options and the review findings.
- Finish: commit, push and create the PR. Leave the unrelated task 36 and task 44 files out of the commit.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/46

If no `-p` is given, `docker/install-module.sh:72` selects the package with `ls | sort | tail -n 1`. The script does not read `_version.txt`. A stray package with a higher version (`v04.01.8164`) thus hides every later correct build. The script affects only the Docker test stores. No OpenCart package changes.

Investigation skipped: the task is in one known file. Greps:
- `grep -n "releases\|_version" build.release.sh`: the release goes to `releases/v${VERSION}/OpenCartTranslateExpertClient-v${VERSION}-${LOCAL_DATE}.full.zip`. The build removes old zips in that folder first (`build.release.sh:11`).
- `grep -n "newest\|releases" docker/rebuild-stores.sh`: `rebuild-stores.sh` calls `install-module.sh` without `-p`. Its `-s` help text says "the newest existing release".
- `docker/README.md:36` and `:58` also say "newest".

## Approach
Without `-p`, read the version from `_version.txt` with the same whitespace strip as `build.release.sh:7`. Select `releases/v<version>/OpenCartTranslateExpertClient-v<version>-*.full.zip`. If no such file exists, stop with an error that names the version and tells the user to run `./build.release.sh`. Do not fall back to another version. If more than one file matches (two build dates for the same version), take the last one in sort order. Keep `-p` as the override. Update the comments in the script, the `-s` help text of `rebuild-stores.sh` and `docker/README.md`.

## Assumptions
- ✅ verified: `build.release.sh:7` reads `_version.txt` with `tr -d '[:space:]'`, and line 19 gives the file name pattern.
- ✅ verified: macOS bash 3.2 compatibility. The change uses only `tr`, `ls`, `sort` and `tail`.

## Steps
- [x] Code: `docker/install-module.sh` package selection and comments
  Findings: an empty or missing `_version.txt` also stops the script with its own error, because the glob `releases/v/...` must not match by accident.
- [x] Docs: `docker/README.md`, `docker/rebuild-stores.sh` help text
- [x] Probe: the issue verification with a fake `v04.01.9999` package (no Docker needed, run the selection part only)
  Findings: a fake repo layout in `tmp/fake` with `v04.01.9999` and `v04.01.0165` packages and `_version.txt` = `04.01.0165`. The `origin/main` script printed `Package: .../v04.01.9999/...`. The fixed script printed `Package: .../v04.01.0165/...`. A version with no package, an empty `_version.txt` and no `_version.txt` each stop with exit 1 and an error. `-p` still selects `v04.01.9999`. The script then fails at the store check, because the container has no Docker. The probe folder was removed after the run.
- [x] Bump version + build + test
  Findings: version 04.01.0165 → 04.01.0166. Build passed. Tests: 393 passed, 0 failed, 6 integration tests skipped (no key).
- [x] Independent review → 46-install-module-version-review.md
  Findings: Sonnet 5.5. No Critical or Important issues. One Minor issue: if `_version.txt` is missing, bash prints its own "No such file" line before the error of the script.
- [x] Address review findings (auto-approve)
  Findings: fixed. The `tr` redirect is now in a `{ ...; } 2>/dev/null` group. A probe showed only the error of the script. A failed `cd` in the probe deleted the real `_version.txt`. It was restored to `04.01.0166` (see the improvements file).
- [x] Bump version + build + tests (verify loop)
  Findings: version 04.01.0166 → 04.01.0167. Build passed. Tests: 393 passed, 0 failed, 6 skipped.
- [x] Re-review? (auto-approve: follow the recommendation)
  Findings: skipped. The only fix was a one-line redirect group, and a probe verified it.
- [x] Improvements pass → 46-install-module-version-improvements.md
- [ ] Commit, push, PR

## Done
- `docker/install-module.sh` now selects the release of `_version.txt` and stops if that package is missing. `-p` still overrides. `docker/README.md` and the `rebuild-stores.sh -s` help text match.
- Final version: 04.01.0167.
- Gotcha: a failed `cd` in a probe deleted `_version.txt`. The dev-workflow skill now requires absolute paths and `set -e` in probes.
- Unrelated task 36 and task 44 files stay uncommitted in the working tree, as the user asked.
