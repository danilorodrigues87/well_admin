<?php

namespace App\Service\Inter;

use App\Common\InterConfig;

class InterService
{
    public static function isReady(): bool
    {
        return InterConfig::isEnabled() && InterConfig::isConfigured();
    }

    /**
     * Smoke test: OAuth2 + mTLS.
     *
     * @return array{ok:bool,message:string,details:array<string,mixed>}
     */
    public static function smokeTestToken(): array
    {
        if (!InterConfig::isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Integração Inter incompleta. Verifique .env e certificados.',
                'details' => [
                    'configured' => false,
                    'errors' => InterConfig::configurationErrors(),
                    'cert_path' => InterConfig::certPath(),
                    'key_path' => InterConfig::keyPath(),
                ],
            ];
        }

        $auth = new InterAuthService();
        InterAuthService::clearCache();
        $result = $auth->obtainAccessToken(true);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Token OAuth obtido com sucesso na API Inter.'
                : ('Falha na autenticação: '.($result['error'] ?? 'erro desconhecido')),
            'details' => [
                'configured' => true,
                'enabled' => InterConfig::isEnabled(),
                'env' => InterConfig::env(),
                'base_url' => InterConfig::baseUrl(),
                'conta_corrente' => InterConfig::contaCorrente(),
                'scope' => InterConfig::scope(),
                'expires_in' => $result['expires_in'] ?? null,
                'raw_status' => $result['raw_status'] ?? 0,
            ],
        ];
    }
}
