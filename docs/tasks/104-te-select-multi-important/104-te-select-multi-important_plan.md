# 104-te-select-multi-important

## Settings

- Planning model: session model (short plan, stripped-down flow).
- Implementation model: Sonnet. Review model: Opus (`ote-code-reviewer`).
- Review findings rule: fix all. Skip only with a verified reason.
- Build and tests: yes. Commit, push and PR: not requested.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/104

Release 05.01.0100 added `height: auto` rules without `!important` for multiple selects. ocStore 3.0.3.7 and 3.0.4.1 set the select height with `!important` in `admin/view/stylesheet/stylesheet.css`. So the 35px height stays (forum comments 1839717 and 1839719). The ocStore stylesheet was not fetched.

Affects OC 1.5 to 4.x. The change is in CSS and templates only.

Key files:

- `sources/upload/admin/view/stylesheet/client_translate_expert.css` (rules at the end of the file)
- `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl` (selects at lines 209, 241, 257, 269)
- `sources/upload/admin/view/template/extension/module/client_translate_expert.oc4.twig` (selects at lines 209, 241, 253, 265)
- `tests/test_client_translate_expert_select_height.php`
- `CLAUDE.md` (text about the select height test)

## Approach

Add the class `te-select-multi` to the 4 multiple selects in both templates. Add the rule `.te-app .te-select-multi { height: auto !important; }`. Do not put `!important` on general `select` or `input` elements. Replace the rules of 05.01.0100 (`select[multiple]` and `select[size]` under `.te-app`) with this rule. The user decided this. Reason: the fallback is not needed. OC 3.0 has `height: auto` for these selects by default, and in ocStore the general `!important` rule beats the fallback. The class is on all 4 multiple selects, so no select needs the old rules.

## Assumptions

- ❓ unverified: the ocStore stylesheet has `height: ...px !important` for `select`. The user comments say so. The session did not fetch the file. If this is wrong, the new rule has no effect, but it does no harm.
- ✅ verified: `!important` in our rule beats an `!important` rule of the theme with a lower specificity. Our selector has the specificity (0,2,0). A plain `select` selector has (0,0,1). The theme selector is not known. If the theme selector is more specific, the rule does not win (unconfirmed).

## Steps

- [x] Templates: add the class `te-select-multi` to the 4 multiple selects in the `.tpl` and in the `.oc4.twig`
- [x] CSS: add the `.te-app .te-select-multi` rule with `height: auto !important`
- [x] Test: update `tests/test_client_translate_expert_select_height.php` (class on each select, the rule with `!important`, no `!important` on general `select` and `input` rules)
- [x] Docs: update the select height test text in `CLAUDE.md`
- [x] Bump version + build + test (05.01.0101 green)
- [x] Update 2: remove the old rules, update test and docs (user request)
- [x] Independent review → 104-te-select-multi-important-review.md
- [x] Address review findings (3 Minor test gaps, all fixed; Findings: each fix proved by a mutation probe)
- [x] Bump version + build + tests (verify loop) (05.01.0103 green)
- [x] Re-review? (asked. The user went on to commit, so no second pass)
- [x] Improvements pass → 104-te-select-multi-important-improvements.md

## Done

- The class `te-select-multi` is on the 4 multiple selects in the `.tpl` and the `.oc4.twig`. The rule `.te-app .te-select-multi { height: auto !important; }` replaces the 2 rule blocks of 05.01.0100.
- The select height test has 32 checks. Mutation probes proved the new checks.
- Review: 3 Minor test gaps, all fixed. Final build 05.01.0103 is green (PHP 2237, JS 817).
- Not verified: the ocStore stylesheet. The user accepted the forum report as given.
- Commit, push and PR: requested by the user.
