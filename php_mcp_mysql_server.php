<?php

declare(strict_types=1);

/**
 * MCP-like PHP server for MariaDB/MySQL (read-mostly DBA tools)
 *
 * Endpoints:
 *   GET  /health
 *   POST /mcp              JSON-RPC 2.0
 *
 * Implemented methods:
 *   initialize
 *   tools/list
 *   tools/call
 *
 * Tools:
 *   db_select
 *   db_tables
 *   db_schema
 *   db_indexes
 *   db_explain
 *   db_processlist
 *   db_variables
 *
 * Run:
 *   php -S 127.0.0.1:8787 php_mcp_mysql_server.php
 *
 * .env example:
 *   DB_HOST=127.0.0.1
 *   DB_PORT=3306
 *   DB_NAME=pmacontrol
 *   DB_USER=mcp_ro
 *   DB_PASS=secret
 *   MCP_TOKEN=change_me
 *   MAX_ROWS_DEFAULT=200
 *   MAX_ROWS_HARD=5000
 */

final class Env
{
    private static array $data = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"'");

            self::$data[$key] = $value;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? self::$data[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }
}

final class Http
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    public static function rawBody(): string
    {
        $raw = file_get_contents('php://input');
        return $raw === false ? '' : $raw;
    }

    public static function json(mixed $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function bearerToken(): ?string
    {

	$header = $_SERVER['HTTP_AUTHORIZATION'] 
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
        ?? null;

        if ($header === null) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return null;

    }

    public static function isAuthorized(): bool
    {
        $expected = Env::get('MCP_TOKEN', '');
        if ($expected === '') {
            return true;
        }
        $received = self::bearerToken();
        return hash_equals($expected, (string) $received);
    }
}

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

final class JsonRpc
{
    public static function success(mixed $id, mixed $result): never
    {
        Http::json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    public static function error(mixed $id, int $code, string $message, mixed $data = null, int $httpStatus = 200): never
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== null) {
            $payload['error']['data'] = $data;
        }

        Http::json($payload, $httpStatus);
    }
}

final class Tools
{
    public static function definitions(): array
    {
        return [
            [
                'name' => 'db_select',
                'description' => 'Execute a read-only SQL query (SELECT, SHOW, EXPLAIN).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'sql' => ['type' => 'string'],
                        'params' => ['type' => 'array',
                        'items' => [
                            'anyOf' => [
                                ['type' => 'string'],
                                ['type' => 'integer'],
                                ['type' => 'number'],
                                ['type' => 'boolean'],
                                ['type' => 'null']
                            ]
                        ]
		    ],
                        'maxRows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => Env::getInt('MAX_ROWS_HARD', 5000)],
                    ],
                    'required' => ['sql'],
                ],
            ],
            [
                'name' => 'db_tables',
                'description' => 'List tables for a schema, with engine, row estimate, size and collation.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'schema' => ['type' => 'string'],
                        'tableLike' => ['type' => 'string'],
                        'maxRows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => Env::getInt('MAX_ROWS_HARD', 5000)],
                    ],
                    'required' => ['schema'],
                ],
            ],
            [
                'name' => 'db_schema',
                'description' => 'Describe columns of one table: types, nullability, defaults, keys, extras.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'schema' => ['type' => 'string'],
                        'table' => ['type' => 'string'],
                    ],
                    'required' => ['schema', 'table'],
                ],
            ],
            [
                'name' => 'db_indexes',
                'description' => 'List indexes for one table, including columns and cardinality.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'schema' => ['type' => 'string'],
                        'table' => ['type' => 'string'],
                    ],
                    'required' => ['schema', 'table'],
                ],
            ],
            [
                'name' => 'db_explain',
                'description' => 'Run EXPLAIN FORMAT=JSON when possible, otherwise classic EXPLAIN, on a SELECT query.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'sql' => ['type' => 'string'],
                        'params' => ['type' => 'array', 
			 'items' => [
                            'anyOf' => [
                                ['type' => 'string'],
                                ['type' => 'integer'],
                                ['type' => 'number'],
                                ['type' => 'boolean'],
                                ['type' => 'null']
                            ]
                        ]
			],
                    ],
                    'required' => ['sql'],
                ],
            ],
            [
                'name' => 'db_processlist',
                'description' => 'Show processlist, optionally filtered to active sessions only.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'activeOnly' => ['type' => 'boolean'],
                        'maxRows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => Env::getInt('MAX_ROWS_HARD', 5000)],
                    ],
                ],
            ],
            [
                'name' => 'db_variables',
                'description' => 'Show server variables, optionally filtered by pattern.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'like' => ['type' => 'string'],
                        'scope' => ['type' => 'string', 'enum' => ['global', 'session']],
                        'maxRows' => ['type' => 'integer', 'minimum' => 1, 'maximum' => Env::getInt('MAX_ROWS_HARD', 5000)],
                    ],
                ],
            ],
        ];
    }

    public static function call(string $name, array $args): array
    {
        return match ($name) {
            'db_select' => self::dbSelect($args),
            'db_tables' => self::dbTables($args),
            'db_schema' => self::dbSchema($args),
            'db_indexes' => self::dbIndexes($args),
            'db_explain' => self::dbExplain($args),
            'db_processlist' => self::dbProcesslist($args),
            'db_variables' => self::dbVariables($args),
            default => throw new InvalidArgumentException('Unknown tool: ' . $name),
        };
    }

    private static function dbSelect(array $args): array
    {
        $sql = (string)($args['sql'] ?? '');
        $params = isset($args['params']) && is_array($args['params']) ? array_values($args['params']) : [];
        $maxRows = self::boundedMaxRows($args['maxRows'] ?? Env::getInt('MAX_ROWS_DEFAULT', 200));

        $sql = SqlGuard::validateReadOnlyQuery($sql);
        $sql = SqlGuard::applyLimitIfMissing($sql, $maxRows);

        return self::runPreparedQuery($sql, $params);
    }

    private static function dbTables(array $args): array
    {
        $schema = SqlGuard::ensureIdentifier((string)($args['schema'] ?? ''), 'schema');
        $tableLike = isset($args['tableLike']) ? (string)$args['tableLike'] : null;
        $maxRows = self::boundedMaxRows($args['maxRows'] ?? Env::getInt('MAX_ROWS_DEFAULT', 200));

        $sql = "SELECT
                    TABLE_SCHEMA,
                    TABLE_NAME,
                    TABLE_TYPE,
                    ENGINE,
                    TABLE_ROWS,
                    DATA_LENGTH,
                    INDEX_LENGTH,
                    DATA_FREE,
                    ROUND((COALESCE(DATA_LENGTH,0) + COALESCE(INDEX_LENGTH,0)) / 1024 / 1024, 2) AS total_mb,
                    AUTO_INCREMENT,
                    TABLE_COLLATION,
                    CREATE_TIME,
                    UPDATE_TIME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?";

        $params = [$schema];

        if ($tableLike !== null && $tableLike !== '') {
            $sql .= " AND TABLE_NAME LIKE ?";
            $params[] = $tableLike;
        }

        $sql .= " ORDER BY TABLE_NAME LIMIT {$maxRows}";

        return self::runPreparedQuery($sql, $params);
    }

    private static function dbSchema(array $args): array
    {
        $schema = SqlGuard::ensureIdentifier((string)($args['schema'] ?? ''), 'schema');
        $table = SqlGuard::ensureIdentifier((string)($args['table'] ?? ''), 'table');

        $sql = "SELECT
                    TABLE_SCHEMA,
                    TABLE_NAME,
                    ORDINAL_POSITION,
                    COLUMN_NAME,
                    COLUMN_TYPE,
                    DATA_TYPE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    COLUMN_KEY,
                    EXTRA,
                    CHARACTER_SET_NAME,
                    COLLATION_NAME,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE,
                    DATETIME_PRECISION,
                    COLUMN_COMMENT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";

        return self::runPreparedQuery($sql, [$schema, $table]);
    }

    private static function dbIndexes(array $args): array
    {
        $schema = SqlGuard::ensureIdentifier((string)($args['schema'] ?? ''), 'schema');
        $table = SqlGuard::ensureIdentifier((string)($args['table'] ?? ''), 'table');

        $sql = "SELECT
                    TABLE_SCHEMA,
                    TABLE_NAME,
                    INDEX_NAME,
                    NON_UNIQUE,
                    SEQ_IN_INDEX,
                    COLUMN_NAME,
                    COLLATION,
                    CARDINALITY,
                    SUB_PART,
                    NULLABLE,
                    INDEX_TYPE,
                    COMMENT,
                    INDEX_COMMENT
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX";

        return self::runPreparedQuery($sql, [$schema, $table]);
    }

    private static function dbExplain(array $args): array
    {
        $sql = (string)($args['sql'] ?? '');
        $params = isset($args['params']) && is_array($args['params']) ? array_values($args['params']) : [];

        $sql = trim($sql);
        $sql = SqlGuard::validateReadOnlyQuery($sql);
        if (!preg_match('/^select\b/i', SqlGuard::stripComments($sql))) {
            throw new InvalidArgumentException('db_explain only accepts SELECT queries');
        }

        try {
            return self::runPreparedQuery('EXPLAIN FORMAT=JSON ' . $sql, $params);
        } catch (Throwable $e) {
            return self::runPreparedQuery('EXPLAIN ' . $sql, $params);
        }
    }

    private static function dbProcesslist(array $args): array
    {
        $activeOnly = (bool)($args['activeOnly'] ?? true);
        $maxRows = self::boundedMaxRows($args['maxRows'] ?? 100);

        $sql = "SELECT
                    ID,
                    USER,
                    HOST,
                    DB,
                    COMMAND,
                    TIME,
                    STATE,
                    INFO
                FROM information_schema.PROCESSLIST";

        if ($activeOnly) {
            $sql .= " WHERE COMMAND <> 'Sleep'";
        }

        $sql .= " ORDER BY TIME DESC LIMIT {$maxRows}";

        return self::runPreparedQuery($sql, []);
    }

    private static function dbVariables(array $args): array
    {
        $scope = strtolower((string)($args['scope'] ?? 'global'));
        $like = isset($args['like']) ? (string)$args['like'] : null;
        $maxRows = self::boundedMaxRows($args['maxRows'] ?? 500);

        if (!in_array($scope, ['global', 'session'], true)) {
            throw new InvalidArgumentException('scope must be global or session');
        }

        $source = $scope === 'session'
            ? 'information_schema.SESSION_VARIABLES'
            : 'information_schema.GLOBAL_VARIABLES';

        $sql = "SELECT VARIABLE_NAME, VARIABLE_VALUE FROM {$source}";
        $params = [];

        if ($like !== null && $like !== '') {
            $sql .= " WHERE VARIABLE_NAME LIKE ?";
            $params[] = $like;
        }

        $sql .= " ORDER BY VARIABLE_NAME LIMIT {$maxRows}";

        return self::runPreparedQuery($sql, $params);
    }

    private static function runPreparedQuery(string $sql, array $params): array
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare($sql);

        foreach (array_values($params) as $i => $value) {
            $stmt->bindValue($i + 1, $value, self::pdoType($value));
        }

        $stmt->execute();
        $rows = $stmt->fetchAll();

        $columns = [];
        $columnCount = $stmt->columnCount();
        for ($i = 0; $i < $columnCount; $i++) {
            $meta = $stmt->getColumnMeta($i) ?: [];
            $columns[] = [
                'name' => $meta['name'] ?? null,
                'table' => $meta['table'] ?? null,
                'native_type' => $meta['native_type'] ?? null,
                'pdo_type' => $meta['pdo_type'] ?? null,
            ];
        }

        return [
            'sql' => $sql,
            'rowCount' => count($rows),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private static function pdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    private static function boundedMaxRows(mixed $value): int
    {
        $hardMax = Env::getInt('MAX_ROWS_HARD', 5000);
        $rows = (int)$value;
        if ($rows < 1) {
            $rows = 1;
        }
        if ($rows > $hardMax) {
            $rows = $hardMax;
        }
        return $rows;
    }
}

final class App
{
    private static function baseUrl(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    private static function isMcpPath(string $path): bool
    {
        return in_array($path, ['/', '/mcp', '/index.php', '/public/index.php'], true);
    }

    public static function run(): void
    {

        Env::load(__DIR__ . '/.env');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        if ($origin === '') {
            $origin = '*';
        }
        $requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']
            ?? 'Content-Type, Authorization, MCP-Session-Id, mcp-session-id, MCP-Protocol-Version, mcp-protocol-version, X-Requested-With';

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
        header('Access-Control-Allow-Headers: ' . $requestedHeaders);
        header('Access-Control-Expose-Headers: MCP-Session-Id, mcp-session-id');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Allow-Private-Network: true');
        if ($origin !== '*') {
            header('Access-Control-Allow-Credentials: true');
        }

        if (Http::method() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $method = Http::method();
        $path = Http::path();

        if ($method === 'GET' && $path === '/health') {
            Http::json([
                'ok' => true,
                'service' => 'php-mcp-mariadb',
                'status' => 'healthy',
            ]);
        }

        $oauthEnabled = Env::get('MCP_ENABLE_OAUTH', '0') === '1';
        if ($oauthEnabled) {
            if ($method === 'GET' && ($path === '/.well-known/oauth-protected-resource' || $path === '/.well-known/oauth-protected-resource/mcp')) {
                Http::json([
                    'resource' => self::baseUrl() . '/mcp',
                    'authorization_servers' => [self::baseUrl()],
                ]);
            }

            if ($method === 'GET' && ($path === '/.well-known/oauth-authorization-server' || $path === '/.well-known/openid-configuration')) {
                Http::json([
                    'issuer' => self::baseUrl(),
                    'authorization_endpoint' => self::baseUrl() . '/oauth/authorize',
                    'token_endpoint' => self::baseUrl() . '/oauth/token',
                    'registration_endpoint' => self::baseUrl() . '/register',
                    'response_types_supported' => ['code', 'token'],
                    'grant_types_supported' => ['client_credentials', 'authorization_code', 'refresh_token'],
                    'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
                    'scopes_supported' => ['mcp'],
                    'code_challenge_methods_supported' => ['S256'],
                ]);
            }

            if ($method === 'POST' && $path === '/register') {
                $base = self::baseUrl();
                $rawBody = Http::rawBody();
                $body = json_decode($rawBody, true);
                if (!is_array($body)) {
                    $body = [];
                    parse_str($rawBody, $body);
                    if (!is_array($body)) {
                        $body = [];
                    }
                }
                $redirectUris = [];
                if (isset($body['redirect_uris']) && is_array($body['redirect_uris'])) {
                    foreach ($body['redirect_uris'] as $uri) {
                        if (is_string($uri) && $uri !== '') {
                            $redirectUris[] = $uri;
                        }
                    }
                }
                if ($redirectUris === []) {
                    $redirectUris = [
                        'http://localhost:6274/oauth/callback',
                        'http://localhost:6277/oauth/callback',
                        $base . '/oauth/callback',
                    ];
                }
                Http::json([
                    'client_id' => 'mcp-inspector-dev-client',
                    'client_id_issued_at' => time(),
                    'client_secret_expires_at' => 0,
                    'token_endpoint_auth_method' => 'none',
                    'grant_types' => ['authorization_code', 'refresh_token', 'client_credentials'],
                    'response_types' => ['code'],
                    'redirect_uris' => $redirectUris,
                ]);
            }

            if ($method === 'POST' && $path === '/oauth/token') {
                $body = json_decode(Http::rawBody(), true);
                if (!is_array($body)) {
                    $body = [];
                }
                $grantType = (string) ($body['grant_type'] ?? '');
                if ($grantType === 'authorization_code') {
                    Http::json([
                        'access_token' => 'mcp-inspector-dev-token',
                        'token_type' => 'Bearer',
                        'expires_in' => 3600,
                        'scope' => 'mcp',
                        'refresh_token' => 'mcp-inspector-dev-refresh-token',
                    ]);
                }
                Http::json([
                    'access_token' => 'mcp-inspector-dev-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'scope' => 'mcp',
                ]);
            }

            if ($method === 'GET' && $path === '/oauth/authorize') {
                $redirectUri = $_GET['redirect_uri'] ?? null;
                $state = $_GET['state'] ?? null;
                $responseType = $_GET['response_type'] ?? '';

                if (!is_string($redirectUri) || $redirectUri === '') {
                    Http::json([
                        'error' => 'invalid_request',
                        'error_description' => 'redirect_uri is required',
                    ], 400);
                }

                if ($responseType !== 'code') {
                    Http::json([
                        'error' => 'unsupported_response_type',
                        'error_description' => 'Only response_type=code is supported in this dev endpoint.',
                    ], 400);
                }

                $code = 'mcp-dev-auth-code';
                $sep = str_contains($redirectUri, '?') ? '&' : '?';
                $location = $redirectUri . $sep . 'code=' . rawurlencode($code);
                if (is_string($state) && $state !== '') {
                    $location .= '&state=' . rawurlencode($state);
                }

                http_response_code(302);
                header('Location: ' . $location);
                exit;
            }
        }

        if ($method === 'POST' && self::isMcpPath($path)) {
            self::handleJsonRpc();
            return;
        }

        Http::json([
            'ok' => false,
            'error' => 'Not found',
            'method' => $method,
            'path' => $path,
        ], 404);
    }

    private static function handleJsonRpc(): void
    {
        // Streamable HTTP clients (including MCP Inspector) may expect a session id header.
        $sessionId = $_SERVER['HTTP_MCP_SESSION_ID'] ?? null;
        if (!is_string($sessionId) || $sessionId === '') {
            $sessionId = 'php-mcp-session';
        }
        header('Mcp-Session-Id: ' . $sessionId);

        $raw = Http::rawBody();
        $request = json_decode($raw, true);

        if (!is_array($request)) {
            JsonRpc::error(null, -32700, 'Parse error');
        }

        $hasId = array_key_exists('id', $request);
        $id = $request['id'] ?? null;
        $jsonrpc = $request['jsonrpc'] ?? null;
        $method = $request['method'] ?? null;
        $params = $request['params'] ?? [];
        $isNotification = !$hasId;

        if ($jsonrpc !== '2.0' || !is_string($method)) {
            JsonRpc::error($id, -32600, 'Invalid Request');
        }

        if (!is_array($params)) {
            JsonRpc::error($id, -32602, 'Invalid params');
        }

        if (!Http::isAuthorized()) {
            if ($isNotification) {
                return;
            }
            JsonRpc::error($id, -32001, 'Unauthorized');
        }

        try {
            switch ($method) {
                case 'initialize':
                    JsonRpc::success($id, [
                        'protocolVersion' => '2024-11-05',
                        'serverInfo' => [
                            'name' => 'php-mcp-mysql',
                            'version' => '1.0.0',
                        ],
                        'capabilities' => [
                            'tools' => (object) [],
                        ],
                    ]);

                case 'notifications/initialized':
                    if ($isNotification) {
                        return;
                    }
                    JsonRpc::success($id, (object) []);

                case 'tools/list':
                    JsonRpc::success($id, [
                        'tools' => Tools::definitions(),
                    ]);

                case 'tools/call':
                    $name = $params['name'] ?? null;
                    $arguments = $params['arguments'] ?? [];

                    if (!is_string($name) || !is_array($arguments)) {
                        JsonRpc::error($id, -32602, 'Invalid params for tools/call');
                    }

                    $result = Tools::call($name, $arguments);

                    JsonRpc::success($id, [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                            ],
                        ],
                        'structuredContent' => $result,
                        'isError' => false,
                    ]);

                default:
                    if ($isNotification) {
                        return;
                    }
                    JsonRpc::error($id, -32601, 'Method not found');
            }
        } catch (Throwable $e) {
            if ($isNotification) {
                return;
            }
            JsonRpc::error($id, -32000, $e->getMessage(), [
                'exception' => get_class($e),
            ]);
        }
    }
}

App::run();
