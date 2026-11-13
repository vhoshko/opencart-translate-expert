# Review: issue 46, install-module.sh package selection

## Strengths
- The script reads `_version.txt` with the same `tr -d '[:space:]'` strip as `build.release.sh:7`.
- The glob matches the `build.release.sh:19` name: `v<version>-<YYYYMMDD>.full.zip`.
- There is no fallback to another version. A stray higher version cannot hide the current build.
- `-p` is unchanged and still skips the version logic. `-c` never needs the version.
- Only `tr`, `ls`, `sort` and `tail` are used. This is safe for bash 3.2 and `set -euo pipefail`. Each pipeline ends with `|| true`, and an explicit check follows it.
- The error messages name the version and the folder, and they tell the user what to do.
- Docs and help text are consistent: `rebuild-stores.sh` `-s`, README and the script header.

## Issues

### Critical
None.

### Important
None.

### Minor
- `docker/install-module.sh:75`: the line `2>/dev/null` comes after `< file`. If `_version.txt` is missing, bash prints its own "No such file or directory" error before the redirect applies. I measured this. The script still stops with exit 1 and its own message, so the user sees two messages. Fix: use `{ tr -d '[:space:]' < "$f"; } 2>/dev/null || true`, or test `[ -f ... ]` first. This is cosmetic.
- Several zips for one version: `build.release.sh:11` deletes old zips in the folder first, so this case cannot come from the build. If it happens by hand, `sort | tail -n 1` takes the latest `YYYYMMDD` date. This is correct, because the date is zero-padded (`date +%Y%m%d`). I probed the sort. The behavior is fine. A one-line comment would make that clear. This is optional.
- An empty `_version.txt` gives the glob `releases/v/...-v-*.full.zip`. The explicit empty check at line 76 stops this first. This is correct. I did not probe it, because the logic is plain.

## Assessment
**Ready to merge?** Yes. The change meets the task. The one nit (double error output when `_version.txt` is missing) is cosmetic. I removed my probe folder `docs/tasks/46-install-module-version/tmp`.
