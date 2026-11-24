# 98-oc3-sed-header-twig

## Settings
- Models: plan Opus (inline, Sonnet session), implementation Sonnet, review Opus.
- Review findings rule: fix all. Skip only with a verified reason.
- Build, tests: yes. Commit, push, PR: only if the user asks.

## Context
Issue #98. `build.sh:171-172` use the patterns `'<\?php echo \$'` and `'; \?\>'`. GNU `sed` reads `\?` as "zero or one" and `\>` as a word boundary. BSD `sed` reads `\?` as a literal `?`. The Linux build gives `<?{{ client_translate_expert_head_html; ?>` in the OC 3.0 OCMOD XML. The macOS build is correct. Only the OC 3.0 package is affected.
The investigation was done in the session that wrote the issue, so the Explore agent is skipped. The evidence: a run of the two patterns on a copy of the XML with GNU `sed` gave the broken lines 69 and 75.

## Approach
1. Replace `\?` with `[?]` in both patterns. This works in GNU and BSD `sed`.
2. Add `check_oc30_xml` to `build.sh`. It fails the build if the OC 3.0 XML has PHP tags after line 1 or lacks the two Twig lines.
3. Add `tests/test_oc30_ocmod_xml.php`. It extracts `file_replace` and the OC 3.0 XML replacement lines from `build.sh`. It runs them on a copy of the XML and checks the result.

## Assumptions
- ✅ The XML source has the 2 PHP echo lines only in `header.tpl` operations. Evidence: grep of the XML.

## Steps
- [x] Code: portable patterns and `check_oc30_xml` in `build.sh`
- [x] Test: `tests/test_oc30_ocmod_xml.php`, and a line in `CLAUDE.md`
- [x] Prove the test fails with the old patterns
- [x] Bump version + build + test
- [x] Independent review → 98-oc3-sed-header-twig-review.md
- [x] Address review findings
- [x] Improvements pass → 98-oc3-sed-header-twig-improvements.md

## Done
- Fixed issue #98. `build.sh` uses `[?]` and `[$]` in the OC 3.0 OCMOD XML patterns. `check_oc30_xml` fails the build on a PHP tag or a missing Twig line.
- New test `tests/test_oc30_ocmod_xml.php` (16 checks). It fails on the old `build.sh` (6 failures).
- Review: 0 critical, 0 important, 6 minor. All 6 fixed. No second review pass (the user chose to continue).
- Final version: 05.00.0102. Build and all tests pass. BSD `sed` was not run in the container (reasoned only).
- Not committed, not pushed. The user can ask for a commit, a push and a PR.
