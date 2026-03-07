<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

final class SqlGuard
{
    public static function validateReadOnly(string $sql): string
    {
        $sql = trim($sql);

        if ($sql === '') {
            throw new InvalidArgumentException('SQL is empty');
        }

        $clean = self::stripComments($sql);
        $normalized = mb_strtolower(trim($clean), 'UTF-8');

        if ($normalized === '') {
            throw new InvalidArgumentException('SQL is empty after comment stripping');
        }

        if (str_contains($normalized, ';')) {
            throw new InvalidArgumentException('Multi-statements are not allowed');
        }

        if (!preg_match('/^(select|show|explain)\b/u', $normalized)) {
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

        foreach ($forbidden as $word) {
            if (str_contains($normalized, $word)) {
                throw new InvalidArgumentException('Forbidden SQL clause detected: ' . $word);
            }
        }

        return $sql;
    }

    public static function enforceLimit(string $sql, int $maxRows): string
    {
        $normalized = mb_strtolower(self::stripComments($sql), 'UTF-8');

        if (preg_match('/\blimit\b/u', $normalized)) {
            return $sql;
        }

        if (preg_match('/^(show|explain)\b/u', trim($normalized))) {
            return $sql;
        }

        return rtrim($sql) . PHP_EOL . 'LIMIT ' . max(1, $maxRows);
    }

    private static function stripComments(string $sql): string
    {
        $sql = preg_replace('!/\*.*?\*/!s', ' ', $sql) ?? $sql;
        $sql = preg_replace('/^\s*--.*$/m', ' ', $sql) ?? $sql;
        $sql = preg_replace('/^\s*#.*$/m', ' ', $sql) ?? $sql;

        return trim($sql);
    }
}
