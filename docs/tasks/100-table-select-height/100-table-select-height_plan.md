# 100-table-select-height

## Settings
- Models: plan Opus (inline), implementation Sonnet, review Opus.
- Review findings rule: fix all. Skip only with a verified reason.
- Build, tests: yes. Commit, push, PR: only if the user asks.
- Branch: `fix/100-table-select-height` from `origin/main` at c9c1ac6 (PR #99 merged).

## Context
Issue #100. The table list `#te-analization-table` in the Site content tab shows 6 rows. A user reported on 24.11.2026 that it is too short. The select is at `client_translate_expert.tpl:209` (`class="form-control"`, `size="6"`) and at `client_translate_expert.oc4.twig:209` (`class="form-select"`, `size="6"`). `client_translate_expert.css` has no rule for `select[multiple]`.
The investigation agent is skipped. Two greps found the select in both templates and no CSS rule for it.

## Approach
1. Set `size="15"` on `#te-analization-table` in both templates.
2. Add a CSS rule `.te-app select[multiple].form-control, .te-app select[multiple].form-select { height: auto; }`. The `.te-app` wrapper exists in both templates. The rule has a higher specificity than a plain `.form-control` height rule, so the `size` attribute works. This also makes the 3 multiple selects of "Additional filters" show 6 rows. That can solve issue #101 (unconfirmed). Do not close #101 with this change.
3. Add a static test for the `size` value and for the CSS rule.

## Assumptions
- ❓ unverified: the OC 3.0 admin theme sets a fixed `form-control` height (the user reported 35px in #101). The OpenCart source is not in this container, so no check is possible. If this is false, the `size="15"` still gives 15 rows and the CSS rule does no harm.
- ✅ verified: both templates have a `te-app` wrapper (grep: `.tpl:22,65,191`, `.oc4.twig:22,65,191`).

## Steps
- [x] Code: `size="15"` in `.tpl` and `.oc4.twig`
- [x] Code: CSS rule in `client_translate_expert.css`
- [x] Test: `tests/test_client_translate_expert_select_height.php`, and a line in `CLAUDE.md` and `.claude/project-workflows.md`
- [x] Prove the test fails without the change
- [x] Bump version + build + test
- [x] Independent review → 100-table-select-height-review.md
- [x] Address review findings
- [x] Re-review? (ask the user)
- [x] Improvements pass → 100-table-select-height-improvements.md

## Update 24.11.2026 (user feedback)
The user showed the CSS of their admin theme: `select[multiple], select[size] { height: auto; }` and `.form-control { height: 36px; }`. In that theme the old `size="6"` already shows 6 rows, and the new CSS rule has no effect there. The rule stays as a safety net for themes with a fixed height (issue #101, unconfirmed).
The user chose a fixed, larger size: `size="15"` replaces `size="10"` in both templates, in the test and in the docs.
