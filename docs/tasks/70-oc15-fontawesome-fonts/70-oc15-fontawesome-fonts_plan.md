# 70-oc15-fontawesome-fonts

## Settings
- Planning model: Opus (the plan is short, so the session wrote it inline). Review model: Opus (the user confirmed that the review is not independent).
- Auto-approve: yes. Decide review findings and the re-review gate. Apply the improvements. Then commit, push and open the PR.
- Flow: stripped-down. The change adds binary files and touches no code.

## Context
Issue: https://github.com/vhoshko/opencart-translate-expert/issues/70. OC 1.5 only.
The CSS `sources/upload/admin/view/stylesheet/oc15/te/font-awesome.min.css` is Font Awesome 4.4.0. It loads `../fonts/fontawesome-webfont.*`. That path is `oc15/fonts/`. The folder did not exist, so the browser showed empty rectangles.

## Approach
Add the 5 web font files of Font Awesome 4.4.0 to `sources/upload/admin/view/stylesheet/oc15/fonts/`. Do not change `build.sh` if the packages already copy the folder.

## Assumptions
- ✅ verified: the npm package `font-awesome@4.4.0` has a `font-awesome.min.css` that is byte-identical to the file in the repository (`cmp`). Its fonts match the CSS.
- ✅ verified: `build.sh:216` removes `oc15` only from the OC 4 package.
- ❓ unverified: no OC 1.5 store run. A 200 response for the font requests needs a store run.

## Steps
- [x] Code: add the 5 font files. Findings: skipped `FontAwesome.otf`, because the CSS does not use it.
- [x] Bump version + build + test. Check the fonts folder in each package.
- [x] Independent review → 70-oc15-fontawesome-fonts-review.md
- [x] Address review findings. Findings: no Critical or Important finding, so no fix pass and no re-review.
- [x] Improvements pass → 70-oc15-fontawesome-fonts-improvements.md

## Done
- Added the 5 Font Awesome 4.4.0 web font files to `oc15/fonts/`. The OC 1.5 package now ships them.
- Final version: 04.01.0206. All test suites pass.
- Gotcha: the OC 2.0 to 3.0 packages also shipped `oc15/`, so each zip would grow by about 400 KB. Fixed in the same PR: `build.sh` excludes `upload/admin/view/stylesheet/oc15/*` from the OC 2.0 to 3.0 zips. The 9 `oc15` files are the only difference. Only the OC 1.5 package keeps them.
- Not verified: a real OC 1.5 store run (font requests must return 200).

## Update 2026-11-17 (user request)
The user asked to fix the package size in the same PR. Change: `-x 'upload/admin/view/stylesheet/oc15/*'` on 5 zip lines of `build.sh`. Build and tests pass (04.01.0206). Review: no new review pass. The change only adds zip exclusions, and the build agent compared the file lists of both releases.
