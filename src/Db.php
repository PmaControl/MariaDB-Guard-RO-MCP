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

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) Env::get('DB_HOST', '127.0.0.1'),
            Env::getInt('DB_PORT', 3306),
            (string) Env::get('DB_NAME', '')
        );

        self::$pdo = new PDO(
            $dsn,
            (string) Env::get('DB_USER', ''),
            (string) Env::get('DB_PASS', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$pdo;
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
