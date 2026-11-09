---
description: Create a GitHub issue in vhoshko/opencart-translate-expert from the current session context. Drafts it, waits for your OK, then creates it.
argument-hint: [steer: issue type, angle, or extra context]
allowed-tools: Bash, Read, Grep, Glob
---

Write up what this session has established as a GitHub issue.

**Target:** the repo `vhoshko/opencart-translate-expert`, using the `gh` CLI.

`$ARGUMENTS` is an optional steer: an issue type, which part of the session to write up, or extra context. If it is absent, use the whole session.

## 1. Gather

Source the issue from **this session only**: the investigation, the diff, the logs and the commands that were run. Pull the specifics while they are still in context: exact error text, the OpenCart and PHP versions involved, the module version from `_version.txt`, table and column names, Google API responses, `file.php:line` references, SQL, and branch and PR numbers. An issue that says "translation fails sometimes" is worthless; one that names the OC version, the table and the API error is what gets picked up.

Do not re-derive from the repo what the session already proved.

## 2. Check for a duplicate

Do one search before writing:

```bash
gh issue list -R vhoshko/opencart-translate-expert --state all --search "<two or three distinctive terms>"
```

If something close already exists, say so with its number and ask whether to add a comment there instead of creating a new issue.

## 3. Pick the label

| Label | When |
|-------|------|
| `bug` | Something is broken, wrong or failing in a released or buildable state |
| `enhancement` | New functionality or a change in behaviour |
| none | Refactor, tooling or investigation |

Default to `bug` when the session was a diagnosis, and to `enhancement` otherwise. Check that the label exists (`gh label list -R vhoshko/opencart-translate-expert`). If it does not, create the issue without a label rather than inventing one.

## 4. Draft it

**Title:** one line, specific enough to act on from the issue list alone. Name the component and the condition, not just the symptom. Use sentence case and no type prefix.

> Good: `Deep analysis skips product_description rows when language_id has no source text`
> Bad: `Fix analysis bug`

**Body:** markdown.

- *Bug*: `### Symptom` (where it was seen, which OC and PHP version, exact messages) · `### Root cause` (one entry per cause, numbered, with `file:line`) · `### Fix` (numbered, including the regression test) · `### Verification` (the steps that reproduce it) · `### Notes` (adjacent findings explicitly ruled out of scope)
- *Enhancement / task*: `## Why` (the problem) · `## What` (the work) · `## Open questions` · `## Notes`

Rules for the body:

- Include only what the session actually established. Label anything inferred `(unconfirmed)` or put it under `## Open questions`. Never state it as fact.
- Use absolute dates (`09.11.2026`), never "yesterday" or "last run".
- Quote error text verbatim in a fenced block rather than paraphrasing it.
- Do not invent acceptance criteria, estimates or priorities.
- If related work exists (an open branch, a sibling issue, a PR), say what it covers and what it does not.

## 5. Confirm

Show the label, title and full body in chat and **stop**. Creating an issue is public. Wait for an explicit go-ahead, and apply any correction before creating it.

## 6. Create

Write the body to a file in the scratchpad, then run:

```bash
gh issue create -R vhoshko/opencart-translate-expert --title "<title>" --body-file <file> [--label <label>] --assignee @me
```

Report one line: the issue number, the title and the URL that `gh` printed.

Do not touch the working tree, the branch or any commit as part of this command.
