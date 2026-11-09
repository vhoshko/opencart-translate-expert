# Review: 15-docker-test-environment

Scope: `git diff HEAD` (README.md, _version.txt) plus the untracked `docker/` tree. I checked that `sources/`, `build.sh` and `build.release.sh` are unchanged (`git diff HEAD --stat` on them is empty). `bash -n` passes on `install-module.sh` and `sh -n` on both container scripts. The end-to-end results listed in the brief are taken as given.

### Strengths

- **One parameterised Dockerfile.** Build args select the PHP image, the OpenCart release and its upload dir, and every release zip is pinned by SHA-256 and checked in a separate fetch stage. That is less duplication than three Dockerfiles, and the hash pinning is real (`sha256sum -c`).
- **Stores install themselves with OpenCart's own `cli_install.php`.** The entrypoint is idempotent: it keys on `DB_DATABASE` in `config.php`, copies the config files OC4 needs first, checks after the run that `config.php` was really written, and removes `install/`.
- **The OC4 path uses the real Extension Installer flow.** It goes through upload, then the `next` chain, then `extension/module.install`. The plan explains why, since `oc_extension_install` drives OC4's autoload paths. The `next` loop has a 20-step bound and checks `error` on every step.
- **Installs are checked against the database, not just HTTP replies.** After the module install the script confirms an `oc_extension` row exists rather than trusting the HTTP response. An undiscoverable package, a missing container or a failed login all end with a visible `ERROR` and a non-zero exit.
- **`php-setup.sh` handles the archive.debian.org fallback for both failure modes** (a failing update and packages that 404 mid-move), and it drops `*-updates` only when it has to.
- **Shell care.** Here-strings replace `echo | grep -q` to avoid the SIGPIPE/pipefail false failure, the code stays bash 3.2-safe, `${2:-}` is used under `set -u`, and the temp dir is cleaned up with a trap.
- **The docs are accurate and concise.** The README covers ports, the re-run behaviour, the manual fallback and teardown semantics.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. **Smoke check 1 can pass on an error page.** The check is at `docker/install-module.sh:233`. It treats "the module rendered" as `grep -q "client_translate_expert"` on the response body. The module's own OCMOD (2.3/3.0) and header view event (4.0) add `client_translate_expert.js` / `.css` to `common/header` on every admin page, so these pages all contain the string:
   - OpenCart's `error/permission` page ("Permission Denied!" / "You do not have permission to access this page…", with no `Warning:` prefix);
   - the `error/not_found` page;
   - the login page, if the token were bad.

   None of those trips `php_errors`, so if the extension install failed to add the access permission, or the OC4 route did not resolve, check 1 would still print `PASS`. The README claim at `docker/README.md:53` ("no OpenCart `Warning:` alert such as a permission error") is also wrong for GET requests: the permission page has no `Warning:` text.

   The negative tests the brief describes only exercised the error branch. They did not test this render branch.

   Fix: match a marker that only the module form outputs, such as `id="input-client_translate_expert_key"` (present in both the `.tpl` and the `.oc4.twig`). Optionally also fail on the permission and not-found headings. Then reword README line 53.

2. **`set -e` is off inside every install step, so install failures can pass silently.** The calls are `install_oc4_store "$PKG" || continue` and `install_ocmod_store "$PKG" || continue` (lines 281 and 283), plus `login || continue` (line 271). Bash suppresses `set -e` for the whole body of a function called on the left of `||`. I confirmed this on the macOS `/bin/bash` 3.2.57. As a result, a failing `unzip`, `docker cp` or `docker exec chown` (lines 149–151, 180–182) or `curl` call is ignored, and the script goes on to log `files copied` or `files updated`.

   The update path is the dangerous one. If `docker cp` fails on a re-run, the store keeps the previous build. Smoke checks then pass against the old files and the script exits 0, which looks like a successful test of the new build. The same applies to `refresh_modifications` (line 137): it logs `modifications refreshed` without checking the curl exit status or the response.

   Fix: add explicit `|| { fail "..."; return 1; }` to `unzip`, `docker cp`, `docker exec` and the refresh `http` call. Alternatively, run each store in a subshell with `set -e` re-enabled and check its status.

3. **A half-finished OC4 install can't be recovered by re-running.** The OC4 "already installed" test (`install-module.sh:177-185`) only looks for an `oc_extension_install` row. Take a run that fails after the upload, for example on an installer `next` step error or on `extension/module.install`. It leaves that row behind, and every later run takes the "files updated" branch: it copies files and returns 0 without ever running `installer.install` or `extension/module.install`. The smoke checks then fail (better than passing), but the script cannot recover, and the README's "running again only replaces files" hides why.

   Fix: take the update branch only when `install_id` is set **and** `module_installed` is true. Otherwise either finish the remaining steps or tell the user to recreate the store.

#### Minor (nice to have)

4. **`smoke_check` can abort the whole run.** Line 231/241, `body="$(http ...)"`, runs with `set -e` active, because `smoke_check` is not called in a `||` context. If curl fails (the store's Apache is down, or a connection reset), the script exits at once with curl's exit code. There is no `[store] ERROR` line, and the remaining stores in `all` are skipped. Since `http` has no `--max-time`, a hung store blocks the run forever. Add `--max-time` (for example 60 s) to `http()` and `|| true`/explicit handling in `smoke_check`.

5. **Check 3 is weak after a container recreate, and the README doesn't say so.** The plan's own finding is that `oc_translate_expert_same_translations` survives a recreate, because OpenCart's reinstall doesn't drop it. The README (line 55) presents check 3 as evidence that the module's install worked. Either add one sentence about this or make the check stronger. One option is to drop the table before a fresh install. Another is to also assert the `oc_extension` row and, on OC4, the two `oc_event` rows, which also covers the "registers its two header events" claim at README line 43.

6. **`json_value` silently returns nothing for non-JSON replies** (line 112–114). In the OC4 `next` loop, an HTML reply (a fatal error page, or a login redirect after the session expires) yields no `error` and no `next`. The loop then ends and logs `extension files installed`. The later `module_installed` check catches the bad outcome, so this is not a silent pass, but the error points at the wrong step. A guard such as `case "$resp" in "{"*|*'{"'*) ;; *) fail "installer: non-JSON response"; return 1;; esac` would point at the right step.

7. **The OC4 update path copies the whole zip root into `extension/client_translate_expert/`** (line 181). That includes `install.json` and `install.php`, which the real installer would not place there, and it never deletes files that a newer build removed. This is harmless for current builds. A short note in the README's re-run paragraph would be enough.

8. **Store ports are published on all interfaces** (`compose.yaml:45,64,83`). The stores use `admin`/`admin` and show errors in the page. Binding to `127.0.0.1:${OC23_PORT:-8023}:80` (and the same for the others) keeps them off the LAN.

9. **The archive fallback installs with `--allow-unauthenticated`** (`php-setup.sh:31`). `Acquire::Check-Valid-Until=false` is what makes the archive usable. `--allow-unauthenticated` also accepts unsigned packages without saying so. This only affects local test images, but if the build works without the flag (stretch and bullseye archive signatures still verify against the image keyrings), drop it.

10. **Changing a port only takes effect on a fresh install.** `OC_HTTP_SERVER` is written into the store's `config.php` on first start only. Changing `OC30_PORT` on a store that is already installed publishes the new port, but the store still links to the old URL. The README port section (lines 26–31) could say "set it before the first `up`, or recreate the store".

11. **Floating base tags.** `php:7.4-apache`, `php:8.1-apache`, `alpine:3.20` and `mariadb:10.6` are minor-version tags, not digests. That is acceptable for a test rig and consistent with the SHA pinning of OpenCart itself. Mention it only if reproducibility matters later.

### Assessment

**Ready to merge?** With fixes. The setup works, and the established end-to-end runs show the install paths are right. However, two of the three smoke-check signals can report success when they shouldn't. Check 1 matches the module name, which every admin page carries in its header (issue 1). On re-runs, `set -e` suppression hides failed `docker cp`/`unzip` calls (issue 2). For a tool whose job is to say "this build works on this store", both should be fixed. Issue 3 is a small robustness fix, and the rest are optional.
