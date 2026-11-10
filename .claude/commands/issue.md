---
description: Create a GitHub issue in vhoshko/opencart-translate-expert from the current session context. Drafts the issue, waits for your OK, then creates it.
argument-hint: [steer: issue type, angle, or extra context]
allowed-tools: Bash, Read, Grep, Glob
---

Write a GitHub issue about what this session established.

**Target:** the repo `vhoshko/opencart-translate-expert`, using the `gh` CLI.

`$ARGUMENTS` is an optional steer. It can give an issue type, the part of the session to write about, or extra context. If `$ARGUMENTS` is absent, use the whole session.

## 1. Gather

Take the issue content from **this session only**: the investigation, the diff, the logs and the commands that you ran. Collect the specifics while they are in context:

- The exact error text
- The OpenCart and PHP versions
- The module version from `_version.txt`
- Table and column names
- Google API responses
- `file.php:line` references
- SQL
- Branch and PR numbers

An issue that says "translation fails sometimes" has no value. An issue that names the OC version, the table and the API error gets action.

Do not find again in the repo what the session already proved.

## 2. Check for a duplicate

Do one search before you write the issue:

```bash
gh issue list -R vhoshko/opencart-translate-expert --state all --search "<two or three distinctive terms>"
```

If a close match exists, give its number. Ask whether to add a comment there instead of a new issue.

## 3. Pick the label

| Label | When |
|-------|------|
| `bug` | Something is broken, wrong or failing in a released or buildable state |
| `enhancement` | New functionality or a change in behavior |
| none | Refactor, tooling or investigation |

If the session was a diagnosis, use `bug`. Otherwise, use `enhancement`. Check that the label exists (`gh label list -R vhoshko/opencart-translate-expert`). If the label does not exist, create the issue without a label. Do not invent a label.

## 4. Draft it

**Title:** Write one line. Make it specific, so that a reader can act on it from the issue list alone. Name the component and the condition, not only the symptom. Use sentence case. Do not add a type prefix.

> Good: `Deep analysis skips product_description rows when language_id has no source text`
> Bad: `Fix analysis bug`

**Body:** Write markdown.

Write the issue in ASD-STE100 Simplified Technical English. See **Writing style (ASD-STE100)** in `CLAUDE.md`.

- *Bug*: `### Symptom` (where you saw it, which OC and PHP version, exact messages) · `### Root cause` (one numbered entry for each cause, with `file:line`) · `### Fix` (numbered, with the regression test) · `### Verification` (the steps that show the problem again) · `### Notes` (nearby findings that are out of scope)
- *Enhancement / task*: `## Why` (the problem) · `## What` (the work) · `## Open questions` · `## Notes`

Follow these rules for the body:

- Include only what the session established. Label each inferred item `(unconfirmed)` or put it under `## Open questions`. Never state an inferred item as fact.
- Use absolute dates (`09.11.2026`). Never use "yesterday" or "last run".
- Quote error text word for word in a fenced block. Do not paraphrase it.
- Do not invent acceptance criteria, estimates or priorities.
- If related work exists (an open branch, a sibling issue, a PR), state what it covers and what it does not cover.

## 5. Confirm

Show the label, title and full body in chat. Then **stop**. Do not create the issue yet, because an issue is public. Wait for an explicit go-ahead. Apply each correction before you create the issue.

## 6. Create

Write the body to a file in the scratchpad. Then run:

```bash
gh issue create -R vhoshko/opencart-translate-expert --title "<title>" --body-file <file> [--label <label>] --assignee @me
```

Report one line with the issue number, the title and the URL that `gh` printed.

Do not change the working tree, the branch or any commit in this command.
