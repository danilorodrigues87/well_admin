<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\SinirEnvio as EntitySinirEnvio;
use App\Service\GeradorNotificacaoService;

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

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'message' => 'Somente coletas finalizadas podem ser enviadas ao SINIR.'];
        }

        if ($coleta->sinir_status === 'enviado' && !$force) {
            return ['ok' => true, 'skipped' => true, 'message' => 'MTR já enviado ao SINIR.'];
        }
        if ($coleta->sinir_status === 'cancelado' && !$force) {
            return ['ok' => false, 'message' => 'Manifesto cancelado no SINIR. Use “Registrar MTR no SINIR” para emitir novo manifesto.'];
        }

        $built = $this->builder->buildManifestoLote($coletaId);
        if (!$built['ok']) {
            $msg = implode(' ', $built['errors']);
            $this->registrarFalha($coletaId, null, null, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        $payload = $built['payload'];
        $credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
        if (!$credentials['ok']) {
            $msg = implode(' ', $credentials['errors']);
            $this->registrarFalha($coletaId, $payload, null, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        EntityColeta::update($coletaId, ['sinir_status' => 'pendente']);

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            $msg = 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida');
            $this->registrarFalha($coletaId, $payload, $tokenResult, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        $response = $this->gateway->post('salvarManifestoLote', $payload, $tokenResult['token']);

        return $this->processarResposta($coletaId, $payload, $response);
    }

    /**
     * Cancela manifesto no SINIR (requer manifestoCodigo e justificativa).
     *
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    public function cancelarColeta(int $coletaId, string $justificativa): array
    {
        if (!SinirConfig::isEnabled() || !SinirConfig::isConfigured()) {
            return ['ok' => false, 'message' => 'Integração SINIR indisponível.'];
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'message' => 'Coleta inválida para cancelamento SINIR.'];
        }
        if (($coleta->sinir_status ?? '') !== 'enviado') {
            return ['ok' => false, 'message' => 'Somente MTRs registrados no SINIR podem ser cancelados.'];
        }

        $manifestoCodigo = self::manifestoCodigoParaApi($coleta);
        if ($manifestoCodigo === null) {
            return ['ok' => false, 'message' => 'Código do manifesto SINIR ausente nesta coleta.'];
        }

        $justificativa = trim($justificativa);
        if ($justificativa === '') {
            return ['ok' => false, 'message' => 'Informe a justificativa do cancelamento.'];
        }

        $payload = [
            'manifestoCodigo' => $manifestoCodigo,
            'justificativa' => mb_substr($justificativa, 0, 500),
        ];

        $tokenResult = $this->auth->obtainAccessToken();
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            $msg = 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida');

            return ['ok' => false, 'message' => $msg];
        }

        $response = $this->gateway->post('cancelarManifesto', $payload, $tokenResult['token']);

        return $this->processarCancelamento($coletaId, $payload, $response);
    }

    /**
     * Consulta situação do manifesto no SINIR (código de barras ou número).
     *
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    public function consultarColeta(int $coletaId): array
    {
        if (!SinirConfig::isEnabled() || !SinirConfig::isConfigured()) {
            return ['ok' => false, 'message' => 'Integração SINIR indisponível.'];
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'message' => 'Coleta inválida.'];
        }

        $barcode = trim((string)($coleta->sinir_codigo_barras ?? ''));
        $manifestoCodigo = self::manifestoCodigoParaApi($coleta);

        if ($barcode === '' && $manifestoCodigo === null) {
            return ['ok' => false, 'message' => 'Coleta sem número/código de barras SINIR para consulta.'];
        }

        $tokenResult = $this->auth->obtainAccessToken();
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida')];
        }

        if ($barcode !== '') {
            $path = 'retornaManifesto/'.rawurlencode($barcode);
        } else {
            $path = 'retornaManifestoPorNumero/'.rawurlencode((string)$manifestoCodigo);
        }

        $response = $this->gateway->post($path, [], $tokenResult['token']);

        return $this->processarConsulta($coletaId, $response);
    }

    private static function manifestoCodigoParaApi(EntityColeta $coleta): int|string|null
    {
        $man = trim((string)($coleta->sinir_man_numero ?? ''));
        if ($man !== '') {
            return ctype_digit($man) ? (int)$man : $man;
        }
        if ($coleta->numero_mtr) {
            return (int)$coleta->numero_mtr;
        }

        return null;
    }

    /** @param array<string,mixed> $payload */
    /** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
    /** @return array{ok:bool,message:string,details?:array<string,mixed>} */
    private function processarCancelamento(int $coletaId, array $payload, array $response): array
    {
        $body = $response['body'] ?? [];
        $retornoCodigo = is_array($body) ? (int)($body['retornoCodigo'] ?? -1) : -1;
        $retorno = is_array($body) ? trim((string)($body['retorno'] ?? '')) : '';

        if ($response['ok'] && $retornoCodigo === 0) {
            EntityColeta::update($coletaId, [
                'sinir_status' => 'cancelado',
            ]);

            EntitySinirEnvio::registrar(
                $coletaId,
                EntitySinirEnvio::proximaTentativa($coletaId),
                'cancelado',
                $payload,
                is_array($body) ? $body : null,
                null
            );

            return [
                'ok' => true,
                'message' => $retorno !== '' ? $retorno : 'Manifesto cancelado no SINIR.',
            ];
        }

        $msg = $retorno !== '' ? $retorno : $this->extrairMensagemErro($response, is_array($body) ? $body : null);

        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'erro',
            $payload,
            is_array($body) ? $body : $response,
            mb_substr($msg, 0, 500)
        );

        return ['ok' => false, 'message' => $msg];
    }

    /** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
    /** @return array{ok:bool,message:string,details?:array<string,mixed>} */
    private function processarConsulta(int $coletaId, array $response): array
    {
        $body = $response['body'] ?? [];
        if (!is_array($body)) {
            return ['ok' => false, 'message' => $this->extrairMensagemErro($response, null)];
        }

        $retornoCodigo = (int)($body['retornoCodigo'] ?? -1);
        if (!$response['ok'] || $retornoCodigo !== 0) {
            $msg = trim((string)($body['retorno'] ?? ''));
            if ($msg === '') {
                $msg = $this->extrairMensagemErro($response, $body);
            }

            return ['ok' => false, 'message' => $msg];
        }

        $situacaoCod = isset($body['situacaoManifestoCodigo']) ? (int)$body['situacaoManifestoCodigo'] : null;
        $situacaoLabel = self::labelSituacaoManifesto($situacaoCod);

        if ($situacaoCod === 4 && ($body['manifestoCodigo'] ?? null) !== null) {
            EntityColeta::update($coletaId, ['sinir_status' => 'cancelado']);
        }

        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'consulta',
            null,
            $body,
            $situacaoLabel
        );

        return [
            'ok' => true,
            'message' => 'Situação no SINIR: '.$situacaoLabel,
            'details' => [
                'situacao_codigo' => $situacaoCod,
                'situacao_label' => $situacaoLabel,
                'manifesto_codigo' => $body['manifestoCodigo'] ?? null,
            ],
        ];
    }

    public static function labelSituacaoManifesto(?int $codigo): string
    {
        return match ($codigo) {
            1 => 'Salvo / emitido',
            3 => 'Recebido no destinador',
            4 => 'Cancelado',
            9 => 'Em armazenamento temporário',
            default => $codigo !== null ? 'Código '.$codigo : 'Desconhecida',
        };
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

            $numeroMtr = self::manifestoParaNumeroMtr($manNumero);
            $update = [
                'sinir_man_numero' => $manNumero !== '' ? $manNumero : null,
                'sinir_codigo_barras' => $codigoBarras !== '' ? $codigoBarras : null,
                'sinir_status' => 'enviado',
                'sinir_enviado_em' => date('Y-m-d H:i:s'),
            ];
            if ($numeroMtr !== null) {
                $update['numero_mtr'] = $numeroMtr;
            }
            EntityColeta::update($coletaId, $update);

            EntitySinirEnvio::registrar(
                $coletaId,
                EntitySinirEnvio::proximaTentativa($coletaId),
                'enviado',
                $payload,
                is_array($body) ? $body : null,
                null
            );

            GeradorNotificacaoService::coletaMtrDisponivel($coletaId);

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

    private static function manifestoParaNumeroMtr(string $manifestoCodigo): ?int
    {
        $manifestoCodigo = trim($manifestoCodigo);
        if ($manifestoCodigo === '') {
            return null;
        }
        if (ctype_digit($manifestoCodigo)) {
            return (int)$manifestoCodigo;
        }
        $digits = preg_replace('/\D/', '', $manifestoCodigo);

        return $digits !== '' ? (int)$digits : null;
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
