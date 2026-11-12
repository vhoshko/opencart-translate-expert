# 34-docker-old-stores: review

Range: `git diff origin/main...HEAD` (commit `7f9e1f1`). `git diff HEAD` shows only plan changes.

## How I did the checks

- I read the full diff and the full text of each changed script.
- I compared the packages of `releases/v04.01.0146` and `releases/v04.01.0147`, file by file.
- I ran vQmod 2.6.6 in this container against the OpenCart 1.5.6.4 admin header files. The probe is `docs/tasks/34-docker-old-stores/tmp/probe/run-probe.sh`.
- I ran the `OC_VERSION` `sed` expression of `entrypoint.sh` against `index.php` of all 8 tags.
- I ran `shellcheck`, `bash -n` and `dash -n` on the scripts, and `php tests/test_vh_google_translator.php` (24 passed, 6 skipped).
- I did not run Docker and I did not run a build script.

Run the probe again with this command:

```bash
cd docs/tasks/34-docker-old-stores/tmp/probe
bash run-probe.sh src/new-0147.xml src/old-0146.xml
```

## Strengths

- The `build.sh` change touches only the 1.5 block. The 2.0, 2.1, 2.2, 2.3, 3.0 and 4.x packages of 0147 differ from 0146 only in the version string.
- The 1.5 fix is correct. Probe case A: vQmod applies the 0147 XML to `header.php` and `header.tpl`, and the changed `header.php` passes `php -l`. Probe case A2: the 0146 XML gives `SEARCH NOT FOUND (ABORTING MOD): public function index(`. This agrees with the plan.
- The built XML has the quoted `$_SERVER["REQUEST_URI"]` and is in `upload/vqmod/xml/`. No copy stays in `upload/system/`.
- The `OC_VERSION` expression finds a version in all 8 tags. The 1.5.6.4 file has CRLF line ends, and the expression is correct for them. Thus `oc23` and `oc30` keep the `uk-ua` folder, as before.
- The `else` branch of the installer call in `entrypoint.sh` is the same as the old call. The 2.3, 3.0, 4.0 and 4.1 stores have no change in behavior.
- `install-module.sh` has no bash 4 syntax. `entrypoint.sh` passes `dash -n`. `shellcheck` gives only `info` and `style` notes, none of them new problems.
- `README.md` and `install-module.sh` agree for 2.0–3.0 and 4.x: the same steps, in the same sequence, with the correct menu names for each version.
- The vQmod archive has its own SHA-256, and an empty `VQMOD_SHA256` with a set `VQMOD_URL` stops the image build.
- `rebuild-stores.sh` finds the new vQmod error, because the message starts with `[opencart] install failed`.

## Issues

### Critical (must fix)

None.

### Important (should fix)

**1. vQmod can ignore the new XML, and the README and the script do not allow for this.**

Files: `README.md:65`, `README.md:68`, `README.md:70`, `docker/install-module.sh:216-224`.

vQmod reads the XML files again only if one of these is newer than `vqmod/mods.cache` (`vqmod.php:222-246`):

- an XML file in `vqmod/xml/`
- the `vqmod/xml` folder
- `vqmod/pathReplaces.php`

Probe case B shows the result. A page load writes `mods.cache`. Then the XML arrives with an older mtime, and the folder mtime is also older. vQmod does not apply the XML. It writes no log entry. Probe case B2: with a new folder mtime, vQmod applies the XML.

Effect on the script. `unzip` gives the file and the `vqmod/xml` folder the build time as mtime (I measured this here). `docker cp` copies in archive mode, so I think the container gets the same mtimes. I could not verify the `docker cp` part without Docker. If it is correct, this sequence fails smoke check 2 on `oc15`:

1. Build the release.
2. Start `oc15`.
3. Open the store or the admin in a browser. This writes `mods.cache`.
4. Run `docker/install-module.sh oc15`.

`rebuild-stores.sh` does not fail, because no page load occurs between the store start and the module install. This agrees with the host run. An install of an older package with `-p` has the same cause: vQmod keeps the cached data of the newer XML.

Effect on the README. The sentences "vQmod applies it on the next page load" and "vQmod sees the changed XML file by itself" are true only if the copy gives the file a new mtime. A copy that keeps the timestamps (`cp -a`, `rsync -a`, an FTP client with that option) can leave the header without the module. Then the hint at `README.md:70` sends the store owner to `vqmod/logs/`, and the log is empty.

Fix:

- In `install-module.sh`, after the `docker cp` for `oc15`, delete `vqmod/mods.cache` in the container, or `touch` the XML file.
- In `README.md`, add the same step to the 1.5 text. For example: if the header does not load the module, delete `vqmod/mods.cache` and the files in `vqmod/vqcache/`. Keep the log hint as the second step.

Both changes keep the README and the script in agreement.

### Minor (nice to have)

**2. The vQmod check tests the wrong file.** `docker/install-module.sh:212` tests `vqmod/vqmod.php`. The Dockerfile copies this file into the image, so the file is there also when the vQmod installer did not run. The error message then cannot appear in the case that it describes. Smoke check 2 finds the fault, so the risk is small. A better test: `grep -q VQMod /var/www/html/admin/index.php`.

**3. `entrypoint.sh` does not stop on an empty `OC_VERSION`.** `docker/opencart/entrypoint.sh:10`. If the expression finds nothing, `version_compare('', '2.2', '<')` is true. Then a 2.2, 2.3 or 3.0 store gets the folder `ukrainian`, and smoke check 4 does not see it. All 8 current tags give a version, so this is only a guard for a later release. Add `[ -n "$OC_VERSION" ] || { echo "[opencart] install failed: no VERSION in index.php" >&2; exit 1; }`. Note: `index.php` at tag 3.0.3.9 has `3.0.3.8`, so the `oc30` log line can show 3.0.3.8. This has no other effect.

**4. The vQmod failure leaves a half-installed store without a hint.** `docker/opencart/entrypoint.sh:75`. `config.php` is complete at that point. On the next start, the first-start block does not run, and Apache starts without vQmod and without `uk-ua`. The `uk-ua` failure message tells the user to recreate the container. Add the same sentence to the vQmod message.

**5. `build.sh` does not check that the two new replacements changed the file.** `build.sh:123-124`. If a later edit changes `public function index(` or `<header ` in the source XML, `sed` changes nothing and the build succeeds. On 1.5, one search that does not match aborts the full mod. The 4.x block already has a guard of this type (`build.sh:417-420`). Add a `grep -q` for `function index(` without `public`, and for `<div id="header">`, on the 1.5 XML.

**6. The reason for PHP 5.6 on 1.5 is not accurate.** `docker/compose.yaml:39` and `docker/README.md:111` say that OpenCart 1.5 needs the `mysql` extension. Only the CLI installer check needs it (`install/cli_install.php:153` at 1.5.6.4). The store has a `mysqli` driver, and the entrypoint changes the store to it. The comment in `php-setup.sh` is correct. Use the same words in the other two places.

**7. `docker/README.md` has small errors for the old stores.**

- `docker/README.md:86-87`: the first bullet says 1.5–2.3, and the second bullet gives a different method for 2.3. Change the first bullet to 1.5–2.2.
- `docker/README.md:92`: the menu path `Extensions › Extensions › Modules` is correct only for 2.3 and later. On 1.5–2.2 the path is `Extensions › Modules`.

**8. "Packages before this fix" names no version.** `README.md:68`. A store owner cannot know which fix this is. Write "Packages before 04.01.0147".

## Assessment

**Ready to merge?** With fixes.

The `build.sh` change is correct and does not change the other packages. The scripts and the README agree for each version. Fix issue 1 before the merge: a normal local sequence can make the `oc15` smoke check fail, and the README gives a store owner a wrong hint in that case. The minor issues do not block the merge.
