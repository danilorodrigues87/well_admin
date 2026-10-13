<?php

/**
 * Diagnóstico salvarManifestoLote — tenta formatos de payload até obter JSON (não HTML Tomcat).
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

function postRaw(string $url, string $token, string $jsonBody, array $extraHeaders = []): array
{
    $headers = array_merge([
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer '.$token,
    ], $extraHeaders);

    $ch = curl_init($url);
    $respHeaders = '';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonBody,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => 'gzip,deflate',
        CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$respHeaders): int {
            $respHeaders .= $line;

            return strlen($line);
        },
    ]);
    if (defined('CURL_IPRESOLVE_V4')) {
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    if (defined('CURLINFO_HEADER_OUT')) {
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    }
    if (!SinirConfig::sslVerify()) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $reqHeaders = defined('CURLINFO_HEADER_OUT') ? (string)curl_getinfo($ch, CURLINFO_HEADER_OUT) : '';
    curl_close($ch);

    $body = is_string($raw) ? json_decode($raw, true) : null;

    return [
        'status' => $status,
        'content_type' => $ctype,
        'raw' => is_string($raw) ? $raw : '',
        'body' => is_array($body) ? $body : null,
        'request_headers' => $reqHeaders,
        'response_headers' => $respHeaders,
        'is_html' => is_string($raw) && (stripos($ctype, 'html') !== false || str_contains($raw, '<html')),
        'is_json' => is_array($body),
    ];
}

function show(string $label, array $r): void
{
    echo "===== {$label} =====\n";
    echo 'status='.$r['status'].' json='.(!empty($r['is_json']) ? '1' : '0')
        .' html='.(!empty($r['is_html']) ? '1' : '0')
        .' ctype='.$r['content_type']
        .' raw_len='.strlen($r['raw']).PHP_EOL;
    if (!empty($r['is_json'])) {
        echo json_encode($r['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    } elseif ($r['raw'] !== '' && empty($r['is_html'])) {
        echo mb_substr($r['raw'], 0, 500).PHP_EOL;
    } else {
        echo "(sem JSON — possível Tomcat HTML)\n";
    }
    echo PHP_EOL;
}

echo 'baseUrl='.SinirConfig::baseUrl().PHP_EOL;
echo 'unidade_env='.SinirConfig::unidade().PHP_EOL;

$built = (new SinirPayloadBuilder())->buildManifestoLote($coletaId);
if (!$built['ok']) {
    echo "PAYLOAD INVALIDO:\n".implode("\n", $built['errors']).PHP_EOL;
    exit(1);
}
$payload = $built['payload'];
$m0 = $payload['manifestoJSONDtos'][0];

$credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
if (!$credentials['ok']) {
    echo implode("\n", $credentials['errors']).PHP_EOL;
    exit(1);
}

SinirAuthService::clearCache();
$tokenResult = (new SinirAuthService())->obtainAccessToken(true, $credentials['integration_token']);
if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
    echo 'AUTH FALHOU: '.($tokenResult['error'] ?? '').PHP_EOL;
    exit(1);
}
$token = (string)$tokenResult['token'];
$jwt = jwtPayload($token);
$jwtUnidade = null;
if (is_string($jwt['sub'] ?? null) && preg_match('/^(\d+),(\d+)$/', $jwt['sub'], $m)) {
    $jwtUnidade = (int)$m[2];
    echo "jwt.sub={$jwt['sub']} → unidade_token={$jwtUnidade}\n";
}

$urlBase = rtrim(SinirConfig::baseUrl(), '/');
$urlSave = $urlBase.'/salvarManifestoLote';

// Smoke GET
$gw = new SinirGateway(30);
$ping = $gw->get('retornaListaUnidade', $token);
echo 'GET retornaListaUnidade status='.($ping['status'] ?? 0).' ok='.(!empty($ping['ok']) ? '1' : '0').PHP_EOL;

$transU = $jwtUnidade ?: (int)$m0['codUnidadeTransportador'];
$destU = $jwtUnidade ?: (int)$m0['codUnidadeDestinador'];

$itemInt = [
    'quantidade' => 1.0,
    'residuo' => '030308',
    'codigoAcondicionamento' => 8,
    'codigoClasse' => 43,
    'codigoTecnologia' => 43,
    'codigoTipoEstado' => 4,
    'codigoUnidade' => 2,
    'manifestoItemObservacao' => '',
    'manifestoItemCodInterno' => '',
    'manifestoItemCodInternoDestinador' => '',
    'tipoDensidadeValor' => 1,
    'tipoDensidadeUnidade' => 1,
];
$itemStr = [
    'quantidade' => '1.00',
    'residuo' => '030308',
    'codigoAcondicionamento' => '8',
    'codigoClasse' => '43',
    'codigoTecnologia' => '43',
    'codigoTipoEstado' => '4',
    'codigoUnidade' => '2',
    'manifestoItemObservacao' => '',
    'manifestoItemCodInterno' => '',
    'manifestoItemCodInternoDestinador' => '',
    'tipoDensidadeValor' => '1',
    'tipoDensidadeUnidade' => '1',
];

$variants = [];

// A) ints + sem armazenador (atual)
$variants['A_ints_sem_armazenador'] = [
    'manifestoJSONDtos' => [[
        'cnpGerador' => $m0['cnpGerador'],
        'codUnidadeGerador' => (int)$m0['codUnidadeGerador'],
        'cnpTransportador' => $m0['cnpTransportador'],
        'codUnidadeTransportador' => $transU,
        'cnpDestinador' => $m0['cnpDestinador'],
        'codUnidadeDestinador' => $destU,
        'seuCodigoReferencia' => 'dbgA'.time(),
        'manifObservacao' => 'teste',
        'manifGeradorNomeResponsavel' => 'TESTE',
        'manifGeradorCargoResponsavel' => 'TESTE',
        'manifTransportadorNomeMotorista' => 'TESTE',
        'manifTransportadorPlacaVeiculo' => 'ABC1D23',
        'manifTransportadorDataExpedicao' => date('Ymd'),
        'itemManifestoJSONs' => [$itemInt],
    ]],
];

// B) strings (como vários PDFs estaduais)
$variants['B_strings'] = [
    'manifestoJSONDtos' => [[
        'cnpGerador' => (string)$m0['cnpGerador'],
        'codUnidadeGerador' => (string)$m0['codUnidadeGerador'],
        'cnpTransportador' => (string)$m0['cnpTransportador'],
        'codUnidadeTransportador' => (string)$transU,
        'cnpDestinador' => (string)$m0['cnpDestinador'],
        'codUnidadeDestinador' => (string)$destU,
        'seuCodigoReferencia' => 'dbgB'.time(),
        'manifObservacao' => 'teste',
        'manifGeradorNomeResponsavel' => 'TESTE',
        'manifGeradorCargoResponsavel' => 'TESTE',
        'manifTransportadorNomeMotorista' => 'TESTE',
        'manifTransportadorPlacaVeiculo' => 'ABC1D23',
        'manifTransportadorDataExpedicao' => date('Ymd'),
        'itemManifestoJSONs' => [$itemStr],
    ]],
];

// C) com armazenador null explícito
$variants['C_com_null_armazenador'] = [
    'manifestoJSONDtos' => [[
        'cnpGerador' => $m0['cnpGerador'],
        'codUnidadeGerador' => (int)$m0['codUnidadeGerador'],
        'cnpTransportador' => $m0['cnpTransportador'],
        'codUnidadeTransportador' => $transU,
        'cnpDestinador' => $m0['cnpDestinador'],
        'codUnidadeDestinador' => $destU,
        'cnpArmazenador' => null,
        'codUnidadeArmazenador' => null,
        'seuCodigoReferencia' => 'dbgC'.time(),
        'manifObservacao' => 'teste',
        'manifGeradorNomeResponsavel' => 'TESTE',
        'manifGeradorCargoResponsavel' => 'TESTE',
        'manifTransportadorNomeMotorista' => 'TESTE',
        'manifTransportadorPlacaVeiculo' => 'ABC1D23',
        'manifTransportadorDataExpedicao' => date('Ymd'),
        'itemManifestoJSONs' => [$itemInt],
    ]],
];

// D) array raiz (sem wrapper) — alguns legados
$variants['D_array_raiz'] = [[
    'cnpGerador' => $m0['cnpGerador'],
    'codUnidadeGerador' => (int)$m0['codUnidadeGerador'],
    'cnpTransportador' => $m0['cnpTransportador'],
    'codUnidadeTransportador' => $transU,
    'cnpDestinador' => $m0['cnpDestinador'],
    'codUnidadeDestinador' => $destU,
    'seuCodigoReferencia' => 'dbgD'.time(),
    'manifObservacao' => 'teste',
    'manifGeradorNomeResponsavel' => 'TESTE',
    'manifGeradorCargoResponsavel' => 'TESTE',
    'manifTransportadorNomeMotorista' => 'TESTE',
    'manifTransportadorPlacaVeiculo' => 'ABC1D23',
    'manifTransportadorDataExpedicao' => date('Ymd'),
    'itemManifestoJSONs' => [$itemInt],
]];

// E) paths alternativos
$altPaths = [
    'salvarManifestoLote',
    'salvarManifesto',
    'manifesto/salvarManifestoLote',
    'salvarManifestoLote/',
];

foreach ($variants as $name => $body) {
    $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // C precisa de null literais
    if ($name === 'C_com_null_armazenador') {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $r = postRaw($urlSave, $token, $json);
    show($name, $r);
    if (!empty($r['is_json'])) {
        echo "*** SUCESSO DE FORMATO: {$name} ***\n";
        echo "Use este formato no SinirPayloadBuilder.\n";
        exit(0);
    }
}

echo "--- paths alternativos (payload B_strings) ---\n";
$jsonB = json_encode($variants['B_strings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
foreach ($altPaths as $path) {
    $r = postRaw($urlBase.'/'.ltrim($path, '/'), $token, $jsonB);
    show('PATH '.$path, $r);
    if (!empty($r['is_json'])) {
        echo "*** SUCESSO DE PATH: {$path} ***\n";
        exit(0);
    }
}

// F) POST vazio / {} no mesmo path (vê se endpoint existe)
show('POST body vazio', postRaw($urlSave, $token, ''));
show('POST body {}', postRaw($urlSave, $token, '{}'));
show('POST body []', postRaw($urlSave, $token, '[]'));

// G) outro POST autenticado com body (cancelar) — se JSON, POST+JSON ok na API
show(
    'POST cancelarManifesto smoke',
    postRaw($urlBase.'/cancelarManifesto', $token, json_encode([
        'manifestoCodigo' => 1,
        'justificativa' => 'teste diagnostico nao aplicar',
    ], JSON_UNESCAPED_UNICODE))
);

echo "Nenhuma variante retornou JSON. Provável bloqueio do endpoint ou contrato diferente na API nacional.\n";
exit(2);
