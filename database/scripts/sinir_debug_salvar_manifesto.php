<?php

/**
 * Reproduz POST /salvarManifestoLote com dump completo (headers + body).
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

echo 'SINIR_ENABLED='.(SinirConfig::isEnabled() ? 'true' : 'false').PHP_EOL;
echo 'baseUrl='.SinirConfig::baseUrl().PHP_EOL;
echo 'unidade_env='.SinirConfig::unidade().' cnpj_env='.SinirConfig::cnpj().PHP_EOL;

$built = (new SinirPayloadBuilder())->buildManifestoLote($coletaId);
if (!$built['ok']) {
    echo "PAYLOAD INVALIDO:\n".implode("\n", $built['errors']).PHP_EOL;
    exit(1);
}

$payload = $built['payload'];
echo "--- payload ---\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

$credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
if (!$credentials['ok']) {
    echo "CREDENCIAIS:\n".implode("\n", $credentials['errors']).PHP_EOL;
    exit(1);
}
echo 'token_len='.strlen((string)$credentials['integration_token'])
    .' transp_unidade='.$credentials['transportador_unidade']
    .' dest_unidade='.$credentials['destinador_unidade'].PHP_EOL;

$tokenResult = (new SinirAuthService())->obtainAccessToken(false, $credentials['integration_token']);
if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
    echo 'AUTH FALHOU: '.($tokenResult['error'] ?? 'sem token').PHP_EOL;
    echo json_encode($tokenResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(1);
}
echo 'access_token_len='.strlen((string)$tokenResult['token']).PHP_EOL;

$response = (new SinirGateway(60))->post('salvarManifestoLote', $payload, $tokenResult['token']);

echo "--- response meta ---\n";
echo 'status='.($response['status'] ?? 0)
    .' ok='.(!empty($response['ok']) ? '1' : '0')
    .' bytes_req='.($response['request_bytes'] ?? 0)
    .' content_type='.($response['content_type'] ?? '').PHP_EOL;
echo 'primary_ip='.($response['primary_ip'] ?? '').PHP_EOL;
echo 'error='.($response['error'] ?? '(null)').PHP_EOL;

echo "--- response headers ---\n";
echo trim((string)($response['response_headers'] ?? '')).PHP_EOL;

echo "--- response raw (".strlen((string)($response['raw'] ?? ''))." bytes) ---\n";
echo (string)($response['raw'] ?? '').PHP_EOL;

echo "--- response body decoded ---\n";
echo json_encode($response['body'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

exit(!empty($response['ok']) ? 0 : 2);
