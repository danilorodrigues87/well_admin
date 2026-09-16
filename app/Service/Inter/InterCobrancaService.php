<?php

namespace App\Service\Inter;

use App\Common\DebugTrace;
use App\Common\InterConfig;
use App\Model\Entity\InterCobranca as EntityInterCobranca;
use App\Service\Banco\BancoGatewayInterface;

class InterCobrancaService implements BancoGatewayInterface
{
    private InterGateway $gateway;
    private InterAuthService $auth;

    public function __construct(?InterGateway $gateway = null, ?InterAuthService $auth = null)
    {
        $this->gateway = $gateway ?? new InterGateway();
        $this->auth = $auth ?? new InterAuthService($this->gateway);
    }

    public function isConfigured(): bool
    {
        return InterConfig::isConfigured();
    }

    public function obtainAccessToken(bool $forceRefresh = false): array
    {
        return $this->auth->obtainAccessToken($forceRefresh);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function emitirCobranca(array $payload): array
    {
        $token = $this->requireToken();
        // #region agent log
        DebugTrace::log('A', 'InterCobrancaService.php:emitir', 'pre-cobranca config', [
            'token_ok' => $token['ok'],
            'token_status' => $token['raw_status'] ?? 0,
            'inter_env' => InterConfig::env(),
            'base_url' => InterConfig::baseUrl(),
            'conta' => DebugTrace::maskConta(InterConfig::contaCorrente()),
            'client_id' => DebugTrace::maskId(InterConfig::clientId()),
            'cert_readable' => is_readable(InterConfig::certPath()),
            'key_readable' => is_readable(InterConfig::keyPath()),
            'valor_nominal' => $payload['valorNominal'] ?? null,
        ]);
        // #endregion
        if (!$token['ok']) {
            return [
                'ok' => false,
                'codigo_solicitacao' => null,
                'body' => null,
                'error' => $token['error'],
                'raw_status' => $token['raw_status'],
            ];
        }

        $response = $this->gateway->request(
            'POST',
            'cobranca/v3/cobrancas',
            $payload,
            $token['token']
        );
        // #region agent log
        DebugTrace::log('A', 'InterCobrancaService.php:emitir', 'cobranca response', [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'error' => $response['error'],
        ]);
        // #endregion

        $body = $response['body'];
        $codigo = is_array($body) ? (string)($body['codigoSolicitacao'] ?? '') : '';

        $error = $response['ok'] && $codigo === ''
            ? 'Resposta sem codigoSolicitacao'
            : $response['error'];
        if ($response['status'] === 401) {
            $error = 'Conta corrente Inter rejeitada (HTTP 401). Verifique INTER_CONTA_CORRENTE no .env — '
                .'número real da conta PJ, somente dígitos, sem traço. '
                .'Detalhe Inter: '.($response['error'] ?? 'Login/senha inválido');
        }

        return [
            'ok' => $response['ok'] && $codigo !== '',
            'codigo_solicitacao' => $codigo !== '' ? $codigo : null,
            'body' => $body,
            'error' => $error,
            'raw_status' => $response['status'],
        ];
    }

    public function consultarCobranca(string $codigoSolicitacao): array
    {
        $token = $this->requireToken();
        if (!$token['ok']) {
            return ['ok' => false, 'body' => null, 'error' => $token['error'], 'raw_status' => $token['raw_status']];
        }

        $response = $this->gateway->request(
            'GET',
            'cobranca/v3/cobrancas/'.rawurlencode($codigoSolicitacao),
            null,
            $token['token']
        );

        return [
            'ok' => $response['ok'],
            'body' => $response['body'],
            'error' => $response['error'],
            'raw_status' => $response['status'],
        ];
    }

    public function cancelarCobranca(string $codigoSolicitacao, string $motivo): array
    {
        $token = $this->requireToken();
        if (!$token['ok']) {
            return ['ok' => false, 'body' => null, 'error' => $token['error'], 'raw_status' => $token['raw_status']];
        }

        $response = $this->gateway->request(
            'POST',
            'cobranca/v3/cobrancas/'.rawurlencode($codigoSolicitacao).'/cancelar',
            ['motivoCancelamento' => $motivo],
            $token['token']
        );

        return [
            'ok' => $response['ok'],
            'body' => $response['body'],
            'error' => $response['error'],
            'raw_status' => $response['status'],
        ];
    }

    /**
     * @return array{ok:bool,body:?array,error:?string,raw_status:int}
     */
    public function obterPdfBase64(string $codigoSolicitacao): array
    {
        $token = $this->requireToken();
        if (!$token['ok']) {
            return ['ok' => false, 'body' => null, 'error' => $token['error'], 'raw_status' => $token['raw_status']];
        }

        $response = $this->gateway->request(
            'GET',
            'cobranca/v3/cobrancas/'.rawurlencode($codigoSolicitacao).'/pdf',
            null,
            $token['token']
        );

        return [
            'ok' => $response['ok'],
            'body' => $response['body'],
            'error' => $response['error'],
            'raw_status' => $response['status'],
        ];
    }

    /**
     * @return array{ok:bool,body:?array,error:?string,raw_status:int}
     */
    public function registrarWebhook(string $url): array
    {
        $token = $this->requireToken();
        if (!$token['ok']) {
            return ['ok' => false, 'body' => null, 'error' => $token['error'], 'raw_status' => $token['raw_status']];
        }

        $response = $this->gateway->request(
            'PUT',
            'cobranca/v3/cobrancas/webhook',
            ['webhookUrl' => $url],
            $token['token']
        );

        return [
            'ok' => $response['ok'],
            'body' => $response['body'],
            'error' => $response['error'],
            'raw_status' => $response['status'],
        ];
    }

    /**
     * Emite cobrança e persiste em inter_cobrancas.
     *
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $meta
     * @return array{ok:bool,entity:?EntityInterCobranca,error:?string}
     */
    public function emitirEPersistir(int $clienteId, array $payload, array $meta = []): array
    {
        $result = $this->emitirCobranca($payload);
        if (!$result['ok'] || empty($result['codigo_solicitacao'])) {
            return ['ok' => false, 'entity' => null, 'error' => $result['error'] ?? 'Falha ao emitir cobrança'];
        }

        $entity = EntityInterCobranca::create([
            'cliente_id' => $clienteId,
            'competencia' => $meta['competencia'] ?? null,
            'codigo_solicitacao' => $result['codigo_solicitacao'],
            'seu_numero' => (string)($payload['seuNumero'] ?? ''),
            'valor_nominal' => (float)($payload['valorNominal'] ?? 0),
            'valor_calculado' => (float)($meta['valor_calculado'] ?? $payload['valorNominal'] ?? 0),
            'data_vencimento' => (string)($payload['dataVencimento'] ?? date('Y-m-d')),
            'status' => 'EMITIDA',
            'payload_request' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'payload_response' => json_encode($result['body'] ?? [], JSON_UNESCAPED_UNICODE),
            'detalhes_json' => $meta['detalhes_json'] ?? null,
            'multa_mora_json' => $meta['multa_mora_json'] ?? null,
            'observacao_ajuste' => $meta['observacao_ajuste'] ?? null,
        ]);

        return ['ok' => true, 'entity' => $entity, 'error' => null];
    }

    /** @return array{ok:bool,token:?string,error:?string,raw_status:int} */
    private function requireToken(): array
    {
        $auth = $this->auth->obtainAccessToken();

        return [
            'ok' => $auth['ok'],
            'token' => $auth['token'] ?? null,
            'error' => $auth['error'] ?? null,
            'raw_status' => $auth['raw_status'] ?? 0,
        ];
    }
}
