<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;
    private static ?bool $isMariaDb = null;
    private static ?bool $isTiDb = null;
    private static ?bool $isVitess = null;
    private static ?bool $isSingleStore = null;
    private static ?bool $isClickHouse = null;
    private static ?string $serverVersion = null;
    private static ?string $versionComment = null;

    public static function pdo(): PDO
    {
        if (self::declaredEngine() === 'clickhouse') {
            throw new RuntimeException('ClickHouse backend does not use PDO. Use ClickHouseClient.');
        }
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$pdo = self::createPdo();

        return self::$pdo;
    }

    public static function freshPdo(): PDO
    {
        if (self::declaredEngine() === 'clickhouse') {
            throw new RuntimeException('ClickHouse backend does not use PDO. Use ClickHouseClient.');
        }
        return self::createPdo();
    }

    private static function createPdo(): PDO
    {
        $dsn = self::buildDsn();
        $options = self::buildPdoOptions();

        return new PDO(
            $dsn,
            (string) Env::get('DB_USER', ''),
            self::dbPassword(),
            $options
        );
    }

    private static function dbPassword(): string
    {
        $legacy = (string) Env::get('DB_PASS', '');
        if ($legacy !== '') {
            return $legacy;
        }
        return (string) Env::get('DB_PASSWORD', '');
    }

    public static function dbUser(): string
    {
        return (string) Env::get('DB_USER', '');
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

        $declared = self::declaredEngine();
        if ($declared === 'mariadb') {
            self::$isMariaDb = true;
            return true;
        }
        if (in_array($declared, ['mysql', 'percona', 'percona/percona-server', 'tidb', 'vitess', 'singlestore', 'memsql', 'clickhouse'], true)) {
            self::$isMariaDb = false;
            return false;
        }

        $version = self::serverVersion();
        self::$isMariaDb = stripos($version, 'mariadb') !== false;

        return self::$isMariaDb;
    }

    public static function isTiDb(): bool
    {
        if (self::$isTiDb !== null) {
            return self::$isTiDb;
        }

        $declared = self::declaredEngine();
        if ($declared === 'tidb') {
            self::$isTiDb = true;
            return true;
        }

        $haystack = self::serverVersion() . ' ' . self::versionComment();
        self::$isTiDb = stripos($haystack, 'tidb') !== false;
        return self::$isTiDb;
    }

    public static function isVitess(): bool
    {
        if (self::$isVitess !== null) {
            return self::$isVitess;
        }

        $declared = self::declaredEngine();
        if ($declared === 'vitess') {
            self::$isVitess = true;
            return true;
        }

        $haystack = self::serverVersion() . ' ' . self::versionComment();
        self::$isVitess = stripos($haystack, 'vitess') !== false;
        return self::$isVitess;
    }

    public static function isSingleStore(): bool
    {
        if (self::$isSingleStore !== null) {
            return self::$isSingleStore;
        }

        $declared = self::declaredEngine();
        if (in_array($declared, ['singlestore', 'memsql'], true)) {
            self::$isSingleStore = true;
            return true;
        }

        $haystack = self::serverVersion() . ' ' . self::versionComment();
        self::$isSingleStore = stripos($haystack, 'singlestore') !== false || stripos($haystack, 'memsql') !== false;
        return self::$isSingleStore;
    }

    public static function isClickHouse(): bool
    {
        if (self::$isClickHouse !== null) {
            return self::$isClickHouse;
        }

        $declared = self::declaredEngine();
        if ($declared === 'clickhouse') {
            self::$isClickHouse = true;
            return true;
        }

        $haystack = self::serverVersion();
        self::$isClickHouse = stripos($haystack, 'clickhouse') !== false;
        return self::$isClickHouse;
    }

    public static function serverVersion(): string
    {
        if (self::$serverVersion !== null) {
            return self::$serverVersion;
        }

        if (self::declaredEngine() === 'clickhouse') {
            self::$serverVersion = (string) ClickHouseClient::scalar('SELECT version()');
            return self::$serverVersion;
        }

        $pdo = self::pdo();
        self::$serverVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        return self::$serverVersion;
    }

    public static function versionComment(): string
    {
        if (self::$versionComment !== null) {
            return self::$versionComment;
        }

        if (self::declaredEngine() === 'clickhouse') {
            self::$versionComment = 'ClickHouse';
            return self::$versionComment;
        }

        try {
            $pdo = self::pdo();
            self::$versionComment = (string) $pdo->query('SELECT @@version_comment')->fetchColumn();
        } catch (Throwable) {
            self::$versionComment = '';
        }

        return self::$versionComment;
    }

    private static function declaredEngine(): string
    {
        return strtolower(trim((string) Env::get('DB_ENGINE', '')));
    }

    public static function isMySqlVersionAtLeast(string $minimum): bool
    {
        if (self::isMariaDb()) {
            return false;
        }
        if (self::isClickHouse()) {
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
            if (self::isClickHouse()) {
                $count = ClickHouseClient::scalar(
                    "SELECT count() FROM system.processes WHERE query_id != currentQueryID()"
                );
                return is_numeric($count) ? (int) $count : 0;
            }
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

    public static function connectionId(PDO $pdo): int
    {
        $value = $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function processlistRowById(PDO $pdo, int $connectionId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO
             FROM information_schema.PROCESSLIST
             WHERE ID = ?"
        );
        $stmt->execute([$connectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function killQuery(PDO $pdo, int $connectionId): void
    {
        $pdo->exec('KILL QUERY ' . max(0, $connectionId));
    }

}
