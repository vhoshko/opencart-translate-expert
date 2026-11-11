# 4-cron-translation — improvements

## 1. Permission prompts
- No recurring prompts worth an allowlist entry this task. Docker probes (`docker exec ote-test-* …`) and `gh issue view/edit` ran a few times each.
- Candidates if they keep coming up: `Bash(docker exec ote-test-*:*)` (read-only probes against local test stores), `Bash(gh issue view:*)`.
- Prune: nothing obviously stale was hit; worth a separate `/fewer-permission-prompts` pass rather than guessing here.

## 2. Steps skipped or improvised
- **No real translation ran.** There was no Google key, so the row cap, `done:true` and the loop refactor were only verified by reading, two stub harnesses (reviewers) and the new loop test. The docker smoke test covered refusals, lock, headers, logging and admin save only.
- **Planning and review used the same model (Fable 5.2)** by the user's choice. The review was still useful (it found the row-cap stall with a simulation), but it is not the cross-model check the workflow intends.
- **Smoke test was not re-run** on 04.01.0133–0136. The fixes after it were library-loop and response-shape changes covered by the loop test; the endpoint wiring it checked was not touched except the `json_encode` call.

## 3. New steps that should exist
- **Grep-verify every implementer/fix report before the verify build.** The review-v2 fix agent reported fix 5 (`JSON_PARTIAL_OUTPUT_ON_ERROR` in the cron controller) as done; the file was unchanged. Only a side remark in the build agent's report ("not used anywhere") exposed it. A one-line grep per claimed fix, run in the main context before dispatching `ote-build-test`, would have caught it immediately.
- **Ask for a Google key up front when the task changes the translation loop.** One real cron run on a docker store would have replaced most of the stub-only verification.

## 4. Friction points
- **`ote-build-test` flags valid PHP 5.6 syntax.** Its standing rule treats `catch (\Throwable $e)` and `finally` outside the OC4 marker blocks as PHP 7+ hits. Both parse on 5.6 (`finally` since 5.5; `catch` accepts any class name). Proposal: allowlist them in `.claude/agents/ote-build-test.md` so the report doesn't need a dispatch-time exception every build.
- **Review passes kept chasing a theoretical case.** Three passes built worst-case scenarios on the empty-Google-result guard, which the source query already makes near-impossible (`client_translate_expert.php:829` excludes empty/whitespace sources). The user asked what the issue actually was. A reviewer brief should state which guards are safety nets for unobserved cases, so they get proportionate attention.
- **A short time limit was the wrong default for cron.** It was copied from the admin AJAX flow (10 s per request) and only questioned in pass 3; the user rejected it. Run-size controls for unattended jobs should be asked about in planning, not inherited.
- The first review pass built a stub harness in the scratchpad; it had to be copied into `tmp/` to survive for the fix agent. Asking reviewers to write harnesses straight into `docs/tasks/<slug>/tmp/` would avoid that.
- `build.sh`'s 3.0 `token` → `user_token` rewrite and the fixed setting-key lists shaped naming decisions again (new JS ids chosen to dodge the key loop). Already in the Known Environment Gotchas table, and it worked as intended.

## 5. Proposed edits to the workflow skills
- **Seen twice → act.** `15-docker-test-environment` already asked to verify *reviewer* claims about build output; this task hit the same thing with an *implementer* claim. Proposal for `dev-workflow` Phase 5 (and Phase 2 for larger changes): "Before dispatching the verify build, check each fix the implementer reports as done with one grep or file read in the main context. A subagent report is a claim, not evidence." Not applied — needs the user's go-ahead.
- `dev-workflow` Phase 4/5 dispatch briefs for reviewers: "write any harness or probe scripts to `docs/tasks/<slug>/tmp/`, not the scratchpad."
