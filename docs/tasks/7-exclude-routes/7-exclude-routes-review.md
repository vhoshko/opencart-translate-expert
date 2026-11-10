# 7-exclude-routes review

Scope: `git diff HEAD` on branch `feat/7-exclude-routes`, version 04.01.0114.

Checks that I did:

- I read the full diff and the plan.
- I ran `php -l` on the source controller. It has no syntax errors.
- I ran `php tests/test_vh_google_translator.php`. The result is 24 passed, 0 failed, 6 skipped.
- I unpacked the packages from `releases/v04.01.0114` into a scratch folder outside the repository.
- I read the built OCMOD XML for oc1.5 and oc3.0, and the built controllers and templates for oc1.5, oc2.3, oc3.0 and oc4.x.

I did not install a package in a store. No runtime check of an excluded page exists in this review.

### Strengths

- The change is small and matches the plan step by step.
- The two `build.sh` fixed lists (lines 127 and 392) include the new key. The built oc3.0 and oc4.x packages use `module_client_translate_expert_excluded_routes` in the controller, the template and the XML.
- The OCMOD XML code uses PHP 5.6 syntax only. The PHP 7+ syntax (`: bool`) is inside the `OC4_EVENT` markers of the admin controller. The built oc1.5, oc2.3 and oc3.0 controllers do not contain `isCurrentRouteExcluded`.
- The `$data[` → `$this->data[` rewrite for OC 1.5 applies correctly to all new lines in the XML.
- `{client_version}` moved into the controller op, and the build still replaces it (`?v=04.01.0114` is in the built XML).
- The local variables in the header op have a `te` prefix. This prevents a name conflict inside the core header controller.
- The check of the module page comes before the rule loop. A `*` rule cannot lock the user out of the settings page.
- On an excluded page, the language query does not run (OC ≤ 3.0 and OC4).
- An existing install has no stored value. `(string)null` gives an empty list, so the upgrade changes no behavior.
- The three language files have the two new strings. The UI is in the `.tpl` and in the `.oc4.twig`, each in its own syntax.
- The textarea output follows the standard OpenCart pattern. The request layer escapes the POST value before the save.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

1. **The help text examples do not match the plan and can mislead the user.**
   Files: `sources/upload/admin/language/en-gb/extension/module/client_translate_expert.php:76`, `ru-ru/...:84`, `uk-ua/...:77`.
   The plan says: "The help text tells the user to write `catalog/product*` for all methods." The help text gives `catalog/product/*` and `catalog/product.*` as examples. These rules match `catalog/product/edit` or `catalog/product.form`. They do not match the list page `catalog/product`. A user who copies the example gets a page where the module still loads, with no message.
   Fix: change the examples to `catalog/product*`, or add a sentence that says the list page needs its own line. Change all three languages.

#### Minor (nice to have)

1. **OC ≤ 3.0 still injects one inline `<script>` on an excluded page.**
   File: `sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:61-70`.
   The `<header ` op always writes the three `document.js_const_*` values (as `0`, `0`, `{}`). OC4 injects nothing. The requirement says "no JS/CSS injection". The inline block is harmless, but the two code paths behave differently. If you want the same behavior, build this block in the controller op as a second `$data` string, like `head_html`.

2. **The default route is not correct for OC 1.5.**
   File: `OpenCartTranslateExpertClient.ocmod.xml:15`.
   If the request has no `route`, the check uses `common/dashboard`. The default admin page of OC 1.5 is `common/home`. The OC 1.5 header op is unverified in the plan, so the effect is small.

3. **Some natural input forms never match, and the user gets no feedback.**
   Files: `OpenCartTranslateExpertClient.ocmod.xml:19-23`, `client_translate_expert.php:838-842`.
   - A rule with a trailing slash (`catalog/product/`) does not match. The code trims `/` from the two ends of the route, but only from the start of the rule.
   - A rule copied from the address bar with more parameters (`route=a/b&user_token=...`) does not match. The stored value has `&amp;` in it.
   - A full URL (`index.php?route=a/b`) does not match.

   Fix (optional): cut the rule at the first `&`, remove the text up to `route=` at any position, and use `trim($rule, '/')`. Apply the same change to the two copies.

4. **No automated test covers the two real copies of the match logic.**
   `docs/tasks/7-exclude-routes/tmp/match_test.php` tests a third copy. The plan accepts the duplicate and the missing regression test. The risk is that a later edit changes one copy only. The comment at `client_translate_expert.php:826` points to the XML copy. Add the reverse pointer to the XML comment at line 13.

5. **The new strings are below the `// char count` comment in the language files.**
   They are not related to the character count. Move them or add a comment line. This is cosmetic.

### Assessment

**Ready to merge?** With fixes.

The implementation is correct in the source and in the built packages for all version groups, and it is backward compatible. Correct the help text examples (Important 1) before the merge, because they contradict the plan and give the user a rule that does not exclude the list page. A check of an excluded page in the Docker stores (OC 2.3, 3.0, 4.x) is still necessary, because this review did not do a runtime check.
