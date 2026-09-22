<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\SinirEnvio as EntitySinirEnvio;
use App\Service\ColetaCdfService;

class SinirRecebimentoService
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
     * Registra recebimento do MTR no SINIR (destinador) e atualiza coleta local.
     *
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    public function receberColeta(int $coletaId, bool $baixarPdf = true, ?string $responsavel = null, ?string $cargo = null): array
    {
        if (!SinirConfig::isEnabled()) {
            return ['ok' => false, 'message' => 'Integração SINIR desabilitada.'];
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'message' => 'Coleta inválida para recebimento.'];
        }
        if (($coleta->sinir_status ?? '') !== 'enviado') {
            return ['ok' => false, 'message' => 'Somente MTRs enviados ao SINIR podem ser recebidos.'];
        }
        if ($coleta->sinir_recebido_em !== null && $coleta->sinir_recebido_em !== '') {
            return ['ok' => false, 'message' => 'Recebimento SINIR já registrado para esta coleta.'];
        }

        $built = $this->builder->buildRecebimentoLote($coletaId, $responsavel, $cargo);
        if (!$built['ok']) {
            return ['ok' => false, 'message' => implode(' ', $built['errors'])];
        }

        $credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
        if (!$credentials['ok']) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida')];
        }

        $payload = $built['payload'];
        $response = $this->gateway->post('receberManifestoLote', $payload, $tokenResult['token']);

        return $this->processarResposta($coletaId, $payload, $response, $baixarPdf, $tokenResult['token']);
    }

    /**
     * Baixa PDF oficial do MTR no SINIR e grava em storage (CDF light).
     *
     * @return array{ok:bool,message:string}
     */
    public function baixarPdfManifesto(int $coletaId, ?string $accessToken = null): array
    {
        if (!SinirConfig::isEnabled()) {
            return ['ok' => false, 'message' => 'Integração SINIR desabilitada.'];
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            return ['ok' => false, 'message' => 'Coleta não encontrada.'];
        }

        $barcode = trim((string)($coleta->sinir_codigo_barras ?? ''));
        if ($barcode === '') {
            return ['ok' => false, 'message' => 'Coleta sem código de barras SINIR.'];
        }

        $credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
        if (!$credentials['ok']) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $token = $accessToken;
        if ($token === null || $token === '') {
            $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
            if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
                return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha')];
            }
            $token = $tokenResult['token'];
        }

        $path = 'buscaPdfManifestoPorCodigoBarras/'.rawurlencode($barcode);
        $response = $this->gateway->postForPdf($path, $token);
        if (!$response['ok'] || empty($response['pdf'])) {
            $msg = $response['error'] ?? 'Não foi possível baixar o PDF do MTR.';
            if (is_array($response['body']) && !empty($response['body']['mensagem'])) {
                $msg = (string)$response['body']['mensagem'];
            }

            return ['ok' => false, 'message' => $msg];
        }

        $saved = ColetaCdfService::gravarPdf($coletaId, $response['pdf'], 'mtr_sinir.pdf', 'mtr_pdf');

        return ['ok' => $saved['ok'], 'message' => $saved['message']];
    }

    /** @param array<string,mixed> $payload */
    /** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
    /** @return array{ok:bool,message:string,details?:array<string,mixed>} */
    private function processarResposta(int $coletaId, array $payload, array $response, bool $baixarPdf, string $accessToken): array
    {
        $body = $response['body'] ?? [];
        $lista = is_array($body['manifestoRecebimentoJSONs'] ?? null) ? $body['manifestoRecebimentoJSONs'] : [];
        $primeiro = is_array($lista[0] ?? null) ? $lista[0] : null;

        if ($response['ok'] && $primeiro !== null && (int)($primeiro['retornoCodigo'] ?? -1) === 0) {
            $update = [
                'situacao_recebimento' => 'recebido',
                'sinir_recebido_em' => date('Y-m-d H:i:s'),
            ];
            if (empty(EntityColeta::getById($coletaId)?->data_recebimento)) {
                $update['data_recebimento'] = date('Y-m-d');
            }
            EntityColeta::update($coletaId, $update);

            EntitySinirEnvio::registrar(
                $coletaId,
                EntitySinirEnvio::proximaTentativa($coletaId),
                'recebido',
                $payload,
                is_array($body) ? $body : null,
                null
            );

            $pdfMsg = '';
            if ($baixarPdf) {
                $pdf = $this->baixarPdfManifesto($coletaId, $accessToken);
                $pdfMsg = $pdf['ok'] ? ' PDF do MTR disponível no portal.' : ' ('.$pdf['message'].')';
            }

            return [
                'ok' => true,
                'message' => (string)($primeiro['retorno'] ?? 'MTR recebido no SINIR.').$pdfMsg,
                'details' => ['manifesto_codigo' => $primeiro['manifestoCodigo'] ?? null],
            ];
        }

        $msg = $primeiro !== null
            ? trim((string)($primeiro['retorno'] ?? ''))
            : '';
        if ($msg === '') {
            $msg = $response['error'] ?? 'Falha ao registrar recebimento no SINIR.';
        }

        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'erro',
            $payload,
            is_array($body) ? $body : ['raw' => $response['raw'] ?? ''],
            mb_substr($msg, 0, 500)
        );

        return ['ok' => false, 'message' => $msg];
    }
}
