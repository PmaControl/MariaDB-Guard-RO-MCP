#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/QueryLogger.php';

Env::load(__DIR__ . '/../.env');

$schema = (string) ($_ENV['DB_NAME'] ?? 'pmacontrol');
$endpoint = (string) ($_ENV['MCP_ENDPOINT'] ?? 'http://127.0.0.1:13306/mcp');
$token = (string) ($_ENV['MCP_TOKEN'] ?? '');
$outputMd = __DIR__ . '/../docs/myxplain_query_catalog.md';
$outputJson = __DIR__ . '/../docs/myxplain_query_catalog.json';

$cases = [
    'ALL','CONST','EQ_REF','FILESORT','FULLTEXT','GROUPBYNOSORT','IMPOSSIBLE','INDEX','INDEX_MERGE','INDEX_SUBQUERY','NULL','RANGE','REF','REF_OR_NULL','SYSTEM','UNIQUE_SUBQUERY','USINGFSORT','USINGIDXGROUPBY','USINGINDEX','USINGTEMP','dependent_subquery','dependent_union','derived','keylen_1','keylen_2','subquery','union',
];

$pairs = discoverPairs($schema);
if (count($pairs) === 0) {
    $pairs = [[
        'child_table' => 'alias_dns',
        'fk_col' => 'id_mysql_server',
        'target_table' => 'mysql_server',
    ]];
}

$catalog = [];
$idx = 1;
$rpcId = 5000;
foreach ($cases as $caseIndex => $case) {
    for ($v = 1; $v <= 4; $v++) {
        $pair = $pairs[($caseIndex * 4 + $v - 1) % count($pairs)];
        $sql = buildQuery($pair, $v);

        $started = microtime(true);
        $resp = callMcp($endpoint, $token, ++$rpcId, 'db_explain_table', ['sql' => $sql]);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $ok = !isset($resp['error']);
        $reason = $ok ? '' : (string)($resp['error']['message'] ?? 'Unknown error');
        $rowCount = 0;
        $tableText = '';
        $rawRows = [];

        if ($ok) {
            $sc = $resp['result']['structuredContent'] ?? [];
            if (is_array($sc)) {
                $rowCount = (int)($sc['rowCount'] ?? 0);
                $tableText = (string)($sc['tableText'] ?? '');
                $rawRows = is_array($sc['rows'] ?? null) ? $sc['rows'] : [];
            }
        }

        $catalog[] = [
            'id' => sprintf('MXP-%03d', $idx),
            'myxplain_case' => $case,
            'variant' => $v,
            'source' => endpointSource($endpoint),
            'tool' => 'db_explain_table',
            'child_table' => $pair['child_table'],
            'fk_col' => $pair['fk_col'],
            'target_table' => $pair['target_table'],
            'sql' => $sql,
            'success' => $ok,
            'error_reason' => $reason,
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'explain_table' => $tableText,
            'explain_rows' => $rawRows,
        ];
        $idx++;
    }
}

file_put_contents($outputJson, json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);

$total = count($catalog);
$okCount = count(array_filter($catalog, static fn(array $q): bool => (bool)$q['success']));
$koCount = $total - $okCount;

$md = [];
$md[] = '# MYXPLAIN Query Catalog (MCP / db_explain_table)';
$md[] = '';
$md[] = '- Source cases: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`';
$md[] = '- Pattern rule used: `YYYYYY.id_XXXXXX -> XXXXXX.id`';
$md[] = '- Endpoint source: `' . endpointSource($endpoint) . '`';
$md[] = '- Total MYXPLAIN cases: `' . count($cases) . '`';
$md[] = '- Variants per case: `4`';
$md[] = '- Total generated queries: `' . $total . '`';
$md[] = '- Pass: `' . $okCount . '`';
$md[] = '- Fail: `' . $koCount . '`';
$md[] = '';
$md[] = '## Replay Command';
$md[] = '';
$md[] = '```bash';
$md[] = 'php scripts/generate_myxplain_query_catalog.php';
$md[] = '```';
$md[] = '';

foreach ($catalog as $q) {
    $md[] = '## ' . $q['id'] . ' - case `' . $q['myxplain_case'] . '` (v' . $q['variant'] . ')';
    $md[] = '';
    $md[] = '- Source (ip:port): `' . $q['source'] . '`';
    $md[] = '- Tool: `db_explain_table`';
    $md[] = '- Relation guessed: `' . $q['child_table'] . '.' . $q['fk_col'] . ' -> ' . $q['target_table'] . '.id`';
    $md[] = '- Success: `' . ($q['success'] ? 'yes' : 'no') . '`';
    $md[] = '- Execution time (ms): `' . (string)$q['duration_ms'] . '`';
    $md[] = '- Returned rows: `' . (string)$q['row_count'] . '`';
    $md[] = '- Fail reason: `' . ((string)$q['error_reason'] !== '' ? (string)$q['error_reason'] : 'none') . '`';
    $md[] = '';
    $md[] = '### SQL';
    $md[] = '';
    $md[] = '```sql';
    $md[] = QueryLogger::formatSql($q['sql']);
    $md[] = '```';
    $md[] = '';
    $md[] = '### EXPLAIN Table (human-readable)';
    $md[] = '';
    $md[] = '```text';
    $md[] = (string)$q['explain_table'] !== '' ? (string)$q['explain_table'] : '(not available)';
    $md[] = '```';
    $md[] = '';
}

file_put_contents($outputMd, implode(PHP_EOL, $md) . PHP_EOL);

echo "Generated: {$outputMd}\n";
echo "Generated: {$outputJson}\n";
echo "Queries: {$total} (ok={$okCount}, fail={$koCount})\n";

function discoverPairs(string $schema): array
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        (string) ($_ENV['DB_HOST'] ?? '127.0.0.1'),
        (int) ($_ENV['DB_PORT'] ?? 3306),
        $schema
    );

    try {
        $pdo = new PDO(
            $dsn,
            (string) ($_ENV['DB_USER'] ?? ''),
            (string) ($_ENV['DB_PASS'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (Throwable $e) {
        return [];
    }

    $sql = <<<'SQL'
SELECT
    c.TABLE_NAME AS child_table,
    c.COLUMN_NAME AS fk_col,
    SUBSTRING(c.COLUMN_NAME, 4) AS target_table
FROM information_schema.COLUMNS c
JOIN information_schema.TABLES t
    ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
   AND t.TABLE_NAME = SUBSTRING(c.COLUMN_NAME, 4)
JOIN information_schema.COLUMNS p
    ON p.TABLE_SCHEMA = c.TABLE_SCHEMA
   AND p.TABLE_NAME = t.TABLE_NAME
   AND p.COLUMN_NAME = 'id'
WHERE c.TABLE_SCHEMA = ?
  AND c.COLUMN_NAME LIKE 'id\\_%'
ORDER BY c.TABLE_NAME, c.COLUMN_NAME
LIMIT 120
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$schema]);
    $rows = $stmt->fetchAll();

    $pairs = [];
    foreach ($rows as $r) {
        $child = (string)($r['child_table'] ?? '');
        $fk = (string)($r['fk_col'] ?? '');
        $target = (string)($r['target_table'] ?? '');
        if ($child === '' || $fk === '' || $target === '') {
            continue;
        }
        $pairs[] = [
            'child_table' => $child,
            'fk_col' => $fk,
            'target_table' => $target,
        ];
    }

    return $pairs;
}

function buildQuery(array $pair, int $variant): string
{
    $c = quoteIdent($pair['child_table']);
    $t = quoteIdent($pair['target_table']);
    $fk = quoteIdent($pair['fk_col']);

    return match ($variant) {
        1 => "SELECT c.id, c.{$fk}, t.id AS target_id FROM {$c} c JOIN {$t} t ON t.id = c.{$fk} WHERE c.{$fk} IS NOT NULL ORDER BY c.id DESC LIMIT 300",
        2 => "SELECT c.{$fk}, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id FROM {$c} c WHERE c.{$fk} IS NOT NULL GROUP BY c.{$fk} ORDER BY cnt DESC LIMIT 200",
        3 => "SELECT x.id, x.{$fk}, t.id AS target_id FROM (SELECT id, {$fk} FROM {$c} WHERE {$fk} IS NOT NULL ORDER BY id DESC LIMIT 1000) x JOIN {$t} t ON t.id = x.{$fk} ORDER BY x.id DESC LIMIT 200",
        default => "WITH ranked AS (SELECT c.id, c.{$fk}, ROW_NUMBER() OVER (PARTITION BY c.{$fk} ORDER BY c.id DESC) AS rn FROM {$c} c WHERE c.{$fk} IS NOT NULL) SELECT r.id, r.{$fk}, t.id AS target_id, r.rn FROM ranked r JOIN {$t} t ON t.id = r.{$fk} WHERE r.rn <= 5 ORDER BY r.id DESC LIMIT 250",
    };
}

function quoteIdent(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}

function endpointSource(string $endpoint): string
{
    $parts = parse_url($endpoint);
    $host = (string)($parts['host'] ?? 'unknown');
    $port = (int)($parts['port'] ?? 80);
    return $host . ':' . $port;
}

function callMcp(string $endpoint, string $token, int $id, string $tool, array $arguments): array
{
    $payload = [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool,
            'arguments' => $arguments,
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
        CURLOPT_TIMEOUT => 60,
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
