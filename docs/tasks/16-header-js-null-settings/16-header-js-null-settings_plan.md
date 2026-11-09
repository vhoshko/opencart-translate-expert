# 16-header-js-null-settings

## Settings
- Planning: Opus 5.5 (inline). Review: Fable 5.1 (pass `model: fable` to `ote-code-reviewer`).
- Flow: stripped-down (user, 09.11.2026). No investigation agent, no plan-approval stop, no improvements write-up. One review pass.
- Build + tests: yes. Commit / push / PR: not by this workflow; ask.
- Auto-approve for review findings: not granted.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/16. On OC 2.3/3.0, before the module settings are first saved, every admin page emits `document.js_const_client_translate_expert_is_enabled = ;` and the same for `_show_char_count_is_enabled`. That is a JS syntax error, and it also drops `js_const_client_translate_expert_languages`. The OCMOD assigns `$this->config->get(...)` unchanged (`sources/upload/system/OpenCartTranslateExpertClient.ocmod.xml:13-14`), and it is `null` for keys that were never saved. Affects every OCMOD package (OC 1.5–3.0). OC 4.0 uses the header event, which already casts (`admin/controller/extension/module/client_translate_expert.php:793-794`).

## Approach
Cast both values with `(int)` at the assignment in `ocmod.xml:13-14`. One change covers 1.5–3.0. `build.sh`'s rewrites leave it intact: 1.5 changes `$data[` to `$this->data[`; 3.0 adds the `module_` prefix to the setting key and turns `<?php echo $x; ?>` into `{{ x }}`, only on the template side.

## Assumptions
- ✅ Bug reproduced 09.11.2026 on fresh Docker stores (OC 2.3.0.2 / PHP 5.6, OC 3.0.3.9 / PHP 7.4) with package v04.01.0108: all four lines are `= ;`.
- ✅ The `build.sh` rewrites don't touch a controller-side `(int)` (`build.sh:114,127-140`).
- ✅ Twig on OC 3.0.3.9 renders an int `0` from `{{ x }}` as `0` (post-fix run on oc30: `= 0;`).

## Steps
- [x] Reproduce on fresh oc23 / oc30 (v04.01.0108). Tool: `tmp/header-consts.sh`.
- [x] Code: `(int)` cast at `ocmod.xml:13-14`
  Findings: also dropped trailing whitespace on line 13.
- [x] Bump version + build + test
  Findings: 04.01.0108 → 04.01.0109, 24 passed / 6 skipped. The cast is present in the oc1.5 (`$this->data[...]`), oc2.3 and oc3.0 (`module_` keys) XML; the 3.0 template keeps `{{ client_translate_expert_is_enabled }}`; oc4.0 ships no XML.
- [x] Verify on fresh oc23 / oc30: unsaved → `= 0;`; after saving with status on → `= 1;`; smoke checks pass
  Findings: fresh stores with v04.01.0109 print `= 0;` for both constants on 2.3 and 3.0. After saving status=1 and show_char_count_globally=1: `= 1;` on both. Smoke checks 9/9 PASS on oc23/oc30/oc40.
- [x] Prove the check catches the bug: v04.01.0108 on fresh stores printed `= ;` (step 1). There is no unit-test harness for OCMOD output, so the Docker run is the regression evidence; no automated regression test was added.
- [x] Independent review → 16-header-js-null-settings-review.md (Fable 5.1: clean, 0 critical / 0 important, 2 minor)
- [x] Address review findings
  Decisions (user, 09.11.2026): fix m1, the `'{}'` fallback when `json_encode` of the languages returns false (`ocmod.xml:16`, mirrors the OC4 handler at `client_translate_expert.php:795`). Skip m2 (no automated test harness for OCMOD output).
- [x] Bump version + build + tests (verify loop) + store re-check
  Findings: 04.01.0109 → 04.01.0110, 24 passed / 6 skipped. Line 16 is intact in the oc1.5 / oc2.3 / oc3.0 XML (the 3.0 template rewrite doesn't touch it). Fresh oc23/oc30 on v04.01.0110: `= 0;` for both flags, the languages JSON renders, 9/9 PASS (oc40 included). The `'{}'` branch itself is not exercised: `json_encode` would need invalid UTF-8 in a language name, and the utf8mb4 column rejects that.
- [x] Re-review? Asked 09.11.2026.

## Done
- **What landed:** `ocmod.xml:13-14` casts both header flags with `(int)`, so unsaved settings print `0` instead of an empty string. `ocmod.xml:16` falls back to `'{}'` if `json_encode` of the languages fails, mirroring the OC4 handler. This applies to all OCMOD packages (OC 1.5–3.0). OC 4.0 is unchanged.
- **Final version:** 04.01.0110.
- **Evidence:** v04.01.0108 printed `= ;` on fresh Docker stores (OC 2.3 and 3.0); v04.01.0110 prints `= 0;`, and `= 1;` once the settings are saved with those options on. One Fable review: clean.
- **Not covered:** no automated test for OCMOD output (review m2, skipped). The `'{}'` branch was not exercised.
- **Flow:** stripped-down (user's choice), so there is no improvements file. Nothing is committed yet.
