# Review v2: Docker test environment (#15), fix pass

Scope: the code changed after the first review, mainly `docker/install-module.sh`, plus the `127.0.0.1` port binding in `docker/compose.yaml` and the `docker/README.md` updates. Settled design decisions and the facts measured against the live stores are taken as given. `bash -n docker/install-module.sh` passes.

### Strengths

- **The `set -e` gap is closed consistently.** The comment at `install-module.sh:103-105` explains why the guards are needed. Every command in `login`, `refresh_modifications`, `install_ocmod_store`, `install_oc4_store` and `smoke_check` that can fail (`unzip`, `docker cp`, `docker exec`, every `http`, every `sql`) now has its own `|| { fail ...; return 1; }`. `local` is declared on a separate line from each `x="$(...)" || ...` assignment, so the guard sees the command's exit status and not the status of `local`. That detail is easy to get wrong, and it is right everywhere here.
- **`module_rows` / `module_installed` fail loudly.** A failed query no longer reads as "installed". `module_installed` returns 2 on a query error and does a numeric test otherwise. `install_ocmod_store:169` and `install_oc4_store:193` fetch the count with their own guard, so the branch decision never runs on empty output.
- **The OC4 state machine is correct.** The three states map onto the installer's real behaviour:
  - full install: there is no `oc_extension_install` row;
  - resume: the row exists, but there is no `oc_extension` row;
  - update: both rows exist.

  The `status = 1` skip avoids `error_directory_exists` on a resumed run. The update path clears the folder before copying, so files removed in a newer build don't linger.
- **The non-JSON guard (`:235-238`) points at the right step.** It also truncates the body to 300 bytes. On OC4, where `display_errors` is on, it has a useful side effect: a PHP warning printed ahead of the JSON now stops the install loudly instead of being parsed past.
- **Smoke check 1** now keys on a marker that only the settings form outputs, and the comment says why. Check 3's new `oc_extension` and `oc_event` conditions make it real evidence, even after a recreate.
- **`smoke_check || continue`** together with `--connect-timeout` / `--max-time` means one dead or hung store can no longer abort or block an `all` run. The measured `OC23_PORT=9999` run confirms this.
- **compose and README.** The `127.0.0.1` port bindings are correct. The README now describes checks 1 and 3 accurately, says the stores are bound to localhost, and explains the `--force-recreate` needed after a port change.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. **PHP errors raised by the module's `install()` are still discarded.** The module's `install()` creates the table and, on 4.0, registers the events. It is the most PHP-version-sensitive code the script runs, and it runs only once, inside the Extensions > Modules > Install request. That request's body is never scanned for PHP errors:
   - On 2.3/3.0, `install-module.sh:173` sends it with `-o /dev/null`. In both versions `extension/extension/module/install` calls `install()` in-process and renders the extension list. Any `Warning` / `Notice` / `Deprecated` printed there goes to `/dev/null`.
   - On 4.0, `install-module.sh:246-250` only extracts `"error"`. A warning printed before the JSON leaves `err` empty. The `oc_extension` row is written before `install()` runs, so `module_installed` passes.

   Smoke check 1 renders the settings page, not the install path, so it does not see these errors either. A PHP 5.6 or 8.1 notice or deprecation in `install()` therefore ends in "module installed" plus 9/9 PASS. Fatal errors are caught indirectly, by check 3 (table or events missing). Non-fatal ones are not caught at all.

   Fix: capture the body (`resp="$(http "$(admin_url ...)")" || ...`) on both paths and run `php_errors` on it, the same way check 1 does. On OC4 the same `case "$resp" in "{"*)` guard used in the installer loop would also work.

#### Minor (nice to have)

2. **Smoke check 2 passes on the login page** (`install-module.sh:285-290`). The I1 reasoning applies here too: the header injection puts `client_translate_expert.js` on the login, permission and not-found pages. If the session were lost between checks 1 and 2, the dashboard request would land on the login page and check 2 would still print PASS. In practice check 1 has already failed in that case, so the run still exits 1. This is not a silent pass for the run, only a misleading PASS line. Fix: also require a dashboard-only marker, or require that the page is not the login form (for example, no `name="password"`).

3. **Double and misleading messages when `module_installed` hits a DB error** (`:153`, `:175`, `:250`). `module_installed` already prints `ERROR: database query failed`. The caller then prints a second error, such as "Extensions > Modules > Install did not register the module". That second message is wrong when the real cause is the query. Fix: drop the caller's `fail` when the status is 2, or have `module_installed` print nothing and let the caller report.

4. **The table check in check 3 has no query guard** (`:302`). If the `SHOW TABLES` query fails, it reports "table ... is missing" instead of "database query failed". This still fails loudly, so only the message is wrong. For consistency, fetch the result into a variable with the same `|| { fail "database query failed"; return 1; }` used for `n` and `events`.

5. **Unbounded response dumps in two error messages** (`:216`, `:250`). `$resp` is printed in full. On a PHP fatal it is a whole HTML page. Use `head -c 300` as the installer loop does at `:237`.

6. **The OC4 update path bypasses `oc_extension_path`.** OC4 records extracted files in `oc_extension_path`, and Installer > Uninstall uses that table to remove them. Files that a newer build adds through the update path are not recorded, so a later manual uninstall in the test store would leave them behind. This is harmless for the scripted flow, which never uninstalls. Consider one line in the README re-run paragraph ("for testing uninstall, recreate the store first").

7. **A duplicate `oc_extension_install` row breaks `install_id`.** That can happen if someone also uploads the package by hand through the installer. `install_id` (`:183`) then returns two lines, and `$id` is used in SQL and in a URL. The result is a confusing failure rather than a clear one. Fix: add `ORDER BY extension_install_id DESC LIMIT 1`.

No regressions found in `compose.yaml` or `README.md`.

### Assessment

**Ready to merge?** With fixes. The fix pass is sound: every first-review finding it targeted is addressed, and no change introduced a regression or a new silent pass. The remaining gap is pre-existing. PHP warnings, notices and deprecations from the module's `install()` are discarded on all three stores, which undercuts the environment's purpose of catching version-specific PHP errors. It is a small change to scan that response with `php_errors`. The minor items are message quality and robustness only.
