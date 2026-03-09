<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AccountSecurityTest extends TestCase
{
    private string $cacheFile;
    private string $envFile;

    protected function setUp(): void
    {
        $this->cacheFile = '/tmp/mcp_account_tested_' . uniqid('', true) . '.json';
        $this->envFile = '/tmp/mcp_env_' . uniqid('', true) . '.env';
        file_put_contents($this->envFile, "DB_HOST=127.0.0.1\n");

        $_ENV['ACCOUNT_TEST_CACHE_FILE'] = $this->cacheFile;
        $_ENV['ACCOUNT_ENV_FILE'] = $this->envFile;
        $_ENV['MAX_SELECT_TIME_S'] = '5';
        $_ENV['MCP_QUERY_LOG'] = '/tmp/mcp_mariadb_query_test.log';

        $this->setDbStatic('pdo', null);
        $this->setDbStatic('isMariaDb', false);
        $this->setDbStatic('serverVersion', '8.0.45');
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFile);
        @unlink($this->envFile);
        unset($_ENV['ACCOUNT_TEST_CACHE_FILE'], $_ENV['ACCOUNT_ENV_FILE']);
    }

    public function testMcpTestPassesForReadOnlyAccount(): void
    {
        $this->installPdoMock([
            'GRANT USAGE ON *.* TO `u`@`%`',
            'GRANT SELECT ON `db`.* TO `u`@`%`',
            'GRANT SHOW VIEW ON `db`.* TO `u`@`%`',
            'GRANT PROCESS ON `pmacontrol`.* TO `u`@`%`',
        ], readOnly: 1, superReadOnly: 1);

        $result = Tools::call('mcp_test', []);
        $this->assertTrue($result['safe']);
        $this->assertFalse($result['blocked']);
        $this->assertFalse($result['fromCache']);
        $this->assertFileExists($this->cacheFile);
    }

    public function testMcpTestFailsForWritableAccount(): void
    {
        $this->installPdoMock([
            'GRANT USAGE ON *.* TO `u`@`%`',
            'GRANT SELECT, INSERT, UPDATE ON `db`.* TO `u`@`%`',
            'GRANT ALL PRIVILEGES ON `db`.* TO `u`@`%`',
            'GRANT ALTER USER, SELECT ON `db`.* TO `u`@`%`',
            
        ], readOnly: 0, superReadOnly: 0);

        $result = Tools::call('mcp_test', []);
        $this->assertFalse($result['safe']);
        $this->assertTrue($result['blocked']);
        $this->assertContains('INSERT', $result['unsafePrivileges']);
        $this->assertContains('UPDATE', $result['unsafePrivileges']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MCP blocked');
        AccountSecurity::assertMcpAllowed();
    }

    public function testCacheInvalidatedWhenEnvIsNewer(): void
    {
        $this->installPdoMock([
            'GRANT USAGE ON *.* TO `u`@`%`',
            'GRANT SELECT ON `db`.* TO `u`@`%`',
        ], readOnly: 1, superReadOnly: 0);

        $first = AccountSecurity::runChecklist(false);
        $this->assertFalse($first['fromCache']);
        $this->assertFileExists($this->cacheFile);

        $second = AccountSecurity::runChecklist(false);
        $this->assertTrue($second['fromCache']);

        sleep(1);
        file_put_contents($this->envFile, "DB_HOST=127.0.0.1\nDB_USER=new_user\n");
        $third = AccountSecurity::runChecklist(false);
        $this->assertFalse($third['fromCache']);
    }

    private function installPdoMock(array $grants, int $readOnly, int $superReadOnly): void
    {
        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['query'])
            ->getMock();

        $pdo->method('query')->willReturnCallback(function (string $query) use ($grants, $readOnly, $superReadOnly) {
            $stmt = $this->getMockBuilder(PDOStatement::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['fetchAll', 'fetch'])
                ->getMock();

            if (str_starts_with($query, 'SHOW GRANTS FOR CURRENT_USER')) {
                $rows = [];
                foreach ($grants as $g) {
                    $rows[] = [$g];
                }
                $stmt->method('fetchAll')->willReturn($rows);
                $stmt->method('fetch')->willReturn(false);
                return $stmt;
            }

            if (str_starts_with($query, 'SELECT @@GLOBAL.read_only')) {
                $stmt->method('fetch')->willReturn(['read_only' => $readOnly]);
                $stmt->method('fetchAll')->willReturn([]);
                return $stmt;
            }

            if (str_starts_with($query, 'SELECT @@GLOBAL.super_read_only')) {
                $stmt->method('fetch')->willReturn(['super_read_only' => $superReadOnly]);
                $stmt->method('fetchAll')->willReturn([]);
                return $stmt;
            }

            $stmt->method('fetch')->willReturn(false);
            $stmt->method('fetchAll')->willReturn([]);
            return $stmt;
        });

        $this->setDbStatic('pdo', $pdo);
    }

    private function setDbStatic(string $property, mixed $value): void
    {
        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty($property);
        $prop->setValue(null, $value);
    }
}
