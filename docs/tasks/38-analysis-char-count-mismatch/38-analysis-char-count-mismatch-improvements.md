# 38-analysis-char-count-mismatch: improvements

## 1. Permission prompts

- The session ran in auto mode. No permission prompt stopped the work.
- No allowlist change is necessary. No pruning candidate came up.

## 2. Steps that were skipped or improvised

- No docker store run. The host checkout is separate, so a store run needs a pushed branch. The workflow does not push. The live probe (`tmp/probe_live_google.php`) replaced it in part. The probe runs the real translate loop with a stub DB and the real Google API.
- The user gave the Google key after the plan approval, not in Phase 0. The probe read the key from the `GOOGLE_KEY` env var. The key went to `ote-build-test` only as a prompt argument. Before the review, a grep of the repo and `releases/` found no copy of the key.
- The user asked a side question about Google quotas during the plan checkpoint. The answer came from a web search. It did not change the plan.

## 3. New steps that should exist

- **Key hygiene check.** If the user gives an API key, pass it to probes through an env var only. Before each review and before the end, run `grep -rl "<first 12 key characters>" .` and confirm that the result is empty.
- **Equivalent mutations.** A mutation that passes all tests is not always a test gap. Before you add a test, check if later code makes the result the same. In this task, the final flush after `break 2` masks the row-cap condition at line 333. Record such a case as "equivalent" in the plan.

## 4. Friction points

- **In-place edits of PHP lines with `$` variables (2 times in this session).**
  - A `perl -pi -e '...'` replacement with `$translationResults->texts` put empty strings into the code, because Perl interpolates `$name` in the replacement.
  - A `grep -n "...\$textRowsToTranslate..."` check in a `&&` chain returned no match, so the chain stopped and a later edit did not run.
  - The Edit tool did both changes correctly.
- **The first M1 regression test did not fail with its mutation.** The running sum used the decoded length, so 600 + 3000 stayed under 5000. A mutation check of each new test found this. Without the check, a test that proves nothing would have stayed.
- **The test error handler found an older bug.** The loop test turns each PHP warning into an exception. This showed the "Array to string conversion" warning in the count-mismatch message. The base code had the same bug.

## 5. Proposed edits to the workflow skills

Applied on 2026-11-12, with the go-ahead of the user.

1. `.claude/project-workflows.md`, **Known Environment Gotchas**: add one row (the friction came up 2 times):
   - Symptom: an in-place `perl`/`sed` edit of a PHP line leaves empty text where `$name` was, or a `grep` check of a PHP line stops a `&&` chain.
   - Cause: Perl interpolates `$name` in the replacement. A `$` in a double-quoted shell string is escaped in a different way for each tool.
   - What to do: use the Edit tool for a PHP line that contains `$`. Use `grep -F` with single quotes for a check.
2. `.claude/skills/dev-workflow/SKILL.md`, Phase 0 **Verify external assumptions**: add the key hygiene check from section 3.
3. `.claude/skills/dev-workflow/SKILL.md`, Phase 3 **Prove the test would have caught it**: add one sentence. If a mutation passes, first check if later code makes the result the same. Then record the case as equivalent, or add a test.
