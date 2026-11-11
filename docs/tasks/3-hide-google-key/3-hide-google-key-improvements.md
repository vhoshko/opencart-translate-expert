# 3-hide-google-key — improvements

## Permission prompts

No permission prompt occurred in this task. No new `.claude/settings.json` entry is necessary.

## Skipped or improvised steps

- The Explore investigation agent did not run. Two greps found the key input in both templates, so the task was trivially locatable.
- The regression-test step did not apply. This is a feature, and no test harness covers templates.
- After the first build, I unzipped the oc3.0 and oc4.x packages in the main context to check the `module_` rewrite of the new ids. The build agent did the same check in the verify loop. The prompt of the build agent can ask for this check from the start.
- The user asked for a PR after the verify loop. This answer replaced the re-review question.

## New steps that should exist

- If a diff adds an element id or a JS selector that contains a `client_translate_expert_*` setting key, tell `ote-build-test` to check the oc3.0 and oc4.x packages. The `module_` rewrite in `build.sh` (lines 127 and 394) is a global replacement. It changes every id and selector that contains the key, not only the setting name.

## Friction points

- `releases/v<version>/` holds only the `.full.zip`. The per-version `.ocmod.zip` files are inside it. A glob such as `releases/v*/OpenCartTranslateExpertClient-oc3.0-*.zip` matches nothing, and zsh stops with "no matches found".
- A `cd releases/v…` in a compound command moved the session working directory. Use absolute paths, or run the command in a subshell.
- The first `gh issue view 3` call with `--comments` showed no output. A second call with `--json` worked. A later plain call also worked. The cause is not known.

## Proposed edits to workflow skills

- `project-workflows.md`, **Known Environment Gotchas**: add one row. Symptom: a glob for a per-version zip under `releases/v<version>/` matches nothing. Cause: the release folder holds only the `.full.zip`. Action: unzip the `.full.zip` to a temp dir first.
- `.claude/agents/ote-build-test.md`: add one step. If the diff adds an id or a selector that contains a setting key, grep the oc3.0 and oc4.x packages for the `module_` form of each occurrence.

This is the first time that I saw each friction point. A grep of earlier improvements files for `full.zip`, `gh issue view` and `no matches found` found no match.
