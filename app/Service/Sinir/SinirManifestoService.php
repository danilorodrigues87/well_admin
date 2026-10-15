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
        $credentials = (new SinirCredentialsResolver())->forEmissaoManifesto($coletaId);
        if (!$credentials['ok']) {
            $msg = implode(' ', $credentials['errors']);
            $this->registrarFalha($coletaId, $payload, null, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        EntityColeta::update($coletaId, ['sinir_status' => 'pendente']);

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            $msg = 'Autenticação SINIR (token do gerador): '.($tokenResult['error'] ?? 'falha desconhecida');
            $this->registrarFalha($coletaId, $payload, $tokenResult, $msg);

            return ['ok' => false, 'message' => $msg];
        }

        // Com token do gerador, a unidade do JWT é a do cliente — não sobrescrever transportador/destinador.
        // Alinhamento JWT só faz sentido com token Well (legado / fallback).
        if (($credentials['token_source'] ?? '') !== 'cliente') {
            $payload = self::alinharUnidadesComJwt($payload, (string)$tokenResult['token']);
        }
        $unidadeJwt = self::unidadeFromJwt((string)$tokenResult['token']);
        $geradorUnidadeCadastro = (int)($credentials['gerador_unidade'] ?? $built['gerador_unidade'] ?? 0);

        // Token do cliente deve ter sido gerado na unidade geradora cadastrada.
        if ($unidadeJwt !== null && $geradorUnidadeCadastro > 0 && $unidadeJwt !== $geradorUnidadeCadastro) {
            $msg = 'Token do gerador é da unidade '.$unidadeJwt
                .', mas o cadastro do cliente tem unidade '.$geradorUnidadeCadastro
                .'. Confira o Cód. unidade SINIR e regenere o Token API WS logado nessa unidade no portal MTR.';
            $this->registrarFalha($coletaId, $payload, [
                'jwt_unidade' => $unidadeJwt,
                'gerador_unidade' => $geradorUnidadeCadastro,
            ], $msg);

            return [
                'ok' => false,
                'message' => $msg,
                'details' => [
                    'jwt_unidade' => $unidadeJwt,
                    'gerador_unidade' => $geradorUnidadeCadastro,
                ],
            ];
        }

        $response = $this->gateway->post('salvarManifestoLote', $payload, $tokenResult['token']);
        $result = $this->processarResposta($coletaId, $payload, $response);
        if (empty($result['ok'])) {
            $m0 = (array_is_list($payload) && is_array($payload[0] ?? null)) ? $payload[0] : [];
            $result['details'] = array_merge(is_array($result['details'] ?? null) ? $result['details'] : [], [
                'jwt_unidade' => $unidadeJwt,
                'gerador_unidade_cadastro' => $geradorUnidadeCadastro,
                'unidades_enviadas' => [
                    'transportador' => $m0['transportador']['unidade'] ?? ($m0['codUnidadeTransportador'] ?? null),
                    'destinador' => $m0['destinador']['unidade'] ?? ($m0['codUnidadeDestinador'] ?? null),
                ],
            ]);
        }

        return $result;
    }

    /**
     * JWT apiws traz sub "pessoaId,unidadeId". Se o .env/cadastro estiver com unidade antiga
     * (ex.: 135551 vs 728559), alinha transportador/destinador Well à unidade do token.
     *
     * @param array<int,array<string,mixed>>|array<string,mixed> $payload
     * @return array<int,array<string,mixed>>|array<string,mixed>
     */
    private static function alinharUnidadesComJwt(array $payload, string $accessToken): array
    {
        $unidadeJwt = self::unidadeFromJwt($accessToken);
        if ($unidadeJwt === null || $unidadeJwt <= 0) {
            return $payload;
        }

        $lista = array_is_list($payload) ? $payload : null;
        if ($lista === null) {
            return $payload;
        }

        foreach ($lista as $i => $manifesto) {
            if (!is_array($manifesto)) {
                continue;
            }
            // Schema MMA/SIGOR (nacional)
            if (isset($manifesto['transportador']) && is_array($manifesto['transportador'])) {
                $lista[$i]['transportador']['unidade'] = $unidadeJwt;
                $cnpT = preg_replace('/\D/', '', (string)($manifesto['transportador']['cpfCnpj'] ?? ''));
                $cnpD = preg_replace('/\D/', '', (string)($manifesto['destinador']['cpfCnpj'] ?? ''));
                if ($cnpT !== '' && $cnpT === $cnpD && isset($lista[$i]['destinador']) && is_array($lista[$i]['destinador'])) {
                    $lista[$i]['destinador']['unidade'] = $unidadeJwt;
                }
                continue;
            }
            // Schema FEAM legado (estados)
            $lista[$i]['codUnidadeTransportador'] = $unidadeJwt;
            $cnpT = preg_replace('/\D/', '', (string)($manifesto['cnpTransportador'] ?? ''));
            $cnpD = preg_replace('/\D/', '', (string)($manifesto['cnpDestinador'] ?? ''));
            if ($cnpT !== '' && $cnpT === $cnpD) {
                $lista[$i]['codUnidadeDestinador'] = $unidadeJwt;
            }
        }

        return $lista;
    }

    private static function unidadeFromJwt(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }
        $b64 = strtr($parts[1], '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $json = base64_decode($b64, true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || !is_string($data['sub'] ?? null)) {
            return null;
        }
        if (!preg_match('/^(\d+),(\d+)$/', $data['sub'], $m)) {
            return null;
        }

        return (int)$m[2];
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

        $manifestoCodigo = self::manifestoNumeroString($coleta);
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

        $credentials = (new SinirCredentialsResolver())->forEmissaoManifesto($coletaId);
        if (!$credentials['ok'] || empty($credentials['integration_token'])) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            $msg = 'Autenticação SINIR (gerador): '.($tokenResult['error'] ?? 'falha desconhecida');

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
        $manifestoNumero = self::manifestoNumeroString($coleta);

        if ($barcode === '' && $manifestoNumero === null) {
            return ['ok' => false, 'message' => 'Coleta sem número/código de barras SINIR para consulta.'];
        }

        $credentials = (new SinirCredentialsResolver())->forConsulta($coletaId);
        if (!$credentials['ok'] || empty($credentials['integration_token'])) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida')];
        }

        $token = $tokenResult['token'];
        $response = null;

        if ($manifestoNumero !== null && self::isNumeroManifestoNacional($manifestoNumero)) {
            $response = $this->gateway->get(
                'retornaManifesto/'.rawurlencode($manifestoNumero),
                $token
            );
        } elseif ($manifestoNumero !== null) {
            $response = $this->gateway->post(
                'retornaManifestoPorNumero/'.rawurlencode($manifestoNumero),
                [],
                $token
            );
        } else {
            $response = $this->gateway->get(
                'retornaManifesto/'.rawurlencode($barcode),
                $token
            );
            if (($response['status'] ?? 0) === 404 || !($response['body'] ?? null)) {
                $response = $this->gateway->post(
                    'retornaManifesto/'.rawurlencode($barcode),
                    [],
                    $token
                );
            }
        }

        return $this->processarConsulta($coletaId, $response);
    }

    private static function manifestoNumeroString(EntityColeta $coleta): ?string
    {
        $man = trim((string)($coleta->sinir_man_numero ?? ''));
        if ($man !== '') {
            return $man;
        }
        if ($coleta->numero_mtr) {
            return (string)(int)$coleta->numero_mtr;
        }

        return null;
    }

    /** @deprecated Use manifestoNumeroString() */
    private static function manifestoCodigoParaApi(EntityColeta $coleta): int|string|null
    {
        return self::manifestoNumeroString($coleta);
    }

    private static function isNumeroManifestoNacional(string $numero): bool
    {
        $digits = preg_replace('/\D/', '', $numero) ?? '';

        return strlen($digits) > 10;
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

        $parsed = SinirConsultaParser::interpretar($body, (bool)($response['ok'] ?? false));
        if (!$parsed['ok']) {
            $msg = $parsed['mensagem'] ?? '';
            if ($msg === '') {
                $msg = $this->extrairMensagemErro($response, $body);
            }

            EntitySinirEnvio::registrar(
                $coletaId,
                EntitySinirEnvio::proximaTentativa($coletaId),
                'erro',
                null,
                $body,
                mb_substr($msg, 0, 500)
            );

            return ['ok' => false, 'message' => $msg];
        }

        $situacaoCod = $parsed['situacao_codigo'];
        $situacaoLabel = self::labelSituacaoManifesto($situacaoCod);

        $update = [];
        if (!empty($parsed['codigo_barras'])) {
            $update['sinir_codigo_barras'] = $parsed['codigo_barras'];
        }
        if ($situacaoCod === 4) {
            $update['sinir_status'] = 'cancelado';
        }
        if ($situacaoCod === 3) {
            $coleta = EntityColeta::getById($coletaId);
            if ($coleta && empty($coleta->sinir_recebido_em)) {
                $update['sinir_recebido_em'] = date('Y-m-d H:i:s');
                $update['situacao_recebimento'] = 'recebido';
            }
        }
        if ($update !== []) {
            EntityColeta::update($coletaId, $update);
        }

        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'consulta',
            null,
            $body,
            $situacaoLabel
        );

        $msg = 'Situação no SINIR: '.$situacaoLabel;
        if (!empty($parsed['codigo_barras'])) {
            $msg .= ' · código de barras atualizado.';
        }

        return [
            'ok' => true,
            'message' => $msg,
            'details' => [
                'situacao_codigo' => $situacaoCod,
                'situacao_label' => $situacaoLabel,
                'manifesto_codigo' => $parsed['manifesto_codigo'],
                'codigo_barras' => $parsed['codigo_barras'],
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
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];

        // Formato nacional apiws (2026+): { respostaApiwsManifestoDTO, erroNacional, mensagemErroNacional }
        if (array_key_exists('erroNacional', $body) || array_key_exists('respostaApiwsManifestoDTO', $body)) {
            return $this->processarRespostaNacional($coletaId, $payload, $response, $body);
        }

        // Formato legado estadual: manifestoJSONDtos[].retornoCodigo
        $manifestos = is_array($body['manifestoJSONDtos'] ?? null) ? $body['manifestoJSONDtos'] : [];
        $primeiro = is_array($manifestos[0] ?? null) ? $manifestos[0] : null;

        $manLegado = (string)($primeiro['manifestoCodigo'] ?? '');
        $retLegado = trim((string)($primeiro['retorno'] ?? ''));
        if ($response['ok'] && $primeiro !== null && (int)($primeiro['retornoCodigo'] ?? -1) === 0
            && self::manifestoCodigoValido($manLegado) && !self::retornoSinirIndicaErro($retLegado)) {
            return $this->registrarSucessoManifesto(
                $coletaId,
                $payload,
                $body,
                $manLegado,
                (string)($primeiro['codigoBarra'] ?? $primeiro['codigoBarras'] ?? ''),
                $retLegado !== '' ? $retLegado : 'MTR enviado ao SINIR.'
            );
        }

        $msg = $this->extrairMensagemErro($response, $primeiro);
        $this->registrarFalhaComRaw($coletaId, $payload, $response, $body, $msg);

        return [
            'ok' => false,
            'message' => $msg,
            'details' => [
                'http_status' => $response['status'] ?? 0,
                'sinir_body' => $body !== [] ? $body : null,
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $payload
     * @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response
     * @param array<string,mixed> $body
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    private function processarRespostaNacional(int $coletaId, ?array $payload, array $response, array $body): array
    {
        $erroNacional = !empty($body['erroNacional']);
        $msgNacional = trim((string)($body['mensagemErroNacional'] ?? ''));
        $dto = $body['respostaApiwsManifestoDTO'] ?? $body['objetoResposta'] ?? null;

        // Resposta SIGOR às vezes vem como lista na raiz.
        if ($dto === null && array_is_list($body) && is_array($body[0] ?? null)) {
            $dto = $body;
            $erroNacional = $erroNacional || !empty($body[0]['erroNacional']);
            if ($msgNacional === '') {
                $msgNacional = trim((string)($body[0]['mensagemErroNacional'] ?? ''));
            }
        }

        $primeiro = null;
        if (is_array($dto)) {
            if (array_is_list($dto) && is_array($dto[0] ?? null)) {
                $primeiro = $dto[0];
            } elseif (!array_is_list($dto)) {
                $primeiro = $dto;
            }
        }

        $retornoCodigo = is_array($primeiro) ? (int)($primeiro['retornoCodigo'] ?? -1) : -1;
        $manNumero = is_array($primeiro)
            ? (string)($primeiro['manifestoCodigo']
                ?? $primeiro['manifestoNumeroNacional']
                ?? $primeiro['manNumero']
                ?? $primeiro['codigoGerado']
                ?? '')
            : '';
        $codigoBarras = is_array($primeiro)
            ? (string)($primeiro['codigoBarra'] ?? $primeiro['codigoBarras'] ?? '')
            : '';
        $retorno = is_array($primeiro)
            ? trim((string)($primeiro['retorno']
                ?? $primeiro['restResponseMensagem']
                ?? $primeiro['mensagem']
                ?? ''))
            : '';

        $manifestoValido = self::manifestoCodigoValido($manNumero);
        $retornoIndicaErro = self::retornoSinirIndicaErro($retorno);

        $restOk = is_array($primeiro) && $manifestoValido && !$retornoIndicaErro
            && ($primeiro['restResponseValido'] ?? null) === true;

        $sucesso = $response['ok'] && !$erroNacional && !$retornoIndicaErro && $manifestoValido && (
            ($primeiro !== null && $retornoCodigo === 0)
            || $restOk
        );

        if ($sucesso) {
            return $this->registrarSucessoManifesto(
                $coletaId,
                $payload,
                $body,
                $manNumero,
                $codigoBarras,
                $retorno !== '' ? $retorno : 'MTR enviado ao SINIR.'
            );
        }

        $msg = $msgNacional;
        if ($msg === '' && $retorno !== '') {
            $msg = $retorno;
        }
        if ($msg === '') {
            $msg = $this->extrairMensagemErro($response, $primeiro);
        }
        if ($msg === '' || str_contains($msg, 'HTTP 400') || str_contains($msg, 'sem mensagem detalhada')) {
            $msg = 'SINIR rejeitou o manifesto (erroNacional)'
                .($manNumero !== '' ? ' — man '.$manNumero : '')
                .'. Confira: token gerado como GERADOR; data de expedição ≥ hoje; schema MMA '
                .'(transportador/destinador/listaManifestoResiduos).';
        }

        $this->registrarFalhaComRaw($coletaId, $payload, $response, $body, $msg);

        return [
            'ok' => false,
            'message' => $msg,
            'details' => [
                'http_status' => $response['status'] ?? 0,
                'sinir_body' => $body,
                'erro_nacional' => $erroNacional,
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $payload
     * @param array<string,mixed> $body
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    private function registrarSucessoManifesto(
        int $coletaId,
        ?array $payload,
        array $body,
        string $manNumero,
        string $codigoBarras,
        string $message
    ): array {
        if (!self::manifestoCodigoValido($manNumero) || self::retornoSinirIndicaErro($message)) {
            $msg = trim($message) !== '' ? $message : 'SINIR não retornou número de manifesto válido.';
            $this->registrarFalha($coletaId, $payload, $body, $msg);

            return [
                'ok' => false,
                'message' => $msg,
            ];
        }

        $numeroMtr = self::manifestoParaNumeroMtr($manNumero);
        $update = [
            'sinir_man_numero' => $manNumero,
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
            $body,
            null
        );

        GeradorNotificacaoService::coletaMtrDisponivel($coletaId);

        return [
            'ok' => true,
            'message' => $message,
            'details' => [
                'manifesto_codigo' => $manNumero,
                'codigo_barras' => $codigoBarras,
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $payload
     * @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response
     * @param array<string,mixed> $body
     */
    private function registrarFalhaComRaw(int $coletaId, ?array $payload, array $response, array $body, string $msg): void
    {
        $audit = $body !== [] ? $body : $response;
        if (is_array($audit) && empty($audit['raw']) && !empty($response['raw'])) {
            $audit['raw'] = mb_substr((string)$response['raw'], 0, 4000);
        }
        $this->registrarFalha($coletaId, $payload, $audit, $msg);
    }

    /** Limite `coletas.numero_mtr` (int unsigned). MTR nacional (12 dígitos) fica só em `sinir_man_numero`. */
    private const MYSQL_UINT_MAX = 4294967295;

    private static function manifestoParaNumeroMtr(string $manifestoCodigo): ?int
    {
        $manifestoCodigo = trim($manifestoCodigo);
        if ($manifestoCodigo === '' || !self::manifestoCodigoValido($manifestoCodigo)) {
            return null;
        }
        $digits = ctype_digit($manifestoCodigo)
            ? $manifestoCodigo
            : (preg_replace('/\D/', '', $manifestoCodigo) ?? '');
        if ($digits === '' || !self::digitosCabemNumeroMtr($digits)) {
            return null;
        }

        return (int)$digits;
    }

    private static function digitosCabemNumeroMtr(string $digits): bool
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return false;
        }
        if (strlen($digits) > 10) {
            return false;
        }
        if (strlen($digits) === 10 && strcmp($digits, (string)self::MYSQL_UINT_MAX) > 0) {
            return false;
        }

        return true;
    }

    /** Código "0" ou vazio = rejeição SINIR, não MTR emitido. */
    private static function manifestoCodigoValido(string $codigo): bool
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '0') {
            return false;
        }
        if (ctype_digit($codigo)) {
            return (int)$codigo > 0;
        }

        return true;
    }

    private static function retornoSinirIndicaErro(string $retorno): bool
    {
        $retorno = trim($retorno);
        if ($retorno === '') {
            return false;
        }
        $u = mb_strtoupper($retorno, 'UTF-8');

        return str_starts_with($u, 'ERRO')
            || str_contains($u, 'ERRO:')
            || str_contains($u, 'FALHA')
            || str_contains($u, 'REJEIT');
    }

    /** @param array<string,mixed>|null $primeiro */
    private function extrairMensagemErro(array $response, ?array $primeiro): string
    {
        $candidates = [];

        if ($primeiro !== null) {
            foreach (['retorno', 'mensagem', 'message', 'erroMensagem', 'descricao'] as $k) {
                $v = trim((string)($primeiro[$k] ?? ''));
                if ($v !== '') {
                    $candidates[] = $v;
                }
            }
        }

        if (!empty($response['error'])) {
            $candidates[] = trim((string)$response['error']);
        }

        $body = $response['body'] ?? null;
        if (is_array($body)) {
            foreach (['mensagemErroNacional', 'mensagem', 'message', 'retorno', 'error', 'title', 'detail'] as $k) {
                $v = $body[$k] ?? null;
                if (is_string($v) && trim($v) !== '' && !in_array(strtolower(trim($v)), ['true', 'false'], true)) {
                    $candidates[] = trim($v);
                }
            }
            if (!empty($body['erroNacional']) && $candidates === []) {
                $candidates[] = 'SINIR retornou erroNacional sem mensagem detalhada.';
            }
            // Lote: vários itens com retorno
            $dtos = $body['manifestoJSONDtos'] ?? null;
            if (is_array($dtos)) {
                foreach ($dtos as $dto) {
                    if (!is_array($dto)) {
                        continue;
                    }
                    $r = trim((string)($dto['retorno'] ?? $dto['mensagem'] ?? ''));
                    if ($r !== '') {
                        $candidates[] = $r;
                    }
                }
            }
            if (!empty($body['erros']) && is_array($body['erros'])) {
                foreach ($body['erros'] as $e) {
                    if (is_string($e) && trim($e) !== '') {
                        $candidates[] = trim($e);
                    } elseif (is_array($e)) {
                        $r = trim((string)($e['mensagem'] ?? $e['message'] ?? $e['retorno'] ?? ''));
                        if ($r !== '') {
                            $candidates[] = $r;
                        }
                    }
                }
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates)));
        if ($candidates !== []) {
            return mb_substr(implode(' | ', $candidates), 0, 500);
        }

        $raw = trim((string)($response['raw'] ?? ''));
        if ($raw !== '') {
            $snippet = mb_substr(preg_replace('/\s+/', ' ', $raw) ?? $raw, 0, 280);

            return 'Falha ao enviar MTR ao SINIR (HTTP '.($response['status'] ?? 0).'): '.$snippet;
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
