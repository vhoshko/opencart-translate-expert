# 35-multi-value-analysis-filters: improvements

## 1. Permission prompts

- The session ran in auto mode. No permission prompt stopped the work.
- No new `.claude/settings.json` entry is necessary. No prune candidate came up in this task.

## 2. Skipped or improvised steps

- **WIP commit before the store run.** The push needs a commit. The workflow never commits, but the user approved the WIP push. For this reason, the first commit came before the review. The known gotcha in `project-workflows.md` covered this case, and it worked.
- **The v2 fixes were small, so the main context applied them.** The main context also ran one mutation inline (it removed the `$tableHasProductId` guard). The mutation failed 2 checks. The file was restored from a copy. This step is from "Prove the test would have caught it". The task is a feature, not a bug fix, but the step was cheap and useful.
- **The issue asked for `distinct`.** Code reading showed that the `GROUP BY` already removes the duplicate rows. The plan used an `exists` semi-join in place of `distinct`, and the reviewer proved it on a real database. An issue can prescribe a mechanism that is wrong. Check the mechanism before the plan copies it.

## 3. New steps that should exist

- **Probe SQL on the local MariaDB.** The container has MariaDB (`/usr/sbin/mariadbd`, `/usr/bin/mariadb`). The v1 reviewer started a throwaway instance with its data folder in `tmp/`. It then ran the real `getTextToTranslateRows()` and `removeDataFromTable()` on it (`tmp/probe_sql.php`). This was the only real SQL check before the store run. Proposed step for Phase 0 ("Verify external assumptions") and Phase 4: "If the task changes SQL, run the real query on a throwaway MariaDB instance in `docs/tasks/<slug>/tmp/`. Stop it and remove its data folder after the probe."
- **A system `mariadbd` also runs** (PID 57, `/var/lib/mysql`, port 3306). It started with the container. Do not stop it. Do not mix it up with a throwaway instance.

## 4. Friction points

- The sanity-check list in Phase 3 of `dev-workflow` names only 2 test scripts (`VHGoogleTranslator` and `ClientTranslateExpertRouteFilter`). The repo now has 4 test scripts. The text is out of date.
- The first fix pass (M2) closed the gap only for string values. The v2 review found the array case (`table[]=x`). A guard against one bad input form must cover all input forms: string, array and missing. Put this item in the fix brief for the implementer.
- The `ote-build-test` agent wrote logs to `/tmp/claude-1000` and not to the scratchpad. This is harmless, but it is not the documented place.

## 5. Proposed edits to the workflow skills

1. `dev-workflow` Phase 3: replace the list of 2 test scripts with "the test scripts in `tests/`". Or name all 4 scripts.
2. `dev-workflow` Phase 0 and Phase 4: add the MariaDB probe step from section 3.
3. `project-workflows.md`, Known Environment Gotchas: add one row. Symptom: "A SQL change has no real DB check before the store run." What to do: use the local MariaDB as section 3 describes.
4. `dev-workflow` Phase 5: in a fix brief for an input guard, list the input forms (string, array, missing) that the guard must handle.

Status: the user gave the go-ahead on 2026-11-12. Items 1–4 are applied in `.claude/skills/dev-workflow/SKILL.md` and `.claude/project-workflows.md`. Item 2 is also in the Phase 4 dispatch list.
