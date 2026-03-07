<?php

declare(strict_types=1);

final class QueryLogger
{
    public static function log(array $entry): void
    {
        $entry['timestamp'] = gmdate('c');
        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        $line = $json . PHP_EOL;
        $path = self::logPath();
        $written = @error_log($line, 3, $path);
        if ($written === false) {
            error_log('[mcp-query-log] ' . $json);
        }
    }

    public static function formatSql(string $sql): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
        if ($normalized === '') {
            return '';
        }

        $patterns = [
            '/\bSELECT\b/i',
            '/\bFROM\b/i',
            '/\bWHERE\b/i',
            '/\bGROUP BY\b/i',
            '/\bORDER BY\b/i',
            '/\bHAVING\b/i',
            '/\bLIMIT\b/i',
            '/\bLEFT JOIN\b/i',
            '/\bRIGHT JOIN\b/i',
            '/\bINNER JOIN\b/i',
            '/\bJOIN\b/i',
            '/\bUNION(?: ALL)?\b/i',
            '/\bVALUES\b/i',
            '/\bSET\b/i',
            '/\bFOR UPDATE\b/i',
        ];

        $formatted = $normalized;
        foreach ($patterns as $pattern) {
            $formatted = preg_replace($pattern, "\n$0", $formatted) ?? $formatted;
        }

        return ltrim($formatted);
    }

    private static function logPath(): string
    {
        return (string) Env::get('MCP_QUERY_LOG', '/tmp/mcp_mariadb_query.log');
    }
}
