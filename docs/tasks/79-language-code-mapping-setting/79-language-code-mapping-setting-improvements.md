# Improvements for task 79

## Permission prompts
- No permission prompt came up that needs a new rule.
- No entry in `.claude/settings.json` needs a prune from this task.

## Steps skipped or improvised
- The review model was the same as the planning model. The user chose it. The review was not independent, but it still found the Critical issue.
- The first plan put the helper in `build.sh` with 3 lines. The Explore agent said that `build.sh` needs no change. The planning agent found the Explore claim wrong. A new library file needs 3 lines in `build.sh`.
- No store run was done. The plan has 3 unverified items (the Save button `submit` event, Twig autoescape and the card colors).

## New steps that should exist
- When a form field holds JSON or another text with quotes, add a test that posts the value escaped with `htmlspecialchars($value, ENT_COMPAT, 'UTF-8')`. OpenCart `Request::clean()` does this for each POST value. The first save test posted raw JSON, so it did not find the Critical issue C1.
- Add this row to **Known Environment Gotchas** in `.claude/project-workflows.md`:
  - Symptom: a posted JSON value fails to parse in the admin controller.
  - Cause: `Request::clean()` escapes each POST value with `htmlspecialchars`.
  - What to do: decode once with `html_entity_decode($value, ENT_QUOTES, 'UTF-8')` before the parse. Post escaped values in the tests.

## Friction points
- The Explore agent gave 2 wrong claims about the code (the `build.sh` rule and the `te_i18n` key list lines). The checks of the main context found them.
- The front-end and server implementers needed a fixed list of names. The contract in the brief worked. One name was missing (`te_lcm_col_key` in the JS key list). The check of the main context found it.

## Proposed edits to the workflow skills
- Add the gotcha row above to `.claude/project-workflows.md`. Wait for the go-ahead of the user.
- In the Phase 2 brief rules of `dev-workflow`, add: "If a form posts text that contains quotes, name a test that posts the escaped form."
