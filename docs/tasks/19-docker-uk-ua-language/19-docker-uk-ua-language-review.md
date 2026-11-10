# Review: 19-docker-uk-ua-language

Reviewed: commit `cb55cb5` against `origin/main` (`git diff origin/main...HEAD`). The uncommitted plan edit in the working tree was ignored, as instructed. Files in scope: `docker/opencart/entrypoint.sh`, `docker/install-module.sh`, `docker/README.md`, `_version.txt`, and the task plan read as requirements.

Verification run here: `sh -n docker/opencart/entrypoint.sh` and `bash -n docker/install-module.sh` pass; the PHP snippet extracted from the heredoc passes `php -l`; feeding it to `php` via the same quoted heredoc under `sh -c 'set -eu; …'` with bad credentials prints `[opencart] adding uk-ua failed: Access denied for user 'x'@'localhost'` and exits 1 with the following command not executed. Unit tests: 24 passed, 0 failed, 6 skipped (no API key). To check the README claims I pulled the relevant OpenCart 2.3.0.2 / 3.0.3.9 / 4.0.2.3 files from the upstream GitHub repository (`system/library/language.php`, `catalog/controller/startup/startup.php` / `startup/language.php`, `install/opencart.sql`, `system/helper/db_schema.php`) into the scratchpad; nothing in the repo was touched.

## Strengths

- **Plan alignment is complete.** Everything in the plan's Approach is present: the insert sits between the `config.php` check and `rm -rf install`, schema detection is `SHOW COLUMNS … LIKE 'directory'`, the per-version column sets and values match the plan exactly, failure aborts under `set -e`, Check 4 follows the `|| { fail …; return 1; }` convention, and the three README edits (intro, smoke-check item 4, reinstall note) are all there. The two documented deviations (quoted heredoc instead of `php -r`; PHP-side `SELECT 1` guard instead of `INSERT … WHERE NOT EXISTS`) are both improvements: the heredoc avoids a second layer of shell quoting around SQL with Cyrillic, and the explicit check reads more clearly than a subselect insert.
- **The PHP snippet is genuinely PHP 5.6-safe.** Procedural mysqli only, no `[]`-free assumptions broken, no `??`, no type hints, no `STDERR` constant. `mysqli_report(MYSQLI_REPORT_OFF)` before `mysqli_connect` is the right way to make PHP 8.1 (which defaults to `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT`) behave like 5.6/7.4 so the `if (!$db)` / `if (!mysqli_query(...))` branches actually run instead of an uncaught `mysqli_sql_exception`.
- **The `STDERR`/stdin bug was found by testing the real invocation path**, not by reading. `file_put_contents('php://stderr', …)` is the correct fix and the comment explains why, so nobody "simplifies" it back later.
- **Heredoc hygiene.** `<<'PHP'` with a quoted delimiter means `$db`, `$message`, `$sql`, backticks and `$found` are untouched by the shell under `set -u`; the body lines and the terminator start at column 0 as `<<` (not `<<-`) requires. Verified with `sh -n` and by running it.
- **Column widths fit.** On 2.3/3.0 `code` is `varchar(5)`: `uk-ua` is exactly 5. `name` is `varchar(32)` in a `utf8` (3-byte) table: `Українська` is 10 characters, 20 bytes. The `utf8mb4` connection charset is converted server-side without loss for Cyrillic.
- **Check 4 is ordered sensibly.** It reports the actionable condition (`uk-ua` missing) before the generic count mismatch, and the README tells the reader what the failure on an old store means.
- **README reinstall note** now says explicitly that translations into `uk-ua` are lost on recreate, which is the thing a tester would otherwise be surprised by.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. **`docker/README.md:13` understates one storefront side effect on 2.3 and 3.0.** `catalog/controller/startup/startup.php` in both 2.3.0.2 (lines 54-85) and 3.0.3.9 (lines 77-108) detects the language from `Accept-Language` on a visitor's first request, and its fallback does `array_key_exists(strtolower($browser_language), $languages)`. A browser sending `uk-UA,uk;q=0.9,…` therefore lands on `uk-ua` automatically. Because there are no `uk-ua` description rows, the catalog queries (`pd.language_id = …`) return nothing: the home page, menu and category pages look empty until the visitor switches to English or something is translated. The admin is unaffected (no detection there), and OC 4.0.2.3's `startup/language.php` only reads `?language=` and the config default, so `oc40` is unaffected too. Suggest one sentence in the intro paragraph or in Notes: "On 2.3 and 3.0 the storefront picks `uk-ua` for browsers that send `Accept-Language: uk-UA`/`uk`, so with nothing translated yet the catalog looks empty there; switch to English via the flag menu." This is a documentation point only; the behaviour follows from the agreed no-description-rows scope. (The README's "flag image is missing" is accurate; selecting `uk-ua` on 2.3/3.0 does not fatal, because `Language::load()` uses `is_file()` and falls back to the default folder.)

2. **`docker/opencart/entrypoint.sh:41-67`: a failure here leaves a half-configured store that will not retry.** Unlike the preceding `config.php` check, this step runs after `config.php` has been written. If the PHP exits 1 (DB hiccup between the installer finishing and this connect), the container exits with the message, but a later `docker compose up` or `docker start` of the same container sees `DB_DATABASE` in `config.php`, skips the whole first-run block and serves a store with `install/` still present, files still root-owned (the `chown` never ran) and no `uk-ua`. Check 4 will catch the missing language and its hint ("recreate it") is in fact the right remedy, so this is low impact; `compose.yaml` has no `restart:` policy, so it does not loop. Two cheap options if you want it tighter: (a) move the `rm -rf install` and `chown` above the uk-ua step so a failure leaves a consistent store that merely lacks `uk-ua`; or (b) extend the `fail()` message with "recreate the container to retry". Not required by the plan.

3. **`docker/install-module.sh:331`: the Check 4 failure message reads awkwardly.** "language uk-ua is missing or disabled (the store was installed before it was added? recreate it)" — the parenthetical is a question followed by an imperative. Suggest: "language uk-ua is missing or disabled (store created before the entrypoint added it? recreate it with `up --force-recreate`)". Cosmetic.

4. **`docker/install-module.sh:333`: `-ne 2` is strict by design, but note the consequence.** The plan settled on "exactly 2", and that is implemented correctly. Just be aware that a tester who adds a third language through the admin to try something will see `-c` fail on every run afterwards. If that turns out to be annoying in practice, `-lt 2` plus the `uk-ua` check gives the same guarantee the entrypoint actually provides. No change needed now.

5. **`_version.txt`** is bumped to `04.01.0111`, which the plan notes is also used by the unmerged `feat/1-support-opencart-4-1` branch. Whichever merges second needs a re-bump. Already recorded in the plan; mentioning it only so it is not lost at merge time.

## Assessment

**Ready to merge? Yes** (the Minor items are optional polish, with item 1 the one I would actually add).

The entrypoint snippet does exactly what the plan asked, on the right PHP versions, with the right per-version columns, and its only non-obvious trap (`STDERR` undefined when PHP reads stdin) was found and fixed by running the real invocation path. The smoke check and the docs match the implementation. I found no PHP 5.6/8.1 compatibility problem, no shell quoting or `set -eu` hazard, no SQL injection surface (all values are literals), and no case where the insert can produce a duplicate or violate a NOT NULL column on any of the three schemas.
