<?php

/**
 * Testa salvarManifestoLote com o formato nacional (array raiz).
 * Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Service\Sinir\SinirAuthService;
use App\Service\Sinir\SinirCredentialsResolver;
use App\Service\Sinir\SinirGateway;
use App\Service\Sinir\SinirManifestoService;
use App\Service\Sinir\SinirPayloadBuilder;

$coletaId = (int)($argv[1] ?? 0);
if ($coletaId <= 0) {
    fwrite(STDERR, "Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}\n");
    exit(1);
}

$built = (new SinirPayloadBuilder())->buildManifestoLote($coletaId);
if (!$built['ok']) {
    echo "PAYLOAD INVALIDO:\n".implode("\n", $built['errors']).PHP_EOL;
    exit(1);
}

echo 'payload_is_list='.(array_is_list($built['payload']) ? '1' : '0').PHP_EOL;
echo json_encode($built['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

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

// Reutiliza o mesmo alinhamento JWT do service via reflexão simples: chama enviarColeta
echo "\n=== via SinirManifestoService::enviarColeta (force) ===\n";
$result = (new SinirManifestoService())->enviarColeta($coletaId, true);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

exit(!empty($result['ok']) ? 0 : 2);
