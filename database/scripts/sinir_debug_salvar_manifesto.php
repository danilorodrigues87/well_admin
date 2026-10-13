<?php

/**
 * Diagnóstico salvarManifestoLote (schema MMA/SIGOR + checagem token gerador).
 * Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
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

/** @var array<int,array<string,mixed>> $payload */
$payload = $built['payload'];
$geradorUnidade = (int)($built['gerador_unidade'] ?? 0);

echo 'payload_is_list='.(array_is_list($payload) ? '1' : '0').PHP_EOL;
echo 'schema_keys='.implode(',', array_keys($payload[0] ?? [])).PHP_EOL;
echo 'gerador_unidade_cadastro='.$geradorUnidade.PHP_EOL;
echo 'dataExpedicao_ms='.($payload[0]['dataExpedicao'] ?? '?')
    .' (='.date('Y-m-d H:i', (int)(($payload[0]['dataExpedicao'] ?? 0) / 1000)).')'.PHP_EOL;

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
$unidadeJwt = unidadeFromJwt($token);
echo 'jwt.unidade='.($unidadeJwt ?? 'null').PHP_EOL;
echo 'env/cadastro units T='.$credentials['transportador_unidade']
    .' D='.$credentials['destinador_unidade'].PHP_EOL;

if ($unidadeJwt !== null && $geradorUnidade > 0 && $unidadeJwt !== $geradorUnidade) {
    echo "\n*** BLOQUEIO ESPERADO ***\n";
    echo "Token JWT unidade={$unidadeJwt} ≠ gerador cadastro={$geradorUnidade}.\n";
    echo "A API nacional (MMA/SIGOR) exige Token API WS gerado no portal\n";
    echo "logado como a unidade GERADORA do cliente ({$geradorUnidade}).\n";
    echo "Token só de transportador/destinador → erro 002.\n";
    $coleta = EntityColeta::getById($coletaId);
    $cli = $coleta ? EntityCliente::getById((int)$coleta->cliente_id) : null;
    echo 'Cliente: '.($cli->nome_fantasia ?? $cli->razao_social ?? '?').PHP_EOL;
}

$payload = alinharSigor($payload, $unidadeJwt);
echo 'payload_aligned T='.($payload[0]['transportador']['unidade'] ?? '?')
    .' D='.($payload[0]['destinador']['unidade'] ?? '?').PHP_EOL;
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

$gateway = new SinirGateway();
echo "\n===== POST salvarManifestoLote (SIGOR/MMA) =====\n";
printProbe($gateway->post('salvarManifestoLote', $payload, $token));

echo "\n=== via SinirManifestoService::enviarColeta (force) ===\n";
$result = (new SinirManifestoService())->enviarColeta($coletaId, true);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

exit(!empty($result['ok']) ? 0 : 2);

/** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
function printProbe(array $response): void
{
    $body = $response['body'] ?? null;
    $raw = (string)($response['raw'] ?? '');
    echo 'status='.$response['status'].' json='.(is_array($body) ? '1' : '0')
        .' raw_len='.strlen($raw).PHP_EOL;
    if (is_array($body)) {
        echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    } else {
        echo mb_substr($raw, 0, 500).PHP_EOL;
    }
}

/**
 * @param array<int,array<string,mixed>> $payload
 * @return array<int,array<string,mixed>>
 */
function alinharSigor(array $payload, ?int $unidadeJwt): array
{
    if ($unidadeJwt === null || $unidadeJwt <= 0) {
        return $payload;
    }
    foreach ($payload as $i => $m) {
        if (!is_array($m)) {
            continue;
        }
        if (isset($m['transportador']) && is_array($m['transportador'])) {
            $payload[$i]['transportador']['unidade'] = $unidadeJwt;
            $cnpT = preg_replace('/\D/', '', (string)($m['transportador']['cpfCnpj'] ?? ''));
            $cnpD = preg_replace('/\D/', '', (string)($m['destinador']['cpfCnpj'] ?? ''));
            if ($cnpT !== '' && $cnpT === $cnpD && isset($payload[$i]['destinador'])) {
                $payload[$i]['destinador']['unidade'] = $unidadeJwt;
            }
        }
    }

    return $payload;
}

function unidadeFromJwt(string $jwt): ?int
{
    $parts = explode('.', $jwt);
    if (count($parts) < 2) {
        return null;
    }
    $b64 = strtr($parts[1], '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad > 0) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    $json = base64_decode($b64, true);
    $data = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($data) || !is_string($data['sub'] ?? null)) {
        return null;
    }
    if (!preg_match('/^(\d+),(\d+)$/', $data['sub'], $m)) {
        return null;
    }

    return (int)$m[2];
}
