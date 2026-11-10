# 19-docker-uk-ua-language

## Settings
- Planning model: Fable 5.1 (Plan subagent). Review model: Fable 5.1. The user chose the same model for both, overriding the workflow default of using a different reviewer.
- Build: bump `_version.txt` + `./build.release.sh`, per CLAUDE.md. No module sources change, but the build still runs.
- Tests: unit tests, `sh -n`/`bash -n`, `php -l` on the PHP snippet. Live verification: the user rebuilds the stores on the host, then the DB is checked here via `host.internal`.
- No commit, push or PR from this workflow. No standing auto-approve for review findings.

## Context
GitHub issue #19. The three Docker test stores come up with only `en-gb`, so translation can't be tested without adding a language by hand in the admin after every reinstall:
- `oc23`: OC 2.3.0.2, PHP 5.6
- `oc30`: OC 3.0.3.9, PHP 7.4
- `oc40`: OC 4.0.2.3, PHP 8.1

(`docker/compose.yaml:35-86`). A store reinstalls whenever its container is recreated (`docker/README.md:87`), so the fix belongs in the first-run install path in `docker/opencart/entrypoint.sh`.

Agreed scope: one enabled `uk-ua` row in `oc_language`. No language pack, no language files, no `*_description` rows; the demo data stays as it is, so the uk-ua descriptions start out empty. The module already copes with that:
- It lists languages from the DB with no status filter.
- On OC 2.2 and later it takes the language-file folder from `code` (`sources/upload/system/library/client_translate_expert_core.php:797-806`), and it creates a missing folder (`client_localization_translate_expert.php:91-95`).
- Analysis treats missing target description rows as empty (LEFT JOIN plus `t2.col is null`, `client_translate_expert.php:535,577`), and translating inserts them (`core.php:369-447`).

`oc41` (OC 4.1) is on the unmerged branch `feat/1-support-opencart-4-1` and is out of scope here.

## Approach
- **Where:** in the first-run block of `entrypoint.sh`, after the `config.php` check (lines 32-35) and before `rm -rf install` (line 38). It runs on every reinstall, because reinstall recreates `oc_language`.
- **How:** the store image has no mysql client, so use `php -r` with `mysqli_connect(getenv(...))`, as `entrypoint.sh:10` already does. The PHP has to run on 5.6, 7.4 and 8.1, so it is procedural mysqli only. Call `mysqli_set_charset(..., 'utf8mb4')` so the Cyrillic name is stored correctly.
- **Schema detection:** `SHOW COLUMNS FROM oc_language LIKE 'directory'`.
  - A hit means the 2.3/3.0 schema. Insert `image='ua.png'`, `directory='uk-ua'` and `locale='uk-ua,uk_UA.UTF-8,uk_UA,ukrainian'`.
  - No hit means the 4.0 schema. Insert `extension=''` and `locale='uk-ua,uk'`.
  - Common values: `name='Українська'`, `code='uk-ua'`, `sort_order=2`, `status=1`.
  - Guard the insert with `WHERE NOT EXISTS (… code='uk-ua')`.
- **Failure:** PHP exits non-zero and `set -e` aborts the install loudly, the same way the `config.php` check does.
- **Smoke check:** add a new Check 4 in `smoke_check()` (`docker/install-module.sh:273-327`) using `sql()` (`:99-101`) and the existing `|| { fail …; return 1; }` convention. It asserts exactly 2 enabled languages, one of them `uk-ua`.
- **Docs:** in `docker/README.md`, add an intro line about the two languages, add item 4 to the smoke-check list, and extend the reinstall note.

## Assumptions
- ✅ The `oc_language` columns differ by version: 2.3/3.0 have `image` and `directory`, 4.0 has `extension` and neither of those. Verified from the release zips' `install/opencart.sql` and OC 4.0's `system/helper/db_schema.php:3497-3534`.
- ✅ Reinstall drops and recreates `oc_language`: 2.3/3.0 `opencart.sql` uses `DROP TABLE IF EXISTS`, and OC 4.0 does it in `cli_install.php:274`.
- ✅ The store image has PHP mysqli and no mysql CLI (`docker/opencart/php-setup.sh:39,45`).
- ✅ The module needs no language files or description rows for `uk-ua`. Checked in code, references in Context.
- ✅ `install-module.sh` is a bash script whose `sql()` runs `mariadb` inside the `db` container.
- ✅ (verified 10.11.2026, live) `mysqli_set_charset('utf8mb4')` works on PHP 5.6 mysqlnd against MariaDB 10.6: `oc23.oc_language` reads back `Українська`. Originally unverified: mysqlnd should support it. Checked in the live step by reading the name back. If it fails, use `utf8`, which still stores Cyrillic.
- ❓ The storefront or admin may log a missing-file notice when `uk-ua` is selected on 2.3/3.0, and the flag image will 404. This is cosmetic and out of scope; I'll note what the live step shows.
- ❓ Live verification depends on the user rebuilding the stores on the host, because there is no Docker CLI here and `install-module.sh` needs one.

## Steps
- [x] Code: `entrypoint.sh`: add the uk-ua insert (PHP 5.6-safe, schema detection, `WHERE NOT EXISTS`, exit non-zero on failure)
  Findings: the PHP is fed to `php` via a quoted heredoc (`php <<'PHP'`) rather than `php -r`, which avoids shell quoting of the SQL. The `WHERE NOT EXISTS` guard became a PHP-side `SELECT 1 … code='uk-ua'` check, which reads more simply. `mysqli_report(MYSQLI_REPORT_OFF)` keeps PHP 8.1's default exception mode from turning a query error into an uncaught exception.
- [x] Code: `install-module.sh`: add smoke Check 4 (2 enabled languages including `uk-ua`)
- [x] Docs: `docker/README.md`: intro paragraph, smoke check item 4, reinstall note
- [x] Syntax: `sh -n entrypoint.sh`, `bash -n install-module.sh`, `php -l` on the extracted PHP snippet; exercise the connect-failure path locally
  Findings: running the snippet through `sh` exactly as the entrypoint does exposed a bug. `STDERR` is undefined when PHP reads its script from stdin (`Undefined constant "STDERR"`, exit 255), so the failure message never appeared. Fixed with `file_put_contents('php://stderr', …)`, plus `@` on `mysqli_connect` to drop the duplicate warning. Retest: prints `[opencart] adding uk-ua failed: Access denied …`, exits 1, and `set -e` stops the script. The success path cannot run here (no DB credentials), so it is covered by the live verify. Tested on local PHP 8.2 only; PHP 5.6/7.4 run in the live verify.
- [x] Bump version + build + test
  Findings: 04.01.0110 → 04.01.0111. Build green (`releases/v04.01.0111/`). Unit tests 24 passed, 0 failed, 6 skipped (no API key). `sh -n` and `bash -n` pass. Note: `feat/1-support-opencart-4-1` also uses 04.01.0111, so whichever merges second will conflict on `_version.txt` and needs a re-bump.
- [x] Live verify: user runs `docker compose -f docker/compose.yaml up -d --build --force-recreate` and `docker/install-module.sh all`. I query `oc_language` on oc23, oc30 and oc40 via `host.internal`: 2 rows, Cyrillic name intact, the right per-version columns. The user confirms Check 4 PASS and that Українська appears in the module's language dropdown
  Findings (10.11.2026): the first attempt showed only en-gb, because the user's host checkout is separate from this container's /workspace, so the stores were built from the old entrypoint. Fixed by pushing WIP commit `cb55cb5` and having the user check out the branch. Second attempt: oc23, oc30 and oc40 each have row 2 `Українська`/`uk-ua`, status 1, sort_order 2, with the right per-version columns (`ua.png`/`uk-ua` on 2.3/3.0, `extension=''` on 4.0). `docker/install-module.sh all` on the host passes all 4 smoke checks on oc23, oc30 and oc40, including `PASS 2 enabled languages (en-gb, uk-ua)`; the module page renders without PHP errors on all three with uk-ua present.
- [x] Independent review → 19-docker-uk-ua-language-review.md
  Findings: Fable 5.1, ready to merge, no Critical/Important findings. 5 Minor findings.
- [x] Address review findings (user picks which to fix or skip)
  Findings: the user chose to fix 1-4. (1) README: 2.3/3.0 storefront auto-selects uk-ua from `Accept-Language`, and the install-order wording is corrected (uk-ua is added before `install/` is deleted). (2) The entrypoint error now adds `the store is half installed; recreate the container to retry`; the install's failure behaviour is unchanged. (3) Check 4 message reworded. (4) Check 4 now requires at least 2 enabled languages (`-lt 2`), and the PASS line prints the count. (5) Skipped: version collision with the 4.1 branch, already noted under the build step. Failure path retested: both lines print, exit 1.
- [x] Bump version + build + tests (verify loop)
  Findings: 04.01.0111 → 04.01.0112. Build green (`releases/v04.01.0112/`). Unit tests 24/0/6. `sh -n` and `bash -n` pass. The fixes were not re-run live; they only touch messages, the README and the Check 4 comparison (`-lt 2`).
- [ ] Re-review? (unreviewed fixes exist; ask the user, and if yes repeat from the review step → -review-v2.md, v3, …)
- [ ] Improvements pass → 19-docker-uk-ua-language-improvements.md

## Done
<filled at the end>
