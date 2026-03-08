<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ToolsExplainTableTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['MAX_SELECT_TIME_S'] = '5';
        $_ENV['MCP_QUERY_LOG'] = '/tmp/mcp_mariadb_query_test.log';

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();

        $pdo->method('prepare')->willReturnCallback(function (string $query) {
            $stmt = $this->getMockBuilder(PDOStatement::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['bindValue', 'execute', 'fetchAll', 'columnCount', 'getColumnMeta'])
                ->getMock();

            $stmt->method('bindValue')->willReturn(true);
            $stmt->method('execute')->willReturn(true);

            if (str_starts_with($query, 'EXPLAIN ')) {
                $rows = [[
                    'id' => 1,
                    'select_type' => 'SIMPLE',
                    'table' => 'alias_dns',
                    'type' => 'ref',
                    'possible_keys' => 'id_mysql_server',
                    'key' => 'id_mysql_server',
                    'rows' => 31680,
                    'Extra' => 'Using where',
                ]];
                $stmt->method('fetchAll')->willReturn($rows);
                $stmt->method('columnCount')->willReturn(count($rows[0]));
                $stmt->method('getColumnMeta')->willReturnCallback(function (int $i) use ($rows) {
                    $keys = array_keys($rows[0]);
                    return ['name' => $keys[$i] ?? ('col' . $i)];
                });
                return $stmt;
            }

            $stmt->method('fetchAll')->willReturn([]);
            $stmt->method('columnCount')->willReturn(0);
            $stmt->method('getColumnMeta')->willReturn(['name' => 'col0']);
            return $stmt;
        });

        $this->setDbStatic('pdo', $pdo);
        $this->setDbStatic('isMariaDb', false);
        $this->setDbStatic('serverVersion', '8.0.45');
    }

    public function testExplainTableReturnsHumanReadableTable(): void
    {
        $result = Tools::call('db_explain_table', [
            'sql' => 'SELECT id,id_mysql_server,port FROM alias_dns WHERE id_mysql_server = 113 ORDER BY id DESC LIMIT 50',
        ]);

        $this->assertSame(1, $result['rowCount']);
        $this->assertArrayHasKey('tableText', $result);
        $this->assertStringContainsString('| id ', $result['tableText']);
        $this->assertStringContainsString('| select_type ', $result['tableText']);
        $this->assertStringContainsString('| alias_dns ', $result['tableText']);
    }

    public function testExplainTableRejectsNonSelect(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('db_explain_table only accepts SELECT queries');

        Tools::call('db_explain_table', ['sql' => 'SHOW TABLES']);
    }

    private function setDbStatic(string $property, mixed $value): void
    {
        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
    }
}
