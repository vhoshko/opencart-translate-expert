# 56-te-ui-foundation review v2

Reviewed range: `git diff HEAD` on top of `d589eaa`, plus the untracked files in `tests/js/`. Scope: only the fixes after the first review (findings 1, 3, 4, 5 and 6). The settled design points of the brief are not reviewed again.

Checks that I ran:

- `node tests/js/test_client_translate_expert_js_helpers.js`: 88 passed, 0 failed (44 for each jQuery version).
- Clean `npm ci` from a copy of `package.json` and `package-lock.json` in `tmp/npmci/`: exit 0, 108 packages in 4 seconds. The lock file is byte-identical after the install. `contextify` (optional, native) is absent after the install, so npm skipped it.
- Mutation checks against copies of the JS in `tmp/npmci/`: without `!Array.isArray(response.responseJSON)`, the check "a jqXHR with an array responseJSON" fails for both jQuery versions. Without the `Array.isArray(response)` branch, the check "an array object is not a JSON object" fails for both. The real file gives 88 passed.
- A test copy with a wrong JS path exits with code 1. The test cannot pass with a missing source file.
- `npm audit` on the lock file: 8 findings (4 critical, 4 moderate). All are in the `jquery1` subtree (`jquery@1.7.2`, `request`, `xmlhttprequest`, `form-data`, `qs`, `tough-cookie`, `uuid`, `jsdom@0.2.19`).
- A lock-only resolve without `jquery1` in `tmp/nojq1/`: 64 lock entries instead of 112. So 48 entries exist only for `jquery1`.
- `grep te_error_internal` in `sources/`, `tests/`, `build.sh`, `build.release.sh` and `docs/pocs/`: no match.
- `client_translate_expert_route_filter.php:23`: a route that contains `module/client_translate_expert` is never excluded. So the header always writes `document.te_i18n_global` on the module page.
- `git ls-files --others --exclude-standard tests/js`: exactly `package.json`, `package-lock.json` and the test script. `node_modules` is ignored by `.gitignore:23`.

### Strengths

- The JS test is a real test, not a mock. It loads `sources/upload/admin/view/javascript/client_translate_expert.js` from a path relative to `__dirname`, runs the existing init code of the file in jsdom, and repeats every check with jQuery 3.7.1 and jQuery 1.7.2. The `check()` helper accepts only `=== true`, catches an exception for each check, and the process exits with code 1 on any failure.
- The two new checks for finding 4 detect the two guards independently (see the mutation checks above). The brief already recorded the status-0 mutation.
- The lock file is pinned (`lockfileVersion` 3, exact versions in `package.json`) and `npm ci` reproduces it from a clean folder.
- Finding 3: `getGlobalI18nJson()` (controller:955-966) now has the same shape as `isCurrentRouteExcluded()` (controller:971-979): an unguarded `require_once` and FQCN static calls. Both sit between the `OC4_EVENT_START` and `OC4_EVENT_END` markers (controller:910 and 980), so the return type hint is allowed.
- Finding 4: `parseResponse` (JS:1596-1604) now treats an array object and an array `responseJSON` as non-JSON, the same as `parseJsonObject()`. The details hold `JSON.stringify(response)`, so the user sees the array. `Array.isArray` is ES5.
- Finding 5: `te_error_internal` is gone from the 3 language files and the controller key list. No other file references the key. `teT('te_global_error_internal')` (JS:1588) works on the module page because the route filter never excludes the module route. The test covers this fallback with `document.te_i18n_global` only.
- Finding 6: the comment on `teAlerts` (JS:1532) names the `.te-app` constraint.
- The docs are consistent: `CLAUDE.md`, `.claude/project-workflows.md` and `.claude/agents/ote-build-test.md` all name the same command. The test output format (`=== Results: N passed, M failed, 0 skipped ===`) is the same as the PHP tests.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. **`tests/js/package.json:11`, `jquery1` = `npm:jquery@1.7.2`.** The test reads only `node_modules/jquery1/tmp/jquery.js`, one static file. The npm package of jquery 1.7.2 is a Node wrapper. It pulls in 48 lock entries that the test never runs: `request@2.88.2`, `xmlhttprequest`, `jsdom@0.2.19` and `contextify` (optional, native, with an install script). Every `npm ci` shows 5 deprecation warnings, one `EBADENGINE` warning (`node: 0.6`) and `8 vulnerabilities (4 moderate, 4 critical)`. None of this code runs, so there is no risk at test time. The cost is maintenance: a permanent "4 critical" line in each test run makes the audit output useless, and the `contextify` build attempt depends on npm to skip a failed optional dependency. Fix: put the exact OC 1.5 admin file `jquery-1.7.1.min.js` (MIT) in `tests/js/vendor/`, point `jqueryFile()` at it, and remove `jquery1` from `package.json` and the lock file. This also tests the real OC 1.5 version instead of 1.7.2. The lock file then has 64 entries, all for jsdom 24 and jQuery 3.7.1. Do this in the improvements pass.

#### Minor (nice to have)

2. **`tests/js/test_client_translate_expert_js_helpers.js:25-40`.** A failed check prints only its message. A developer who sees `[FAIL] teIcon maps an FA 4 name to the FA 6 name` has no expected and actual value. The PHP tests have the same limit, so this is not a convention break. Optional: let `check()` accept a value and an expected value, and print both on failure.

3. **`.claude/project-workflows.md:52` and `.claude/agents/ote-build-test.md:79`.** The workflow table says "Run `npm ci` one time for each container". The agent runs `npm ci --silent` on every test run. Both work, because `npm ci` takes 4 seconds. Make the two texts agree, for example: "`npm ci` is safe to repeat. It removes and reinstalls `node_modules`".

### Assessment

**Ready to merge?** Yes. The five fixes are correct, small and covered by the test. The new test exercises the real file with two jQuery versions, cannot pass on a missing file, and detects a mutation of each new guard. The one Important finding is about the dependency footprint of the jQuery 1.7.2 npm package, not about the code under test. Do it in the improvements pass.
