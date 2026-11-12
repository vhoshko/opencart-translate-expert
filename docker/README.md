# Docker test stores

Eight local OpenCart stores, one for each built package, to check the module on:

| Service | OpenCart | PHP | Admin | Package installed |
|---------|----------|-----|-------|-------------------|
| `oc15`  | 1.5.6.4 + vQmod 2.6.6 | 5.6 | http://localhost:8015/admin/ | `…-oc1.5-…ocmod.zip` |
| `oc20`  | 2.0.3.1  | 5.6 | http://localhost:8020/admin/ | `…-oc2.0-…ocmod.zip` |
| `oc21`  | 2.1.0.2  | 5.6 | http://localhost:8021/admin/ | `…-oc2.1-…ocmod.zip` |
| `oc22`  | 2.2.0.0  | 5.6 | http://localhost:8022/admin/ | `…-oc2.2-…ocmod.zip` |
| `oc23`  | 2.3.0.2  | 5.6 | http://localhost:8023/admin/ | `…-oc2.3-…ocmod.zip` |
| `oc30`  | 3.0.3.9  | 7.4 | http://localhost:8030/admin/ | `…-oc3.0-…ocmod.zip` |
| `oc40`  | 4.0.2.3  | 8.1 | http://localhost:8040/admin/ | `…-oc4.x-…/client_translate_expert.ocmod.zip` |
| `oc41`  | 4.1.0.4  | 8.2 | http://localhost:8041/admin/ | `…-oc4.x-…/client_translate_expert.ocmod.zip` (same package as `oc40`) |

Admin login on every store: `admin` / `admin`. All stores share one MariaDB 10.6 container (databases `oc15`, `oc20`, `oc21`, `oc22`, `oc23`, `oc30`, `oc40`, `oc41`, user `opencart` / `opencart`).

The images are built from the official OpenCart release zips on GitHub, pinned by SHA-256 in `compose.yaml`. OpenCart 1.5.6.4, 2.0.3.1 and 2.1.0.2 have no release zip, so their images use GitHub's tag archive of the release. GitHub does not promise that a tag archive keeps the same bytes; if the SHA-256 check fails, check the archive and update the hash. The `oc15` image also gets vQmod 2.6.6 (pinned the same way), because OpenCart 1.5 has no OCMOD: a 1.5 store owner installs vQmod before the module (see Installation in the main [README](../README.md)). On its first start each store installs itself with OpenCart's own `install/cli_install.php`, adds a second language, `uk-ua` (Українська), next to the stock English, so the module has a language to translate into. Only the `oc_language` row is added: there is no language pack, so the `uk-ua` descriptions start out empty and its flag image is missing. Then it deletes `install/`. On `oc15` it also installs vQmod into `index.php` and `admin/index.php` and changes `DB_DRIVER` from `mysql` to `mysqli` in both config files: the 1.5 installer writes `mysql`, which shows a "Deprecated" notice on every page on PHP 5.6. On 1.5–2.1 the `uk-ua` row uses the language folder `ukrainian` (the folder the module's packages for those versions use); a missing catalog folder falls back to English. On 1.5–3.0 the storefront picks its language from the browser's `Accept-Language`, so a browser set to Ukrainian opens the store in `uk-ua` and sees an empty catalog until something is translated. The admin and 4.x (4.0, 4.1) are not affected. PHP errors are shown in the page (`display_errors = On`, `E_ALL`).

Requirements: Docker with Compose v2, `curl` and `unzip` on the host. Run every command from the repo root.

## Start

```bash
docker compose -f docker/compose.yaml up -d --build          # all eight stores
docker compose -f docker/compose.yaml up -d --build oc30     # just one
```

The first build takes a few minutes. Follow the install with `docker compose -f docker/compose.yaml logs -f oc30` and wait for `[opencart] ready`.

To do everything in one go, run `docker/rebuild-stores.sh`. It runs `./build.release.sh`, recreates the stores (a fresh OpenCart install, so anything done in them is lost), waits for `[opencart] ready` and runs `install-module.sh` with its smoke checks on each store. It stops with the store's log if an install fails.

```bash
docker/rebuild-stores.sh                 # build, then all eight stores
docker/rebuild-stores.sh oc30            # build, then just one
docker/rebuild-stores.sh -s oc23 oc40    # skip the build, use the newest release
```

`build.release.sh` uses `_version.txt` as it is, so bump it first if the module sources changed since the last build (see CLAUDE.md). The wait times out after 600 seconds; set `READY_TIMEOUT` to change that.

The stores listen on `127.0.0.1` only, so they are not reachable from the network. Ports can be changed with `OC15_PORT`, `OC20_PORT`, `OC21_PORT`, `OC22_PORT`, `OC23_PORT`, `OC30_PORT`, `OC40_PORT` and `OC41_PORT`. Set the same variable for `install-module.sh`:

```bash
OC30_PORT=9030 docker compose -f docker/compose.yaml up -d --force-recreate oc30
OC30_PORT=9030 docker/install-module.sh oc30
```

OpenCart writes its URL (with the port) into `config.php` when it installs, which only happens on a store's first start. That is why a port change needs `--force-recreate`: the store reinstalls under the new URL, and the module has to be installed again.

## Install the module

```bash
./build.release.sh                    # bump _version.txt first, see CLAUDE.md
docker/install-module.sh all          # or oc15 / oc20 / oc21 / oc22 / oc23 / oc30 / oc40 / oc41
```

The script takes the newest `releases/v*/OpenCartTranslateExpertClient-v*.full.zip` (or the one given with `-p <file>`), picks the right package for each store and installs it with the steps of the Installation section in the main [README](../README.md), signed in to the admin with `curl`:

- **oc20, oc21, oc22, oc23, oc30**: copies `upload/` into the store, runs Extensions › Modifications › Refresh (this applies `system/OpenCartTranslateExpertClient.ocmod.xml` to the admin header), then Extensions › Modules › Install.
- **oc15**: checks that vQmod is installed, copies `upload/` into the store (this puts `vqmod/xml/OpenCartTranslateExpertClient.xml` in place, which vQmod applies on the next page load), then Extensions › Modules › Install.
- **oc40, oc41**: Extensions › Installer upload and install, then Extensions › Modules › Install (this runs the module's `install()`, which registers its two header events).

The package's own `install.php` is not run on 1.5–3.0. What it does (admin permissions, the `translate_expert_same_translations` table) is already covered by Extensions › Modules › Install.

Running the script again on a store where the module is already installed only replaces the files (and refreshes modifications on 2.0–3.0). That is the quick way to try a new build. For a truly fresh install, recreate the stores (see [Tear down](#tear-down)).

### Smoke checks

After installing, the script checks each store and exits non-zero if anything fails:

1. The module's settings form renders (the page contains its API key field, `input-client_translate_expert_key` or `input-module_client_translate_expert_key` on 3.0 and 4.x, so a login, permission-denied or not-found page fails) and shows no PHP error, notice or warning.
2. The admin dashboard (`common/home` on 1.5) loads `client_translate_expert.js`, which shows the OCMOD (2.0–3.0), the vQmod XML (1.5) or the header event (4.x) is working.
3. The module is registered: its `oc_extension` row and the `oc_translate_expert_same_translations` table exist, and on 4.x its two header events are in `oc_event`.
4. The store has at least two enabled languages, one of them `uk-ua`. A store installed before `uk-ua` was added fails this check until it is recreated.

Run only the checks, for example after editing files inside a container:

```bash
docker/install-module.sh -c all
```

### Manual install (fallback)

Use the store's own Extension Installer when you want to test the upload path itself or the script does not work:

- **1.5–2.3**: copy the files by hand, as the main [README](../README.md) describes.
- **2.3 / 3.0**: Extensions › Installer, upload `OpenCartTranslateExpertClient-oc2.3-…ocmod.zip` or `…-oc3.0-…ocmod.zip` (unzip it from the `.full.zip` first). Then Extensions › Modifications › Refresh, then Extensions › Extensions › Modules › Translate Expert › Install.
- **4.0 / 4.1**: Extensions › Installer, upload `client_translate_expert.ocmod.zip` from the `…-oc4.x-…` folder in the `.full.zip`, click Install, then Extensions › Extensions › Modules › Install.

## Manual checks

Open the module page (Extensions › Extensions › Modules › Translate Expert › Edit) and check:

- the settings form saves;
- table analysis runs;
- translation works. This needs a Google Cloud Translation API key entered in the module settings. Without a key, everything except the actual translation can still be checked.

## Tear down

```bash
docker compose -f docker/compose.yaml down        # stop, keep the database volume
docker compose -f docker/compose.yaml down -v     # stop and delete everything
```

Each store's files live in its container, not in a volume. If a store container is recreated (for example by `up --build`), it reinstalls OpenCart into its database from scratch (with `uk-ua` added again, and everything translated into it gone), and the module has to be installed again.

## Notes

- The PHP 5.6 and 7.4 images run on end-of-life Debian releases. `opencart/php-setup.sh` switches apt to `archive.debian.org` when the normal mirrors fail.
- MariaDB runs with an empty `sql_mode`, because OpenCart 2.3 and older write rows that strict mode rejects.
- OpenCart 1.5 needs the PHP 5 `mysql` extension, so PHP 5.6 is the newest PHP it runs on. All stores before 2.3 use PHP 5.6 too.
- `db/init.sql` runs only when the database volume is first created. On a volume made before a store existed (for example `oc15`, `oc20`, `oc21` and `oc22`, which came after `oc41`), that store waits forever at `[opencart] waiting for database db/<store>...`. Either recreate the volume (`down -v`) or add the database once (replace `oc41` with the store name):
  `docker compose -f docker/compose.yaml exec db mariadb -uroot -proot -e "CREATE DATABASE IF NOT EXISTS oc41 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; GRANT ALL PRIVILEGES ON oc41.* TO 'opencart'@'%';"`
