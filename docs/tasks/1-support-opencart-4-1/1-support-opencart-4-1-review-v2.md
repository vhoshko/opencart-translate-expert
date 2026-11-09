# Review v2: OpenCart 4.1 Docker test store (static, nothing run)

### Strengths
- Every former `oc40`-only branch in `docker/install-module.sh` now goes through `is_oc4`: login, install dispatch, smoke-check route, the event count, and the PASS message. A grep for `oc40` finds only the argument parsing, the port lookup, the package lookup and `is_oc4` itself. There are no leftover `[ "$STORE" = "oc40" ]` tests.
- `is_oc4` is a plain `case` function with no bash 4 features. It is only used inside `if`/`elif`, so `set -e` cannot trigger on its non-zero return.
- The package glob `*-oc4.[0x]-*/client_translate_expert.ocmod.zip` is plain pathname expansion, so it works in bash 3.2. It keeps the `ls ... | head -n 1 || true` shape, so an empty match still falls through to the "no package for $STORE" failure. It cannot collide with the `oc2.3` or `oc3.0` globs.
- `oc41` in `compose.yaml` mirrors `oc40`: same build context, `store-env` anchor, port variable pattern, `depends_on` health condition, and a distinct image tag. `OC_UPLOAD_DIR: upload` matches the 4.1 zip layout, and the SHA-256 is the pinned one.
- `init.sql` has both the `CREATE DATABASE` and the `GRANT` for `oc41`.
- The README table, port variables, the "all four stores" wording and the 4.x terminology are accurate. They match the code and the main `README.md`.

### Issues

#### Critical (must fix)
None.

#### Important (should fix)
None.

#### Minor (nice to have)
- `docker/README.md`, last bullet: the workaround is correct, assuming the `mariadb` client binary exists in `mariadb:10.6`, which it does. It does not say what the failure looks like. On an old volume without `oc41`, `docker compose up` for `oc41` never finishes. `entrypoint.sh` loops forever on `[opencart] waiting for database db/oc41...`, with no error and no timeout. Add that log line as the symptom so the bullet can be found from the log. The infinite loop is old behaviour, not new.
- `docker/install-module.sh:197`: the section banner `# --- OC 4.x (4.0, 4.1) ---...---` is longer than the other banners. Cosmetic.
- `docker/install-module.sh:88`: the comment says "Builds before 04.01.0111 named its folder oc4.0". That is accurate for the version bump in the working tree, but the rule is easy to misread if 0111 is released under a different number. Consider "Older builds named the folder oc4.0".
- `docker/compose.yaml`, the oc41 comment: "PHP 8.2 is used so its new deprecations show up" is a sound reason. Note that `oc40` stays on PHP 8.1, so 8.2-only deprecations are covered only by `oc41`. This is intended and needs no change.

### Assessment
**Ready to merge?** Yes. The `install-module.sh` edits cover every oc40-only branch for oc41. The glob is bash 3.2 safe and handles both the old `oc4.0` and the new `oc4.x` folder. The compose service, `init.sql` and docs are consistent with the existing services and with the verified 4.1.0.4 facts. Only the optional doc polish above remains.
