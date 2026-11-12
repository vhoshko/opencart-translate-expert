# 34-docker-old-stores: review, pass 2

Range: `git diff 7f9e1f1 ccf711a`, without `docs/tasks/**` and `_version.txt`. This pass examines only the fix commit.

## How I did the checks

- I read the full diff and the full text of `entrypoint.sh`, and the changed parts of `install-module.sh` and `build.sh`.
- I downloaded vQmod 2.6.6 from the URL in `compose.yaml`. Its SHA-256 agrees with `VQMOD_SHA256`. Its `vqmod.php` is the same file as in the pass 1 probe.
- I ran the vQmod installer on the stock `index.php` and `admin/index.php` of OpenCart 1.5.6.4.
- I ran vQmod with three cache procedures, for an update that keeps the file dates.
- I read `admin/controller/common/login.php` and `system/engine/controller.php` at tag 1.5.6.4.
- I did not run Docker and I did not run a build script. I did not measure the file dates that `docker cp` gives.

The probes are in `docs/tasks/34-docker-old-stores/tmp/probe2/`. Run them again with these commands:

```bash
cd docs/tasks/34-docker-old-stores/tmp/probe2
bash run-install-probe.sh
bash run-stale-probe.sh rm-cache
bash run-stale-probe.sh rm-cache-and-vqcache
bash run-stale-probe.sh touch-xml
bash run-stale-probe.sh rm-cache header
```

## Result for each fix

| Finding | Result |
|---------|--------|
| 1. `mods.cache` | Closed for the first install and for a newer package. Not closed for an older package (see issue 1). |
| 2. vQmod check | Closed. |
| 3. Empty `OC_VERSION` | Closed. No regression on a restart. |
| 4. vQmod failure hint | Closed. |
| 5. `build.sh` guard | Closed. |
| 6. `mysql` extension note | Closed. |
| 7. `docker/README.md` bullets and menu path | Closed. |
| 8. Version in `README.md` | Closed. |

## Strengths

- **Fix 2.** The vQmod installer writes `VQMod::bootup();` into `admin/index.php` (`vqmod/install/index.php`, the `//VirtualQMOD` pattern). The stock 1.5.6.4 file has no `VQMod` text. Probe: `grep -q 'VQMod::bootup'` gives 1 before the installer and 0 after it. The check now finds the condition that its message describes.
- **Fix 3.** The guard runs on each start, before the first-start block. This is safe. The vQmod installer changes only the `// Startup` comment and the `require_once(DIR_SYSTEM ...)` lines of `index.php`. Probe: the `sed` expression gives `1.5.6.4` before and after the vQmod install. The entrypoint does not change `index.php` on the other stores. Thus a restart of an installed container does not stop.
- **Fix 3, message.** The message starts with `[opencart] install failed`, so `rebuild-stores.sh:63` finds it.
- **Fix 4.** The new line has the same words as the `uk-ua` failure message. The multi-line `case` branch is correct POSIX syntax.
- **Fix 5.** The guard is in an `if` condition, so `set -euo pipefail` does not stop the script before the error message. `grep -F` makes `[`, `(` and `]` literal. The patterns include the `<![CDATA[` prefix, so the source text `<![CDATA[public function index(]]>` cannot satisfy the first pattern. `<div id="header">` is not in the source XML. The guard therefore fails only when a rewrite did not occur. The guard has the same shape as the 4.x guard at `build.sh:417-420`.
- **Fix 1, sequence.** The `mods.cache` delete is after `docker cp` and `chown`, and before `login`. This is the correct sequence: no request can write a new `mods.cache` between the copy and the delete. The delete also runs on later runs ("module already installed"), which is necessary.
- **Fix 1, first install.** With no `mods.cache`, vQmod sets its reference time to `time()` and clears `checked.cache` (`vqmod.php:239-250`). The header files have no `vqcache` file before the first install. The first-install case of pass 1 is closed.
- **Fixes 6 to 8.** The `compose.yaml` comment and `docker/README.md` now agree with `php-setup.sh:37`. `oc23` uses `php:5.6-apache`, so "like `oc23`" is correct. The menu paths are correct for each version group.
- `README.md`, `docker/README.md` and the header comment of `install-module.sh` give the same steps for 1.5, in the same sequence.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

**1. The `mods.cache` delete does not replace an old compiled header when the new XML is older than that compiled file.**

Files: `docker/install-module.sh:221-227`, `README.md:66`, `README.md:69`, `README.md:71`.

vQmod keeps a compiled copy of each changed file in `vqmod/vqcache/`. It uses the copy again while the copy is not older than its reference time and not older than the source file (`vqmod.php:123`). The reference time is `time()` only in the request that finds no `mods.cache`. In all later requests, the reference time is the newest date of the XML files, the `vqmod/xml` folder and `pathReplaces.php`.

In `install-module.sh`, the first request after the delete is the `login` POST. On 1.5.6.4, a correct login calls `$this->redirect()`, which calls `exit()` (`admin/controller/common/login.php:14-21`, `system/engine/controller.php:27-31`). The request does not load the header. It writes a new `mods.cache`. The next page load then compares the old compiled `header.php` with the date of the XML from the package.

Probe `run-stale-probe.sh rm-cache` shows the result. The store has 0147, and vQmod compiled the header 30 minutes ago. An XML with version 0146 and a date of 1 hour ago arrives. After the delete and one request without the header, the admin page still has `client_translate_expert.js?v=04.01.0147`. vQmod writes no log entry.

When this occurs with the script:

- A run with `-p` and an older package, after a run with a newer package. Pass 1 named this case in finding 1.
- This needs the condition that `docker cp` keeps the file dates. The comment at `install-module.sh:222-224` says that it does. I did not measure it.

A newer package does not have this fault: its XML is newer than the compiled file, so vQmod compiles the file again. The first install does not have it. The four smoke checks do not find the fault, because the old header also loads `client_translate_expert.js`.

Effect: the store runs the header code of a different package than the files on disk. The XML has the version in `?v=`, so the XML is different in each version. Usually only the cache parameter of the JS and CSS is wrong. If the header code of two versions is different, a test of the older package gives a wrong result.

For a store owner the conditions are narrow: a copy that keeps the file dates, a compiled header that is newer than the package, and a first request that does not show the admin header. A storefront visit is such a request.

Fix: give vQmod a new XML date. Then the reference time is new in all requests. Probe `touch-xml` and probe `rm-cache-and-vqcache` both give the correct header.

- In `install-module.sh`, replace the `rm -f` with `touch /var/www/html/vqmod/xml/OpenCartTranslateExpertClient.xml`, or do both. Change the log line and the header comment to agree.
- In `README.md`, tell the store owner to delete `vqmod/mods.cache` and the files in `vqmod/vqcache/`. Pass 1 proposed these words. Use them in step 2, in the update paragraph and in the hint at line 71.
- Change `docker/README.md:60` to agree with the script.

**2. The header comment of `install-module.sh` has one long line.** `docker/install-module.sh:24`. The fix added text to the line and did not wrap it. The other lines of the comment block wrap at about 78 characters. This is style only.

## Assessment

**Ready to merge?** Yes.

Fixes 2 to 8 close their findings and cause no regression. The empty `OC_VERSION` guard does not stop a restart of an installed store, and the `build.sh` guard is correct with `set -e`. Fix 1 closes the first-install case and the newer-package case. The older-package case stays open, but it is a narrow test-tool case and the correction is one line, so it does not block the merge.
