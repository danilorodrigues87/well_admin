<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\SinirEnvio as EntitySinirEnvio;
use App\Service\ColetaCdfService;

class SinirCdfService
{
    private SinirAuthService $auth;
    private SinirGateway $gateway;

    public function __construct(?SinirAuthService $auth = null, ?SinirGateway $gateway = null)
    {
        $this->auth = $auth ?? new SinirAuthService();
        $this->gateway = $gateway ?? new SinirGateway();
    }

    /**
     * Emite CDF no SINIR para um MTR recebido e baixa o PDF oficial.
     *
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    public function emitirCdfColeta(int $coletaId, ?string $responsavel = null): array
    {
        if (!SinirConfig::isEnabled()) {
            return ['ok' => false, 'message' => 'Integração SINIR desabilitada.'];
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return ['ok' => false, 'message' => 'Coleta inválida.'];
        }
        if (($coleta->sinir_status ?? '') !== 'enviado') {
            return ['ok' => false, 'message' => 'MTR precisa estar registrado no SINIR.'];
        }
        if (empty($coleta->sinir_recebido_em)) {
            return ['ok' => false, 'message' => 'Registre o recebimento no SINIR antes de emitir o CDF.'];
        }
        if (!empty($coleta->sinir_cdf_codigo)) {
            return ['ok' => false, 'message' => 'CDF SINIR já emitido (#'.(string)$coleta->sinir_cdf_codigo.').'];
        }

        $manifesto = trim((string)($coleta->sinir_man_numero ?? ''));
        if ($manifesto === '' && $coleta->numero_mtr) {
            $manifesto = (string)(int)$coleta->numero_mtr;
        }
        if ($manifesto === '') {
            return ['ok' => false, 'message' => 'Número do manifesto SINIR ausente.'];
        }

        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        $cliente = EntityCliente::getById($coleta->cliente_id);
        $geradorCnpj = preg_replace('/\D/', '', (string)($snapshot->gerador_cnpj ?? $cliente->cnpj ?? ''));
        if ($geradorCnpj === '') {
            return ['ok' => false, 'message' => 'CNPJ do gerador ausente.'];
        }

        $destinador = $coleta->destinador_id
            ? EntityDestinador::getById((int)$coleta->destinador_id)
            : EntityDestinador::getPadrao();
        $respRecebimento = trim((string)($destinador?->responsavel ?? $snapshot->destinador_responsavel ?? ''));
        $respCdf = trim((string)($destinador?->sinir_responsavel_cdf ?? ''));
        $resp = trim((string)($responsavel ?? ''));
        if ($resp === '') {
            $resp = $respCdf !== '' ? $respCdf : $respRecebimento;
        }
        if ($resp === '') {
            return ['ok' => false, 'message' => 'Destinador sem responsável técnico do CDF. Preencha esse nome no cadastro do destinador.'];
        }

        $refDate = $coleta->data_recebimento ?: $coleta->data_coleta ?: date('Y-m-d');
        $nacional = strlen(preg_replace('/\D/', '', $manifesto)) > 10;
        $observacao = mb_substr('Well · coleta #'.$coletaId.' · rel '.($coleta->numero_relatorio ?? '—'), 0, 255);
        if ($nacional) {
            $unidade = (int)($destinador?->sinir_cod_unidade ?? 0);
            $cnpjDest = preg_replace('/\D/', '', (string)($destinador?->cnpj ?? $snapshot->destinador_cnpj ?? ''));
            if ($unidade <= 0 || $cnpjDest === '') {
                return ['ok' => false, 'message' => 'Destinador sem unidade SINIR ou CNPJ para emitir o CDF.'];
            }
            [$inicioMs, $fimMs] = self::periodoRecebimentoEpochMs($refDate);
            $payload = [
                'cerDataInicial' => $inicioMs,
                'cerDataFinal' => $fimMs,
                'cerObservacao' => $observacao,
                'parceiroDestinador' => $unidade,
                'cnpjDestinador' => $cnpjDest,
                'tipoCertificadoDestinacao' => 1,
                'listaManifesto' => [['manNumero' => $manifesto]],
                'listaParceiroGerador' => [],
                'nomeResponsavel' => mb_substr($resp, 0, 255),
            ];
        } else {
            $dataYmd = date('Ymd', strtotime($refDate) ?: time());
            $payload = [
                'dataInicial' => $dataYmd,
                'dataFinal' => $dataYmd,
                'responsavel' => mb_substr($resp, 0, 255),
                'txtObservacoes' => $observacao,
                'listaGerador' => [],
                'listaMtr' => [$manifesto],
            ];
        }

        $credentials = (new SinirCredentialsResolver())->forRecebimento($coletaId);
        if (!$credentials['ok']) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha')];
        }

        if ($nacional) {
            $jaEmitido = $this->localizarCdfExistente($coletaId, $manifesto, $tokenResult['token']);
            if ($jaEmitido !== null) {
                return $this->concluirEmissaoCdf(
                    $coletaId,
                    $payload,
                    ['mensagem' => 'CDF já constava no SINIR.'],
                    $jaEmitido,
                    $tokenResult['token']
                );
            }
        }

        $response = $this->gateway->post('emiteCDF', $payload, $tokenResult['token']);
        $body = $response['body'] ?? [];
        if (!is_array($body)) {
            return ['ok' => false, 'message' => $response['error'] ?? 'Resposta inválida do SINIR.'];
        }

        $certCodigo = $nacional
            ? self::extrairCodigoCertificadoNacional($body)
            : self::extrairCodigoCertificado($body, $geradorCnpj);
        $criado = self::corpoIndicaCdfCriado($body);
        if ($certCodigo === null && $nacional && ($criado || $response['ok'])) {
            $certCodigo = $this->localizarCdfExistente($coletaId, $manifesto, $tokenResult['token']);
        }
        $retornoCodigo = (int)($body['retornoCodigo'] ?? -1);
        $sucessoLegado = !$nacional && $response['ok'] && $retornoCodigo === 0 && $certCodigo !== null;
        $sucessoNacional = $nacional && $certCodigo !== null && ($criado || ($response['ok'] && empty($body['erro'])));
        if (!$sucessoLegado && !$sucessoNacional) {
            if ($criado) {
                return [
                    'ok' => true,
                    'message' => 'Certificado criado no SINIR. O número ainda não voltou na consulta do MTR. Gere de novo em Certificados para vincular o PDF.',
                ];
            }
            $msg = self::mensagemFalhaCdf($body, (string)($response['error'] ?? ''));
            if ($nacional && stripos($msg, 'pertence a outro CDF') !== false) {
                $existente = $this->localizarCdfExistente($coletaId, $manifesto, $tokenResult['token']);
                if ($existente !== null) {
                    return $this->concluirEmissaoCdf($coletaId, $payload, $body, $existente, $tokenResult['token']);
                }
            }
            if (stripos($msg, 'Responsável não encontrado') !== false) {
                $msg .= ' Nome enviado: '.$resp.'. O CDF usa o Responsável Técnico, lista diferente da de recebimento. Ajuste o campo no cadastro do destinador.';
            }
            EntitySinirEnvio::registrar(
                $coletaId,
                EntitySinirEnvio::proximaTentativa($coletaId),
                'erro',
                $payload,
                $body,
                mb_substr($msg, 0, 500)
            );

            return ['ok' => false, 'message' => $msg];
        }

        return $this->concluirEmissaoCdf($coletaId, $payload, $body, $certCodigo, $tokenResult['token']);
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $payload
     * @param array<string,mixed> $body
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    private function concluirEmissaoCdf(
        int $coletaId,
        array $payload,
        array $body,
        int|string $certCodigo,
        string $accessToken
    ): array {
        EntityColeta::update($coletaId, ['sinir_cdf_codigo' => (string)$certCodigo]);

        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'enviado',
            $payload,
            $body,
            'CDF emitido · código '.$certCodigo
        );

        $pdf = $this->baixarPdfCdf($coletaId, (string)$certCodigo, $accessToken);
        if (!$pdf['ok']) {
            return [
                'ok' => true,
                'message' => 'CDF emitido (#'.$certCodigo.'). Baixe o PDF em Certificados. Falha ao gravar agora: '.$pdf['message'],
                'details' => ['sinir_cdf_codigo' => $certCodigo],
            ];
        }

        return [
            'ok' => true,
            'message' => 'CDF SINIR #'.$certCodigo.' emitido. O PDF fica retido no portal até liberar em Certificados.',
            'details' => ['sinir_cdf_codigo' => $certCodigo],
        ];
    }

    /**
     * Um certificado SINIR para vários MTRs nacionais do mesmo gerador e destinador.
     * Não preenche cdf_liberado_em.
     *
     * @param list<int> $coletaIds
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    public function emitirCdfLote(array $coletaIds): array
    {
        if (!SinirConfig::isEnabled()) {
            return ['ok' => false, 'message' => 'Integração SINIR desabilitada.'];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $coletaIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Selecione ao menos uma coleta.'];
        }
        if (count($ids) > 80) {
            return ['ok' => false, 'message' => 'Selecione no máximo 80 coletas por certificado.'];
        }

        $coletas = [];
        foreach ($ids as $id) {
            $coleta = EntityColeta::getById($id);
            if (!$coleta) {
                return ['ok' => false, 'message' => 'Coleta #'.$id.' não encontrada.'];
            }
            $coletas[] = $coleta;
        }

        $clienteId = (int)$coletas[0]->cliente_id;
        $destinadorId = (int)($coletas[0]->destinador_id ?? 0);
        $manifestos = [];
        $datas = [];
        foreach ($coletas as $coleta) {
            if ((int)$coleta->cliente_id !== $clienteId) {
                return ['ok' => false, 'message' => 'O certificado em lote precisa ser do mesmo gerador.'];
            }
            if ((int)($coleta->destinador_id ?? 0) !== $destinadorId) {
                return ['ok' => false, 'message' => 'Selecione coletas do mesmo destinador.'];
            }
            if ($coleta->status !== 'finalizada' || ($coleta->sinir_status ?? '') !== 'enviado') {
                return ['ok' => false, 'message' => 'A coleta #'.$coleta->id.' ainda não tem MTR registrado no SINIR.'];
            }
            if (empty($coleta->sinir_recebido_em)) {
                return ['ok' => false, 'message' => 'A coleta #'.$coleta->id.' ainda não foi recebida no SINIR.'];
            }
            if (!empty($coleta->sinir_cdf_codigo)) {
                return ['ok' => false, 'message' => 'A coleta #'.$coleta->id.' já tem CDF #'.$coleta->sinir_cdf_codigo.'. Retire-a da seleção.'];
            }
            $man = trim((string)($coleta->sinir_man_numero ?? ''));
            $digitos = preg_replace('/\D/', '', $man) ?? '';
            if (strlen($digitos) <= 10) {
                return ['ok' => false, 'message' => 'A coleta #'.$coleta->id.' não tem MTR nacional para entrar no CDF.'];
            }
            $manifestos[] = $man;
            $ref = trim((string)($coleta->data_recebimento ?: $coleta->data_coleta ?: ''));
            if ($ref === '') {
                return ['ok' => false, 'message' => 'A coleta #'.$coleta->id.' está sem data de encerramento.'];
            }
            $datas[] = $ref;
        }

        $destinador = $destinadorId > 0
            ? EntityDestinador::getById($destinadorId)
            : EntityDestinador::getPadrao();
        $respCdf = trim((string)($destinador?->sinir_responsavel_cdf ?? ''));
        $resp = $respCdf !== '' ? $respCdf : trim((string)($destinador?->responsavel ?? ''));
        if ($resp === '') {
            return ['ok' => false, 'message' => 'Destinador sem responsável técnico do CDF.'];
        }
        $unidade = (int)($destinador?->sinir_cod_unidade ?? 0);
        $cnpjDest = preg_replace('/\D/', '', (string)($destinador?->cnpj ?? '')) ?? '';
        if ($unidade <= 0 || $cnpjDest === '') {
            return ['ok' => false, 'message' => 'Destinador sem unidade SINIR ou CNPJ para emitir o CDF.'];
        }

        sort($datas);
        [$inicioMs, $fimMs] = self::periodoEntreEpochMs($datas[0], $datas[count($datas) - 1]);
        $lista = [];
        foreach ($manifestos as $man) {
            $lista[] = ['manNumero' => $man];
        }
        $payload = [
            'cerDataInicial' => $inicioMs,
            'cerDataFinal' => $fimMs,
            'cerObservacao' => mb_substr('CDF do período · '.count($ids).' MTR', 0, 255),
            'parceiroDestinador' => $unidade,
            'cnpjDestinador' => $cnpjDest,
            'tipoCertificadoDestinacao' => 1,
            'listaManifesto' => $lista,
            'listaParceiroGerador' => [],
            'nomeResponsavel' => mb_substr($resp, 0, 255),
        ];

        $credentials = (new SinirCredentialsResolver())->forRecebimento((int)$ids[0]);
        if (!$credentials['ok']) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }
        $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return ['ok' => false, 'message' => 'Autenticação SINIR: '.($tokenResult['error'] ?? 'falha')];
        }
        $token = $tokenResult['token'];

        $ja = $this->codigosJaEmitidos($ids, $manifestos, $token);
        if ($ja['erro'] !== null) {
            return ['ok' => false, 'message' => $ja['erro']];
        }
        if ($ja['codigo'] !== null) {
            return $this->concluirEmissaoCdfLote($ids, $payload, ['mensagem' => 'CDF já constava no SINIR.'], $ja['codigo'], $token);
        }

        $response = $this->gateway->post('emiteCDF', $payload, $token);
        $body = $response['body'] ?? [];
        if (!is_array($body)) {
            return ['ok' => false, 'message' => $response['error'] ?? 'Resposta inválida do SINIR.'];
        }

        $certCodigo = self::extrairCodigoCertificadoNacional($body);
        $criado = self::corpoIndicaCdfCriado($body);
        if ($certCodigo === null && ($criado || $response['ok'])) {
            $certCodigo = $this->localizarCdfExistente((int)$ids[0], $manifestos[0], $token);
        }
        $sucesso = $certCodigo !== null && ($criado || ($response['ok'] && empty($body['erro'])));
        if (!$sucesso) {
            $msg = self::mensagemFalhaCdf($body, (string)($response['error'] ?? ''));
            if (stripos($msg, 'pertence a outro CDF') !== false) {
                $recuperado = $this->codigosJaEmitidos($ids, $manifestos, $token);
                if ($recuperado['codigo'] !== null) {
                    return $this->concluirEmissaoCdfLote($ids, $payload, $body, $recuperado['codigo'], $token);
                }
                if ($recuperado['erro'] !== null) {
                    $msg = $recuperado['erro'];
                }
            }
            if (stripos($msg, 'Responsável não encontrado') !== false) {
                $msg .= ' Nome enviado: '.$resp.'.';
            }
            EntitySinirEnvio::registrar(
                (int)$ids[0],
                EntitySinirEnvio::proximaTentativa((int)$ids[0]),
                'erro',
                $payload,
                $body,
                mb_substr($msg, 0, 500)
            );

            return ['ok' => false, 'message' => $msg];
        }

        return $this->concluirEmissaoCdfLote($ids, $payload, $body, $certCodigo, $token);
    }

    /**
     * @param list<int> $ids
     * @param list<string> $manifestos
     * @return array{codigo:int|string|null,erro:?string}
     */
    private function codigosJaEmitidos(array $ids, array $manifestos, string $token): array
    {
        $porMan = [];
        foreach ($manifestos as $i => $man) {
            $porMan[] = $this->localizarCdfExistente((int)$ids[$i], $man, $token);
        }
        $presentes = array_filter($porMan, static fn ($codigo) => $codigo !== null);
        if ($presentes === []) {
            return ['codigo' => null, 'erro' => null];
        }
        $unicos = [];
        foreach ($presentes as $codigo) {
            $unicos[(string)$codigo] = true;
        }
        $codigo = (string)array_key_first($unicos);
        if (count($unicos) > 1) {
            return [
                'codigo' => null,
                'erro' => 'Esses MTRs já pertencem a certificados diferentes ('.implode(', ', array_keys($unicos)).'). Não dá para reuni-los num CDF novo.',
            ];
        }
        if (count($presentes) !== count($manifestos)) {
            return [
                'codigo' => null,
                'erro' => 'Parte dos MTRs já está no CDF #'.$codigo.'. Retire os que já têm certificado e gere só os demais.',
            ];
        }

        return ['codigo' => $codigo, 'erro' => null];
    }

    /**
     * @param list<int> $ids
     * @param array<string,mixed>|array<int,mixed> $payload
     * @param array<string,mixed> $body
     * @return array{ok:bool,message:string,details?:array<string,mixed>}
     */
    private function concluirEmissaoCdfLote(
        array $ids,
        array $payload,
        array $body,
        int|string $certCodigo,
        string $accessToken
    ): array {
        $codigo = (string)$certCodigo;
        $agora = date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            EntityColeta::update((int)$id, ['sinir_cdf_codigo' => $codigo]);
            EntitySinirEnvio::registrar(
                (int)$id,
                EntitySinirEnvio::proximaTentativa((int)$id),
                'enviado',
                $payload,
                $body,
                'CDF em lote · código '.$codigo
            );
        }

        $pdf = $this->baixarPdfCdf((int)$ids[0], $codigo, $accessToken);
        $primeiro = EntityColeta::getById((int)$ids[0]);
        $path = $primeiro?->cdf_path;
        if ($pdf['ok'] && $path) {
            foreach ($ids as $id) {
                EntityColeta::update((int)$id, [
                    'sinir_cdf_codigo' => $codigo,
                    'cdf_path' => $path,
                    'cdf_tipo' => 'sinir_cdf',
                    'cdf_obtido_em' => $agora,
                ]);
            }
        }

        $qtd = count($ids);
        $msg = 'CDF #'.$codigo.' gerado para '.$qtd.' coleta'.($qtd === 1 ? '' : 's').'. O documento fica retido no portal até liberar.';
        if (!$pdf['ok']) {
            $msg .= ' Falha ao gravar o PDF agora: '.$pdf['message'];
        }

        return [
            'ok' => true,
            'message' => $msg,
            'details' => ['sinir_cdf_codigo' => $codigo, 'coletas' => $ids],
        ];
    }

    /** @return array{ok:bool,message:string} */
    public function baixarPdfCdf(int $coletaId, ?string $cdfCodigo = null, ?string $accessToken = null): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            return ['ok' => false, 'message' => 'Coleta não encontrada.'];
        }

        $codigo = trim((string)($cdfCodigo ?? $coleta->sinir_cdf_codigo ?? ''));
        if ($codigo === '') {
            return ['ok' => false, 'message' => 'Sem código CDF SINIR.'];
        }

        $credentials = (new SinirCredentialsResolver())->forRecebimento($coletaId);
        if (!$credentials['ok']) {
            return ['ok' => false, 'message' => implode(' ', $credentials['errors'])];
        }

        $token = $accessToken;
        if ($token === null || $token === '') {
            $tokenResult = $this->auth->obtainAccessToken(false, $credentials['integration_token']);
            if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
                return ['ok' => false, 'message' => 'Autenticação SINIR falhou.'];
            }
            $token = $tokenResult['token'];
        }

        $manDigitos = preg_replace('/\D/', '', (string)($coleta->sinir_man_numero ?? ''));
        $nacional = strlen($manDigitos) > 10;
        $codigoUrl = rawurlencode($codigo);
        $paths = $nacional
            ? ['downloadCertificado/'.$codigoUrl, 'buscaPdfCdf/'.$codigoUrl]
            : ['buscaPdfCdf/'.$codigoUrl, 'downloadCertificado/'.$codigoUrl];

        $lastMsg = 'PDF do CDF indisponível.';
        foreach ($paths as $path) {
            $response = $this->gateway->postForPdf($path, $token);
            if ($response['ok'] && !empty($response['pdf'])) {
                $saved = ColetaCdfService::gravarPdf(
                    $coletaId,
                    $response['pdf'],
                    'cdf_sinir_'.$codigo.'.pdf',
                    'sinir_cdf',
                    $codigo
                );

                return ['ok' => $saved['ok'], 'message' => $saved['message']];
            }
            $lastMsg = $response['error'] ?? $lastMsg;
            if (is_array($response['body']) && !empty($response['body']['mensagem'])) {
                $lastMsg = (string)$response['body']['mensagem'];
            }
        }

        return ['ok' => false, 'message' => $lastMsg];
    }

    /** @return array{0:int,1:int} Início e fim do dia do recebimento, em epoch ms (America/Sao_Paulo). */
    private static function periodoRecebimentoEpochMs(string $dataRef): array
    {
        $tz = new \DateTimeZone('America/Sao_Paulo');
        try {
            $dia = new \DateTimeImmutable($dataRef, $tz);
        } catch (\Exception) {
            $dia = new \DateTimeImmutable('today', $tz);
        }
        $inicio = $dia->setTime(0, 0, 0);
        $fim = $dia->setTime(23, 59, 59);

        return [(int)$inicio->format('U') * 1000, ((int)$fim->format('U') * 1000) + 999];
    }

    /** @return array{0:int,1:int} Do início do primeiro dia ao fim do último (America/Sao_Paulo). */
    private static function periodoEntreEpochMs(string $inicioRef, string $fimRef): array
    {
        $tz = new \DateTimeZone('America/Sao_Paulo');
        try {
            $inicio = new \DateTimeImmutable($inicioRef, $tz);
        } catch (\Exception) {
            $inicio = new \DateTimeImmutable('today', $tz);
        }
        try {
            $fim = new \DateTimeImmutable($fimRef, $tz);
        } catch (\Exception) {
            $fim = $inicio;
        }
        if ($fim < $inicio) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        return [
            (int)$inicio->setTime(0, 0, 0)->format('U') * 1000,
            ((int)$fim->setTime(23, 59, 59)->format('U') * 1000) + 999,
        ];
    }

    private function codigoCdfNoManifesto(string $manifesto, string $accessToken): int|string|null
    {
        $digitos = preg_replace('/\D/', '', $manifesto);
        if (strlen($digitos) <= 10) {
            return null;
        }
        $response = $this->gateway->get('retornaManifesto/'.rawurlencode($manifesto), $accessToken);
        $body = $response['body'] ?? null;
        if (!is_array($body)) {
            return null;
        }

        return self::extrairCodigoCertificadoNacional($body);
    }

    private function localizarCdfExistente(int $coletaId, string $manifesto, string $tokenDestinador): int|string|null
    {
        $codigo = $this->codigoCdfNoManifesto($manifesto, $tokenDestinador);
        if ($codigo !== null) {
            return $codigo;
        }

        $tokens = [$tokenDestinador];
        $consulta = (new SinirCredentialsResolver())->forConsulta($coletaId);
        if ($consulta['ok'] && !empty($consulta['integration_token'])) {
            $gerador = $this->auth->obtainAccessToken(false, $consulta['integration_token']);
            if ($gerador['ok'] && !empty($gerador['token']) && $gerador['token'] !== $tokenDestinador) {
                $codigo = $this->codigoCdfNoManifesto($manifesto, $gerador['token']);
                if ($codigo !== null) {
                    return $codigo;
                }
                $tokens[] = $gerador['token'];
            }
        }

        foreach ($tokens as $token) {
            $codigo = $this->codigoCdfNaLista($manifesto, $token);
            if ($codigo !== null) {
                return $codigo;
            }
        }

        return null;
    }

    private function codigoCdfNaLista(string $manifesto, string $accessToken): int|string|null
    {
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $fim = new \DateTimeImmutable('today', $tz);
        $inicio = $fim->modify('-29 days');
        $inicioMs = (int)$inicio->setTime(0, 0, 0)->format('U') * 1000;
        $fimMs = ((int)$fim->setTime(23, 59, 59)->format('U') * 1000) + 999;
        $payloads = [
            [
                'cdfDataInicial' => $inicio->format('d/m/Y'),
                'cdfDataFinal' => $fim->format('d/m/Y'),
                'cdfGeradorCodigo' => null,
                'cdfDestinadorCodigo' => null,
            ],
            [
                'cdfDataInicial' => $inicioMs,
                'cdfDataFinal' => $fimMs,
            ],
            [
                'cerDataInicial' => $inicioMs,
                'cerDataFinal' => $fimMs,
            ],
        ];

        foreach ($payloads as $payload) {
            $response = $this->gateway->post('consultaListaCdf', $payload, $accessToken);
            $body = $response['body'] ?? null;
            if (!is_array($body)) {
                continue;
            }
            $codigo = self::codigoCdfDaLista($body, $manifesto);
            if ($codigo !== null) {
                return $codigo;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $body */
    private static function codigoCdfDaLista(array $body, string $manifesto): int|string|null
    {
        $alvo = preg_replace('/\D/', '', $manifesto);
        $achado = null;
        $walk = static function (array $node) use (&$walk, &$achado, $alvo): void {
            if ($achado !== null) {
                return;
            }
            $codigo = null;
            foreach (['numeroCDF', 'cdfNumero', 'cdfCodigo', 'cerCodigo'] as $chave) {
                if (!isset($node[$chave]) || !is_scalar($node[$chave])) {
                    continue;
                }
                $texto = trim((string)$node[$chave]);
                if ($texto !== '' && $texto !== '0') {
                    $codigo = $node[$chave];
                    break;
                }
            }
            $mtrs = $node['listaMtr'] ?? $node['listaManifesto'] ?? null;
            if ($codigo !== null && is_array($mtrs) && self::listaContemManifesto($mtrs, $alvo)) {
                $achado = $codigo;

                return;
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($body);

        return $achado;
    }

    /** @param array<int|string,mixed> $lista */
    private static function listaContemManifesto(array $lista, string $manifestoDigitos): bool
    {
        foreach ($lista as $item) {
            $valor = is_array($item)
                ? ($item['manNumero'] ?? $item['manifestoCodigo'] ?? $item['numero'] ?? null)
                : $item;
            if (!is_scalar($valor)) {
                continue;
            }
            if (preg_replace('/\D/', '', (string)$valor) === $manifestoDigitos) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $body */
    private static function extrairCodigoCertificadoNacional(array $body): int|string|null
    {
        $objeto = $body['objetoResposta'] ?? null;
        if (is_string($objeto)) {
            $decoded = json_decode($objeto, true);
            if (is_array($decoded)) {
                $body['objetoResposta'] = $decoded;
            }
        }

        $chaves = ['numeroCDF', 'cdfNumero', 'cdfCodigo', 'cerCodigo', 'codigoGerado', 'numeroCertificado', 'codigoCertificado'];
        $encontrado = null;
        $walk = static function (array $node) use (&$walk, &$encontrado, $chaves): void {
            if ($encontrado !== null) {
                return;
            }
            foreach ($chaves as $chave) {
                if (!array_key_exists($chave, $node) || !is_scalar($node[$chave])) {
                    continue;
                }
                $codigo = trim((string)$node[$chave]);
                if ($codigo !== '' && $codigo !== '0') {
                    $encontrado = $node[$chave];

                    return;
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($body);

        return $encontrado;
    }

    /** @param array<string,mixed> $body */
    private static function corpoIndicaCdfCriado(array $body): bool
    {
        $achou = false;
        $walk = static function (array $node) use (&$walk, &$achou): void {
            if ($achou) {
                return;
            }
            foreach (['restResponseMensagem', 'retorno', 'mensagem'] as $key) {
                if (!isset($node[$key]) || !is_scalar($node[$key])) {
                    continue;
                }
                $texto = mb_strtoupper(trim((string)$node[$key]), 'UTF-8');
                if (str_contains($texto, 'CERTIFICADO CRIADO')
                    || str_contains($texto, 'CDF GERADO')
                    || str_contains($texto, 'CDF EMITIDO')) {
                    $achou = true;

                    return;
                }
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($body);

        return $achou;
    }

    /** @param array<string,mixed> $body */
    private static function mensagemFalhaCdf(array $body, string $fallback): string
    {
        $aninhadas = [];
        $walk = static function (array $node) use (&$walk, &$aninhadas): void {
            foreach (['restResponseMensagem', 'retorno', 'mensagem'] as $key) {
                if (!isset($node[$key]) || !is_scalar($node[$key])) {
                    continue;
                }
                $texto = trim((string)$node[$key]);
                $normalizado = mb_strtoupper($texto, 'UTF-8');
                if ($texto === '' || in_array($normalizado, ['OK', 'OK!', 'VALIDAÇÃO OK', 'VALIDAÇÃO OK!', 'SUCESSO'], true)
                    || str_contains($normalizado, 'CERTIFICADO CRIADO')) {
                    continue;
                }
                $aninhadas[] = $texto;
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($body);

        $unicas = array_values(array_unique($aninhadas));
        $generica = 'Nao foi possível emitir o Certificado de Destinação (CDF)!';
        $especificas = array_values(array_filter(
            $unicas,
            static fn (string $texto): bool => mb_strtolower($texto) !== mb_strtolower($generica)
        ));
        $msg = $especificas !== [] ? implode(' · ', $especificas) : ($unicas[0] ?? '');
        if ($msg === '') {
            $msg = $fallback !== '' ? $fallback : 'Falha ao emitir CDF no SINIR.';
        }
        if (mb_strtolower($msg) === mb_strtolower($generica)) {
            $msg .= ' O responsável do destinador precisa ser o nome exato da lista de emissão de CDF da unidade no portal SINIR.';
        }

        return $msg;
    }

    /** @param array<string,mixed> $body */
    private static function extrairCodigoCertificado(array $body, string $geradorCnpj): int|string|null
    {
        $lista = $body['listaCertificadoJSON'] ?? null;
        if (!is_array($lista)) {
            return null;
        }
        foreach ($lista as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cnp = preg_replace('/\D/', '', (string)($item['cnpGerador'] ?? ''));
            if ($cnp !== '' && $cnp !== $geradorCnpj) {
                continue;
            }
            if (isset($item['codigo']) && $item['codigo'] !== '') {
                return $item['codigo'];
            }
        }
        $first = $lista[0] ?? null;
        if (is_array($first) && isset($first['codigo'])) {
            return $first['codigo'];
        }

        return null;
    }
}
