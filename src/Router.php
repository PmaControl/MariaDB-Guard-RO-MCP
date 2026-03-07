<?php

declare(strict_types=1);

namespace App;

use Throwable;
use PDO;

final class Router
{
    public static function dispatch(): void
    {
        $method = Http::method();
        $path   = Http::path();

        if ($method === 'GET' && $path === '/health') {
            Http::json([
                'ok' => true,
                'service' => 'php-mcp-mysql',
                'status' => 'healthy',
            ]);
        }

        if ($method === 'GET' && $path === '/mcp') {
            Http::requireTokenIfConfigured();

            Http::json([
                'ok' => true,
                'server' => [
                    'name' => 'php-mcp-mysql',
                    'version' => '1.0.0',
                    'description' => 'Read-only MCP-like server for MySQL/MariaDB',
                ],
                'tools' => [
                    [
                        'name' => 'db_select',
                        'description' => 'Execute a read-only SQL query (SELECT, SHOW, EXPLAIN)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'sql' => [
                                    'type' => 'string',
                                    'description' => 'SQL read-only query'
                                ],
                                'params' => [
                                    'type' => 'array',
                                    'description' => 'Prepared statement parameters',
                                    'items' => []
                                ],
                                'maxRows' => [
                                    'type' => 'integer',
                                    'minimum' => 1,
                                    'maximum' => Config::getInt('MAX_ROWS_HARD', 5000),
                                ],
                            ],
                            'required' => ['sql'],
                        ],
                    ],
                ],
                'endpoints' => [
                    'health' => '/health',
                    'describe' => '/mcp',
                    'db_select' => '/mcp/tool/db_select',
                ],
            ]);
        }

        if ($method === 'POST' && $path === '/mcp/tool/db_select') {
            Http::requireTokenIfConfigured();
            self::handleDbSelect();
        }

        Http::json([
            'ok' => false,
            'error' => 'Not found',
            'method' => $method,
            'path' => $path,
        ], 404);
    }

    private static function handleDbSelect(): void
    {
        try {
            $body = Http::getJsonBody();

            $sql = isset($body['sql']) ? (string) $body['sql'] : '';
            $params = isset($body['params']) && is_array($body['params']) ? array_values($body['params']) : [];
            $maxRows = isset($body['maxRows']) ? (int) $body['maxRows'] : Config::getInt('MAX_ROWS_DEFAULT', 200);
            $hardMax = Config::getInt('MAX_ROWS_HARD', 5000);

            if ($maxRows < 1) {
                $maxRows = 1;
            }
            if ($maxRows > $hardMax) {
                $maxRows = $hardMax;
            }

            $sql = SqlGuard::validateReadOnly($sql);
            $sql = SqlGuard::enforceLimit($sql, $maxRows);

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare($sql);

            foreach ($params as $index => $value) {
                $paramType = self::detectPdoType($value);
                $stmt->bindValue($index + 1, $value, $paramType);
            }

            $stmt->execute();
            $rows = $stmt->fetchAll();

            $columns = [];
            $count = $stmt->columnCount();
            for ($i = 0; $i < $count; $i++) {
                $meta = $stmt->getColumnMeta($i);
                $columns[] = [
                    'name' => $meta['name'] ?? null,
                    'table' => $meta['table'] ?? null,
                    'native_type' => $meta['native_type'] ?? null,
                    'pdo_type' => $meta['pdo_type'] ?? null,
                ];
            }

            Http::json([
                'ok' => true,
                'query' => $sql,
                'rowCount' => count($rows),
                'columns' => $columns,
                'rows' => $rows,
            ]);
        } catch (Throwable $e) {
            Http::json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private static function detectPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }
}
