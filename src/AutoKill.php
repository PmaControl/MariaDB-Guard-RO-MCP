<?php

declare(strict_types=1);

final class AutoKill
{
    public static function enabledForQuery(string $toolName, string $sql): bool
    {
        if (!Env::getBool('AUTO_KILL_DB_SELECT', false)) {
            return false;
        }

        if ($toolName !== 'db_select') {
            return false;
        }

        $timeoutSeconds = Env::getInt('MAX_SELECT_TIME_S', 5);
        if ($timeoutSeconds <= 0) {
            return false;
        }

        $normalized = SqlGuard::stripComments($sql);
        return preg_match('/^(select|with)\b/i', $normalized) === 1;
    }

    public static function start(PDO $queryPdo, string $toolName, string $originalSql, string $executedSql): ?array
    {
        if (!self::enabledForQuery($toolName, $originalSql)) {
            return null;
        }

        $phpBinary = PHP_BINARY;
        if ($phpBinary === '' || !is_file($phpBinary)) {
            QueryLogger::log([
                'event' => 'mcp_auto_kill',
                'status' => 'disabled',
                'reason' => 'php_binary_unavailable',
                'tool' => $toolName,
            ]);
            return null;
        }

        $monitorScript = dirname(__DIR__) . '/bin/mcp-auto-kill-monitor.php';
        if (!is_file($monitorScript)) {
            QueryLogger::log([
                'event' => 'mcp_auto_kill',
                'status' => 'disabled',
                'reason' => 'monitor_script_missing',
                'tool' => $toolName,
            ]);
            return null;
        }

        $token = bin2hex(random_bytes(12));
        $stateDir = self::stateDirectory();
        if (!is_dir($stateDir) && !@mkdir($stateDir, 0775, true) && !is_dir($stateDir)) {
            QueryLogger::log([
                'event' => 'mcp_auto_kill',
                'status' => 'disabled',
                'reason' => 'state_dir_unavailable',
                'tool' => $toolName,
            ]);
            return null;
        }

        $stopFile = $stateDir . '/' . $token . '.stop';
        $payload = [
            'token' => $token,
            'connectionId' => Db::connectionId($queryPdo),
            'tool' => $toolName,
            'dbUser' => Db::dbUser(),
            'dbName' => (string) Env::get('DB_NAME', ''),
            'sqlOriginal' => $originalSql,
            'sqlExecuted' => $executedSql,
            'timeoutMs' => self::timeoutMs(),
            'pollMs' => max(50, Env::getInt('AUTO_KILL_POLL_MS', 200)),
            'stopFile' => $stopFile,
            'envFile' => getenv('ENV_FILE') ?: (dirname(__DIR__) . '/.env'),
        ];

        $command = [
            $phpBinary,
            $monitorScript,
            base64_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];

        $devNull = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $devNull, 'r'],
            1 => ['file', $devNull, 'a'],
            2 => ['file', $devNull, 'a'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            QueryLogger::log([
                'event' => 'mcp_auto_kill',
                'status' => 'disabled',
                'reason' => 'monitor_start_failed',
                'tool' => $toolName,
                'connectionId' => $payload['connectionId'],
            ]);
            return null;
        }

        QueryLogger::log([
            'event' => 'mcp_auto_kill',
            'status' => 'armed',
            'tool' => $toolName,
            'connectionId' => $payload['connectionId'],
            'timeoutMs' => $payload['timeoutMs'],
            'token' => $token,
        ]);

        return [
            'process' => $process,
            'stopFile' => $stopFile,
            'token' => $token,
            'connectionId' => $payload['connectionId'],
        ];
    }

    public static function stop(?array $handle): void
    {
        if ($handle === null) {
            return;
        }

        $stopFile = (string) ($handle['stopFile'] ?? '');
        if ($stopFile !== '') {
            @file_put_contents($stopFile, 'stop');
        }

        $process = $handle['process'] ?? null;
        if (is_resource($process)) {
            $status = @proc_get_status($process);
            if (is_array($status) && !empty($status['running'])) {
                @proc_terminate($process);
                usleep(100000);
            }
            @proc_close($process);
        }

        if ($stopFile !== '') {
            @unlink($stopFile);
        }
    }

    public static function timeoutMs(): int
    {
        $timeoutSeconds = Env::getInt('MAX_SELECT_TIME_S', 5);
        if ($timeoutSeconds <= 0) {
            return 0;
        }
        return $timeoutSeconds * 1000;
    }

    public static function isKillableProcesslistRow(array $row, int $expectedConnectionId, string $expectedDbUser, string $expectedDbName): bool
    {
        $connectionId = (int) ($row['ID'] ?? 0);
        if ($connectionId !== $expectedConnectionId) {
            return false;
        }

        $command = strtoupper(trim((string) ($row['COMMAND'] ?? '')));
        if (!in_array($command, ['QUERY', 'EXECUTE'], true)) {
            return false;
        }

        $info = trim((string) ($row['INFO'] ?? ''));
        if ($info === '') {
            return false;
        }

        $user = (string) ($row['USER'] ?? '');
        if ($expectedDbUser !== '' && $user !== $expectedDbUser) {
            return false;
        }

        $dbName = (string) ($row['DB'] ?? '');
        if ($expectedDbName !== '' && $dbName !== '' && $dbName !== $expectedDbName) {
            return false;
        }

        return true;
    }

    private static function stateDirectory(): string
    {
        $custom = trim((string) Env::get('AUTO_KILL_STATE_DIR', ''));
        if ($custom !== '') {
            return $custom;
        }

        return dirname(__DIR__) . '/var/auto-kill';
    }
}
