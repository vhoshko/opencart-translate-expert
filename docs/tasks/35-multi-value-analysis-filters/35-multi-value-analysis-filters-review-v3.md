# 35-multi-value-analysis-filters: review v3

Range: the changes after review v2 (`6b68723` m-fixes, `83dea52`, `71a94dd`, `7d98a36`, `0bfa382`). Reviewer: Fable 5.2.

## What I did

- I read `git show` for each of the 5 commits. For `6b68723`, I read only the m1, m2 and m3 parts.
- I made a list of each Twig construct in the full `client_translate_expert.oc4.twig` (tags, filters, tests, functions, operators).
- I read the Twig versions that OpenCart ships, with the core source probe (`installed.json` at the tags `4.0.2.0`, `4.0.2.3` and `4.1.0.3`).
- I read the filter list and the test list of Twig `v3.4.2` from the Twig repository. I compared them with the saved `tmp/CoreExtension-3.18.php`. I ran no Twig code.
- I ran `php tests/test_oc4_twig_filters.php` (6 passed).
- I ran the checker of the new test with 11 limit inputs. The script is `docs/tasks/35-multi-value-analysis-filters/tmp/probe_twig_filter_test.php`.
- I ran `tmp/mutate_sql_test.sh` again (baseline 205 passed, 7 mutations).
- I read the JS, the 2 templates, the OC 1.5 Bootstrap shim CSS and the changed docs.

## Fix status of the review v2 findings

| Finding | Status | Evidence |
|---|---|---|
| m1 | Closed | `readStrictListParam()` returns `false` if the key is set and the value is not a string. The check is before the parser call. `removeDataFromDb()` then stops before the session writes. |
| m2 | Closed | Mutation m7 (the `$tableHasProductId` guard removed from `$categoryCondition`) now gives 2 failures. In review v2 it gave 0. |
| m3 | Closed | The 2 `@return` lines now state the rule for a value without items. They agree with the code. |

## Twig constructs in the OC4 template

The template uses only these constructs:

| Kind | Constructs | Twig 3.4.2 (OC 4.0.2.x) | Twig 3.18.0 (OC 4.1) |
|---|---|---|---|
| Tags | `if`, `else`, `endif`, `for`, `endfor` | Yes | Yes |
| Filters | `e('html_attr')`, `raw` | Yes | Yes |
| Tests | `is defined` | Yes | Yes |
| Operators | `in`, `not`, `and`, `==`, `? :` | Yes | Yes |
| Functions | None | - | - |

- No construct fails on Twig 3.18.0 or on Twig 3.4.2.
- The template has no `{# #}` comment, no `set`, no `include`, no macro and no function call.
- `in` uses `twig_compare()` in Twig 3.4.2 also (read in the `v3.4.2` source). The removal of `|string` is thus safe on OC 4.0.2.x.
- `te_table`, `te_product_category_id` and `te_stock_status` are always arrays (`readSessionList()`). Thus `in` never uses the substring rule for a string on the right side.

## Strengths

- **The Twig fix is minimal and correct.** The commit removes only the 3 `|string` filters. The `.tpl` keeps its `(string)` cast with a loose `in_array`, so the 2 templates select the same options.
- **The new test finds the real defect.** The plan records that the test fails on the template before the fix. The checker test cases cover a filter in a tag, a filter with white space and a pipe in a string literal.
- **The buttons cannot submit the form.** All 12 buttons (6 in each template) have `type="button"`.
- **The `data-te-target` values are correct.** Each value is the `id` of the select that follows it. The 3 ids are the same in the `.tpl`, in the `.oc4.twig` and in the JS.
- **The `change` event reaches the cron link.** The cron link handler uses jQuery `.on('change', ...)` on the 3 selects. The jQuery `.change()` call of the button handler starts it.
- **The button code works on each jQuery version.** `.prop()` and `.on()` exist in jQuery 1.7.1 (OC 1.5). `.click()` and `.change()` exist in jQuery 3.7.1 (OC 4.1, read with the core source probe).
- **The buttons work when the module status is disabled.** `initTranslateExpertMultiSelectButtons()` runs before the status check, as the cron init does.
- **The classes fit each Bootstrap version.** `btn btn-default btn-xs` is Bootstrap 3. The OC 1.5 shim CSS (Bootstrap 3.3.5) defines `.btn-default`, `.btn-xs` and `.help-block`. `btn btn-outline-secondary btn-sm`, `mb-1`, `form-label` and `form-text` are Bootstrap 5.
- **The labels are correct.** Each new `label` has a `for` value that is the `id` of its select. The table select keeps the label of its row. A click on a label only moves the focus, so it does not change the selection.
- **The order is the same in the 2 templates.** Title, buttons, select, hint.
- **i18n is complete.** `button_select_all`, `button_clear_selection` and the new `help_multi_select` text are in `en-gb`, `ru-ru` and `uk-ua`. `index()` passes the 2 new strings.
- **The repo constraints hold.** The change adds no route, no AJAX method, no library class and no setting key. Thus `build.sh` needs no new entry. The new PHP lines in `sources/` are PHP 5.6 syntax.
- **The m2 test uses the real guard.** `SqlDb::$hasProductId = false` lets the `INFORMATION_SCHEMA.COLUMNS` query go to `FakeDb`, which returns no row. The 4 new checks run with full lists, for `select` and `remove`.
- **The workflow docs are correct.** The container has `/usr/sbin/mariadbd` and `mariadb-install-db`. The local PHP is 8.2.35. The input guard rule cites the real m1 case.

## Issues

### Critical (must fix)

None.

### Important (should fix)

1. **The filter list of the test is wider than the oldest Twig that the oc4.x package supports.** `tests/test_oc4_twig_filters.php:16-22`.
   - The oc4.x package covers OpenCart 4.0.2.x and 4.1.0.x (`CLAUDE.md`, Build).
   - OpenCart `4.0.2.0` and `4.0.2.3` ship `twig/twig` `v3.4.2`. OpenCart `4.1.0.3` ships `v3.18.0`. I read these values in `installed.json` with the core source probe.
   - Twig `v3.4.2` does not define the filters `find` and `shuffle`. I read its `CoreExtension.php`. All other names in the list exist in the 2 versions.
   - The probe shows that the checker accepts `{{ a|find(...) }}` and `{{ a|shuffle }}`. Such a template passes the test and then gives "Could not load template" on OC 4.0.2.x. This is the same defect class that the test must stop.
   - The current template uses neither filter, so no store is broken today.
   - Fix:
     - Remove `'find'` and `'shuffle'` from `$knownFilters`.
     - Change the comments and the labels to name the 2 versions (Twig 3.4.2 for OC 4.0.2.x, Twig 3.18.0 for OC 4.1).
     - Add one checker case, for example `unknownFilters('{{ a|shuffle }}')` gives `array('shuffle')`.
     - Update the 3 doc lines that name only Twig v3.18.0: `CLAUDE.md:100`, `.claude/project-workflows.md:51` and the new gotchas row at `.claude/project-workflows.md:76` ("Use only Twig core filters").

### Minor (nice to have)

1. **The test checks filters only.** `tests/test_oc4_twig_filters.php:47-62`.
   - An unknown test (`is sequence`, which Twig 3.4.2 does not define), an unknown function or an unknown tag gives the same `SyntaxError`. The checker does not look at them.
   - `{% apply string %}` also names a filter without a pipe. The checker does not find it.
   - The template uses only `is defined`, `if` and `for` today, so the risk is low.
   - Fix, if you want it: add an allow list for `is <name>` tests (the 10 tests of Twig 3.4.2) and for tag names. If not, say in the doc text that the test covers filters with a pipe only.

2. **The block regex has false negatives for rare syntax.** `tests/test_oc4_twig_filters.php:50`.
   - `{{ "}}"|string }}` gives no finding. The lazy regex stops at the `}}` in the string literal.
   - `{{ a|replace({"x": {"y": 1}})|string }}` gives no finding. The regex stops at the `}}` of the nested hash.
   - `{{ "#{x|string}" }}` gives no finding. The checker removes the full string literal, with its interpolation.
   - No such syntax is in the template. A fix is not necessary now. A note in the docblock of the test is sufficient.

3. **The checker has one false positive.** `tests/test_oc4_twig_filters.php:50`.
   - `{# old: {{ x|string }} #}` gives `["string"]`, but Twig ignores a comment.
   - This direction is safe: the test fails, and the developer sees the cause.
   - Fix, if you want it: remove `{# ... #}` from the source before the block search.

4. **The test docblock and its title name only OC 4.1.** `tests/test_oc4_twig_filters.php:8-11`. This is a part of Important 1. I list it here so that the fix does not miss it.

## Not verified

- I did not compile the template with Twig. The brief forbids a run of the downloaded Twig code. The construct table comes from a read of the template and of the Twig sources.
- I did not open the admin page in a store or in a browser. The layout statements come from a read of the HTML and of the Bootstrap class names.
- I did not read the Twig version of OC 4.0.2.1, 4.0.2.2, 4.1.0.0, 4.1.0.1 or 4.1.0.2. The brief gives `v3.18.0` for 4.1.0.0.
- I did not run the removal action with an array parameter in a store. The m1 result comes from a read of the code.

## Assessment

**Ready to merge?** With fixes.

The OC 4.1 fix is correct, and no construct in the template fails on Twig 3.4.2 or 3.18.0. The buttons, the labels, the i18n and the m1, m2 and m3 fixes are clean. The one fix is Important 1: the filter list of the new test must not permit `find` and `shuffle`, because OC 4.0.2.x ships Twig 3.4.2. It is a change in the test and the docs only, and it does not change the shipped code.
