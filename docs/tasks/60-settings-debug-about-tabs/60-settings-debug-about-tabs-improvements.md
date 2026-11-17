# Improvements: 60-settings-debug-about-tabs

## Permission prompts

- No prompt blocked the work.

## Skipped or improvised steps

- The Explore agent was skipped. The task changes templates only, and 3 file reads found all the facts. The Context of the plan records this.
- The 2 templates came from one throwaway generator script in `tmp/`. This kept the `.tpl` and the `.oc4.twig` equal in content.
- The re-review was skipped under auto-approve. The review fixes were small: 1 restored cast, 1 class, 1 CSS rule and 2 string values.

## Friction

- The generator dropped the `(int)` cast and the `htmlspecialchars()` call of the `.tpl`. The reviewer found this. After a generated rewrite, diff each old value expression against the new one.
- No browser is available, so no one saw the real page. The user must check the 3 tabs on OC 1.5, 3.0 and 4.x.

## Proposed edits to the workflow

- Phase 2 of `dev-workflow`: if a script rewrites a template, compare the PHP and Twig value expressions of the old and the new file before the build.
