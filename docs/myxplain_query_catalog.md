# MYXPLAIN Query Catalog (MCP / db_explain)

- Source cases: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`
- Pattern rule used: `YYYYYY.id_XXXXXX -> XXXXXX.id`
- Endpoint source: `127.0.0.1:13306`
- Total MYXPLAIN cases: `27`
- Variants per case: `4`
- Total generated queries: `108`

## How To Replay

Use each SQL with MCP tool `db_explain`.

## MXP-001 - case `ALL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-002 - case `ALL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-003 - case `ALL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-004 - case `ALL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-005 - case `CONST` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-006 - case `CONST` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-007 - case `CONST` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-008 - case `CONST` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-009 - case `EQ_REF` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-010 - case `EQ_REF` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-011 - case `EQ_REF` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-012 - case `EQ_REF` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-013 - case `FILESORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-014 - case `FILESORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-015 - case `FILESORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-016 - case `FILESORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-017 - case `FULLTEXT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-018 - case `FULLTEXT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-019 - case `FULLTEXT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-020 - case `FULLTEXT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-021 - case `GROUPBYNOSORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-022 - case `GROUPBYNOSORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-023 - case `GROUPBYNOSORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-024 - case `GROUPBYNOSORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-025 - case `IMPOSSIBLE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-026 - case `IMPOSSIBLE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-027 - case `IMPOSSIBLE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-028 - case `IMPOSSIBLE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-029 - case `INDEX` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-030 - case `INDEX` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-031 - case `INDEX` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-032 - case `INDEX` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-033 - case `INDEX_MERGE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-034 - case `INDEX_MERGE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-035 - case `INDEX_MERGE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-036 - case `INDEX_MERGE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-037 - case `INDEX_SUBQUERY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-038 - case `INDEX_SUBQUERY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-039 - case `INDEX_SUBQUERY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-040 - case `INDEX_SUBQUERY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-041 - case `NULL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-042 - case `NULL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-043 - case `NULL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-044 - case `NULL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-045 - case `RANGE` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-046 - case `RANGE` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-047 - case `RANGE` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-048 - case `RANGE` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-049 - case `REF` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-050 - case `REF` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-051 - case `REF` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-052 - case `REF` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-053 - case `REF_OR_NULL` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-054 - case `REF_OR_NULL` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-055 - case `REF_OR_NULL` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-056 - case `REF_OR_NULL` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-057 - case `SYSTEM` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-058 - case `SYSTEM` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-059 - case `SYSTEM` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-060 - case `SYSTEM` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-061 - case `UNIQUE_SUBQUERY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-062 - case `UNIQUE_SUBQUERY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-063 - case `UNIQUE_SUBQUERY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-064 - case `UNIQUE_SUBQUERY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-065 - case `USINGFSORT` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-066 - case `USINGFSORT` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-067 - case `USINGFSORT` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-068 - case `USINGFSORT` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-069 - case `USINGIDXGROUPBY` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-070 - case `USINGIDXGROUPBY` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-071 - case `USINGIDXGROUPBY` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-072 - case `USINGIDXGROUPBY` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-073 - case `USINGINDEX` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-074 - case `USINGINDEX` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-075 - case `USINGINDEX` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-076 - case `USINGINDEX` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-077 - case `USINGTEMP` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-078 - case `USINGTEMP` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-079 - case `USINGTEMP` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-080 - case `USINGTEMP` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-081 - case `dependent_subquery` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-082 - case `dependent_subquery` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-083 - case `dependent_subquery` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-084 - case `dependent_subquery` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-085 - case `dependent_union` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-086 - case `dependent_union` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-087 - case `dependent_union` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-088 - case `dependent_union` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-089 - case `derived` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-090 - case `derived` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-091 - case `derived` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-092 - case `derived` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-093 - case `keylen_1` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-094 - case `keylen_1` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-095 - case `keylen_1` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-096 - case `keylen_1` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-097 - case `keylen_2` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-098 - case `keylen_2` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-099 - case `keylen_2` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-100 - case `keylen_2` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-101 - case `subquery` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-102 - case `subquery` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-103 - case `subquery` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-104 - case `subquery` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

## MXP-105 - case `union` (v1)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.id, c.`id_mysql_server`, t.id AS target_id 
FROM `alias_dns` c 
JOIN `mysql_server` t ON t.id = c.`id_mysql_server` 
WHERE c.`id_mysql_server` IS NOT NULL 
ORDER BY c.id DESC 
LIMIT 300
```

## MXP-106 - case `union` (v2)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT c.`id_mysql_server`, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL 
GROUP BY c.`id_mysql_server` 
ORDER BY cnt DESC 
LIMIT 200
```

## MXP-107 - case `union` (v3)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON 
SELECT x.id, x.`id_mysql_server`, t.id AS target_id 
FROM (
SELECT id, `id_mysql_server` 
FROM `alias_dns` 
WHERE `id_mysql_server` IS NOT NULL 
ORDER BY id DESC 
LIMIT 1000) x 
JOIN `mysql_server` t ON t.id = x.`id_mysql_server` 
ORDER BY x.id DESC 
LIMIT 200
```

## MXP-108 - case `union` (v4)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Relation guessed: `alias_dns.id_mysql_server -> mysql_server.id`

```sql
EXPLAIN FORMAT=JSON WITH ranked AS (
SELECT c.id, c.`id_mysql_server`, ROW_NUMBER() OVER (PARTITION BY c.`id_mysql_server` 
ORDER BY c.id DESC) AS rn 
FROM `alias_dns` c 
WHERE c.`id_mysql_server` IS NOT NULL) 
SELECT r.id, r.`id_mysql_server`, t.id AS target_id, r.rn 
FROM ranked r 
JOIN `mysql_server` t ON t.id = r.`id_mysql_server` 
WHERE r.rn <= 5 
ORDER BY r.id DESC 
LIMIT 250
```

