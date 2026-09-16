<?php

namespace App\Common;

class DebugTrace
{
    private const SESSION = '644d61';
    private const FILE = 'debug-644d61.log';

    /** @param array<string,mixed> $data */
    public static function log(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        // #region agent log
        $payload = json_encode([
            'sessionId' => self::SESSION,
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return;
        }

        $path = dirname(__DIR__, 2).'/'.self::FILE;
        @file_put_contents($path, $payload."\n", FILE_APPEND | LOCK_EX);
        // #endregion
    }

    public static function maskConta(string $conta): string
    {
        $digits = preg_replace('/\D/', '', $conta);

        return strlen($digits) >= 4 ? '***'.substr($digits, -4) : '(vazio/curto)';
    }

    public static function maskId(string $id): string
    {
        $id = trim($id);

        return strlen($id) >= 4 ? '***'.substr($id, -4) : '(vazio/curto)';
    }
}
