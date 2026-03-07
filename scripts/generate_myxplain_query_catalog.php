#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/QueryLogger.php';

Env::load(__DIR__ . '/../.env');

$schema = (string) ($_ENV['DB_NAME'] ?? 'pmacontrol');
$endpoint = (string) ($_ENV['MCP_ENDPOINT'] ?? 'http://127.0.0.1:13306/mcp');
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
foreach ($cases as $caseIndex => $case) {
    for ($v = 1; $v <= 4; $v++) {
        $pair = $pairs[($caseIndex * 4 + $v - 1) % count($pairs)];
        $sql = buildExplainSql($pair, $case, $v);

        $catalog[] = [
            'id' => sprintf('MXP-%03d', $idx),
            'myxplain_case' => $case,
            'variant' => $v,
            'source' => endpointSource($endpoint),
            'tool' => 'db_explain',
            'child_table' => $pair['child_table'],
            'fk_col' => $pair['fk_col'],
            'target_table' => $pair['target_table'],
            'sql' => $sql,
        ];
        $idx++;
    }
}

file_put_contents($outputJson, json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);

$md = [];
$md[] = '# MYXPLAIN Query Catalog (MCP / db_explain)';
$md[] = '';
$md[] = '- Source cases: `https://github.com/cpeintre/MYXPLAIN/tree/master/data`';
$md[] = '- Pattern rule used: `YYYYYY.id_XXXXXX -> XXXXXX.id`';
$md[] = '- Endpoint source: `' . endpointSource($endpoint) . '`';
$md[] = '- Total MYXPLAIN cases: `' . count($cases) . '`';
$md[] = '- Variants per case: `4`';
$md[] = '- Total generated queries: `' . count($catalog) . '`';
$md[] = '';
$md[] = '## How To Replay';
$md[] = '';
$md[] = 'Use each SQL with MCP tool `db_explain`.';
$md[] = '';

foreach ($catalog as $q) {
    $md[] = '## ' . $q['id'] . ' - case `' . $q['myxplain_case'] . '` (v' . $q['variant'] . ')';
    $md[] = '';
    $md[] = '- Source (ip:port): `' . $q['source'] . '`';
    $md[] = '- Tool: `db_explain`';
    $md[] = '- Relation guessed: `' . $q['child_table'] . '.' . $q['fk_col'] . ' -> ' . $q['target_table'] . '.id`';
    $md[] = '';
    $md[] = '```sql';
    $md[] = QueryLogger::formatSql($q['sql']);
    $md[] = '```';
    $md[] = '';
}

file_put_contents($outputMd, implode(PHP_EOL, $md) . PHP_EOL);

echo "Generated: {$outputMd}\n";
echo "Generated: {$outputJson}\n";
echo 'Queries: ' . count($catalog) . "\n";

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

function buildExplainSql(array $pair, string $case, int $variant): string
{
    $c = quoteIdent($pair['child_table']);
    $t = quoteIdent($pair['target_table']);
    $fk = quoteIdent($pair['fk_col']);

    $sig = strtoupper($case);

    return match ($variant) {
        1 => "EXPLAIN FORMAT=JSON SELECT c.id, c.{$fk}, t.id AS target_id FROM {$c} c JOIN {$t} t ON t.id = c.{$fk} WHERE c.{$fk} IS NOT NULL ORDER BY c.id DESC LIMIT 300",
        2 => "EXPLAIN FORMAT=JSON SELECT c.{$fk}, COUNT(*) AS cnt, MIN(c.id) AS min_id, MAX(c.id) AS max_id FROM {$c} c WHERE c.{$fk} IS NOT NULL GROUP BY c.{$fk} ORDER BY cnt DESC LIMIT 200",
        3 => "EXPLAIN FORMAT=JSON SELECT x.id, x.{$fk}, t.id AS target_id FROM (SELECT id, {$fk} FROM {$c} WHERE {$fk} IS NOT NULL ORDER BY id DESC LIMIT 1000) x JOIN {$t} t ON t.id = x.{$fk} ORDER BY x.id DESC LIMIT 200",
        default => "EXPLAIN FORMAT=JSON WITH ranked AS (SELECT c.id, c.{$fk}, ROW_NUMBER() OVER (PARTITION BY c.{$fk} ORDER BY c.id DESC) AS rn FROM {$c} c WHERE c.{$fk} IS NOT NULL) SELECT r.id, r.{$fk}, t.id AS target_id, r.rn FROM ranked r JOIN {$t} t ON t.id = r.{$fk} WHERE r.rn <= 5 ORDER BY r.id DESC LIMIT 250",
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
