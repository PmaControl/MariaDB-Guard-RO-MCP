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

    public static function pingServer(?string $host = null, ?int $port = null, float $timeoutSeconds = 1.5): array
    {
        $targetHost = $host !== null && $host !== '' ? $host : (string) Env::get('DB_HOST', '127.0.0.1');
        $targetPort = $port !== null && $port > 0 ? $port : Env::getInt('DB_PORT', 3306);
        $timeoutSeconds = $timeoutSeconds > 0 ? $timeoutSeconds : 1.5;

        $startedAt = microtime(true);
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($targetHost, $targetPort, $errno, $errstr, $timeoutSeconds);
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (is_resource($socket)) {
            fclose($socket);
            return [
                'target' => $targetHost,
                'port' => $targetPort,
                'reachable' => true,
                'latencyMs' => $latencyMs,
                'mode' => 'tcp',
            ];
        }

        return [
            'target' => $targetHost,
            'port' => $targetPort,
            'reachable' => false,
            'latencyMs' => $latencyMs,
            'mode' => 'tcp',
            'errorCode' => $errno,
            'error' => $errstr !== '' ? $errstr : 'Connection failed',
        ];
    }
}
