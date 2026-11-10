# 7-exclude-routes improvements

## 1. Permission prompts

- The session ran in auto mode. No permission prompt stopped the work.
- Nothing to add to `.claude/settings.json` from this session.
- Nothing to prune from this session. A full prune of the allowlist needs a separate pass.

## 2. Skipped or improvised steps

- **Regression-test proof (Phase 3) did not apply.** The task is a feature, and the test harness covers only `VHGoogleTranslator`. A throwaway script (`tmp/match_test.php`) tested a copy of the match logic. It is not a regression test. It does not test the two real copies.
- **Live check in the Docker stores was improvised.** The first review recommended it. A `general-purpose` agent did it: install `releases/v04.01.0115`, write the setting with SQL, log in with curl, grep each page. The workflow has no step for it.
- **The live check changed store data.** The form save test left module settings rows in the oc30 and oc40 stores (status=1, empty API key). Nothing in the brief told the agent to keep the store state.

## 3. New steps that should exist

- **Phase 3b: live store check.** If the change affects admin pages, OCMOD ops or OC4 events, run a live check in the Docker stores after the build passes. This is the third task that verified against live stores (`15-docker-test-environment`, `19-docker-uk-ua-language`, this task). See section 5.
- **Throwaway checks: make PHP warnings fatal.** Add `set_error_handler` that throws. The first throwaway check did not find `strpos(): Empty needle` (PHP < 8), because the default CLI shows the warning and goes on. The v2 review found it.

## 4. Friction points

- **The language files do not share comment anchors.** The en-gb file has `// reset cache`. The ru-ru and uk-ua files have translated comments (`// сброс кеша`, `// скинути кеш`). A script that inserts at an English anchor fails on two files. Find the anchor in each file first.
- **Empty `strpos` needle.** `strpos($x, '')` gives a warning and `false` on PHP 5.6–7.4, and `0` on PHP 8. Code that calculates a prefix from user input must check for an empty prefix. This is a new environment gotcha (first time seen).
- **Two copies of one logic.** OC ≤ 3.0 runs the OCMOD op, and OC4 runs the controller method. Each review fix had to go into both copies. Cross-reference comments now help. A shared library class needs build entries for OC4 namespacing.

## 5. Proposed edits to the workflow skills

These edits need the go-ahead of the user.

1. `dev-workflow` Phase 3: add a "live store check" sub-step. The text:
   - If the diff changes admin pages, OCMOD ops, OC4 events or settings, dispatch a `general-purpose` agent for a live check in the Docker stores.
   - Give it the release path, the pages to fetch, the expected result for each page, and an instruction to restore all store data that it changes.
   - Do not run a build in this agent.
2. `project-workflows.md` Known Environment Gotchas: add a row for the empty `strpos` needle (symptom, cause, what to do).
3. `dev-workflow` Phase 2: add one line. "If you write a throwaway check script, make PHP warnings fatal with `set_error_handler`."
