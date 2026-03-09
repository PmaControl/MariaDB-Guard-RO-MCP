#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composerAutoload = $root . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    require_once $root . '/src/Env.php';
    require_once $root . '/src/Http.php';
    require_once $root . '/src/Db.php';
    require_once $root . '/src/AccountSecurity.php';
    require_once $root . '/src/SqlGuard.php';
    require_once $root . '/src/JsonRpc.php';
    require_once $root . '/src/QueryLogger.php';
    require_once $root . '/src/AutoKill.php';
    require_once $root . '/src/Tools.php';
    require_once $root . '/src/App.php';
}

$encoded = $argv[1] ?? '';
if ($encoded === '') {
    exit(1);
}

$json = base64_decode($encoded, true);
if (!is_string($json) || $json === '') {
    exit(1);
}

$payload = json_decode($json, true);
if (!is_array($payload)) {
    exit(1);
}

$envFile = (string) ($payload['envFile'] ?? ($root . '/.env'));
if (is_file($envFile)) {
    Env::load($envFile);
}

$timeoutMs = max(0, (int) ($payload['timeoutMs'] ?? 0));
$pollMs = max(50, (int) ($payload['pollMs'] ?? 200));
$stopFile = (string) ($payload['stopFile'] ?? '');
$connectionId = (int) ($payload['connectionId'] ?? 0);
$tool = (string) ($payload['tool'] ?? 'db_select');
$token = (string) ($payload['token'] ?? '');
$dbUser = (string) ($payload['dbUser'] ?? '');
$dbName = (string) ($payload['dbName'] ?? '');
$sqlOriginal = (string) ($payload['sqlOriginal'] ?? '');

if ($connectionId <= 0 || $timeoutMs <= 0) {
    exit(0);
}

$deadline = microtime(true) + ($timeoutMs / 1000);
while (microtime(true) < $deadline) {
    if ($stopFile !== '' && is_file($stopFile)) {
        exit(0);
    }
    usleep($pollMs * 1000);
}

if ($stopFile !== '' && is_file($stopFile)) {
    exit(0);
}

try {
    $monitorPdo = Db::freshPdo();
    $row = Db::processlistRowById($monitorPdo, $connectionId);
    if ($row === null) {
        QueryLogger::log([
            'event' => 'mcp_auto_kill',
            'status' => 'skipped',
            'reason' => 'process_not_found',
            'tool' => $tool,
            'token' => $token,
            'connectionId' => $connectionId,
        ]);
        exit(0);
    }

    if (!AutoKill::isKillableProcesslistRow($row, $connectionId, $dbUser, $dbName)) {
        QueryLogger::log([
            'event' => 'mcp_auto_kill',
            'status' => 'skipped',
            'reason' => 'ownership_not_proven',
            'tool' => $tool,
            'token' => $token,
            'connectionId' => $connectionId,
            'processlist' => $row,
        ]);
        exit(0);
    }

    Db::killQuery($monitorPdo, $connectionId);
    QueryLogger::log([
        'event' => 'mcp_auto_kill',
        'status' => 'killed',
        'tool' => $tool,
        'token' => $token,
        'connectionId' => $connectionId,
        'dbUser' => $dbUser,
        'dbName' => $dbName,
        'sqlOriginal' => QueryLogger::formatSql($sqlOriginal),
    ]);
} catch (Throwable $e) {
    QueryLogger::log([
        'event' => 'mcp_auto_kill',
        'status' => 'error',
        'tool' => $tool,
        'token' => $token,
        'connectionId' => $connectionId,
        'error' => $e->getMessage(),
    ]);
    exit(1);
}

exit(0);
