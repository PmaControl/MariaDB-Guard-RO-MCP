<?php

declare(strict_types=1);

namespace App;

final class Http
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::json([
                'ok' => false,
                'error' => 'Invalid JSON body'
            ], 400);
        }

        return $data;
    }

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

    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($header === null) {
            return null;
        }

        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    public static function requireTokenIfConfigured(): void
    {
        $expected = (string) Config::get('MCP_TOKEN', '');

        if ($expected === '') {
            return;
        }

        $received = self::bearerToken();
        if ($received !== $expected) {
            self::json([
                'ok' => false,
                'error' => 'Unauthorized'
            ], 401);
        }
    }
}
