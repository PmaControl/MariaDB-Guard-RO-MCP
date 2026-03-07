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
        $_ENV['MAX_SELECT_TIME_MS'] = '5000';
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
                'rows' => 15000,
            ]],
            tableRows: 20000,
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

    private function installDbMock(array $explainPlan, int $tableRows, int $columnCount, array $rows): void
    {
        $lastQuery = '';

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();

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
        $this->setDbStatic('serverVersion', '8.0.36');
    }

    private function setDbStatic(string $property, mixed $value): void
    {
        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
    }
}
