<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\SinirEnvio as EntitySinirEnvio;

class SinirManifestoService
{
    private SinirAuthService $auth;
    private SinirGateway $gateway;
    private SinirPayloadBuilder $builder;

    public function __construct(
        ?SinirAuthService $auth = null,
        ?SinirGateway $gateway = null,
        ?SinirPayloadBuilder $builder = null
    ) {
        $this->auth = $auth ?? new SinirAuthService();
        $this->gateway = $gateway ?? new SinirGateway();
        $this->builder = $builder ?? new SinirPayloadBuilder();
    }

    /**
     * Envia MTR ao SINIR após finalização da coleta.
     *
     * @return array{ok:bool,skipped?:bool,message:string,details?:array<string,mixed>}
     */
    public function enviarColeta(int $coletaId, bool $force = false): array
    {
        if (!SinirConfig::isEnabled()) {
            return ['ok' => false, 'skipped' => true, 'message' => 'Integração SINIR desabilitada (SINIR_ENABLED=false).'];
        }

        if (!SinirConfig::isConfigured()) {
            return ['ok' => false, 'message' => 'SINIR não configurado no .env (token, unidade, CNPJ).'];
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'message' => 'Somente coletas finalizadas podem ser enviadas ao SINIR.'];
        }

        if ($coleta->sinir_status === 'enviado' && !$force) {
            return ['ok' => true, 'skipped' => true, 'message' => 'MTR já enviado ao SINIR.'];
        }

        $built = $this->builder->buildManifestoLote($coletaId);
        if (!$built['ok']) {
            $msg = implode(' ', $built['errors']);
            $this->registrarFalha($coletaId, null, null, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        $payload = $built['payload'];
        $tokenResult = $this->auth->obtainAccessToken();
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            $msg = 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida');
            $this->registrarFalha($coletaId, $payload, $tokenResult, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        $response = $this->gateway->post('salvarManifestoLote', $payload, $tokenResult['token']);

        return $this->processarResposta($coletaId, $payload, $response);
    }

    /** @param array<string,mixed>|null $payload */
    /** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
    /** @return array{ok:bool,message:string,details?:array<string,mixed>} */
    private function processarResposta(int $coletaId, ?array $payload, array $response): array
    {
        $body = $response['body'] ?? [];
        $manifestos = is_array($body['manifestoJSONDtos'] ?? null) ? $body['manifestoJSONDtos'] : [];
        $primeiro = is_array($manifestos[0] ?? null) ? $manifestos[0] : null;

        if ($response['ok'] && $primeiro !== null && (int)($primeiro['retornoCodigo'] ?? -1) === 0) {
            $manNumero = (string)($primeiro['manifestoCodigo'] ?? '');
            $codigoBarras = (string)($primeiro['codigoBarra'] ?? $primeiro['codigoBarras'] ?? '');

            EntityColeta::update($coletaId, [
                'sinir_man_numero' => $manNumero !== '' ? $manNumero : null,
                'sinir_codigo_barras' => $codigoBarras !== '' ? $codigoBarras : null,
                'sinir_status' => 'enviado',
                'sinir_enviado_em' => date('Y-m-d H:i:s'),
            ]);

            EntitySinirEnvio::registrar(
                $coletaId,
                EntitySinirEnvio::proximaTentativa($coletaId),
                'enviado',
                $payload,
                is_array($body) ? $body : null,
                null
            );

            return [
                'ok' => true,
                'message' => (string)($primeiro['retorno'] ?? 'MTR enviado ao SINIR.'),
                'details' => [
                    'manifesto_codigo' => $manNumero,
                    'codigo_barras' => $codigoBarras,
                ],
            ];
        }

        $msg = $this->extrairMensagemErro($response, $primeiro);
        $this->registrarFalha($coletaId, $payload, is_array($body) ? $body : $response, $msg);

        return ['ok' => false, 'message' => $msg, 'details' => ['http_status' => $response['status']]];
    }

    /** @param array<string,mixed>|null $primeiro */
    private function extrairMensagemErro(array $response, ?array $primeiro): string
    {
        if ($primeiro !== null) {
            $retorno = trim((string)($primeiro['retorno'] ?? ''));
            if ($retorno !== '') {
                return $retorno;
            }
        }

        if (!empty($response['error'])) {
            return (string)$response['error'];
        }

        $body = $response['body'] ?? [];
        if (is_array($body) && !empty($body['mensagem'])) {
            return (string)$body['mensagem'];
        }

        return 'Falha ao enviar MTR ao SINIR (HTTP '.($response['status'] ?? 0).').';
    }

    /** @param array<string,mixed>|null $payload */
    /** @param array<string,mixed>|null $responsePayload */
    private function registrarFalha(int $coletaId, ?array $payload, ?array $responsePayload, string $mensagem): void
    {
        EntityColeta::update($coletaId, ['sinir_status' => 'erro']);

        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'erro',
            $payload,
            $responsePayload,
            $mensagem
        );
    }
}
