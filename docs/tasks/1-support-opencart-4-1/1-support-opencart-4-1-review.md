# Review: GitHub issue #1, Support OpenCart 4.1.*

Scope: `git diff HEAD` on feat/1-support-opencart-4-1 (build.sh, build.release.sh, .gitignore, the two libraries, docs). I also inspected the built release 04.01.0106.

### Strengths
- The change is small and matches the plan. Source fixes (`{$c1}`, `\DateTime`) are PHP 5.6 safe and apply to every package.
- `"${c1}"` to `"{$c1}"` fixes exactly the two E_DEPRECATED sites. No other `${` interpolation or `ini_set` remains in `sources/` or `tests/`.
- `\DateTime` is the right fix. The OC4 build prepends a namespace to the core library, so the bare `DateTime` would have been a fatal in the debug logger. I checked the other namespaced libraries for unqualified global classes. Only `Exception` in `vh_google_translator.php` (already handled by `file_replace`) and `Log` (already rewritten) occur.
- Removing the `ini_set` block is correct and safe.
  - Nothing reads it back. `_debug` only gates `$_debugLog` writes, so debug logging is unaffected.
  - The block forced `display_errors=1` and `E_ALL` on every admin request, in all packages. That is a production information-leak and JSON-corruption risk, and OpenCart sets its own error policy.
  - Side effect: stores with `display_errors` off no longer see fatals on screen. That is the intended behaviour.
- In build.sh, dropping `OC4_INSTALL` and its `file_replace` is complete. `grep` finds no remaining reference. The `rm -f sources_release_40/install.php` is safe under `set -euo pipefail`, because `-f` never fails on a missing file. The old `mv ... || true` was the fragile part.
- Built output confirms the requirements. The oc4.x zip has `install.json` and no `install.php`. The oc3.0 zip keeps `install.php`. The oc1.5 and oc2.2 packages keep it too, per the plan notes.
- The OC4 install path does not need `install.php`. The controller's `install()` calls the model, which calls the library's `install()` to create `translate_expert_same_translations`, then registers the events. The old OC4 `install.php` never ran, so nothing is lost.
- Cleanup globs are correct.
  - `build.sh` removes both `oc4.x-v*/` and legacy `oc4.0-v*/` outside release mode.
  - `build.release.sh` does the same before building.
  - The final zip glob (`oc4.x-*/`) and the post-zip `rm -rf` match what `build.sh` creates (`oc4.x-v${VERSION}-${LOCAL_DATE}`).
- The per-request OC4 event handlers (`eventHeaderControllerAfter` and `eventHeaderViewAfter`, which run on every admin page) are deprecation-clean on 8.2.
  - They have typed signatures.
  - `(int)` casts handle null config values.
  - `str_replace` and `strpos` operate on a string `$output`.
  - `$languagesJson` is guarded before use.

### Issues

#### Critical (must fix)
None.

#### Important (should fix)
None blocking. One caveat:
- The README and CLAUDE.md now claim "Supports OpenCart 1.5 through 4.1". Runtime behaviour on 4.1.0.x is not verified. The plan marks "OC error handler shows the `${}` deprecation during AJAX" and the PHP 8.1 null-argument deprecations as unverified or deferred. The static API comparison is the only evidence. Consider wording like "4.1.0.x (API-compatible, not yet runtime-tested)", or record the follow-up in the issue and PR, so a user who hits a null-to-internal-function deprecation on 4.1 is not surprised.

#### Minor (nice to have)
- `build.sh` around line 342-344 now leaves two consecutive blank lines where the install.php route rewrite was removed. Cosmetic.
- `.gitignore` no longer lists `/OpenCartTranslateExpertClient-oc4.0-v*/`. A legacy leftover folder from a previous aborted build would show as untracked. The scripts delete it on the next run, so this is harmless. Keeping both entries would be marginally tidier.
- `.claude/agents/ote-build-test.md` and `ote-code-reviewer.md` still say "OC 4.0" in places (for example "for OC 4.0 only", `version_compare(VERSION, '4.0', '>=')`). Those are accurate descriptions of the OC4 code path and rewrite lists, so I would leave them. `ote-code-quality-reviewer.md` still says "OC 4.0" for the Twig template, which is also accurate.
- CLAUDE.md "Supports OpenCart 1.5–4.1 across PHP 5.6/7.0/7.1+" omits 8.x, unlike the README. Cosmetic.
- No automated regression guard exists for the `${}` deprecation. The plan relies on a manual strict lint (`php -d error_reporting=-1 -l`). A one-line grep or lint step in the build-test agent doc would make it repeatable.
- `install.json` `version` still comes from `${VERSION}` and the description hardcodes "4.0.2.x-4.1.0.x". It needs updating together with the docs when the supported range changes.

### Assessment
**Ready to merge?** Yes.

The build-script edits are consistent (no dangling `install.php` or `OC4_INSTALL` references, `set -e` safe, cleanup globs correct). Removing the `ini_set` block changes no functional behaviour and removes a real production hazard. The two source fixes are PHP 5.6 compatible. The remaining notes are doc wording and cosmetics. The only real residual risk is the unverified runtime behaviour on 4.1, which the plan already flags.
