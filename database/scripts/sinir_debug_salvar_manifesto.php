<?php

/**
 * Diagnóstico emissão MTR (token do gerador + schema MMA).
 * Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Service\Sinir\SinirAuthService;
use App\Service\Sinir\SinirCredentialsResolver;
use App\Service\Sinir\SinirManifestoService;
use App\Service\Sinir\SinirPayloadBuilder;

$coletaId = (int)($argv[1] ?? 0);
if ($coletaId <= 0) {
    fwrite(STDERR, "Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}\n");
    exit(1);
}

$creds = (new SinirCredentialsResolver())->forEmissaoManifesto($coletaId);
echo 'emissao_ok='.($creds['ok'] ? '1' : '0').PHP_EOL;
echo 'token_source='.($creds['token_source'] ?? 'null').PHP_EOL;
echo 'gerador_unidade='.($creds['gerador_unidade'] ?? 'null').PHP_EOL;
echo 'transportador_unidade='.($creds['transportador_unidade'] ?? 'null').PHP_EOL;
echo 'destinador_unidade='.($creds['destinador_unidade'] ?? 'null').PHP_EOL;
if (!$creds['ok']) {
    echo "CREDENCIAIS:\n".implode("\n", $creds['errors']).PHP_EOL;
    exit(1);
}

$built = (new SinirPayloadBuilder())->buildManifestoLote($coletaId);
if (!$built['ok']) {
    echo "PAYLOAD INVALIDO:\n".implode("\n", $built['errors']).PHP_EOL;
    exit(1);
}

echo 'payload_keys='.implode(',', array_keys($built['payload'][0] ?? [])).PHP_EOL;
echo json_encode($built['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

SinirAuthService::clearCache();
$tokenResult = (new SinirAuthService())->obtainAccessToken(true, $creds['integration_token']);
if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
    echo 'AUTH FALHOU: '.($tokenResult['error'] ?? '').PHP_EOL;
    exit(1);
}

$jwt = (string)$tokenResult['token'];
$parts = explode('.', $jwt);
$unidadeJwt = null;
if (count($parts) >= 2) {
    $b64 = strtr($parts[1], '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $data = json_decode((string)base64_decode($b64, true), true);
    if (is_array($data) && preg_match('/^(\d+),(\d+)$/', (string)($data['sub'] ?? ''), $m)) {
        $unidadeJwt = (int)$m[2];
    }
}
echo 'jwt.unidade='.($unidadeJwt ?? 'null').PHP_EOL;
if ($unidadeJwt !== null && (int)$creds['gerador_unidade'] !== $unidadeJwt) {
    echo "*** JWT ≠ gerador cadastrado — confira o token do cliente ***\n";
}

echo "\n=== via SinirManifestoService::enviarColeta (force) ===\n";
$result = (new SinirManifestoService())->enviarColeta($coletaId, true);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

exit(!empty($result['ok']) ? 0 : 2);
