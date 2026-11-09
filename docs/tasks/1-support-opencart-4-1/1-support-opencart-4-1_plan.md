# 1-support-opencart-4-1

## Settings
- Planning model: Opus 5.5 (session model, inline)
- Review model: Sonnet 5.5 (`ote-code-reviewer` dispatched with `model: sonnet`, so the reviewer differs from the planner)
- **Standing auto-approve: ON.** The user said "select as approved all options which you marked as recommended". Decide review findings and recommended options without asking, and report each decision with its reasoning. Changes to the agreed approach, external-system risk or scope expansion still go back to the user.
- Build/tests: run per the workflow. The workflow does not commit, push or open PRs on its own. The user explicitly asked for the handoff commit + push on 2026-11-09.

## Status: handoff (2026-11-09)
The session was paused in **Phase 0** while the user rebuilds the sandbox container, because the old one had **no PHP interpreter**.
- Done: issue fetched, branch `feat/1-support-opencart-4-1` created from `origin/main` (version `04.01.0105`), and this task folder.
- Sanity check (`ote-build-test`, checks only) was **BLOCKED**: there was no `php` binary, so `php -l` and the unit tests did not run. The grep-based PHP 5.6 check was clean. The only PHP 7+ syntax is the controller's OC4 marker block (lines 774–807), which is allowed.
- An investigation (Explore) agent was comparing our OC4 package against OpenCart 4.1. Its results were **not** captured before the pause, so re-run it.

### Resume checklist (new session)
1. `command -v php && php -v` must succeed. Then re-run the sanity check (`ote-build-test`, "checks only").
2. Re-create the OpenCart reference sources in `docs/tasks/1-support-opencart-4-1/tmp/oc/` (gitignored):
   ```bash
   for t in 4.0.2.3 4.1.0.4; do
     git clone -q --depth 1 --branch $t --filter=blob:none --sparse https://github.com/opencart/opencart.git oc-$t
     (cd oc-$t && git sparse-checkout set --no-cone /upload/admin/ /upload/system/engine/ /upload/system/library/ \
       /upload/system/framework.php /upload/system/startup.php /upload/system/config/ /upload/system/helper/ \
       /upload/install/ /upload/extension/opencart/admin/ /upload/catalog/controller/startup/)
   done
   ```
   The latest tags at handoff were 4.0.x → `4.0.2.3` and 4.1.x → `4.1.0.0`–`4.1.0.4`.
3. Re-dispatch the Explore investigation. The brief is below.
4. Write the Context/Approach/Assumptions/Steps sections, then continue from Phase 1 under auto-approve.

### Investigation brief (to re-use)
For every OpenCart core API or convention the OC4 package uses (`build.sh` OC4 section ~L150–400, plus the controller's `OC4_EVENT` block), diff the behaviour between 4.0.2.3 and 4.1.0.4:
- the setting/event/extension/language/user_group model methods and signatures (`editValue`, `addEvent`, `deleteEventByCode`, `getLanguages` keys incl. `image`)
- the event trigger/action format and handler signature
- the extension installer flow (install.json, `install()`/`uninstall()`, permissions)
- the loader/request/response/url/user/config/Log/DB APIs, `DIR_EXTENSION`
- the admin layout and assets (Bootstrap, jQuery, FontAwesome versions; `#alert`; `data-oc-toggle`) used by `.oc4.twig` and the JS
- the DB schema for the queried tables (`oc_language`, `oc_setting`)
- the PHP minimum and PHP 8.2+ deprecations (dynamic properties, `${}` interpolation, null passed to string functions)
- hardcoded `4.0` version checks and package naming (`oc4.0`)

Deliverables: a breakage table with path:line evidence from the 4.1 tree, and a recommendation on whether one OC4 package can cover 4.0 and 4.1 (runtime `version_compare`) or 4.1 needs its own build variant.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/1, "Support Opencart 4.1.*" (empty body). Affects the OC 4.x package only, which is built from the OC 3.0 variant in `build.sh`. TBD after the investigation.

## Approach
TBD

## Assumptions
TBD

## Steps
- [ ] Phase 0: PHP available + sanity check, investigation, verify 4.1 behaviour against the OC source
- [ ] Plan approval (auto-approve)
- [ ] Code / build.sh changes
- [ ] Bump version + build + test
- [ ] Independent review → 1-support-opencart-4-1-review.md
- [ ] Address review findings
- [ ] Bump version + build + tests (verify loop)
- [ ] Re-review?
- [ ] Improvements pass → 1-support-opencart-4-1-improvements.md

## Done
