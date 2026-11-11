# 25-route-null-check review

Scope: `git diff HEAD` on branch `fix/25-route-null-check`, file `sources/upload/admin/view/javascript/client_translate_expert.js` only. The review ignores `_version.txt` and `docs/tasks/**`.

## Strengths

- The diff is two lines. It changes only the two callers that the issue names (`:211` and `:268`).
- `getParameter('route') || ''` is ES5-safe. It adds no new syntax for old admin themes or old browsers.
- The fix keeps `getParameter()` as it is. The token callers get the same value as before.
- An empty string gives `indexOf(...) == -1`. Thus the two functions return early on a route-less page, which is the correct behavior.
- The plan matches the issue, and the diff matches the plan. There are no deviations.
- `node --check` passes on the changed file.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. `sources/upload/admin/view/javascript/client_translate_expert.js:258-259` (`updateBatchEditorElements()`), not part of this diff. `nameRegex.exec(...)` can return `null`, and the next line reads `nameMatch[1]`. An example is a nameless `textarea` with the id `description_description`, which matches the selector but has no digits. This throw has the same effect as the bug in issue #25, because the function is in the same `addTranslateExpertElements()` chain. It does not depend on the `route` parameter, so it is outside the symptom of this issue. A possible fix is a `if (!nameMatch) return;` line in the `each` callback. A separate issue is a better place for it.

## Checks done

Null-sensitive paths on a route-less admin page, in the `initTranslateExpert()` chain:

- `getParameter(` has four hits in `sources`: the two fixed callers, the definition, and `getOcToken()`. No other caller reads `route`.
- `getOcToken()` (`:513`) and `showTranslatedCharCount()` (`:1017-1021`) only join the token into a URL string. A `null` token does not throw.
- `updateGrapesJsElements()` guards each property before it reads the property.
- `updateSimpleElements()` and `updateHandyProductManagerElements()` now return before their unguarded lookups on a route-less page.
- `checkElementSupportTranslateExpert()` (`:330-343`) checks `nameMatch` for `null` before it reads the match.
- `addTranslateExpertElements()` `:311`: if the URL has no `index.php`, `indexOf` returns `-1` and `substr(0, -1)` returns `''`. The image URL becomes relative. It does not throw.
- `initModule()` only binds jQuery click handlers and calls `showTranslatedCharCount()`. Missing elements give empty jQuery sets, which do not throw.

Build rewrites of this JS file:

- OC 1.5-2.2: `'extension/module'` → `'module'` and `"extension/module"` → `"module"`. The fixed lines do not contain these strings. The literal `'handy_product_manager/productList'` also does not match.
- OC 3.0: `token` → `user_token`, global. The fixed lines do not contain `token`. `getOcToken` has a capital `T`, so its name does not change, and `getParameter('token')` correctly becomes `getParameter('user_token')`.
- OC 4.x: the `js_const_module_prefix` value, the `/client_translate_expert/<method>` list, the modal rewrites and the image path. None of these patterns match the fixed lines.

Other checklist items (PHP syntax, SQL, i18n, `install.php`, settings, templates, Google API usage) do not apply. The diff has no PHP, template, language or schema changes.

Testing: the repository has no JS test harness, so there is no regression test. This is an accepted limit from the brief.

## Assessment

**Ready to merge?** Yes.

The change removes the two `null` dereferences that the issue names, and no other path in the init chain throws because the `route` parameter is missing. No build rewrite touches the fixed lines. The one Minor item is an older, independent defect and does not block this fix.
