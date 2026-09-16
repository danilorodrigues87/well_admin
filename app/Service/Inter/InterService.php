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

        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => 'Falha na autenticação: '.($result['error'] ?? 'erro desconhecido'),
                'details' => [
                    'configured' => true,
                    'step' => 'oauth',
                    'raw_status' => $result['raw_status'] ?? 0,
                ],
            ];
        }

        $contaTest = self::smokeTestContaCorrente($result['token'] ?? '');
        if (!$contaTest['ok']) {
            return [
                'ok' => false,
                'message' => $contaTest['message'],
                'details' => array_merge([
                    'configured' => true,
                    'enabled' => InterConfig::isEnabled(),
                    'env' => InterConfig::env(),
                    'base_url' => InterConfig::baseUrl(),
                    'conta_corrente' => InterConfig::contaCorrente(),
                    'scope' => InterConfig::scope(),
                    'oauth_ok' => true,
                    'step' => 'conta_corrente',
                ], $contaTest['details'] ?? []),
            ];
        }

        return [
            'ok' => true,
            'message' => 'Token OAuth e conta corrente validados na API Inter.',
            'details' => [
                'configured' => true,
                'enabled' => InterConfig::isEnabled(),
                'env' => InterConfig::env(),
                'base_url' => InterConfig::baseUrl(),
                'conta_corrente' => InterConfig::contaCorrente(),
                'scope' => InterConfig::scope(),
                'expires_in' => $result['expires_in'] ?? null,
                'raw_status' => $result['raw_status'] ?? 0,
                'conta_test_status' => $contaTest['details']['raw_status'] ?? null,
            ],
        ];
    }

    /** @return array{ok:bool,message:string,details?:array<string,mixed>} */
    private static function smokeTestContaCorrente(string $token): array
    {
        if ($token === '') {
            return ['ok' => false, 'message' => 'Token vazio para teste de conta corrente'];
        }

        $hoje = date('Y-m-d');
        $gateway = new InterGateway();
        $response = $gateway->request(
            'GET',
            'cobranca/v3/cobrancas?dataInicial='.$hoje.'&dataFinal='.$hoje.'&paginacao.itensPorPagina=1',
            null,
            $token
        );

        if ($response['status'] === 401) {
            return [
                'ok' => false,
                'message' => 'OAuth OK, mas INTER_CONTA_CORRENTE foi rejeitada (HTTP 401). '
                    .'Use o número real da conta PJ no Inter — não o exemplo 12345678.',
                'details' => [
                    'raw_status' => 401,
                    'inter_error' => $response['error'],
                ],
            ];
        }

        if (!$response['ok']) {
            return [
                'ok' => false,
                'message' => 'OAuth OK, mas API de cobrança falhou: '.($response['error'] ?? 'HTTP '.$response['status']),
                'details' => ['raw_status' => $response['status']],
            ];
        }

        return ['ok' => true, 'message' => 'Conta corrente OK', 'details' => ['raw_status' => $response['status']]];
    }
}
