<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

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
}
