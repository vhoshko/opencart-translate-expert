# 104-te-select-multi-important: improvements

## Permission prompts

No new prompt that needs an allowlist entry.

## Steps skipped or improvised

- The task used the stripped-down flow. The Explore agent and the sanity check did not run. The user approved this.
- The user changed the plan after the first build. The plan kept the old rules as a fallback. The user asked to replace them. I waited for the running build to finish before I edited files. This avoided the file mix of task 98.
- The second review pass was not run. No important finding existed. The user asked for the commit.

## Friction

- The first `sed` for the `.tpl` did not match. The `.tpl` tag contains `?>`, so `[^>]*` stops early. The grep count showed 0, and I fixed the pattern. This is the same cause as the test regex of task 100. Use a line-based pattern for the `.tpl` select tags.
- The reviewer found that a regex check for a general `!important` rule missed 3 cases. A parsed check (selector and body pairs) is more reliable than one regex for CSS.

## New steps that should exist

None.

## Proposed edits to the workflow skills

None. The `.tpl` tag note is a gotcha for template edits. Add it to `project-workflows.md` if it comes again.

## Not verified

The ocStore stylesheet was not fetched. The rule that wins over `!important` of the theme depends on its selector. No ocStore install was run.
