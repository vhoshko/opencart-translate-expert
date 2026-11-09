# Docker test stores

Three local OpenCart stores to check the built module on, one per package family:

| Service | OpenCart | PHP | Admin | Package installed |
|---------|----------|-----|-------|-------------------|
| `oc23`  | 2.3.0.2  | 5.6 | http://localhost:8023/admin/ | `…-oc2.3-…ocmod.zip` |
| `oc30`  | 3.0.3.9  | 7.4 | http://localhost:8030/admin/ | `…-oc3.0-…ocmod.zip` |
| `oc40`  | 4.0.2.3  | 8.1 | http://localhost:8040/admin/ | `…-oc4.0-…/client_translate_expert.ocmod.zip` |

Admin login on every store: `admin` / `admin`. All stores share one MariaDB 10.6 container (databases `oc23`, `oc30`, `oc40`, user `opencart` / `opencart`).

The images are built from the official OpenCart release zips on GitHub, pinned by SHA-256 in `compose.yaml`. On its first start each store installs itself with OpenCart's own `install/cli_install.php` and then deletes `install/`. PHP errors are shown in the page (`display_errors = On`, `E_ALL`).

Requirements: Docker with Compose v2, `curl` and `unzip` on the host. Run every command from the repo root.

## Start

```bash
docker compose -f docker/compose.yaml up -d --build          # all three stores
docker compose -f docker/compose.yaml up -d --build oc30     # just one
```

The first build takes a few minutes. Follow the install with `docker compose -f docker/compose.yaml logs -f oc30` and wait for `[opencart] ready`.

The stores listen on `127.0.0.1` only, so they are not reachable from the network. Ports can be changed with `OC23_PORT`, `OC30_PORT` and `OC40_PORT`. Set the same variable for `install-module.sh`:

```bash
OC30_PORT=9030 docker compose -f docker/compose.yaml up -d --force-recreate oc30
OC30_PORT=9030 docker/install-module.sh oc30
```

OpenCart writes its URL (with the port) into `config.php` when it installs, which only happens on a store's first start. That is why a port change needs `--force-recreate`: the store reinstalls under the new URL, and the module has to be installed again.

## Install the module

```bash
./build.release.sh                    # bump _version.txt first, see CLAUDE.md
docker/install-module.sh all          # or oc23 / oc30 / oc40
```

The script takes the newest `releases/v*/OpenCartTranslateExpertClient-v*.full.zip` (or the one given with `-p <file>`), picks the right package for each store and installs it the way a store owner would, signed in to the admin with `curl`:

- **oc23, oc30**: copies `upload/` into the store, runs Extensions › Modifications › Refresh (this applies `system/OpenCartTranslateExpertClient.ocmod.xml` to the admin header), then Extensions › Modules › Install.
- **oc40**: Extensions › Installer upload and install, then Extensions › Modules › Install (this runs the module's `install()`, which registers its two header events).

The package's own `install.php` is not run on oc23 and oc30. What it does (admin permissions, the `translate_expert_same_translations` table) is already covered by Extensions › Modules › Install.

Running the script again on a store where the module is already installed only replaces the files (and refreshes modifications on oc23 and oc30). That is the quick way to try a new build. For a truly fresh install, recreate the stores (see [Tear down](#tear-down)).

### Smoke checks

After installing, the script checks each store and exits non-zero if anything fails:

1. The module's settings form renders (the page contains its API key field, `input-client_translate_expert_key` or `input-module_client_translate_expert_key` on 3.0 and 4.0, so a login, permission-denied or not-found page fails) and shows no PHP error, notice or warning.
2. The admin dashboard loads `client_translate_expert.js`, which shows the OCMOD (2.3, 3.0) or the header event (4.0) is working.
3. The module is registered: its `oc_extension` row and the `oc_translate_expert_same_translations` table exist, and on 4.0 its two header events are in `oc_event`.

Run only the checks, for example after editing files inside a container:

```bash
docker/install-module.sh -c all
```

### Manual install (fallback)

Use the store's own Extension Installer when you want to test the upload path itself or the script does not work:

- **2.3 / 3.0**: Extensions › Installer, upload `OpenCartTranslateExpertClient-oc2.3-…ocmod.zip` or `…-oc3.0-…ocmod.zip` (unzip it from the `.full.zip` first). Then Extensions › Modifications › Refresh, then Extensions › Extensions › Modules › Translate Expert › Install.
- **4.0**: Extensions › Installer, upload `client_translate_expert.ocmod.zip` from the `…-oc4.0-…` folder in the `.full.zip`, click Install, then Extensions › Extensions › Modules › Install.

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

Each store's files live in its container, not in a volume. If a store container is recreated (for example by `up --build`), it reinstalls OpenCart into its database from scratch, and the module has to be installed again.

## Notes

- The PHP 5.6 and 7.4 images run on end-of-life Debian releases. `opencart/php-setup.sh` switches apt to `archive.debian.org` when the normal mirrors fail.
- MariaDB runs with an empty `sql_mode`, because OpenCart 2.3 writes rows that strict mode rejects.
- OC 1.5 and OC 2.0–2.2 packages are not covered here.
