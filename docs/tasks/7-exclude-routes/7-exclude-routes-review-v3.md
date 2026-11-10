# Review v3: 7-exclude-routes (shared library refactor)

Scope: `git diff HEAD` on branch `feat/7-exclude-routes`, plus the two untracked files. Base: `ac6bf72`.

Method:

- I compared the removed XML op and the removed OC4 method with the new library, line by line.
- I extracted the seven packages of `releases/v04.01.0117` into a scratch folder and read the built files.
- I ran `php tests/test_client_translate_expert_route_filter.php` (34 passed, 0 failed, local PHP 7.4.33).

I did not run a build. I did not do a check in a live store.

### Strengths

- **Behavior parity is complete.** The library has the same steps, in the same sequence, as the two removed copies. I found no input for which the result changes.
  - Route: the same cast, the same `trim` character list, the same default route fallback, the same `|` to `.` change.
  - Module page exemption: the same `strpos` test, before the rule loop.
  - Rule: the same `route=` cut, `index.php` fallback, `&` cut, `trim` and `|` to `.` change.
  - Match: the same `*` prefix test with the `$prefix === ''` guard, and the same exact test.
- **The default route stays correct for each caller.** The XML op passes `defaultRoute()`. The OC4 method passes the literal `common/dashboard`. Both agree with `ac6bf72`.
- **The library is PHP 5.6 safe.** It has no type hints, no `??`, no return types and no short syntax newer than PHP 5.3. The PHP 7+ syntax (`: bool`) stays in the OC4 marker block of the admin controller.
- **The namespace on OC4 is safe.** The library calls only functions and one constant (`VERSION`) without a `use` statement. PHP falls back to the global function and the global constant. The library names no other class, so no class lookup can fail in the namespace.
- **The built packages are correct.**
  - The six pre-4.0 packages hold a byte-identical copy of the source library in `upload/system/library/`.
  - The oc4.x package holds the library in `system/library/` with the namespace on line 2.
  - The OC 3.0 XML and the OC4 controller have the `module_` prefix on the setting key.
  - The OC 1.5 XML has `$this->data[` and keeps the `require_once` line unchanged.
  - The pre-4.0 controllers have no reference to the OC4 class, because the build strips the marker block.
- **The `require_once` paths agree with the package layout** of each version. The OC4 path uses the same `DIR_EXTENSION . 'client_translate_expert/system/library/'` pattern as the other library files.
- **The tests call the real class.** There are no mocks. The error handler changes each PHP warning into a failure, so the tests catch the empty-needle bug of review v2.
- **The library has no OpenCart dependency.** This makes the unit tests possible, and it keeps the cost on each admin page small.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

**I1. A missing library file now stops all admin pages (OC 1.5–3.0).**
`sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:14`

The op adds an unconditional `require_once` to `admin/controller/common/header.php`. At `ac6bf72` the op had no file dependency. If the library file was absent, the result was only a 404 for the JS and CSS. Now the result is a fatal error on each admin page that shows the header.

The file can be absent while the modified header is still active:

- OC 3.0: the Extension Installer uninstall deletes the uploaded files. It does not refresh the modification cache. The cached `header.php` in `storage/modification` keeps the `require_once` until the admin user refreshes the modifications. The admin user cannot open the Modifications page, because that page also shows the header.
- All versions: a partial upload through FTP, or a manual removal of the module files.

I did not reproduce this in a live store. The OC 3.0 uninstall sequence comes from my knowledge of the OpenCart core, so confirm it in the Docker oc30 store.

Fix: guard the load in the XML op. For example:

```php
$teFilterFile = DIR_SYSTEM . 'library/client_translate_expert_route_filter.php';
$teExcluded = true;
if (is_file($teFilterFile)) {
	require_once($teFilterFile);
	$teExcluded = ClientTranslateExpertRouteFilter::isExcluded(/* same arguments */);
}
```

A value of `true` for an absent file is the better default. An absent library means an incomplete package, so the module must not add its JS and CSS. The cost is one `is_file()` call for each admin page.

The OC4 path does not have this risk. The controller and the library are in the same extension folder, and the uninstall removes the event.

#### Minor (nice to have)

**M1. `defaultRoute()` has no test.**
`tests/test_client_translate_expert_route_filter.php`

The tests always pass the default route as an argument. No test calls `defaultRoute()`. A CLI run has no `VERSION` constant, so one test can assert only the fallback value `common/dashboard`. That test still protects the `defined('VERSION')` guard, which prevents an error on PHP 8.

**M2. Small gaps in the test cases.**
`tests/test_client_translate_expert_route_filter.php:85-109`

These cases are correct in the library, but no test locks them:

- A URL rule without `route=` with the default route `common/home` (OC 1.5).
- A prefix rule against a missing route, for example the rule `common/*` and the route `null`.
- A `*` that is not the last character, for example `catalog/*/edit`. The rule is an exact rule.

**M3. The docs are not fully consistent about the two test scripts.**

- `.claude/skills/dev-workflow/SKILL.md:229` still says "The test script has no filter" and names only `test_vh_google_translator.php`. The next paragraph names two scripts.
- `.claude/agents/ote-code-reviewer.md:27` names only `test_vh_google_translator.php` in the list of permitted commands. The words "and similar commands" cover the new script, so this is optional.

**M4. The `## Done` section of the plan is stale.**
`docs/tasks/7-exclude-routes/7-exclude-routes_plan.md`, section `## Done`

The section still says that the final version is 04.01.0116 and that there were two reviews. It does not name the library. Update it when the live check is complete.

**M5. Small STE points in the new plan section.**
`docs/tasks/7-exclude-routes/7-exclude-routes_plan.md`, section `## Update 10.11.2026 (shared library)`

- "The two copies of the match logic (...) are replaced." uses the passive voice. Write "The shared library replaces the two copies of the match logic (...)."
- "with the empty-prefix guard removed (in a copy under `tmp/proof/`), 5 tests fail" puts a long condition in a non-standard form. Write "If you remove the empty-prefix guard, 5 tests fail on `strpos(): Empty needle`."

The edits in `CLAUDE.md` and `.claude/**` obey the STE rules.

### Notes (no action)

- A request with an array route (`?route[]=x`) gives an "Array to string conversion" message from the `(string)` cast. The committed code had the same cast, so this is not a regression.
- The tests load the source library, which has no namespace. The namespaced copy differs only in line 2, so a second run against the OC4 copy is not necessary.

### Assessment

**Ready to merge?** With fixes.

The refactor keeps the behavior of `ac6bf72` exactly, and the packages for all versions are correct. Fix I1 before the merge, because the new unconditional `require_once` in the admin header can stop the full admin panel if the library file is absent. The Minor items do not block the merge.
