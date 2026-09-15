<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;

class SinirAuthService
{
    private SinirGateway $gateway;

    /** @var array<string,mixed>|null cache em memória por request */
    private static ?array $cachedToken = null;

    public function __construct(?SinirGateway $gateway = null)
    {
        $this->gateway = $gateway ?? new SinirGateway();
    }

    public function isConfigured(): bool
    {
        return SinirConfig::isConfigured();
    }

    /**
     * Troca Token de Integração (API WS) por Token de Acesso (~1h).
     *
     * @return array{ok:bool,token:?string,expires_in:?int,error:?string,raw_status:int}
     */
    public function obtainAccessToken(bool $forceRefresh = false): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'token' => null,
                'expires_in' => null,
                'error' => 'SINIR não configurado (.env: token, unidade, CNPJ)',
                'raw_status' => 0,
            ];
        }

        if (!$forceRefresh && self::$cachedToken !== null) {
            $expiresAt = (int)(self::$cachedToken['expires_at'] ?? 0);
            if ($expiresAt > time() + 60) {
                return [
                    'ok' => true,
                    'token' => (string)self::$cachedToken['token'],
                    'expires_in' => $expiresAt - time(),
                    'error' => null,
                    'raw_status' => 200,
                ];
            }
        }

        $integrationToken = SinirConfig::integrationToken();
        $response = $this->gateway->post('token', [], $integrationToken);

        if (!$response['ok']) {
            $error = $response['error'] ?? ('HTTP '.$response['status']);
            if (!empty($response['hint'])) {
                $error .= ' — '.$response['hint'];
            }

            return [
                'ok' => false,
                'token' => null,
                'expires_in' => null,
                'error' => $error,
                'raw_status' => $response['status'],
                'curl_errno' => $response['curl_errno'] ?? null,
                'primary_ip' => $response['primary_ip'] ?? null,
            ];
        }

        $body = $response['body'] ?? [];
        $accessToken = self::extractAccessToken($body);
        if ($accessToken === '') {
            return [
                'ok' => false,
                'token' => null,
                'expires_in' => null,
                'error' => 'Resposta sem token de acesso',
                'raw_status' => $response['status'],
            ];
        }

        $expiresIn = (int)($body['expires_in'] ?? $body['expiresIn'] ?? 3600);
        self::$cachedToken = [
            'token' => $accessToken,
            'expires_at' => time() + max(60, $expiresIn),
        ];

        return [
            'ok' => true,
            'token' => $accessToken,
            'expires_in' => $expiresIn,
            'error' => null,
            'raw_status' => $response['status'],
        ];
    }

    /** Limpa cache (útil em testes CLI). */
    public static function clearCache(): void
    {
        self::$cachedToken = null;
    }

    /** @param array<string,mixed> $body */
    private static function extractAccessToken(array $body): string
    {
        $candidates = [
            $body['token'] ?? null,
            $body['access_token'] ?? null,
            $body['accessToken'] ?? null,
        ];

        $obj = $body['objetoResposta'] ?? null;
        if (is_string($obj)) {
            $candidates[] = $obj;
        } elseif (is_array($obj)) {
            $candidates[] = $obj['token'] ?? null;
            $candidates[] = $obj['access_token'] ?? null;
            $candidates[] = $obj['accessToken'] ?? null;
        }

        foreach ($candidates as $candidate) {
            $token = self::normalizeBearerToken((string)$candidate);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }

    private static function normalizeBearerToken(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (stripos($raw, 'Bearer ') === 0) {
            $raw = trim(substr($raw, 7));
        }

        return $raw;
    }
}
