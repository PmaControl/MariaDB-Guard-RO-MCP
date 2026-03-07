# MYXPLAIN Query Catalog (MCP / db_select + db_explain_table)

- Source cases: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`
- Pattern rule used: `YYYYYY.id_XXXXXX -> XXXXXX.id`
- Endpoint source: `127.0.0.1:13306`
- Minimum table size enforced: `TABLE_ROWS >= 100` (child and target)
- Total MYXPLAIN cases: `27`
- Variants per case: `4`
- Total generated queries: `108`
- Query pass/fail: `69/39`
- Explain pass/fail: `104/4`
- Explain signature matches expected case: `43/108`
- Skipped entries: `4`

## Replay Command

```bash
php scripts/generate_myxplain_query_catalog.php
```

## MXP-001 - case `ALL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1165386`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `722`
- Returned rows: `212`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `ALL`
- Signature match: `no`
- Fail reason: `none`

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
- Relation guessed: `dot3_cluster.id_dot3_graph -> dot3_graph.id`
- Child table rows (estimate): `2832952`
- Target table rows (estimate): `36951`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `441`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_dot3_graph`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `dot3_cluster` c 
WHERE c.`id_dot3_graph` IS NOT NULL 
GROUP BY c.`id_dot3_graph` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `1`
- Expected signature: `ALL`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+---------------+---------------+---------+------+---------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key           | key_len | ref  | rows    | Extra                                                     |
+----+-------------+-------+-------+---------------+---------------+---------+------+---------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_dot3_graph | id_dot3_graph |       4 | NULL | 2832961 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+---------------+---------------+---------+------+---------+-----------------------------------------------------------+
```

## MXP-003 - case `ALL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster.id_dot3_information -> dot3_information.id`
- Child table rows (estimate): `2832952`
- Target table rows (estimate): `186196`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_dot3_information`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_dot3_information` 
FROM `dot3_cluster` 
WHERE `id_dot3_information` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `dot3_information` t ON t.id = x.`id_dot3_information` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `ALL`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
| id | select_type | table        | type   | possible_keys         | key     | key_len | ref                   | rows    | Extra          |
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
|  1 | PRIMARY     | <derived2>   | ALL    | NULL                  | NULL    | NULL    | NULL                  |    1000 | Using filesort |
|  1 | PRIMARY     | t            | eq_ref | PRIMARY               | PRIMARY |       4 | x.id_dot3_information |       1 | Using index    |
|  2 | DERIVED     | dot3_cluster | index  | id_dot3_information_2 | PRIMARY |       4 | NULL                  | 2832961 | Using where    |
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
```

## MXP-004 - case `ALL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster__mysql_server.id_dot3_cluster -> dot3_cluster.id`
- Child table rows (estimate): `11665075`
- Target table rows (estimate): `2832954`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30008`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_dot3_cluster`, ROW_NUMBER() OVER (PARTITION BY `id_dot3_cluster` 
ORDER BY `id` DESC) AS rn 
FROM `dot3_cluster__mysql_server` 
WHERE `id_dot3_cluster` IS NOT NULL) 
SELECT r.row_id, r.`id_dot3_cluster`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `dot3_cluster` t ON t.id = r.`id_dot3_cluster` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `ALL`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-----------------+----------------------------+-------+-----------------+-----------------+---------+-----------------+---------+----------------------------------------------+
| id | select_type     | table                      | type  | possible_keys   | key             | key_len | ref             | rows    | Extra                                        |
+----+-----------------+----------------------------+-------+-----------------+-----------------+---------+-----------------+---------+----------------------------------------------+
|  1 | PRIMARY         | t                          | index | PRIMARY         | id_dot3_graph   |       4 | NULL            | 2833158 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY         | <derived2>                 | ref   | key0            | key0            |       5 | pmacontrol.t.id |       2 | Using where                                  |
|  2 | LATERAL DERIVED | dot3_cluster__mysql_server | ref   | id_dot3_cluster | id_dot3_cluster |       4 | pmacontrol.t.id |       1 | Using where; Using index; Using temporary    |
+----+-----------------+----------------------------+-------+-----------------+-----------------+---------+-----------------+---------+----------------------------------------------+
```

## MXP-005 - case `CONST` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster__mysql_server.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `11665075`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `2820`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `dot3_cluster__mysql_server` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `CONST`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |  457 | Using index                                               |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-006 - case `CONST` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `foreign_key_real.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1183`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `13`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `foreign_key_real` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `CONST`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys                     | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server,id_mysql_server_2 | id_mysql_server |     520 | NULL | 1183 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-007 - case `CONST` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `foreign_key_virtual.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `7694`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `6`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `foreign_key_virtual` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `CONST`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table               | type  | possible_keys                                       | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY                                             | is_deleted |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0                                                | key0       |       4 | pmacontrol.t.id |   10 |                                                           |
|  2 | DERIVED     | foreign_key_virtual | index | id_mysql_server_2,id_mysql_server_3,id_mysql_server | PRIMARY    |      11 | NULL            | 7694 | Using where                                               |
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-008 - case `CONST` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `geolocalisation_city.id_geolocalisation_country -> geolocalisation_country.id`
- Child table rows (estimate): `161703`
- Target table rows (estimate): `246`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `101`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_geolocalisation_country`, ROW_NUMBER() OVER (PARTITION BY `id_geolocalisation_country` 
ORDER BY `id` DESC) AS rn 
FROM `geolocalisation_city` 
WHERE `id_geolocalisation_country` IS NOT NULL) 
SELECT r.row_id, r.`id_geolocalisation_country`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `geolocalisation_country` t ON t.id = r.`id_geolocalisation_country` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `CONST`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
| id | select_type | table                | type  | possible_keys              | key                          | key_len | ref             | rows   | Extra                                        |
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
|  1 | PRIMARY     | t                    | index | PRIMARY                    | id_geolocalisation_continent |       4 | NULL            |    246 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>           | ref   | key0                       | key0                         |       5 | pmacontrol.t.id |    657 | Using where                                  |
|  2 | DERIVED     | geolocalisation_city | index | id_geolocalisation_country | id_geolocalisation_country   |       4 | NULL            | 161703 | Using where; Using index; Using temporary    |
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
```

## MXP-009 - case `EQ_REF` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `global_variable.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `651462`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `global_variable` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `EQ_REF`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys    | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | uniq_id_variable | PRIMARY |      11 | NULL                               |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY          | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-010 - case `EQ_REF` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `index_stats.id_mysql_database -> mysql_database.id`
- Child table rows (estimate): `7698`
- Target table rows (estimate): `2995`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `184`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_database`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `index_stats` c 
WHERE c.`id_mysql_database` IS NOT NULL 
GROUP BY c.`id_mysql_database` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `EQ_REF`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys     | key               | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_database | id_mysql_database |       4 | NULL | 7698 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-011 - case `EQ_REF` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `index_stats.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `7698`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `index_stats` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `EQ_REF`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table       | type  | possible_keys | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t           | index | PRIMARY       | is_deleted |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>  | ref   | key0          | key0       |       4 | pmacontrol.t.id |   10 |                                                           |
|  2 | DERIVED     | index_stats | index | NULL          | PRIMARY    |       4 | NULL            | 7698 | Using where                                               |
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-012 - case `EQ_REF` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `2995`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `7`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id` DESC) AS rn 
FROM `mysql_database` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `EQ_REF`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table          | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t              | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>     | ref   | key0            | key0            |       5 | pmacontrol.t.id |   24 | Using where                                               |
|  2 | DERIVED     | mysql_database | index | id_mysql_server | id_mysql_server |       4 | NULL            | 2995 | Using where; Using index; Using temporary                 |
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-013 - case `FILESORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_database -> mysql_database.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `2995`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `110`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_database`, t.id AS target_id 
FROM `mysql_database__mysql_digest` c 
JOIN `mysql_database` t ON t.id = c.`id_mysql_database` 
WHERE c.`id_mysql_database` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `FILESORT`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys     | key               | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY           | id_mysql_server   |       4 | NULL            | 2995 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_database | id_mysql_database |       4 | pmacontrol.t.id |  144 | Using index                                               |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-014 - case `FILESORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_digest -> mysql_digest.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `192325`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `115`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_digest`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `mysql_database__mysql_digest` c 
WHERE c.`id_mysql_digest` IS NOT NULL 
GROUP BY c.`id_mysql_digest` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `FILESORT`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+----------------------------+------------+---------+------+--------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys              | key        | key_len | ref  | rows   | Extra                                                     |
+----+-------------+-------+-------+----------------------------+------------+---------+------+--------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_digest,idx_digest | idx_digest |       4 | NULL | 471977 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+----------------------------+------------+---------+------+--------+-----------------------------------------------------------+
```

## MXP-015 - case `FILESORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `mysql_database__mysql_digest` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `FILESORT`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+------------------------------+-------+-----------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
| id | select_type | table                        | type  | possible_keys   | key        | key_len | ref             | rows   | Extra                                                     |
+----+-------------+------------------------------+-------+-----------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                            | index | PRIMARY         | is_deleted |       1 | NULL            |    124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>                   | ref   | key0            | key0       |       4 | pmacontrol.t.id |     10 |                                                           |
|  2 | DERIVED     | mysql_database__mysql_digest | index | id_mysql_server | PRIMARY    |       4 | NULL            | 471977 | Using where                                               |
+----+-------------+------------------------------+-------+-----------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
```

## MXP-016 - case `FILESORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ssh_tunnel.id_maxscale_server -> maxscale_server.id`
- Child table rows (estimate): `522`
- Target table rows (estimate): `37530`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_maxscale_server`, ROW_NUMBER() OVER (PARTITION BY `id_maxscale_server` 
ORDER BY `id` DESC) AS rn 
FROM `ssh_tunnel` 
WHERE `id_maxscale_server` IS NOT NULL) 
SELECT r.row_id, r.`id_maxscale_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `maxscale_server` t ON t.id = r.`id_maxscale_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `FILESORT`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys      | key                | key_len | ref                        | rows | Extra                                     |
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL               | NULL               | NULL    | NULL                       |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY            | PRIMARY            |      11 | r.id_maxscale_server,const |    1 | Using index                               |
|  2 | DERIVED     | ssh_tunnel | range  | id_maxscale_server | id_maxscale_server |       5 | NULL                       |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+-------------------------------------------+
```

## MXP-017 - case `FULLTEXT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `. -> .id`
- Child table rows (estimate): `0`
- Target table rows (estimate): `0`
- Skipped: `yes`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Fail reason: `skipped: No FULLTEXT index on this server`

### SQL

```sql
(not available)
```

### EXPLAIN (`db_explain_table`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Expected signature: `FULLTEXT`
- Signature match: `no`
- Fail reason: `skipped: No FULLTEXT index on this server`

```text
(not available)
```

## MXP-018 - case `FULLTEXT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `. -> .id`
- Child table rows (estimate): `0`
- Target table rows (estimate): `0`
- Skipped: `yes`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Fail reason: `skipped: No FULLTEXT index on this server`

### SQL

```sql
(not available)
```

### EXPLAIN (`db_explain_table`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Expected signature: `FULLTEXT`
- Signature match: `no`
- Fail reason: `skipped: No FULLTEXT index on this server`

```text
(not available)
```

## MXP-019 - case `FULLTEXT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `. -> .id`
- Child table rows (estimate): `0`
- Target table rows (estimate): `0`
- Skipped: `yes`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Fail reason: `skipped: No FULLTEXT index on this server`

### SQL

```sql
(not available)
```

### EXPLAIN (`db_explain_table`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Expected signature: `FULLTEXT`
- Signature match: `no`
- Fail reason: `skipped: No FULLTEXT index on this server`

```text
(not available)
```

## MXP-020 - case `FULLTEXT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `. -> .id`
- Child table rows (estimate): `0`
- Target table rows (estimate): `0`
- Skipped: `yes`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Fail reason: `skipped: No FULLTEXT index on this server`

### SQL

```sql
(not available)
```

### EXPLAIN (`db_explain_table`)

- Success: `no`
- Execution time (ms): `0`
- Returned rows: `0`
- Expected signature: `FULLTEXT`
- Signature match: `no`
- Fail reason: `skipped: No FULLTEXT index on this server`

```text
(not available)
```

## MXP-021 - case `GROUPBYNOSORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_mysql_query.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `71962`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `ts_mysql_query` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `GROUPBYNOSORT`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys   | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_mysql_server | PRIMARY |       4 | NULL                               |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-022 - case `GROUPBYNOSORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_int.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `740562020`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30006`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id_mysql_server`) AS min_id, MAX(c.`id_mysql_server`) AS max_id 
FROM `ts_value_digest_int` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `1`
- Expected signature: `GROUPBYNOSORT`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows      | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |      17 | NULL | 740562020 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
```

## MXP-023 - case `GROUPBYNOSORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_int.id_ts_mysql_query -> ts_mysql_query.id`
- Child table rows (estimate): `740562020`
- Target table rows (estimate): `71962`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30006`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT x.row_id, x.`id_ts_mysql_query`, t.id AS target_id 
FROM (
SELECT `id_ts_mysql_query` AS row_id, `id_ts_mysql_query` 
FROM `ts_value_digest_int` 
WHERE `id_ts_mysql_query` IS NOT NULL 
ORDER BY `id_ts_mysql_query` DESC 
LIMIT 1000) x 
JOIN `ts_mysql_query` t ON t.id = x.`id_ts_mysql_query` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `GROUPBYNOSORT`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+---------------------+--------+---------------+---------+---------+---------------------+-----------+------------------------------------------+
| id | select_type | table               | type   | possible_keys | key     | key_len | ref                 | rows      | Extra                                    |
+----+-------------+---------------------+--------+---------------+---------+---------+---------------------+-----------+------------------------------------------+
|  1 | PRIMARY     | <derived2>          | ALL    | NULL          | NULL    | NULL    | NULL                |      1000 | Using filesort                           |
|  1 | PRIMARY     | t                   | eq_ref | PRIMARY       | PRIMARY |       4 | x.id_ts_mysql_query |         1 | Using index                              |
|  2 | DERIVED     | ts_value_digest_int | index  | NULL          | PRIMARY |      17 | NULL                | 740562020 | Using where; Using index; Using filesort |
+----+-------------+---------------------+--------+---------------+---------+---------+---------------------+-----------+------------------------------------------+
```

## MXP-024 - case `GROUPBYNOSORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_int.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `740562020`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30104`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id_ts_variable` AS row_id, `id_ts_variable`, ROW_NUMBER() OVER (PARTITION BY `id_ts_variable` 
ORDER BY `id_ts_variable` DESC) AS rn 
FROM `ts_value_digest_int` 
WHERE `id_ts_variable` IS NOT NULL) 
SELECT r.row_id, r.`id_ts_variable`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `ts_variable` t ON t.id = r.`id_ts_variable` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `GROUPBYNOSORT`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+---------------------+-------+---------------+--------------------+---------+-----------------+-----------+----------------------------------------------+
| id | select_type | table               | type  | possible_keys | key                | key_len | ref             | rows      | Extra                                        |
+----+-------------+---------------------+-------+---------------+--------------------+---------+-----------------+-----------+----------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY       | ts_variable_ibfk_1 |       4 | NULL            |      2798 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0          | key0               |       5 | pmacontrol.t.id |    264675 | Using where                                  |
|  2 | DERIVED     | ts_value_digest_int | index | NULL          | PRIMARY            |      17 | NULL            | 740562020 | Using where; Using index; Using temporary    |
+----+-------------+---------------------+-------+---------------+--------------------+---------+-----------------+-----------+----------------------------------------------+
```

## MXP-025 - case `IMPOSSIBLE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_text.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `86664`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server` AS row_id 
FROM `ts_value_digest_text` c 
WHERE 1=0 
LIMIT 10
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `IMPOSSIBLE`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra            |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
|  1 | SIMPLE      | NULL  | NULL | NULL          | NULL | NULL    | NULL | NULL | Impossible WHERE |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
```

## MXP-026 - case `IMPOSSIBLE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_text.id_ts_mysql_query -> ts_mysql_query.id`
- Child table rows (estimate): `86664`
- Target table rows (estimate): `71962`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_ts_mysql_query` AS row_id 
FROM `ts_value_digest_text` c 
WHERE 1=0 
LIMIT 10
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `IMPOSSIBLE`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra            |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
|  1 | SIMPLE      | NULL  | NULL | NULL          | NULL | NULL    | NULL | NULL | Impossible WHERE |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
```

## MXP-027 - case `IMPOSSIBLE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_text.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `86664`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id 
FROM `ts_value_digest_text` c 
WHERE 1=0 
LIMIT 10
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `IMPOSSIBLE`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra            |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
|  1 | SIMPLE      | NULL  | NULL | NULL          | NULL | NULL    | NULL | NULL | Impossible WHERE |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
```

## MXP-028 - case `IMPOSSIBLE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_double.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `602271541`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server` AS row_id 
FROM `ts_value_general_double` c 
WHERE 1=0 
LIMIT 10
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `IMPOSSIBLE`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
| id | select_type | table | type | possible_keys | key  | key_len | ref  | rows | Extra            |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
|  1 | SIMPLE      | NULL  | NULL | NULL          | NULL | NULL    | NULL | NULL | Impossible WHERE |
+----+-------------+-------+------+---------------+------+---------+------+------+------------------+
```

## MXP-029 - case `INDEX` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_double.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `602271541`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30085`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable`, t.id AS target_id 
FROM `ts_value_general_double` c 
JOIN `ts_variable` t ON t.id = c.`id_ts_variable` 
WHERE c.`id_ts_variable` IS NOT NULL 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Expected signature: `INDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows      | Extra                                    |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |      13 | NULL                        | 602295231 | Using where; Using index; Using filesort |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |         1 | Using index                              |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
```

## MXP-030 - case `INDEX` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_int.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `31544877635`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30005`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id_mysql_server`) AS min_id, MAX(c.`id_mysql_server`) AS max_id 
FROM `ts_value_general_int` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `INDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-------------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows        | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-------------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |      13 | NULL | 31546157691 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-------------+-----------------------------------------------------------+
```

## MXP-031 - case `INDEX` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_int.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `31544877635`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30006`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT x.row_id, x.`id_ts_variable`, t.id AS target_id 
FROM (
SELECT `id_ts_variable` AS row_id, `id_ts_variable` 
FROM `ts_value_general_int` 
WHERE `id_ts_variable` IS NOT NULL 
ORDER BY `id_ts_variable` DESC 
LIMIT 1000) x 
JOIN `ts_variable` t ON t.id = x.`id_ts_variable` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `INDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------------+------------------------------------------+
| id | select_type | table                | type   | possible_keys | key     | key_len | ref              | rows        | Extra                                    |
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------------+------------------------------------------+
|  1 | PRIMARY     | <derived2>           | ALL    | NULL          | NULL    | NULL    | NULL             |        1000 | Using filesort                           |
|  1 | PRIMARY     | t                    | eq_ref | PRIMARY       | PRIMARY |       4 | x.id_ts_variable |           1 | Using index                              |
|  2 | DERIVED     | ts_value_general_int | index  | NULL          | PRIMARY |      13 | NULL             | 31546472376 | Using where; Using index; Using filesort |
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------------+------------------------------------------+
```

## MXP-032 - case `INDEX` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_json.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `330597278`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30026`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id_mysql_server` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id_mysql_server` DESC) AS rn 
FROM `ts_value_general_json` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `INDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
| id | select_type | table                 | type  | possible_keys   | key        | key_len | ref             | rows      | Extra                                                     |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                     | index | PRIMARY         | is_deleted |       1 | NULL            |       124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>            | ref   | key0            | key0       |       5 | pmacontrol.t.id |   2666299 | Using where                                               |
|  2 | DERIVED     | ts_value_general_json | index | id_mysql_server | PRIMARY    |      13 | NULL            | 330621171 | Using where; Using index; Using temporary                 |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
```

## MXP-033 - case `INDEX_MERGE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_json.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `330597278`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30011`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable`, t.id AS target_id 
FROM `ts_value_general_json` c 
JOIN `ts_variable` t ON t.id = c.`id_ts_variable` 
WHERE c.`id_ts_variable` IS NOT NULL 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `INDEX_MERGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows      | Extra                                    |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |      13 | NULL                        | 330619996 | Using where; Using index; Using filesort |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |         1 | Using index                              |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
```

## MXP-034 - case `INDEX_MERGE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_text.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `2125535228`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30043`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id_mysql_server`) AS min_id, MAX(c.`id_mysql_server`) AS max_id 
FROM `ts_value_general_text` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `INDEX_MERGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows       | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |      13 | NULL | 2125674342 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------------+-----------------------------------------------------------+
```

## MXP-035 - case `INDEX_MERGE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_text.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `2125535228`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30016`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT x.row_id, x.`id_ts_variable`, t.id AS target_id 
FROM (
SELECT `id_ts_variable` AS row_id, `id_ts_variable` 
FROM `ts_value_general_text` 
WHERE `id_ts_variable` IS NOT NULL 
ORDER BY `id_ts_variable` DESC 
LIMIT 1000) x 
JOIN `ts_variable` t ON t.id = x.`id_ts_variable` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `INDEX_MERGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-----------------------+--------+---------------+---------+---------+------------------+------------+------------------------------------------+
| id | select_type | table                 | type   | possible_keys | key     | key_len | ref              | rows       | Extra                                    |
+----+-------------+-----------------------+--------+---------------+---------+---------+------------------+------------+------------------------------------------+
|  1 | PRIMARY     | <derived2>            | ALL    | NULL          | NULL    | NULL    | NULL             |       1000 | Using filesort                           |
|  1 | PRIMARY     | t                     | eq_ref | PRIMARY       | PRIMARY |       4 | x.id_ts_variable |          1 | Using index                              |
|  2 | DERIVED     | ts_value_general_text | index  | NULL          | PRIMARY |      13 | NULL             | 2125696138 | Using where; Using index; Using filesort |
+----+-------------+-----------------------+--------+---------------+---------+---------+------------------+------------+------------------------------------------+
```

## MXP-036 - case `INDEX_MERGE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_double.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `33614657`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30465`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id_mysql_server` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id_mysql_server` DESC) AS rn 
FROM `ts_value_slave_double` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `INDEX_MERGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
| id | select_type | table                 | type  | possible_keys   | key        | key_len | ref             | rows     | Extra                                                     |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                     | index | PRIMARY         | is_deleted |       1 | NULL            |      124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>            | ref   | key0            | key0       |       5 | pmacontrol.t.id |   271103 | Using where                                               |
|  2 | DERIVED     | ts_value_slave_double | index | id_mysql_server | PRIMARY    |      79 | NULL            | 33616845 | Using where; Using index; Using temporary                 |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
```

## MXP-037 - case `INDEX_SUBQUERY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_double.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `33614657`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30449`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable` 
FROM `ts_value_slave_double` c 
WHERE c.`id_ts_variable` IN (
SELECT t.id 
FROM `ts_variable` t 
WHERE t.id = c.`id_ts_variable`) 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `INDEX_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+----------+-----------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows     | Extra                       |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+----------+-----------------------------+
|  1 | PRIMARY     | c     | index  | NULL          | PRIMARY |      79 | NULL                        | 33617153 | Using index; Using filesort |
|  1 | PRIMARY     | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |        1 | Using index                 |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+----------+-----------------------------+
```

## MXP-038 - case `INDEX_SUBQUERY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_int.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `710999852`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `10`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server` AS row_id, c.`id_mysql_server` 
FROM `ts_value_slave_int` c 
WHERE c.`id_mysql_server` IN (
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server`) 
ORDER BY c.`id_mysql_server` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `INDEX_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                    |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY         | PRIMARY         |      11 | NULL            |   37 | Using where; Using index |
|  1 | PRIMARY     | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |    8 | Using index              |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
```

## MXP-039 - case `INDEX_SUBQUERY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_int.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `710999852`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30141`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable` 
FROM `ts_value_slave_int` c 
WHERE c.`id_ts_variable` IN (
SELECT t.id 
FROM `ts_variable` t 
WHERE t.id = c.`id_ts_variable`) 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `INDEX_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+-----------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows      | Extra                       |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+-----------------------------+
|  1 | PRIMARY     | c     | index  | NULL          | PRIMARY |      79 | NULL                        | 711055750 | Using index; Using filesort |
|  1 | PRIMARY     | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |         1 | Using index                 |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+-----------------------------+
```

## MXP-040 - case `INDEX_SUBQUERY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_text.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1379143268`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `247`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server` AS row_id, c.`id_mysql_server` 
FROM `ts_value_slave_text` c 
WHERE c.`id_mysql_server` IN (
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server`) 
ORDER BY c.`id_mysql_server` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `INDEX_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                    |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY         | PRIMARY         |      11 | NULL            |   37 | Using where; Using index |
|  1 | PRIMARY     | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |    8 | Using index              |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
```

## MXP-041 - case `NULL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_text.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `1379143268`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30126`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable`, t.id AS target_id 
FROM `ts_value_slave_text` c 
JOIN `ts_variable` t ON t.id = c.`id_ts_variable` 
WHERE c.`id_ts_variable` IS NOT NULL 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `NULL`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+------------+------------------------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows       | Extra                                    |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+------------+------------------------------------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |      79 | NULL                        | 1379234970 | Using where; Using index; Using filesort |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |          1 | Using index                              |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+------------+------------------------------------------+
```

## MXP-042 - case `NULL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `vip_server.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `70195`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `vip_server` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `NULL`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------------------------+-------------------------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys                       | key                                 | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-------------------------------------+-------------------------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | uk_infra_vip_route__id_mysql_server | uk_infra_vip_route__id_mysql_server |      11 | NULL |    3 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-------------------------------------+-------------------------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-043 - case `NULL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `worker_execution.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `283104477`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `worker_execution` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `NULL`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+------------------+-------+-----------------------------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
| id | select_type | table            | type  | possible_keys                     | key        | key_len | ref             | rows      | Extra                                                     |
+----+-------------+------------------+-------+-----------------------------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                | index | PRIMARY                           | is_deleted |       1 | NULL            |       124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>       | ref   | key0                              | key0       |       4 | pmacontrol.t.id |        10 |                                                           |
|  2 | DERIVED     | worker_execution | index | idx_mysql_server_date_started_run | PRIMARY    |       4 | NULL            | 283115919 | Using where                                               |
+----+-------------+------------------+-------+-----------------------------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
```

## MXP-044 - case `NULL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `worker_execution.id_worker_run -> worker_run.id`
- Child table rows (estimate): `283104477`
- Target table rows (estimate): `8433`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30121`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_worker_run`, ROW_NUMBER() OVER (PARTITION BY `id_worker_run` 
ORDER BY `id` DESC) AS rn 
FROM `worker_execution` 
WHERE `id_worker_run` IS NOT NULL) 
SELECT r.row_id, r.`id_worker_run`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `worker_run` t ON t.id = r.`id_worker_run` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `NULL`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+------------------+-------+---------------+---------------+---------+-----------------+-----------+----------------------------------------------+
| id | select_type | table            | type  | possible_keys | key           | key_len | ref             | rows      | Extra                                        |
+----+-------------+------------------+-------+---------------+---------------+---------+-----------------+-----------+----------------------------------------------+
|  1 | PRIMARY     | t                | index | PRIMARY       | is_working    |       1 | NULL            |      8433 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>       | ref   | key0          | key0          |       5 | pmacontrol.t.id |     33572 | Using where                                  |
|  2 | DERIVED     | worker_execution | index | id_worker_run | id_worker_run |       4 | NULL            | 283116674 | Using where; Using index; Using temporary    |
+----+-------------+------------------+-------+---------------+---------------+---------+-----------------+-----------+----------------------------------------------+
```

## MXP-045 - case `RANGE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1165386`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `1366`
- Returned rows: `212`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Expected signature: `RANGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_server | id_mysql_server |       5 | pmacontrol.t.id | 3447 | Using where; Using index                                  |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-046 - case `RANGE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster.id_dot3_graph -> dot3_graph.id`
- Child table rows (estimate): `2832952`
- Target table rows (estimate): `36951`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `1479`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_dot3_graph`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `dot3_cluster` c 
WHERE c.`id_dot3_graph` IS NOT NULL 
GROUP BY c.`id_dot3_graph` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `RANGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+---------------+---------------+---------+------+---------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key           | key_len | ref  | rows    | Extra                                                     |
+----+-------------+-------+-------+---------------+---------------+---------+------+---------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_dot3_graph | id_dot3_graph |       4 | NULL | 2836215 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+---------------+---------------+---------+------+---------+-----------------------------------------------------------+
```

## MXP-047 - case `RANGE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster.id_dot3_information -> dot3_information.id`
- Child table rows (estimate): `2832952`
- Target table rows (estimate): `186196`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_dot3_information`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_dot3_information` 
FROM `dot3_cluster` 
WHERE `id_dot3_information` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `dot3_information` t ON t.id = x.`id_dot3_information` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `RANGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
| id | select_type | table        | type   | possible_keys         | key     | key_len | ref                   | rows    | Extra          |
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
|  1 | PRIMARY     | <derived2>   | ALL    | NULL                  | NULL    | NULL    | NULL                  |    1000 | Using filesort |
|  1 | PRIMARY     | t            | eq_ref | PRIMARY               | PRIMARY |       4 | x.id_dot3_information |       1 | Using index    |
|  2 | DERIVED     | dot3_cluster | index  | id_dot3_information_2 | PRIMARY |       4 | NULL                  | 2836215 | Using where    |
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
```

## MXP-048 - case `RANGE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster__mysql_server.id_dot3_cluster -> dot3_cluster.id`
- Child table rows (estimate): `11665075`
- Target table rows (estimate): `2832954`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30004`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_dot3_cluster`, ROW_NUMBER() OVER (PARTITION BY `id_dot3_cluster` 
ORDER BY `id` DESC) AS rn 
FROM `dot3_cluster__mysql_server` 
WHERE `id_dot3_cluster` IS NOT NULL) 
SELECT r.row_id, r.`id_dot3_cluster`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `dot3_cluster` t ON t.id = r.`id_dot3_cluster` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `RANGE`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-----------------+----------------------------+-------+-----------------+-----------------+---------+-----------------+---------+----------------------------------------------+
| id | select_type     | table                      | type  | possible_keys   | key             | key_len | ref             | rows    | Extra                                        |
+----+-----------------+----------------------------+-------+-----------------+-----------------+---------+-----------------+---------+----------------------------------------------+
|  1 | PRIMARY         | t                          | index | PRIMARY         | id_dot3_graph   |       4 | NULL            | 2836416 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY         | <derived2>                 | ref   | key0            | key0            |       5 | pmacontrol.t.id |       2 | Using where                                  |
|  2 | LATERAL DERIVED | dot3_cluster__mysql_server | ref   | id_dot3_cluster | id_dot3_cluster |       4 | pmacontrol.t.id |       1 | Using where; Using index; Using temporary    |
+----+-----------------+----------------------------+-------+-----------------+-----------------+---------+-----------------+---------+----------------------------------------------+
```

## MXP-049 - case `REF` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster__mysql_server.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `11665075`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `2471`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `dot3_cluster__mysql_server` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `REF`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |  457 | Using index                                               |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-050 - case `REF` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `foreign_key_real.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1183`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `13`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `foreign_key_real` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `10`
- Returned rows: `1`
- Expected signature: `REF`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys                     | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server,id_mysql_server_2 | id_mysql_server |     520 | NULL | 1183 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-051 - case `REF` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `foreign_key_virtual.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `7694`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `10`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `foreign_key_virtual` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `7`
- Returned rows: `3`
- Expected signature: `REF`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table               | type  | possible_keys                                       | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY                                             | is_deleted |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0                                                | key0       |       4 | pmacontrol.t.id |   10 |                                                           |
|  2 | DERIVED     | foreign_key_virtual | index | id_mysql_server_2,id_mysql_server_3,id_mysql_server | PRIMARY    |      11 | NULL            | 7694 | Using where                                               |
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-052 - case `REF` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `geolocalisation_city.id_geolocalisation_country -> geolocalisation_country.id`
- Child table rows (estimate): `161703`
- Target table rows (estimate): `246`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `144`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_geolocalisation_country`, ROW_NUMBER() OVER (PARTITION BY `id_geolocalisation_country` 
ORDER BY `id` DESC) AS rn 
FROM `geolocalisation_city` 
WHERE `id_geolocalisation_country` IS NOT NULL) 
SELECT r.row_id, r.`id_geolocalisation_country`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `geolocalisation_country` t ON t.id = r.`id_geolocalisation_country` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `REF`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
| id | select_type | table                | type  | possible_keys              | key                          | key_len | ref             | rows   | Extra                                        |
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
|  1 | PRIMARY     | t                    | index | PRIMARY                    | id_geolocalisation_continent |       4 | NULL            |    246 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>           | ref   | key0                       | key0                         |       5 | pmacontrol.t.id |    657 | Using where                                  |
|  2 | DERIVED     | geolocalisation_city | index | id_geolocalisation_country | id_geolocalisation_country   |       4 | NULL            | 161703 | Using where; Using index; Using temporary    |
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
```

## MXP-053 - case `REF_OR_NULL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `global_variable.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `651462`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `21`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `global_variable` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Expected signature: `REF_OR_NULL`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys    | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | uniq_id_variable | PRIMARY |      11 | NULL                               |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY          | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-054 - case `REF_OR_NULL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `index_stats.id_mysql_database -> mysql_database.id`
- Child table rows (estimate): `7698`
- Target table rows (estimate): `2995`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `7`
- Returned rows: `184`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_database`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `index_stats` c 
WHERE c.`id_mysql_database` IS NOT NULL 
GROUP BY c.`id_mysql_database` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `1`
- Expected signature: `REF_OR_NULL`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys     | key               | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_database | id_mysql_database |       4 | NULL | 7698 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-055 - case `REF_OR_NULL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `index_stats.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `7698`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `9`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `index_stats` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `REF_OR_NULL`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table       | type  | possible_keys | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t           | index | PRIMARY       | is_deleted |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>  | ref   | key0          | key0       |       4 | pmacontrol.t.id |   10 |                                                           |
|  2 | DERIVED     | index_stats | index | NULL          | PRIMARY    |       4 | NULL            | 7698 | Using where                                               |
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-056 - case `REF_OR_NULL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `2995`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `18`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id` DESC) AS rn 
FROM `mysql_database` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `3`
- Expected signature: `REF_OR_NULL`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table          | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t              | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>     | ref   | key0            | key0            |       5 | pmacontrol.t.id |   24 | Using where                                               |
|  2 | DERIVED     | mysql_database | index | id_mysql_server | id_mysql_server |       4 | NULL            | 2995 | Using where; Using index; Using temporary                 |
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-057 - case `SYSTEM` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_database -> mysql_database.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `2995`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `198`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_database`, t.id AS target_id 
FROM `mysql_database__mysql_digest` c 
JOIN `mysql_database` t ON t.id = c.`id_mysql_database` 
WHERE c.`id_mysql_database` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `9`
- Returned rows: `2`
- Expected signature: `SYSTEM`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys     | key               | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY           | id_mysql_server   |       4 | NULL            | 2995 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_database | id_mysql_database |       4 | pmacontrol.t.id |  144 | Using index                                               |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-058 - case `SYSTEM` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_digest -> mysql_digest.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `192325`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `235`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_digest`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `mysql_database__mysql_digest` c 
WHERE c.`id_mysql_digest` IS NOT NULL 
GROUP BY c.`id_mysql_digest` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `1`
- Expected signature: `SYSTEM`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+----------------------------+------------+---------+------+--------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys              | key        | key_len | ref  | rows   | Extra                                                     |
+----+-------------+-------+-------+----------------------------+------------+---------+------+--------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_digest,idx_digest | idx_digest |       4 | NULL | 471977 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+----------------------------+------------+---------+------+--------+-----------------------------------------------------------+
```

## MXP-059 - case `SYSTEM` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `8`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `mysql_database__mysql_digest` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `SYSTEM`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+------------------------------+-------+-----------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
| id | select_type | table                        | type  | possible_keys   | key        | key_len | ref             | rows   | Extra                                                     |
+----+-------------+------------------------------+-------+-----------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                            | index | PRIMARY         | is_deleted |       1 | NULL            |    124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>                   | ref   | key0            | key0       |       4 | pmacontrol.t.id |     10 |                                                           |
|  2 | DERIVED     | mysql_database__mysql_digest | index | id_mysql_server | PRIMARY    |       4 | NULL            | 471977 | Using where                                               |
+----+-------------+------------------------------+-------+-----------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
```

## MXP-060 - case `SYSTEM` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ssh_tunnel.id_maxscale_server -> maxscale_server.id`
- Child table rows (estimate): `522`
- Target table rows (estimate): `37530`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `8`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_maxscale_server`, ROW_NUMBER() OVER (PARTITION BY `id_maxscale_server` 
ORDER BY `id` DESC) AS rn 
FROM `ssh_tunnel` 
WHERE `id_maxscale_server` IS NOT NULL) 
SELECT r.row_id, r.`id_maxscale_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `maxscale_server` t ON t.id = r.`id_maxscale_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `3`
- Expected signature: `SYSTEM`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+-------------------------------------------+
| id | select_type | table      | type   | possible_keys      | key                | key_len | ref                        | rows | Extra                                     |
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+-------------------------------------------+
|  1 | PRIMARY     | <derived2> | ALL    | NULL               | NULL               | NULL    | NULL                       |    2 | Using where; Using filesort               |
|  1 | PRIMARY     | t          | eq_ref | PRIMARY            | PRIMARY            |      11 | r.id_maxscale_server,const |    1 | Using index                               |
|  2 | DERIVED     | ssh_tunnel | range  | id_maxscale_server | id_maxscale_server |       5 | NULL                       |    1 | Using where; Using index; Using temporary |
+----+-------------+------------+--------+--------------------+--------------------+---------+----------------------------+------+-------------------------------------------+
```

## MXP-061 - case `UNIQUE_SUBQUERY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ssh_tunnel.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `522`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `6`
- Returned rows: `10`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `ssh_tunnel` c 
WHERE c.`id_mysql_server` IN (
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server`) 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `2`
- Expected signature: `UNIQUE_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
|  1 | PRIMARY     | c     | index  | idx_server    | PRIMARY |      11 | NULL                               |   20 | Using where |
|  1 | PRIMARY     | t     | eq_ref | PRIMARY       | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+---------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-062 - case `UNIQUE_SUBQUERY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_date_by_server.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `254101411`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30155`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `ts_date_by_server` c 
WHERE c.`id_mysql_server` IN (
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server`) 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `2`
- Expected signature: `UNIQUE_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |    4 | Using index                                               |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-063 - case `UNIQUE_SUBQUERY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_max_date.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `5455`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `7`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `ts_max_date` c 
WHERE c.`id_mysql_server` IN (
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server`) 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `UNIQUE_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------------------------------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys                                       | key               | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------------------------------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY                                             | is_deleted        |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | c     | ref   | id_mysql_server_2,id_mysql_server,id_mysql_server_4 | id_mysql_server_2 |       4 | pmacontrol.t.id |   21 | Using index                                               |
+----+-------------+-------+-------+-----------------------------------------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-064 - case `UNIQUE_SUBQUERY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_mysql_digest_stat.id_mysql_database__mysql_digest -> mysql_database__mysql_digest.id`
- Child table rows (estimate): `2639967210`
- Target table rows (estimate): `471977`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `11`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_database__mysql_digest` AS row_id, c.`id_mysql_database__mysql_digest` 
FROM `ts_mysql_digest_stat` c 
WHERE c.`id_mysql_database__mysql_digest` IN (
SELECT t.id 
FROM `mysql_database__mysql_digest` t 
WHERE t.id = c.`id_mysql_database__mysql_digest`) 
ORDER BY c.`id_mysql_database__mysql_digest` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `UNIQUE_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-------------+
| id | select_type | table | type  | possible_keys     | key               | key_len | ref             | rows | Extra       |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-------------+
|  1 | PRIMARY     | t     | index | PRIMARY           | PRIMARY           |       4 | NULL            |    1 | Using index |
|  1 | PRIMARY     | c     | ref   | idx_digest_schema | idx_digest_schema |       4 | pmacontrol.t.id | 1288 | Using index |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-------------+
```

## MXP-065 - case `USINGFSORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_mysql_query.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `71962`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `ts_mysql_query` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `USINGFSORT`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys   | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | id_mysql_server | PRIMARY |       4 | NULL                               |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+-----------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-066 - case `USINGFSORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_int.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `740562020`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30014`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id_mysql_server`) AS min_id, MAX(c.`id_mysql_server`) AS max_id 
FROM `ts_value_digest_int` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `USINGFSORT`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows      | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |      17 | NULL | 740562020 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-----------+-----------------------------------------------------------+
```

## MXP-067 - case `USINGFSORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_int.id_ts_mysql_query -> ts_mysql_query.id`
- Child table rows (estimate): `740562020`
- Target table rows (estimate): `71962`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30005`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT x.row_id, x.`id_ts_mysql_query`, t.id AS target_id 
FROM (
SELECT `id_ts_mysql_query` AS row_id, `id_ts_mysql_query` 
FROM `ts_value_digest_int` 
WHERE `id_ts_mysql_query` IS NOT NULL 
ORDER BY `id_ts_mysql_query` DESC 
LIMIT 1000) x 
JOIN `ts_mysql_query` t ON t.id = x.`id_ts_mysql_query` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `USINGFSORT`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+---------------------+--------+---------------+---------+---------+---------------------+-----------+------------------------------------------+
| id | select_type | table               | type   | possible_keys | key     | key_len | ref                 | rows      | Extra                                    |
+----+-------------+---------------------+--------+---------------+---------+---------+---------------------+-----------+------------------------------------------+
|  1 | PRIMARY     | <derived2>          | ALL    | NULL          | NULL    | NULL    | NULL                |      1000 | Using filesort                           |
|  1 | PRIMARY     | t                   | eq_ref | PRIMARY       | PRIMARY |       4 | x.id_ts_mysql_query |         1 | Using index                              |
|  2 | DERIVED     | ts_value_digest_int | index  | NULL          | PRIMARY |      17 | NULL                | 740562020 | Using where; Using index; Using filesort |
+----+-------------+---------------------+--------+---------------+---------+---------+---------------------+-----------+------------------------------------------+
```

## MXP-068 - case `USINGFSORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_int.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `740562020`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30102`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id_ts_variable` AS row_id, `id_ts_variable`, ROW_NUMBER() OVER (PARTITION BY `id_ts_variable` 
ORDER BY `id_ts_variable` DESC) AS rn 
FROM `ts_value_digest_int` 
WHERE `id_ts_variable` IS NOT NULL) 
SELECT r.row_id, r.`id_ts_variable`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `ts_variable` t ON t.id = r.`id_ts_variable` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `USINGFSORT`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+---------------------+-------+---------------+--------------------+---------+-----------------+-----------+----------------------------------------------+
| id | select_type | table               | type  | possible_keys | key                | key_len | ref             | rows      | Extra                                        |
+----+-------------+---------------------+-------+---------------+--------------------+---------+-----------------+-----------+----------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY       | ts_variable_ibfk_1 |       4 | NULL            |      2798 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0          | key0               |       5 | pmacontrol.t.id |    264675 | Using where                                  |
|  2 | DERIVED     | ts_value_digest_int | index | NULL          | PRIMARY            |      17 | NULL            | 740562020 | Using where; Using index; Using temporary    |
+----+-------------+---------------------+-------+---------------+--------------------+---------+-----------------+-----------+----------------------------------------------+
```

## MXP-069 - case `USINGIDXGROUPBY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_text.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `86664`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `28`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `ts_value_digest_text` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id_mysql_server` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Expected signature: `USINGIDXGROUPBY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+-----------------+-----------------+---------+------------------------------------+------+--------------------------+
| id | select_type | table | type   | possible_keys   | key             | key_len | ref                                | rows | Extra                    |
+----+-------------+-------+--------+-----------------+-----------------+---------+------------------------------------+------+--------------------------+
|  1 | SIMPLE      | c     | index  | id_mysql_server | id_mysql_server |      17 | NULL                               |  300 | Using where; Using index |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY         | PRIMARY         |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index              |
+----+-------------+-------+--------+-----------------+-----------------+---------+------------------------------------+------+--------------------------+
```

## MXP-070 - case `USINGIDXGROUPBY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_text.id_ts_mysql_query -> ts_mysql_query.id`
- Child table rows (estimate): `86664`
- Target table rows (estimate): `71962`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `94`
- Returned rows: `18`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_ts_mysql_query`, COUNT(*) AS cnt, MIN(c.`id_ts_mysql_query`) AS min_id, MAX(c.`id_ts_mysql_query`) AS max_id 
FROM `ts_value_digest_text` c 
WHERE c.`id_ts_mysql_query` IS NOT NULL 
GROUP BY c.`id_ts_mysql_query` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `1`
- Expected signature: `USINGIDXGROUPBY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+---------------+---------+---------+------+-------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys | key     | key_len | ref  | rows  | Extra                                                     |
+----+-------------+-------+-------+---------------+---------+---------+------+-------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | NULL          | PRIMARY |      17 | NULL | 86664 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+---------------+---------+---------+------+-------+-----------------------------------------------------------+
```

## MXP-071 - case `USINGIDXGROUPBY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_digest_text.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `86664`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `69`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_ts_variable`, t.id AS target_id 
FROM (
SELECT `id_ts_variable` AS row_id, `id_ts_variable` 
FROM `ts_value_digest_text` 
WHERE `id_ts_variable` IS NOT NULL 
ORDER BY `id_ts_variable` DESC 
LIMIT 1000) x 
JOIN `ts_variable` t ON t.id = x.`id_ts_variable` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `USINGIDXGROUPBY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------+------------------------------------------+
| id | select_type | table                | type   | possible_keys | key     | key_len | ref              | rows  | Extra                                    |
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------+------------------------------------------+
|  1 | PRIMARY     | <derived2>           | ALL    | NULL          | NULL    | NULL    | NULL             |  1000 | Using filesort                           |
|  1 | PRIMARY     | t                    | eq_ref | PRIMARY       | PRIMARY |       4 | x.id_ts_variable |     1 | Using index                              |
|  2 | DERIVED     | ts_value_digest_text | index  | NULL          | PRIMARY |      17 | NULL             | 86664 | Using where; Using index; Using filesort |
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------+------------------------------------------+
```

## MXP-072 - case `USINGIDXGROUPBY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_double.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `602271541`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30076`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id_mysql_server` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id_mysql_server` DESC) AS rn 
FROM `ts_value_general_double` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `3`
- Expected signature: `USINGIDXGROUPBY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
| id | select_type | table                   | type  | possible_keys   | key        | key_len | ref             | rows      | Extra                                                     |
+----+-------------+-------------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                       | index | PRIMARY         | is_deleted |       1 | NULL            |       124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>              | ref   | key0            | key0       |       5 | pmacontrol.t.id |   4857845 | Using where                                               |
|  2 | DERIVED     | ts_value_general_double | index | id_mysql_server | PRIMARY    |      13 | NULL            | 602372880 | Using where; Using index; Using temporary                 |
+----+-------------+-------------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
```

## MXP-073 - case `USINGINDEX` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_double.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `602271541`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30285`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable`, t.id AS target_id 
FROM `ts_value_general_double` c 
JOIN `ts_variable` t ON t.id = c.`id_ts_variable` 
WHERE c.`id_ts_variable` IS NOT NULL 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `USINGINDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows      | Extra                                    |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |      13 | NULL                        | 602372880 | Using where; Using index; Using filesort |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |         1 | Using index                              |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
```

## MXP-074 - case `USINGINDEX` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_int.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `31544877635`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30020`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id_mysql_server`) AS min_id, MAX(c.`id_mysql_server`) AS max_id 
FROM `ts_value_general_int` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `USINGINDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-------------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows        | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-------------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |      13 | NULL | 31549929429 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+-------------+-----------------------------------------------------------+
```

## MXP-075 - case `USINGINDEX` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_int.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `31544877635`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30006`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT x.row_id, x.`id_ts_variable`, t.id AS target_id 
FROM (
SELECT `id_ts_variable` AS row_id, `id_ts_variable` 
FROM `ts_value_general_int` 
WHERE `id_ts_variable` IS NOT NULL 
ORDER BY `id_ts_variable` DESC 
LIMIT 1000) x 
JOIN `ts_variable` t ON t.id = x.`id_ts_variable` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `USINGINDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------------+------------------------------------------+
| id | select_type | table                | type   | possible_keys | key     | key_len | ref              | rows        | Extra                                    |
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------------+------------------------------------------+
|  1 | PRIMARY     | <derived2>           | ALL    | NULL          | NULL    | NULL    | NULL             |        1000 | Using filesort                           |
|  1 | PRIMARY     | t                    | eq_ref | PRIMARY       | PRIMARY |       4 | x.id_ts_variable |           1 | Using index                              |
|  2 | DERIVED     | ts_value_general_int | index  | NULL          | PRIMARY |      13 | NULL             | 31550226096 | Using where; Using index; Using filesort |
+----+-------------+----------------------+--------+---------------+---------+---------+------------------+-------------+------------------------------------------+
```

## MXP-076 - case `USINGINDEX` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_json.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `330597278`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30029`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id_mysql_server` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id_mysql_server` DESC) AS rn 
FROM `ts_value_general_json` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `USINGINDEX`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
| id | select_type | table                 | type  | possible_keys   | key        | key_len | ref             | rows      | Extra                                                     |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                     | index | PRIMARY         | is_deleted |       1 | NULL            |       124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>            | ref   | key0            | key0       |       5 | pmacontrol.t.id |   2666732 | Using where                                               |
|  2 | DERIVED     | ts_value_general_json | index | id_mysql_server | PRIMARY    |      13 | NULL            | 330674811 | Using where; Using index; Using temporary                 |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+-----------+-----------------------------------------------------------+
```

## MXP-077 - case `USINGTEMP` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_json.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `330597278`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30015`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable`, t.id AS target_id 
FROM `ts_value_general_json` c 
JOIN `ts_variable` t ON t.id = c.`id_ts_variable` 
WHERE c.`id_ts_variable` IS NOT NULL 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Expected signature: `USINGTEMP`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows      | Extra                                    |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
|  1 | SIMPLE      | c     | index  | NULL          | PRIMARY |      13 | NULL                        | 330676302 | Using where; Using index; Using filesort |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |         1 | Using index                              |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+------------------------------------------+
```

## MXP-078 - case `USINGTEMP` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_text.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `2125535228`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30005`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id_mysql_server`) AS min_id, MAX(c.`id_mysql_server`) AS max_id 
FROM `ts_value_general_text` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `USINGTEMP`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref  | rows       | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server | id_mysql_server |      13 | NULL | 2125918762 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------+-----------------+---------+------+------------+-----------------------------------------------------------+
```

## MXP-079 - case `USINGTEMP` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_general_text.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `2125535228`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30028`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT x.row_id, x.`id_ts_variable`, t.id AS target_id 
FROM (
SELECT `id_ts_variable` AS row_id, `id_ts_variable` 
FROM `ts_value_general_text` 
WHERE `id_ts_variable` IS NOT NULL 
ORDER BY `id_ts_variable` DESC 
LIMIT 1000) x 
JOIN `ts_variable` t ON t.id = x.`id_ts_variable` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `USINGTEMP`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-----------------------+--------+---------------+---------+---------+------------------+------------+------------------------------------------+
| id | select_type | table                 | type   | possible_keys | key     | key_len | ref              | rows       | Extra                                    |
+----+-------------+-----------------------+--------+---------------+---------+---------+------------------+------------+------------------------------------------+
|  1 | PRIMARY     | <derived2>            | ALL    | NULL          | NULL    | NULL    | NULL             |       1000 | Using filesort                           |
|  1 | PRIMARY     | t                     | eq_ref | PRIMARY       | PRIMARY |       4 | x.id_ts_variable |          1 | Using index                              |
|  2 | DERIVED     | ts_value_general_text | index  | NULL          | PRIMARY |      13 | NULL             | 2125943823 | Using where; Using index; Using filesort |
+----+-------------+-----------------------+--------+---------------+---------+---------+------------------+------------+------------------------------------------+
```

## MXP-080 - case `USINGTEMP` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_double.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `33614657`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30595`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
WITH ranked AS (
SELECT `id_mysql_server` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id_mysql_server` DESC) AS rn 
FROM `ts_value_slave_double` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `USINGTEMP`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
| id | select_type | table                 | type  | possible_keys   | key        | key_len | ref             | rows     | Extra                                                     |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                     | index | PRIMARY         | is_deleted |       1 | NULL            |      124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>            | ref   | key0            | key0       |       5 | pmacontrol.t.id |   271133 | Using where                                               |
|  2 | DERIVED     | ts_value_slave_double | index | id_mysql_server | PRIMARY    |      79 | NULL            | 33620559 | Using where; Using index; Using temporary                 |
+----+-------------+-----------------------+-------+-----------------+------------+---------+-----------------+----------+-----------------------------------------------------------+
```

## MXP-081 - case `dependent_subquery` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_double.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `33614657`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `31355`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable` 
FROM `ts_value_slave_double` c 
WHERE EXISTS (
SELECT 1 
FROM `ts_variable` t 
WHERE t.id = c.`id_ts_variable` AND t.id = c.`id_ts_variable`) 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Expected signature: `DEPENDENT_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+----------+-----------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows     | Extra                       |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+----------+-----------------------------+
|  1 | PRIMARY     | c     | index  | NULL          | PRIMARY |      79 | NULL                        | 33620756 | Using index; Using filesort |
|  1 | PRIMARY     | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |        1 | Using index                 |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+----------+-----------------------------+
```

## MXP-082 - case `dependent_subquery` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_int.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `710999852`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `15`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server` AS row_id, c.`id_mysql_server` 
FROM `ts_value_slave_int` c 
WHERE EXISTS (
SELECT 1 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server` AND t.id = c.`id_mysql_server`) 
ORDER BY c.`id_mysql_server` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `2`
- Expected signature: `DEPENDENT_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                    |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY         | PRIMARY         |      11 | NULL            |   31 | Using where; Using index |
|  1 | PRIMARY     | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |    8 | Using index              |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
```

## MXP-083 - case `dependent_subquery` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_int.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `710999852`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30115`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable` 
FROM `ts_value_slave_int` c 
WHERE EXISTS (
SELECT 1 
FROM `ts_variable` t 
WHERE t.id = c.`id_ts_variable` AND t.id = c.`id_ts_variable`) 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `DEPENDENT_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+-----------------------------+
| id | select_type | table | type   | possible_keys | key     | key_len | ref                         | rows      | Extra                       |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+-----------------------------+
|  1 | PRIMARY     | c     | index  | NULL          | PRIMARY |      79 | NULL                        | 711128896 | Using index; Using filesort |
|  1 | PRIMARY     | t     | eq_ref | PRIMARY       | PRIMARY |       4 | pmacontrol.c.id_ts_variable |         1 | Using index                 |
+----+-------------+-------+--------+---------------+---------+---------+-----------------------------+-----------+-----------------------------+
```

## MXP-084 - case `dependent_subquery` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_text.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1379143268`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `198`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server` AS row_id, c.`id_mysql_server` 
FROM `ts_value_slave_text` c 
WHERE EXISTS (
SELECT 1 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server` AND t.id = c.`id_mysql_server`) 
ORDER BY c.`id_mysql_server` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `DEPENDENT_SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                    |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY         | PRIMARY         |      11 | NULL            |   31 | Using where; Using index |
|  1 | PRIMARY     | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |    8 | Using index              |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+--------------------------+
```

## MXP-085 - case `dependent_union` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_value_slave_text.id_ts_variable -> ts_variable.id`
- Child table rows (estimate): `1379143268`
- Target table rows (estimate): `2798`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30006`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_ts_variable` AS row_id, c.`id_ts_variable` 
FROM `ts_value_slave_text` c 
WHERE c.`id_ts_variable` IN ((
SELECT t.id 
FROM `ts_variable` t 
WHERE t.id = c.`id_ts_variable`) 
UNION (
SELECT t2.id 
FROM `ts_variable` t2 
WHERE t2.id = c.`id_ts_variable`)) 
ORDER BY c.`id_ts_variable` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `4`
- Expected signature: `DEPENDENT_UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------------+------------+--------+---------------------------------+---------+---------+-----------------------------+------------+------------------------------------------+
| id   | select_type        | table      | type   | possible_keys                   | key     | key_len | ref                         | rows       | Extra                                    |
+------+--------------------+------------+--------+---------------------------------+---------+---------+-----------------------------+------------+------------------------------------------+
|    1 | PRIMARY            | c          | index  | NULL                            | PRIMARY |      79 | NULL                        | 1379404549 | Using where; Using index; Using filesort |
|    2 | DEPENDENT SUBQUERY | t          | eq_ref | PRIMARY,ts_variable_ibfk_1,from | PRIMARY |       4 | pmacontrol.c.id_ts_variable |          1 | Using where; Using index                 |
|    3 | DEPENDENT UNION    | t2         | eq_ref | PRIMARY,ts_variable_ibfk_1,from | PRIMARY |       4 | pmacontrol.c.id_ts_variable |          1 | Using where; Using index                 |
| NULL | UNION RESULT       | <union2,3> | ALL    | NULL                            | NULL    | NULL    | NULL                        | NULL       |                                          |
+------+--------------------+------------+--------+---------------------------------+---------+---------+-----------------------------+------------+------------------------------------------+
```

## MXP-086 - case `dependent_union` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `vip_server.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `70195`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `3`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `vip_server` c 
WHERE c.`id_mysql_server` IN ((
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server`) 
UNION (
SELECT t2.id 
FROM `mysql_server` t2 
WHERE t2.id = c.`id_mysql_server`)) 
ORDER BY c.`id` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `4`
- Expected signature: `DEPENDENT_UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------------+------------+--------+-----------------+---------+---------+------------------------------------+------+--------------------------+
| id   | select_type        | table      | type   | possible_keys   | key     | key_len | ref                                | rows | Extra                    |
+------+--------------------+------------+--------+-----------------+---------+---------+------------------------------------+------+--------------------------+
|    1 | PRIMARY            | c          | index  | NULL            | PRIMARY |      11 | NULL                               |    3 | Using where              |
|    2 | DEPENDENT SUBQUERY | t          | eq_ref | PRIMARY,name,ip | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using where; Using index |
|    3 | DEPENDENT UNION    | t2         | eq_ref | PRIMARY,name,ip | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using where; Using index |
| NULL | UNION RESULT       | <union2,3> | ALL    | NULL            | NULL    | NULL    | NULL                               | NULL |                          |
+------+--------------------+------------+--------+-----------------+---------+---------+------------------------------------+------+--------------------------+
```

## MXP-087 - case `dependent_union` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `worker_execution.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `283104477`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `11`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `worker_execution` c 
WHERE c.`id_mysql_server` IN ((
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id = c.`id_mysql_server`) 
UNION (
SELECT t2.id 
FROM `mysql_server` t2 
WHERE t2.id = c.`id_mysql_server`)) 
ORDER BY c.`id` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `4`
- Expected signature: `DEPENDENT_UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------------+------------+--------+-----------------+---------+---------+------------------------------------+------+--------------------------+
| id   | select_type        | table      | type   | possible_keys   | key     | key_len | ref                                | rows | Extra                    |
+------+--------------------+------------+--------+-----------------+---------+---------+------------------------------------+------+--------------------------+
|    1 | PRIMARY            | c          | index  | NULL            | PRIMARY |       4 | NULL                               |  250 | Using where              |
|    2 | DEPENDENT SUBQUERY | t          | eq_ref | PRIMARY,name,ip | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using where; Using index |
|    3 | DEPENDENT UNION    | t2         | eq_ref | PRIMARY,name,ip | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using where; Using index |
| NULL | UNION RESULT       | <union2,3> | ALL    | NULL            | NULL    | NULL    | NULL                               | NULL |                          |
+------+--------------------+------------+--------+-----------------+---------+---------+------------------------------------+------+--------------------------+
```

## MXP-088 - case `dependent_union` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `worker_execution.id_worker_run -> worker_run.id`
- Child table rows (estimate): `283104477`
- Target table rows (estimate): `8433`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `9`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_worker_run` 
FROM `worker_execution` c 
WHERE c.`id_worker_run` IN ((
SELECT t.id 
FROM `worker_run` t 
WHERE t.id = c.`id_worker_run`) 
UNION (
SELECT t2.id 
FROM `worker_run` t2 
WHERE t2.id = c.`id_worker_run`)) 
ORDER BY c.`id` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `4`
- Expected signature: `DEPENDENT_UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------------+------------+--------+--------------------------------------------------+---------+---------+----------------------------+------+--------------------------+
| id   | select_type        | table      | type   | possible_keys                                    | key     | key_len | ref                        | rows | Extra                    |
+------+--------------------+------------+--------+--------------------------------------------------+---------+---------+----------------------------+------+--------------------------+
|    1 | PRIMARY            | c          | index  | NULL                                             | PRIMARY |       4 | NULL                       |  250 | Using where              |
|    2 | DEPENDENT SUBQUERY | t          | eq_ref | PRIMARY,pid,is_working,is_working_2,is_working_3 | PRIMARY |       4 | pmacontrol.c.id_worker_run |    1 | Using where; Using index |
|    3 | DEPENDENT UNION    | t2         | eq_ref | PRIMARY,pid,is_working,is_working_2,is_working_3 | PRIMARY |       4 | pmacontrol.c.id_worker_run |    1 | Using where; Using index |
| NULL | UNION RESULT       | <union2,3> | ALL    | NULL                                             | NULL    | NULL    | NULL                       | NULL |                          |
+------+--------------------+------------+--------+--------------------------------------------------+---------+---------+----------------------------+------+--------------------------+
```

## MXP-089 - case `derived` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1165386`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `412`
- Returned rows: `212`
- Fail reason: `none`

### SQL

```sql
SELECT d.row_id, d.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1200) d 
JOIN `mysql_server` t ON t.id = d.`id_mysql_server` 
ORDER BY d.row_id DESC 
LIMIT 220
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `DERIVED`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+------------+-------+-----------------+------------+---------+-----------------+---------+-----------------------------------------------------------+
| id | select_type | table      | type  | possible_keys   | key        | key_len | ref             | rows    | Extra                                                     |
+----+-------------+------------+-------+-----------------+------------+---------+-----------------+---------+-----------------------------------------------------------+
|  1 | PRIMARY     | t          | index | PRIMARY         | is_deleted |       1 | NULL            |     124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2> | ref   | key0            | key0       |       5 | pmacontrol.t.id |      10 |                                                           |
|  2 | DERIVED     | alias_dns  | index | id_mysql_server | PRIMARY    |      11 | NULL            | 1165386 | Using where                                               |
+----+-------------+------------+-------+-----------------+------------+---------+-----------------+---------+-----------------------------------------------------------+
```

## MXP-090 - case `derived` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster.id_dot3_graph -> dot3_graph.id`
- Child table rows (estimate): `2832952`
- Target table rows (estimate): `36951`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `220`
- Fail reason: `none`

### SQL

```sql
SELECT d.row_id, d.`id_dot3_graph`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_dot3_graph` 
FROM `dot3_cluster` 
WHERE `id_dot3_graph` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1200) d 
JOIN `dot3_graph` t ON t.id = d.`id_dot3_graph` 
ORDER BY d.row_id DESC 
LIMIT 220
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `DERIVED`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+--------------+--------+---------------+---------+---------+-----------------+---------+----------------+
| id | select_type | table        | type   | possible_keys | key     | key_len | ref             | rows    | Extra          |
+----+-------------+--------------+--------+---------------+---------+---------+-----------------+---------+----------------+
|  1 | PRIMARY     | <derived2>   | ALL    | NULL          | NULL    | NULL    | NULL            |    1200 | Using filesort |
|  1 | PRIMARY     | t            | eq_ref | PRIMARY       | PRIMARY |       4 | d.id_dot3_graph |       1 | Using index    |
|  2 | DERIVED     | dot3_cluster | index  | id_dot3_graph | PRIMARY |       4 | NULL            | 2839664 | Using where    |
+----+-------------+--------------+--------+---------------+---------+---------+-----------------+---------+----------------+
```

## MXP-091 - case `derived` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster.id_dot3_information -> dot3_information.id`
- Child table rows (estimate): `2832952`
- Target table rows (estimate): `186196`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `6`
- Returned rows: `220`
- Fail reason: `none`

### SQL

```sql
SELECT d.row_id, d.`id_dot3_information`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_dot3_information` 
FROM `dot3_cluster` 
WHERE `id_dot3_information` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1200) d 
JOIN `dot3_information` t ON t.id = d.`id_dot3_information` 
ORDER BY d.row_id DESC 
LIMIT 220
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `DERIVED`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
| id | select_type | table        | type   | possible_keys         | key     | key_len | ref                   | rows    | Extra          |
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
|  1 | PRIMARY     | <derived2>   | ALL    | NULL                  | NULL    | NULL    | NULL                  |    1200 | Using filesort |
|  1 | PRIMARY     | t            | eq_ref | PRIMARY               | PRIMARY |       4 | d.id_dot3_information |       1 | Using index    |
|  2 | DERIVED     | dot3_cluster | index  | id_dot3_information_2 | PRIMARY |       4 | NULL                  | 2839664 | Using where    |
+----+-------------+--------------+--------+-----------------------+---------+---------+-----------------------+---------+----------------+
```

## MXP-092 - case `derived` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster__mysql_server.id_dot3_cluster -> dot3_cluster.id`
- Child table rows (estimate): `11665075`
- Target table rows (estimate): `2832954`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `7`
- Returned rows: `220`
- Fail reason: `none`

### SQL

```sql
SELECT d.row_id, d.`id_dot3_cluster`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_dot3_cluster` 
FROM `dot3_cluster__mysql_server` 
WHERE `id_dot3_cluster` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1200) d 
JOIN `dot3_cluster` t ON t.id = d.`id_dot3_cluster` 
ORDER BY d.row_id DESC 
LIMIT 220
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `DERIVED`
- Signature match: `yes`
- Fail reason: `none`

```text
+----+-------------+----------------------------+--------+-----------------+---------+---------+-------------------+----------+----------------+
| id | select_type | table                      | type   | possible_keys   | key     | key_len | ref               | rows     | Extra          |
+----+-------------+----------------------------+--------+-----------------+---------+---------+-------------------+----------+----------------+
|  1 | PRIMARY     | <derived2>                 | ALL    | NULL            | NULL    | NULL    | NULL              |     1200 | Using filesort |
|  1 | PRIMARY     | t                          | eq_ref | PRIMARY         | PRIMARY |       4 | d.id_dot3_cluster |        1 | Using index    |
|  2 | DERIVED     | dot3_cluster__mysql_server | index  | id_dot3_cluster | PRIMARY |       4 | NULL              | 11692931 | Using where    |
+----+-------------+----------------------------+--------+-----------------+---------+---------+-------------------+----------+----------------+
```

## MXP-093 - case `keylen_1` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `dot3_cluster__mysql_server.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `11665075`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `2813`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `dot3_cluster__mysql_server` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `2`
- Expected signature: `KEYLEN_1`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | t     | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | SIMPLE      | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id |  457 | Using index                                               |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-094 - case `keylen_1` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `foreign_key_real.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `1183`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `13`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `foreign_key_real` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `KEYLEN_1`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys                     | key             | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_server,id_mysql_server_2 | id_mysql_server |     520 | NULL | 1183 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-----------------------------------+-----------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-095 - case `keylen_1` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `foreign_key_virtual.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `7694`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `6`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `foreign_key_virtual` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `KEYLEN_1`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table               | type  | possible_keys                                       | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t                   | index | PRIMARY                                             | is_deleted |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>          | ref   | key0                                                | key0       |       4 | pmacontrol.t.id |   10 |                                                           |
|  2 | DERIVED     | foreign_key_virtual | index | id_mysql_server_2,id_mysql_server_3,id_mysql_server | PRIMARY    |      11 | NULL            | 7694 | Using where                                               |
+----+-------------+---------------------+-------+-----------------------------------------------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-096 - case `keylen_1` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `geolocalisation_city.id_geolocalisation_country -> geolocalisation_country.id`
- Child table rows (estimate): `161703`
- Target table rows (estimate): `246`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `103`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_geolocalisation_country`, ROW_NUMBER() OVER (PARTITION BY `id_geolocalisation_country` 
ORDER BY `id` DESC) AS rn 
FROM `geolocalisation_city` 
WHERE `id_geolocalisation_country` IS NOT NULL) 
SELECT r.row_id, r.`id_geolocalisation_country`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `geolocalisation_country` t ON t.id = r.`id_geolocalisation_country` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `KEYLEN_1`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
| id | select_type | table                | type  | possible_keys              | key                          | key_len | ref             | rows   | Extra                                        |
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
|  1 | PRIMARY     | t                    | index | PRIMARY                    | id_geolocalisation_continent |       4 | NULL            |    246 | Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>           | ref   | key0                       | key0                         |       5 | pmacontrol.t.id |    657 | Using where                                  |
|  2 | DERIVED     | geolocalisation_city | index | id_geolocalisation_country | id_geolocalisation_country   |       4 | NULL            | 161703 | Using where; Using index; Using temporary    |
+----+-------------+----------------------+-------+----------------------------+------------------------------+---------+-----------------+--------+----------------------------------------------+
```

## MXP-097 - case `keylen_2` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `global_variable.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `651462`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server`, t.id AS target_id 
FROM `global_variable` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.`id` DESC 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `KEYLEN_2`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
| id | select_type | table | type   | possible_keys    | key     | key_len | ref                                | rows | Extra       |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
|  1 | SIMPLE      | c     | index  | uniq_id_variable | PRIMARY |      11 | NULL                               |  300 | Using where |
|  1 | SIMPLE      | t     | eq_ref | PRIMARY          | PRIMARY |      11 | pmacontrol.c.id_mysql_server,const |    1 | Using index |
+----+-------------+-------+--------+------------------+---------+---------+------------------------------------+------+-------------+
```

## MXP-098 - case `keylen_2` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `index_stats.id_mysql_database -> mysql_database.id`
- Child table rows (estimate): `7698`
- Target table rows (estimate): `2995`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `7`
- Returned rows: `184`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id_mysql_database`, COUNT(*) AS cnt, MIN(c.`id`) AS min_id, MAX(c.`id`) AS max_id 
FROM `index_stats` c 
WHERE c.`id_mysql_database` IS NOT NULL 
GROUP BY c.`id_mysql_database` 
ORDER BY cnt DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `1`
- Expected signature: `KEYLEN_2`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys     | key               | key_len | ref  | rows | Extra                                                     |
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
|  1 | SIMPLE      | c     | index | id_mysql_database | id_mysql_database |       4 | NULL | 7698 | Using where; Using index; Using temporary; Using filesort |
+----+-------------+-------+-------+-------------------+-------------------+---------+------+------+-----------------------------------------------------------+
```

## MXP-099 - case `keylen_2` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `index_stats.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `7698`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `200`
- Fail reason: `none`

### SQL

```sql
SELECT x.row_id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT `id` AS row_id, `id_mysql_server` 
FROM `index_stats` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY `id` DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.row_id DESC 
LIMIT 200
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `3`
- Returned rows: `3`
- Expected signature: `KEYLEN_2`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table       | type  | possible_keys | key        | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t           | index | PRIMARY       | is_deleted |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>  | ref   | key0          | key0       |       4 | pmacontrol.t.id |   10 |                                                           |
|  2 | DERIVED     | index_stats | index | NULL          | PRIMARY    |       4 | NULL            | 7698 | Using where                                               |
+----+-------------+-------------+-------+---------------+------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-100 - case `keylen_2` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `2995`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `7`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
WITH ranked AS (
SELECT `id` AS row_id, `id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY `id_mysql_server` 
ORDER BY `id` DESC) AS rn 
FROM `mysql_database` 
WHERE `id_mysql_server` IS NOT NULL) 
SELECT r.row_id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.row_id DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `KEYLEN_2`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table          | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t              | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | <derived2>     | ref   | key0            | key0            |       5 | pmacontrol.t.id |   24 | Using where                                               |
|  2 | DERIVED     | mysql_database | index | id_mysql_server | id_mysql_server |       4 | NULL            | 2995 | Using where; Using index; Using temporary                 |
+----+-------------+----------------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-101 - case `subquery` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_database -> mysql_database.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `2995`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `81`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_database` 
FROM `mysql_database__mysql_digest` c 
WHERE c.`id_mysql_database` IN (
SELECT t.id 
FROM `mysql_database` t 
WHERE t.id IS NOT NULL) 
ORDER BY c.`id` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys     | key               | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY           | id_mysql_server   |       4 | NULL            | 2995 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | c     | ref   | id_mysql_database | id_mysql_database |       4 | pmacontrol.t.id |  144 | Using index                                               |
+----+-------------+-------+-------+-------------------+-------------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-102 - case `subquery` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_digest -> mysql_digest.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `192325`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `394`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_digest` 
FROM `mysql_database__mysql_digest` c 
WHERE c.`id_mysql_digest` IN (
SELECT t.id 
FROM `mysql_digest` t 
WHERE t.id IS NOT NULL) 
ORDER BY c.`id` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+----------------------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys              | key        | key_len | ref             | rows   | Extra                                                     |
+----+-------------+-------+-------+----------------------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY                    | uk_digest  |     258 | NULL            | 192325 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | c     | ref   | id_mysql_digest,idx_digest | idx_digest |       4 | pmacontrol.t.id |      1 | Using index                                               |
+----+-------------+-------+-------+----------------------------+------------+---------+-----------------+--------+-----------------------------------------------------------+
```

## MXP-103 - case `subquery` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `mysql_database__mysql_digest.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `471977`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `168`
- Returned rows: `250`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `mysql_database__mysql_digest` c 
WHERE c.`id_mysql_server` IN (
SELECT t.id 
FROM `mysql_server` t 
WHERE t.id IS NOT NULL) 
ORDER BY c.`id` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
| id | select_type | table | type  | possible_keys   | key             | key_len | ref             | rows | Extra                                                     |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
|  1 | PRIMARY     | t     | index | PRIMARY         | is_deleted      |       1 | NULL            |  124 | Using where; Using index; Using temporary; Using filesort |
|  1 | PRIMARY     | c     | ref   | id_mysql_server | id_mysql_server |       4 | pmacontrol.t.id | 2458 | Using index                                               |
+----+-------------+-------+-------+-----------------+-----------------+---------+-----------------+------+-----------------------------------------------------------+
```

## MXP-104 - case `subquery` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ssh_tunnel.id_maxscale_server -> maxscale_server.id`
- Child table rows (estimate): `522`
- Target table rows (estimate): `37530`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `5`
- Returned rows: `0`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_maxscale_server` 
FROM `ssh_tunnel` c 
WHERE c.`id_maxscale_server` IN (
SELECT t.id 
FROM `maxscale_server` t 
WHERE t.id IS NOT NULL) 
ORDER BY c.`id` DESC 
LIMIT 250
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `2`
- Expected signature: `SUBQUERY`
- Signature match: `no`
- Fail reason: `none`

```text
+----+-------------+-------+--------+--------------------+--------------------+---------+---------------------------------------+------+------------------------------------------+
| id | select_type | table | type   | possible_keys      | key                | key_len | ref                                   | rows | Extra                                    |
+----+-------------+-------+--------+--------------------+--------------------+---------+---------------------------------------+------+------------------------------------------+
|  1 | PRIMARY     | c     | range  | id_maxscale_server | id_maxscale_server |       5 | NULL                                  |    1 | Using where; Using index; Using filesort |
|  1 | PRIMARY     | t     | eq_ref | PRIMARY            | PRIMARY            |      11 | pmacontrol.c.id_maxscale_server,const |    1 | Using index                              |
+----+-------------+-------+--------+--------------------+--------------------+---------+---------------------------------------+------+------------------------------------------+
```

## MXP-105 - case `union` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ssh_tunnel.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `522`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `4`
- Returned rows: `10`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `ssh_tunnel` c 
WHERE c.`id_mysql_server` IS NOT NULL 
UNION 
SELECT c2.`id` AS row_id, c2.`id_mysql_server` 
FROM `ssh_tunnel` c2 
WHERE c2.`id_mysql_server` IS NOT NULL 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------+------------+-------+---------------+------------+---------+------+------+--------------------------+
| id   | select_type  | table      | type  | possible_keys | key        | key_len | ref  | rows | Extra                    |
+------+--------------+------------+-------+---------------+------------+---------+------+------+--------------------------+
|    1 | PRIMARY      | c          | range | idx_server    | idx_server |       5 | NULL |   10 | Using where; Using index |
|    2 | UNION        | c2         | range | idx_server    | idx_server |       5 | NULL |   10 | Using where; Using index |
| NULL | UNION RESULT | <union1,2> | ALL   | NULL          | NULL       | NULL    | NULL | NULL |                          |
+------+--------------+------------+-------+---------------+------------+---------+------+------+--------------------------+
```

## MXP-106 - case `union` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_date_by_server.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `254101411`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30022`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `ts_date_by_server` c 
WHERE c.`id_mysql_server` IS NOT NULL 
UNION 
SELECT c2.`id` AS row_id, c2.`id_mysql_server` 
FROM `ts_date_by_server` c2 
WHERE c2.`id_mysql_server` IS NOT NULL 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------+------------+-------+-----------------+-----------------+---------+------+-----------+--------------------------+
| id   | select_type  | table      | type  | possible_keys   | key             | key_len | ref  | rows      | Extra                    |
+------+--------------+------------+-------+-----------------+-----------------+---------+------+-----------+--------------------------+
|    1 | PRIMARY      | c          | index | id_mysql_server | id_mysql_server |      13 | NULL | 254184630 | Using where; Using index |
|    2 | UNION        | c2         | index | id_mysql_server | id_mysql_server |      13 | NULL | 254184630 | Using where; Using index |
| NULL | UNION RESULT | <union1,2> | ALL   | NULL            | NULL            | NULL    | NULL | NULL      |                          |
+------+--------------+------------+-------+-----------------+-----------------+---------+------+-----------+--------------------------+
```

## MXP-107 - case `union` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_max_date.id_mysql_server -> mysql_server.id`
- Child table rows (estimate): `5455`
- Target table rows (estimate): `1282`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `yes`
- Execution time (ms): `8`
- Returned rows: `300`
- Fail reason: `none`

### SQL

```sql
SELECT c.`id` AS row_id, c.`id_mysql_server` 
FROM `ts_max_date` c 
WHERE c.`id_mysql_server` IS NOT NULL 
UNION 
SELECT c2.`id` AS row_id, c2.`id_mysql_server` 
FROM `ts_max_date` c2 
WHERE c2.`id_mysql_server` IS NOT NULL 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------+------------+-------+-----------------------------------------------------+-------------------+---------+------+------+--------------------------+
| id   | select_type  | table      | type  | possible_keys                                       | key               | key_len | ref  | rows | Extra                    |
+------+--------------+------------+-------+-----------------------------------------------------+-------------------+---------+------+------+--------------------------+
|    1 | PRIMARY      | c          | index | id_mysql_server_2,id_mysql_server,id_mysql_server_4 | id_mysql_server_2 |       8 | NULL | 5455 | Using where; Using index |
|    2 | UNION        | c2         | index | id_mysql_server_2,id_mysql_server,id_mysql_server_4 | id_mysql_server_2 |       8 | NULL | 5455 | Using where; Using index |
| NULL | UNION RESULT | <union1,2> | ALL   | NULL                                                | NULL              | NULL    | NULL | NULL |                          |
+------+--------------+------------+-------+-----------------------------------------------------+-------------------+---------+------+------+--------------------------+
```

## MXP-108 - case `union` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Relation guessed: `ts_mysql_digest_stat.id_mysql_database__mysql_digest -> mysql_database__mysql_digest.id`
- Child table rows (estimate): `2639967210`
- Target table rows (estimate): `471977`
- Skipped: `no`

### Real Query Execution (`db_select`)

- Success: `no`
- Execution time (ms): `30034`
- Returned rows: `0`
- Fail reason: `guard [execution time reached]`

### SQL

```sql
SELECT c.`id_mysql_database__mysql_digest` AS row_id, c.`id_mysql_database__mysql_digest` 
FROM `ts_mysql_digest_stat` c 
WHERE c.`id_mysql_database__mysql_digest` IS NOT NULL 
UNION 
SELECT c2.`id_mysql_database__mysql_digest` AS row_id, c2.`id_mysql_database__mysql_digest` 
FROM `ts_mysql_digest_stat` c2 
WHERE c2.`id_mysql_database__mysql_digest` IS NOT NULL 
LIMIT 300
```

### EXPLAIN (`db_explain_table`)

- Success: `yes`
- Execution time (ms): `2`
- Returned rows: `3`
- Expected signature: `UNION`
- Signature match: `yes`
- Fail reason: `none`

```text
+------+--------------+------------+-------+-------------------+-------------------+---------+------+------------+--------------------------+
| id   | select_type  | table      | type  | possible_keys     | key               | key_len | ref  | rows       | Extra                    |
+------+--------------+------------+-------+-------------------+-------------------+---------+------+------------+--------------------------+
|    1 | PRIMARY      | c          | index | idx_digest_schema | idx_digest_schema |       9 | NULL | 2640335008 | Using where; Using index |
|    2 | UNION        | c2         | index | idx_digest_schema | idx_digest_schema |       9 | NULL | 2640335008 | Using where; Using index |
| NULL | UNION RESULT | <union1,2> | ALL   | NULL              | NULL              | NULL    | NULL | NULL       |                          |
+------+--------------+------------+-------+-------------------+-------------------+---------+------+------------+--------------------------+
```

