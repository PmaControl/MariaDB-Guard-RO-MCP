<?php

declare(strict_types=1);

final class App
{
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
