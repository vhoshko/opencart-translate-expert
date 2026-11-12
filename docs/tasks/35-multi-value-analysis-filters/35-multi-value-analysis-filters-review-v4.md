# Review v4: Twig name test rewrite and the git step of `rebuild-stores.sh`

Scope: two changes only.

1. `tests/test_oc4_twig_filters.php` with its doc lines in `CLAUDE.md` and `.claude/project-workflows.md`.
2. Commit `db4fecf`: `docker/rebuild-stores.sh`, `docker/README.md` and one gotchas row.

Note: change 1 was uncommitted at the start of this review. During the review, commit `b6ff8dc` recorded it. The working tree is clean, so the reviewed text and the committed text are the same.

Probe scripts of this review:

- `docs/tasks/35-multi-value-analysis-filters/tmp/probe_v4_scanner.php` (49 edge inputs for the scanner)
- `docs/tasks/35-multi-value-analysis-filters/tmp/probe_v4_rebuild_git.sh` (11 git conditions, with a stub `docker`)

## Strengths

### Twig name test

- All four allow lists are correct. I compared each list with `CoreExtension.php`, `EscaperExtension.php`, `ExpressionParser.php` and the `TokenParser` classes of both Twig source trees.
  - Filters: the 37 names are the full v3.4.2 set. v3.18.0 adds only `find` and `shuffle`.
  - Tests: the 10 first words are the full v3.4.2 set. v3.18.0 adds only `mapping` and `sequence`.
  - Functions: v3.4.2 registers 9 names. `parent`, `block` and `attribute` are parser built-ins there (`ExpressionParser::getFunctionNode`). v3.18.0 adds only `enum` and `enum_cases`.
  - Tags: v3.4.2 has 16 Core parsers, `autoescape` from the Escaper extension, and `verbatim` in the lexer. v3.18.0 adds only `guard` and `types`.
  - The lists correctly leave out `dump`, `template_from_string` and `sandbox`. `Environment` loads only the Core, Escaper and Optimizer extensions by default.
- The test passes: 20 passed, 0 failed.
- The scanner gave no PHP warning on any edge input. The inputs included an unclosed block, an unclosed string, a backslash at the end of the file, an unclosed interpolation and a lone `{` at the end of the file.
- The scanner follows the Twig lexer where it matters. It ends a block only at bracket depth 0, as the lexer does. It handles escapes in both string types. It reads `#{...}` only in double-quoted strings, also when nested.
- The scanner found a planted bad name in each of these positions: a filter argument, a test argument, an `apply` chain, a nested interpolation, after a comment, after a `verbatim` block, and with `~` whitespace control.
- The scanner gave no false positive on 19 valid inputs that a template can plausibly use. Examples are arrow functions, `not in (..)`, `b-and`, `starts with (..)`, nested hashes, `import ... as` macro calls and JS braces outside Twig.
- The removed Twig 1 tags `spaceless` and `filter` fail, which is correct for Twig 3.
- The doc lines are accurate. The `dump()` warning in the gotchas row agrees with the established fact about OC 4.0.2.x.

### `rebuild-stores.sh`

- The argument check runs before the git step, so a bad store name stops the script before network use.
- `${ORIG_ARGS[@]+"${ORIG_ARGS[@]}"}` is the correct guard for an empty array with `set -u` on bash 3.2. The script already uses the same form for `still`.
- The restart uses the absolute `$SCRIPT_DIR`, not `$0`. The probe showed a correct restart for a relative call from `docker/`, with all arguments.
- No loop is possible. `REBUILD_STORES_PULLED=1` skips the full git block in the second run.
- `--ff-only` never makes a merge commit. The probe showed a clean stop for diverged branches (exit 128, no tree change).
- The script does not stop where a stop gives no value. The probe showed a skipped pull, then a normal run, for these conditions: no remote, a detached HEAD, and an upstream branch that the remote deleted.
- A dirty tree that does not touch the pulled files pulls correctly and keeps the local changes.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

**m1. `docker/rebuild-stores.sh:52` — a fetch failure stops the script, and the skip is not documented.**
The probe showed exit 128 when the remote is not reachable. An offline run, or a VPN or credential problem, thus prevents a rebuild of stores that need no new code. `REBUILD_STORES_PULLED=1 docker/rebuild-stores.sh ...` skips the git step (the probe confirmed it), but only the code comment shows this.

Is "stop on any git failure" the correct default? Yes, for this tool. The gotchas row exists because a store run silently tested old code. A warning in a long log is easy to miss, and a stop is not. The stop is also free of side effects, because it occurs before the build and before the stores are recreated. But a default stop needs a visible skip. Add one line to `docker/README.md` and to the header comment that names `REBUILD_STORES_PULLED=1` as the skip. A `-n` (no pull) option is an alternative.

**m2. `docker/rebuild-stores.sh:65-66` — the "bump it first" comment now leads to a pull failure.**
Each pushed commit of this branch changes `_version.txt`. If the user bumps `_version.txt` on the host, as the comment tells them, the next pull stops. The probe showed: "Your local changes to the following files would be overwritten by merge: _version.txt", exit 1. The git message is clear, so the user can recover with `git checkout _version.txt`. But the comment is now out of date: the pull brings the version with the code. Change the comment to say that the pulled `_version.txt` is used and that a local change to it can stop the pull.

**m3. `docker/rebuild-stores.sh:56` — the message is wrong for a detached HEAD.**
On a detached HEAD, the script prints "== updating the checkout (HEAD)" and then "The current branch has no upstream branch". No current branch exists. The fetch runs, but the stores get the old commit. The behavior is acceptable. A more accurate text is "No upstream branch (or a detached HEAD). Skipping git pull."

**m4. `docker/rebuild-stores.sh:50-51` — a folder that is not a git checkout stops the script.**
The probe showed two "fatal: not a git repository" lines and "== updating the checkout ()", then exit 128. Before this commit, the script worked from an unpacked archive. This is a small regression for a developer tool. If you want to keep that use, skip the git block when `git -C "$REPO_ROOT" rev-parse --git-dir` fails.

**m5. `tests/test_oc4_twig_filters.php:19-20` — the "Limits" comment does not list the syntax that only Twig v3.18.0 accepts.**
The test checks names only. The probe confirmed that these inputs pass the test, and the Twig sources confirm that v3.4.2 does not have them:

- The `has some` and `has every` operators (not in the v3.4.2 `CoreExtension::getOperators`)
- The `xor` operator (same)
- The `...` spread operator (not in the v3.4.2 lexer)
- A named argument with a colon, such as `slice(start: 1)` (v3.4.2 accepts only `=`)

Each of these breaks the OC 4.0.2.x page with the same "Could not load template" error. The doc lines claim only "filter, test, function or tag", so they are not wrong. Add these four items to the "Limits" comment. Optionally, add a check for them in `unknownInExpression()`: the string literals are already removed there, so `\bxor\b`, `\bhas\s+(some|every)\b` and `\.\.\.` are safe patterns.

**m6. `tests/test_oc4_twig_filters.php:132` — two edge cases of the function pattern.**
- False negative: `{{ 1..enum("X") }}` passes. The lookbehind refuses a name after `.`, and the range operator `..` ends with `.`. A fix is the lookbehind `(?<![A-Za-z0-9_$])(?<!(?<!\.)\.)`, which refuses a name only after a single dot.
- False positive: `{{ a . b() }}` reports `function b`. Twig permits spaces around the dot. This style is rare, and the failure is safe.

Neither case is plausible in the module template today, which uses only `if`, `for`, `else`, `|e`, `|raw` and `|replace`.

**m7. `tests/test_oc4_twig_filters.php:159-160, 179-180, 228` — an unclosed construct passes without a message.**
An unclosed comment, block, string or `verbatim` block makes the scanner go to the end of the file. The probe showed that a bad filter after an unclosed `{#` passes. Twig rejects each of these inputs, so the page breaks. This is a structure error, not a name error, and the settled decision is a static name test. Thus the minimum fix is one more "Limits" line. A stronger fix is cheap: return a `syntax unclosed ...` entry when `strpos` finds no `#}`, when `readCode()` gets to `$len` without `$close`, or when no `endverbatim` is found.

### Not verified

- I did not run the script on bash 3.2 or on macOS. The container has bash 5.2.15 and git 2.39.5. I read the added lines for bash 3.2 problems and found none: the lines use only arrays, `[ ]`, `${var:-}`, the `${a[@]+...}` guard and `VAR=1 exec`.
- I did not run Twig, as the brief instructs. The list check is a source comparison.
- If the pull renames or removes `docker/rebuild-stores.sh`, the `exec` fails with exit 127 (the probe showed it). I do not report this as an issue, because a second manual run uses the new name.

## Assessment

**Ready to merge?** Yes.

The allow lists agree with both Twig sources, the scanner gave no PHP warning and no plausible false positive, and the git step stops only where a stop protects the user from a test of old code. All findings are Minor. m1 and m2 are documentation lines that prevent a confused user on the host, so they give the most value for the least work.
