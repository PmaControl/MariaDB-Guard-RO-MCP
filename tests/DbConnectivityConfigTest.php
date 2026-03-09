<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DbConnectivityConfigTest extends TestCase
{
    private array $backupEnv = [];

    protected function setUp(): void
    {
        $this->backupEnv = $_ENV;
        $this->setDbStatic('pdo', null);
        $this->setDbStatic('isMariaDb', null);
        $this->setDbStatic('serverVersion', null);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->backupEnv;
    }

    public function testDefaultDsnUsesUtf8mb4AndNoSslMode(): void
    {
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_NAME'] = 'pmacontrol';
        unset($_ENV['DB_SSL'], $_ENV['DB_CHARSET'], $_ENV['DB_SSL_VERIFY_CERT'], $_ENV['DB_SSL_VERIFY_IDENTITY']);

        $dsn = $this->invokePrivate('buildDsn');
        $this->assertStringContainsString('charset=utf8mb4', $dsn);
        $this->assertStringNotContainsString('ssl-mode=', $dsn);
    }

    public function testCharsetCanBeOverridden(): void
    {
        $_ENV['DB_HOST'] = '10.0.0.2';
        $_ENV['DB_PORT'] = '3307';
        $_ENV['DB_NAME'] = 'dbx';
        $_ENV['DB_CHARSET'] = 'latin1';

        $dsn = $this->invokePrivate('buildDsn');
        $this->assertStringContainsString('charset=latin1', $dsn);
    }

    public function testSslModeVerifyIdentityIsApplied(): void
    {
        $_ENV['DB_HOST'] = '10.0.0.3';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_NAME'] = 'dbx';
        $_ENV['DB_SSL'] = 'true';
        $_ENV['DB_SSL_VERIFY_IDENTITY'] = 'true';

        $dsn = $this->invokePrivate('buildDsn');
        $this->assertStringContainsString('ssl-mode=VERIFY_IDENTITY', $dsn);
    }

    public function testSslModeVerifyCaIsApplied(): void
    {
        $_ENV['DB_HOST'] = '10.0.0.3';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_NAME'] = 'dbx';
        $_ENV['DB_SSL'] = '1';
        $_ENV['DB_SSL_VERIFY_CERT'] = '1';
        $_ENV['DB_SSL_VERIFY_IDENTITY'] = '0';

        $dsn = $this->invokePrivate('buildDsn');
        $this->assertStringContainsString('ssl-mode=VERIFY_CA', $dsn);
    }

    public function testBuildPdoOptionsFailsWhenSslFileDoesNotExist(): void
    {
        $_ENV['DB_SSL'] = 'true';
        $_ENV['DB_SSL_CA'] = '/no/such/ca.pem';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_SSL_CA file not found');
        $this->invokePrivate('buildPdoOptions');
    }

    public function testBuildPdoOptionsAcceptsExistingSslFiles(): void
    {
        $dir = sys_get_temp_dir() . '/mcp_ssl_' . uniqid('', true);
        mkdir($dir, 0777, true);
        $ca = $dir . '/ca.pem';
        $cert = $dir . '/client.pem';
        $key = $dir . '/client.key';
        file_put_contents($ca, 'ca');
        file_put_contents($cert, 'cert');
        file_put_contents($key, 'key');

        $_ENV['DB_SSL'] = 'true';
        $_ENV['DB_SSL_CA'] = $ca;
        $_ENV['DB_SSL_CERT'] = $cert;
        $_ENV['DB_SSL_KEY'] = $key;
        $_ENV['DB_SSL_VERIFY_CERT'] = 'true';

        $options = $this->invokePrivate('buildPdoOptions');
        $this->assertIsArray($options);
        $this->assertArrayHasKey(PDO::ATTR_ERRMODE, $options);

        @unlink($ca);
        @unlink($cert);
        @unlink($key);
        @rmdir($dir);
    }

    public function testDbPasswordSupportsLegacyAndNewVariable(): void
    {
        $_ENV['DB_PASS'] = 'legacy';
        $_ENV['DB_PASSWORD'] = 'new';
        $pwd = $this->invokePrivate('dbPassword');
        $this->assertSame('legacy', $pwd);

        $_ENV['DB_PASS'] = '';
        $pwd2 = $this->invokePrivate('dbPassword');
        $this->assertSame('new', $pwd2);
    }

    private function invokePrivate(string $method): mixed
    {
        $ref = new ReflectionClass(Db::class);
        $m = $ref->getMethod($method);
        return $m->invoke(null);
    }

    private function setDbStatic(string $property, mixed $value): void
    {
        $ref = new ReflectionClass(Db::class);
        $prop = $ref->getProperty($property);
        $prop->setValue(null, $value);
    }
}
