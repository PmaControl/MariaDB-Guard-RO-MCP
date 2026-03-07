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
                'name' => 'db_explain_table',
                'description' => 'Run classic EXPLAIN and return a human-readable MariaDB/MySQL table rendering.',
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
            'db_explain_table' => self::dbExplainTable($args),
            'db_processlist' => self::dbProcesslist($args),
            'db_variables' => self::dbVariables($args),
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
        self::assertDbSelectNotBusy();

        return self::runPreparedQuery($sql, $params, 'db_select');
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

        return self::runPreparedQuery($sql, $params, 'db_tables');
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

        return self::runPreparedQuery($sql, [$schema, $table], 'db_schema');
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

        return self::runPreparedQuery($sql, [$schema, $table], 'db_indexes');
    }

    private static function dbExplain(array $args): array
    {
        $sql = (string)($args['sql'] ?? '');
        $params = isset($args['params']) && is_array($args['params']) ? array_values($args['params']) : [];

        $sql = trim($sql);
        $sql = SqlGuard::validateReadOnlyQuery($sql);
        if (!preg_match('/^(select|with)\b/i', SqlGuard::stripComments($sql))) {
            throw new InvalidArgumentException('db_explain only accepts SELECT queries (including non-recursive CTE)');
        }

        try {
            return self::runPreparedQuery('EXPLAIN FORMAT=JSON ' . $sql, $params, 'db_explain');
        } catch (Throwable $e) {
            return self::runPreparedQuery('EXPLAIN ' . $sql, $params, 'db_explain');
        }
    }

    private static function dbExplainTable(array $args): array
    {
        $sql = (string)($args['sql'] ?? '');
        $params = isset($args['params']) && is_array($args['params']) ? array_values($args['params']) : [];

        $sql = trim($sql);
        $sql = SqlGuard::validateReadOnlyQuery($sql);
        if (!preg_match('/^(select|with)\b/i', SqlGuard::stripComments($sql))) {
            throw new InvalidArgumentException('db_explain_table only accepts SELECT queries (including non-recursive CTE)');
        }

        $result = self::runPreparedQuery('EXPLAIN ' . $sql, $params, 'db_explain_table');
        $result['tableText'] = self::renderExplainTable($result['rows']);
        return $result;
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

        return self::runPreparedQuery($sql, [], 'db_processlist');
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

        return self::runPreparedQuery($sql, $params, 'db_variables');
    }

    private static function runPreparedQuery(string $sql, array $params, string $toolName = 'query'): array
    {
        $pdo = Db::pdo();
        $originalSql = $sql;
        $executedSql = self::applySelectTimeout($sql);
        $startedAt = microtime(true);

        try {
            $stmt = $pdo->prepare($executedSql);
            self::bindParams($stmt, $params);
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

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            QueryLogger::log([
                'event' => 'mcp_sql_query',
                'tool' => $toolName,
                'sql' => QueryLogger::formatSql($executedSql),
                'sqlOriginal' => QueryLogger::formatSql($originalSql),
                'params' => $params,
                'rowCount' => count($rows),
                'durationMs' => $durationMs,
                'plan' => self::buildExecutionPlan($originalSql, $params),
                'status' => 'ok',
            ]);

            return [
                'sql' => $originalSql,
                'rowCount' => count($rows),
                'columns' => $columns,
                'rows' => $rows,
            ];
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $publicError = self::normalizeExecutionTimeoutError($e->getMessage());
            QueryLogger::log([
                'event' => 'mcp_sql_query',
                'tool' => $toolName,
                'sql' => QueryLogger::formatSql($executedSql),
                'sqlOriginal' => QueryLogger::formatSql($originalSql),
                'params' => $params,
                'rowCount' => 0,
                'durationMs' => $durationMs,
                'plan' => self::buildExecutionPlan($originalSql, $params),
                'status' => 'error',
                'error' => $publicError,
                'errorRaw' => $e->getMessage(),
            ]);
            if ($publicError === 'guard [execution time reached]') {
                throw new InvalidArgumentException($publicError, 0, $e);
            }
            throw $e;
        }
    }

    private static function runStatement(string $sql, string $toolName = 'statement'): array
    {
        $pdo = Db::pdo();
        $startedAt = microtime(true);
        try {
            $affected = $pdo->exec($sql);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            QueryLogger::log([
                'event' => 'mcp_sql_query',
                'tool' => $toolName,
                'sql' => QueryLogger::formatSql($sql),
                'sqlOriginal' => QueryLogger::formatSql($sql),
                'params' => [],
                'rowCount' => $affected === false ? 0 : (int) $affected,
                'durationMs' => $durationMs,
                'plan' => null,
                'status' => 'ok',
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            QueryLogger::log([
                'event' => 'mcp_sql_query',
                'tool' => $toolName,
                'sql' => QueryLogger::formatSql($sql),
                'sqlOriginal' => QueryLogger::formatSql($sql),
                'params' => [],
                'rowCount' => 0,
                'durationMs' => $durationMs,
                'plan' => null,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return [
            'sql' => $sql,
            'ok' => true,
            'affectedRows' => $affected === false ? 0 : (int) $affected,
        ];
    }

    private static function applySelectTimeout(string $sql): string
    {
        $normalized = SqlGuard::stripComments($sql);
        if (!preg_match('/^(select|with)\b/i', $normalized)) {
            return $sql;
        }

        $timeoutMs = Env::getInt('MAX_SELECT_TIME_MS', 30000);
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
            '/\bselect\b/i',
            'SELECT /*+ MAX_EXECUTION_TIME(' . $timeoutMs . ') */',
            $sql,
            1
        ) ?? $sql;
    }

    private static function enforceDbSelectPolicies(string $sql, array $params): void
    {
        $normalized = SqlGuard::stripComments($sql);
        $hasWhere = preg_match('/\bwhere\b/i', $normalized) === 1;
        $hasJoin = preg_match('/\bjoin\b/i', $normalized) === 1;
        $tableCount = self::countFromTables($normalized);

        if (!$hasWhere && preg_match('/^\s*select\s+\*/i', $normalized)) {
            if ($hasJoin || $tableCount !== 1) {
                throw new InvalidArgumentException('db_select allows SELECT * without WHERE only for a single table without JOIN.');
            }
        }

        // With WHERE, keep a safeguard for wide tables.
        if ($hasWhere && preg_match('/^\s*select\s+\*/i', $normalized)) {
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

        self::assertWhereScanPolicy($sql, $params, $hasWhere);
    }

    private static function countFromTables(string $sql): int
    {
        if (!preg_match('/\bfrom\b([\s\S]*?)(?:\bwhere\b|\bgroup\s+by\b|\border\s+by\b|\blimit\b|$)/i', $sql, $m)) {
            return 0;
        }
        $fromPart = trim($m[1]);
        if ($fromPart === '') {
            return 0;
        }
        return substr_count($fromPart, ',') + 1;
    }

    private static function assertWhereScanPolicy(string $sql, array $params, bool $hasWhere): void
    {
        if (!$hasWhere) {
            return;
        }

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
            $explainRows = (int)($row['rows'] ?? 0);

            if ($table === '' || strtoupper($table) === 'NULL') {
                continue;
            }

            $isFullScan = $accessType === 'ALL' || $usedKey === '';
            if ($isFullScan) {
                $tableRows = self::getTableRowEstimate((string) Env::get('DB_NAME', ''), $table);
                $effectiveRows = max($tableRows, $explainRows);
                $maxRowsForFullScan = self::whereFullScanMaxRows();
                if ($effectiveRows > $maxRowsForFullScan) {
                    throw new InvalidArgumentException(
                        "db_select refuses WHERE full scan on large table '{$table}' (rows={$effectiveRows} > {$maxRowsForFullScan})."
                    );
                }
            }
        }
    }

    private static function assertDbSelectNotBusy(): void
    {
        $running = Db::activeRunningQueryCount();
        if ($running > 3) {
            throw new InvalidArgumentException('database busy retry in 1 second');
        }
    }

    private static function whereFullScanMaxRows(): int
    {
        $limit = Env::getInt('WHERE_FULLSCAN_MAX_ROWS', 30000);
        return $limit > 0 ? $limit : 30000;
    }

    private static function normalizeExecutionTimeoutError(string $message): string
    {
        $m = strtolower($message);
        if (
            str_contains($m, 'max_statement_time exceeded') ||
            str_contains($m, 'max execution time') ||
            str_contains($m, 'query execution was interrupted') ||
            str_contains($m, 'execution time exceeded')
        ) {
            return 'guard [execution time reached]';
        }
        return $message;
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

    private static function getTableRowEstimate(string $schema, string $table): int
    {
        if ($schema === '' || $table === '') {
            return 0;
        }
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(TABLE_ROWS, 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
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

    private static function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach (array_values($params) as $i => $value) {
            $stmt->bindValue($i + 1, $value, self::pdoType($value));
        }
    }

    private static function buildExecutionPlan(string $sql, array $params): array|null
    {
        $normalized = SqlGuard::stripComments($sql);
        if (preg_match('/^\s*explain\b/i', $normalized)) {
            return [['note' => 'query is already an EXPLAIN statement']];
        }
        if (!preg_match('/^\s*(select|show|with)\b/i', $normalized)) {
            return null;
        }

        try {
            $pdo = Db::pdo();
            $stmt = $pdo->prepare('EXPLAIN ' . $sql);
            self::bindParams($stmt, $params);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [['error' => $e->getMessage()]];
        }
    }

    private static function renderExplainTable(array $rows): string
    {
        if (count($rows) === 0) {
            return '(no rows)';
        }

        $headers = array_keys((array) $rows[0]);
        if (count($headers) === 0) {
            return '(no columns)';
        }

        $widths = [];
        foreach ($headers as $header) {
            $widths[$header] = self::strWidth((string) $header);
        }

        foreach ($rows as $row) {
            foreach ($headers as $header) {
                $value = self::explainCellValue($row[$header] ?? null);
                $widths[$header] = max($widths[$header], self::strWidth($value));
            }
        }

        $sep = '+';
        foreach ($headers as $header) {
            $sep .= str_repeat('-', $widths[$header] + 2) . '+';
        }

        $lines = [$sep];
        $headerLine = '|';
        foreach ($headers as $header) {
            $headerLine .= ' ' . str_pad((string)$header, $widths[$header], ' ', STR_PAD_RIGHT) . ' |';
        }
        $lines[] = $headerLine;
        $lines[] = $sep;

        foreach ($rows as $row) {
            $line = '|';
            foreach ($headers as $header) {
                $value = self::explainCellValue($row[$header] ?? null);
                $padType = is_numeric($row[$header] ?? null) ? STR_PAD_LEFT : STR_PAD_RIGHT;
                $line .= ' ' . str_pad($value, $widths[$header], ' ', $padType) . ' |';
            }
            $lines[] = $line;
        }
        $lines[] = $sep;

        return implode(PHP_EOL, $lines);
    }

    private static function explainCellValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return (string) $value;
    }

    private static function strWidth(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }
        return strlen($value);
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
