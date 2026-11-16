# 56-te-ui-foundation: improvements

## 1. Permission prompts

- The session ran in auto mode. No permission prompt stopped the work.
- Proposal: if a later session runs in the default mode, add `Bash(cd tests/js && npm ci*)` and `Bash(node tests/js/*)` to `.claude/settings.json`. These commands are read-only for the repository, because `node_modules` is ignored.
- I did not check the allowlist for single-use rules in this task.

## 2. Skipped or improvised steps

- The plan used a throwaway jsdom probe in `tmp/` for the JS helpers. Review 1 asked for a permanent test, and the user agreed. The probe became `tests/js/test_client_translate_expert_js_helpers.js`. Next time, write a JS change against `tests/js/` from the start.
- I edited the plan with short Python replace scripts. One script stopped on an `assert`, because the plan line had a different text than my copy (`toJson(array $strings)`). The `assert` prevented a silent edit, so keep this pattern.
- The user asked for auto-approve during Phase 2 ("turn on auto approve mode"). The skill defines auto-approve only for review findings. I applied it only to review findings and recorded that scope in Settings.

## 3. New steps that should exist

- If a diff changes `sources/upload/admin/view/javascript/client_translate_expert.js`, run `tests/js/` in Phase 3. The "prove the test would have caught it" step now applies to JS bug fixes too. This task did a mutation run of the status-0 fix (86 passed, 2 failed, as expected).
- If a diff adds CSS, check that each new selector starts with `.te-app` or `.te-header-count`. The header loads the module CSS on all admin pages. I did this check with an ad hoc script. Proposal: add a static check to `tests/js/` or a small PHP test that reads the CSS file.

## 4. Friction points

- npm has no `jquery@1.7.1`, and `jquery@1.7.2` is a Node wrapper with 48 old dependencies and "4 critical" audit lines. The fix is the vendored OC 1.5 file `tests/js/vendor/jquery-1.7.1.min.js` from `gh api` (tag `1.5.6.4`).
- The OC 3.0 build rewrites `; ?>`, `<?php echo $` and `.tpl` in the **whole** OCMOD XML file, and `token` in the whole controller and JS file. The Known Environment Gotchas table does not list these whole-file rewrites. The investigation agent found them in `build.sh:141-159`. A new PHP line in the XML header op can break on OC 3.0 only.
- The header injections loaded no language file before this task. A `load->language()` call in the OC ≤ 3.0 header merges keys into the shared language object of the page. The new `ClientTranslateExpertI18n` class avoids this. Later tasks that need header strings must use it.

## 5. Proposed edits to the workflow skills

Repeated friction that this task resolves:

- "No JS test harness" (tasks 25 and 28): resolved by `tests/js/`. `CLAUDE.md`, `project-workflows.md` and `.claude/agents/ote-build-test.md` now list the command.

Proposed edits (not applied, they need the go-ahead of the user):

1. `.claude/project-workflows.md`, **Known Environment Gotchas**: add a row. Symptom: "A header op works on OC 1.5–2.3 but gives a PHP parse error or a wrong string on OC 3.0". Cause: "`build.sh:156-159` rewrites `; ?>`, `<?php echo $` and `.tpl` in the whole OCMOD XML, and `token` in the whole controller and JS". What to do: "Do not write these strings in PHP code inside the XML. After the build, extract the add block of the 3.0 XML and run `php -l` on it".
2. `.claude/skills/dev-workflow/SKILL.md`, Phase 3 **Prove the test would have caught it**: add `tests/js/test_client_translate_expert_js_helpers.js` as a test that the mutation run can use for JS bug fixes.
3. `.claude/skills/dev-workflow/SKILL.md`, Phase 4 **Independent review**: if the user turns on auto-approve during a task, record the date and the scope (review findings only) in Settings.
