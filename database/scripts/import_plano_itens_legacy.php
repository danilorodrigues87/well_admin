<?php

/**
 * Importa plano_itens a partir de well_antigo.planos (saldo_residuo + valor_exced).
 * Uso: php database/scripts/import_plano_itens_legacy.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;
use App\Model\Entity\PlanoItem;
use App\Service\LegacyPlanoParser;
use App\Service\PlanoService;

$db = new Database();

$col = $db->execute(
    "SELECT COUNT(*) AS c FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plano_itens'"
)->fetch(PDO::FETCH_ASSOC);

if ((int)($col['c'] ?? 0) === 0) {
    fwrite(STDERR, "Erro: aplique database/migrations/009_plano_itens.sql antes.\n");
    exit(1);
}

$rows = $db->execute(
    'SELECT p.ID AS id, p.saldo_residuo, p.valor_exced
     FROM well_antigo.planos p
     INNER JOIN planos wp ON wp.id = p.ID'
)->fetchAll(PDO::FETCH_ASSOC);

$totalItens = 0;
foreach ($rows as $row) {
    $itens = LegacyPlanoParser::parseParSaldoExced(
        (string)($row['saldo_residuo'] ?? ''),
        (string)($row['valor_exced'] ?? '')
    );
    $itens = PlanoService::enrichItensWithTipoResiduo($itens);
    PlanoItem::replaceForPlano((int)$row['id'], $itens);
    $totalItens += count($itens);
    echo 'Plano #'.$row['id'].': '.count($itens)." itens\n";
}

echo "Concluído. Total itens importados: {$totalItens}\n";
