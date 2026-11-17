# 71-oc15-header-char-count review

Scope: `git diff HEAD` for these files:

- `sources/upload/admin/view/javascript/client_translate_expert.js`
- `sources/upload/admin/view/stylesheet/client_translate_expert.css`
- `tests/js/test_client_translate_expert_js_helpers.js`

The review ignores `_version.txt` and `releases/`.

Probe evidence (OC 1.5.6.4, from GitHub with `gh api`):

- `admin/view/template/common/header.tpl`: `#header > .div1 > .div2` (logo) and `.div3` (lock image and logged-in text). The template adds `.div3` only if `$logged` is set.
- `admin/view/stylesheet/stylesheet.css:41-58`: `#header .div1` is 56px high, with `background: url('../image/header.png')`. `#header .div3` has `color: #FFF`, `font-size: 12px`, `padding-top: 15px` and `float: right`. The stylesheet has no `.dropdown` or `.nav-item` rule.
- `admin/view/image/header.png`: a vertical gradient of dark blue, from `#0d4c76` (top) to `#1274b0` (bottom). Decoded with `docs/tasks/71-oc15-header-char-count/tmp/png_colors.py`.

## Strengths

- The selector order for OC 2.x, 3.0 and 4.x does not change. The three list selectors run first, in the same order. The `.div3` fallback runs only when all three find nothing. Thus OC 2.x–4.x get the same `li` in the same parent as before.
- The new `return` when no parent exists removes a silent no-op. Before the fix, the code made an `li` and called `prepend` on an empty jQuery set. Now the code writes one console line and stops.
- `.first()` keeps one item if a page has more than one `.div3` in `#header`.
- The added JS uses only `$(selector)`, `.length`, `.first()` (jQuery 1.4+) and `.prepend()`, with `var` and no ES6 syntax. All of these work in jQuery 1.7.1 and in old browsers. `teEl` uses `document.createElement(tag)`, so a `span` works the same as an `li`.
- The CSS rule has the scope `#header .div3 > #te-translated-char-count-info`. Its specificity (2 ids, 1 class) is higher than the base rule `#te-translated-char-count-info` (1 id), so `inline-flex` replaces `flex` only on OC 1.5. No OC 2.x–4.x header has `.div3`, so the rule has no effect on the other header variants.
- The test markup (`#header > .div1 > .div3`) agrees with the 1.5.6.4 template. The tests run on jQuery 3.7.1 and on jQuery 1.7.1. They check the tag (`span`), the parent, the value text, the reset button, item reuse on a second call and the case with no known parent. All 631 checks pass.

## Issues

### Critical (must fix)

None.

### Important (should fix)

1. `sources/upload/admin/view/stylesheet/client_translate_expert.css:654-658` and `:663-705`. On OC 1.5 the count is almost unreadable. The `.te-header-count` colors are for the light OC 2.x–4.x headers: text `#6d6d6d`, label `#999`, value `#444`, reset `#1e6fd9`. The OC 1.5 `.div1` background is dark blue (`#0d4c76` to `#1274b0`, see the probe). Approximate contrast ratios against `#1274b0`:
   - value `#444`: about 1.9:1
   - label `#999`: about 1.8:1
   - reset button `#1e6fd9`: about 1.0:1 (the button is almost invisible)

   The `.div3` rule sets `color: #FFF`, but `.te-header-count` sets its own colors, so the white does not apply. Also, the hover rule sets a `#eee` background on a dark header. This gives a light box that has no relation to the OC 1.5 look. The fix puts the item in the correct location, but the user cannot read the count. That was the purpose of issue #71.

   Fix: add OC 1.5 color rules with the same scope. For example:

   ```css
   #header .div3 > #te-translated-char-count-info .te-header-count { color: #fff; }
   #header .div3 > #te-translated-char-count-info .te-header-count__label { color: #cfe3f2; }
   #header .div3 > #te-translated-char-count-info .te-header-count__value { color: #fff; }
   #header .div3 > #te-translated-char-count-info .te-header-count__reset { color: #fff; text-decoration: underline; }
   #header .div3 > #te-translated-char-count-info .te-header-count:hover { background: rgba(255, 255, 255, 0.12); }
   ```

   Keep the `--over` error color readable on dark blue. `#c53030` on `#1274b0` has a low contrast too. Use a light red (for example `#ffb3b3`) in the OC 1.5 scope. The look stays unverified until somebody opens an OC 1.5 store, as the plan says.

### Minor (nice to have)

1. `tests/js/test_client_translate_expert_js_helpers.js:401-416`. No test locks the selector priority. Add one case with an OC 2.x list and a `.div3` in the same `#header`. Then assert that the item is an `li` in the list and that `.div3` stays empty. This guards the rule "the selector order must not change" against a future reorder.
2. `tests/js/test_client_translate_expert_js_helpers.js:413-416`. The "no known header element" case does not assert the new console line. `console.log` is already stubbed at line 349. Record the calls in the stub and assert one call with the "no admin header element" text.
3. `sources/upload/admin/view/javascript/client_translate_expert.js:603`. On the OC 1.5 login page, `header.tpl` adds no `.div3` (`$logged` is empty). If the OCMOD header script runs there, every load of the login page writes the console line. Before the fix, the code also did a no-op there, so this is not a regression. The console line is harmless, but a check for the parent before the `$.get` call would also save one AJAX request on such pages. This is optional.
4. `sources/upload/admin/view/javascript/client_translate_expert.js:603`. The `span` gets the classes `dropdown nav-item`. OC 1.5 has no CSS for these classes (probe), so the classes do nothing. They are harmless and keep one code path. No change is necessary.

## Assessment

**Ready to merge?** With fixes.

The JS change is correct and safe. The OC 2.x–4.x selector order and output do not change, the new CSS selector cannot match their headers, and the added code is compatible with jQuery 1.7.1. But on the dark-blue OC 1.5 header, the inherited `.te-header-count` colors make the count and the reset button almost invisible. Add OC 1.5 color rules before the merge.
