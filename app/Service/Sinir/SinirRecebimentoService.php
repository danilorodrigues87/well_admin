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

        $credentials = (new SinirCredentialsResolver())->forRecebimento($coletaId);
        if (!$credentials['ok']) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida')];
        }

        $payload = $built['payload'];
        $response = $this->gateway->post('receberManifestoLote', $payload, $tokenResult['token']);

        return $this->processarResposta(
            $coletaId,
            $payload,
            $response,
            $baixarPdf,
            $tokenResult['token'],
            ($built['format'] ?? '') === 'nacional'
        );
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

        $credentials = (new SinirCredentialsResolver())->forRecebimento($coletaId);
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

        $manNumero = trim((string)($coleta->sinir_man_numero ?? ''));
        $barcode = trim((string)($coleta->sinir_codigo_barras ?? ''));

        if ($barcode === '' && $manNumero !== '') {
            $consulta = (new SinirManifestoService())->consultarColeta($coletaId);
            if (!empty($consulta['ok'])) {
                $coleta = EntityColeta::getById($coletaId);
                $barcode = trim((string)($coleta->sinir_codigo_barras ?? ''));
            }
        }

        $pdfResponse = null;
        if ($manNumero !== '') {
            $path = 'downloadManifesto/'.rawurlencode($manNumero);
            $pdfResponse = $this->gateway->postForPdf($path, $token);
            if (empty($pdfResponse['pdf'])) {
                $pdfResponse = $this->gateway->getForPdf($path, $token);
            }
        }

        if (($pdfResponse === null || empty($pdfResponse['pdf'])) && $barcode !== '') {
            $path = 'buscaPdfManifestoPorCodigoBarras/'.rawurlencode($barcode);
            $pdfResponse = $this->gateway->postForPdf($path, $token);
        }

        if ($pdfResponse === null || empty($pdfResponse['pdf'])) {
            $msg = 'Não foi possível baixar o PDF do MTR.';
            if (is_array($pdfResponse)) {
                $msg = $pdfResponse['error'] ?? $msg;
                if (is_array($pdfResponse['body'] ?? null) && !empty($pdfResponse['body']['mensagem'])) {
                    $msg = (string)$pdfResponse['body']['mensagem'];
                }
            }
            if ($barcode === '' && $manNumero === '') {
                $msg = 'Coleta sem número nacional SINIR. Consulte o manifesto no painel primeiro.';
            }

            return ['ok' => false, 'message' => $msg];
        }

        $saved = ColetaCdfService::gravarPdf($coletaId, $pdfResponse['pdf'], 'mtr_sinir.pdf', 'mtr_pdf');

        return ['ok' => $saved['ok'], 'message' => $saved['message']];
    }

    /** @param array<string,mixed>|array<int,array<string,mixed>> $payload */
    /** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
    /** @return array{ok:bool,message:string,details?:array<string,mixed>} */
    private function processarResposta(
        int $coletaId,
        array $payload,
        array $response,
        bool $baixarPdf,
        string $accessToken,
        bool $formatoNacional
    ): array {
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];

        if ($formatoNacional || array_is_list($payload)) {
            $nacional = $this->interpretarRespostaRecebimentoNacional($body, $response);
            if ($nacional['sucesso']) {
                return $this->finalizarRecebimentoOk(
                    $coletaId,
                    $payload,
                    $body,
                    $nacional['mensagem'],
                    $nacional['manifesto_codigo'],
                    $baixarPdf,
                    $accessToken
                );
            }
            if ($nacional['mensagem'] !== '') {
                return $this->finalizarRecebimentoErro($coletaId, $payload, $body, $response, $nacional['mensagem']);
            }
        }

        $lista = is_array($body['manifestoRecebimentoJSONs'] ?? null) ? $body['manifestoRecebimentoJSONs'] : [];
        $primeiro = is_array($lista[0] ?? null) ? $lista[0] : null;

        if ($response['ok'] && $primeiro !== null && (int)($primeiro['retornoCodigo'] ?? -1) === 0) {
            return $this->finalizarRecebimentoOk(
                $coletaId,
                $payload,
                $body,
                (string)($primeiro['retorno'] ?? 'MTR recebido no SINIR.'),
                $primeiro['manifestoCodigo'] ?? null,
                $baixarPdf,
                $accessToken
            );
        }

        $msg = $primeiro !== null
            ? trim((string)($primeiro['retorno'] ?? ''))
            : '';
        if ($msg === '') {
            $msg = $response['error'] ?? 'Falha ao registrar recebimento no SINIR.';
        }

        return $this->finalizarRecebimentoErro($coletaId, $payload, $body, $response, $msg);
    }

    /**
     * @param array<string,mixed> $body
     * @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response
     * @return array{sucesso:bool,mensagem:string,manifesto_codigo:?string}
     */
    private function interpretarRespostaRecebimentoNacional(array $body, array $response): array
    {
        if (array_key_exists('erroNacional', $body) || array_key_exists('respostaApiwsManifestoDTO', $body)) {
            $erroNacional = !empty($body['erroNacional']);
            $msgNacional = trim((string)($body['mensagemErroNacional'] ?? ''));
            $dto = $body['respostaApiwsManifestoDTO'] ?? $body['objetoResposta'] ?? null;
            $primeiro = self::primeiroItemDto($dto);
            $retorno = is_array($primeiro) ? trim((string)($primeiro['retorno'] ?? $primeiro['mensagem'] ?? '')) : '';
            $retornoCodigo = is_array($primeiro) ? (int)($primeiro['retornoCodigo'] ?? -1) : -1;
            $man = is_array($primeiro)
                ? (string)($primeiro['manNumero'] ?? $primeiro['manifestoCodigo'] ?? '')
                : '';

            if ($response['ok'] && !$erroNacional && ($retornoCodigo === 0 || ($retorno !== '' && !str_starts_with(mb_strtoupper($retorno), 'ERRO')))) {
                return [
                    'sucesso' => true,
                    'mensagem' => $retorno !== '' ? $retorno : 'MTR recebido no SINIR.',
                    'manifesto_codigo' => $man !== '' ? $man : null,
                ];
            }

            return [
                'sucesso' => false,
                'mensagem' => $msgNacional !== '' ? $msgNacional : ($retorno !== '' ? $retorno : ''),
                'manifesto_codigo' => null,
            ];
        }

        if (array_is_list($body) && is_array($body[0] ?? null)) {
            $primeiro = $body[0];
            $retornoCodigo = (int)($primeiro['retornoCodigo'] ?? -1);
            $retorno = trim((string)($primeiro['retorno'] ?? $primeiro['mensagem'] ?? ''));
            $man = (string)($primeiro['manNumero'] ?? $primeiro['manifestoCodigo'] ?? '');

            if ($response['ok'] && ($retornoCodigo === 0 || ($retorno !== '' && !str_starts_with(mb_strtoupper($retorno), 'ERRO')))) {
                return [
                    'sucesso' => true,
                    'mensagem' => $retorno !== '' ? $retorno : 'MTR recebido no SINIR.',
                    'manifesto_codigo' => $man !== '' ? $man : null,
                ];
            }

            return ['sucesso' => false, 'mensagem' => $retorno, 'manifesto_codigo' => null];
        }

        if ($response['ok'] && empty($body['erro'])) {
            return ['sucesso' => true, 'mensagem' => 'MTR recebido no SINIR.', 'manifesto_codigo' => null];
        }

        return [
            'sucesso' => false,
            'mensagem' => trim((string)($body['mensagem'] ?? $response['error'] ?? '')),
            'manifesto_codigo' => null,
        ];
    }

    /** @param mixed $dto */
    private static function primeiroItemDto(mixed $dto): ?array
    {
        if (!is_array($dto)) {
            return null;
        }
        if (array_is_list($dto) && is_array($dto[0] ?? null)) {
            return $dto[0];
        }
        if (!array_is_list($dto)) {
            return $dto;
        }

        return null;
    }

    /** @param array<string,mixed>|array<int,array<string,mixed>> $payload */
    /** @param array<string,mixed> $body */
    /** @return array{ok:bool,message:string,details?:array<string,mixed>} */
    private function finalizarRecebimentoOk(
        int $coletaId,
        array $payload,
        array $body,
        string $mensagem,
        mixed $manifestoCodigo,
        bool $baixarPdf,
        string $accessToken
    ): array {
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
            $body,
            null
        );

        $pdfMsg = '';
        if ($baixarPdf) {
            $pdf = $this->baixarPdfManifesto($coletaId, $accessToken);
            $pdfMsg = $pdf['ok'] ? ' PDF do MTR disponível no portal.' : ' ('.$pdf['message'].')';
        }

        return [
            'ok' => true,
            'message' => $mensagem.$pdfMsg,
            'details' => ['manifesto_codigo' => $manifestoCodigo],
        ];
    }

    /** @param array<string,mixed>|array<int,array<string,mixed>> $payload */
    /** @param array<string,mixed> $body */
    /** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
    /** @return array{ok:bool,message:string} */
    private function finalizarRecebimentoErro(
        int $coletaId,
        array $payload,
        array $body,
        array $response,
        string $msg
    ): array {
        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'erro',
            $payload,
            $body !== [] ? $body : ['raw' => $response['raw'] ?? ''],
            mb_substr($msg, 0, 500)
        );

        return ['ok' => false, 'message' => $msg];
    }
}
