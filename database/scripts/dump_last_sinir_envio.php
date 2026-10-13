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
    if ($compact && isset($data['manifestoJSONDtos'][0])) {
        $m = $data['manifestoJSONDtos'][0];
        $itens = $m['itemManifestoJSONs'] ?? [];
        $resumo = [
            'cnpGerador' => $m['cnpGerador'] ?? null,
            'codUnidadeGerador' => $m['codUnidadeGerador'] ?? null,
            'cnpTransportador' => $m['cnpTransportador'] ?? null,
            'codUnidadeTransportador' => $m['codUnidadeTransportador'] ?? null,
            'cnpDestinador' => $m['cnpDestinador'] ?? null,
            'codUnidadeDestinador' => $m['codUnidadeDestinador'] ?? null,
            'placa' => $m['manifTransportadorPlacaVeiculo'] ?? null,
            'motorista' => $m['manifTransportadorNomeMotorista'] ?? null,
            'dataExpedicao' => $m['manifTransportadorDataExpedicao'] ?? null,
            'itens' => array_map(static fn ($i) => [
                'residuo' => $i['residuo'] ?? null,
                'qtd' => $i['quantidade'] ?? null,
                'cla' => $i['codigoClasse'] ?? null,
                'tra' => $i['codigoTecnologia'] ?? null,
                'tie' => $i['codigoTipoEstado'] ?? null,
                'tia' => $i['codigoAcondicionamento'] ?? null,
                'uni' => $i['codigoUnidade'] ?? null,
            ], $itens),
        ];

        return json_encode($resumo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
