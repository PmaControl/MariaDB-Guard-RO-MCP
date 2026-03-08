<?php

declare(strict_types=1);

final class AccountSecurity
{
    private const CACHE_FILE_NAME = '.account_tested';

    public static function envPath(): string
    {
        return (string) Env::get('ACCOUNT_ENV_FILE', dirname(__DIR__) . '/.env');
    }

    public static function cachePath(): string
    {
        return (string) Env::get('ACCOUNT_TEST_CACHE_FILE', dirname(__DIR__) . '/' . self::CACHE_FILE_NAME);
    }

    public static function runChecklist(bool $forceRefresh = false): array
    {
        self::invalidateCacheIfEnvNewer();
        $cachePath = self::cachePath();

        if ($forceRefresh && is_file($cachePath)) {
            @unlink($cachePath);
        }

        $cached = self::readCache();
        if (is_array($cached)) {
            $cached['fromCache'] = true;
            return $cached;
        }

        $report = self::evaluateAccount();
        self::writeCache($report);
        $report['fromCache'] = false;
        return $report;
    }

    public static function assertMcpAllowed(): void
    {
        $report = self::runChecklist(false);
        if (($report['safe'] ?? false) !== true) {
            $reason = (string)($report['blockReason'] ?? 'MySQL/MariaDB account is not read-only.');
            throw new RuntimeException(
                $reason . ' Run mcp_test. If fixed, update .env (or remove .account_tested) to force retest.'
            );
        }
    }

    public static function invalidateCacheIfEnvNewer(): void
    {
        $envPath = self::envPath();
        $cachePath = self::cachePath();
        if (!is_file($envPath) || !is_file($cachePath)) {
            return;
        }

        $envTime = @filemtime($envPath);
        $cacheTime = @filemtime($cachePath);
        if ($envTime !== false && $cacheTime !== false && $envTime > $cacheTime) {
            @unlink($cachePath);
        }
    }

    private static function evaluateAccount(): array
    {
        $checklist = [];
        $unsafePrivileges = [];
        $grants = [];

        try {
            $pdo = Db::pdo();
            $checklist[] = [
                'id' => 'db_connection',
                'status' => 'pass',
                'title' => 'Database connectivity',
                'details' => 'Connection to server succeeded.',
            ];

            $grants = self::fetchGrants($pdo);
            $unsafePrivileges = self::extractUnsafePrivileges($grants);
            $isReadOnly = count($unsafePrivileges) === 0;

            $checklist[] = [
                'id' => 'account_read_only',
                'status' => $isReadOnly ? 'pass' : 'fail',
                'title' => 'Account privilege scope',
                'details' => $isReadOnly
                    ? 'Only read-only privileges detected (USAGE/SELECT).'
                    : 'Write/modify privileges detected: ' . implode(', ', $unsafePrivileges),
                'howToFix' => $isReadOnly ? '' : 'Remove all write/DDL/admin grants. Keep only SELECT (and USAGE).',
            ];

            $serverMode = self::fetchServerReadOnlyMode($pdo);
            $checklist[] = [
                'id' => 'server_read_only_mode',
                'status' => ($serverMode['readOnly'] || $serverMode['superReadOnly']) ? 'pass' : 'warn',
                'title' => 'Server read-only mode',
                'details' => 'read_only=' . ($serverMode['readOnly'] ? 'ON' : 'OFF')
                    . ', super_read_only=' . ($serverMode['superReadOnly'] ? 'ON' : 'OFF'),
                'howToFix' => ($serverMode['readOnly'] || $serverMode['superReadOnly'])
                    ? ''
                    : 'For production, prefer running this MCP against a read replica.',
            ];
        } catch (Throwable $e) {
            $checklist[] = [
                'id' => 'db_connection',
                'status' => 'fail',
                'title' => 'Database connectivity',
                'details' => $e->getMessage(),
                'howToFix' => 'Check .env credentials, DB reachability, and account validity.',
            ];
        }

        $safe = !in_array('fail', array_map(static fn(array $c): string => (string)($c['status'] ?? ''), $checklist), true);
        $blockReason = '';
        if (!$safe) {
            $blockReason = 'MCP blocked: MySQL/MariaDB account must be strictly read-only.';
        }

        return [
            'safe' => $safe,
            'blocked' => !$safe,
            'blockReason' => $blockReason,
            'checkedAt' => gmdate('c'),
            'cacheFile' => self::cachePath(),
            'envFile' => self::envPath(),
            'grants' => $grants,
            'unsafePrivileges' => $unsafePrivileges,
            'checklist' => $checklist,
            'nextSteps' => $safe
                ? ['Account is read-only and accepted.']
                : [
                    'Revoke all write/DDL/admin privileges from this account.',
                    'Keep only SELECT (and USAGE).',
                    'Then update .env or delete .account_tested to force a new validation.',
                ],
        ];
    }

    private static function fetchGrants(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW GRANTS FOR CURRENT_USER');
        if (!$stmt instanceof PDOStatement) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $grants = [];
        foreach ($rows as $row) {
            foreach ((array)$row as $value) {
                $v = trim((string)$value);
                if ($v !== '') {
                    $grants[] = $v;
                }
            }
        }
        return $grants;
    }

    private static function fetchServerReadOnlyMode(PDO $pdo): array
    {
        $readOnly = false;
        $superReadOnly = false;

        try {
            $stmt = $pdo->query('SELECT @@GLOBAL.read_only AS read_only');
            $row = $stmt instanceof PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (is_array($row)) {
                $readOnly = ((int)($row['read_only'] ?? 0)) === 1;
            }
        } catch (Throwable) {
            $readOnly = false;
        }

        try {
            $stmt = $pdo->query('SELECT @@GLOBAL.super_read_only AS super_read_only');
            $row = $stmt instanceof PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (is_array($row)) {
                $superReadOnly = ((int)($row['super_read_only'] ?? 0)) === 1;
            }
        } catch (Throwable) {
            $superReadOnly = false;
        }

        return [
            'readOnly' => $readOnly,
            'superReadOnly' => $superReadOnly,
        ];
    }

    private static function extractUnsafePrivileges(array $grants): array
    {
        $allowed = ['USAGE', 'SELECT'];
        $unsafe = [];

        foreach ($grants as $grant) {
            $upper = strtoupper($grant);
            if (!str_starts_with($upper, 'GRANT ')) {
                continue;
            }

            if (str_contains($upper, ' ON ')) {
                if (!preg_match('/^GRANT\s+(.+?)\s+ON\s+/i', $grant, $m)) {
                    continue;
                }
                $privPart = strtoupper(trim((string)$m[1]));
                $privPart = str_replace('ALL PRIVILEGES', 'ALL_PRIVILEGES', $privPart);
                $tokens = array_map(
                    static fn(string $t): string => trim(str_replace('ALL_PRIVILEGES', 'ALL PRIVILEGES', $t), " `"),
                    explode(',', $privPart)
                );
                foreach ($tokens as $token) {
                    if ($token === '' || in_array($token, $allowed, true)) {
                        continue;
                    }
                    $unsafe[$token] = true;
                }
                continue;
            }

            // Grant forms without ON (roles/proxy) are treated as unsafe for strict read-only mode.
            if (str_contains($upper, ' TO ')) {
                $unsafe['ROLE_OR_PROXY_GRANT'] = true;
            }
        }

        $result = array_keys($unsafe);
        sort($result);
        return $result;
    }

    private static function readCache(): ?array
    {
        $cachePath = self::cachePath();
        if (!is_file($cachePath)) {
            return null;
        }
        $raw = @file_get_contents($cachePath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function writeCache(array $report): void
    {
        $cachePath = self::cachePath();
        $report['fromCache'] = false;
        @file_put_contents($cachePath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
        if (is_file($cachePath)) {
            @chown($cachePath, 'aurelien');
            @chgrp($cachePath, 'aurelien');
        }
    }
}
