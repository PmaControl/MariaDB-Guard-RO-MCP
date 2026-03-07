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
$skipCaseReasons = [
    'FULLTEXT' => 'No FULLTEXT index on this server',
];

$pairs = discoverPairs($schema, 100);
if (count($pairs) === 0) {
    fwrite(STDERR, "No relation pairs found with TABLE_ROWS >= 100 for both tables.\n");
    exit(1);
}

$catalog = [];
$idx = 1;
$rpcId = 7000;
foreach ($cases as $caseIndex => $case) {
    if (isset($skipCaseReasons[$case])) {
        for ($v = 1; $v <= 4; $v++) {
            $catalog[] = [
                'id' => sprintf('MXP-%03d', $idx),
                'myxplain_case' => $case,
                'variant' => $v,
                'source' => endpointSource($endpoint),
                'tool_query' => 'db_select',
                'tool_explain' => 'db_explain_table',
                'child_table' => '',
                'fk_col' => '',
                'target_table' => '',
                'child_table_rows' => 0,
                'target_table_rows' => 0,
                'child_has_id' => false,
                'sql' => '',
                'query_success' => false,
                'query_fail_reason' => 'skipped: ' . $skipCaseReasons[$case],
                'query_execution_time_ms' => 0,
                'query_returned_rows' => 0,
                'expected_signature' => expectedSignature($case),
                'explain_success' => false,
                'explain_fail_reason' => 'skipped: ' . $skipCaseReasons[$case],
                'explain_execution_time_ms' => 0,
                'explain_returned_rows' => 0,
                'explain_matches_case' => false,
                'explain_table' => '',
                'explain_rows' => [],
                'skipped' => true,
            ];
            echo sprintf("%s case=%s v=%d skipped=%s\n", sprintf('MXP-%03d', $idx), $case, $v, $skipCaseReasons[$case]);
            $idx++;
        }
        continue;
    }
    for ($v = 1; $v <= 4; $v++) {
        $pair = $pairs[($caseIndex * 4 + $v - 1) % count($pairs)];
        $sql = buildQuery($case, $pair, $v);
        $expected = expectedSignature($case);

        // 1) Execute real query for true row count and true duration.
        $qStarted = microtime(true);
        $qResp = callMcp($endpoint, $token, ++$rpcId, 'db_select', ['sql' => $sql]);
        $qDurationMs = (int) round((microtime(true) - $qStarted) * 1000);
        $qSuccess = !isset($qResp['error']);
        $qReason = $qSuccess ? '' : (string)($qResp['error']['message'] ?? 'Unknown error');
        $qRowCount = 0;
        if ($qSuccess) {
            $sc = $qResp['result']['structuredContent'] ?? [];
            if (is_array($sc)) {
                $qRowCount = (int)($sc['rowCount'] ?? 0);
            }
        }

        // 2) Execute explain table rendering.
        $eStarted = microtime(true);
        $eResp = callMcp($endpoint, $token, ++$rpcId, 'db_explain_table', ['sql' => $sql]);
        $eDurationMs = (int) round((microtime(true) - $eStarted) * 1000);
        $eSuccess = !isset($eResp['error']);
        $eReason = $eSuccess ? '' : (string)($eResp['error']['message'] ?? 'Unknown error');
        $eRowCount = 0;
        $eTableText = '';
        $eRows = [];
        $eMatches = false;
        if ($eSuccess) {
            $sc = $eResp['result']['structuredContent'] ?? [];
            if (is_array($sc)) {
                $eRowCount = (int)($sc['rowCount'] ?? 0);
                $eTableText = (string)($sc['tableText'] ?? '');
                $eRows = is_array($sc['rows'] ?? null) ? $sc['rows'] : [];
                $eMatches = explainMatchesCase($expected, $eRows, $eTableText);
            }
        }

        $catalog[] = [
            'id' => sprintf('MXP-%03d', $idx),
            'myxplain_case' => $case,
            'variant' => $v,
            'source' => endpointSource($endpoint),
            'tool_query' => 'db_select',
            'tool_explain' => 'db_explain_table',
            'child_table' => $pair['child_table'],
            'fk_col' => $pair['fk_col'],
            'target_table' => $pair['target_table'],
            'child_table_rows' => (int)$pair['child_table_rows'],
            'target_table_rows' => (int)$pair['target_table_rows'],
            'child_has_id' => (bool)$pair['child_has_id'],
            'sql' => $sql,
            'query_success' => $qSuccess,
            'query_fail_reason' => $qReason,
            'query_execution_time_ms' => $qDurationMs,
            'query_returned_rows' => $qRowCount,
            'expected_signature' => $expected,
            'explain_success' => $eSuccess,
            'explain_fail_reason' => $eReason,
            'explain_execution_time_ms' => $eDurationMs,
            'explain_returned_rows' => $eRowCount,
            'explain_matches_case' => $eMatches,
            'explain_table' => $eTableText,
            'explain_rows' => $eRows,
            'skipped' => false,
        ];
        echo sprintf(
            "%s case=%s v=%d query=%s explain=%s match=%s\n",
            sprintf('MXP-%03d', $idx),
            $case,
            $v,
            $qSuccess ? 'ok' : 'fail',
            $eSuccess ? 'ok' : 'fail',
            $eMatches ? 'yes' : 'no'
        );
        $idx++;
    }
}

file_put_contents($outputJson, json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);

$total = count($catalog);
$qOk = count(array_filter($catalog, static fn(array $q): bool => (bool)$q['query_success']));
$qKo = $total - $qOk;
$eOk = count(array_filter($catalog, static fn(array $q): bool => (bool)$q['explain_success']));
$eKo = $total - $eOk;

$md = [];
$md[] = '# MYXPLAIN Query Catalog (MCP / db_select + db_explain_table)';
$md[] = '';
$md[] = '- Source cases: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`';
$md[] = '- Pattern rule used: `YYYYYY.id_XXXXXX -> XXXXXX.id`';
$md[] = '- Endpoint source: `' . endpointSource($endpoint) . '`';
$md[] = '- Minimum table size enforced: `TABLE_ROWS >= 100` (child and target)';
$md[] = '- Total MYXPLAIN cases: `' . count($cases) . '`';
$md[] = '- Variants per case: `4`';
$md[] = '- Total generated queries: `' . $total . '`';
$md[] = '- Query pass/fail: `' . $qOk . '/' . $qKo . '`';
$md[] = '- Explain pass/fail: `' . $eOk . '/' . $eKo . '`';
$md[] = '- Explain signature matches expected case: `' . count(array_filter($catalog, static fn(array $q): bool => (bool)$q['explain_matches_case'])) . '/' . $total . '`';
$md[] = '- Skipped entries: `' . count(array_filter($catalog, static fn(array $q): bool => (bool)($q['skipped'] ?? false))) . '`';
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
    $md[] = '- Relation guessed: `' . $q['child_table'] . '.' . $q['fk_col'] . ' -> ' . $q['target_table'] . '.id`';
    $md[] = '- Child table rows (estimate): `' . (string)$q['child_table_rows'] . '`';
    $md[] = '- Target table rows (estimate): `' . (string)$q['target_table_rows'] . '`';
    $md[] = '- Skipped: `' . ((bool)($q['skipped'] ?? false) ? 'yes' : 'no') . '`';
    $md[] = '';
    $md[] = '### Real Query Execution (`db_select`)';
    $md[] = '';
    $md[] = '- Success: `' . ($q['query_success'] ? 'yes' : 'no') . '`';
    $md[] = '- Execution time (ms): `' . (string)$q['query_execution_time_ms'] . '`';
    $md[] = '- Returned rows: `' . (string)$q['query_returned_rows'] . '`';
    $md[] = '- Fail reason: `' . ((string)$q['query_fail_reason'] !== '' ? (string)$q['query_fail_reason'] : 'none') . '`';
    $md[] = '';
    $md[] = '### SQL';
    $md[] = '';
    $md[] = '```sql';
    $md[] = (string)$q['sql'] !== '' ? QueryLogger::formatSql($q['sql']) : '(not available)';
    $md[] = '```';
    $md[] = '';
    $md[] = '### EXPLAIN (`db_explain_table`)';
    $md[] = '';
    $md[] = '- Success: `' . ($q['explain_success'] ? 'yes' : 'no') . '`';
    $md[] = '- Execution time (ms): `' . (string)$q['explain_execution_time_ms'] . '`';
    $md[] = '- Returned rows: `' . (string)$q['explain_returned_rows'] . '`';
    $md[] = '- Expected signature: `' . (string)$q['expected_signature'] . '`';
    $md[] = '- Signature match: `' . ((bool)$q['explain_matches_case'] ? 'yes' : 'no') . '`';
    $md[] = '- Fail reason: `' . ((string)$q['explain_fail_reason'] !== '' ? (string)$q['explain_fail_reason'] : 'none') . '`';
    $md[] = '';
    $md[] = '```text';
    $md[] = (string)$q['explain_table'] !== '' ? (string)$q['explain_table'] : '(not available)';
    $md[] = '```';
    $md[] = '';
}

file_put_contents($outputMd, implode(PHP_EOL, $md) . PHP_EOL);

echo "Generated: {$outputMd}\n";
echo "Generated: {$outputJson}\n";
echo "Queries: {$total} (query_ok={$qOk}, query_fail={$qKo}, explain_ok={$eOk}, explain_fail={$eKo}, case_match=" . count(array_filter($catalog, static fn(array $q): bool => (bool)$q['explain_matches_case'])) . ")\n";

function discoverPairs(string $schema, int $minRows): array
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
    SUBSTRING(c.COLUMN_NAME, 4) AS target_table,
    COALESCE(ct.TABLE_ROWS, 0) AS child_table_rows,
    COALESCE(tt.TABLE_ROWS, 0) AS target_table_rows,
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS ci
        WHERE ci.TABLE_SCHEMA = c.TABLE_SCHEMA
          AND ci.TABLE_NAME = c.TABLE_NAME
          AND ci.COLUMN_NAME = 'id'
    ) AS child_has_id
FROM information_schema.COLUMNS c
JOIN information_schema.TABLES ct
    ON ct.TABLE_SCHEMA = c.TABLE_SCHEMA
   AND ct.TABLE_NAME = c.TABLE_NAME
JOIN information_schema.TABLES tt
    ON tt.TABLE_SCHEMA = c.TABLE_SCHEMA
   AND tt.TABLE_NAME = SUBSTRING(c.COLUMN_NAME, 4)
JOIN information_schema.COLUMNS p
    ON p.TABLE_SCHEMA = c.TABLE_SCHEMA
   AND p.TABLE_NAME = tt.TABLE_NAME
   AND p.COLUMN_NAME = 'id'
WHERE c.TABLE_SCHEMA = ?
  AND c.COLUMN_NAME LIKE 'id\_%'
  AND COALESCE(ct.TABLE_ROWS, 0) >= ?
  AND COALESCE(tt.TABLE_ROWS, 0) >= ?
ORDER BY c.TABLE_NAME, c.COLUMN_NAME
LIMIT 200
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$schema, $minRows, $minRows]);
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
            'child_table_rows' => (int)($r['child_table_rows'] ?? 0),
            'target_table_rows' => (int)($r['target_table_rows'] ?? 0),
            'child_has_id' => (int)($r['child_has_id'] ?? 0) === 1,
        ];
    }

    return $pairs;
}

function buildQuery(string $case, array $pair, int $variant): string
{
    $c = quoteIdent($pair['child_table']);
    $t = quoteIdent($pair['target_table']);
    $fk = quoteIdent($pair['fk_col']);
    $idCol = !empty($pair['child_has_id']) ? '`id`' : $fk;
    $upperCase = strtoupper($case);

    return match ($upperCase) {
        'INDEX_SUBQUERY' => "SELECT c.{$idCol} AS row_id, c.{$fk} FROM {$c} c WHERE c.{$fk} IN (SELECT t.id FROM {$t} t WHERE t.id = c.{$fk}) ORDER BY c.{$idCol} DESC LIMIT 300",
        'UNIQUE_SUBQUERY' => "SELECT c.{$idCol} AS row_id, c.{$fk} FROM {$c} c WHERE c.{$fk} IN (SELECT t.id FROM {$t} t WHERE t.id = c.{$fk}) ORDER BY c.{$idCol} DESC LIMIT 300",
        'DEPENDENT_SUBQUERY' => "SELECT c.{$idCol} AS row_id, c.{$fk} FROM {$c} c WHERE EXISTS (SELECT 1 FROM {$t} t WHERE t.id = c.{$fk} AND t.id = c.{$fk}) ORDER BY c.{$idCol} DESC LIMIT 250",
        'DEPENDENT_UNION' => "SELECT c.{$idCol} AS row_id, c.{$fk} FROM {$c} c WHERE c.{$fk} IN ((SELECT t.id FROM {$t} t WHERE t.id = c.{$fk}) UNION (SELECT t2.id FROM {$t} t2 WHERE t2.id = c.{$fk})) ORDER BY c.{$idCol} DESC LIMIT 250",
        'SUBQUERY' => "SELECT c.{$idCol} AS row_id, c.{$fk} FROM {$c} c WHERE c.{$fk} IN (SELECT t.id FROM {$t} t WHERE t.id IS NOT NULL) ORDER BY c.{$idCol} DESC LIMIT 250",
        'UNION' => "SELECT c.{$idCol} AS row_id, c.{$fk} FROM {$c} c WHERE c.{$fk} IS NOT NULL UNION SELECT c2.{$idCol} AS row_id, c2.{$fk} FROM {$c} c2 WHERE c2.{$fk} IS NOT NULL LIMIT 300",
        'DERIVED' => "SELECT d.row_id, d.{$fk}, t.id AS target_id FROM (SELECT {$idCol} AS row_id, {$fk} FROM {$c} WHERE {$fk} IS NOT NULL ORDER BY {$idCol} DESC LIMIT 1200) d JOIN {$t} t ON t.id = d.{$fk} ORDER BY d.row_id DESC LIMIT 220",
        'IMPOSSIBLE' => "SELECT c.{$idCol} AS row_id FROM {$c} c WHERE 1=0 LIMIT 10",
        default => match ($variant) {
            1 => "SELECT c.{$idCol} AS row_id, c.{$fk}, t.id AS target_id FROM {$c} c JOIN {$t} t ON t.id = c.{$fk} WHERE c.{$fk} IS NOT NULL ORDER BY c.{$idCol} DESC LIMIT 300",
            2 => "SELECT c.{$fk}, COUNT(*) AS cnt, MIN(c.{$idCol}) AS min_id, MAX(c.{$idCol}) AS max_id FROM {$c} c WHERE c.{$fk} IS NOT NULL GROUP BY c.{$fk} ORDER BY cnt DESC LIMIT 200",
            3 => "SELECT x.row_id, x.{$fk}, t.id AS target_id FROM (SELECT {$idCol} AS row_id, {$fk} FROM {$c} WHERE {$fk} IS NOT NULL ORDER BY {$idCol} DESC LIMIT 1000) x JOIN {$t} t ON t.id = x.{$fk} ORDER BY x.row_id DESC LIMIT 200",
            default => "WITH ranked AS (SELECT {$idCol} AS row_id, {$fk}, ROW_NUMBER() OVER (PARTITION BY {$fk} ORDER BY {$idCol} DESC) AS rn FROM {$c} WHERE {$fk} IS NOT NULL) SELECT r.row_id, r.{$fk}, t.id AS target_id, r.rn FROM ranked r JOIN {$t} t ON t.id = r.{$fk} WHERE r.rn <= 5 ORDER BY r.row_id DESC LIMIT 250",
        },
    };
}

function expectedSignature(string $case): string
{
    return strtoupper($case);
}

function explainMatchesCase(string $expected, array $rows, string $tableText): bool
{
    $haystack = strtoupper($tableText);
    foreach ($rows as $row) {
        $selectType = strtoupper((string)($row['select_type'] ?? ''));
        $accessType = strtoupper((string)($row['type'] ?? ''));
        $extra = strtoupper((string)($row['Extra'] ?? ''));
        $haystack .= ' ' . $selectType . ' ' . $accessType . ' ' . $extra;
    }

    return match ($expected) {
        'INDEX_SUBQUERY' => str_contains($haystack, 'INDEX_SUBQUERY'),
        'UNIQUE_SUBQUERY' => str_contains($haystack, 'UNIQUE_SUBQUERY'),
        'DEPENDENT_SUBQUERY' => str_contains($haystack, 'DEPENDENT SUBQUERY'),
        'DEPENDENT_UNION' => str_contains($haystack, 'DEPENDENT UNION'),
        'SUBQUERY' => str_contains($haystack, 'SUBQUERY'),
        'UNION' => str_contains($haystack, 'UNION'),
        'DERIVED' => str_contains($haystack, 'DERIVED'),
        'FILESORT', 'USINGFSORT' => str_contains($haystack, 'USING FILESORT'),
        'USINGTEMP' => str_contains($haystack, 'USING TEMPORARY'),
        'USINGINDEX' => str_contains($haystack, 'USING INDEX'),
        'USINGIDXGROUPBY', 'GROUPBYNOSORT' => str_contains($haystack, 'USING INDEX FOR GROUP-BY'),
        'IMPOSSIBLE' => str_contains($haystack, 'IMPOSSIBLE'),
        default => str_contains($haystack, $expected),
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
