# 100-table-select-height review

Scope: `git diff HEAD` on `fix/100-table-select-height`, plus `tests/test_client_translate_expert_select_height.php` and the plan. The plan includes the section "Update 24.11.2026 (user feedback)". This review applies to the current files with `size="15"`.

## Strengths

- The change is small and agrees with the updated plan. Both templates change `size="6"` to `size="15"` on `#te-analization-table` only (`client_translate_expert.tpl:209`, `client_translate_expert.oc4.twig:209`). The 3 filter selects keep `size="6"`.
- The value `size="15"` is the same in the 2 templates, the test, `CLAUDE.md` and `.claude/project-workflows.md`. No `size="10"` remains in `sources/`, `tests/`, `CLAUDE.md` or `.claude/`. Only the update note in the plan contains `size="10"`.
- The `.tpl` uses `form-control` (Bootstrap 3) and the `.oc4.twig` uses `form-select` (Bootstrap 5). The CSS rule covers both classes.
- The CSS rule is in the `.te-app` scope. Only the 4 multiple selects of the module get the rule. The admin theme outside the module does not change.
- The specificity of the new rule is (0,3,1). This is higher than each height rule in the bundled CSS and in the theme CSS that the user showed (see Check 1).
- The new test is static and fast. The mutation run shows that it detects a removal of the change. It passes 8 of 8 on the current files.
- The `CLAUDE.md`, `.claude/project-workflows.md` and `.claude/agents/ote-build-test.md` entries agree with the format of the other test entries.

## Checks from the brief

### 1. Specificity against Bootstrap and the admin themes

- Bundled `sources/upload/admin/view/stylesheet/oc15/te/bootstrap.css` (Bootstrap v3.3.5, OC 1.5 only, loaded by the `<!--OC15-TE-->` line in `build.sh:172`):
  - `.form-control { height: 35px; }` at line 2076. The specificity is (0,1,0).
  - `select[multiple], select[size] { height: auto; }` at line 2061. The specificity is (0,1,1). This rule already wins over `.form-control`.
  - The file has no `select[multiple].form-control` rule. The only `!important` height rule is `.navbar-collapse.collapse` at line 3181. It does not apply to a select.
  - The `.form-group-sm` and `.form-group-lg` rules for `select[multiple].form-control` have the specificity (0,3,1). The module templates do not use these classes.
- The admin theme of the user has the same 2 rules: `select[multiple], select[size] { height: auto; }` (0,1,1) and `.form-control { height: 36px; }` (0,1,0). In that theme the `size` attribute already sets the height, and the new CSS rule has no effect. This agrees with the bundled file.
- Bootstrap 5 (OC 4): `.form-select` has no fixed height. The `.form-select` part of the rule is a no-op on a standard theme.
- Result: on all known CSS the new rule is a no-op. It is a safety net for a theme with a fixed height that is more specific than (0,1,1). It loses only to an `!important` height rule or to an inline `style` attribute. The other admin theme CSS is not available in this container, so this part is unverified. The CSS comment (`client_translate_expert.css:748`) describes the rule as a guard. That is correct.

### 2. Other places that set the height or the size

- `client_translate_expert.js`: no code sets `size`, `height` or a CSS height on `#te-analization-table`. The JS only reads the selected values (`:932`, `:1550`, `:1795`). No select plugin (select2, chosen, selectpicker) replaces the select.
- `client_translate_expert.css`: no other rule sets the height of a select in `.te-app`. `.te-app .te-lcm-row .form-control` (`:724`) applies to the language code map rows only.
- No inline `style` attribute is on the 4 multiple selects in the 2 templates.

### 3. Test robustness

The test is not a tautology. It reads the real files and fails 3 of 8 checks when the change is removed. Some regular expressions are fragile (see Minor 1 and Minor 2).

### 4. ASD-STE100 of the docs text

The new lines in `CLAUDE.md`, `.claude/project-workflows.md` and `.claude/agents/ote-build-test.md` agree with the rules. The sentences are short, active and in a simple tense. There are no semicolons, contractions or Latin abbreviations. The noun clusters have 3 words maximum ("select height test"). The term "multiple selects" is used in all 3 files. The update section of the plan also agrees with the rules. The test docblock and the CSS comment are source code comments, so they are out of scope.

### 5. Layout with 15 rows

- 15 rows is approximately 270 px to 310 px high, plus padding. The exact value depends on the font size of the theme (12 px in the bundled Bootstrap 3 file, approximately 16 px in Bootstrap 5).
- The select is in `.te-field__control` of a 2-column grid (`minmax(160px, 1fr) 3fr`, `align-items: start`). The label stays at the top of the row. A higher control does not break the grid.
- On a screen narrower than 768 px the grid has 1 column. Mobile browsers usually show a multiple select as a native picker, so the `size` value has a small effect there.
- On a laptop screen with a height of approximately 768 px, the select, the buttons above it and the admin header fill most of the visible area. The filters and the action buttons below the select move down by approximately 100 px compared to `size="10"`. The user can scroll, so this is acceptable.
- A standard OpenCart database has more than 100 tables, so a larger list helps the user. The user chose this value, so I do not suggest a change. An optional alternative is a CSS `resize: vertical` on the select, so that each user can set the height.

## Issues

### Critical (must fix)

None.

### Important (should fix)

None.

### Minor (nice to have)

1. `tests/test_client_translate_expert_select_height.php:37`: the regular expression `/<select id="te-analization-table".*?class="form-[a-z]+">/s` requires `id` as the first attribute and `class` as the last attribute. The class value must also be one word. If a later change adds a class (for example `class="form-select mb-2"`, as the filter selects have), the lazy `.*?` with the `s` modifier extends past the `>` of the select. It stops at the next text that ends with `class="form-..."` and `>`. Then the 3 checks read the wrong text. They can fail with a false result, or pass with a false result if the other text contains `size="15"`. Use a pattern that stays in one tag, for example `/<select\b[^>]*\bid="te-analization-table"[^>]*>/`.
2. `tests/test_client_translate_expert_select_height.php:45-46`: the CSS check also passes if the rule is in a comment, or if the selector is `.form-control-foo`. Remove the comments from `$css` before the match (`preg_replace('#/\*.*?\*/#s', '', $css)`). Also add `\s*[,{]` after the class name.
3. `tests/test_client_translate_expert_select_height.php`: the test does not fail on a PHP warning. The other static tests in `CLAUDE.md` say "Any PHP warning fails the test". If a template file is missing, `file_get_contents()` gives a warning and the test continues with `false`. The checks then fail, so the result is correct. An `is_file()` check with a clear FAIL message is better.
4. `CLAUDE.md:113` says "Select height tests" and `CLAUDE.md:159` says "The select height test". The file has one test file. Other entries also mix the singular and the plural, so this is optional.

## Assessment

**Ready to merge?** Yes. The `size="15"` change is correct and the same in both templates, the test and the docs. The CSS rule is a no-op on the bundled Bootstrap 3 file, on Bootstrap 5 and on the theme of the user, and it is a correct safety net for other themes. The minor test items make the test more robust, but they do not block the merge.
