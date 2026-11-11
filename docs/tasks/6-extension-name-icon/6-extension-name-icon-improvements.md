# 6-extension-name-icon: improvements

## Permission prompts
- No permission prompt stopped the work. The session ran in auto mode.
- Prune this entry from `.claude/settings.local.json`: `Bash(bash /Users/macbook/VH/vh-repos/opencart-translate-expert/build.release.sh)`. The repo is now at `/Users/macbook/repos/vh/opencart-translate-expert`, so the rule cannot match again.

## Skipped or improvised steps
- The Explore investigation agent did not run. Four greps found every `heading_title` usage, the icon file and the OC4 image rewrites in `build.sh`. This is the third task in a row with this decision (see `24-char-count-reset` and `3-hide-google-key`).
- The issue had a title only. Two questions to the user set the scope (icon on the list and the H1, colored part of the name). The user added the colored part in a free-text answer.

## New steps that should exist
- Verify OpenCart core behavior with `gh api "repos/opencart/opencart/contents/<path>?ref=<tag>" -H "Accept: application/vnd.github.raw" | grep -n "<pattern>"`. No local OpenCart source exists. This call checked six versions in two commands. It found the `autoescape => false` setting, the source of the list name and the `array_multisort` sort of the list. Use the `upload/` prefix for the path. OC 2.3 and 3.0 keep the module list at `upload/admin/controller/extension/extension/module.php`. OC 1.5–2.2 and 4.x keep it at `upload/admin/controller/extension/module.php`.

## Friction points
- `gh issue view 6 --comments` showed no output again. A second call with `--json title,body,comments,state` worked. The `3-hide-google-key` task had the same problem. This is the second time.
- The reviewer gave the list sort order "from memory, not confirmed". One `gh api` call confirmed it. A reviewer brief can tell the reviewer that this probe is available.
- The user chose "other color" in a multi-select answer without a value. One more question was necessary. If an option needs a value, ask for the value in the option text.

## Proposed edits to workflow skills
1. `dev-workflow` Phase 0, Input parsing: change the command to `gh issue view <url-or-number> -R vhoshko/opencart-translate-expert --json title,body,comments,state`. This removes the repeated empty-output friction.
2. `dev-workflow` Phase 0, Investigation subagent: make the skip rule specific. "If 2–4 greps find all usages of the touched identifiers, skip the Explore agent and record this in the plan."
3. `project-workflows.md`: add the `gh api` OpenCart source probe (above) as a standard way to verify external assumptions.
4. `ote-code-reviewer` brief: say that the reviewer can use the `gh api` probe to confirm OpenCart core behavior.

## Repeat friction (act, do not report again)
- `gh issue view` empty output: second time. Do proposed edit 1, or add a row to **Known Environment Gotchas** in `project-workflows.md`.
- Explore skip on grep-locatable tasks: third time. Do proposed edit 2.
- Both edits wait for the go-ahead of the user.

## Applied
The user gave the go-ahead. These edits are done:
- Edit 1: `.claude/skills/dev-workflow/SKILL.md` Input parsing uses `gh issue view … --json title,body,comments,state`.
- Edit 2: `.claude/skills/dev-workflow/SKILL.md` Investigation subagent has the "2–4 greps" skip rule.
- Edit 3: `.claude/project-workflows.md` has a new **OpenCart Core Source Probe** section.
- Edit 4: `.claude/agents/ote-code-reviewer.md` Scope tells the reviewer to verify core behavior with the probe.
- The stale `/Users/macbook/VH/vh-repos/…` rule is removed from `.claude/settings.local.json`.
