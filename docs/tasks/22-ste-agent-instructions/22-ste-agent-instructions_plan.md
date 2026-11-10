# 22-ste-agent-instructions

## Settings

- Planning model: Opus 5.5 (inline).
- Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Plan approved by the user on 10.11.2026.
- Build: checks only. No version bump, no `build.release.sh` (user decision, 10.11.2026: the change is Markdown only, and no build script reads these files).
- Tests: checks only (`php -l` and unit tests).
- Commit, push, PR: not done by this workflow.
- Auto-approve for review findings: granted by the user on 10.11.2026. Claude decides each finding (lean to fix) and reports the reason. Changes to the agreed approach, external systems or scope still go to the user.
- Auto-approve, wider scope (user, 10.11.2026), **for one session only**: if Claude needs an answer from the user, Claude selects its own recommended option and does not ask. Claude reports each choice in chat. A later session must not use this setting. Ask the user again.

## Context

Issue: https://github.com/vhoshko/opencart-translate-expert/issues/22

The agent instruction files are in free-form English. Rewrite them in ASD-STE100 Simplified Technical English (STE). Add a house rule: all agent answers in this repo, and new plan and review files under `docs/tasks/**`, use STE.

No OpenCart version is affected. No PHP, template, language file or build script changes.

Files (14, 1070 lines):

| File | Lines |
|---|---|
| `CLAUDE.md` | 81 |
| `.claude/project-workflows.md` | 75 |
| `.claude/agents/ote-build-test.md` | 91 |
| `.claude/agents/ote-code-quality-reviewer.md` | 51 |
| `.claude/agents/ote-code-reviewer.md` | 77 |
| `.claude/agents/ote-implementer.md` | 68 |
| `.claude/agents/ote-spec-reviewer.md` | 41 |
| `.claude/commands/bump-version.md` | 9 |
| `.claude/commands/do.md` | 9 |
| `.claude/commands/issue.md` | 73 |
| `.claude/skills/bump-version/SKILL.md` | 52 |
| `.claude/skills/dev-workflow/SKILL.md` | 300 |
| `.claude/skills/requesting-code-review/SKILL.md` | 49 |
| `.claude/skills/subagent-driven-development/SKILL.md` | 94 |

Out of scope (from the issue): existing `docs/tasks/**` files, code comments, UI copy and language files, agent answers outside this repo, any automated check, a project word list.

## Approach

1. Make an STE rule checklist from Part 1 (writing rules) of ASD-STE100 Issue 9. Keep it in `tmp/` (git-ignored). It holds rule numbers and short paraphrases only; no rule text goes into the repo (CC BY-NC-ND).
2. Rewrite each file to the checklist. Keep the meaning, every instruction, every command, path, code identifier and the frontmatter keys. Code blocks, commands and quoted literal strings do not change.
3. Add the house rule to `CLAUDE.md`: a short STE summary (rule areas, not rule text), the source (Issue 9, DOI), the technical-noun and technical-verb allowance for project terms, and the scope from the issue.
4. Add a one-line pointer to the house rule in each agent file and in the plan and review templates (decision from the user, 10.11.2026).

> **Design correction.** The issue says "publicly documented ASD-STE100 writing rules only (asd-ste100.org)". The site does not publish the 53 rules; it only summarises them. The full standard is free on Zenodo, and the site links to it. The user chose (10.11.2026): follow the Issue 9 Part 1 rules, cite rule numbers, copy no rule text, no dictionary check.

## Assumptions

- ✅ verified: asd-ste100.org is reachable. HTTP 200 on `asd-ste100.org` and `www.asd-ste100.org` (10.11.2026). The issue note "not reachable" is out of date.
- ✅ verified: the site does not publish the rule text. `about_STE.html` and `STE_faq.html` give only "53 writing rules in 9 sections" and examples (active voice, imperative, condition first, no "-ing" forms, technical nouns and technical verbs).
- ✅ verified: the full standard is openly available. Zenodo API: record 23666005 (concept DOI 10.5281/zenodo.23666005), `access_right: open`, licence `cc-by-nc-nd-4.0`. File from version record 23666006: `ASD-STE100_ISSUE9.pdf`, 3316157 bytes, sha256 `d1f4ea9e…1a166431`, in `tmp/ste-src/` (git-ignored by `/docs/tasks/*/tmp/`).
- ✅ verified: no build script reads these files. `grep` of `build.sh` and `build.release.sh` for `CLAUDE.md` and `.claude`: no match.
- ✅ verified: frontmatter keys in use are `name`, `description`, `model`, `tools`, `argument-hint`, `allowed-tools`. Only `description` values are prose; the rewrite can change them. All other values stay byte-identical.
- ❓ unverified: subagents load `CLAUDE.md`. Not tested in this session. If false, the pointer line in each agent file (Approach step 4) still gives them the rule.

## Steps

- [x] STE checklist from Issue 9 Part 1 → `tmp/ste-rules-checklist.md`
  Findings: Part 1 is PDF pages 43–128. It has 53 rules in 9 sections and 8 recommendations (GR-1 to GR-8). Hard limits: procedure sentence 20 words, descriptive sentence 25 words, paragraph 6 sentences, noun cluster 3 words. Dictionary rules (1.1–1.4, 1.6, 3.1, word choice in 9.1 and 9.2) are out of scope. The Read tool cannot render PDFs here because `pdftoppm` is missing, so the agent used `pypdf` in a scratchpad venv. The Zenodo DOI goes to the concept record (23666005). The file is in version record 23666006; the `/records/23666005/files/...` URL gives a 404 HTML page.
- [x] Invariant checker → `tmp/check_invariants.py`. Baseline on `origin/main`: 0 fail, 0 warn.
  Findings: code blocks with a language tag must stay byte-identical (FAIL). Untagged and `markdown` blocks hold prose templates, so a change gives WARN, and Claude inspects it by hand. A missing inline code span gives WARN.
- [x] Rewrite in 4 parallel groups (`ote-implementer`): A = `CLAUDE.md` + house rule + `project-workflows.md`; B = 5 agent files + pointer; C = 3 commands + `bump-version` + `requesting-code-review`; D = `dev-workflow` + `subagent-driven-development` + template pointers.
  Findings: all 4 groups reported DONE. None of them reported an instruction that STE could not express. None of them measured sentence length.
- [x] House rule in `CLAUDE.md` (`## Writing style (ASD-STE100)`, after Project Overview)
- [x] Rewrite `CLAUDE.md` and `.claude/project-workflows.md`
- [x] Rewrite the 5 agent files (+ pointer line)
- [x] Rewrite the 3 command files (+ pointer in `issue.md`)
- [x] Rewrite the 4 skill files (+ pointer before the plan template and in Phase 4 and Phase 7 of `dev-workflow`)
  Findings: `requesting-code-review` defines no review file format, so it has no pointer. `ote-code-reviewer.md` owns the review format and has the pointer.
- [x] Check: frontmatter non-prose values, code blocks and commands are unchanged → `check_invariants.py`: 0 fail, 1 warn
  Findings: the WARN is the `dev-workflow` plan template. Claude inspected it by hand: headings, placeholders and checkboxes are kept. Descriptions: all trigger phrases are kept (old/new compared).
- [x] STE length check → `tmp/check_lengths.py` (counts code span = 1 word, parentheses = 1 word)
  Findings: `origin/main` had 31 sentences over 25 words, 32 at 21–25 words and 1 paragraph over 6 sentences. After the implementers: 5 over 25 and 9 long paragraphs. Claude fixed these inline. The result is 0 over 25, 0 long paragraphs and 2 at 21–25 words. Both of these are descriptive, so the 25-word limit applies. Claude also removed 1 semicolon (rule 8.1) and 1 "e.g." (Latin abbreviation). A scan for semicolons, Latin abbreviations and contractions now finds none in the prose.
- [x] Checks only: unit tests 24 passed, 0 failed, 6 skipped (integration, no key). No PHP file changed, so the Phase 0 `php -l` result (13 files, clean) still applies. No version bump, no build.
- [x] Independent review (Fable 5.2) → `22-ste-agent-instructions-review.md`
  Findings: 0 critical, 3 important, 12 minor. Verdict: ready to merge with fixes. No lost gate, "never" rule, number or example. Literal strings and trigger phrases are consistent.
- [x] Address review findings (auto-approve: Claude decides)
  Findings: fix 1–15. Spelling (5): American English, because STE follows Merriam-Webster. Kept: the house-rule bullet "Changes to `CLAUDE.md` and `.claude/**`" (finding 14). This is a small scope widening, so the user decides. The user confirmed on 10.11.2026 that the house rule covers future edits to these files. Claude checked the rule numbers against the checklist: Latin abbreviations are GR-6, not 8.1 or 4.2. Fixes go to one `ote-implementer`.
- [x] Verify loop
  Findings: the implementer reported 1–15 done. Claude checked it again: `check_invariants.py` 0 fail, 1 warn (known template WARN). `check_lengths.py` 0 over 25, 0 long paragraphs, 3 descriptive sentences at 21–25 words. No "the team" remains. Unit tests: 24 passed, 0 failed, 6 skipped. Only `CLAUDE.md` and `.claude/**` changed. The implementer missed one "licence" (`CLAUDE.md:19`), and Claude fixed it. The regression-proof steps are now one ordered list (copy → disable → run → confirm → restore → verify).
- [x] Re-review? (ask the user) → yes, Fable 5.2
- [x] Independent review → `22-ste-agent-instructions-review-v2.md`
  Findings: 0 critical, 0 important, 1 minor (a table cell in passive voice, `dev-workflow/SKILL.md:327`). Claude fixed it. Checks: 0 fail, 0 over 25, tests 24 passed. Re-review gate: Claude stopped the loop (auto-approve, recommended option), because the findings are now at wording level.
- [x] Improvements pass → `22-ste-agent-instructions-improvements.md`

## Done

- All 14 instruction files are in ASD-STE100 STE (Issue 9 Part 1 writing rules, no dictionary check). `CLAUDE.md` has the house rule `## Writing style (ASD-STE100)`. It covers answers, new `docs/tasks/**` plan, review and improvements files, and future edits to `CLAUDE.md` and `.claude/**`. Pointer lines are in the 5 agent files and next to the plan, improvements and issue templates.
- Version: not changed (`04.01.0113` on `origin/main`). The task is checks only, so there was no build.
- Reviews: pass 1 had 15 findings, and Claude fixed all of them. Pass 2 had 1 finding, and Claude fixed it.
- Gotchas: asd-ste100.org does not publish the rules. Get the PDF from the Zenodo version record. The Read tool cannot render a PDF here.
- Deferred: a documentation-only path in `dev-workflow`, an optional STE length check, and a test that subagents load `CLAUDE.md` (see the improvements file). Nothing is committed.
