<?php

namespace App\Service\Inter;

use App\Common\InterConfig;

class InterAuthService
{
    private InterGateway $gateway;

    public function __construct(?InterGateway $gateway = null)
    {
        $this->gateway = $gateway ?? new InterGateway();
    }

    public function isConfigured(): bool
    {
        return InterConfig::isConfigured();
    }

    /**
     * @return array{ok:bool,token:?string,expires_in:?int,error:?string,raw_status:int}
     */
    public function obtainAccessToken(bool $forceRefresh = false): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'token' => null,
                'expires_in' => null,
                'error' => implode('; ', InterConfig::configurationErrors()),
                'raw_status' => 0,
            ];
        }

        if (!$forceRefresh) {
            $cached = $this->loadCachedToken();
            if ($cached !== null) {
                return [
                    'ok' => true,
                    'token' => $cached['token'],
                    'expires_in' => max(0, $cached['expires_at'] - time()),
                    'error' => null,
                    'raw_status' => 200,
                ];
            }
        }

        $response = $this->gateway->requestToken();
        if (!$response['ok']) {
            return [
                'ok' => false,
                'token' => null,
                'expires_in' => null,
                'error' => $response['error'] ?? ('HTTP '.$response['status']),
                'raw_status' => $response['status'],
            ];
        }

        $body = $response['body'] ?? [];
        $token = (string)($body['access_token'] ?? '');
        if ($token === '') {
            return [
                'ok' => false,
                'token' => null,
                'expires_in' => null,
                'error' => 'Resposta OAuth sem access_token',
                'raw_status' => $response['status'],
            ];
        }

        $expiresIn = (int)($body['expires_in'] ?? 3600);
        $this->saveCachedToken($token, $expiresIn);

        return [
            'ok' => true,
            'token' => $token,
            'expires_in' => $expiresIn,
            'error' => null,
            'raw_status' => $response['status'],
        ];
    }

    public static function clearCache(): void
    {
        $path = InterConfig::tokenCachePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @return array{token:string,expires_at:int}|null */
    private function loadCachedToken(): ?array
    {
        $path = InterConfig::tokenCachePath();
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $token = (string)($data['access_token'] ?? $data['token'] ?? '');
        $expiresAt = (int)($data['expires_at'] ?? 0);
        if ($token === '' || $expiresAt <= time() + 60) {
            return null;
        }

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    private function saveCachedToken(string $token, int $expiresIn): void
    {
        $path = InterConfig::tokenCachePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = json_encode([
            'access_token' => $token,
            'expires_at' => time() + max(60, $expiresIn),
            'saved_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE);

        if ($payload !== false) {
            file_put_contents($path, $payload);
        }
    }
}
