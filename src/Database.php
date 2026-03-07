<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('DB_HOST', '127.0.0.1');
        $port = Config::getInt('DB_PORT', 3306);
        $db   = (string) Config::get('DB_NAME', '');
        $user = (string) Config::get('DB_USER', '');
        $pass = (string) Config::get('DB_PASS', '');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $db
        );

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            Http::json([
                'ok' => false,
                'error' => 'Database connection failed',
                'details' => $e->getMessage(),
            ], 500);
        }

        return self::$pdo;
    }
}
