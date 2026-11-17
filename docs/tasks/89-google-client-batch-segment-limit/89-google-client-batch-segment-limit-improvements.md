# Improvements for task 89

## Permission prompts

- No new permission prompt came up in this task.

## Steps skipped or improvised

- I skipped the Explore agent. The greps in the issue session already located all code.
- I did the implementation in the main context. The change was small, and the session model is the implementation model.
- I ran the mutation check inline with `sed` on a copy of the file. I restored the file from the copy and checked the restore with a fixed-string grep.

## Friction

- A test stub that adds a suffix to each chunk of a long text gives a wrong expected value. The first run of the new long-text test failed for this reason. Remove the suffix before the compare, or use a stub that does not add one.
- A `grep -c` pattern with `\$` in double quotes gave 0 for a line that exists. Use `grep -F` with single quotes for a code fragment.
- The shell working directory changed to `sources/upload` during the issue session. Use absolute paths.
- An unquoted glob in a zsh check printed "no matches found". Task 3 reported the same friction (`3-hide-google-key-improvements.md`).

## Proposed edits to the workflow skills

- Promote the zsh glob friction to the **Known Environment Gotchas** table in `.claude/project-workflows.md`. It came up twice. Rule: quote each glob in a check command.
- Add one line to the mutation step of Phase 3: use `grep -F` to check that the restore worked.
