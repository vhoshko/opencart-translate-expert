# 91-donation-info: improvements

## Permission prompts

- The user saw "manual mode" and asked 3 times for auto mode. The agent cannot change the permission mode. Tell the user how to change it (Shift+Tab, `/config`) at the start of an unattended run. Do not say that auto-approve is on, because it only skips workflow questions.
- Suggestion: run `/fewer-permission-prompts` for the read-only commands that this task used (`git status`, `git diff`, `grep`, `php tests/*.php`, `swift` for the QR decode).

## Steps skipped or improvised

- Phase 0 skipped the check of the user stop. The user stopped the first implementer run at step 3. The tree held a partial change (README and images). The second brief listed the done steps, and this worked well.
- The QR decode used macOS `swift` with CoreImage. No install was needed. Add this to **Known Environment Gotchas** if a QR step comes again.
- The second review was skipped because the fixes changed only the test and the store text.

## New steps that should exist

- If a task copies a user attachment into the repo, copy it into the task folder at once. The scratchpad does not survive the session.

## Friction points

- The implementer wrote leftovers (`lang.py`, `o.txt`, `README.bak`) in `tmp/`. Tell the brief to delete its own throwaway files.
- A static "address appears" test with `strpos` passes with a second wrong address. Use an exact count and a check for all address-like tokens. The reviewer found this with a mutation probe.

## Proposed edits to the workflow skills

- In Phase 0, add one line: if the user asks for auto mode, say that the permission mode is a client setting and give the keys.
- In Phase 2, add: ask the implementer to remove its throwaway files from `tmp/`.
