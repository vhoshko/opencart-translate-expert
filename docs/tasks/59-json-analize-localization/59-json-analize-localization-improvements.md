# 59-json-analize-localization — improvements

## 1. Permission prompts

- No permission prompt came up. The session ran in auto mode.
- The repo has no `.claude/settings.json` and no `.claude/settings.local.json`. No allowlist entry needs a prune.

## 2. Steps that were skipped or improvised

- **The plan subagent cannot write files.** The `Plan` agent type has no Write tool. It returned a long plan as text, and the main context wrote the plan file again. This cost much context. See section 5, edit 1.
- **Two implementers in parallel.** The change split into a PHP part (LOC, CTRL, PHP tests) and a front-end part (JS, CSS, templates, language files, JS tests, docs). Two `ote-implementer` agents ran in one message on disjoint file sets. This worked well and saved time. One cross-file item (the CTRL `$data` keys that the templates need) had one owner. The other agent still reported it as open work. See section 5, edit 2.
- **The re-review gate under auto-approve.** The user asked for a full run with no stop. The skill says to ask the re-review question. I decided it under auto-approve: the fixes changed logic, so one pass v2 ran. I recorded this in the Settings block.

## 3. New steps that should exist

- **A round-trip probe when the front end builds a file that the server parses.** The reviewer ran the real JS helpers in jsdom, then the OpenCart request clean, `html_entity_decode`, `php -l` and `parseLocalizationFile`. This probe found the 2 Important findings (decoded entities and a `?>` in a literal). The unit tests did not find them. See section 5, edit 3.

## 4. Friction points

- **The review stopped at the usage limit again.** The Fable 5.1 review agent stopped with HTTP 429. A `SendMessage` to the same agent after the reset continued its work. This also happened in task 58. It is a repeated friction, so it goes to the **Known Environment Gotchas** table. See section 5, edit 4.
- **The same model for the plan and the review.** The user chose Fable 5.1 for both. The review still found 3 Important findings. No change is necessary.
- **The `cd` state of the shell moved.** A `cd` into `sources/upload` stayed for later calls. Absolute paths prevented errors. No change is necessary, because the skill already asks for absolute paths.

## 5. Proposed edits to the workflow skills

1. `dev-workflow` Phase 0 "To apply the choice": if the planning model differs, dispatch `general-purpose` with `model:` in place of `Plan`. Tell it to write the plan file itself. Then read only the plan file for the checkpoint.
2. `dev-workflow` Phase 2: if the change splits into a server part and a front-end part, dispatch 2 implementers in one message. Give each agent a disjoint file list. Give each cross-file item to one owner, and tell the other agent that the item is not its work.
3. `dev-workflow` Phase 4: if the front end builds content that the server parses or writes (for example a PHP file), tell the reviewer to run a round-trip probe. The probe uses the real JS helpers and the real server parser.
4. `project-workflows.md` **Known Environment Gotchas**: add a row for a subagent that stops with HTTP 429 at the usage limit. Send a message to the same agent after the reset. Do not start a new agent.

All 4 edits are applied in this task, because the user asked to apply all improvements.
