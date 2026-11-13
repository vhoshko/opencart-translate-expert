# Review: issue #50 module language keys

### Strengths
- The three language files now have the same key set. The new test passes (371 passed, 0 failed).
- I made a mutation copy (key `entry_name` renamed to `Entry_name` in ru-ru). The test failed with the missing and extra key names. The test detects the original bug.
- A grep of `sources`, `tests`, `tools`, `build.sh`, `build.release.sh` and `docker` found no use of the 7 removed keys. The only dynamic key is `value_analization_mode_` plus a mode. That key set is intact in all three languages.
- JS, `.tpl` and `.oc4.twig` match. The controller passes the four new `js_const_*` values.
- ru-ru and uk-ua translations are correct. The `{currIndex}`, `{count}` and `{path}` placeholders are kept.
- The new strings have no double quote, backslash or newline. They are safe inside the double-quoted JS literals in both templates.
- The `Таблиця` to `Table` fix is applied in en-gb.
- The library change uses no PHP 7+ syntax. `php -l` passes.

### Issues

#### Critical (must fix)
None.

#### Important (should fix)
None.

#### Minor (nice to have)
- `tests/test_client_translate_expert_localization.php`: the test compares only key names. It does not check that the `{placeholders}` of each `js_const_*` value match across languages. This is optional.
- `htmlspecialchars()` on the two button labels is a safe guard. It has no observed failure case, and it does no harm.

### Assessment
**Ready to merge?** Yes. The change is complete and consistent, and the test fails on a key mismatch. I found no reference to a removed key.
