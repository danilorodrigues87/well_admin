<?php

/**
 * Diagnóstico salvarManifestoLote (coleta real + probes de schema).
 * Uso: php database/scripts/sinir_debug_salvar_manifesto.php {coleta_id}
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;
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

/** @var array<int,array<string,mixed>> $feamPayload */
$feamPayload = $built['payload'];
echo 'payload_is_list='.(array_is_list($feamPayload) ? '1' : '0').PHP_EOL;

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
echo 'env_units transportador='.$credentials['transportador_unidade']
    .' destinador='.$credentials['destinador_unidade'].PHP_EOL;

$feamAligned = alinharUnidadesComJwt($feamPayload, $token);
$m0 = $feamAligned[0] ?? [];
echo 'feam_aligned units T='.($m0['codUnidadeTransportador'] ?? '?')
    .' D='.($m0['codUnidadeDestinador'] ?? '?')
    .' G='.($m0['codUnidadeGerador'] ?? '?').PHP_EOL;

$gateway = new SinirGateway();

$listaUnidades = $gateway->get('retornaListaUnidade', $token);
echo 'retornaListaUnidade status='.$listaUnidades['status']
    .' ok='.(($listaUnidades['ok'] ?? false) ? '1' : '0').PHP_EOL;
if (is_array($listaUnidades['body'] ?? null)) {
    $unidades = extrairLista($listaUnidades['body']);
    $wellCnpj = preg_replace('/\D/', '', (string)($m0['cnpTransportador'] ?? ''));
    $gerCnpj = preg_replace('/\D/', '', (string)($m0['cnpGerador'] ?? ''));
    foreach ($unidades as $u) {
        if (!is_array($u)) {
            continue;
        }
        $cnp = preg_replace('/\D/', '', (string)($u['cnp'] ?? $u['cpfCnpj'] ?? $u['parCnpj'] ?? ''));
        $cod = $u['codigo'] ?? $u['uniCodigo'] ?? $u['codUnidade'] ?? $u['unidade'] ?? null;
        $nome = $u['nome'] ?? $u['descricao'] ?? $u['razaoSocial'] ?? '';
        if ($cnp === $wellCnpj || $cnp === $gerCnpj || (int)$cod === (int)($unidadeJwt ?? 0)
            || (int)$cod === (int)($m0['codUnidadeGerador'] ?? 0)
            || (int)$cod === 135551 || (int)$cod === 728559) {
            echo '  unidade hit cod='.$cod.' cnp='.$cnp.' nome='.mb_substr((string)$nome, 0, 60).PHP_EOL;
        }
    }
}

echo "\n===== PROBE_FEAM_aligned =====\n";
printProbe($gateway->post('salvarManifestoLote', $feamAligned, $token));

$feamPaper = $feamAligned;
if (isset($feamPaper[0]['itemManifestoJSONs'][0])) {
    $feamPaper[0]['itemManifestoJSONs'] = [$feamPaper[0]['itemManifestoJSONs'][0]];
    // remove strings vazias
    foreach ($feamPaper[0]['itemManifestoJSONs'] as $i => $item) {
        foreach ($item as $k => $v) {
            if ($v === '') {
                unset($feamPaper[0]['itemManifestoJSONs'][$i][$k]);
            }
        }
        $feamPaper[0]['itemManifestoJSONs'][$i]['quantidade'] = (float)$item['quantidade'];
    }
    echo "\n===== PROBE_FEAM_so_papelao_sem_vazios =====\n";
    printProbe($gateway->post('salvarManifestoLote', $feamPaper, $token));
}

$sigor = buildSigorFromFeam($feamAligned[0], $unidadeJwt);
echo "\n===== PROBE_SIGOR_nacional (token=gerador implícito) =====\n";
echo json_encode($sigor, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
printProbe($gateway->post('salvarManifestoLote', $sigor, $token));

echo "\n=== via SinirManifestoService::enviarColeta (force) ===\n";
$result = (new SinirManifestoService())->enviarColeta($coletaId, true);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

$db = new Database();
$stmt = $db->execute(
    'SELECT id, request_payload, response_payload, mensagem_erro, created_at
     FROM sinir_envios WHERE coleta_id = ? ORDER BY id DESC LIMIT 1',
    [$coletaId]
);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (is_array($row)) {
    echo "\n=== último sinir_envios #{$row['id']} ===\n";
    echo 'mensagem_erro: '.($row['mensagem_erro'] ?? '').PHP_EOL;
    $req = json_decode((string)$row['request_payload'], true);
    if (is_array($req) && isset($req[0]) && is_array($req[0])) {
        echo 'request units T='.($req[0]['codUnidadeTransportador'] ?? $req[0]['transportador']['unidade'] ?? '?')
            .' D='.($req[0]['codUnidadeDestinador'] ?? $req[0]['destinador']['unidade'] ?? '?')
            .' G='.($req[0]['codUnidadeGerador'] ?? '(sigor/implícito)').PHP_EOL;
        echo 'request keys: '.implode(', ', array_keys($req[0])).PHP_EOL;
    }
}

exit(!empty($result['ok']) ? 0 : 2);

/** @param array{ok:bool,status:int,body:?array,raw:string,error:?string} $response */
function printProbe(array $response): void
{
    $body = $response['body'] ?? null;
    $raw = (string)($response['raw'] ?? '');
    $isJson = is_array($body);
    $isHtml = str_contains(strtolower((string)($response['content_type'] ?? '')), 'html')
        || str_starts_with(ltrim($raw), '<');
    echo 'status='.$response['status'].' json='.($isJson ? '1' : '0')
        .' html='.($isHtml ? '1' : '0')
        .' raw_len='.strlen($raw).PHP_EOL;
    if ($isJson) {
        echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    } else {
        echo '(sem JSON) '.mb_substr($raw, 0, 300).PHP_EOL;
    }
}

/**
 * @param array<int,array<string,mixed>> $payload
 * @return array<int,array<string,mixed>>
 */
function alinharUnidadesComJwt(array $payload, string $accessToken): array
{
    $unidadeJwt = unidadeFromJwt($accessToken);
    if ($unidadeJwt === null || $unidadeJwt <= 0 || !array_is_list($payload)) {
        return $payload;
    }
    foreach ($payload as $i => $manifesto) {
        if (!is_array($manifesto)) {
            continue;
        }
        $payload[$i]['codUnidadeTransportador'] = $unidadeJwt;
        $cnpT = preg_replace('/\D/', '', (string)($manifesto['cnpTransportador'] ?? ''));
        $cnpD = preg_replace('/\D/', '', (string)($manifesto['cnpDestinador'] ?? ''));
        if ($cnpT !== '' && $cnpT === $cnpD) {
            $payload[$i]['codUnidadeDestinador'] = $unidadeJwt;
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

/**
 * Formato SIGOR/nacional (stallone MtrWSType.gerarLoteMTR): array raiz, gerador = unidade do token.
 *
 * @param array<string,mixed> $feam
 * @return array<int,array<string,mixed>>
 */
function buildSigorFromFeam(array $feam, ?int $unidadeJwt): array
{
    $itens = [];
    foreach ($feam['itemManifestoJSONs'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itens[] = [
            'resCodigoIbama' => (string)($item['residuo'] ?? ''),
            'marQuantidade' => (float)($item['quantidade'] ?? 0),
            'claCodigo' => (int)($item['codigoClasse'] ?? 0),
            'traCodigo' => (int)($item['codigoTecnologia'] ?? 0),
            'tieCodigo' => (int)($item['codigoTipoEstado'] ?? 0),
            'tiaCodigo' => (int)($item['codigoAcondicionamento'] ?? 0),
            'uniCodigo' => (int)($item['codigoUnidade'] ?? 0),
        ];
    }

    $dataExp = (string)($feam['manifTransportadorDataExpedicao'] ?? date('Ymd'));
    // SIGOR tipicamente usa epoch ms; se a API rejeitar, o probe mostra.
    $ts = strtotime(substr($dataExp, 0, 4).'-'.substr($dataExp, 4, 2).'-'.substr($dataExp, 6, 2).' 12:00:00');
    $unidadeT = (int)($unidadeJwt ?: ($feam['codUnidadeTransportador'] ?? 0));
    $unidadeD = (int)($unidadeJwt ?: ($feam['codUnidadeDestinador'] ?? 0));

    return [[
        'nomeResponsavel' => (string)($feam['manifGeradorNomeResponsavel'] ?? 'Responsavel'),
        'seuCodigo' => (string)($feam['seuCodigoReferencia'] ?? ''),
        'dataExpedicao' => $ts ? (int)($ts * 1000) : null,
        'nomeMotorista' => (string)($feam['manifTransportadorNomeMotorista'] ?? ''),
        'placaVeiculo' => (string)($feam['manifTransportadorPlacaVeiculo'] ?? ''),
        'observacoes' => (string)($feam['manifObservacao'] ?? ''),
        'transportador' => [
            'cpfCnpj' => (string)($feam['cnpTransportador'] ?? ''),
            'unidade' => $unidadeT,
        ],
        'destinador' => [
            'cpfCnpj' => (string)($feam['cnpDestinador'] ?? ''),
            'unidade' => $unidadeD,
        ],
        'armazenadorTemporario' => [
            'cpfCnpj' => '99999999999999',
            'unidade' => 99999,
        ],
        'listaManifestoResiduos' => $itens,
    ]];
}

/** @param mixed $body @return list<mixed> */
function extrairLista($body): array
{
    if (!is_array($body)) {
        return [];
    }
    if (array_is_list($body)) {
        return $body;
    }
    foreach (['objetoResposta', 'lista', 'unidades', 'retornaListaUnidade'] as $k) {
        if (isset($body[$k]) && is_array($body[$k]) && array_is_list($body[$k])) {
            return $body[$k];
        }
    }

    return [];
}
