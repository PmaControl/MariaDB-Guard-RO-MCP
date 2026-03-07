<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ToolsComplexQueriesReplayTest extends TestCase
{
    public const SQL_HEAVY_DERIVED = <<<'SQL'
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
SQL;

    public const SQL_BIG_TABLES = <<<'SQL'
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
SQL;

    public const SQL_WINDOW_FILTERED = <<<'SQL'
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
SQL;

    protected function setUp(): void
    {
        $_ENV['DB_NAME'] = 'pmacontrol';
        $_ENV['MAX_ROWS_DEFAULT'] = '1000';
        $_ENV['MAX_ROWS_HARD'] = '5000';
        $_ENV['MAX_SELECT_TIME_MS'] = '5000';
        $_ENV['WHERE_FULLSCAN_MAX_ROWS'] = '30000';
        $_ENV['MCP_QUERY_LOG'] = '/tmp/mcp_mariadb_query_test.log';

        $this->installReplayDbMock();
    }

    public static function replayCases(): array
    {
        return [
            [
                'id' => 'Q1',
                'label' => 'Heavy derived (expected guard)',
                'tool' => 'db_select',
                'sql' => self::SQL_HEAVY_DERIVED,
            ],
            [
                'id' => 'Q2',
                'label' => 'Big tables + indexes',
                'tool' => 'db_select',
                'sql' => self::SQL_BIG_TABLES,
            ],
            [
                'id' => 'Q3',
                'label' => 'Window + filtered join',
                'tool' => 'db_select',
                'sql' => self::SQL_WINDOW_FILTERED,
            ],
            [
                'id' => 'Q4',
                'label' => 'Explain window + filtered join',
                'tool' => 'db_explain',
                'sql' => self::SQL_WINDOW_FILTERED,
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
    }

    public function testHeavyDerivedQueryIsRejectedByFullScanPolicy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('refuses WHERE full scan on large table');

        Tools::call('db_select', ['sql' => self::SQL_HEAVY_DERIVED]);
    }

    public function testBigTablesQueryPassesAndReturnsRows(): void
    {
        $result = Tools::call('db_select', ['sql' => self::SQL_BIG_TABLES]);

        $this->assertSame(2, $result['rowCount']);
        $this->assertSame('ts_value_general_int', $result['rows'][0]['table_name']);
    }

    public function testWindowFilteredQueryPassesAndReturnsRows(): void
    {
        $result = Tools::call('db_select', ['sql' => self::SQL_WINDOW_FILTERED]);

        $this->assertSame(2, $result['rowCount']);
        $this->assertSame(113, (int) $result['rows'][0]['id_mysql_server']);
    }

    public function testExplainOnWindowFilteredQueryReturnsPlan(): void
    {
        $result = Tools::call('db_explain', ['sql' => self::SQL_WINDOW_FILTERED]);

        $this->assertSame(1, $result['rowCount']);
        $this->assertArrayHasKey('EXPLAIN', $result['rows'][0]);
    }

    private function installReplayDbMock(): void
    {
        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();

        $pdo->method('prepare')->willReturnCallback(function (string $query) {
            $stmt = $this->getMockBuilder(PDOStatement::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['bindValue', 'execute', 'fetchAll', 'columnCount', 'getColumnMeta', 'fetchColumn'])
                ->getMock();

            $stmt->method('bindValue')->willReturn(true);
            $stmt->method('execute')->willReturn(true);

            if (str_starts_with($query, 'SELECT COALESCE(TABLE_ROWS, 0) FROM information_schema.TABLES')) {
                $stmt->method('fetchColumn')->willReturn(1165385);
                $stmt->method('fetchAll')->willReturn([]);
                $stmt->method('columnCount')->willReturn(0);
                return $stmt;
            }

            if (str_starts_with($query, 'SELECT COUNT(*) FROM information_schema.COLUMNS')) {
                $stmt->method('fetchColumn')->willReturn(8);
                $stmt->method('fetchAll')->willReturn([]);
                $stmt->method('columnCount')->willReturn(0);
                return $stmt;
            }

            if (str_starts_with($query, 'EXPLAIN FORMAT=JSON ')) {
                $stmt->method('fetchAll')->willReturn([[
                    'EXPLAIN' => '{"query_block":{"select_id":1,"nested_loop":[]}}',
                ]]);
                $stmt->method('columnCount')->willReturn(1);
                $stmt->method('getColumnMeta')->willReturn(['name' => 'EXPLAIN']);
                $stmt->method('fetchColumn')->willReturn(0);
                return $stmt;
            }

            if (str_starts_with($query, 'EXPLAIN ')) {
                $plan = [['table' => 'users', 'type' => 'ref', 'key' => 'idx_status', 'rows' => 100]];

                if (str_contains($query, 'FROM alias_dns ad2')) {
                    $plan = [[
                        'table' => 'ad2',
                        'type' => 'ALL',
                        'key' => null,
                        'rows' => 1165385,
                    ]];
                } elseif (str_contains($query, 'FROM alias_dns ad') && str_contains($query, 'id_mysql_server = 113')) {
                    $plan = [[
                        'table' => 'ad',
                        'type' => 'ref',
                        'key' => 'id_mysql_server',
                        'rows' => 4,
                    ]];
                } elseif (str_contains($query, 'FROM information_schema.tables t')) {
                    $plan = [[
                        'table' => 'tables',
                        'type' => 'ref',
                        'key' => 'PRIMARY',
                        'rows' => 20,
                    ]];
                }

                $stmt->method('fetchAll')->willReturn($plan);
                $stmt->method('columnCount')->willReturn(0);
                $stmt->method('fetchColumn')->willReturn(0);
                return $stmt;
            }

            $rows = [];
            if (str_contains($query, 'FROM information_schema.tables t')) {
                $rows = [
                    [
                        'table_name' => 'ts_value_general_int',
                        'table_rows' => '31513643362',
                        'index_count' => 2,
                        'indexes_preview' => 'id_mysql_server,id_ts_variable,date',
                    ],
                    [
                        'table_name' => 'ts_mysql_digest_stat',
                        'table_rows' => '2638376302',
                        'index_count' => 2,
                        'indexes_preview' => 'id_mysql_database__mysql_digest,date',
                    ],
                ];
            } elseif (str_contains($query, 'COUNT(*) OVER (PARTITION BY ad.id_mysql_server)')) {
                $rows = [
                    [
                        'id' => 329,
                        'id_mysql_server' => 113,
                        'name' => 'server_68cd3b0699986',
                        'ip' => '127.0.0.1',
                        'port' => 3306,
                        'is_from_ssh' => 0,
                        'total_alias_for_server' => 4,
                        'rn' => 1,
                    ],
                    [
                        'id' => 322,
                        'id_mysql_server' => 113,
                        'name' => 'server_68cd3b0699986',
                        'ip' => '127.0.0.1',
                        'port' => 3306,
                        'is_from_ssh' => 0,
                        'total_alias_for_server' => 4,
                        'rn' => 2,
                    ],
                ];
            }

            $stmt->method('fetchAll')->willReturn($rows);
            $stmt->method('columnCount')->willReturn(count($rows) > 0 ? count($rows[0]) : 0);
            $stmt->method('getColumnMeta')->willReturnCallback(function (int $i) use ($rows) {
                if (count($rows) === 0) {
                    return ['name' => 'col' . $i];
                }
                $keys = array_keys($rows[0]);
                return ['name' => $keys[$i] ?? ('col' . $i)];
            });
            $stmt->method('fetchColumn')->willReturn(0);

            return $stmt;
        });

        $this->setDbStatic('pdo', $pdo);
        $this->setDbStatic('isMariaDb', false);
        $this->setDbStatic('serverVersion', '8.0.45');
    }

    private function setDbStatic(string $property, mixed $value): void
    {
        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
    }
}
