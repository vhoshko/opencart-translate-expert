# 104-te-select-multi-important review

Scope: `git diff HEAD` on branch `fix/104-te-select-multi-important` and the plan `104-te-select-multi-important_plan.md`.

Probe: `docs/tasks/104-te-select-multi-important/tmp/probe_general_important_regex.php`.

### Strengths

- The class `te-select-multi` is on exactly the 4 multiple selects in both templates. In the `.tpl`, these are lines 209, 241, 257 and 269. In the `.oc4.twig`, these are lines 209, 241, 253 and 265. The other selects (`te-langbar-from`, `te-langbar-to`, `input-client_translate_expert_status`) are single selects. They do not need the rule.
- The JS file builds no `<select>` element. No other file in `sources/upload` (the vendor `oc15/te/` files excluded) has a multiple select or a `size=` select. Thus, the removal of the `select[multiple]` and `select[size]` rules removes the height rule from no other element.
- The CSS rule is correct and small. `.te-app .te-select-multi` has the specificity (0,2,0). With `!important`, it wins over a theme `!important` rule on `select` (0,0,1) or `.form-control` (0,1,0). All 4 selects are in a `.te-app` container in both templates (`#te-app`, line 22).
- The `!important` is on the module class only. No general `select` or `input` rule gets `!important`.
- The test checks the class on each select per template and the rule with `!important`. The brief confirms that a mutation (rule without `!important`) makes the test fail.
- The CSS file ends with a newline. The OC4 Twig name test and the settings cards test still pass. No zip or build folder remains in the repo root.
- No document outside `docs/tasks/` refers to the removed rules. The only remaining text is the CLAUDE.md sentence that says the test fails if the rules come back, and the test itself. The `select[multiple]` hits in `oc15/te/bootstrap.css` are vendor Bootstrap 3 rules, not module rules.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. `tests/test_client_translate_expert_select_height.php:70` — the guard "no `!important` in a rule for a general select or input" has false passes. The probe shows that the regex does not catch these cases:
   - A rule after a comment: `}\n/* note */\n.te-app select { height: 30px !important; }`. Almost all rules in the CSS file have a comment above them. Thus, this is the most probable form of a regression.
   - A descendant scope: `.te-app .te-field__control select { ... !important }`.
   - A selector that is not first in a list: `.te-app .x, .te-app select { ... !important }`.

   The `^` also matches only the start of the file, because the pattern has no `m` flag. The CLAUDE.md sentence "The test fails if a rule for a general `select` or `input` has `!important`" is thus wider than the real check.

   Fix: remove the comments first with `preg_replace('#/\*.*?\*/#s', '', $css)`. Then read each `selector { body }` pair with `preg_match_all('/([^{}]+)\{([^}]*)\}/', ...)`. If the body has `!important`, split the selector at each comma. Fail if the last compound selector of a part starts with `select` or `input` and has no class (`/(?:^|\s)(?:select|input)(?:\[[^\]]*\])*(?::[\w-]+)*\s*$/`). Add the probe cases to the test as self-checks, or keep the probe as a record.

2. `tests/test_client_translate_expert_select_height.php:61` — the check `substr_count($html, 'te-select-multi') === 4` catches a fifth select with the class. It does not catch a fifth multiple select without the class. Before this change, the general `select[multiple]` rule gave such a select a height fallback. Now the class is the only rule. Thus, a new multiple select without the class shows the problem of issue #104 again, and no test fails.

   Fix: count all `<select` tags with `multiple` in each template. Check that each one has the class `te-select-multi`. For example, use `preg_match_all('/<select\b[^>]*\bmultiple\b[^>]*>/', $html, $all)` and check each match for the class.

3. `tests/test_client_translate_expert_select_height.php:59-60` — the stock status select has its own regex and check outside the `$lists` loop. This repeats the class check from the loop. A second array (id => rows, with `6` for the stock status) or a separate id list for the class check removes the copy. This is style only.

### Assessment

**Ready to merge?** Yes. The CSS and template change is correct and complete for all 4 multiple selects in both templates. No other select loses its height rule. The 3 minor items are test gaps that do not change shipped behavior. Items 1 and 2 make the guards match the CLAUDE.md text and protect the removal of the general rules.
