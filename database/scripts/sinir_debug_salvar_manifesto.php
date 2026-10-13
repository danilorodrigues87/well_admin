<?php

/**
 * Reproduz POST /salvarManifestoLote com diagnóstico (JWT, listas GET, payload mínimo).
 * Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\SinirConfig;
use App\Service\Sinir\SinirAuthService;
use App\Service\Sinir\SinirCredentialsResolver;
use App\Service\Sinir\SinirGateway;
use App\Service\Sinir\SinirPayloadBuilder;

$coletaId = (int)($argv[1] ?? 0);
if ($coletaId <= 0) {
    fwrite(STDERR, "Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}\n");
    exit(1);
}

function jwtPayload(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return [];
    }
    $b64 = strtr($parts[1], '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $json = base64_decode($b64, true);
    $data = is_string($json) ? json_decode($json, true) : null;

    return is_array($data) ? $data : [];
}

function printResponse(string $label, array $response): void
{
    echo "===== {$label} =====\n";
    echo 'status='.($response['status'] ?? 0)
        .' ok='.(!empty($response['ok']) ? '1' : '0')
        .' content_type='.($response['content_type'] ?? '')
        .' raw_len='.strlen((string)($response['raw'] ?? '')).PHP_EOL;
    echo 'error='.($response['error'] ?? '(null)').PHP_EOL;
    if (!empty($response['request_headers'])) {
        echo "--- request headers ---\n".$response['request_headers'].PHP_EOL;
    }
    $raw = (string)($response['raw'] ?? '');
    $ct = (string)($response['content_type'] ?? '');
    if ($raw !== '' && (stripos($ct, 'html') !== false || str_starts_with(ltrim($raw), '<'))) {
        echo "(html omitido)\n";
    } elseif ($raw !== '') {
        echo "--- raw ---\n".mb_substr($raw, 0, 2000).PHP_EOL;
    }
    if (is_array($response['body'] ?? null)) {
        echo "--- body ---\n".json_encode($response['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
    echo PHP_EOL;
}

echo 'SINIR_ENABLED='.(SinirConfig::isEnabled() ? 'true' : 'false').PHP_EOL;
echo 'baseUrl='.SinirConfig::baseUrl().PHP_EOL;
echo 'unidade_env='.SinirConfig::unidade().' cnpj_env='.SinirConfig::cnpj().PHP_EOL;

$built = (new SinirPayloadBuilder())->buildManifestoLote($coletaId);
if (!$built['ok']) {
    echo "PAYLOAD INVALIDO:\n".implode("\n", $built['errors']).PHP_EOL;
    exit(1);
}
$payload = $built['payload'];
echo "--- payload coleta ---\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

$credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
if (!$credentials['ok']) {
    echo "CREDENCIAIS:\n".implode("\n", $credentials['errors']).PHP_EOL;
    exit(1);
}

SinirAuthService::clearCache();
$tokenResult = (new SinirAuthService())->obtainAccessToken(true, $credentials['integration_token']);
if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
    echo 'AUTH FALHOU: '.($tokenResult['error'] ?? 'sem token').PHP_EOL;
    exit(1);
}
$token = (string)$tokenResult['token'];
$jwt = jwtPayload($token);
echo 'access_token jwt.sub='.json_encode($jwt['sub'] ?? null, JSON_UNESCAPED_UNICODE)
    .' tipo='.json_encode($jwt['tipo'] ?? null)
    .' role='.json_encode($jwt['role'] ?? null).PHP_EOL;

$jwtUnidade = null;
if (is_string($jwt['sub'] ?? null) && preg_match('/^(\d+),(\d+)$/', $jwt['sub'], $m)) {
    echo "jwt pessoa/codigo={$m[1]} unidade={$m[2]}\n";
    $jwtUnidade = (int)$m[2];
    if ($jwtUnidade > 0 && $jwtUnidade !== (int)SinirConfig::unidade()) {
        echo "ALERTA: SINIR_UNIDADE no .env=".SinirConfig::unidade()
            ." difere da unidade do token JWT={$jwtUnidade}\n";
    }
}

$gw = new SinirGateway(60);

// Listas oficiais = GET (POST → 405)
printResponse('GET retornaListaResiduo', $gw->get('retornaListaResiduo', $token));
$unidadesResp = $gw->get('retornaListaUnidade', $token);
printResponse('GET retornaListaUnidade', $unidadesResp);

// Payload mínimo ASCII (isola acento / (*) / códigos)
$minimal = [
    'manifestoJSONDtos' => [[
        'cnpGerador' => $payload['manifestoJSONDtos'][0]['cnpGerador'],
        'codUnidadeGerador' => $payload['manifestoJSONDtos'][0]['codUnidadeGerador'],
        'cnpTransportador' => $payload['manifestoJSONDtos'][0]['cnpTransportador'],
        'codUnidadeTransportador' => $jwtUnidade ?: $payload['manifestoJSONDtos'][0]['codUnidadeTransportador'],
        'cnpDestinador' => $payload['manifestoJSONDtos'][0]['cnpDestinador'],
        'codUnidadeDestinador' => $jwtUnidade ?: $payload['manifestoJSONDtos'][0]['codUnidadeDestinador'],
        'seuCodigoReferencia' => 'dbg'.time(),
        'manifObservacao' => 'teste',
        'manifGeradorNomeResponsavel' => 'TESTE',
        'manifGeradorCargoResponsavel' => 'TESTE',
        'manifTransportadorNomeMotorista' => 'TESTE',
        'manifTransportadorPlacaVeiculo' => 'ABC1D23',
        'manifTransportadorDataExpedicao' => date('Ymd'),
        'itemManifestoJSONs' => [[
            'quantidade' => 1,
            'residuo' => '030308',
            'codigoAcondicionamento' => 8,
            'codigoClasse' => 43,
            'codigoTecnologia' => 43,
            'codigoTipoEstado' => 4,
            'codigoUnidade' => 1,
            'manifestoItemObservacao' => 'papel',
            'manifestoItemCodInterno' => '1',
            'manifestoItemCodInternoDestinador' => '',
            'tipoDensidadeValor' => 1,
            'tipoDensidadeUnidade' => 1,
        ]],
    ]],
];
echo "--- payload minimo (unidade transp/dest=".($jwtUnidade ?: 'original').", uni_residuo=1) ---\n";
echo json_encode($minimal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
printResponse('POST salvarManifestoLote MINIMO', $gw->post('salvarManifestoLote', $minimal, $token));

printResponse('POST salvarManifestoLote COLETA', $gw->post('salvarManifestoLote', $payload, $token));

// Variante: unidades do JWT no payload da coleta
if ($jwtUnidade) {
    $payloadJwt = $payload;
    $payloadJwt['manifestoJSONDtos'][0]['codUnidadeTransportador'] = $jwtUnidade;
    $payloadJwt['manifestoJSONDtos'][0]['codUnidadeDestinador'] = $jwtUnidade;
    printResponse(
        'POST salvarManifestoLote COLETA+JWT_UNIDADE',
        $gw->post('salvarManifestoLote', $payloadJwt, $token)
    );
}

exit(0);
