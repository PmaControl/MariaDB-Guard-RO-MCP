#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/QueryLogger.php';

Env::load(__DIR__ . '/../.env');

$endpoint = (string) ($_ENV['MCP_ENDPOINT'] ?? 'http://127.0.0.1:13306/mcp');
$token = (string) ($_ENV['MCP_TOKEN'] ?? '');
$output = __DIR__ . '/../docs/mcp_test_queries_report.md';

$queries = [
    [
        'id' => 'Q1',
        'label' => 'Heavy derived (expected guard)',
        'tool' => 'db_select',
        'explain_table' => <<<'TXT'
+------+-------------+------------+-------+--------------------+------------+---------+------------------+---------+----------------------------------------------+
| id   | select_type | table      | type  | possible_keys      | key        | key_len | ref              | rows    | Extra                                        |
+------+-------------+------------+-------+--------------------+------------+---------+------------------+---------+----------------------------------------------+
|    1 | PRIMARY     | ms         | range | PRIMARY,is_deleted | is_deleted | 1       | NULL             | 102     | Using where; Using temporary; Using filesort |
|    1 | PRIMARY     | <derived2> | ref   | key1               | key1       | 5       | pmacontrol.ms.id | 4699    | Using where                                  |
|    1 | PRIMARY     | <derived3> | ref   | key0               | key0       | 5       | pmacontrol.ms.id | 10      |                                              |
|    3 | DERIVED     | ad2        | ALL   | id_mysql_server    | NULL       | NULL    | NULL             | 1165386 | Using where; Using temporary; Using filesort |
|    2 | DERIVED     | ad         | ALL   | id_mysql_server    | NULL       | NULL    | NULL             | 1165386 | Using where; Using temporary                 |
+------+-------------+------------+-------+--------------------+------------+---------+------------------+---------+----------------------------------------------+
TXT,
        'sql' => <<<'SQL'
SELECT
    ms.id,
    ms.name,
    ms.ip,
    ms.port AS mysql_port,
    agg.total_alias,
    agg.ssh_alias,
    ROUND(agg.ssh_alias * 100.0 / NULLIF(agg.total_alias, 0), 2) AS ssh_ratio_pct,
    f.id AS latest_alias_id,
    f.port AS latest_alias_port
FROM (
    SELECT
        ad.id,
        ad.id_mysql_server,
        ad.port,
        ad.is_from_ssh,
        ROW_NUMBER() OVER (PARTITION BY ad.id_mysql_server ORDER BY ad.id DESC) AS rn
    FROM alias_dns ad
    WHERE ad.id_mysql_server IS NOT NULL
      AND ad.port BETWEEN 1 AND 65535
) f
JOIN (
    SELECT
        ad2.id_mysql_server,
        COUNT(*) AS total_alias,
        SUM(CASE WHEN ad2.is_from_ssh = 1 THEN 1 ELSE 0 END) AS ssh_alias
    FROM alias_dns ad2
    WHERE ad2.id_mysql_server IS NOT NULL
      AND ad2.port BETWEEN 1 AND 65535
    GROUP BY ad2.id_mysql_server
) agg ON agg.id_mysql_server = f.id_mysql_server
JOIN mysql_server ms ON ms.id = f.id_mysql_server
WHERE f.rn = 1
  AND ms.is_deleted = 0
ORDER BY agg.total_alias DESC, ms.id DESC
LIMIT 50
SQL,
    ],
    [
        'id' => 'Q2',
        'label' => 'Big tables + indexes',
        'tool' => 'db_select',
        'sql' => <<<'SQL'
SELECT
    b.table_name,
    b.table_rows,
    COALESCE(i.index_count, 0) AS index_count,
    LEFT(COALESCE(i.all_indexes, ''), 1000) AS indexes_preview
FROM (
    SELECT
        t.table_name,
        COALESCE(t.table_rows, 0) AS table_rows
    FROM information_schema.tables t
    WHERE t.table_schema = 'pmacontrol'
      AND COALESCE(t.table_rows, 0) >= 100000
) b
LEFT JOIN (
    SELECT
        x.table_name,
        COUNT(*) AS index_count,
        GROUP_CONCAT(x.indexed_cols SEPARATOR ' | ') AS all_indexes
    FROM (
        SELECT
            s.table_name,
            s.index_name,
            GROUP_CONCAT(DISTINCT s.column_name ORDER BY s.seq_in_index SEPARATOR ',') AS indexed_cols
        FROM information_schema.statistics s
        WHERE s.table_schema = 'pmacontrol'
        GROUP BY s.table_name, s.index_name
    ) x
    GROUP BY x.table_name
) i ON i.table_name = b.table_name
ORDER BY b.table_rows DESC
LIMIT 20
SQL,
    ],
    [
        'id' => 'Q3',
        'label' => 'Window + filtered join',
        'tool' => 'db_select',
        'sql' => <<<'SQL'
SELECT
    ad.id,
    ad.id_mysql_server,
    ms.name,
    ms.ip,
    ad.port,
    ad.is_from_ssh,
    COUNT(*) OVER (PARTITION BY ad.id_mysql_server) AS total_alias_for_server,
    ROW_NUMBER() OVER (PARTITION BY ad.id_mysql_server ORDER BY ad.id DESC) AS rn
FROM alias_dns ad
JOIN mysql_server ms ON ms.id = ad.id_mysql_server
WHERE ad.id_mysql_server = 113
  AND ms.is_deleted = 0
ORDER BY ad.id DESC
LIMIT 200
SQL,
    ],
    [
        'id' => 'Q4',
        'label' => 'Explain window + filtered join',
        'tool' => 'db_explain',
        'sql' => <<<'SQL'
SELECT
    ad.id,
    ad.id_mysql_server,
    ms.name,
    ms.ip,
    ad.port,
    ad.is_from_ssh,
    COUNT(*) OVER (PARTITION BY ad.id_mysql_server) AS total_alias_for_server,
    ROW_NUMBER() OVER (PARTITION BY ad.id_mysql_server ORDER BY ad.id DESC) AS rn
FROM alias_dns ad
JOIN mysql_server ms ON ms.id = ad.id_mysql_server
WHERE ad.id_mysql_server = 113
  AND ms.is_deleted = 0
ORDER BY ad.id DESC
LIMIT 200
SQL,
    ],
    [
        'id' => 'Q5',
        'label' => 'Non-recursive CTE',
        'tool' => 'db_select',
        'sql' => "WITH u AS (SELECT id FROM users WHERE status = 'ACTIVE') SELECT id FROM u WHERE id > 0",
    ],
    [
        'id' => 'Q6',
        'label' => 'Recursive CTE (expected guard)',
        'tool' => 'db_select',
        'sql' => 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM t WHERE n < 3) SELECT * FROM t',
    ],
];

$source = endpointSource($endpoint);
$lines = [];
$lines[] = '# MCP Test Queries Report';
$lines[] = '';
$lines[] = '- Generated at: `' . gmdate('c') . '`';
$lines[] = '- Endpoint: `' . $endpoint . '`';
$lines[] = '- Source (ip:port): `' . $source . '`';
$lines[] = '';

$rpcId = 1000;
foreach ($queries as $case) {
    $rpcId++;
    $started = microtime(true);
    $response = callMcp($endpoint, $token, $rpcId, $case['tool'], $case['sql']);
    $durationMs = (int) round((microtime(true) - $started) * 1000);

    $isSuccess = !isset($response['error']);
    $guardReason = $isSuccess ? '' : (string)($response['error']['message'] ?? 'Unknown error');
    $rowCount = 0;
    $explainText = '';
    $explainTableText = (string)($case['explain_table'] ?? '');

    if ($isSuccess) {
        $structured = $response['result']['structuredContent'] ?? [];
        $rowCount = (int)($structured['rowCount'] ?? 0);

        if ($case['tool'] === 'db_explain') {
            $explainText = summarizeExplain($structured);
        } elseif ($case['tool'] === 'db_select') {
            $rpcId++;
            $explainResp = callMcp($endpoint, $token, $rpcId, 'db_explain', $case['sql']);
            if (!isset($explainResp['error'])) {
                $explainText = summarizeExplain($explainResp['result']['structuredContent'] ?? []);
            } else {
                $explainText = 'EXPLAIN error: ' . (string)($explainResp['error']['message'] ?? 'unknown');
            }
        }
    }

    $lines[] = '## ' . $case['id'] . ' - ' . $case['label'];
    $lines[] = '';
    $lines[] = '- Source (ip:port): `' . $source . '`';
    $lines[] = '- Tool: `' . $case['tool'] . '`';
    $lines[] = '- Success: `' . ($isSuccess ? 'yes' : 'no') . '`';
    $lines[] = '- Processing time (ms): `' . (string)$durationMs . '`';
    $lines[] = '- Returned rows: `' . (string)$rowCount . '`';
    $lines[] = '- Guard/Error reason: `' . ($guardReason !== '' ? $guardReason : 'none') . '`';
    $lines[] = '';
    $lines[] = '### Formatted SQL';
    $lines[] = '';
    $lines[] = '```sql';
    $lines[] = QueryLogger::formatSql($case['sql']);
    $lines[] = '```';
    $lines[] = '';
    $lines[] = '### Explain';
    $lines[] = '';
    $lines[] = '```json';
    $lines[] = $explainText !== '' ? $explainText : '{}';
    $lines[] = '```';
    $lines[] = '';
    $lines[] = '### Explain (MariaDB table format)';
    $lines[] = '';
    $lines[] = '```text';
    $lines[] = $explainTableText !== '' ? $explainTableText : '(not available)';
    $lines[] = '```';
    $lines[] = '';
}

file_put_contents($output, implode(PHP_EOL, $lines) . PHP_EOL);
echo "Report generated: {$output}" . PHP_EOL;

function endpointSource(string $endpoint): string
{
    $parts = parse_url($endpoint);
    $host = (string)($parts['host'] ?? 'unknown');
    $port = (int)($parts['port'] ?? 80);
    return $host . ':' . $port;
}

function callMcp(string $endpoint, string $token, int $id, string $tool, string $sql): array
{
    $payload = [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool,
            'arguments' => ['sql' => $sql],
        ],
    ];

    $headers = ['Content-Type: application/json'];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($endpoint);
    if ($ch === false) {
        return ['error' => ['message' => 'Cannot init curl']];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 45,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw) || $raw === '') {
        return ['error' => ['message' => $err !== '' ? $err : 'Empty response']];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['error' => ['message' => 'Invalid JSON response']];
    }

    return $decoded;
}

function summarizeExplain(array $structured): string
{
    $rows = $structured['rows'] ?? [];
    if (!is_array($rows) || count($rows) === 0) {
        return '{}';
    }

    if (isset($rows[0]['EXPLAIN']) && is_string($rows[0]['EXPLAIN'])) {
        $decoded = json_decode($rows[0]['EXPLAIN'], true);
        if (is_array($decoded)) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        return $rows[0]['EXPLAIN'];
    }

    return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
