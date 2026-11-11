# 24-char-count-reset: review v3 (fixes after review v2)

Scope: the four fixes after review v2 in `sources/upload/admin/model/extension/module/client_translate_expert.php` (commit `132dec0`, lines 128-229). The design, the controller and the decided v1 and v2 findings are not in scope.

Checks that I ran:

- `php -l` on the model: no syntax errors.
- `php tests/test_vh_google_translator.php`: 24 passed, 0 failed, 6 skipped.
- `php tests/test_client_translate_expert_route_filter.php`: 40 passed, 0 failed.
- A read of the DB drivers in the four Docker stores (`system/library/db/mysqli.php`, `pdo.php`).
- A read of the `oc_setting` table data in the four stores: engine, indexes and the `code` column.

I did not run the build. I did not run `test_counter.sh` or `race.sh`. I did not write to a store database. The lock finding below (finding 1) comes from a read of the code only.

### Strengths

- **Fix 1, `settingGroupColumn()` (`model:141-149`).** `SHOW COLUMNS ... LIKE 'code'` is valid on MySQL 5.x, MySQL 8.x and MariaDB. Each driver that I read returns `num_rows` for a result set: `mysqli` on OC 2.3 to 4.1, and `pdo` on OC 3.0 to 4.1. The pattern has no wildcard character. The cache holds the result for the request, and the query runs only on the insert path and the save path.
- **Fix 2, the `DELETE` with a derived table (`model:164`).** The derived table has an aggregate (`MIN`), so the server must materialize it. MySQL 5.7+ and MariaDB cannot merge it into the outer query, so error 1093 (target table in `FROM`) does not occur. `JOIN` with no `ON` clause is valid MySQL syntax. `MIN` with no `GROUP BY` is valid with `ONLY_FULL_GROUP_BY`. A `NULL` `keep_id` deletes nothing.
- **Fix 2, the read order (`model:153`).** `ORDER BY setting_id ASC LIMIT 1` reads the row that the `DELETE` keeps. `setting_id` is the primary key in all four stores.
- **Fix 3, the self-join `UPDATE` (`model:218`).** MySQL and MariaDB permit an `UPDATE` of two aliases of one table when the statement does not change a key column. The statement changes `value` only. The two calls of `ensureCounterRow()` before the statement make sure that the join finds a count row.
- **Fix 3, `countAffected()`.** The server counts changed rows, not matched rows. In a new month the month row always changes, so the result is 1 or more, also when the count is already `0`. In the same month the `WHERE` clause matches no row, so the result is 0, also with a "found rows" connection flag. No query runs between the `UPDATE` and `countAffected()`. The `mysqli` drivers read `affected_rows`, and the `pdo` drivers read `rowCount()` of the same statement.
- **Fix 4, `CAST(value AS UNSIGNED) + N` (`model:196`).** The `CASE` evaluates the `CAST` only when the `REGEXP` matches, so a non-numeric value does not cause a strict-mode error. The result is an integer, and the text form has no exponent.
- The new SQL escapes all values and uses `DB_PREFIX`. The new code has no PHP 7+ syntax. The fixes add no AJAX method, library class or setting key, so `build.sh` needs no new entry.
- `race.sh norows` deletes the rows first. The script fails if a process stops with an exception, because the final count is then less than 1000.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

None.

#### Minor (nice to have)

**1. `model:164`: the cleanup `DELETE` can possibly cause an InnoDB deadlock on OC 4.x. Not verified.**

The `oc_setting` table is InnoDB on OC 4.0 and 4.1 and MyISAM on OC 2.3 and 3.0. The table has no index on `key`, so each statement scans all rows.

In a `DELETE`, InnoDB reads the derived table with shared locks at the default `REPEATABLE READ` level. The outer scan then asks for exclusive locks on the same rows. If a second writer waits for an exclusive lock on one of those rows at that moment, InnoDB reports a deadlock (error 1213). The second writer can be one of these statements:

- The `DELETE` of a second request with no row
- The `UPDATE` in `addCharCount()`
- The `DELETE` in `editSetting()`

The driver then throws an exception. If the failed statement is in `storeTranslatedCharCount()`, the request fails after Google charged the characters.

The evidence against this finding is strong: `race.sh norows` gave exactly 1000 on oc40 with 10 processes. I did not reproduce a deadlock. The window opens only when a counter row does not exist, which is the first use after an install. MyISAM stores are not affected.

A possible hardening, if you want it: put the cleanup `DELETE` in `try { ... } catch (\Exception $e) { }`. The cleanup is not necessary for the request that fails, because the other request removes the second row, or the next insert does. This syntax is valid on PHP 5.6. This does not help when the `UPDATE` of the other request is the deadlock victim. It is acceptable to do nothing.

**2. `model:162-163`: the comment "the oldest row has all increments" has one theoretical exception.**

On InnoDB, request A can get `setting_id` 1 and request B can get `setting_id` 2, and the insert of B can become visible first. An `UPDATE` in that gap changes row 2 only. The cleanup then keeps row 1 and loses that increment. The gap is a few microseconds, between two single-statement inserts, and only on first use. No code change is necessary. I record it only because the comment states the rule without a limit.

### Assessment

**Ready to merge?** Yes.

The four fixes are correct and portable on MySQL 5.x, MySQL 8.x and MariaDB, and through the `mysqli` and `pdo` drivers of OC 2.3 to 4.1. The fixes add no regression that I can find. The two Minor findings apply only to concurrent requests in the first-use window, and the `norows` race test did not show them.

Not verified in this pass: the OC 1.5 `mysql` driver and the OC 2.0 to 2.2 drivers, because no store with those versions exists. The statements use only syntax that MySQL 5.0 and later accept, and those drivers return `num_rows` and the affected row count in the standard way.
