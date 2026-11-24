# 98-oc3-sed-header-twig review

Scope: `git diff HEAD` (`build.sh`, `CLAUDE.md`, `_version.txt`) and the untracked `tests/test_oc30_ocmod_xml.php`. Requirements: `98-oc3-sed-header-twig_plan.md`.

Probe: `docs/tasks/98-oc3-sed-header-twig/tmp/probe_check_oc30_xml.sh`. It extracts `check_oc30_xml` from `build.sh` and runs it on good and broken copies of the XML in a `mktemp -d` folder.

## Strengths

- `build.sh:189-190`: the new patterns `'<[?]php echo [$]'` and `'; [?]>'` use only POSIX BRE bracket expressions. In a bracket expression, `?` and `$` are literal characters in GNU `sed` and in BSD `sed`. POSIX BRE gives `?` no special meaning, and BSD `sed` has no GNU extensions. The `|` delimiter is safe because the pattern and the replacement (`{{ `, ` }}`) contain no `|`, `&` or `\`. The `${search}` value is not expanded again inside the double quotes of `file_replace`. GNU `sed --posix` on this host also gives `{{ x }}`.
- `check_oc30_xml` (`build.sh:35-49`) checks the two required Twig lines and the absence of PHP tags. The probe shows that it fails (exit 1) on the issue #98 text that the old patterns make, and on a stray `?>`. It gives no false positive on the real XML. `tail -n +2` skips only the `<?xml ... ?>` declaration. The `?v={client_version}` URL on line 51 does not match `<[?]` or `[?]>`.
- The check cannot be skipped. It runs directly after the rewrite and before the zip step, and `exit 1` stops the build under `set -euo pipefail`. `build.release.sh` calls `build.sh`, so the release path also runs it.
- The test reads the real `file_replace` function and the real OC 3.0 lines from `build.sh`, so it cannot drift from the build. The test also checks that the `check_oc30_xml` call stays in `build.sh`. The `count >= 3` check fails if somebody moves the lines to a variable form. The test passes (11 of 11) on this host.
- I checked all other `file_replace` lines in `build.sh` for `\?`, `\+`, `\|`, `\<`, `\>` and `\n`. No line uses them. The remaining escapes are `\$`, `\[`, `\&` and `\\`. GNU `sed` and BSD `sed` read these escapes in the same way. Line 360 has a plain `+`, which is literal in BRE on both. The unescaped `.` in `'.tpl'` and in other patterns matches any character, but no unwanted match exists in the current files.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. `build.sh:43` and `build.sh:45`: `tail -n +2 "$xml" | grep -qE ...` under `set -o pipefail` can give a false negative. If `grep -q` finds a match and exits while `tail` still writes, `tail` gets SIGPIPE (status 141). The pipeline then has a non-zero status, and the `if` sees "no match". The probe shows this on a copy with a stray `?>` and 20000 padding lines ("big bad: PASSED (false negative)"). The real XML is 4.3 KB, which is smaller than the pipe buffer, so the current build is safe. To remove the latent risk, use one command with no pipe, for example `awk 'NR > 1 && /<[?]|[?]>/ { print NR": "$0; found = 1 } END { exit !found }' "$xml"`.
2. `build.sh:45`: the error output prints line numbers of the `tail` output, so each number is one less than the real line number. The probe reports `69:` for a tag on line 70. The awk form in item 1 also fixes this.
3. `tests/test_oc30_ocmod_xml.php`: the test checks that the `check_oc30_xml` call exists, but it does not run the function. A negative case would prove that the guard fails on the issue #98 text. Extract the function with the same regex as `file_replace`, run it on a copy that has `<?{{ client_translate_expert_head_html; ?>` and expect a non-zero exit code.
4. `tests/test_oc30_ocmod_xml.php:47-48`: the test does not check the result of `mkdir`, and a fatal error before the cleanup leaves the folder. The risk is low because the name has the PID. `tempnam()` plus `unlink()` and `mkdir()`, or a `register_shutdown_function` cleanup, makes it more robust. The line `OSTYPE="${OSTYPE:-linux-gnu}"` has no effect because `bash` always sets `OSTYPE`. It does no harm under `set -u`.
5. `CLAUDE.md:156`: the last sentence puts the reason before the instruction and joins them with "so". It also does not name `[$]`. STE rule 7.2 and 7.3: give the instruction first, then the reason. Suggested text: "Use `[?]` and `[$]` in `sed` patterns. GNU `sed` and BSD `sed` read `\?` in a different way." The other new sentences obey the length and voice rules.
6. `.claude/agents/ote-build-test.md:74-85` and the test table in `.claude/project-workflows.md:50-63` do not list the new test. The build-test agent thus does not run it. Other recent tests (settings cards, donation info, language code map) are also missing from the agent list, so this gap is not new. Add the new test to both lists.

## Assessment

**Ready to merge?** Yes, with optional fixes. The new patterns are portable POSIX BRE, the build guard catches the issue #98 output and has no false positive on the real XML, and the regression test runs the real build lines. The minor items are latent robustness and documentation polish, and none of them affects the current package.
