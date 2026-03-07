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

        if (preg_match('/^\s*with\s+recursive\b/', $normalized)) {
            throw new InvalidArgumentException('WITH RECURSIVE is not allowed');
        }

        $statement = self::leadingStatementKeyword($normalized);
        if (!in_array($statement, ['select', 'show', 'explain'], true)) {
            throw new InvalidArgumentException('Only SELECT, SHOW, EXPLAIN and non-recursive CTE are allowed');
        }

        if (preg_match('/\bfor\s+update\b/', $normalized)) {
            throw new InvalidArgumentException('SELECT ... FOR UPDATE is not allowed');
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

    private static function leadingStatementKeyword(string $normalized): string
    {
        $sql = ltrim($normalized);
        if ($sql === '') {
            return '';
        }

        if (!str_starts_with($sql, 'with')) {
            return self::readWordAt($sql, 0);
        }

        $len = strlen($sql);
        $i = 4; // "with"
        self::skipWhitespace($sql, $i, $len);
        if (self::readWordAt($sql, $i) === 'recursive') {
            return 'with_recursive';
        }

        while ($i < $len) {
            self::skipIdentifier($sql, $i, $len);
            if ($i >= $len) {
                return '';
            }

            self::skipWhitespace($sql, $i, $len);
            if ($i < $len && $sql[$i] === '(') {
                if (!self::skipParenthesized($sql, $i, $len)) {
                    return '';
                }
                self::skipWhitespace($sql, $i, $len);
            }

            if (self::readWordAt($sql, $i) !== 'as') {
                return '';
            }
            $i += 2;
            self::skipWhitespace($sql, $i, $len);

            if ($i >= $len || $sql[$i] !== '(') {
                return '';
            }
            if (!self::skipParenthesized($sql, $i, $len)) {
                return '';
            }

            self::skipWhitespace($sql, $i, $len);
            if ($i < $len && $sql[$i] === ',') {
                $i++;
                self::skipWhitespace($sql, $i, $len);
                continue;
            }
            break;
        }

        return self::readWordAt($sql, $i);
    }

    private static function readWordAt(string $sql, int $offset): string
    {
        $len = strlen($sql);
        $i = $offset;
        self::skipWhitespace($sql, $i, $len);
        if ($i >= $len) {
            return '';
        }

        if (!preg_match('/[a-z_]/', $sql[$i])) {
            return '';
        }

        $start = $i;
        $i++;
        while ($i < $len && preg_match('/[a-z_]/', $sql[$i])) {
            $i++;
        }
        return substr($sql, $start, $i - $start);
    }

    private static function skipWhitespace(string $sql, int &$i, int $len): void
    {
        while ($i < $len && ctype_space($sql[$i])) {
            $i++;
        }
    }

    private static function skipIdentifier(string $sql, int &$i, int $len): void
    {
        while ($i < $len && preg_match('/[`a-z0-9_$.-]/', $sql[$i])) {
            $i++;
        }
    }

    private static function skipParenthesized(string $sql, int &$i, int $len): bool
    {
        if ($i >= $len || $sql[$i] !== '(') {
            return false;
        }
        $depth = 0;
        while ($i < $len) {
            $ch = $sql[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $i++;
                    return true;
                }
            }
            $i++;
        }
        return false;
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
