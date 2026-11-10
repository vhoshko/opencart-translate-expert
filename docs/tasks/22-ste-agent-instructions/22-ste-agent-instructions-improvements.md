# 22-ste-agent-instructions — improvements

## 1. Permission prompts

- No permission prompts occurred in this task.
- No rules to prune. `.claude/settings.local.json` has 5 allow rules. None of them points to a session path or a task folder.

## 2. Steps skipped or improvised

- **Build skipped.** The user chose checks only, because the task changes only Markdown and no build script reads these files. The skill does not have a path for a task that changes only documentation. Claude had to ask the user.
- **No Explore investigation.** The task did not touch `sources/`. Claude listed the files with `git ls-files` and read only the frontmatter keys.
- **Two checker scripts were improvised** in `tmp/`:
  - `check_invariants.py` compares each file with `origin/main`. It checks code blocks, frontmatter and inline code spans.
  - `check_lengths.py` counts STE words for each sentence and sentences for each paragraph.
  - The implementers did not measure sentence length. The checker found 5 long sentences and 9 long paragraphs after their pass.
- **The STE source changed during the task.** The issue said "asd-ste100.org only". The site gives only a summary of the rules. The full standard is open on Zenodo. The user chose the Issue 9 rules.

## 3. New steps that should exist

- **A path for documentation-only tasks** in `dev-workflow`. If only `CLAUDE.md`, `.claude/**` or `docs/**` change, run checks only. Do not bump the version. Record the decision in `## Settings`.
- **A length check for STE text** before review. The house rule says "no automated check", so keep this check optional. The user can decide to keep `check_lengths.py` as a tool. It is local in `tmp/` now and git ignores it.

## 4. Friction points

- **PDF read.** The Read tool cannot show a PDF page here, because `pdftoppm` is not installed. The checklist agent used `pypdf` in a scratchpad venv. The review agent had no PDF tool at all, so it could not compare the house rule with the rule text.
- **Zenodo concept DOI.** DOI `10.5281/zenodo.23666005` goes to the concept record. The file is in version record `23666006`. The URL `/records/23666005/files/<name>` gives a 404 HTML page with HTTP 200. Use the API link `https://zenodo.org/api/records/23666006/files/ASD-STE100_ISSUE9.pdf/content`, and run `file` on the result.
- **zsh `===`.** In zsh, `echo ===` fails with `== not found`, because zsh expands `=word` as a command path. Use `echo "---"` or put the text in quotes.
- **An import ran a script.** `check_lengths.py` imported `check_invariants.py`, and that ran the main code of the second script. Put a `__main__` guard in every helper script that another script can import.
- **Parallel implementers missed small items.** Examples: one "licence" and one table cell in passive voice. Run a final grep or checker yourself after each implementer.
- **Unverified:** do subagents load `CLAUDE.md`? The pointer line in each agent file covers this case. A test is still open.

## 5. Proposed edits to the workflow skills

Claude did not make these edits. The user must approve them first.

1. `dev-workflow` Phase 0: add a "documentation-only task" rule, as in section 3.
2. `project-workflows.md`, Known Environment Gotchas: add the zsh `=word` expansion row. Add it only if it occurs again (first occurrence).
3. `ote-implementer.md`: for STE text, tell the implementer to count words for each sentence before it reports DONE.
