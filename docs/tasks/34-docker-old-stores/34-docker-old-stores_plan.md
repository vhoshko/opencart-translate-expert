# 34-docker-old-stores

## Settings

- Planning model: Opus 5.5 (inline). Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes. `build.sh` changes, so bump the version and run `./build.release.sh`.
- Commit, push, PR: only when the user asks. A live check on the host needs a pushed WIP branch (see memory `host-checkout-separate`). Ask before the push.
- Auto-approve for review findings: not granted.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/34

The build makes 8 packages. The Docker test environment has stores for OC 2.3, 3.0, 4.0 and 4.1 only. Packages for 1.5, 2.0, 2.1 and 2.2 have no test store, so nobody can install and smoke-check them before a release.

Scope added by the user (2026-11-12):

1. Fix the 1.5 package in `build.sh`. Today it injects nothing into the 1.5 admin header.
2. Write per-version install and update steps in the manual (`README.md`, section **Installation**), with a 1.5 subsection.
3. Make `docker/install-module.sh` do the same steps as the manual for each version.

Affected OC versions: 1.5, 2.0, 2.1, 2.2 (new stores and the 1.5 package). The 2.3, 3.0, 4.0 and 4.1 stores do not change.

Key files:

- `docker/compose.yaml`, `docker/db/init.sql`, `docker/opencart/Dockerfile`, `docker/opencart/php-setup.sh`, `docker/opencart/entrypoint.sh`
- `docker/install-module.sh`, `docker/rebuild-stores.sh`, `docker/README.md`
- `build.sh:113-123` (1.5 package), `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml`
- `README.md:28-30` (Installation)

Investigation: no Explore agent. All touched files are in `docker/` plus `build.sh:113-123`, and I read them directly. A research agent read the OpenCart source at the 4 tags and the vQmod source. I checked the load-bearing claims myself (see Assumptions).

## Approach

Add 4 stores: `oc22` (2.2.0.0), `oc21` (2.1.0.2), `oc20` (2.0.3.1) and `oc15` (1.5.6.4). All use `php:5.6-apache`.

Design corrections:

- **1.5 uses vQmod, not OCMOD.** The `oc1.5` package already rewrites the XML to vQmod syntax (`path` + `name`), but it ships the file in `system/`. vQmod reads only `vqmod/xml/*.xml`. Move the file to `upload/vqmod/xml/OpenCartTranslateExpertClient.xml` in the 1.5 package.
- **The 1.5 vQmod searches miss.** 1.5 has `protected function index()` and `<div id="header">`. The source XML searches `public function index(` and `<header `. A vQmod operation without `error` defaults to `abort`, so vQmod skips the whole mod. In the 1.5 build, rewrite the searches to `function index(` and `<div id="header">`.
- **The 1.5 snippet has `$_SERVER[REQUEST_URI]` without quotes.** On PHP 5.6 this gives a Notice on the module page. Quote the key.
- **The 1.5 store gets vQmod 2.6.6 in its image.** A 1.5 store owner installs vQmod before the module. The manual lists vQmod as a requirement. The Docker store has it pre-installed, like a real store.
- **One install path for 1.5–3.0 in `install-module.sh`:** copy `upload/`, then a modification refresh (2.0–3.0 only, 1.5 has none), then Extensions › Modules › Install. The routes differ by version, so add small per-store helpers (module route, dashboard route, refresh route).
- **Manual install method for 1.5–2.3: copy `upload/` to the store root.** This works on every version (the 2.0 Extension Installer needs FTP settings). `install-module.sh` uses the same method, so the manual and the script match.

## Assumptions

- ✅ Releases, SHA-256 and `OC_UPLOAD_DIR` for each tag. Evidence: research agent downloaded each zip and ran `sha256sum`. 2.2.0.0 has a release asset. 1.5.6.4, 2.0.3.1 and 2.1.0.2 have no asset, so use the GitHub tag archive.
- ❓ The SHA-256 of a GitHub tag archive stays the same. GitHub makes these archives on demand and does not guarantee the bytes. If it changes, the image build fails at `sha256sum -c` with a clear error. Then update the hash.
- ✅ `install/cli_install.php` exists in all 4 tags. 2.0–2.2 use the same options as 2.3 (2.0 ignores `--db_port`). 1.5 uses `--db_host --db_user --db_password --db_name --db_prefix --agree_tnc yes`. Evidence: 1.5.6.4 `install/cli_install.php:61-123`.
- ✅ 1.5 writes `DB_DRIVER 'mysql'` into both config files (`cli_install.php:241,280`). On PHP 5.6 the `mysql` driver prints Deprecated on each page. The entrypoint changes it to `mysqli` after the install. The 1.5 CLI check needs the `mysql` extension, so `php-setup.sh` installs it.
- ✅ The 1.5 header hooks: `protected function index()` at `admin/controller/common/header.php:3`, `<div id="header">` at `admin/view/template/common/header.tpl:55`. 2.0–2.2 have `public function index()` and `<header id="header"`. Evidence: `gh api` at each tag.
- ✅ vQmod defaults an operation to `error="abort"` (`vqmod.php:823`). vQmod reads `vqmod/xml/*.xml` and reloads when a file or the folder mtime changes (`vqmod.php:222-235`). So an update needs only a file copy.
- ✅ Routes: token parameter is `token` on 1.5–2.2. Login is a POST to `common/login` with a 302 that has `&token=`. Refresh is `extension/modification/refresh` on 2.0–2.2. Install is `extension/module/install&extension=X` on 1.5–2.2. The module page is `module/client_translate_expert`. The dashboard is `common/home` on 1.5. Evidence: research agent, `admin/controller/extension/module.php` and `modification.php` at each tag.
- ✅ `oc_language`: 1.5 has a `filename` column (NOT NULL). 1.5–2.1 use `directory` for the language folder, 2.2 uses `code`. The second-language row: 1.5 → directory `ukrainian`, filename `english`. 2.0 and 2.1 → directory `ukrainian`. 2.2 → as today. Evidence: 1.5.6.4 `system/library/language.php:28-39`, `client_translate_expert_core.php:813-814`.
- ❓ The module itself works on 1.5, 2.0, 2.1 and 2.2. Nobody ran these packages on a store before. Only a live run on the host proves it. If a smoke check fails, the fix can be in `sources/` or `build.sh`, and that can expand the scope. I cannot run Docker in this container.

## Steps

- [x] `docker/opencart/Dockerfile`: optional build args `VQMOD_URL` and `VQMOD_SHA256`. If set, download and verify vQmod, and copy `vqmod/` into the store root.
- [x] `docker/opencart/php-setup.sh`: add `mysql` to the 5.6 branch (1.5 CLI check).
- [x] `docker/compose.yaml`: services `oc22`, `oc21`, `oc20`, `oc15` with ports `8022`, `8021`, `8020`, `8015` and port variables. Findings: no YAML parser in the container, so the format is checked by eye only. The host run is the real check.
- [x] `docker/db/init.sql`: databases `oc22`, `oc21`, `oc20`, `oc15`.
- [x] `docker/opencart/entrypoint.sh`: the 1.5 CLI options, the `DB_DRIVER` fix, vQmod install (`php vqmod/install/index.php`), and the `uk-ua` row with `filename` and the correct `directory` for each version. Findings: the entrypoint reads the release from `define('VERSION', …)` in `index.php`, so `compose.yaml` needs no new variable. The vQmod installer loads `ugrsr.class.php` by a relative path, so it runs from `vqmod/install/`.
- [x] `build.sh`: 1.5 package. Fix the 2 searches, quote `REQUEST_URI`, move the XML to `upload/vqmod/xml/`.
- [x] `docker/install-module.sh`: new stores, `all`, ports, packages, per-store routes, vQmod path for 1.5 (no refresh), smoke checks. Findings: new helpers `is_oc15`, `is_pre23`, `dashboard_route`, `module_install_route`. 1.5 has no refresh step.
- [x] `docker/rebuild-stores.sh`: new stores.
- [x] `README.md`: Installation and Update for each version (4.x, 2.0–3.0, 1.5 with vQmod). Findings: the method for 1.5–3.0 is a copy of `upload/`, the same as the script. "An uninstall deletes its settings" is verified: `admin/controller/extension/module.php` calls `deleteSetting` at 1.5.6.4:146 and 2.0.3.1:61.
- [x] `docker/README.md`: new stores in the table and the text. Remove "OC 1.5 and OC 2.0–2.2 packages are not covered here". Findings: 1.5–2.1 also detect the browser language (`HTTP_ACCEPT_LANGUAGE` in `index.php`), so the note now says 1.5–3.0.
- [ ] Bump version + build + test (`ote-build-test`). Also check the built oc1.5 package: XML in `upload/vqmod/xml/`, new searches, quoted key.
- [ ] Live run on the host: push a WIP branch (ask first). The user runs `docker/rebuild-stores.sh oc15 oc20 oc21 oc22` and sends the output.
- [ ] Independent review → `34-docker-old-stores-review.md`
- [ ] Address review findings (the user picks which to fix or skip)
- [ ] Bump version + build + tests (verify loop)
- [ ] Re-review? (ask the user)
- [ ] Improvements pass → `34-docker-old-stores-improvements.md`

## Done

<fill at the end>
