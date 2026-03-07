<?php

declare(strict_types=1);

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
