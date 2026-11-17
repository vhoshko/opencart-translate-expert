# 71-oc15-header-char-count

## Settings
- Planning model: session model (Sonnet 5.5), inline. The task is small, so the stripped-down flow applies.
- Review model: Opus.
- Auto-approve: yes. Decide review findings, then commit, push and open a PR.

## Context
Issue #71. On OC 1.5 the header has no `.nav.pull-right`, `.navbar-nav` or similar list. `showTranslatedCharCount()` in `client_translate_expert.js` finds no parent. The count item is never added. The OC 1.5 header has `#header .div3`.

## Approach
Add an OC 1.5 fallback. If no list exists, use `#header .div3` and a `span` item. If no parent exists, log one line and return. Add CSS for the item inside `.div3`.

## Assumptions
- ✅ verified: the OC 1.5 header structure comes from the issue (`header.tpl`, tag 1.5.6.4).
- ❓ unverified: the look of the item on a real OC 1.5 page. No OC 1.5 store ran.

## Steps
- [x] Code: fallback in `showTranslatedCharCount()`. Findings: the new tests fail without the fix.
- [x] CSS: rule for `#header .div3 > #te-translated-char-count-info`
- [x] JS tests: OC 1.5 header and no known header
- [x] Bump version + build + test (04.01.0203)
- [x] Independent review → 71-oc15-header-char-count-review.md
- [x] Address review findings: Important (OC 1.5 colors) and Minor 1, 2 fixed. Minor 3, 4 skipped (optional or no action). Verify build 04.01.0204 green, 633 JS tests.
- [x] Re-review skipped: the fixes are CSS and tests only.
- [x] Improvements pass

## Done
The OC 1.5 header now shows the count in `#header .div3`, with light colors for the dark header. Final version 04.01.0204. The look is unverified on a real OC 1.5 store. Deferred: skip the AJAX request on the OC 1.5 login page (review Minor 3).
