# 25-route-null-check

## Settings
- Planning model: Opus 5.5 (inline). Review model: Fable 5.2 (`ote-code-reviewer` with `model: fable`).
- Flow: stripped-down. The user agreed on 2026-11-11. No Explore agent and no sanity-check agent.
- Build and tests: yes. Commit, push and PR: no (the workflow never commits).
- Standing auto-approve for review findings: no.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/25

If the admin URL has no `route` parameter, `getParameter('route')` returns `null`. An example is the bare dashboard URL `index.php?token=...`. Two callers then call `route.indexOf(...)` and throw a TypeError:

- `sources/upload/admin/view/javascript/client_translate_expert.js:211` (`updateSimpleElements()`)
- `sources/upload/admin/view/javascript/client_translate_expert.js:268` (`updateHandyProductManagerElements()`)

`addTranslateExpertElements()` calls both functions first. The first throw in `initTranslateExpert()` stops the `setInterval` and `initModule()`. All OC versions ship this JS file, so all versions have the bug.

## Approach
Change the two callers to `var route = getParameter('route') || '';`. Do not change `getParameter()`. The token callers (`getOcToken()`) only join the value into a string, so a `null` token does not throw. They stay out of scope.

## Assumptions
- ✅ verified: only lines 211 and 268 call `getParameter('route')`. Evidence: a grep over `sources` for `getParameter(` in `*.js`, `*.tpl` and `*.twig`.
- ✅ verified: `build.sh` does not rewrite `getParameter('route')` or `|| ''` in the JS. Its JS rewrites touch only `'extension/module'`, `"extension/module"` and `token`.

## Steps
- [x] Code: add `|| ''` to the two `getParameter('route')` callers
  Findings: a 2-line diff. `node --check` passes.
- [x] Bump version + build + test
  Findings: 04.01.0123 → 04.01.0124. Route filter: 40 passed. Translator: 24 passed, 6 skipped (no API key). The fixed line appears twice in each package (oc1.5–oc3.0 and oc4.x).
- [x] Independent review → 25-route-null-check-review.md
  Findings: Fable 5.2 found the fix clean. The build rewrites do not touch the fixed lines. One Minor finding is outside scope: `updateBatchEditorElements()` (`:257-260`) reads `nameMatch[1]` without a null check.
- [x] Address review findings (the user picks which to fix or skip)
  Findings: the user chose a separate issue for the Minor finding. This branch has no code change from the review.
- [x] ~~Re-review?~~ Not necessary. No fix pass occurred.
- [x] Improvements pass → 25-route-null-check-improvements.md

No regression test: the repository has no JS test harness.

## Done
- The two `getParameter('route')` callers in `client_translate_expert.js` now fall back to `''`. A route-less admin URL no longer throws.
- Final version: 04.01.0124. All packages have the fix. The tests are green.
- Deferred: the null check in `updateBatchEditorElements()` goes to a separate issue.
- No browser verification. The repository has no JS test harness.
