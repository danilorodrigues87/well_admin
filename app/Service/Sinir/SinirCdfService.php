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

        $response = $this->gateway->post('emiteCDF', $payload, $tokenResult['token']);
        $body = $response['body'] ?? [];
        if (!is_array($body)) {
            return ['ok' => false, 'message' => $response['error'] ?? 'Resposta inválida do SINIR.'];
        }

        $certCodigo = $nacional
            ? self::extrairCodigoCertificadoNacional($body)
            : self::extrairCodigoCertificado($body, $geradorCnpj);
        $retornoCodigo = (int)($body['retornoCodigo'] ?? -1);
        $sucessoLegado = !$nacional && $response['ok'] && $retornoCodigo === 0 && $certCodigo !== null;
        $sucessoNacional = $nacional && $response['ok'] && $certCodigo !== null && empty($body['erro']);
        if (!$sucessoLegado && !$sucessoNacional) {
            $msg = self::mensagemFalhaCdf($body, (string)($response['error'] ?? ''));
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

        EntityColeta::update($coletaId, ['sinir_cdf_codigo' => (string)$certCodigo]);

        EntitySinirEnvio::registrar(
            $coletaId,
            EntitySinirEnvio::proximaTentativa($coletaId),
            'enviado',
            $payload,
            $body,
            'CDF emitido · código '.$certCodigo
        );

        $pdf = $this->baixarPdfCdf($coletaId, (string)$certCodigo, $tokenResult['token']);
        if (!$pdf['ok']) {
            return [
                'ok' => true,
                'message' => 'CDF emitido (#'.$certCodigo.'), mas falha ao baixar PDF: '.$pdf['message'],
                'details' => ['sinir_cdf_codigo' => $certCodigo],
            ];
        }

        return [
            'ok' => true,
            'message' => 'CDF SINIR emitido e PDF disponível no portal gerador.',
            'details' => ['sinir_cdf_codigo' => $certCodigo],
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

    /** @param array<string,mixed> $body */
    private static function extrairCodigoCertificadoNacional(array $body): int|string|null
    {
        $objeto = is_array($body['objetoResposta'] ?? null) ? $body['objetoResposta'] : $body;
        $lista = $objeto['listaParceiroGerador'] ?? null;
        if (is_array($lista)) {
            foreach ($lista as $item) {
                if (!is_array($item) || self::itemCdfInvalido($item)) {
                    continue;
                }
                $codigo = $item['numeroCDF'] ?? $item['codigo'] ?? null;
                if ($codigo !== null && $codigo !== '' && (string)$codigo !== '0') {
                    return $codigo;
                }
            }
        }

        $direto = $objeto['codigoGerado'] ?? $objeto['numeroCDF'] ?? null;
        if ($direto !== null && $direto !== '' && (string)$direto !== '0') {
            return $direto;
        }

        return null;
    }

    /** @param array<string,mixed> $item */
    private static function itemCdfInvalido(array $item): bool
    {
        if (array_key_exists('restResponseValido', $item) && $item['restResponseValido'] === false) {
            return true;
        }
        $manifestos = $item['listaManifesto'] ?? [];
        if (!is_array($manifestos)) {
            return false;
        }
        foreach ($manifestos as $man) {
            if (is_array($man) && array_key_exists('restResponseValido', $man) && $man['restResponseValido'] === false) {
                return true;
            }
        }

        return false;
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
                if ($texto === '' || in_array($normalizado, ['OK', 'OK!', 'VALIDAÇÃO OK', 'VALIDAÇÃO OK!', 'SUCESSO'], true)) {
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
