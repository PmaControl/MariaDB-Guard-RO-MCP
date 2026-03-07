<?php

declare(strict_types=1);

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
