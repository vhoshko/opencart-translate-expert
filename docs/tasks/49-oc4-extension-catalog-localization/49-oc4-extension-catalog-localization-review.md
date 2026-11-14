# Review: 49-oc4-extension-catalog-localization

## Strengths

- The change is small. `isOc4ExtensionName()`, `getLocalizationTargetFolder()` and `getLocalizationRoots()` now use `<scope>/language`. Admin and catalog share one path.
- The save guard stays strict. It checks the safe name, the scope, the existing scope language folder and the target language extension.
- `getLocalizationRoots()` still skips the language extensions of the source and the target. The core root covers them.
- No new PHP syntax. PHP 5.6 safe. No `build.sh` change is needed: no new route, class or setting key.
- The tests mirror the admin cases. They cover roots, analysis, includes, reverse direction, save and bad-root cases. The bad-root cases include a catalog-only extension in the admin scope and the reverse.
- `php -l` passes. The localization test passes: 683 passed, 0 failed.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

- `client_localization_translate_expert.php:177`: `isLocalizationScope($scope)` in `isOc4ExtensionName()` is a safety net. It is cheap. The callers already validate the scope, so it is redundant, but it is harmless. Keep it.
- `client_localization_translate_expert.php:176`: the safe-name regex is also a safety net. Keep it, because `$name` comes from user input in the save path.
- `client_localization_translate_expert.php:214`: `getLocalizationRoots()` calls `isLocalizationScope()` before the version check. The core root is built before this, so the order has no effect. No change needed.

## Assessment

**Ready to merge?** Yes.

The change is scope-generic and consistent with the admin logic. It matches the verified OC 4.0.2.3 and 4.1.0.0 loading behavior in the plan. I found no bugs, security gaps or compatibility problems. The live OC 4.x run stays unverified, as the plan states.
