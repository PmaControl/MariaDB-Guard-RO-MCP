<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ToolsDbSelectPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['DB_NAME'] = 'pmacontrol';
        $_ENV['MAX_ROWS_DEFAULT'] = '1000';
        $_ENV['MAX_ROWS_HARD'] = '5000';
        $_ENV['MAX_SELECT_TIME_S'] = '5';
        $_ENV['WHERE_FULLSCAN_MAX_ROWS'] = '30000';
        $_ENV['MCP_QUERY_LOG'] = '/tmp/mcp_mariadb_query_test.log';
    }

    public function testSelectStarWithoutWhereSingleTableAllowed(): void
    {
        $this->installDbMock(
            explainPlan: [],
            tableRows: 0,
            columnCount: 10,
            rows: [['id' => 1, 'name' => 'ok']]
        );

        $result = Tools::call('db_select', ['sql' => 'SELECT * FROM users']);

        $this->assertSame(1, $result['rowCount']);
        $this->assertSame('ok', $result['rows'][0]['name']);
    }

    public function testSelectStarWithoutWhereWithJoinIsRejected(): void
    {
        $this->installDbMock([], 0, 10, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('single table without JOIN');

        Tools::call('db_select', [
            'sql' => 'SELECT * FROM users u JOIN orders o ON o.user_id = u.id',
        ]);
    }

    public function testOrInWhereIsRejected(): void
    {
        $this->installDbMock([], 0, 10, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rewrite the query using UNION or UNION ALL');

        Tools::call('db_select', [
            'sql' => "SELECT id FROM users WHERE email = 'a@b.com' OR phone = '123'",
        ]);
    }

    public function testWhereFullScanAllowedForSmallTables(): void
    {
        $this->installDbMock(
            explainPlan: [[
                'table' => 'users',
                'type' => 'ALL',
                'key' => null,
                'rows' => 200,
            ]],
            tableRows: 9000,
            columnCount: 10,
            rows: [['id' => 1]]
        );

        $result = Tools::call('db_select', [
            'sql' => "SELECT id FROM users WHERE status = 'ACTIVE'",
        ]);

        $this->assertSame(1, $result['rowCount']);
    }

    public function testWhereFullScanRejectedForLargeTables(): void
    {
        $this->installDbMock(
            explainPlan: [[
                'table' => 'users',
                'type' => 'ALL',
                'key' => null,
                'rows' => 35000,
            ]],
            tableRows: 40000,
            columnCount: 10,
            rows: []
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('refuses WHERE full scan on large table');

        Tools::call('db_select', [
            'sql' => "SELECT id FROM users WHERE status = 'ACTIVE'",
        ]);
    }

    public function testWhereIndexedAccessAllowedForLargeTables(): void
    {
        $this->installDbMock(
            explainPlan: [[
                'table' => 'users',
                'type' => 'ref',
                'key' => 'idx_status',
                'rows' => 500,
            ]],
            tableRows: 250000,
            columnCount: 10,
            rows: [['id' => 42]]
        );

        $result = Tools::call('db_select', [
            'sql' => "SELECT id FROM users WHERE status = 'ACTIVE'",
        ]);

        $this->assertSame(1, $result['rowCount']);
        $this->assertSame(42, $result['rows'][0]['id']);
    }

    public function testSelectStarWithWhereRejectedOnWideTable(): void
    {
        $this->installDbMock(
            explainPlan: [[
                'table' => 'wide_users',
                'type' => 'ref',
                'key' => 'PRIMARY',
                'rows' => 1,
            ]],
            tableRows: 1000,
            columnCount: 31,
            rows: []
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wide tables (>30 columns)');

        Tools::call('db_select', [
            'sql' => 'SELECT * FROM wide_users WHERE id = 1',
        ]);
    }

    public function testNonRecursiveCteIsAllowed(): void
    {
        $this->installDbMock(
            explainPlan: [[
                'table' => 'users',
                'type' => 'ref',
                'key' => 'idx_status',
                'rows' => 100,
            ]],
            tableRows: 200000,
            columnCount: 10,
            rows: [['id' => 7]]
        );

        $result = Tools::call('db_select', [
            'sql' => "WITH u AS (SELECT id FROM users WHERE status = 'ACTIVE') SELECT id FROM u WHERE id > 0",
        ]);

        $this->assertSame(1, $result['rowCount']);
        $this->assertSame(7, $result['rows'][0]['id']);
    }

    public function testRecursiveCteIsRejected(): void
    {
        $this->installDbMock([], 0, 10, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WITH RECURSIVE is not allowed');

        Tools::call('db_select', [
            'sql' => 'WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n+1 FROM t WHERE n < 3) SELECT * FROM t',
        ]);
    }

    public function testDbSelectRejectedWhenDatabaseBusy(): void
    {
        $this->installDbMock(
            explainPlan: [[
                'table' => 'users',
                'type' => 'ref',
                'key' => 'idx_status',
                'rows' => 100,
            ]],
            tableRows: 200000,
            columnCount: 10,
            rows: [['id' => 1]],
            runningQueries: 4
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('database busy retry in 1 second');

        Tools::call('db_select', [
            'sql' => "SELECT id FROM users WHERE status = 'ACTIVE'",
        ]);
    }

    public function testDbSelectAllowedWhenDatabaseNotBusy(): void
    {
        $this->installDbMock(
            explainPlan: [[
                'table' => 'users',
                'type' => 'ref',
                'key' => 'idx_status',
                'rows' => 100,
            ]],
            tableRows: 200000,
            columnCount: 10,
            rows: [['id' => 55]],
            runningQueries: 3
        );

        $result = Tools::call('db_select', [
            'sql' => "SELECT id FROM users WHERE status = 'ACTIVE'",
        ]);

        $this->assertSame(1, $result['rowCount']);
        $this->assertSame(55, $result['rows'][0]['id']);
    }

    private function installDbMock(array $explainPlan, int $tableRows, int $columnCount, array $rows, int $runningQueries = 0): void
    {
        $lastQuery = '';

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'query'])
            ->getMock();

        $pdo->method('query')->willReturnCallback(function (string $query) use ($runningQueries) {
            $stmt = $this->getMockBuilder(PDOStatement::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['fetchColumn'])
                ->getMock();

            if (str_contains($query, 'FROM information_schema.PROCESSLIST')) {
                $stmt->method('fetchColumn')->willReturn($runningQueries);
                return $stmt;
            }

            $stmt->method('fetchColumn')->willReturn(0);
            return $stmt;
        });

        $pdo->method('prepare')->willReturnCallback(function (string $query) use (&$lastQuery, $explainPlan, $tableRows, $columnCount, $rows) {
            $lastQuery = $query;

            $stmt = $this->getMockBuilder(PDOStatement::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['bindValue', 'execute', 'fetchAll', 'columnCount', 'getColumnMeta', 'fetchColumn'])
                ->getMock();

            $stmt->method('bindValue')->willReturn(true);
            $stmt->method('execute')->willReturn(true);

            if (str_starts_with($query, 'EXPLAIN ')) {
                $stmt->method('fetchAll')->willReturn($explainPlan);
                $stmt->method('columnCount')->willReturn(0);
                $stmt->method('fetchColumn')->willReturn(0);
                return $stmt;
            }

            if (str_starts_with($query, 'SELECT COUNT(*) FROM information_schema.COLUMNS')) {
                $stmt->method('fetchColumn')->willReturn($columnCount);
                $stmt->method('fetchAll')->willReturn([]);
                $stmt->method('columnCount')->willReturn(0);
                return $stmt;
            }

            if (str_starts_with($query, 'SELECT COALESCE(TABLE_ROWS, 0) FROM information_schema.TABLES')) {
                $stmt->method('fetchColumn')->willReturn($tableRows);
                $stmt->method('fetchAll')->willReturn([]);
                $stmt->method('columnCount')->willReturn(0);
                return $stmt;
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
