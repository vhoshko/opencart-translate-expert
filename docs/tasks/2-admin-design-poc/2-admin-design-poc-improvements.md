# 2-admin-design-poc: improvements

## 1. Permission prompts

There were no permission prompts in this task. No `.claude/settings.json` change is necessary. There are no entries to prune from this session.

## 2. Steps that I skipped or improvised

1. **Version bump and release build: skipped.** The task changes no file under `sources/`. The user chose "checks only" in Phase 0. `CLAUDE.md` says "always run `./build.release.sh`" after changes. The workflow has no rule for a change that touches only `docs/`.
2. **Templates and language strings steps: dropped.** No UI file changes in a PoC.
3. **Prove-the-test step: not applicable.** The task is not a bug fix, and it adds no test.
4. **Verify loop after the review fixes: run, but with no value.** The fixes changed only Markdown. The `ote-build-test` run gave the same result as before. The run was cheap (about 15 seconds), so I kept it.

## 3. New steps that should exist

1. **A "docs-only" path in `dev-workflow`.** If the diff touches only `docs/**`, do these steps:
   - Run one checks-only pass before the change and one after the change.
   - Do not bump `_version.txt` and do not build.
   - Skip the verify loop after review fixes that change only Markdown.
2. **A fact check of design facts in the PoC before the review.** Two mock-up facts were wrong in my first draft: more than one target language column and the size of the free monthly limit. A short grep of the code found both errors. For a design PoC, check each number and each data shape in a mock-up against the code before the review.

## 4. Friction points

1. The Explore agent gave a good report, but its report cannot tell what the front end needs in each field. The reviewer found that the proposed `analizeLocalization` format dropped `full_text` and the batch-0 totals. For a "replace HTML with JSON" task, ask the Explore agent for each HTML field and for the data that the field carries, not only for the field name.
2. `CLAUDE.md` gives the ASD-STE100 rule for `docs/tasks/**`, `CLAUDE.md` and `.claude/**`. It does not name `docs/pocs/**`. I used the STE rules for `docs/pocs/**` as well. The scope is not clear.

## 5. Proposed edits to the workflow skills

Status: the user said "apply improvements which you think we should add" on 14.11.2026. All 4 edits are applied. Edit 1 also contains the fact check of section 3, item 2.

1. `.claude/skills/dev-workflow/SKILL.md`, Overview: add a "docs-only task" paragraph with the steps in section 3, item 1.
2. `CLAUDE.md`, Build: add one sentence: "If a change touches only `docs/**`, run the unit tests and do not build."
3. `CLAUDE.md`, Writing style: add `docs/pocs/**` to the list of files that use ASD-STE100.
4. `.claude/skills/dev-workflow/SKILL.md`, Phase 0 investigation: for a task that changes a response format, tell the Explore agent to list, for each field, the data that the front end reads from it.

None of these points is in an earlier improvements file. A search for "docs-only", "docs only" and "Markdown only" in `docs/tasks/**/*improvements*.md` found nothing.
