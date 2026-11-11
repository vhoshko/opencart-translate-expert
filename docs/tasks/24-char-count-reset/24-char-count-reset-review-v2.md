# 24-char-count-reset: review v2 (atomic counter writes)

Scope: the uncommitted change only (`git diff HEAD`), version 04.01.0122.

Files in scope:

- `sources/upload/admin/model/extension/module/client_translate_expert.php`
- `sources/upload/admin/controller/extension/module/client_translate_expert.php`

Checks that I ran:

- `php -l` on the model: no syntax errors.
- `php tests/test_vh_google_translator.php`: 24 passed, 0 failed, 6 skipped.
- `php tests/test_client_translate_expert_route_filter.php`: 40 passed, 0 failed.

I did not run a store. I did not run the build. The findings below come from a read of the code and the two test scripts.

### Strengths

- The increment is one `UPDATE` statement (`model:179`). The statement does not use the config snapshot of the request. This removes the lost-increment race.
- The counter queries find the rows by `store_id` and `key` only. Rows in the old group work before the first settings save. No migration step in `install()` is necessary.
- `prepareCounterSettings()` moves the rows before `editSetting()` runs. The order in `index()` is correct, and the call is after `validate()`.
- The controller removes the two keys from the POST. A crafted POST cannot put a second copy of the counter in the module group.
- The model `uninstall()` and `install()` change only the `translate_expert_same_translations` table (`client_translate_expert_core.php:838-855`). `resetCache()` keeps the counter rows. Only the controller `uninstall()` deletes them.
- `deleteCounterSettings()` deletes by key, so it also removes rows that are still in the old group.
- The SQL escapes all values and uses `DB_PREFIX`. `$charCount` is cast to `int` before it goes into the SQL.
- The new code has no PHP 7+ syntax. No new AJAX method, library class or setting key needs a `build.sh` entry.
- The `CASE ... REGEXP` guard keeps the `UPDATE` safe in strict mode when the stored value is not a number.

### Issues

#### Critical (must fix)

None.

#### Important (should fix)

**1. `model:138-141`: the `group` / `code` test is possibly wrong for OpenCart 2.0.0.0.**

`settingGroupColumn()` returns `code` for each version from `2.0`. From memory, OpenCart 2.0.0.0 still has the `group` column in the `setting` table, and 2.0.1.0 renamed it to `code`. I did not verify this in a store or in the OpenCart sources. If it is true, these statements fail on 2.0.0.0 with an unknown column error:

- The `UPDATE` in `prepareCounterSettings()` (`model:167`). Each settings save then fails.
- The `INSERT` in `ensureCounterRow()` (`model:152`). The first translation request with no counter row then fails after Google charged the characters.

The oc2.0 package ships to these stores. The plan already lists the OC 1.5 branch as not tested, so the version test has two not-verified edges.

Fix: do not use the version number. Ask the database for the column, and keep the result in a private property:

```php
$query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "setting` LIKE 'code'");
return $query->num_rows ? 'code' : 'group';
```

This fix also removes the OC 1.5 assumption. A smaller fix is `version_compare(VERSION, '2.0.1.0', '<')`, after a check of the 2.0.0.0 schema.

**2. `model:148-154`: `ensureCounterRow()` can make a second row, and the two rows can then have different values.**

The method does a `SELECT` and then an `INSERT`. The `setting` table has no unique key on `store_id` and `key`. Two requests that find no row at the same time each insert a row.

The bad sequence is:

1. Request A and request B both find no count row.
2. Request A inserts `0` and adds 5. The row of A has the value 5.
3. Request B inserts `0`. Each later `UPDATE` changes both rows, so the rows keep a difference of 5.

After that, `getCounterValue()` uses `LIMIT 1` with no `ORDER BY`, so the row that it reads is not defined. The config loader keeps the last row that it reads. The settings page (`controller:264`, from the config) and `translatedCharCountInfo()` (from the model) can then show different counts. No code path repairs this condition. Only an uninstall removes the second row.

The window is small. It opens only when a row does not exist:

- The first counter use after an install, with no settings save before
- After an uninstall and a new install
- After a manual delete of the rows

`tmp/race.sh` does not catch this. It sets the row to `0` before the processes start, so the row always exists. The "missing rows" checks in `tmp/test_counter.sh` run one request at a time.

Fix, one of these options:

- Put the `SELECT` and the `INSERT` between `SELECT GET_LOCK('client_translate_expert_usage', 5)` and `RELEASE_LOCK(...)`.
- After each `INSERT`, delete the rows of this key where `setting_id` is larger than the smallest `setting_id` of this key. Read with `ORDER BY setting_id` in `getCounterValue()`.

Then add a `race.sh` variant that deletes the rows before the processes start.

#### Minor (nice to have)

**3. `model:201-211`: the monthly reset is two statements, and an increment between them is lost.**

Request A changes the month row. Before A sets the count to `0`, request B (which sees the new month) adds N. Then A writes `0` and removes the N characters of B. If A stops between the two statements, the count keeps the value of the old month until the next month.

The loss is limited to one short window in each month, so the risk is low. A multi-table `UPDATE` does both writes in one statement and closes the window:

```sql
UPDATE oc_setting m JOIN oc_setting c ON c.store_id = m.store_id AND c.`key` = '<count key>'
SET m.value = '<month>', c.value = '0'
WHERE m.store_id = '0' AND m.`key` = '<month key>' AND m.value <> '<month>'
```

**4. `model:203`: a missing month row now means "no reset".**

If the month row does not exist, `ensureCounterRow()` inserts the current month. The conditional `UPDATE` then changes no row, and the count stays. The old code did a reset in this condition. A count row with no month row is rare, because each settings save of the old versions wrote both rows. This is acceptable, but record it in the plan as a known behavior change.

**5. `model:174-184`: `addCharCount()` uses three queries for one increment.**

The method does a `SELECT` (in `ensureCounterRow()`), the `UPDATE` and a second `SELECT`. The caller (`controller:605`) does not use the return value. A check of `countAffected()` after the `UPDATE`, with the `INSERT` only when no row changed, removes the first `SELECT` from the usual path. The second `SELECT` is necessary only for `config->set()`, and no later code in the request reads that key.

**6. `model:179`: `value + N` uses floating-point arithmetic on a text column.**

MySQL converts the text to `DOUBLE` for the addition. The result is exact for all counts that a store can get, so this has no effect now. `CAST(value AS UNSIGNED) + N` makes the integer intent clear and keeps the text form stable for very large values.

**7. No automated test covers the new model methods.**

The proof is in `tmp/test_counter.sh` and `tmp/race.sh`, which need the Docker stores. This agrees with the earlier decision that no harness covers the admin code. Keep the two scripts after the task, because they are the only regression check for this logic.

### Assessment

**Ready to merge?** With fixes.

The core design is correct: the atomic increment, the separate group, the lookup by key, and the split between the model `uninstall()` and the controller `uninstall()`. Fix finding 1 or disprove it against an OpenCart 2.0.0.0 schema, because a wrong column name stops the settings save on that version. Fix finding 2 or accept it as a documented risk, because the tests do not cover the missing-row race and the result is a permanent wrong count.
