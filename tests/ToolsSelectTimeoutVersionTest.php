<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ToolsSelectTimeoutVersionTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['MAX_SELECT_TIME_S'] = '5';
        $this->setDbStatic('pdo', null);
        $this->setDbStatic('isMariaDb', null);
        $this->setDbStatic('serverVersion', null);
    }

    /**
     * @dataProvider timeoutProvider
     */
    public function testApplySelectTimeoutByServerVersion(
        bool $isMariaDb,
        string $version,
        string $sql,
        string $expected
    ): void {
        $this->setDbStatic('isMariaDb', $isMariaDb);
        $this->setDbStatic('serverVersion', $version);

        $actual = $this->invokeApplySelectTimeout($sql);
        $this->assertSame($expected, $actual);
    }

    public function timeoutProvider(): array
    {
        return [
            'mariadb_5_5_45_no_timeout' => [
                true,
                '5.5.45-MariaDB-1~wheezy',
                'SELECT id FROM users',
                'SELECT id FROM users',
            ],
            'mariadb_10_1_1_timeout_enabled' => [
                true,
                '10.1.1-MariaDB',
                'SELECT id FROM users',
                'SET STATEMENT max_statement_time=5 FOR SELECT id FROM users',
            ],
            'mariadb_10_5_29_timeout_enabled' => [
                true,
                '10.5.29-MariaDB',
                'SELECT id FROM users',
                'SET STATEMENT max_statement_time=5 FOR SELECT id FROM users',
            ],
            'mariadb_10_6_23_timeout_enabled' => [
                true,
                '10.6.23-MariaDB',
                'SELECT id FROM users',
                'SET STATEMENT max_statement_time=5 FOR SELECT id FROM users',
            ],
            'mariadb_10_11_16_timeout_enabled' => [
                true,
                '10.11.16-MariaDB',
                'SELECT id FROM users',
                'SET STATEMENT max_statement_time=5 FOR SELECT id FROM users',
            ],
            'mariadb_12_3_2_timeout_enabled' => [
                true,
                '12.3.2-MariaDB',
                'SELECT id FROM users',
                'SET STATEMENT max_statement_time=5 FOR SELECT id FROM users',
            ],
            'percona_5_7_1_no_hint' => [
                false,
                '5.7.1-1 Percona Server (GPL), Release 1, Revision 123',
                'SELECT id FROM users',
                'SELECT id FROM users',
            ],
            'percona_5_7_4_hint_enabled' => [
                false,
                '5.7.4-2 Percona Server (GPL), Release 2, Revision 456',
                'SELECT id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'percona_5_7_44_hint_enabled' => [
                false,
                '5.7.44-48-log Percona Server (GPL), Release 48, Revision 1234',
                'SELECT id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'mysql_4_1_22_no_hint' => [
                false,
                '4.1.22',
                'SELECT id FROM users',
                'SELECT id FROM users',
            ],
            'mysql_5_7_1_no_hint' => [
                false,
                '5.7.1',
                'SELECT id FROM users',
                'SELECT id FROM users',
            ],
            'mysql_5_7_4_hint_enabled' => [
                false,
                '5.7.4',
                'SELECT id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'mysql_5_7_44_hint_enabled' => [
                false,
                '5.7.44',
                'SELECT id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'mysql_8_0_hint_enabled' => [
                false,
                '8.0.45',
                'SELECT id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'mysql_8_4_5_hint_enabled' => [
                false,
                '8.4.5',
                'SELECT id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'mysql_9_6_0_hint_enabled' => [
                false,
                '9.6.0',
                'SELECT id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'existing_mysql_hint_kept' => [
                false,
                '8.0.45',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
                'SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users',
            ],
            'mysql_cte_hint_enabled' => [
                false,
                '8.0.45',
                'WITH x AS (SELECT id FROM users) SELECT id FROM x',
                'WITH x AS (SELECT /*+ MAX_EXECUTION_TIME(5000) */ id FROM users) SELECT id FROM x',
            ],
            'mariadb_cte_timeout_enabled' => [
                true,
                '12.3.2-MariaDB',
                'WITH x AS (SELECT id FROM users) SELECT id FROM x',
                'SET STATEMENT max_statement_time=5 FOR WITH x AS (SELECT id FROM users) SELECT id FROM x',
            ],
            'non_select_unchanged' => [
                false,
                '8.0.45',
                'SHOW TABLES',
                'SHOW TABLES',
            ],
        ];
    }

    private function invokeApplySelectTimeout(string $sql): string
    {
        $method = new ReflectionMethod(Tools::class, 'applySelectTimeout');
        return (string) $method->invoke(null, $sql);
    }

    private function setDbStatic(string $property, mixed $value): void
    {
        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty($property);
        $prop->setValue(null, $value);
    }
}
