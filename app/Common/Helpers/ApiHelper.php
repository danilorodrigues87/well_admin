<?php

namespace App\Common\Helpers;

use App\Http\Request;

class ApiHelper
{
    public static function jsonOk(mixed $data = null, int $httpCode = 200): string
    {
        $payload = ['success' => true];
        if ($data !== null) {
            $payload['data'] = $data;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function jsonError(string $code, string $message, int $httpCode = 400): string
    {
        return json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function response(mixed $content, int $httpCode = 200): \App\Http\Response
    {
        return new \App\Http\Response($httpCode, $content, 'application/json');
    }

    public static function ok(mixed $data = null, int $httpCode = 200): \App\Http\Response
    {
        return self::response(self::jsonOk($data), $httpCode);
    }

    public static function fail(string $code, string $message, int $httpCode = 400): \App\Http\Response
    {
        return self::response(self::jsonError($code, $message), $httpCode);
    }

    public static function bearerToken(Request $request): ?string
    {
        $headers = $request->getHeaders();
        $auth = '';
        foreach ($headers as $key => $value) {
            if (strcasecmp((string)$key, 'Authorization') === 0) {
                $auth = (string)$value;
                break;
            }
        }

        if ($auth === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = (string)$_SERVER['HTTP_AUTHORIZATION'];
        }
        if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
            return null;
        }

        return $m[1];
    }

    public static function paginationMeta(int $total, int $page, int $perPage): array
    {
        $lastPage = max(1, (int)ceil($total / max(1, $perPage)));

        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }
}
