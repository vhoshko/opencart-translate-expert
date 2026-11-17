# 81-collapse-settings-cards: review

Scope: `git diff HEAD` plus the untracked `tests/test_client_translate_expert_settings_cards.php`. Requirements: issue #81 and `81-collapse-settings-cards_plan.md`.

Probe: `docs/tasks/81-collapse-settings-cards/tmp/probe_render.php`. It renders the real `.tpl` (PHP include) and the real `.oc4.twig` (Twig v3.4.2 from GitHub, in `tmp/twig/`). It renders each file with no error, with an error and (`.tpl` only) with the variable unset.

Probe result for each of the 5 renders:

- The Settings card section has a balanced `div` count. The 2 open `div` elements at the cut point belong to the "Try a translation" card, where the probe cuts the section.
- `te-cron-panel`, `te-routes-panel` and `te-try-panel` have `te-hidden` and `aria-expanded="false"`.
- `te-lcm-panel` has `te-hidden` and `aria-expanded="false"` if no error exists. It has no `te-hidden` and has `aria-expanded="true"` if `error_language_code_map` is set. The `.te-field__error` line shows only in the error case.
- The `.tpl` with the variable unset gives the closed state and no PHP notice output.

Tests run: `test_client_translate_expert_settings_cards.php` (40 passed), `test_oc4_twig_filters.php` (29 passed), JS helper tests (723 passed, jQuery 3.7.1 and 1.7.1).

### Strengths

- The 3 cards copy the "Try a translation" pattern exactly: the same button classes, the same `data-te-target`/`aria-controls` pair and the same `te-card__body te-hidden` body. Thus, the existing `teInitCollapse()` delegate handler (`client_translate_expert.js:1434`) operates them with no new JS. The OC 1.5 package gets the same `.te-app .te-collapse-toggle` rules (`client_translate_expert.css:547-563`) that the "Try a translation" card head already uses. There is no new CSS risk on any OC version.
- The `.tpl` and the `.oc4.twig` change in parallel. Each uses its own syntax. Twig `if error_language_code_map` and PHP `empty()` agree for `''`, `null` and an unset variable.
- The JS fix in `teLcmInit()` (`client_translate_expert.js:841-843`) closes a real gap. Without it, the client-side submit check puts its alerts in a hidden card and blocks the submit with no visible message. The new JS tests check both directions: the card opens on an error and stays closed on a valid submit.
- The form fields in the collapsed cards still post. `display: none` does not remove an input from the form submit. The hidden input `client_translate_expert_language_code_map` gets its value from the submit handler, which does not need the card open. The cron secret generate button and `updateTranslateExpertCronLink` read and write `#te-cron-secret` by id, so they operate on a hidden field too.
- No other code shows an error or sets focus inside these cards on page load. The only server-side card error is `error_language_code_map` (`validateLanguageCodeMap()`, controller line 954). The `.focus()` at `client_translate_expert.js:822` follows a click on "Add", which the user can only do with the card open. The `.focus()` at line 282 is for the translate-on-page fields, not these cards. The general tab is active after a failed save (`$tab_general_class`), and `error_warning` also shows the message at the top of the page.
- No new strings, so no i18n change is necessary. The `CLAUDE.md` and `.claude/project-workflows.md` rows are short and accurate.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. `client_translate_expert.tpl:153`: the condition is `if (!!empty($error_language_code_map))`. The double negation comes from the generator script (`tmp/edit.py` builds `'!' + '!empty(...)'`). The result is correct, because `!!empty(x)` equals `empty(x)`. But a reader can easily take it for `!empty(x)`, which is the opposite. Change it to `if (empty($error_language_code_map))`.

2. `tests/test_client_translate_expert_settings_cards.php:55-57`: for `te-lcm-panel`, the test checks only that the strings `te-hidden` and `error_language_code_map` occur in the class attribute and in `aria-expanded`. It does not check the polarity. An inverted condition (open when no error, closed on an error) passes the test. The `.tpl` is easy to render with `extract()` and `include`, as the probe shows. Add a render check of the `.tpl` for the two cases (error set and error empty), and assert the class and the `aria-expanded` value. The Twig side can stay static, because no Twig runtime is available to the test suite.

3. `client_translate_expert.tpl:120` and `client_translate_expert.oc4.twig:120`: `#te-cron-bulk-amount` is `type="number" min="1" max="100000"`, and the form has no `novalidate`. The server clamps the value before it renders, so the start value is always valid. If the user types an out-of-range value, closes the card and clicks Save, the browser blocks the submit. It cannot show its validation bubble on a hidden field, so the user sees no message. The same thing already occurs before this change if the user saves from another tab, so this is an edge case, not a regression. A possible fix: in the delegate handler, do not close a card whose body has an `:invalid` field. Another fix: on the form `invalid` event (capture), open the parent `.te-card__body`. Optional.

4. `client_translate_expert.js:841-843`: if the user clicks Save while another tab is active, the card opens but the Settings tab stays hidden. The alerts are still not visible. This behavior existed before the change (the alerts were always in the Settings tab), so it is out of scope for #81. If you want to fix it, also show `#tab-general` (Bootstrap `tab('show')` through the `teBs` adapter) in the same branch.

### Assessment

**Ready to merge?** Yes. The probe renders show correct and balanced markup in both template engines, with and without the save error, and the start states agree with the 3 rules of issue #81. The items above are polish: one confusing `!!empty` and one test that does not check the condition polarity.
