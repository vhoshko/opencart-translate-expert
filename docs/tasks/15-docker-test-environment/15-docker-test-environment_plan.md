# 15-docker-test-environment

## Settings
- Planning model: Opus 5.5 (inline). Review model: Fable 5.1 (pass `model: fable` to `ote-code-reviewer`).
- Build + tests: yes (`ote-build-test`, bump + `./build.release.sh`). Commit / push / PR: not done by this workflow — ask.
- Auto-approve for review findings: not granted — ask per finding.
- Scope decisions (user, 09.11.2026): one store per family (OC 2.3.0.2, 3.0.3.9, 4.0.2.3); module installed by a helper script, manual Extension Installer documented as fallback.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/15 — no way to run the built packages on a real OpenCart store. Tooling only: `sources/` and the build scripts are not touched. Affects the 2.x, 3.0 and 4.0 package families.

Package facts (investigation, verified against `releases/v04.01.0105` and `build.sh`):
- `build.release.sh` leaves one `releases/v<VER>/OpenCartTranslateExpertClient-v<VER>-<DATE>.full.zip`. Inside: `…-oc2.3-v<VER>-<DATE>.ocmod.zip`, `…-oc3.0-…ocmod.zip`, and `…-oc4.0-v<VER>-<DATE>/client_translate_expert.ocmod.zip`.
- OC 2.3 / 3.0 zips: `install.php` + `upload/`; OCMOD is `upload/system/OpenCartTranslateExpertClient.ocmod.xml` (no root `install.xml`), patches `admin/controller/common/header.php` + header template → needs a modification refresh.
- OC4 zip: `install.json` + files at root, extension code `client_translate_expert`; `install()` registers two `oc_event` rows (controller `client_translate_expert.php:732-757`).
- Module `install()` creates `translate_expert_same_translations` (`client_translate_expert_core.php:844-855`); no settings written.

## Approach
New `docker/` folder: one `compose.yaml` with a shared MariaDB and three PHP+Apache stores built from the official OpenCart release zips (pinned version + SHA-256). Each store's entrypoint runs OpenCart's own `install/cli_install.php` on first start (admin/admin), then deletes `install/`. `docker/install-module.sh <oc23|oc30|oc40|all>` takes the newest release `.full.zip`, extracts the right inner package, and installs it the way a store owner would, driven over HTTP with `curl` as the admin user:
- 2.3 / 3.0: `docker cp` `upload/` into the webroot → Modifications › Refresh → Extensions › Modules › Install (which adds permissions and calls the module's `install()`). The package's `install.php` is not run (its effects — permissions + table — are covered by the extension install).
- 4.0: Extension Installer upload (`marketplace/installer.upload`) → `marketplace/installer.install` → `extension/module.install`, so `oc_extension_install`, `oc_extension_path`, `oc_extension`, permissions and events are all real.
Design correction vs. the investigation report: on OC4 the `oc_extension_install` row is mandatory, not cosmetic — `admin/controller/startup/extension.php` registers extension autoload paths from `getInstalls()`. Hence the real installer flow rather than file copy + SQL.

## Assumptions
- ✅ OC 2.3.0.2 installer needs `mcrypt_encrypt` → PHP 5.6 (`install/cli_install.php:168`). Matches the module's oldest supported PHP.
- ✅ OC 3.0.3.9 installer needs PHP ≥ 7.3 + OpenSSL (`cli_install.php:146,170`) → PHP 7.4.
- ✅ OC 4.0.2.3 installer needs PHP ≥ 8.0, and `config.php` + `admin/config.php` must exist and be writable (`cli_install.php:175,207-214`) → PHP 8.1, copy `config-dist.php` first.
- ✅ All three releases ship `upload/install/cli_install.php` and are on GitHub releases (SHA-256 recorded at download: 2.3.0.2 `4b8e0c21…`, 3.0.3.9 `9ad297be…`, 4.0.2.3 `8d18dc67…`).
- ✅ Modification refresh on 2.3 / 3.0 picks up `system/*.ocmod.xml` (`extension/modification.php:117`, `marketplace/modification.php:117`).
- ✅ OC4 startup autoload depends on `oc_extension_install` (`admin/controller/startup/extension.php:16`, `model/setting/extension.php:148`).
- ✅ `php:5.6-apache` (Debian stretch) still builds — via `archive.debian.org` (build log 09.11.2026: `[php-setup] switching apt to archive.debian.org`).
- ✅ OC 2.3 runs on MariaDB 10.6 with `sql_mode=''` — CLI install `SUCCESS`, module installed and smoke checks pass.
- ✅ The OC 4.0.2.3 installer endpoints work from `curl` (upload → `installer.install` following `next` → `extension/module.install`): `uploaded (extension_install_id=2)`, all smoke checks PASS.

## Steps
- [x] Code: `docker/compose.yaml`, `docker/db/init.sql`, ~~`docker/opencart/Dockerfile.{oc23,oc30,oc40}`~~ one `docker/opencart/Dockerfile` (build args) + `php-setup.sh`, `docker/opencart/entrypoint.sh`
  Findings: PHP 7.4 (bullseye) is mid-move to the archive: `apt-get update` succeeds but `bullseye-security` packages 404. So the archive fallback triggers on a failed *install*, not a failed update. `stretch-updates` does not exist on the archive (404) → dropped on retry. Both 5.6 and 7.4 images take the archive path today.
- [x] Code: `docker/install-module.sh` (login, refresh, install per family)
  Findings: `echo "$body" | grep -q` under `pipefail` can false-fail (SIGPIPE) → here-strings. Added `-c` (checks only) after the first negative test proved vacuous: the update path overwrote the deliberately broken file before checking. Re-runs on an installed store replace files only (OC4: copy into `extension/client_translate_expert/`).
- [x] Docs: `docker/README.md` (start, admin URLs/creds, install module, manual fallback, smoke checks, teardown) + short pointer in `README.md`
- [x] Bump version + build + test
  Findings: 04.01.0105 → 04.01.0106, `releases/v04.01.0106/`, unit tests 24 passed / 6 skipped (no key), shell `-n` checks pass. `install-module.sh all` (no `-p`) picked v04.01.0106 and updated all 3 stores, 9/9 PASS; oc30 refreshed header carries `client_translate_expert.js?v=04.01.0106`.
- [x] End-to-end run: `docker compose up`, install module in all three stores, confirm module page loads (HTTP 200, no PHP error) and JS is injected in the admin header
  Findings (09.11.2026, package v04.01.0105): all 3 stores install, 9/9 smoke checks PASS. Negative tests: injected `echo $undefined_var_for_test;` into the module controller on oc30 → `ERROR … <b>Notice</b>: Undefined variable`, exit 1; on oc40 → `Warning: Undefined variable $undefined_var_for_test in …/client_translate_expert.php on line 12`, exit 1; re-run restored both. `up --force-recreate oc23 oc40` over the existing DB reinstalls OpenCart and the module installs again (OC4 reinstall drops `oc_extension_install`, so the full installer path runs).
  Out of scope, existing bug found: on a fresh 2.3/3.0 install before the module settings are saved, the OCMOD header prints `document.js_const_client_translate_expert_is_enabled = ;` (and the same for `_show_char_count_is_enabled`) — a JS syntax error on every admin page. Seen on oc23. Candidate for a separate issue.
  Limitation: the table check is weak after a container recreate — OpenCart's reinstall does not drop `oc_translate_expert_same_translations`, so the table can pre-date the module install.
- [x] Independent review → 15-docker-test-environment-review.md (Fable 5.1: 3 important, 8 minor)
- [x] Address review findings (user picks which to fix or skip)
  Decisions (user, 09.11.2026: "as recommended"): fix I1, I2, I3, M1–M5, M7; skip M6 (`--allow-unauthenticated` — the archive's expired-key risk, test-only image) and M8 (tag pinning is fine for a test setup).
  Findings:
  - I1 confirmed before fixing: the login page on all 3 stores contains `client_translate_expert` (header injection). Marker switched to the API key field. The reviewer's suggested `id="input-client_translate_expert_key"` was wrong for 3.0/4.0 — `build.sh` adds `module_` → `input-module_client_translate_expert_key`; caught when the stricter check failed on oc30/oc40. Pattern now `input-(module_)?client_translate_expert_key`.
  - I3: resume must also skip `installer.install` when extraction finished — OC 4.0.2.3 returns `error_directory_exists` on page 1 if the folder exists (`installer.php:330`). Uses `oc_extension_install.status = 1` (set at end of extraction).
  - M4 partly wrong in the review: the real OC4 installer also extracts `install.json` / `install.php` into `extension/<code>/` (`installer.php:355-395`), so copying them is faithful. Stale-file half fixed by clearing the folder before copying.
  - `module_installed` used to treat a failed DB query (empty output) as "installed" (`!= "0"`); now numeric and fails loudly.
  - Ports bound to 127.0.0.1 (M5), so the stores were recreated → exercised the full install path again with the new script: 9/9 PASS.
  - Negative / recovery tests: permission removed on oc30 → check 1 ERROR, exit 1; oc_extension + oc_event rows deleted on oc40 → "resuming … already extracted … module installed", 3/3 PASS; stale file in OC4 extension folder → removed on update; `OC23_PORT=9999 -c all` → `[oc23] ERROR: login request failed`, oc30/oc40 still checked, exit 1.
  - Not exercised: a failing `docker cp` / `unzip` (hard to provoke); covered by the explicit `|| fail` guards only.
- [x] Bump version + build + tests (verify loop)
  Findings: 04.01.0106 → 04.01.0107, `releases/v04.01.0107/`, 24 passed / 6 skipped, shell `-n` pass, root clean. `install-module.sh all` updated all 3 stores to v04.01.0107, 9/9 PASS, exit 0.
- [x] Re-review? (asked 09.11.2026 — user: yes)
- [x] Independent review → 15-docker-test-environment-review-v2.md (Fable 5.1: fix pass clean, no regressions; 1 important + 6 minor, the important one pre-existing)
- [x] Address review-v2 findings
  Decisions (user, 09.11.2026: "as recommended"): fix I1, m2, m3, m4, m6; skip m1 (check 2 PASS on a login page — exit code already 1 via check 1) and m5 (`oc_extension_path` bookkeeping on the OC4 update path; test stores only).
  Findings:
  - I1: the module Install response (2.3/3.0 extension list page, 4.0 JSON) is now run through `php_errors`. No false positives on the 2.3/3.0 list page (forced install path on all 3 stores, 9/9 PASS). Negative: `echo $undefined_in_install;` in OC4 `install()` → `ERROR: module install printed PHP errors: Warning: Undefined variable $undefined_in_install … line 697`.
  - Limitation (not fixed): OpenCart writes the `oc_extension` row before `install()` runs, so after a failed `install()` a re-run takes the update path and does not run `install()` again. Recreate the store in that case.
  - m2: `module_installed` removed; callers read `module_rows` and print one accurate message.
- [x] Bump version + build + tests (verify loop v2)
  Findings: 04.01.0107 → 04.01.0108, `releases/v04.01.0108/`, 24 passed / 6 skipped, root clean. `install-module.sh all` → 9/9 PASS, exit 0.
- [x] Re-review v3? Asked 09.11.2026. User: no; findings had dropped to non-correctness items. (ask the user)
- [x] Improvements pass → 15-docker-test-environment-improvements.md

Dropped: Templates and Language strings steps (no UI change); regression-test proof (not a bug fix, and Docker tooling has no test harness — the end-to-end run is the verification).

## Done
- **What landed:**
  - `docker/` contains `compose.yaml` and a shared MariaDB. It runs three self-installing stores: OC 2.3.0.2 on PHP 5.6, OC 3.0.3.9 on PHP 7.4 and OC 4.0.2.3 on PHP 8.1. Their ports are bound to 127.0.0.1.
  - `docker/install-module.sh` installs or updates the newest release package the way a store owner would, and runs 3 smoke checks per store. `-c` runs the checks only.
  - Docs are in `docker/README.md`, with a pointer in `README.md`.
  - `sources/` and the build scripts are untouched.
- **Final version:** 04.01.0108. Verified end to end on all 3 stores, 9/9 PASS, with negative tests for every check. Two Fable 5.1 review passes, findings fixed as the user decided.
- **Gotchas:**
  - Debian bullseye is mid-move to archive.debian.org (update OK, packages 404).
  - `build.sh`'s `module_` prefix also changes form field ids on 3.0/4.0.
  - OC4 autoloads extensions from `oc_extension_install`.
  - The OC4 installer refuses to re-extract over an existing folder.
- **Deferred / follow-up:**
  - Existing bug: before the module settings are first saved, OC 2.3/3.0 admin pages emit `document.js_const_client_translate_expert_is_enabled = ;`, a JS syntax error. Candidate for a separate issue.
  - Skipped review items: M6, M8, v2 m1, v2 m5.
  - After a failed `install()`, a re-run doesn't run it again; recreate the store.
  - Nothing committed. The plan is the handoff once it is committed.
