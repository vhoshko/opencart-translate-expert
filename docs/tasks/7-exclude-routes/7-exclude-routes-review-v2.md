# 7-exclude-routes review v2

Scope: `git diff HEAD` on branch `feat/7-exclude-routes`, version 04.01.0115. This pass examines the fixes for the findings I1 and M1–M5 of the first review.

Checks that I did:

- I read the full diff, the plan and the first review.
- I compared the two copies of the match logic line by line.
- I ran `php -l` on the source controller. It has no syntax errors.
- I ran `xmllint --noout` on the source OCMOD XML. It passes.
- I ran `php -r 'var_dump(strpos("abc",""));'` on PHP 7.4.33. The result is a warning and `false`.

I did not run the build, the unit tests or a store. The brief gives the build result and the test result as facts.

### Strengths

- I1: the three language files give `catalog/product*` as the example, with the same structure in each language. The text also tells the user that a full admin URL is permitted, which agrees with M3.
- M1: `OpenCartTranslateExpertClient.ocmod.xml:46-58` builds the two HTML strings in one place. The two strings are empty on an excluded page, so OC 1.5–3.0 and OC4 now behave the same. No reference to the three old `$data` values stays in `sources/`.
- M1: the `(int)` casts and the `?: '{}'` fallback from the old template op are still there. The header script cannot become invalid JS when a setting has no value.
- M2: `OpenCartTranslateExpertClient.ocmod.xml:15-17` selects the default route with `version_compare(VERSION, '2.0', '<')`. The OC4 copy keeps `common/dashboard`, which is correct for OC4.
- M3: the two copies do the same steps in the same sequence: trim, text after `route=`, cut at `&`, trim spaces and `/`, `|` to `.`. The cut at `&` also works for the stored value, because the stored value has `&amp;`.
- M4: the XML comment (line 14) and the controller comment (line 826) point to each other.
- M5: the new strings have their own comment in the three language files.
- The check of the module page is still before the rule loop in the two copies.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. **A rule that is only `*` gives a PHP warning on each admin page on PHP 5.6–7.4, and the result depends on the PHP version.**
   Files: `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:35-36`, `sources/upload/admin/controller/extension/module/client_translate_expert.php:853-854`.
   For the rule `*`, `substr($rule, 0, -1)` is an empty string. On PHP 5.6–7.4, `strpos($route, '')` gives `Warning: strpos(): Empty needle` and returns `false`. On PHP 8, it returns `0`.
   The effects are:
   - PHP 5.6–7.4 (OC 1.5–3.0): the rule excludes nothing. Each admin page writes a warning to the error log, or shows it in the page header if the error display is on.
   - PHP 8 (OC4, and OC 3.0 on PHP 8): the rule excludes all pages but the module page.

   The first review did not find this defect. The M3 fix makes more inputs become `*`: `/*`, `*/`, `route=*` and `index.php?route=*&user_token=...`.
   Fix: calculate the prefix first. If the prefix is empty, do not call `strpos`. Make a decision: an empty prefix matches all routes, or the check ignores the rule. Apply the same change to the two copies. The line `$prefix === '' || strpos($route, $prefix) === 0` gives the "matches all routes" behavior on all PHP versions.

#### Minor (nice to have)

1. **The plan `## Approach` section does not agree with the code after the fixes.**
   File: `docs/tasks/7-exclude-routes/7-exclude-routes_plan.md`, `## Approach`.
   The section still says "remove a leading `route=` and a leading `/`" and "`client_translate_expert_is_enabled` is 0". The `## Update 10.11.2026` section gives the new behavior, so the record is complete. A short pointer from `## Approach` to the update prevents a wrong reading.

2. **A pasted URL that has no `route=` never matches, and the user gets no feedback.**
   Files: the same lines as M3 in the two copies.
   Example: the dashboard URL `index.php?user_token=abc` on a store that opens the dashboard with no route. The help text says "You can also paste a full admin URL". The effect is small, because the rule `common/dashboard` (or `common/home` on OC 1.5) does the job. No change is necessary if you accept this limit.

### Status of the first review findings

| Finding | Status |
|---|---|
| I1 help text examples | Fixed |
| M1 inline script on an excluded page | Fixed |
| M2 default route on OC 1.5 | Fixed |
| M3 input forms | Fixed. It makes Important 1 easier to reach |
| M4 cross-reference comments | Fixed |
| M5 comment for the new strings | Fixed |

### Assessment

**Ready to merge?** With fixes.

The six fixes are correct, and the two copies of the match logic are the same. Correct the empty prefix case (Important 1) in the two copies before the merge, because one line in the setting can put a warning on each admin page on PHP 5.6–7.4. This review did no runtime check in a store.
