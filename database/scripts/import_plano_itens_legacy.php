<?php

/**
 * Importa plano_itens a partir de well_antigo.planos (saldo_residuo + valor_exced).
 * Uso: php database/scripts/import_plano_itens_legacy.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Helpers\TipoResiduoMatcher;
use App\Model\Db\Database;
use PDO;
use App\Model\Entity\PlanoItem;
use App\Service\LegacyPlanoParser;

$db = new Database();

$rows = $db->execute(
    'SELECT p.ID AS id, p.saldo_residuo, p.valor_exced
     FROM well_antigo.planos p
     INNER JOIN planos wp ON wp.id = p.ID'
)->fetchAll(PDO::FETCH_ASSOC);

$totalItens = 0;
foreach ($rows as $row) {
    $parsed = LegacyPlanoParser::parseParSaldoExced(
        (string)($row['saldo_residuo'] ?? ''),
        (string)($row['valor_exced'] ?? '')
    );
    $itens = [];
    foreach ($parsed as $item) {
        $tipoId = TipoResiduoMatcher::resolve($item['cod_ibama'] ?? null, $item['nome'] ?? '');
        if ($tipoId === null) {
            echo '  [skip] plano #'.$row['id'].' — sem tipo: '.($item['nome'] ?? '')."\n";
            continue;
        }
        $itens[] = [
            'tipo_residuo_id' => $tipoId,
            'saldo_incluso' => $item['saldo_incluso'],
            'unidade' => $item['unidade'],
            'valor_excedente' => $item['valor_excedente'],
            'saldo_compartilhado' => 0,
        ];
    }
    PlanoItem::replaceForPlano((int)$row['id'], $itens);
    $totalItens += count($itens);
    echo 'Plano #'.$row['id'].': '.count($itens)." itens\n";
}

echo "Concluído. Total itens importados: {$totalItens}\n";
