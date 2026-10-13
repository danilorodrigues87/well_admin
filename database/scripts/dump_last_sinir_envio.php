<?php

/**
 * Dump últimos envios SINIR (request/response) para diagnosticar HTTP 400.
 * Uso: php database/scripts/dump_last_sinir_envio.php [coleta_id]
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$db = new Database();
$coletaId = isset($argv[1]) ? (int)$argv[1] : 0;

if ($coletaId > 0) {
    $stmt = $db->execute(
        'SELECT id, coleta_id, tentativa, status, mensagem_erro, request_payload, response_payload, created_at
         FROM sinir_envios WHERE coleta_id = ? ORDER BY id DESC LIMIT 5',
        [$coletaId]
    );
} else {
    $stmt = $db->execute(
        'SELECT id, coleta_id, tentativa, status, mensagem_erro, request_payload, response_payload, created_at
         FROM sinir_envios ORDER BY id DESC LIMIT 5'
    );
}

$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
if ($rows === []) {
    echo "Nenhum registro em sinir_envios.\n";
    exit(0);
}

foreach ($rows as $row) {
    echo str_repeat('=', 72).PHP_EOL;
    echo '#'.$row['id'].' coleta='.$row['coleta_id'].' tentativa='.$row['tentativa']
        .' status='.$row['status'].' em='.$row['created_at'].PHP_EOL;
    echo 'mensagem_erro: '.($row['mensagem_erro'] ?? '(null)').PHP_EOL;
    echo '--- response ---'.PHP_EOL;
    echo selfPretty($row['response_payload']).PHP_EOL;
    echo '--- request (resumo) ---'.PHP_EOL;
    echo selfPretty($row['request_payload'], true).PHP_EOL;
}

function selfPretty(?string $json, bool $compact = false): string
{
    if ($json === null || $json === '') {
        return '(vazio)';
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return mb_substr($json, 0, 2000);
    }
    $m = null;
    if ($compact && isset($data['manifestoJSONDtos'][0]) && is_array($data['manifestoJSONDtos'][0])) {
        $m = $data['manifestoJSONDtos'][0];
    } elseif ($compact && array_is_list($data) && is_array($data[0] ?? null)) {
        $m = $data[0];
    }
    if ($compact && is_array($m)) {
        $itens = $m['itemManifestoJSONs'] ?? $m['listaManifestoResiduos'] ?? [];
        $resumo = [
            'schema' => isset($m['listaManifestoResiduos']) ? 'sigor' : 'feam',
            'cnpGerador' => $m['cnpGerador'] ?? null,
            'codUnidadeGerador' => $m['codUnidadeGerador'] ?? null,
            'cnpTransportador' => $m['cnpTransportador'] ?? ($m['transportador']['cpfCnpj'] ?? null),
            'codUnidadeTransportador' => $m['codUnidadeTransportador'] ?? ($m['transportador']['unidade'] ?? null),
            'cnpDestinador' => $m['cnpDestinador'] ?? ($m['destinador']['cpfCnpj'] ?? null),
            'codUnidadeDestinador' => $m['codUnidadeDestinador'] ?? ($m['destinador']['unidade'] ?? null),
            'placa' => $m['manifTransportadorPlacaVeiculo'] ?? ($m['placaVeiculo'] ?? null),
            'motorista' => $m['manifTransportadorNomeMotorista'] ?? ($m['nomeMotorista'] ?? null),
            'dataExpedicao' => $m['manifTransportadorDataExpedicao'] ?? ($m['dataExpedicao'] ?? null),
            'itens' => array_map(static fn ($i) => [
                'residuo' => $i['residuo'] ?? ($i['resCodigoIbama'] ?? null),
                'qtd' => $i['quantidade'] ?? ($i['marQuantidade'] ?? null),
                'cla' => $i['codigoClasse'] ?? ($i['claCodigo'] ?? null),
                'tra' => $i['codigoTecnologia'] ?? ($i['traCodigo'] ?? null),
                'tie' => $i['codigoTipoEstado'] ?? ($i['tieCodigo'] ?? null),
                'tia' => $i['codigoAcondicionamento'] ?? ($i['tiaCodigo'] ?? null),
                'uni' => $i['codigoUnidade'] ?? ($i['uniCodigo'] ?? null),
            ], is_array($itens) ? $itens : []),
        ];

        return json_encode($resumo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
