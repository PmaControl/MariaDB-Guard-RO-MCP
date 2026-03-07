<?php

declare(strict_types=1);

final class SqlGuard
{
    public static function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    public static function stripComments(string $sql): string
    {
        $sql = preg_replace('!/\\*.*?\\*/!s', ' ', $sql) ?? $sql;
//        $sql = preg_replace('!/*.*?*/!s', ' ', $sql) ?? $sql;
        $sql = preg_replace('/^\s*--.*$/m', ' ', $sql) ?? $sql;
        $sql = preg_replace('/^\s*#.*$/m', ' ', $sql) ?? $sql;
        return trim($sql);
    }

    public static function validateReadOnlyQuery(string $sql): string
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new InvalidArgumentException('SQL is empty');
        }

        $clean = self::stripComments($sql);
        $normalized = self::lower($clean);

        if (str_contains($normalized, ';')) {
            throw new InvalidArgumentException('Multi-statements are not allowed');
        }

        if (!preg_match('/^(select|show|explain)\b/', trim($normalized))) {
            throw new InvalidArgumentException('Only SELECT, SHOW and EXPLAIN are allowed');
        }

        $forbidden = [
            'into outfile',
            'into dumpfile',
            'load_file(',
            'load data',
            'outfile',
            'dumpfile',
        ];

        foreach ($forbidden as $item) {
            if (str_contains($normalized, $item)) {
                throw new InvalidArgumentException('Forbidden SQL clause detected: ' . $item);
            }
        }

        return $sql;
    }

    public static function applyLimitIfMissing(string $sql, int $maxRows): string
    {
        $normalized = self::lower(self::stripComments($sql));

        if (preg_match('/^(show|explain)\b/', trim($normalized))) {
            return $sql;
        }

        if (preg_match('/\blimit\b/', $normalized)) {
            return $sql;
        }

        return rtrim($sql) . PHP_EOL . 'LIMIT ' . max(1, $maxRows);
    }

    public static function ensureIdentifier(string $value, string $name = 'identifier'): string
    {
        if (!preg_match('/^[A-Za-z0-9$_-]+$/', $value)) {
            throw new InvalidArgumentException("Invalid {$name}: {$value}");
        }
        return $value;
    }
}
