# MYXPLAIN Query Catalog (MCP / db_explain_table)

- Source cases: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`
- Pattern rule used: `YYYYYY.id_XXXXXX -> XXXXXX.id`
- Endpoint source: `127.0.0.1:13306`
- Total MYXPLAIN cases: `27`
- Variants per case: `4`
- Total generated queries: `108`
- Pass: `101`
- Fail: `7`

## Replay Command

```bash
php scripts/generate_myxplain_query_catalog.php
```

## MXP-001 - case `ALL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_server | id_mysql_server |       5 | pmacontrol.t.id | 3447 | Using where; Using index                                  |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-002 - case `ALL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `archive.id_backup_storage_area -> backup_storage_area.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_backup_storage_area`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `archive` c 
WHERE c.`id_backup_storage_area` IS NOT NULL 
GROUP BY c.`id_backup_storage_area` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+------------------------+------------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys          | key                    | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+------------------------+------------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_backup_storage_area | id_backup_storage_area |       4 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+------------------------+------------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-003 - case `ALL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `archive.id_cleaner_main -> cleaner_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_cleaner_main`, t.id AS target_id 
FROM (
SELECT id, `id_cleaner_main` 
FROM `archive` 
WHERE `id_cleaner_main` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `cleaner_main` t ON t.id = x.`id_cleaner_main` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY         | id_mysql_server |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0            | key0            |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | archive    | index | id_cleaner_main | PRIMARY         |       4 | NULL            |    1 | Using where                                  |
+----+-------------+------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-004 - case `ALL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `archive_load.id_cleaner_main -> cleaner_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_cleaner_main`, ROW_NUMBER() OVER (PARTITION BY c.`id_cleaner_main` 
ORDER BY c.id DESC) AS rn 
FROM `archive_load` c 
WHERE c.`id_cleaner_main` IS NOT NULL) 
SELECT r.id, r.`id_cleaner_main`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `cleaner_main` t ON t.id = r.`id_cleaner_main` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+---------------+-----------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys | key             | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+---------------+-----------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY       | id_mysql_server |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0          | key0            |       5 | pmacontrol.t.id |    2 | Using where                                  |
|  2 | DERIVED     | c          | ALL   | NULL          | NULL            | NULL    | NULL            |    1 | Using where; Using temporary                 |
+----+-------------+------------+-------+---------------+-----------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-005 - case `CONST` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `archive_load.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `archive_load` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |       4 | NULL                               |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-006 - case `CONST` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `archive_load.id_user_main -> user_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_user_main`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `archive_load` c 
WHERE c.`id_user_main` IS NOT NULL 
GROUP BY c.`id_user_main` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra                                        |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
|  1 | SIMPLE      | c     | ALL  | NULL          | NULL | NULL    | NULL |    1 | Using where; Using temporary; Using filesort |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
```

## MXP-007 - case `CONST` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `archive_load_detail.id_archive -> archive.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_archive`, t.id AS target_id 
FROM (
SELECT id, `id_archive` 
FROM `archive_load_detail` 
WHERE `id_archive` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `archive` t ON t.id = x.`id_archive` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+---------------------+-------+---------------+------------------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table               | type  | possible_keys | key                    | key_len | ref             | rows | Extra                                        |
+----+-------------+---------------------+-------+---------------+------------------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY       | id_backup_storage_area |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0          | key0                   |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | archive_load_detail | index | id_archive    | PRIMARY                |       4 | NULL            |    1 | Using where                                  |
+----+-------------+---------------------+-------+---------------+------------------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-008 - case `CONST` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `archive_load_detail.id_archive_load -> archive_load.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_archive_load`, ROW_NUMBER() OVER (PARTITION BY c.`id_archive_load` 
ORDER BY c.id DESC) AS rn 
FROM `archive_load_detail` c 
WHERE c.`id_archive_load` IS NOT NULL) 
SELECT r.id, r.`id_archive_load`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `archive_load` t ON t.id = r.`id_archive_load` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY         | PRIMARY         |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0            | key0            |       5 | pmacontrol.t.id |    2 | Using where                                  |
|  2 | DERIVED     | c          | index | id_archive_load | id_archive_load |       4 | NULL            |    1 | Using where; Using index; Using temporary    |
+----+-------------+------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-009 - case `EQ_REF` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_database.id_backup_dump -> backup_dump.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_backup_dump`, t.id AS target_id 
FROM `backup_database` c 
JOIN `backup_dump` t ON t.id = c.`id_backup_dump` 
WHERE c.`id_backup_dump` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+----------------+---------+---------+-----------------------------+------+-------------+
| id | select_type | table | type   | possible_keys  | key     | key_len | ref                         | rows | Extra       |
+----+-------------+-------+--------+----------------+---------+---------+-----------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_backup_dump | PRIMARY |       4 | NULL                        |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY        | PRIMARY |       4 | pmacontrol.c.id_backup_dump |    1 | Using index |
+----+-------------+-------+--------+----------------+---------+---------+-----------------------------+------+-------------+
```

## MXP-010 - case `EQ_REF` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_dump.id_backup_main -> backup_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_backup_main`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `backup_dump` c 
WHERE c.`id_backup_main` IS NOT NULL 
GROUP BY c.`id_backup_main` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys  | key            | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_backup_main | id_backup_main |       4 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-011 - case `EQ_REF` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_dump.id_job -> job.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_job`, t.id AS target_id 
FROM (
SELECT id, `id_job` 
FROM `backup_dump` 
WHERE `id_job` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `job` t ON t.id = x.`id_job` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table       | type  | possible_keys | key     | key_len | ref             | rows | Extra                                        |
+----+-------------+-------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t           | index | PRIMARY       | PRIMARY |       4 | NULL            |    2 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>  | ref   | key0          | key0    |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | backup_dump | index | id_job        | PRIMARY |       4 | NULL            |    1 | Using where                                  |
+----+-------------+-------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
```

## MXP-012 - case `EQ_REF` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_dump.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `backup_dump` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+--------------------+--------------------+---------+-------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys      | key                | key_len | ref                     | rows | Extra                                     |
+----+-------------+------------+--------+--------------------+--------------------+---------+-------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL               | NULL               | NULL    | NULL                    |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY            | PRIMARY            |      11 | r.id_mysql_server,const |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_backup_database | id_backup_database |       4 | NULL                    |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+--------------------+--------------------+---------+-------------------------+------+-------------------------------------------+
```

## MXP-013 - case `FILESORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_main.id_backup_storage_area -> backup_storage_area.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_backup_storage_area`, t.id AS target_id 
FROM `backup_main` c 
JOIN `backup_storage_area` t ON t.id = c.`id_backup_storage_area` 
WHERE c.`id_backup_storage_area` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+------------------------+---------+---------+-------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys          | key     | key_len | ref                                 | rows | Extra       |
+----+-------------+-------+--------+------------------------+---------+---------+-------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_backup_storage_area | PRIMARY |       4 | NULL                                |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY                | PRIMARY |       4 | pmacontrol.c.id_backup_storage_area |    1 | Using index |
+----+-------------+-------+--------+------------------------+---------+---------+-------------------------------------+------+-------------+
```

## MXP-014 - case `FILESORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_main.id_backup_type -> backup_type.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_backup_type`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `backup_main` c 
WHERE c.`id_backup_type` IS NOT NULL 
GROUP BY c.`id_backup_type` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys  | key            | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_backup_type | id_backup_type |       4 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-015 - case `FILESORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_main.id_crontab -> crontab.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_crontab`, t.id AS target_id 
FROM (
SELECT id, `id_crontab` 
FROM `backup_main` 
WHERE `id_crontab` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `crontab` t ON t.id = x.`id_crontab` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table       | type  | possible_keys | key     | key_len | ref             | rows | Extra                                        |
+----+-------------+-------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t           | index | PRIMARY       | PRIMARY |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>  | ref   | key0          | key0    |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | backup_main | index | id_crontab    | PRIMARY |       4 | NULL            |    1 | Using where                                  |
+----+-------------+-------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
```

## MXP-016 - case `FILESORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_main.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `backup_main` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys   | key             | key_len | ref                     | rows | Extra                                     |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL            | NULL            | NULL    | NULL                    |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY         | PRIMARY         |      11 | r.id_mysql_server,const |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_mysql_server | id_mysql_server |       4 | NULL                    |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
```

## MXP-017 - case `FULLTEXT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_storage_area.id_geolocalisation_city -> geolocalisation_city.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_geolocalisation_city`, t.id AS target_id 
FROM `backup_storage_area` c 
JOIN `geolocalisation_city` t ON t.id = c.`id_geolocalisation_city` 
WHERE c.`id_geolocalisation_city` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+-------------------------+---------+---------+--------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys           | key     | key_len | ref                                  | rows | Extra       |
+----+-------------+-------+--------+-------------------------+---------+---------+--------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_geolocalisation_city | PRIMARY |       4 | NULL                                 |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY                 | PRIMARY |       4 | pmacontrol.c.id_geolocalisation_city |    1 | Using index |
+----+-------------+-------+--------+-------------------------+---------+---------+--------------------------------------+------+-------------+
```

## MXP-018 - case `FULLTEXT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_storage_area.id_geolocalisation_country -> geolocalisation_country.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_geolocalisation_country`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `backup_storage_area` c 
WHERE c.`id_geolocalisation_country` IS NOT NULL 
GROUP BY c.`id_geolocalisation_country` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+---------------+-------------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key                     | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+---------------+-------------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | NULL          | id_geolocalisation_city |       8 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+---------------+-------------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-019 - case `FULLTEXT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_storage_area.id_ssh_key -> ssh_key.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_ssh_key`, t.id AS target_id 
FROM (
SELECT id, `id_ssh_key` 
FROM `backup_storage_area` 
WHERE `id_ssh_key` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `ssh_key` t ON t.id = x.`id_ssh_key` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+---------------------+-------+---------------+-------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table               | type  | possible_keys | key         | key_len | ref             | rows | Extra                                        |
+----+-------------+---------------------+-------+---------------+-------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY       | fingerprint |     166 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0          | key0        |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | backup_storage_area | index | id_ssh_key    | PRIMARY     |       4 | NULL            |    1 | Using where                                  |
+----+-------------+---------------------+-------+---------------+-------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-020 - case `FULLTEXT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `backup_storage_space.id_backup_storage_area -> backup_storage_area.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_backup_storage_area`, ROW_NUMBER() OVER (PARTITION BY c.`id_backup_storage_area` 
ORDER BY c.id DESC) AS rn 
FROM `backup_storage_space` c 
WHERE c.`id_backup_storage_area` IS NOT NULL) 
SELECT r.id, r.`id_backup_storage_area`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `backup_storage_area` t ON t.id = r.`id_backup_storage_area` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+------------------------+------------------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys          | key                    | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+------------------------+------------------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY                | id_ssh_key             |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0                   | key0                   |       5 | pmacontrol.t.id |    2 | Using where                                  |
|  2 | DERIVED     | c          | index | id_backup_storage_area | id_backup_storage_area |       4 | NULL            |    1 | Using where; Using index; Using temporary    |
+----+-------------+------------+-------+------------------------+------------------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-021 - case `GROUPBYNOSORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `benchmark_main.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `benchmark_main` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys   | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_mysql_server | PRIMARY |       4 | NULL                               |   26 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-022 - case `GROUPBYNOSORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `benchmark_main.id_user_main -> user_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_user_main`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `benchmark_main` c 
WHERE c.`id_user_main` IS NOT NULL 
GROUP BY c.`id_user_main` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra                                        |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
|  1 | SIMPLE      | c     | ALL  | NULL          | NULL | NULL    | NULL |   26 | Using where; Using temporary; Using filesort |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
```

## MXP-023 - case `GROUPBYNOSORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `benchmark_run.id_benchmark_main -> benchmark_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_benchmark_main`, t.id AS target_id 
FROM (
SELECT id, `id_benchmark_main` 
FROM `benchmark_run` 
WHERE `id_benchmark_main` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `benchmark_main` t ON t.id = x.`id_benchmark_main` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+---------------+-------+-------------------+-----------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table         | type  | possible_keys     | key             | key_len | ref             | rows | Extra                                        |
+----+-------------+---------------+-------+-------------------+-----------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t             | index | PRIMARY           | id_mysql_server |       4 | NULL            |   26 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>    | ref   | key0              | key0            |       4 | pmacontrol.t.id |   10 |                                              |
|  2 | DERIVED     | benchmark_run | index | id_benchmark_main | PRIMARY         |       4 | NULL            |  168 | Using where                                  |
+----+-------------+---------------+-------+-------------------+-----------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-024 - case `GROUPBYNOSORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `binlog_backup.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `binlog_backup` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys   | key             | key_len | ref                     | rows | Extra                                     |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL            | NULL            | NULL    | NULL                    |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY         | PRIMARY         |      11 | r.id_mysql_server,const |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_mysql_server | id_mysql_server |     198 | NULL                    |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
```

## MXP-025 - case `IMPOSSIBLE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `binlog_history.id_binlog_max -> binlog_max.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_binlog_max`, t.id AS target_id 
FROM `binlog_history` c 
JOIN `binlog_max` t ON t.id = c.`id_binlog_max` 
WHERE c.`id_binlog_max` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+---------------+---------+---------+----------------------------+------+-------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                        | rows | Extra       |
+----+-------------+-------+--------+---------------+---------+---------+----------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_binlog_max | PRIMARY |       4 | NULL                       |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_binlog_max |    1 | Using index |
+----+-------------+-------+--------+---------------+---------+---------+----------------------------+------+-------------+
```

## MXP-026 - case `IMPOSSIBLE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `binlog_max.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `binlog_max` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |       4 | NULL |    9 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-027 - case `IMPOSSIBLE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `cleaner_foreign_key.id_cleaner_main -> cleaner_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_cleaner_main`, t.id AS target_id 
FROM (
SELECT id, `id_cleaner_main` 
FROM `cleaner_foreign_key` 
WHERE `id_cleaner_main` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `cleaner_main` t ON t.id = x.`id_cleaner_main` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+---------------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table               | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                        |
+----+-------------+---------------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY         | id_mysql_server |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0            | key0            |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | cleaner_foreign_key | index | id_cleaner_main | PRIMARY         |       4 | NULL            |    1 | Using where                                  |
+----+-------------+---------------------+-------+-----------------+-----------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-028 - case `IMPOSSIBLE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `cleaner_main.id_backup_storage_area -> backup_storage_area.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_backup_storage_area`, ROW_NUMBER() OVER (PARTITION BY c.`id_backup_storage_area` 
ORDER BY c.id DESC) AS rn 
FROM `cleaner_main` c 
WHERE c.`id_backup_storage_area` IS NOT NULL) 
SELECT r.id, r.`id_backup_storage_area`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `backup_storage_area` t ON t.id = r.`id_backup_storage_area` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+---------------+------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys | key        | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+---------------+------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY       | id_ssh_key |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0          | key0       |       5 | pmacontrol.t.id |    2 | Using where                                  |
|  2 | DERIVED     | c          | ALL   | NULL          | NULL       | NULL    | NULL            |    1 | Using where; Using temporary                 |
+----+-------------+------------+-------+---------------+------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-029 - case `INDEX` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `cleaner_main.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `cleaner_main` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys   | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_mysql_server | PRIMARY |       4 | NULL                               |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-030 - case `INDEX` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `cleaner_main.id_user_main -> user_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_user_main`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `cleaner_main` c 
WHERE c.`id_user_main` IS NOT NULL 
GROUP BY c.`id_user_main` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra                                        |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
|  1 | SIMPLE      | c     | ALL  | NULL          | NULL | NULL    | NULL |    1 | Using where; Using temporary; Using filesort |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
```

## MXP-031 - case `INDEX` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `crontab_history.id_crontab -> crontab.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_crontab`, t.id AS target_id 
FROM (
SELECT id, `id_crontab` 
FROM `crontab_history` 
WHERE `id_crontab` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `crontab` t ON t.id = x.`id_crontab` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-----------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table           | type  | possible_keys | key     | key_len | ref             | rows | Extra                                        |
+----+-------------+-----------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t               | index | PRIMARY       | PRIMARY |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>      | ref   | key0          | key0    |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | crontab_history | index | id_crontab    | PRIMARY |       4 | NULL            |    1 | Using where                                  |
+----+-------------+-----------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
```

## MXP-032 - case `INDEX` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_database_instance.id_docker_image -> docker_image.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_docker_image`, ROW_NUMBER() OVER (PARTITION BY c.`id_docker_image` 
ORDER BY c.id DESC) AS rn 
FROM `docker_database_instance` c 
WHERE c.`id_docker_image` IS NOT NULL) 
SELECT r.id, r.`id_docker_image`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `docker_image` t ON t.id = r.`id_docker_image` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+---------------+----------+---------+-------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys | key      | key_len | ref               | rows | Extra                                     |
+----+-------------+------------+--------+---------------+----------+---------+-------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL          | NULL     | NULL    | NULL              |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY       | PRIMARY  |       4 | r.id_docker_image |    1 | Using where; Using index                  |
|  2 | DERIVED     | c          | index  | fk_image      | fk_image |       4 | NULL              |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+---------------+----------+---------+-------------------+------+-------------------------------------------+
```

## MXP-033 - case `INDEX_MERGE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_database_instance.id_docker_server -> docker_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_docker_server`, t.id AS target_id 
FROM `docker_database_instance` c 
JOIN `docker_server` t ON t.id = c.`id_docker_server` 
WHERE c.`id_docker_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+----------------+----------------+---------+-------------------------------+------+------------------------------------------+
| id | select_type | table | type   | possible_keys  | key            | key_len | ref                           | rows | Extra                                    |
+----+-------------+-------+--------+----------------+----------------+---------+-------------------------------+------+------------------------------------------+
|  1 | SIMPLE      | c     | range  | fk_docker_host | fk_docker_host |       5 | NULL                          |    1 | Using where; Using index; Using filesort |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY        | PRIMARY        |       4 | pmacontrol.c.id_docker_server |    1 | Using where; Using index                 |
+----+-------------+-------+--------+----------------+----------------+---------+-------------------------------+------+------------------------------------------+
```

## MXP-034 - case `INDEX_MERGE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_image.id_docker_software -> docker_software.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_docker_software`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `docker_image` c 
WHERE c.`id_docker_software` IS NOT NULL 
GROUP BY c.`id_docker_software` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra                                        |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
|  1 | SIMPLE      | c     | ALL  | NULL          | NULL | NULL    | NULL |  787 | Using where; Using temporary; Using filesort |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
```

## MXP-035 - case `INDEX_MERGE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_image__docker_server.id_docker_image -> docker_image.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_docker_image`, t.id AS target_id 
FROM (
SELECT id, `id_docker_image` 
FROM `docker_image__docker_server` 
WHERE `id_docker_image` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `docker_image` t ON t.id = x.`id_docker_image` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-----------------------------+--------+---------------------+---------+---------+-------------------+------+----------------+
| id | select_type | table                       | type   | possible_keys       | key     | key_len | ref               | rows | Extra          |
+----+-------------+-----------------------------+--------+---------------------+---------+---------+-------------------+------+----------------+
|  1 | PRIMARY     | <derived2>                  | ALL    | NULL                | NULL    | NULL    | NULL              |    7 | Using filesort |
|  1 | PRIMARY     | t                           | eq_ref | PRIMARY             | PRIMARY |       4 | x.id_docker_image |    1 | Using index    |
|  2 | DERIVED     | docker_image__docker_server | index  | uniq_link,idx_image | PRIMARY |       4 | NULL              |    7 | Using where    |
+----+-------------+-----------------------------+--------+---------------------+---------+---------+-------------------+------+----------------+
```

## MXP-036 - case `INDEX_MERGE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_image__docker_server.id_docker_server -> docker_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_docker_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_docker_server` 
ORDER BY c.id DESC) AS rn 
FROM `docker_image__docker_server` c 
WHERE c.`id_docker_server` IS NOT NULL) 
SELECT r.id, r.`id_docker_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `docker_server` t ON t.id = r.`id_docker_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+---------------+------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys | key        | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+---------------+------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY       | id_ssh_key |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0          | key0       |       5 | pmacontrol.t.id |    2 | Using where                                  |
|  2 | DERIVED     | c          | index | idx_server    | idx_server |       4 | NULL            |    7 | Using where; Using index; Using temporary    |
+----+-------------+------------+-------+---------------+------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-037 - case `INDEX_SUBQUERY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_mysql_instance.id_docker_image -> docker_image.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_docker_image`, t.id AS target_id 
FROM `docker_mysql_instance` c 
JOIN `docker_image` t ON t.id = c.`id_docker_image` 
WHERE c.`id_docker_image` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys   | key     | key_len | ref                          | rows | Extra       |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_docker_image | PRIMARY |       4 | NULL                         |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY |       4 | pmacontrol.c.id_docker_image |    1 | Using index |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------+------+-------------+
```

## MXP-038 - case `INDEX_SUBQUERY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_server.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `docker_server` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | range | id_mysql_server | id_mysql_server |       5 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-039 - case `INDEX_SUBQUERY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `docker_server.id_ssh_key -> ssh_key.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_ssh_key`, t.id AS target_id 
FROM (
SELECT id, `id_ssh_key` 
FROM `docker_server` 
WHERE `id_ssh_key` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `ssh_key` t ON t.id = x.`id_ssh_key` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+---------------+-------+---------------+-------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table         | type  | possible_keys | key         | key_len | ref             | rows | Extra                                        |
+----+-------------+---------------+-------+---------------+-------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t             | index | PRIMARY       | fingerprint |     166 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>    | ref   | key0          | key0        |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | docker_server | index | id_ssh_key    | PRIMARY     |       4 | NULL            |    1 | Using where                                  |
+----+-------------+---------------+-------+---------------+-------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-040 - case `INDEX_SUBQUERY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `dot3_cluster.id_dot3_graph -> dot3_graph.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_dot3_graph`, ROW_NUMBER() OVER (PARTITION BY c.`id_dot3_graph` 
ORDER BY c.id DESC) AS rn 
FROM `dot3_cluster` c 
WHERE c.`id_dot3_graph` IS NOT NULL) 
SELECT r.id, r.`id_dot3_graph`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `dot3_graph` t ON t.id = r.`id_dot3_graph` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+---------------+---------------+---------+-----------------+---------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys | key           | key_len | ref             | rows    | Extra                                        |
+----+-------------+------------+-------+---------------+---------------+---------+-----------------+---------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY       | md5           |     128 | NULL            |   36907 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0          | key0          |       5 | pmacontrol.t.id |      76 | Using where                                  |
|  2 | DERIVED     | c          | index | id_dot3_graph | id_dot3_graph |       4 | NULL            | 2819588 | Using where; Using index; Using temporary    |
+----+-------------+------------+-------+---------------+---------------+---------+-----------------+---------+----------------------------------------------+
```

## MXP-041 - case `NULL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `dot3_cluster.id_dot3_information -> dot3_information.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_dot3_information`, t.id AS target_id 
FROM `dot3_cluster` c 
JOIN `dot3_information` t ON t.id = c.`id_dot3_information` 
WHERE c.`id_dot3_information` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+-----------------------+---------+---------+----------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys         | key     | key_len | ref                              | rows | Extra       |
+----+-------------+-------+--------+-----------------------+---------+---------+----------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_dot3_information_2 | PRIMARY |       4 | NULL                             |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY               | PRIMARY |       4 | pmacontrol.c.id_dot3_information |    1 | Using index |
+----+-------------+-------+--------+-----------------------+---------+---------+----------------------------------+------+-------------+
```

## MXP-042 - case `NULL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `dot3_cluster__mysql_server.id_dot3_cluster -> dot3_cluster.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_dot3_cluster`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `dot3_cluster__mysql_server` c 
WHERE c.`id_dot3_cluster` IS NOT NULL 
GROUP BY c.`id_dot3_cluster` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+----------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows     | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+----------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_dot3_cluster | id_dot3_cluster |       8 | NULL | 11609606 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+----------+-----------------------------------------------------------+
```

## MXP-043 - case `NULL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `dot3_cluster__mysql_server.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `dot3_cluster__mysql_server` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+----------------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
| id | select_type | table                      | type  | possible_keys   | key        | key_len | ref             | rows     | Extra                                                     |
+----+-------------+----------------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                          | index | PRIMARY         | is_deleted |       1 | NULL            |      124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>                 | ref   | key0            | key0       |       4 | pmacontrol.t.id |       10 |                                                           |
|  2 | DERIVED     | dot3_cluster__mysql_server | index | id_mysql_server | PRIMARY    |       4 | NULL            | 11609606 | Using where                                               |
+----+-------------+----------------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
```

## MXP-044 - case `NULL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `dot3_information_extra.id_dot3_information -> dot3_information.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_dot3_information`, ROW_NUMBER() OVER (PARTITION BY c.`id_dot3_information` 
ORDER BY c.id DESC) AS rn 
FROM `dot3_information_extra` c 
WHERE c.`id_dot3_information` IS NOT NULL) 
SELECT r.id, r.`id_dot3_information`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `dot3_information` t ON t.id = r.`id_dot3_information` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+---------------------+---------------------+---------+-----------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys       | key                 | key_len | ref                   | rows | Extra                                     |
+----+-------------+------------+--------+---------------------+---------------------+---------+-----------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL                | NULL                | NULL    | NULL                  |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY             | PRIMARY             |       4 | r.id_dot3_information |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_dot3_information | id_dot3_information |       4 | NULL                  |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+---------------------+---------------------+---------+-----------------------+------+-------------------------------------------+
```

## MXP-045 - case `RANGE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `event_log.id_maxscale_server -> maxscale_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_maxscale_server`, t.id AS target_id 
FROM `event_log` c 
JOIN `maxscale_server` t ON t.id = c.`id_maxscale_server` 
WHERE c.`id_maxscale_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+---------------+---------+---------+---------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                                   | rows | Extra       |
+----+-------------+-------+--------+---------------+---------+---------+---------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |       4 | NULL                                  |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |      11 | pmacontrol.c.id_maxscale_server,const |    1 | Using index |
+----+-------------+-------+--------+---------------+---------+---------+---------------------------------------+------+-------------+
```

## MXP-046 - case `RANGE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `event_log.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `event_log` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra                                        |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
|  1 | SIMPLE      | c     | ALL  | NULL          | NULL | NULL    | NULL |    1 | Using where; Using temporary; Using filesort |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
```

## MXP-047 - case `RANGE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `event_log.id_proxysql_server -> proxysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_proxysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_proxysql_server` 
FROM `event_log` 
WHERE `id_proxysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `proxysql_server` t ON t.id = x.`id_proxysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+---------------+---------+---------+----------------------------+------+-----------------------------+
| id | select_type | table      | type   | possible_keys | key     | key_len | ref                        | rows | Extra                       |
+----+-------------+------------+--------+---------------+---------+---------+----------------------------+------+-----------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL          | NULL    | NULL    | NULL                       |    2 | Using where; Using filesort |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY       | PRIMARY |      11 | x.id_proxysql_server,const |    1 | Using index                 |
|  2 | DERIVED     | event_log  | index  | NULL          | PRIMARY |       4 | NULL                       |    1 | Using where                 |
+----+-------------+------------+--------+---------------+---------+---------+----------------------------+------+-----------------------------+
```

## MXP-048 - case `RANGE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `event_main.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `event_main` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+---------------+---------+---------+-------------------------+------+------------------------------+
| id | select_type | table      | type   | possible_keys | key     | key_len | ref                     | rows | Extra                        |
+----+-------------+------------+--------+---------------+---------+---------+-------------------------+------+------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL          | NULL    | NULL    | NULL                    |    2 | Using where; Using filesort  |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY       | PRIMARY |      11 | r.id_mysql_server,const |    1 | Using index                  |
|  2 | DERIVED     | c          | ALL    | NULL          | NULL    | NULL    | NULL                    |    1 | Using where; Using temporary |
+----+-------------+------------+--------+---------------+---------+---------+-------------------------+------+------------------------------+
```

## MXP-049 - case `REF` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `event_main.id_proxysql_server -> proxysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_proxysql_server`, t.id AS target_id 
FROM `event_main` c 
JOIN `proxysql_server` t ON t.id = c.`id_proxysql_server` 
WHERE c.`id_proxysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+---------------+---------+---------+---------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                                   | rows | Extra       |
+----+-------------+-------+--------+---------------+---------+---------+---------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |       4 | NULL                                  |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |      11 | pmacontrol.c.id_proxysql_server,const |    1 | Using index |
+----+-------------+-------+--------+---------------+---------+---------+---------------------------------------+------+-------------+
```

## MXP-050 - case `REF` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `foreign_key_blacklist.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `foreign_key_blacklist` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+--------------------+--------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys      | key                | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+--------------------+--------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server_36 | id_mysql_server_36 |     778 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+--------------------+--------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-051 - case `REF` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `foreign_key_proposal.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `foreign_key_proposal` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+----------------------+--------+--------------------+---------+---------+-------------------------+------+----------------+
| id | select_type | table                | type   | possible_keys      | key     | key_len | ref                     | rows | Extra          |
+----+-------------+----------------------+--------+--------------------+---------+---------+-------------------------+------+----------------+
|  1 | PRIMARY     | <derived2>           | ALL    | NULL               | NULL    | NULL    | NULL                    |    2 | Using filesort |
|  1 | PRIMARY     | t                    | eq_ref | PRIMARY            | PRIMARY |      11 | x.id_mysql_server,const |    1 | Using index    |
|  2 | DERIVED     | foreign_key_proposal | index  | id_mysql_server_35 | PRIMARY |       4 | NULL                    |    1 | Using where    |
+----+-------------+----------------------+--------+--------------------+---------+---------+-------------------------+------+----------------+
```

## MXP-052 - case `REF` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `foreign_key_real.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `foreign_key_real` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+-----------------------------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table      | type  | possible_keys                     | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+------------+-------+-----------------------------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY                           | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0                              | key0            |       5 | pmacontrol.t.id |   10 | Using where                                               |
|  2 | DERIVED     | c          | index | id_mysql_server,id_mysql_server_2 | id_mysql_server |     520 | NULL            | 1183 | Using where; Using index; Using temporary                 |
+----+-------------+------------+-------+-----------------------------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-053 - case `REF_OR_NULL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `foreign_key_remove_prefix.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `foreign_key_remove_prefix` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys   | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_mysql_server | PRIMARY |       4 | NULL                               |    2 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-054 - case `REF_OR_NULL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `foreign_key_virtual.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `foreign_key_virtual` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------------------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys                                       | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------------------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server_2,id_mysql_server_3,id_mysql_server | id_mysql_server |       4 | NULL | 7694 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------------------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-055 - case `REF_OR_NULL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `geolocalisation_city.id_geolocalisation_country -> geolocalisation_country.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_geolocalisation_country`, t.id AS target_id 
FROM (
SELECT id, `id_geolocalisation_country` 
FROM `geolocalisation_city` 
WHERE `id_geolocalisation_country` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `geolocalisation_country` t ON t.id = x.`id_geolocalisation_country` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+----------------------+--------+----------------------------+---------+---------+------------------------------+--------+----------------+
| id | select_type | table                | type   | possible_keys              | key     | key_len | ref                          | rows   | Extra          |
+----+-------------+----------------------+--------+----------------------------+---------+---------+------------------------------+--------+----------------+
|  1 | PRIMARY     | <derived2>           | ALL    | NULL                       | NULL    | NULL    | NULL                         |   1000 | Using filesort |
|  1 | PRIMARY     | t                    | eq_ref | PRIMARY                    | PRIMARY |       4 | x.id_geolocalisation_country |      1 | Using index    |
|  2 | DERIVED     | geolocalisation_city | index  | id_geolocalisation_country | PRIMARY |       4 | NULL                         | 161703 | Using where    |
+----+-------------+----------------------+--------+----------------------------+---------+---------+------------------------------+--------+----------------+
```

## MXP-056 - case `REF_OR_NULL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `geolocalisation_country.id_geolocalisation_continent -> geolocalisation_continent.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_geolocalisation_continent`, ROW_NUMBER() OVER (PARTITION BY c.`id_geolocalisation_continent` 
ORDER BY c.id DESC) AS rn 
FROM `geolocalisation_country` c 
WHERE c.`id_geolocalisation_continent` IS NOT NULL) 
SELECT r.id, r.`id_geolocalisation_continent`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `geolocalisation_continent` t ON t.id = r.`id_geolocalisation_continent` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+------------------------------+------------------------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys                | key                          | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+------------------------------+------------------------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY                      | PRIMARY                      |       4 | NULL            |    7 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0                         | key0                         |       5 | pmacontrol.t.id |   10 | Using where                                  |
|  2 | DERIVED     | c          | index | id_geolocalisation_continent | id_geolocalisation_continent |       4 | NULL            |  246 | Using where; Using index; Using temporary    |
+----+-------------+------------+-------+------------------------------+------------------------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-057 - case `SYSTEM` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `global_variable.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `global_variable` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys    | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | uniq_id_variable | PRIMARY |      11 | NULL                               |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY          | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-058 - case `SYSTEM` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `haproxy_main_input.id_haproxy_main -> haproxy_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_haproxy_main`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `haproxy_main_input` c 
WHERE c.`id_haproxy_main` IS NOT NULL 
GROUP BY c.`id_haproxy_main` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_haproxy_main | id_haproxy_main |       4 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-059 - case `SYSTEM` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ha_proxy_stats.id_haproxy_main -> haproxy_main.id`
- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_haproxy_main`, t.id AS target_id 
FROM (
SELECT id, `id_haproxy_main` 
FROM `ha_proxy_stats` 
WHERE `id_haproxy_main` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `haproxy_main` t ON t.id = x.`id_haproxy_main` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+----------------+-------+-----------------+---------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table          | type  | possible_keys   | key     | key_len | ref             | rows | Extra                                        |
+----+-------------+----------------+-------+-----------------+---------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t              | index | PRIMARY         | PRIMARY |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>     | ref   | key0            | key0    |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | ha_proxy_stats | index | id_haproxy_main | PRIMARY |       4 | NULL            |    1 | Using where                                  |
+----+-------------+----------------+-------+-----------------+---------+---------+-----------------+------+----------------------------------------------+
```

## MXP-060 - case `SYSTEM` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `index_stats.id_mysql_database -> mysql_database.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_database`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_database` 
ORDER BY c.id DESC) AS rn 
FROM `index_stats` c 
WHERE c.`id_mysql_database` IS NOT NULL) 
SELECT r.id, r.`id_mysql_database`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_database` t ON t.id = r.`id_mysql_database` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+-------------------+-------------------+---------+---------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys     | key               | key_len | ref                       | rows | Extra                                     |
+----+-------------+------------+--------+-------------------+-------------------+---------+---------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL              | NULL              | NULL    | NULL                      | 7698 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY           | PRIMARY           |      11 | r.id_mysql_database,const |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_mysql_database | id_mysql_database |       4 | NULL                      | 7698 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+-------------------+-------------------+---------+---------------------------+------+-------------------------------------------+
```

## MXP-061 - case `UNIQUE_SUBQUERY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `index_stats.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `index_stats` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |       4 | NULL                               |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-062 - case `UNIQUE_SUBQUERY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `index_stats.id_mysql_table -> mysql_table.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_table`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `index_stats` c 
WHERE c.`id_mysql_table` IS NOT NULL 
GROUP BY c.`id_mysql_table` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra                                        |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
|  1 | SIMPLE      | c     | ALL  | NULL          | NULL | NULL    | NULL | 7698 | Using where; Using temporary; Using filesort |
+----+-------------+-------+------+---------------+------+---------+------+------+----------------------------------------------+
```

## MXP-063 - case `UNIQUE_SUBQUERY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `infra_vip_dns.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `infra_vip_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+---------------+--------+-------------------------+-------------------------+---------+-------------------------+------+------------------------------------------+
| id | select_type | table         | type   | possible_keys           | key                     | key_len | ref                     | rows | Extra                                    |
+----+-------------+---------------+--------+-------------------------+-------------------------+---------+-------------------------+------+------------------------------------------+
|  1 | PRIMARY     | <derived2>    | ALL    | NULL                    | NULL                    | NULL    | NULL                    |    2 | Using where; Using filesort              |
|  1 | PRIMARY     | t             | eq_ref | PRIMARY                 | PRIMARY                 |      11 | x.id_mysql_server,const |    1 | Using index                              |
|  2 | DERIVED     | infra_vip_dns | range  | fk_vip_dns_mysql_server | fk_vip_dns_mysql_server |       5 | NULL                    |    1 | Using where; Using index; Using filesort |
+----+-------------+---------------+--------+-------------------------+-------------------------+---------+-------------------------+------+------------------------------------------+
```

## MXP-064 - case `UNIQUE_SUBQUERY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `kb_assessment.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `kb_assessment` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys   | key             | key_len | ref                     | rows | Extra                                     |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL            | NULL            | NULL    | NULL                    |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY         | PRIMARY         |      11 | r.id_mysql_server,const |    1 | Using where; Using index                  |
|  2 | DERIVED     | c          | index  | k_assess_server | k_assess_server |      13 | NULL                    |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
```

## MXP-065 - case `USINGFSORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `kb_item_tag.id_tag -> tag.id`
- Success: `no`
- Execution time (ms): `1`
- Returned rows: `0`
- Fail reason: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.id' in 'SELECT'`

### SQL

```sql
SELECT c.id, c.`id_tag`, t.id AS target_id 
FROM `kb_item_tag` c 
JOIN `tag` t ON t.id = c.`id_tag` 
WHERE c.`id_tag` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
(not available)
```

## MXP-066 - case `USINGFSORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ldap_group.id_group -> group.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_group`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `ldap_group` c 
WHERE c.`id_group` IS NOT NULL 
GROUP BY c.`id_group` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+---------------+----------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key      | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+---------------+----------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_group      | id_group |       4 | NULL |    2 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+---------------+----------+---------+------+------+-----------------------------------------------------------+
```

## MXP-067 - case `USINGFSORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `link__haproxy_main_output__mysql_server.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `link__haproxy_main_output__mysql_server` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-----------------------------------------+--------+-----------------+---------+---------+-------------------------+------+----------------+
| id | select_type | table                                   | type   | possible_keys   | key     | key_len | ref                     | rows | Extra          |
+----+-------------+-----------------------------------------+--------+-----------------+---------+---------+-------------------------+------+----------------+
|  1 | PRIMARY     | <derived2>                              | ALL    | NULL            | NULL    | NULL    | NULL                    |    2 | Using filesort |
|  1 | PRIMARY     | t                                       | eq_ref | PRIMARY         | PRIMARY |      11 | x.id_mysql_server,const |    1 | Using index    |
|  2 | DERIVED     | link__haproxy_main_output__mysql_server | index  | id_mysql_server | PRIMARY |       4 | NULL                    |    1 | Using where    |
+----+-------------+-----------------------------------------+--------+-----------------+---------+---------+-------------------------+------+----------------+
```

## MXP-068 - case `USINGFSORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `link__mysql_server__ssh_key.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `link__mysql_server__ssh_key` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys   | key             | key_len | ref                     | rows | Extra                                     |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL            | NULL            | NULL    | NULL                    |   69 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY         | PRIMARY         |      11 | r.id_mysql_server,const |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_mysql_server | id_mysql_server |       4 | NULL                    |   69 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
```

## MXP-069 - case `USINGIDXGROUPBY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `link__mysql_server__ssh_key.id_ssh_key -> ssh_key.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_ssh_key`, t.id AS target_id 
FROM `link__mysql_server__ssh_key` c 
JOIN `ssh_key` t ON t.id = c.`id_ssh_key` 
WHERE c.`id_ssh_key` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+---------------+-------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key         | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+---------------+-------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY       | fingerprint |     166 | NULL            |    1 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_ssh_key    | id_ssh_key  |       4 | pmacontrol.t.id |   17 | Using index                                               |
+----+-------------+-------+-------+---------------+-------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-070 - case `USINGIDXGROUPBY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `link__mysql_server__tag.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `link__mysql_server__tag` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |       8 | NULL |   71 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-071 - case `USINGIDXGROUPBY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `link__mysql_server__tag.id_tag -> tag.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_tag`, t.id AS target_id 
FROM (
SELECT id, `id_tag` 
FROM `link__mysql_server__tag` 
WHERE `id_tag` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `tag` t ON t.id = x.`id_tag` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------------------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table                   | type  | possible_keys | key     | key_len | ref             | rows | Extra                                        |
+----+-------------+-------------------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t                       | index | PRIMARY       | name    |      52 | NULL            |    5 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>              | ref   | key0          | key0    |       4 | pmacontrol.t.id |    7 |                                              |
|  2 | DERIVED     | link__mysql_server__tag | index | id_tag        | PRIMARY |       4 | NULL            |   71 | Using where                                  |
+----+-------------+-------------------------+-------+---------------+---------+---------+-----------------+------+----------------------------------------------+
```

## MXP-072 - case `USINGIDXGROUPBY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `listener_main.id_ts_file -> ts_file.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_ts_file`, ROW_NUMBER() OVER (PARTITION BY c.`id_ts_file` 
ORDER BY c.id DESC) AS rn 
FROM `listener_main` c 
WHERE c.`id_ts_file` IS NOT NULL) 
SELECT r.id, r.`id_ts_file`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `ts_file` t ON t.id = r.`id_ts_file` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+---------------+------------+---------+--------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys | key        | key_len | ref          | rows | Extra                                     |
+----+-------------+------------+--------+---------------+------------+---------+--------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL          | NULL       | NULL    | NULL         |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY       | PRIMARY    |       4 | r.id_ts_file |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_ts_file    | id_ts_file |       4 | NULL         |    2 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+---------------+------------+---------+--------------+------+-------------------------------------------+
```

## MXP-073 - case `USINGINDEX` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `maxscale_server__mysql_server.id_maxscale_server -> maxscale_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_maxscale_server`, t.id AS target_id 
FROM `maxscale_server__mysql_server` c 
JOIN `maxscale_server` t ON t.id = c.`id_maxscale_server` 
WHERE c.`id_maxscale_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+--------------------+---------+---------+---------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys      | key     | key_len | ref                                   | rows | Extra       |
+----+-------------+-------+--------+--------------------+---------+---------+---------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_maxscale_server | PRIMARY |       4 | NULL                                  |    8 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY            | PRIMARY |      11 | pmacontrol.c.id_maxscale_server,const |    1 | Using index |
+----+-------------+-------+--------+--------------------+---------+---------+---------------------------------------+------+-------------+
```

## MXP-074 - case `USINGINDEX` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `maxscale_server__mysql_server.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `maxscale_server__mysql_server` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+--------------------------------------+--------------------------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys                        | key                                  | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+--------------------------------------+--------------------------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | maxscale_server__mysql_server_ibfk_2 | maxscale_server__mysql_server_ibfk_2 |       4 | NULL |    8 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+--------------------------------------+--------------------------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-075 - case `USINGINDEX` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_database.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `mysql_database` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+----------------+-------+-----------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table          | type  | possible_keys   | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+----------------+-------+-----------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t              | index | PRIMARY         | is_deleted |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>     | ref   | key0            | key0       |       4 | pmacontrol.t.id |   10 |                                                           |
|  2 | DERIVED     | mysql_database | index | id_mysql_server | PRIMARY    |      11 | NULL            | 2995 | Using where                                               |
+----+-------------+----------------+-------+-----------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-076 - case `USINGINDEX` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_database -> mysql_database.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_database`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_database` 
ORDER BY c.id DESC) AS rn 
FROM `mysql_database__mysql_digest` c 
WHERE c.`id_mysql_database` IS NOT NULL) 
SELECT r.id, r.`id_mysql_database`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_database` t ON t.id = r.`id_mysql_database` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+-------------------+-------------------+---------+-----------------+--------+-----------------------------------------------------------+
| id | select_type | table      | type  | possible_keys     | key               | key_len | ref             | rows   | Extra                                                     |
+----+-------------+------------+-------+-------------------+-------------------+---------+-----------------+--------+-----------------------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY           | id_mysql_server   |       4 | NULL            |   2995 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0              | key0              |       5 | pmacontrol.t.id |    157 | Using where                                               |
|  2 | DERIVED     | c          | index | id_mysql_database | id_mysql_database |       4 | NULL            | 471945 | Using where; Using index; Using temporary                 |
+----+-------------+------------+-------+-------------------+-------------------+---------+-----------------+--------+-----------------------------------------------------------+
```

## MXP-077 - case `USINGTEMP` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_digest -> mysql_digest.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_digest`, t.id AS target_id 
FROM `mysql_database__mysql_digest` c 
JOIN `mysql_digest` t ON t.id = c.`id_mysql_digest` 
WHERE c.`id_mysql_digest` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+----------------------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys              | key        | key_len | ref             | rows   | Extra                                                     |
+----+-------------+-------+-------+----------------------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY                    | uk_digest  |     258 | NULL            | 192301 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_digest,idx_digest | idx_digest |       4 | pmacontrol.t.id |      1 | Using index                                               |
+----+-------------+-------+-------+----------------------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
```

## MXP-078 - case `USINGTEMP` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `1`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `mysql_database__mysql_digest` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+--------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows   | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+--------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |       4 | NULL | 471945 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+--------+-----------------------------------------------------------+
```

## MXP-079 - case `USINGTEMP` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_dump.id_backup_database -> backup_database.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_backup_database`, t.id AS target_id 
FROM (
SELECT id, `id_backup_database` 
FROM `mysql_dump` 
WHERE `id_backup_database` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `backup_database` t ON t.id = x.`id_backup_database` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+--------------------+-------------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys      | key               | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+--------------------+-------------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY            | id_mysql_database |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0               | key0              |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | mysql_dump | index | id_backup_database | PRIMARY           |       4 | NULL            |    1 | Using where                                  |
+----+-------------+------------+-------+--------------------+-------------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-080 - case `USINGTEMP` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_dump.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `mysql_dump` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+------------------+------------------+---------+-------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys    | key              | key_len | ref                     | rows | Extra                                     |
+----+-------------+------------+--------+------------------+------------------+---------+-------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL             | NULL             | NULL    | NULL                    |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY          | PRIMARY          |      11 | r.id_mysql_server,const |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_mysql_serveur | id_mysql_serveur |       4 | NULL                    |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+------------------+------------------+---------+-------------------------+------+-------------------------------------------+
```

## MXP-081 - case `dependent_subquery` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_server.id_client -> client.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_client`, t.id AS target_id 
FROM `mysql_server` c 
JOIN `client` t ON t.id = c.`id_client` 
WHERE c.`id_client` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+------------------------+---------+---------+------------------------+------+-------------+
| id | select_type | table | type   | possible_keys          | key     | key_len | ref                    | rows | Extra       |
+----+-------------+-------+--------+------------------------+---------+---------+------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_client,is_monitored | PRIMARY |      11 | NULL                   |  124 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY                | PRIMARY |       4 | pmacontrol.c.id_client |    1 | Using index |
+----+-------------+-------+--------+------------------------+---------+---------+------------------------+------+-------------+
```

## MXP-082 - case `dependent_subquery` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_server.id_environment -> environment.id`
- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_environment`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `mysql_server` c 
WHERE c.`id_environment` IS NOT NULL 
GROUP BY c.`id_environment` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys  | key            | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_environment | id_environment |       4 | NULL |  124 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-083 - case `dependent_subquery` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `mysql_table.id_mysql_database -> mysql_database.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_database`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_database` 
FROM `mysql_table` 
WHERE `id_mysql_database` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_database` t ON t.id = x.`id_mysql_database` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------------+--------+-------------------+---------+---------+---------------------------+------+----------------+
| id | select_type | table       | type   | possible_keys     | key     | key_len | ref                       | rows | Extra          |
+----+-------------+-------------+--------+-------------------+---------+---------+---------------------------+------+----------------+
|  1 | PRIMARY     | <derived2>  | ALL    | NULL              | NULL    | NULL    | NULL                      |    2 | Using filesort |
|  1 | PRIMARY     | t           | eq_ref | PRIMARY           | PRIMARY |      11 | x.id_mysql_database,const |    1 | Using index    |
|  2 | DERIVED     | mysql_table | index  | id_mysql_database | PRIMARY |       4 | NULL                      |    1 | Using where    |
+----+-------------+-------------+--------+-------------------+---------+---------+---------------------------+------+----------------+
```

## MXP-084 - case `dependent_subquery` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `percona_osc_table.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `percona_osc_table` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys   | key             | key_len | ref                     | rows | Extra                                     |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL            | NULL            | NULL    | NULL                    |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY         | PRIMARY         |      11 | r.id_mysql_server,const |    1 | Using index                               |
|  2 | DERIVED     | c          | index  | id_mysql_server | id_mysql_server |     136 | NULL                    |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+-----------------+-----------------+---------+-------------------------+------+-------------------------------------------+
```

## MXP-085 - case `dependent_union` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `plugin_file.id_plugin_main -> plugin_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_plugin_main`, t.id AS target_id 
FROM `plugin_file` c 
JOIN `plugin_main` t ON t.id = c.`id_plugin_main` 
WHERE c.`id_plugin_main` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+----------------+---------+---------+-----------------------------+------+-------------+
| id | select_type | table | type   | possible_keys  | key     | key_len | ref                         | rows | Extra       |
+----+-------------+-------+--------+----------------+---------+---------+-----------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_plugin_main | PRIMARY |       4 | NULL                        |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY        | PRIMARY |       4 | pmacontrol.c.id_plugin_main |    1 | Using index |
+----+-------------+-------+--------+----------------+---------+---------+-----------------------------+------+-------------+
```

## MXP-086 - case `dependent_union` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `plugin_menu.id_plugin_main -> plugin_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_plugin_main`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `plugin_menu` c 
WHERE c.`id_plugin_main` IS NOT NULL 
GROUP BY c.`id_plugin_main` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys  | key            | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_plugin_main | id_plugin_main |       4 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+----------------+----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-087 - case `dependent_union` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `pmacli_drain_item.id_pmacli_drain_process -> pmacli_drain_process.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_pmacli_drain_process`, t.id AS target_id 
FROM (
SELECT id, `id_pmacli_drain_process` 
FROM `pmacli_drain_item` 
WHERE `id_pmacli_drain_process` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `pmacli_drain_process` t ON t.id = x.`id_pmacli_drain_process` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------------------+-------+-------------------------+-----------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table             | type  | possible_keys           | key             | key_len | ref             | rows | Extra                                        |
+----+-------------+-------------------+-------+-------------------------+-----------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t                 | index | PRIMARY                 | id_mysql_server |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>        | ref   | key0                    | key0            |       4 | pmacontrol.t.id |    2 |                                              |
|  2 | DERIVED     | pmacli_drain_item | index | id_pmacli_drain_process | PRIMARY         |       4 | NULL            |    1 | Using where                                  |
+----+-------------+-------------------+-------+-------------------------+-----------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-088 - case `dependent_union` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `pmacli_drain_process.id_cleaner_main -> cleaner_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_cleaner_main`, ROW_NUMBER() OVER (PARTITION BY c.`id_cleaner_main` 
ORDER BY c.id DESC) AS rn 
FROM `pmacli_drain_process` c 
WHERE c.`id_cleaner_main` IS NOT NULL) 
SELECT r.id, r.`id_cleaner_main`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `cleaner_main` t ON t.id = r.`id_cleaner_main` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+---------------+-----------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys | key             | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+---------------+-----------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY       | id_mysql_server |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0          | key0            |       5 | pmacontrol.t.id |    2 | Using where                                  |
|  2 | DERIVED     | c          | ALL   | NULL          | NULL            | NULL    | NULL            |    1 | Using where; Using temporary                 |
+----+-------------+------------+-------+---------------+-----------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-089 - case `derived` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `pmacli_drain_process.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `pmacli_drain_process` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys   | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_mysql_server | PRIMARY |       4 | NULL                               |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-090 - case `derived` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `pmc_event_alert.id_user_main -> user_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_user_main`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `pmc_event_alert` c 
WHERE c.`id_user_main` IS NOT NULL 
GROUP BY c.`id_user_main` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+---------------+-----------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key                   | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+---------------+-----------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | NULL          | idx_scope_user_active |      27 | NULL |    1 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+---------------+-----------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-091 - case `derived` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `proxysql_server.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `proxysql_server` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-----------------+--------+-----------------+-----------------+---------+-------------------------+------+------------------------------------------+
| id | select_type | table           | type   | possible_keys   | key             | key_len | ref                     | rows | Extra                                    |
+----+-------------+-----------------+--------+-----------------+-----------------+---------+-------------------------+------+------------------------------------------+
|  1 | PRIMARY     | <derived2>      | ALL    | NULL            | NULL            | NULL    | NULL                    |   22 | Using where; Using filesort              |
|  1 | PRIMARY     | t               | eq_ref | PRIMARY         | PRIMARY         |      11 | x.id_mysql_server,const |    1 | Using index                              |
|  2 | DERIVED     | proxysql_server | range  | id_mysql_server | id_mysql_server |       5 | NULL                    |   22 | Using where; Using index; Using filesort |
+----+-------------+-----------------+--------+-----------------+-----------------+---------+-------------------------+------+------------------------------------------+
```

## MXP-092 - case `derived` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `recover_page.id_recover_table -> recover_table.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_recover_table`, ROW_NUMBER() OVER (PARTITION BY c.`id_recover_table` 
ORDER BY c.id DESC) AS rn 
FROM `recover_page` c 
WHERE c.`id_recover_table` IS NOT NULL) 
SELECT r.id, r.`id_recover_table`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `recover_table` t ON t.id = r.`id_recover_table` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+-------------------------------------+------------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys                       | key              | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+-------------------------------------+------------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY                             | table_schema     |     516 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0                                | key0             |       5 | pmacontrol.t.id |    2 | Using where                                  |
|  2 | DERIVED     | c          | index | id_recover_table_2,id_recover_table | id_recover_table |       4 | NULL            |    1 | Using where; Using index; Using temporary    |
+----+-------------+------------+-------+-------------------------------------+------------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-093 - case `keylen_1` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ssh_tunnel.id_maxscale_server -> maxscale_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_maxscale_server`, t.id AS target_id 
FROM `ssh_tunnel` c 
JOIN `maxscale_server` t ON t.id = c.`id_maxscale_server` 
WHERE c.`id_maxscale_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+--------------------+--------------------+---------+---------------------------------------+------+------------------------------------------+
| id | select_type | table | type   | possible_keys      | key                | key_len | ref                                   | rows | Extra                                    |
+----+-------------+-------+--------+--------------------+--------------------+---------+---------------------------------------+------+------------------------------------------+
|  1 | SIMPLE      | c     | range  | id_maxscale_server | id_maxscale_server |       5 | NULL                                  |    1 | Using where; Using index; Using filesort |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY            | PRIMARY            |      11 | pmacontrol.c.id_maxscale_server,const |    1 | Using index                              |
+----+-------------+-------+--------+--------------------+--------------------+---------+---------------------------------------+------+------------------------------------------+
```

## MXP-094 - case `keylen_1` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ssh_tunnel.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `ssh_tunnel` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+---------------+------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key        | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+---------------+------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | range | idx_server    | idx_server |       5 | NULL |   10 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+---------------+------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-095 - case `keylen_1` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ssh_tunnel.id_proxysql_server -> proxysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_proxysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_proxysql_server` 
FROM `ssh_tunnel` 
WHERE `id_proxysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `proxysql_server` t ON t.id = x.`id_proxysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+------------------------------------------+
| id | select_type | table      | type   | possible_keys      | key                | key_len | ref                        | rows | Extra                                    |
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL               | NULL               | NULL    | NULL                       |    2 | Using where; Using filesort              |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY            | PRIMARY            |      11 | x.id_proxysql_server,const |    1 | Using index                              |
|  2 | DERIVED     | ssh_tunnel | range  | id_proxysql_server | id_proxysql_server |       5 | NULL                       |    1 | Using where; Using index; Using filesort |
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+------------------------------------------+
```

## MXP-096 - case `keylen_1` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `statistics.id_user_main -> user_main.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_user_main`, ROW_NUMBER() OVER (PARTITION BY c.`id_user_main` 
ORDER BY c.id DESC) AS rn 
FROM `statistics` c 
WHERE c.`id_user_main` IS NOT NULL) 
SELECT r.id, r.`id_user_main`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `user_main` t ON t.id = r.`id_user_main` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+------------------+----------------------------+---------+-----------------+------+----------------------------------------------+
| id | select_type | table      | type  | possible_keys    | key                        | key_len | ref             | rows | Extra                                        |
+----+-------------+------------+-------+------------------+----------------------------+---------+-----------------+------+----------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY          | id_geolocalisation_country |       4 | NULL            |    1 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0             | key0                       |       5 | pmacontrol.t.id |   10 | Using where                                  |
|  2 | DERIVED     | c          | range | idx_id_user_main | idx_id_user_main           |       5 | NULL            |  107 | Using where; Using index; Using temporary    |
+----+-------------+------------+-------+------------------+----------------------------+---------+-----------------+------+----------------------------------------------+
```

## MXP-097 - case `keylen_2` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `table_columns.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `table_columns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | TABLE_SCHEMA  | PRIMARY |       4 | NULL                               |    1 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-098 - case `keylen_2` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_date_by_server.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `ts_date_by_server` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows      | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |      13 | NULL | 253948212 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
```

## MXP-099 - case `keylen_2` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_date_by_server.id_ts_file -> ts_file.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_ts_file`, t.id AS target_id 
FROM (
SELECT id, `id_ts_file` 
FROM `ts_date_by_server` 
WHERE `id_ts_file` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `ts_file` t ON t.id = x.`id_ts_file` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------------------+-------+---------------+-----------+---------+-----------------+-----------+----------------------------------------------+
| id | select_type | table             | type  | possible_keys | key       | key_len | ref             | rows      | Extra                                        |
+----+-------------+-------------------+-------+---------------+-----------+---------+-----------------+-----------+----------------------------------------------+
|  1 | PRIMARY     | t                 | index | PRIMARY       | file_name |      52 | NULL            |        33 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>        | ref   | key0          | key0      |       4 | pmacontrol.t.id |        10 |                                              |
|  2 | DERIVED     | ts_date_by_server | index | NULL          | PRIMARY   |      13 | NULL            | 253948212 | Using where                                  |
+----+-------------+-------------------+-------+---------------+-----------+---------+-----------------+-----------+----------------------------------------------+
```

## MXP-100 - case `keylen_2` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_max_date.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `ts_max_date` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+------------+-------+-----------------------------------------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table      | type  | possible_keys                                       | key               | key_len | ref             | rows | Extra                                                     |
+----+-------------+------------+-------+-----------------------------------------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY                                             | is_deleted        |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0                                                | key0              |       5 | pmacontrol.t.id |   43 | Using where                                               |
|  2 | DERIVED     | c          | index | id_mysql_server_2,id_mysql_server,id_mysql_server_4 | id_mysql_server_2 |       8 | NULL            | 5455 | Using where; Using index; Using temporary                 |
+----+-------------+------------+-------+-----------------------------------------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-101 - case `subquery` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_max_date.id_ts_file -> ts_file.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Fail reason: `none`

### SQL

```sql
SELECT c.id, c.`id_ts_file`, t.id AS target_id 
FROM `ts_max_date` c 
JOIN `ts_file` t ON t.id = c.`id_ts_file` 
WHERE c.`id_ts_file` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+-------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY       | file_name  |      52 | NULL            |   33 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_ts_file    | id_ts_file |       4 | pmacontrol.t.id |   61 | Using index                                               |
+----+-------------+-------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-102 - case `subquery` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_mysql_digest_stat.id_mysql_database__mysql_digest -> mysql_database__mysql_digest.id`
- Success: `no`
- Execution time (ms): `1`
- Returned rows: `0`
- Fail reason: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.id' in 'SELECT'`

### SQL

```sql
SELECT c.`id_mysql_database__mysql_digest`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `ts_mysql_digest_stat` c 
WHERE c.`id_mysql_database__mysql_digest` IS NOT NULL 
GROUP BY c.`id_mysql_database__mysql_digest` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
(not available)
```

## MXP-103 - case `subquery` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_mysql_query.id_mysql_server -> mysql_server.id`
- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `ts_mysql_query` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
+----+-------------+----------------+-------+-----------------+------------+---------+-----------------+-------+-----------------------------------------------------------+
| id | select_type | table          | type  | possible_keys   | key        | key_len | ref             | rows  | Extra                                                     |
+----+-------------+----------------+-------+-----------------+------------+---------+-----------------+-------+-----------------------------------------------------------+
|  1 | PRIMARY     | t              | index | PRIMARY         | is_deleted |       1 | NULL            |   124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>     | ref   | key0            | key0       |       4 | pmacontrol.t.id |    10 |                                                           |
|  2 | DERIVED     | ts_mysql_query | index | id_mysql_server | PRIMARY    |       4 | NULL            | 71962 | Using where                                               |
+----+-------------+----------------+-------+-----------------+------------+---------+-----------------+-------+-----------------------------------------------------------+
```

## MXP-104 - case `subquery` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_value_calculated_double.id_mysql_server -> mysql_server.id`
- Success: `no`
- Execution time (ms): `1`
- Returned rows: `0`
- Fail reason: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.id' in 'SELECT'`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `ts_value_calculated_double` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
(not available)
```

## MXP-105 - case `union` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_value_calculated_double.id_ts_variable -> ts_variable.id`
- Success: `no`
- Execution time (ms): `1`
- Returned rows: `0`
- Fail reason: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.id' in 'SELECT'`

### SQL

```sql
SELECT c.id, c.`id_ts_variable`, t.id AS target_id 
FROM `ts_value_calculated_double` c 
JOIN `ts_variable` t ON t.id = c.`id_ts_variable` 
WHERE c.`id_ts_variable` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

### EXPLAIN Table (human-readable)

```text
(not available)
```

## MXP-106 - case `union` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_value_calculated_int.id_mysql_server -> mysql_server.id`
- Success: `no`
- Execution time (ms): `2`
- Returned rows: `0`
- Fail reason: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.id' in 'SELECT'`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `ts_value_calculated_int` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
(not available)
```

## MXP-107 - case `union` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_value_calculated_int.id_ts_variable -> ts_variable.id`
- Success: `no`
- Execution time (ms): `1`
- Returned rows: `0`
- Fail reason: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'id' in 'SELECT'`

### SQL

```sql
SELECT x.id, x.`id_ts_variable`, t.id AS target_id 
FROM (
SELECT id, `id_ts_variable` 
FROM `ts_value_calculated_int` 
WHERE `id_ts_variable` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `ts_variable` t ON t.id = x.`id_ts_variable` 
ORDER BY x.id DESC 
LIMIT 200
```

### EXPLAIN Table (human-readable)

```text
(not available)
```

## MXP-108 - case `union` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain_table`
- Relation guessed: `ts_value_calculated_json.id_mysql_server -> mysql_server.id`
- Success: `no`
- Execution time (ms): `1`
- Returned rows: `0`
- Fail reason: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.id' in 'SELECT'`

### SQL

```sql
WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `ts_value_calculated_json` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

### EXPLAIN Table (human-readable)

```text
(not available)
```

