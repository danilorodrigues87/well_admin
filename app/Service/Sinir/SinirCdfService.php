<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
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

        $refDate = $coleta->data_recebimento ?: $coleta->data_coleta ?: date('Y-m-d');
        $dataYmd = date('Ymd', strtotime($refDate));
        $resp = trim((string)($responsavel ?? ''));
        if ($resp === '') {
            $resp = trim((string)($snapshot->destinador_responsavel ?? 'Responsável técnico'));
        }

        $payload = [
            'dataInicial' => $dataYmd,
            'dataFinal' => $dataYmd,
            'responsavel' => mb_substr($resp, 0, 255),
            'txtObservacoes' => mb_substr('Well · coleta #'.$coletaId.' · rel '.($coleta->numero_relatorio ?? '—'), 0, 255),
            'listaGerador' => [],
            'listaMtr' => [$manifesto],
        ];

        $credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
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

        $retornoCodigo = (int)($body['retornoCodigo'] ?? -1);
        if (!$response['ok'] || $retornoCodigo !== 0) {
            $msg = trim((string)($body['retorno'] ?? ''));
            if ($msg === '') {
                $msg = $response['error'] ?? 'Falha ao emitir CDF no SINIR.';
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

        $certCodigo = self::extrairCodigoCertificado($body, $geradorCnpj);
        if ($certCodigo === null) {
            EntitySinirEnvio::registrar(
                $coletaId,
                EntitySinirEnvio::proximaTentativa($coletaId),
                'erro',
                $payload,
                $body,
                'CDF emitido mas código não retornado.'
            );

            return ['ok' => false, 'message' => 'SINIR não retornou código do certificado.'];
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

        $credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
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

        $path = 'buscaPdfCdf/'.rawurlencode($codigo);
        $response = $this->gateway->postForPdf($path, $token);
        if (!$response['ok'] || empty($response['pdf'])) {
            $msg = $response['error'] ?? 'PDF do CDF indisponível.';
            if (is_array($response['body']) && !empty($response['body']['mensagem'])) {
                $msg = (string)$response['body']['mensagem'];
            }

            return ['ok' => false, 'message' => $msg];
        }

        $saved = ColetaCdfService::gravarPdf(
            $coletaId,
            $response['pdf'],
            'cdf_sinir_'.$codigo.'.pdf',
            'sinir_cdf',
            $codigo
        );

        return ['ok' => $saved['ok'], 'message' => $saved['message']];
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
