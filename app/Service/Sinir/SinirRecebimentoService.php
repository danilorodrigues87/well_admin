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
     * Monta o payload efetivamente enviado, já sincronizado com as unidades e
     * quantidades canônicas retornadas pelo manifesto nacional.
     *
     * @return array{ok:bool,payload:?array,errors:string[],format?:string}
     */
    public function prepararPayloadRecebimento(
        int $coletaId,
        ?string $responsavel = null,
        ?string $cargo = null
    ): array {
        $built = $this->builder->buildRecebimentoLote($coletaId, $responsavel, $cargo);
        if (!$built['ok'] || ($built['format'] ?? '') !== 'nacional') {
            return $built;
        }

        $canonical = $this->sincronizarItensComManifestoSinir($coletaId, $built['payload']);
        if (!$canonical['ok']) {
            return [
                'ok' => false,
                'payload' => null,
                'errors' => [$canonical['message']],
                'format' => 'nacional',
            ];
        }

        $built['payload'] = $canonical['payload'];

        return $built;
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

        $built = $this->prepararPayloadRecebimento($coletaId, $responsavel, $cargo);
        if (!$built['ok']) {
            return ['ok' => false, 'message' => implode(' ', $built['errors'])];
        }

        $payload = $built['payload'];

        $credentials = (new SinirCredentialsResolver())->forRecebimento($coletaId);
        if (!$credentials['ok']) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha desconhecida')];
        }

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
     * O GET do manifesto normaliza kg para tonelada (ex.: 1 kg → 0,001 TON).
     * O recebimento deve repetir exatamente quantidade/resíduo/tratamento salvos no SINIR.
     *
     * @param array<int,array<string,mixed>> $payload
     * @return array{ok:bool,message:string,payload:array<int,array<string,mixed>>}
     */
    private function sincronizarItensComManifestoSinir(int $coletaId, array $payload): array
    {
        $manNumero = trim((string)($payload[0]['manNumero'] ?? ''));
        if ($manNumero === '') {
            return ['ok' => false, 'message' => 'Número nacional ausente no payload de recebimento.', 'payload' => $payload];
        }

        $credentials = (new SinirCredentialsResolver())->forConsulta($coletaId);
        if (!$credentials['ok'] || empty($credentials['integration_token'])) {
            return [
                'ok' => false,
                'message' => 'Não foi possível consultar o manifesto antes do recebimento: '
                    .implode(' ', $credentials['errors']),
                'payload' => $payload,
            ];
        }

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return [
                'ok' => false,
                'message' => 'Autenticação do gerador para validar o recebimento: '
                    .($tokenResult['error'] ?? 'falha desconhecida'),
                'payload' => $payload,
            ];
        }

        $response = $this->gateway->get(
            'retornaManifesto/'.rawurlencode($manNumero),
            $tokenResult['token']
        );
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $manifesto = is_array($body['objetoResposta'] ?? null) ? $body['objetoResposta'] : [];
        $itens = $manifesto['listaManifestoResiduo'] ?? $manifesto['listaManifestoResiduos'] ?? null;

        if (!$response['ok'] || !is_array($itens) || $itens === []) {
            return [
                'ok' => false,
                'message' => 'O SINIR não devolveu os itens do manifesto para validar o recebimento. '
                    .($response['error'] ?? ''),
                'payload' => $payload,
            ];
        }

        $itensRecebimento = [];
        foreach ($itens as $item) {
            if (!is_array($item)) {
                continue;
            }
            $residuo = is_array($item['residuo'] ?? null) ? $item['residuo'] : [];
            $tratamento = is_array($item['tratamento'] ?? null) ? $item['tratamento'] : [];
            $codigo = trim((string)($residuo['resCodigoIbama'] ?? $item['resCodigoIbama'] ?? ''));
            $quantidade = isset($item['marQuantidade']) ? (float)$item['marQuantidade'] : 0.0;
            $traCodigo = (int)($tratamento['traCodigo'] ?? $item['traCodigo'] ?? 0);

            if ($codigo === '' || $quantidade <= 0 || $traCodigo <= 0) {
                return [
                    'ok' => false,
                    'message' => 'O SINIR retornou item incompleto no manifesto '.$manNumero.'.',
                    'payload' => $payload,
                ];
            }

            $itensRecebimento[] = [
                'resCodigoIbama' => $codigo,
                'marQuantidade' => $quantidade,
                'marQuantidadeRecebida' => $quantidade,
                'traCodigo' => $traCodigo,
            ];
        }

        if ($itensRecebimento === []) {
            return ['ok' => false, 'message' => 'Manifesto SINIR sem itens válidos.', 'payload' => $payload];
        }

        $payload[0]['listaManifestoResiduos'] = $itensRecebimento;

        return ['ok' => true, 'message' => 'Payload sincronizado com o manifesto.', 'payload' => $payload];
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

        $credentials = (new SinirCredentialsResolver())->forConsulta($coletaId);
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
            // Após receber, o token disponível é o do destinador. Se ele não
            // puder baixar o documento emitido pelo gerador, repete com o
            // token do gerador resolvido por forConsulta().
            if (empty($pdfResponse['pdf']) && $accessToken !== null && $accessToken !== '') {
                $geradorToken = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
                if ($geradorToken['ok'] && !empty($geradorToken['token'])) {
                    $pdfResponse = $this->gateway->postForPdf($path, $geradorToken['token']);
                }
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
        if (array_key_exists('erroNacional', $body)
            || array_key_exists('respostaApiwsManifestoDTO', $body)
            || array_key_exists('objetoResposta', $body)) {
            $erroNacional = !empty($body['erroNacional']) || !empty($body['erro']);
            $msgNacional = trim((string)($body['mensagemErroNacional'] ?? $body['mensagem'] ?? ''));
            $dto = $body['respostaApiwsManifestoDTO'] ?? $body['objetoResposta'] ?? null;
            $primeiro = self::primeiroItemDto($dto);
            $retorno = is_array($primeiro)
                ? trim((string)($primeiro['retorno']
                    ?? $primeiro['restResponseMensagem']
                    ?? $primeiro['mensagem']
                    ?? ''))
                : '';
            $retornoCodigo = is_array($primeiro) ? (int)($primeiro['retornoCodigo'] ?? -1) : -1;
            $restValido = is_array($primeiro) ? ($primeiro['restResponseValido'] ?? null) : null;
            $man = is_array($primeiro)
                ? (string)($primeiro['manNumero'] ?? $primeiro['manifestoCodigo'] ?? '')
                : '';

            if ($response['ok'] && !$erroNacional && $restValido !== false
                && ($retornoCodigo === 0 || $restValido === true
                    || ($retorno !== '' && !self::textoIndicaErro($retorno)))) {
                return [
                    'sucesso' => true,
                    'mensagem' => $retorno !== '' ? $retorno : 'MTR recebido no SINIR.',
                    'manifesto_codigo' => $man !== '' ? $man : null,
                ];
            }

            $detalhes = self::extrairMensagemRecursiva($body);
            $mensagemBase = $msgNacional !== '' ? $msgNacional : $retorno;
            if ($detalhes !== '' && ($mensagemBase === '' || !str_contains($detalhes, $mensagemBase))) {
                $mensagemBase = trim($mensagemBase.' · '.$detalhes, ' ·');
            }

            return [
                'sucesso' => false,
                'mensagem' => $mensagemBase,
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

        $mensagem = self::extrairMensagemRecursiva($body);

        return [
            'sucesso' => false,
            'mensagem' => $mensagem !== ''
                ? $mensagem
                : trim((string)($response['error'] ?? 'Falha ao registrar recebimento no SINIR.')),
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

    private static function textoIndicaErro(string $texto): bool
    {
        $normalizado = mb_strtoupper(trim($texto), 'UTF-8');

        return str_contains($normalizado, 'ERRO')
            || str_contains($normalizado, 'FALHA')
            || str_contains($normalizado, 'INVÁLID')
            || str_contains($normalizado, 'INVALID')
            || str_contains($normalizado, 'ENCONTRADO');
    }

    /** Coleta mensagens/códigos mesmo quando o conector os aninha por manifesto/item. */
    private static function extrairMensagemRecursiva(array $data): string
    {
        $mensagens = [];
        $walk = static function (array $node) use (&$walk, &$mensagens): void {
            $codigo = null;
            foreach (['retornoCodigo', 'restResponseCodigo', 'codigoErro'] as $key) {
                if (isset($node[$key]) && is_scalar($node[$key])) {
                    $codigo = trim((string)$node[$key]);
                    break;
                }
            }
            foreach ([
                'retorno',
                'restResponseMensagem',
                'mensagemErroNacional',
                'mensagem',
                'descricao',
                'erroMensagem',
            ] as $key) {
                if (!isset($node[$key]) || !is_scalar($node[$key])) {
                    continue;
                }
                $texto = trim((string)$node[$key]);
                if ($texto !== '' && !in_array(mb_strtolower($texto), ['true', 'false'], true)) {
                    $mensagens[] = ($codigo !== null && $codigo !== '' ? '['.$codigo.'] ' : '').$texto;
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($data);

        return implode(' · ', array_values(array_unique($mensagens)));
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
