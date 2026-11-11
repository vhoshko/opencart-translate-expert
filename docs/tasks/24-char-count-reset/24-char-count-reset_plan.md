# 24-char-count-reset

## Settings
- Planning model: Opus 5.5 (inline). Review model: Fable 5.2 (pass `model: fable` to `ote-code-reviewer`).
- Build and tests: yes. Commit, push and PR: yes. The user asked for a PR after the review.
- Standing auto-approve for review findings: no.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/24

Each save of the module settings form sets `client_translate_expert_char_count` to 0. The store then loses the counted Google API usage for the month. All OpenCart versions (1.5–4.x) run this code path.

Root cause:
- `sources/upload/admin/view/template/extension/module/client_translate_expert.tpl:76` and `client_translate_expert.oc4.twig:76` show the count in a `<div>`. A `<div>` is not a form field, so the POST never contains the key.
- `sources/upload/admin/controller/extension/module/client_translate_expert.php:48-51` sets the missing key to 0. Then line 64 calls `editSetting()` with the POST, and this call writes 0 over the stored count.
- Lines 60-63 do the same for `client_translate_expert_last_reset_char_month`. Each save sets it to `date('m')`.

Other writes of these two keys use `editSettingValue` only (lines 573-574, 611, 623-624).

## Approach
In `index()`, before `editSetting()`, set the two keys from the stored config values. Use `0` and `date('m')` only if no stored value exists.

Design correction: the issue says "if the POST has no key". The plan always uses the stored value and ignores the POST value. No form field sends these keys, so a POST value can only come from a crafted request. This change also blocks a manual overwrite of the counter through the settings form.

On OC 3.0 and 4.x, `build.sh:127` and `build.sh:394` add the `module_` prefix to both keys. Both keys are already in the fixed list, so `build.sh` needs no change.

## Assumptions
- ✅ verified: `$this->config->get('client_translate_expert_char_count')` returns the stored count in the admin controller on all versions. Evidence: the production code at `client_translate_expert.php:538` and `:607` reads the count this way.
- ✅ verified: `build.sh` rewrites both keys to the `module_` form for OC 3.0 and 4.x. Evidence: the fixed list at `build.sh:127` and `build.sh:394` has both keys.

## Steps
- [x] Code: in `index()`, replace the two `isset` blocks for `char_count` and `last_reset_char_month` with an unconditional copy of the stored value (fallback `0` and `date('m')`).
  Findings: the change is in `client_translate_expert.php:48-53`. The code uses a ternary with no `??`, so it stays PHP 5.6 compatible. The user approved the full flow.
- [x] Bump version + build + test
  Findings: version 04.01.0120. Build green. Tests: 24 passed, 6 skipped (no API key) and 40 passed. The oc3.0 and oc4.x packages use the `module_` prefix for both keys in `index()`.
- [x] Independent review → 24-char-count-reset-review.md
  Findings: Fable 5.2 says ready to merge. It found 0 Critical, 0 Important and 3 Minor findings.
- [x] Address review findings (the user picks which to fix or skip)
  Findings:
  - Finding 1 (a translation that runs during a save can lose its count): the user chose to skip it, with no new issue. A full fix needs a redesign: an own setting group, a SQL increment and a migration. `storeTranslatedCharCount()` has the same race between two translation requests.
  - Finding 2 (no test and no reproduction in a store): no code change. The user can do the check steps from the issue by hand.
  - Finding 3: fixed. The count `<div>` in the `.tpl` and `.oc4.twig` has no `name` attribute now. No JS or OCMOD selects it by name.
- [x] Bump version + build + tests (verify loop)
  Findings: version 04.01.0121. Build green. Tests: 24 passed, 6 skipped and 40 passed. The oc4.x template has the `div` with no `name` attribute and shows the count as before.
- [x] Re-review? (ask the user)
- [x] Improvements pass → 24-char-count-reset-improvements.md

Skipped steps:
- Templates and language strings: no UI change.
- Investigation subagent: the issue gives the exact lines. A direct read confirmed them.
- Regression test: no test harness covers the admin controller. The suites cover `VHGoogleTranslator` and `ClientTranslateExpertRouteFilter` only.

## Done
- A settings save now keeps the stored `client_translate_expert_char_count` and `client_translate_expert_last_reset_char_month` values. A POST value for these keys has no effect.
- The count `<div>` in the `.tpl` and `.oc4.twig` has no `name` attribute now.
- Final version: 04.01.0121. Build green. Tests: 24 passed, 6 skipped and 40 passed.
- Deferred: the counter read-modify-write race (review finding 1). The user chose to skip it, with no issue.
- Not done: a manual check in a running store. The user asked for the commit, push and PR after the review.
