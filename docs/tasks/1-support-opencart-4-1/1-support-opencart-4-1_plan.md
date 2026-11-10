# 1-support-opencart-4-1

## Settings
- Planning model: Opus 5.5 (session model, inline)
- Review model: Sonnet 5.5 (`ote-code-reviewer` dispatched with `model: sonnet`, so the reviewer differs from the planner)
- **Standing auto-approve: ON.** The user said "select as approved all options which you marked as recommended". Decide review findings and recommended options without asking, and report each decision with its reasoning. Changes to the agreed approach, external-system risk or scope expansion still go back to the user.
- Build/tests: run per the workflow. The workflow does not commit, push or open PRs on its own. The user explicitly asked for the handoff commit + push on 2026-11-09.

## Status: handoff (2026-11-09)
The session was paused in **Phase 0** while the user rebuilds the sandbox container, because the old one had **no PHP interpreter**.
- Done: issue fetched, branch `feat/1-support-opencart-4-1` created from `origin/main` (version `04.01.0105`), and this task folder.
- Sanity check (`ote-build-test`, checks only) was **BLOCKED**: there was no `php` binary, so `php -l` and the unit tests did not run. The grep-based PHP 5.6 check was clean. The only PHP 7+ syntax is the controller's OC4 marker block (lines 774–807), which is allowed.
- The investigation (Explore) **finished before the pause**. Its findings are under "Investigation results" below, so do **not** re-run it. Only re-clone the OC sources (step 2) if you need to check something specific.

### Investigation results (2026-11-09, OC 4.0.2.3 vs 4.1.0.4 source diff)
**Bottom line:** no OpenCart 4.1 API change breaks the OC4 package. The same engine, events, installer, setting/event/language/user_group models, request/url/db/log/language libraries, DB schema and admin assets apply (BS 5.3.1 → 5.3.8, same jQuery 3.7.1/FA/CKEditor). The handler signature `(&$route,&$args,&$output)` is unchanged, and 4.1 ignores handler return values (ours return void). **One package can serve 4.0.2.x and 4.1.0.x**: every version gate is `version_compare(VERSION,'4.0','>=')`, so 4.1 takes the 4.0 branches. No runtime 4.1 gating is needed.

The real risks are PHP-level. 4.1 requires **PHP 8.1+** (✅ checked at `oc-4.1.0.4/upload/system/startup.php:6`), so most installs run 8.2+. The 4.1 error handler handles E_DEPRECATED with `error_display=true` by default, so a deprecation echoed into an AJAX response corrupts the JSON.

Concrete fixes, all in `sources/` and safe on PHP 5.6 / every package (each ✅ confirmed by grep in the main session):
1. `"${c1}"` interpolation is deprecated in PHP 8.2. Change it to `{$c1}` at `sources/upload/system/library/client_translate_expert.php:199,212`.
2. `ini_set('error_reporting', E_ALL)` / `display_errors` / `display_startup_errors` at `sources/upload/system/library/client_translate_expert_core.php:3-5` force every deprecation onto the screen for the whole admin request. Remove them (all versions).
3. **Existing 4.0 bug:** `DateTime::createFromFormat` is unqualified at `client_translate_expert_core.php:836-837`. In the namespaced OC4 package this is a fatal "Class …\Library\DateTime not found" when debug logging is on. Use `\DateTime` in the source (PHP 5.3+).
4. Not yet audited: PHP 8.1 null-to-internal-function deprecations in the libraries. Do a quick pass.

Packaging/docs:
- Rename the `oc4.0` artifact to `oc4.x` (recommended) at `build.sh:40,419` and `build.release.sh:15,19,21`.
- Update README (L12, L30) and CLAUDE.md (L23) to "OC 4.0.2.x–4.1.0.x; PHP 8.0+ for 4.0, 8.1+ for 4.1".
- Optional: add `description` to install.json (`build.sh` ~L409). 4.1 displays it, and 4.0 ignores it (inferred).

Design corrections:
- The OC4 installer never executes `install.php`, but the package ships it at `extension/client_translate_expert/install.php` (build.sh ~L203,345). The controller's `install()` already does that work. Recommended: drop the file from the OC4 package, because a direct browser hit causes a fatal.
- 4.1 caches controller instances in the registry, so `index()` and the header events can share an object. Don't rely on per-request controller state.

Inferred, not verified: that the compile-time `${}` deprecation reaches OC's handler, that `DIR_LOGS`/`HTTP_CATALOG` are still defined, and any runtime behaviour, since nothing was executed. Assumption to record: support starts at 4.0.2.x. 4.0.0.x/4.0.1.x were not assessed.

### Resume checklist (new session)
1. `command -v php && php -v` must succeed. Then re-run the sanity check (`ote-build-test`, "checks only").
2. Re-create the OpenCart reference sources in `docs/tasks/1-support-opencart-4-1/tmp/oc/` (gitignored):
   ```bash
   for t in 4.0.2.3 4.1.0.4; do
     git clone -q --depth 1 --branch $t --filter=blob:none --sparse https://github.com/opencart/opencart.git oc-$t
     (cd oc-$t && git sparse-checkout set --no-cone /upload/admin/ /upload/system/engine/ /upload/system/library/ \
       /upload/system/framework.php /upload/system/startup.php /upload/system/config/ /upload/system/helper/ \
       /upload/install/ /upload/extension/opencart/admin/ /upload/catalog/controller/startup/)
   done
   ```
   The latest tags at handoff were 4.0.x → `4.0.2.3` and 4.1.x → `4.1.0.0`–`4.1.0.4`.
3. ~~Re-dispatch the Explore investigation~~. Already done, see "Investigation results".
4. Write the Context/Approach/Assumptions/Steps sections, then continue from Phase 1 under auto-approve.

### Investigation brief (to re-use)
For every OpenCart core API or convention the OC4 package uses (`build.sh` OC4 section ~L150–400, plus the controller's `OC4_EVENT` block), diff the behaviour between 4.0.2.3 and 4.1.0.4:
- the setting/event/extension/language/user_group model methods and signatures (`editValue`, `addEvent`, `deleteEventByCode`, `getLanguages` keys incl. `image`)
- the event trigger/action format and handler signature
- the extension installer flow (install.json, `install()`/`uninstall()`, permissions)
- the loader/request/response/url/user/config/Log/DB APIs, `DIR_EXTENSION`
- the admin layout and assets (Bootstrap, jQuery, FontAwesome versions; `#alert`; `data-oc-toggle`) used by `.oc4.twig` and the JS
- the DB schema for the queried tables (`oc_language`, `oc_setting`)
- the PHP minimum and PHP 8.2+ deprecations (dynamic properties, `${}` interpolation, null passed to string functions)
- hardcoded `4.0` version checks and package naming (`oc4.0`)

Deliverables: a breakage table with path:line evidence from the 4.1 tree, and a recommendation on whether one OC4 package can cover 4.0 and 4.1 (runtime `version_compare`) or 4.1 needs its own build variant.

## Update 2026-11-09 — resumed in the new container
PHP 8.2.35 and `zip` are now available. Sanity check (checks only): **PASS**. `php -l` is clean on 10/10 files, and the tests show 24 passed, 0 failed, 6 skipped (integration, no key).

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/1, "Support Opencart 4.1.*" (empty body).
The OC 4.0 package (built from the OC 3.0 variant in `build.sh` ~L150–425) already uses only APIs that are unchanged in 4.1.0.x (see "Investigation results"). What 4.1 changes is the platform: it requires PHP 8.1+ (so in practice 8.2+), and it reports E_DEPRECATED on screen by default, which corrupts JSON AJAX responses. Key files:
- `sources/upload/system/library/client_translate_expert.php:199,212`: `"${c1}"` interpolation
- `sources/upload/system/library/client_translate_expert_core.php:3-5`: global `ini_set` of error_reporting/display_errors
- `sources/upload/system/library/client_translate_expert_core.php:836-837`: unqualified `DateTime` (fatal in the namespaced OC4 package)
- `build.sh:40,199-203,405-420`, `build.release.sh:15,19,21`, `.gitignore:11`: install.php, install.json, `oc4.0` naming
- `README.md:12,30`, `CLAUDE.md:23`, `.claude/project-workflows.md:56`, `.claude/agents/ote-build-test.md:64-72`: docs naming 4.0

Affected OC versions: the source fixes apply to every package (1.5–4.x) and stay PHP 5.6 safe. The packaging changes apply to the OC4 package only.

## Approach
Keep **one OC4 package for 4.0.2.x–4.1.0.x** (no new build variant, no runtime 4.1 gating) and make it PHP 8.2-clean:
1. `${c1}` → `{$c1}`.
2. Remove the forced `ini_set` error display from the core library.
3. `DateTime` → `\DateTime`.
4. Rename the package folder `oc4.0` → `oc4.x` everywhere (build scripts, .gitignore, docs and agent docs). The build scripts also clean up any leftover `oc4.0` folders.
5. `install.json`: add `description`, which 4.1 shows and 4.0 ignores, and point `link` at the GitHub repo. `translator.codeguild.com.ua` was removed elsewhere in #12, so this is a small consistency fix.
6. Stop shipping `install.php` in the OC4 package: neither installer runs it, and the controller's `install()` does that work.
7. Update the docs (README, CLAUDE.md) with the supported range and PHP requirements.
Design correction vs the issue title: OpenCart 4.1 itself needs no code changes. The work is PHP 8.2 hygiene plus packaging and docs.

## Assumptions
- ✅ The OC 4.1.0.4 APIs our package uses are identical to 4.0.2.3. Evidence: the Explore source diff (see "Investigation results").
- ✅ 4.1 requires PHP 8.1+: `oc-4.1.0.4/upload/system/startup.php:6`.
- ✅ `"${var}"` emits a deprecation on PHP 8.2: `php -d error_reporting=-1 -l` on client_translate_expert.php prints "Using ${var} in strings is deprecated" for lines 199 and 212.
- ✅ The 4.1 installer defaults missing install.json keys (incl. `description`) to `''` (`installer.php:313-327`) and shows `description` (L138, L345). 4.0.2.3 reads only name/version/author/link (L107-117, L245-281), so an extra key is harmless.
- ✅ Neither installer references `install.php`: `grep -rn install.php upload/admin/controller/` is empty in both trees.
- ❓ Whether OC's error handler shows the compile-time `${}` deprecation during the AJAX request. Not runnable here (no OC instance). If it doesn't, fix 1 is still correct hygiene, because the warning would be lost rather than displayed.
- ❓ PHP 8.1 "null passed to internal function" deprecations in the libraries. A static audit can't tell without runtime data. Deferred, and noted in Done as a follow-up.
- ❓ OC 4.0.0.x/4.0.1.x support was not assessed. The docs will state 4.0.2.x+.

## Steps
- [x] Phase 0: PHP available + sanity check, investigation, verify 4.1 behaviour against the OC source
- [x] Plan approval (auto-approve)
- [x] Code: `${c1}`→`{$c1}`, remove `ini_set` block, `\DateTime`
  Findings: no other `ini_set`/`error_reporting(` calls exist in sources/ or tools/. The `${` form appeared only at L199/L212. The sanity check's `php -l` missed the deprecation because CLI default error_reporting hides E_DEPRECATED, so lint with `-d error_reporting=-1`.
- [x] Build scripts: `oc4.0`→`oc4.x` (build.sh, build.release.sh, .gitignore), install.json description + GitHub link, drop install.php from the OC4 package
  Findings: also removed the now-dead `OC4_INSTALL` variable and its `file_replace`. Both scripts' pre-build cleanup also removes legacy `oc4.0-v*/` folders.
- [x] Docs: README, CLAUDE.md, project-workflows.md, ote-build-test.md
  Findings: CLAUDE.md's "Website: translator.codeguild.com.ua" line was left as-is (out of scope).
- [x] Bump version + build + test (+ confirm the OC4 package has no `${`, no install.php, and install.json has description)
  Findings: 04.01.0105 → **04.01.0106**, build PASS, tests 24 passed / 0 failed / 6 skipped (integration, no key). A strict lint (`error_reporting=-1`) shows 0 Deprecated. The oc4.x package has no install.php, valid install.json with description + GitHub link, no `${`/bare `DateTime::`/`ini_set`. oc1.5/oc2.2 still ship install.php. No regression test was added: the changed library code (`client_translate_expert*.php`) has no test harness; the strict lint is the regression guard for the `${}` deprecation.
- [x] Independent review (Sonnet 5.5) → 1-support-opencart-4-1-review.md
  Findings: verdict ready to merge, 0 critical/important, 5 minor.
- [x] Address review findings (auto-approve)
  Findings: FIXED: doubled blank line in build.sh, re-added the legacy `oc4.0` .gitignore entry, PHP 8.x in the CLAUDE.md support line. SKIPPED: softening the 4.1 support claim (it is the issue's deliverable and is backed by the source diff; a live smoke test is recorded as a follow-up). DEFERRED to improvements: an automated strict-lint guard (needs an ote-build-test agent edit).
- [x] Bump version + build + tests (verify loop)
  Findings: **04.01.0107**, build PASS, 24 passed / 0 failed / 6 skipped, strict lint 0 Deprecated, oc4.x has no install.php, oc1.5–3.0 keep it.
- [x] Re-review? No (auto-approve): the fixes were whitespace/docs only, and the first pass was already at wording level.
- [x] Improvements pass → 1-support-opencart-4-1-improvements.md

## Done
- **Landed:** one OC4 package (`oc4.x`) for OpenCart 4.0.2.x–4.1.0.x. PHP 8.2 fixes in the libraries (`${}` → `{$}`, removed the forced `ini_set` error display, `\DateTime`, which also fixes a latent fatal on the 4.0 debug path). install.json gets a description + GitHub link. install.php is dropped from the OC4 package. Docs updated.
- **Final version:** ~~04.01.0107~~ → ~~04.01.0111~~ → **04.01.0113** after the second rebase onto main (`releases/v04.01.0113/`, gitignored). Build green, 24/0/6 tests.
- **Also landed (after the rebase):** an `oc41` Docker test store (OC 4.1.0.4 / PHP 8.2), and `install-module.sh` support for oc41 and the `oc4.x` folder. Not run here (no Docker in the sandbox).
- **Gotcha:** `php -l` hides E_DEPRECATED unless run with `-d error_reporting=-1`. OC 4.1 displays deprecations by default.
- **Follow-ups:** (1) a live smoke test on an OC 4.1.0.x / PHP 8.2+ store (install, settings save, a translate AJAX call); (2) an audit of PHP 8.1 null-to-internal-function deprecations in the libraries; (3) the proposed workflow edits in the improvements file.


## Update 2026-11-09 (2): rebase onto main + Docker 4.1 store
The user asked to rebase onto `origin/main` and extend the files from the new commits to cover OC 4.1, then push under auto-approve.
- Rebased onto `3743d6b` (#17 Docker test stores, #18 OCMOD header casts). The only conflict was `_version.txt`; I kept main's `04.01.0110`, so the next build is `0111`.
- #18 (cast header values) touches the OCMOD XML only, which applies to OC ≤ 3.0. The OC4 header event (`eventHeaderViewAfter`) already uses `(int)` casts and a `'{}'` fallback, so no 4.x change is needed.
- [x] Docker: added an `oc41` store (OpenCart 4.1.0.4, `php:8.2-apache`, port 8041, DB `oc41`) to `docker/compose.yaml` and `docker/db/init.sql`. `install-module.sh` treats `oc41` like `oc40` (`is_oc4` helper) and finds the package as `*-oc4.[0x]-*`, so pre-rename builds still work. Docs: `docker/README.md`, README.
  Findings: ✅ the 4.1.0.4 release zip SHA-256 is `cdef537f…18c0` (downloaded and hashed). It unpacks to `upload/` (4.0.2.3 used `opencart-4.0.2.3/upload`). ✅ The 4.1 `cli_install.php` takes the same options as 4.0, and its checks (PHP 8.1+, mysqli, gd, curl, openssl, zlib, zip) are covered by `php-setup.sh`'s non-5.6 branch. ✅ The 4.1 admin login (`common/login.login` + `login_token` → JSON `redirect`), `marketplace/installer.upload` (adds an `oc_extension_install` row with code/status), `installer.install` (`next` chaining, `editStatus`) and `extension/module.install` (`extension`/`code`) match what the script drives for 4.0. ❓ The stores were not started here (no Docker in the sandbox), so the oc41 store and the script are unrun. `init.sql` only runs on a fresh volume, and the docs give the one-line manual DB creation.
- [x] Bump version + build + test
  Findings: 04.01.0110 → **04.01.0111**, build PASS, 24/0/6, strict lint 0 Deprecated. oc2.3/oc3.0 OCMOD keep #18's `(int)` casts. `bash -n`/`sh -n` pass on the docker scripts. The `*-oc4.[0x]-*` glob matches exactly one package.
- [x] Targeted review v2 (Sonnet 5.5) → 1-support-opencart-4-1-review-v2.md
  Findings: ready to merge, 0 critical/important. All 3 minor items FIXED (auto-approve): named the hang symptom in the old-volume note, "Older builds" wording, banner width. No v3: the fixes were comment/doc-only, and both passes were at wording level.
- [x] Commit + push (the user asked; `--force-with-lease`, since the rebase rewrote the 2 pushed plan commits)

## Update 2026-11-10: second rebase onto main (#20 uk-ua Docker language)
- Rebased onto `a3e2df7` (#20: entrypoint adds an enabled `uk-ua` row, smoke check 4 for ≥2 languages, new `docker/rebuild-stores.sh`).
- Conflicts: `_version.txt` (kept main's 04.01.0112), `docker/README.md` (kept main's rebuild-stores section and check 4, plus my `OC41_PORT` and 4.x wording), `docker/install-module.sh` (main's `ukua` local + my `is_oc4`).
- Ported to 4.1: `rebuild-stores.sh` `ALL_STORES` now includes `oc41`, and the README says "all four stores" and "4.x (4.0, 4.1) are not affected".
- ✅ 4.1.0.4 `oc_language` columns (`db_schema.php:3578`) are name/code/locale/extension/sort_order/status with no `directory`, so the entrypoint's OC 4.x insert branch applies unchanged. ✅ The 4.1 `catalog/controller/startup/language.php` does not read Accept-Language, so the "4.x not affected" note holds.
- [x] Bump version + build + test → 04.01.0113 (PASS, 24/0/6, strict lint 0 Deprecated, entrypoint PHP heredoc lints clean, no conflict markers)
- [x] Force-push (with lease)
