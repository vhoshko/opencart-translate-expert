# 70-oc15-fontawesome-fonts review

Scope: `git diff HEAD` (`_version.txt` 04.01.0204 → 04.01.0205) and the 5 new untracked files in `sources/upload/admin/view/stylesheet/oc15/fonts/`. The release `releases/v04.01.0205/` was unzipped into `docs/tasks/70-oc15-fontawesome-fonts/tmp/rel/` for inspection. The release `v04.01.0204` was unzipped into `tmp/old/` for comparison.

### Strengths

- The fix is at the correct location. `oc15/te/font-awesome.min.css` loads `url('../fonts/fontawesome-webfont.*?v=4.4.0')`. From `stylesheet/oc15/te/`, `../fonts/` resolves to `stylesheet/oc15/fonts/`. That is the new folder. The 5 file names match the 5 `url()` names exactly (`eot`, `woff2`, `woff`, `ttf`, `svg#fontawesomeregular`).
- The fonts are the correct version. The `cmp` check against `tmp/x/package/` (npm `font-awesome@4.4.0`) passes for all 5 fonts and for `font-awesome.min.css`. Thus the glyph code points agree with the CSS.
- All icon names that the OC 1.5 page uses exist in the 4.4.0 CSS: `fa-save`, `fa-reply`, `fa-pencil`, `fa-exchange`, `fa-exclamation-circle`, `fa-exclamation-triangle`, `fa-eye`, `fa-eraser`, `fa-download`, and the `teIcon` names `times`, `info-circle`, `check-circle`. The 2 names that are not in 4.4.0 (`fa-solid`, `fa-right-left`) come only from the FA 6 path of `teIcon`, which runs only with Bootstrap 5 (OC 4).
- `FontAwesome.otf` is correctly left out. The CSS does not reference it.
- No code changes, no `build.sh` change. The OCMOD `<!--OC15-TE-->` link in `build.sh:125` already loads the CSS, and `build.sh:216` already removes `oc15` from the OC 4 package.
- Build output is correct. The oc1.5, oc2.0, oc2.1, oc2.2, oc2.3 and oc3.0 zips contain `upload/admin/view/stylesheet/oc15/fonts/` with the 5 files. The oc4.x zip contains no `oc15` path.
- Git handles the files as binary. `git diff --numstat` shows `-  -` for the `woff`. The `svg` has no CR characters, so a `core.autocrlf` conversion cannot change it. A `.gitattributes` file is not necessary. The repository already keeps PNG files without one.
- The `tmp/` folder (6.3 MB, with the npm tarball) is ignored by `.gitignore:20` (`/docs/tasks/*/tmp/`). It will not go into the commit.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

1. **Package size on OC 2.0–3.0.** Each OCMOD zip grows from approximately 180 KB to approximately 585 KB (+405 KB). The `.full.zip` grows from 1.19 MB to 3.62 MB. On OC 2.0–3.0 the `oc15` folder is dead weight, because the OCMOD loads the `oc15` CSS only if `VERSION < 2.0`. This is not a regression: these packages already ship `oc15/te/` (jQuery, Bootstrap, CSS). The new size is also well below the 2 MB default `upload_max_filesize`. If you want smaller packages, add `rm -rf .../admin/view/stylesheet/oc15` for the 2.0–3.0 release folders in `build.sh`, the same as line 216 for OC 4. Do this in a separate task, because it changes package contents for 5 versions. A smaller option: drop `eot` and `svg` (only IE 8 and old iOS Safari use them). Do not do this, because the CSS references them and the files must stay byte-identical to the upstream package.

2. **Glyphicons fonts are missing too, but this has no effect.** `oc15/te/bootstrap.css` references `../fonts/bootstrap/glyphicons-halflings-regular.*`, and that folder does not exist. No template, JS or PHP file under `sources/upload/` uses a `glyphicon` class (grep found none outside `bootstrap.css`). A browser requests an `@font-face` file only when an element uses that font. Thus the page makes no 404 request. Do not add the files. If a later change adds a `glyphicon` class, it must also add these fonts.

### Assessment

**Ready to merge?** Yes. The 5 fonts are the exact upstream Font Awesome 4.4.0 files, they are at the path the CSS resolves to, and all release packages that load the CSS contain them. The 2 minor items need no action in this task. A real OC 1.5 store run is still necessary to confirm the 200 responses (the plan marks this as unverified).
