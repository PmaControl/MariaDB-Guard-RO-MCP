# MCP Test Queries Report

- Generated at: `2026-03-07T07:09:58+00:00`
- Endpoint: `http://127.0.0.1:13306/mcp`
- Source (ip:port): `127.0.0.1:13306`

## Q1 - Heavy derived (expected guard)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_select`
- Success: `no`
- Processing time (ms): `5`
- Returned rows: `0`
- Guard/Error reason: `db_select refuses WHERE full scan on large table 'ad2' (rows=1165386 > 30000).`

### Formatted SQL

```sql
SELECT ms.id, ms.name, ms.ip, ms.port AS mysql_port, agg.total_alias, agg.ssh_alias, ROUND(agg.ssh_alias * 100.0 / NULLIF(agg.total_alias, 0), 2) AS ssh_ratio_pct, f.id AS latest_alias_id, f.port AS latest_alias_port 
FROM ( 
SELECT ad.id, ad.id_mysql_server, ad.port, ad.is_from_ssh, ROW_NUMBER() OVER (PARTITION BY ad.id_mysql_server 
ORDER BY ad.id DESC) AS rn 
FROM alias_dns ad 
WHERE ad.id_mysql_server IS NOT NULL AND ad.port BETWEEN 1 AND 65535 ) f 
JOIN ( 
SELECT ad2.id_mysql_server, COUNT(*) AS total_alias, SUM(CASE WHEN ad2.is_from_ssh = 1 THEN 1 ELSE 0 END) AS ssh_alias 
FROM alias_dns ad2 
WHERE ad2.id_mysql_server IS NOT NULL AND ad2.port BETWEEN 1 AND 65535 
GROUP BY ad2.id_mysql_server ) agg ON agg.id_mysql_server = f.id_mysql_server 
JOIN mysql_server ms ON ms.id = f.id_mysql_server 
WHERE f.rn = 1 AND ms.is_deleted = 0 
ORDER BY agg.total_alias DESC, ms.id DESC 
LIMIT 50
```

### Explain

```json
{}
```

## Q2 - Big tables + indexes

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_select`
- Success: `yes`
- Processing time (ms): `28`
- Returned rows: `20`
- Guard/Error reason: `none`

### Formatted SQL

```sql
SELECT b.table_name, b.table_rows, COALESCE(i.index_count, 0) AS index_count, LEFT(COALESCE(i.all_indexes, ''), 1000) AS indexes_preview 
FROM ( 
SELECT t.table_name, COALESCE(t.table_rows, 0) AS table_rows 
FROM information_schema.tables t 
WHERE t.table_schema = 'pmacontrol' AND COALESCE(t.table_rows, 0) >= 100000 ) b 
LEFT 
JOIN ( 
SELECT x.table_name, COUNT(*) AS index_count, GROUP_CONCAT(x.indexed_cols SEPARATOR ' | ') AS all_indexes 
FROM ( 
SELECT s.table_name, s.index_name, GROUP_CONCAT(DISTINCT s.column_name 
ORDER BY s.seq_in_index SEPARATOR ',') AS indexed_cols 
FROM information_schema.statistics s 
WHERE s.table_schema = 'pmacontrol' 
GROUP BY s.table_name, s.index_name ) x 
GROUP BY x.table_name ) i ON i.table_name = b.table_name 
ORDER BY b.table_rows DESC 
LIMIT 20
```

### Explain

```json
{
    "query_block": {
        "select_id": 1,
        "filesort": {
            "sort_key": "coalesce(t.TABLE_ROWS,0) desc",
            "temporary_table": {
                "nested_loop": [
                    {
                        "table": {
                            "table_name": "t",
                            "access_type": "ALL",
                            "key": "TABLE_SCHEMA",
                            "attached_condition": "t.TABLE_SCHEMA = 'pmacontrol' and coalesce(t.TABLE_ROWS,0) >= 100000",
                            "open_full_table": true,
                            "scanned_databases": 1
                        }
                    },
                    {
                        "block-nl-join": {
                            "table": {
                                "table_name": "<derived3>",
                                "access_type": "ALL",
                                "rows": 2,
                                "filtered": 100
                            },
                            "buffer_type": "flat",
                            "buffer_size": "4Kb",
                            "join_type": "BNL",
                            "attached_condition": "trigcond(i.`table_name` = t.`TABLE_NAME`)",
                            "materialized": {
                                "query_block": {
                                    "select_id": 3,
                                    "nested_loop": [
                                        {
                                            "read_sorted_file": {
                                                "filesort": {
                                                    "sort_key": "x.`table_name`",
                                                    "table": {
                                                        "table_name": "<derived4>",
                                                        "access_type": "ALL",
                                                        "rows": 2,
                                                        "filtered": 100,
                                                        "materialized": {
                                                            "query_block": {
                                                                "select_id": 4,
                                                                "nested_loop": [
                                                                    {
                                                                        "read_sorted_file": {
                                                                            "filesort": {
                                                                                "sort_key": "s.`TABLE_NAME`, s.INDEX_NAME",
                                                                                "table": {
                                                                                    "table_name": "s",
                                                                                    "access_type": "ALL",
                                                                                    "key": "TABLE_SCHEMA",
                                                                                    "attached_condition": "s.TABLE_SCHEMA = 'pmacontrol'",
                                                                                    "open_frm_only": true,
                                                                                    "scanned_databases": 1
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                ]
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    ]
                                }
                            }
                        }
                    }
                ]
            }
        }
    }
}
```

## Q3 - Window + filtered join

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_select`
- Success: `yes`
- Processing time (ms): `22`
- Returned rows: `4`
- Guard/Error reason: `none`

### Formatted SQL

```sql
SELECT ad.id, ad.id_mysql_server, ms.name, ms.ip, ad.port, ad.is_from_ssh, COUNT(*) OVER (PARTITION BY ad.id_mysql_server) AS total_alias_for_server, ROW_NUMBER() OVER (PARTITION BY ad.id_mysql_server 
ORDER BY ad.id DESC) AS rn 
FROM alias_dns ad 
JOIN mysql_server ms ON ms.id = ad.id_mysql_server 
WHERE ad.id_mysql_server = 113 AND ms.is_deleted = 0 
ORDER BY ad.id DESC 
LIMIT 200
```

### Explain

```json
{
    "query_block": {
        "select_id": 1,
        "filesort": {
            "sort_key": "ad.`id` desc",
            "window_functions_computation": {
                "sorts": [
                    {
                        "filesort": {
                            "sort_key": "ad.id_mysql_server, ad.`id` desc"
                        }
                    }
                ],
                "temporary_table": {
                    "nested_loop": [
                        {
                            "table": {
                                "table_name": "ms",
                                "partitions": [
                                    "pn"
                                ],
                                "access_type": "const",
                                "possible_keys": [
                                    "PRIMARY",
                                    "is_deleted"
                                ],
                                "key": "PRIMARY",
                                "key_length": "11",
                                "used_key_parts": [
                                    "id",
                                    "row_end"
                                ],
                                "ref": [
                                    "const",
                                    "const"
                                ],
                                "rows": 1,
                                "filtered": 100
                            }
                        },
                        {
                            "table": {
                                "table_name": "ad",
                                "access_type": "ref",
                                "possible_keys": [
                                    "id_mysql_server"
                                ],
                                "key": "id_mysql_server",
                                "key_length": "5",
                                "used_key_parts": [
                                    "id_mysql_server"
                                ],
                                "ref": [
                                    "const"
                                ],
                                "rows": 31680,
                                "filtered": 100,
                                "index_condition": "ad.row_end = TIMESTAMP'2038-01-19 04:14:07.999999'"
                            }
                        }
                    ]
                }
            }
        }
    }
}
```

## Q4 - Explain window + filtered join

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_explain`
- Success: `yes`
- Processing time (ms): `5`
- Returned rows: `1`
- Guard/Error reason: `none`

### Formatted SQL

```sql
SELECT ad.id, ad.id_mysql_server, ms.name, ms.ip, ad.port, ad.is_from_ssh, COUNT(*) OVER (PARTITION BY ad.id_mysql_server) AS total_alias_for_server, ROW_NUMBER() OVER (PARTITION BY ad.id_mysql_server 
ORDER BY ad.id DESC) AS rn 
FROM alias_dns ad 
JOIN mysql_server ms ON ms.id = ad.id_mysql_server 
WHERE ad.id_mysql_server = 113 AND ms.is_deleted = 0 
ORDER BY ad.id DESC 
LIMIT 200
```

### Explain

```json
{
    "query_block": {
        "select_id": 1,
        "filesort": {
            "sort_key": "ad.`id` desc",
            "window_functions_computation": {
                "sorts": [
                    {
                        "filesort": {
                            "sort_key": "ad.id_mysql_server, ad.`id` desc"
                        }
                    }
                ],
                "temporary_table": {
                    "nested_loop": [
                        {
                            "table": {
                                "table_name": "ms",
                                "partitions": [
                                    "pn"
                                ],
                                "access_type": "const",
                                "possible_keys": [
                                    "PRIMARY",
                                    "is_deleted"
                                ],
                                "key": "PRIMARY",
                                "key_length": "11",
                                "used_key_parts": [
                                    "id",
                                    "row_end"
                                ],
                                "ref": [
                                    "const",
                                    "const"
                                ],
                                "rows": 1,
                                "filtered": 100
                            }
                        },
                        {
                            "table": {
                                "table_name": "ad",
                                "access_type": "ref",
                                "possible_keys": [
                                    "id_mysql_server"
                                ],
                                "key": "id_mysql_server",
                                "key_length": "5",
                                "used_key_parts": [
                                    "id_mysql_server"
                                ],
                                "ref": [
                                    "const"
                                ],
                                "rows": 31680,
                                "filtered": 100,
                                "index_condition": "ad.row_end = TIMESTAMP'2038-01-19 04:14:07.999999'"
                            }
                        }
                    ]
                }
            }
        }
    }
}
```

## Q5 - Non-recursive CTE

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_select`
- Success: `no`
- Processing time (ms): `2`
- Returned rows: `0`
- Guard/Error reason: `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'pmacontrol.users' doesn't exist`

### Formatted SQL

```sql
WITH u AS (
SELECT id 
FROM users 
WHERE status = 'ACTIVE') 
SELECT id 
FROM u 
WHERE id > 0
```

### Explain

```json
{}
```

## Q6 - Recursive CTE (expected guard)

- Source (ip:port): `127.0.0.1:13306`
- Tool: `db_select`
- Success: `no`
- Processing time (ms): `1`
- Returned rows: `0`
- Guard/Error reason: `WITH RECURSIVE is not allowed`

### Formatted SQL

```sql
WITH RECURSIVE t(n) AS (
SELECT 1 
UNION ALL 
SELECT n+1 
FROM t 
WHERE n < 3) 
SELECT * 
FROM t
```

### Explain

```json
{}
```

