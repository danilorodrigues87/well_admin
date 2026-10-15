<?php

/**
 * Diagnóstico consulta + payload recebimento (MMA nacional).
 * Uso: php database/scripts/sinir_debug_consulta_receber.php {coleta_id} [--send-receber]
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Service\Sinir\SinirAuthService;
use App\Service\Sinir\SinirCredentialsResolver;
use App\Service\Sinir\SinirManifestoService;
use App\Service\Sinir\SinirRecebimentoService;
use App\Service\Sinir\SinirTokenInspector;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Db\Database;

$coletaId = (int)($argv[1] ?? 0);
$sendReceber = in_array('--send-receber', $argv, true);

if ($coletaId <= 0) {
    fwrite(STDERR, "Uso: php database/scripts/sinir_debug_consulta_receber.php {coleta_id} [--send-receber]\n");
    exit(1);
}

$coleta = EntityColeta::getById($coletaId);
if (!$coleta) {
    fwrite(STDERR, "Coleta {$coletaId} não encontrada.\n");
    exit(1);
}

echo 'sinir_man_numero='.($coleta->sinir_man_numero ?? 'null').PHP_EOL;
echo 'sinir_codigo_barras='.($coleta->sinir_codigo_barras ?? 'null').PHP_EOL;

$cConsulta = (new SinirCredentialsResolver())->forConsulta($coletaId);
echo 'consulta_ok='.($cConsulta['ok'] ? '1' : '0').' source='.($cConsulta['token_source'] ?? 'null').PHP_EOL;
if (!$cConsulta['ok']) {
    echo implode("\n", $cConsulta['errors']).PHP_EOL;
    exit(1);
}

echo "\n=== consultarColeta (service) ===\n";
SinirAuthService::clearCache();
$resultConsulta = (new SinirManifestoService())->consultarColeta($coletaId);
echo json_encode($resultConsulta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

$stmt = (new Database())->execute(
    'SELECT response_payload FROM sinir_envios
     WHERE coleta_id = ? AND status = ? ORDER BY id DESC LIMIT 1',
    [$coletaId, 'consulta']
);
$rawConsulta = $stmt->fetchColumn();
if (is_string($rawConsulta) && $rawConsulta !== '') {
    $decoded = json_decode($rawConsulta, true);
    echo "\n=== resposta bruta da consulta ===\n";
    echo json_encode(
        is_array($decoded) ? $decoded : $rawConsulta,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ).PHP_EOL;
}

$cReceb = (new SinirCredentialsResolver())->forRecebimento($coletaId);
echo "\nrecebimento_token_source=".($cReceb['token_source'] ?? 'null').PHP_EOL;
echo 'recebimento_destinador_unidade='.($cReceb['destinador_unidade'] ?? 'null').PHP_EOL;
if ($cReceb['ok'] && !empty($cReceb['integration_token'])) {
    $tokenReceb = (new SinirAuthService())->obtainAccessToken(false, $cReceb['integration_token']);
    $tokenUnidade = !empty($tokenReceb['token'])
        ? SinirTokenInspector::unidade((string)$tokenReceb['token'])
        : null;
    echo 'recebimento_token_unidade='.($tokenUnidade ?? 'null').PHP_EOL;
}

$recebimentoService = new SinirRecebimentoService();
$built = $recebimentoService->prepararPayloadRecebimento($coletaId);
echo 'recebimento_payload_format='.($built['format'] ?? '?').PHP_EOL;
if (!$built['ok']) {
    echo implode("\n", $built['errors']).PHP_EOL;
    exit(2);
}
echo json_encode($built['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

if ($sendReceber) {
    echo "\n=== receberColeta (envio real) ===\n";
    SinirAuthService::clearCache();
    $resultRec = $recebimentoService->receberColeta($coletaId, false);
    echo json_encode($resultRec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(!empty($resultRec['ok']) ? 0 : 3);
}

exit(!empty($resultConsulta['ok']) ? 0 : 2);
