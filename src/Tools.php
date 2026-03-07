<?php

declare(strict_types=1);

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
            [
                'name' => 'db_create_table',
                'description' => 'Create a table using a CREATE TABLE statement (no CTAS).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'sql' => ['type' => 'string'],
                    ],
                    'required' => ['sql'],
                ],
            ],
            [
                'name' => 'db_ping',
                'description' => 'Check TCP reachability to DB host/port.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'host' => ['type' => 'string'],
                        'port' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
                        'timeoutMs' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000],
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
            'db_create_table' => self::dbCreateTable($args),
            'db_ping' => self::dbPing($args),
            default => throw new InvalidArgumentException('Unknown tool: ' . $name),
        };
    }

    private static function dbSelect(array $args): array
    {
        $sql = (string)($args['sql'] ?? '');
        $params = isset($args['params']) && is_array($args['params']) ? array_values($args['params']) : [];
        $maxRows = self::boundedMaxRows($args['maxRows'] ?? Env::getInt('MAX_ROWS_DEFAULT', 1000));

        $sql = SqlGuard::validateReadOnlyQuery($sql);
        $sql = SqlGuard::applyLimitIfMissing($sql, $maxRows);
        self::enforceDbSelectPolicies($sql, $params);

        return self::runPreparedQuery($sql, $params);
    }

    private static function dbTables(array $args): array
    {
        $schema = SqlGuard::ensureIdentifier((string)($args['schema'] ?? ''), 'schema');
        $tableLike = isset($args['tableLike']) ? (string)$args['tableLike'] : null;
        $maxRows = self::boundedMaxRows($args['maxRows'] ?? Env::getInt('MAX_ROWS_DEFAULT', 1000));

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

    private static function dbCreateTable(array $args): array
    {
        $sql = (string)($args['sql'] ?? '');
        $sql = SqlGuard::validateCreateTableQuery($sql);

        return self::runStatement($sql);
    }

    private static function dbPing(array $args): array
    {
        $host = isset($args['host']) ? (string) $args['host'] : null;
        $port = isset($args['port']) ? (int) $args['port'] : null;
        $timeoutMs = isset($args['timeoutMs']) ? (int) $args['timeoutMs'] : 1500;
        if ($timeoutMs < 1) {
            $timeoutMs = 1;
        }
        if ($timeoutMs > 10000) {
            $timeoutMs = 10000;
        }

        return Db::pingServer($host, $port, $timeoutMs / 1000);
    }

    private static function runPreparedQuery(string $sql, array $params): array
    {
        $pdo = Db::pdo();
        $displaySql = $sql;
        $sql = self::applySelectTimeout($sql);
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
            'sql' => $displaySql,
            'rowCount' => count($rows),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private static function runStatement(string $sql): array
    {
        $pdo = Db::pdo();
        $affected = $pdo->exec($sql);

        return [
            'sql' => $sql,
            'ok' => true,
            'affectedRows' => $affected === false ? 0 : (int) $affected,
        ];
    }

    private static function applySelectTimeout(string $sql): string
    {
        $normalized = SqlGuard::stripComments($sql);
        if (!preg_match('/^select\b/i', $normalized)) {
            return $sql;
        }

        $timeoutMs = Env::getInt('MAX_SELECT_TIME_MS', 5000);
        if ($timeoutMs <= 0) {
            return $sql;
        }

        if (Db::isMariaDb()) {
            if (!Db::isMariaDbVersionAtLeast('10.1.1')) {
                return $sql;
            }
            $seconds = max(0.001, $timeoutMs / 1000);
            $secondsLiteral = rtrim(rtrim(sprintf('%.3F', $seconds), '0'), '.');
            return "SET STATEMENT max_statement_time={$secondsLiteral} FOR {$sql}";
        }

        if (!Db::isMySqlVersionAtLeast('5.7.4')) {
            return $sql;
        }

        if (preg_match('/\/\*\+\s*MAX_EXECUTION_TIME\s*\(/i', $sql)) {
            return $sql;
        }

        return preg_replace(
            '/^select\b/i',
            'SELECT /*+ MAX_EXECUTION_TIME(' . $timeoutMs . ') */',
            $sql,
            1
        ) ?? $sql;
    }

    private static function enforceDbSelectPolicies(string $sql, array $params): void
    {
        $normalized = SqlGuard::stripComments($sql);

        if (preg_match('/^\s*select\s+\*/i', $normalized)) {
            $tableRef = self::extractSelectStarTable($normalized);
            if ($tableRef !== null) {
                $columnCount = self::countTableColumns($tableRef['schema'], $tableRef['table']);
                if ($columnCount > 30) {
                    throw new InvalidArgumentException(
                        "db_select forbids SELECT * for wide tables (>30 columns). Table '{$tableRef['table']}' has {$columnCount} columns."
                    );
                }
            }
        }

        // Policy requested: replace OR-based filters with UNION/UNION ALL.
        if (preg_match('/\bwhere\b[\s\S]*\bor\b/i', $normalized)) {
            throw new InvalidArgumentException('db_select forbids OR in WHERE. Rewrite the query using UNION or UNION ALL.');
        }

        self::assertIndexedExplainPlan($sql, $params);
    }

    private static function assertIndexedExplainPlan(string $sql, array $params): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare('EXPLAIN ' . $sql);
        foreach (array_values($params) as $i => $value) {
            $stmt->bindValue($i + 1, $value, self::pdoType($value));
        }
        $stmt->execute();
        $plan = $stmt->fetchAll();

        foreach ($plan as $row) {
            $table = (string)($row['table'] ?? '');
            $accessType = strtoupper((string)($row['type'] ?? ''));
            $usedKey = trim((string)($row['key'] ?? ''));

            if ($table === '' || strtoupper($table) === 'NULL') {
                continue;
            }

            if ($accessType === 'ALL' || $usedKey === '') {
                throw new InvalidArgumentException(
                    "db_select requires indexed WHERE/JOIN access. Table '{$table}' has non-indexed plan (type={$accessType}, key=" . ($usedKey === '' ? 'NULL' : $usedKey) . ').'
                );
            }
        }
    }

    private static function extractSelectStarTable(string $sql): ?array
    {
        if (!preg_match('/\bfrom\b\s+([`A-Za-z0-9_$.]+)/i', $sql, $matches)) {
            return null;
        }

        $identifier = trim($matches[1]);
        if ($identifier === '' || str_starts_with($identifier, '(')) {
            return null;
        }

        $parts = explode('.', $identifier);
        if (count($parts) === 1) {
            $schema = (string) Env::get('DB_NAME', '');
            $table = self::stripIdentifierQuotes($parts[0]);
        } elseif (count($parts) === 2) {
            $schema = self::stripIdentifierQuotes($parts[0]);
            $table = self::stripIdentifierQuotes($parts[1]);
        } else {
            return null;
        }

        if ($schema === '' || $table === '') {
            return null;
        }

        return ['schema' => $schema, 'table' => $table];
    }

    private static function stripIdentifierQuotes(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B`");
    }

    private static function countTableColumns(string $schema, string $table): int
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$schema, $table]);
        return (int) $stmt->fetchColumn();
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
