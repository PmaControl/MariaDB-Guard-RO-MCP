<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;
    private static ?bool $isMariaDb = null;
    private static ?string $serverVersion = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = self::buildDsn();
        $options = self::buildPdoOptions();

        self::$pdo = new PDO(
            $dsn,
            (string) Env::get('DB_USER', ''),
            self::dbPassword(),
            $options
        );

        return self::$pdo;
    }

    private static function dbPassword(): string
    {
        $legacy = (string) Env::get('DB_PASS', '');
        if ($legacy !== '') {
            return $legacy;
        }
        return (string) Env::get('DB_PASSWORD', '');
    }

    private static function buildDsn(): string
    {
        $charset = (string) Env::get('DB_CHARSET', 'utf8mb4');
        if ($charset === '') {
            $charset = 'utf8mb4';
        }

        $parts = [
            'mysql:host=' . (string) Env::get('DB_HOST', '127.0.0.1'),
            'port=' . Env::getInt('DB_PORT', 3306),
            'dbname=' . (string) Env::get('DB_NAME', ''),
            'charset=' . $charset,
        ];

        $sslMode = self::sslMode();
        if ($sslMode !== null) {
            $parts[] = 'ssl-mode=' . $sslMode;
        }

        return implode(';', $parts);
    }

    private static function buildPdoOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (!Env::getBool('DB_SSL', false)) {
            return $options;
        }

        $ca = self::envPath('DB_SSL_CA');
        $cert = self::envPath('DB_SSL_CERT');
        $key = self::envPath('DB_SSL_KEY');

        if ($ca === null) {
            error_log('MCP DB SSL warning: DB_SSL=true but DB_SSL_CA is not configured.');
        }

        self::assertExistingSslFile('DB_SSL_CA', $ca);
        self::assertExistingSslFile('DB_SSL_CERT', $cert);
        self::assertExistingSslFile('DB_SSL_KEY', $key);

        self::setMysqlSslOption($options, 'MYSQL_ATTR_SSL_CA', $ca);
        self::setMysqlSslOption($options, 'MYSQL_ATTR_SSL_CERT', $cert);
        self::setMysqlSslOption($options, 'MYSQL_ATTR_SSL_KEY', $key);

        if (Env::getBool('DB_SSL_VERIFY_CERT', false)) {
            self::setMysqlSslOption($options, 'MYSQL_ATTR_SSL_VERIFY_SERVER_CERT', true);
        }

        return $options;
    }

    private static function sslMode(): ?string
    {
        if (!Env::getBool('DB_SSL', false)) {
            return null;
        }

        if (Env::getBool('DB_SSL_VERIFY_IDENTITY', false)) {
            return 'VERIFY_IDENTITY';
        }

        if (Env::getBool('DB_SSL_VERIFY_CERT', false)) {
            return 'VERIFY_CA';
        }

        return 'REQUIRED';
    }

    private static function envPath(string $key): ?string
    {
        $value = trim((string) Env::get($key, ''));
        return $value === '' ? null : $value;
    }

    private static function assertExistingSslFile(string $envName, ?string $path): void
    {
        if ($path === null) {
            return;
        }
        if (!is_file($path)) {
            throw new RuntimeException("MCP DB SSL configuration error: {$envName} file not found: {$path}");
        }
    }

    private static function setMysqlSslOption(array &$options, string $constantName, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $fqcn = 'PDO::' . $constantName;
        if (!defined($fqcn)) {
            return;
        }

        $options[constant($fqcn)] = $value;
    }

    public static function isMariaDb(): bool
    {
        if (self::$isMariaDb !== null) {
            return self::$isMariaDb;
        }

        $version = self::serverVersion();
        self::$isMariaDb = stripos($version, 'mariadb') !== false;

        return self::$isMariaDb;
    }

    public static function serverVersion(): string
    {
        if (self::$serverVersion !== null) {
            return self::$serverVersion;
        }

        $pdo = self::pdo();
        self::$serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        return self::$serverVersion;
    }

    public static function isMySqlVersionAtLeast(string $minimum): bool
    {
        if (self::isMariaDb()) {
            return false;
        }

        $version = self::serverVersion();
        if (!preg_match('/\d+\.\d+\.\d+/', $version, $matches)) {
            return false;
        }

        return version_compare($matches[0], $minimum, '>=');
    }

    public static function isMariaDbVersionAtLeast(string $minimum): bool
    {
        if (!self::isMariaDb()) {
            return false;
        }

        $version = self::serverVersion();
        if (!preg_match('/\d+\.\d+\.\d+/', $version, $matches)) {
            return false;
        }

        return version_compare($matches[0], $minimum, '>=');
    }

    public static function activeRunningQueryCount(): int
    {
        try {
            $pdo = self::pdo();
            $sql = "SELECT COUNT(*)
                    FROM information_schema.PROCESSLIST
                    WHERE ID <> CONNECTION_ID()
                      AND COMMAND IN ('Query', 'Execute')
                      AND INFO IS NOT NULL";

            $count = $pdo->query($sql)->fetchColumn();
            return is_numeric($count) ? (int) $count : 0;
        } catch (Throwable) {
            return 0;
        }
    }

}
