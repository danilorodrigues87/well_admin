<?php

declare(strict_types=1);

/**
 * Preenche comercial_snapshot_json em contratos ativos/rascunho sem snapshot.
 * Uso: php database/scripts/repair_contrato_comercial_snapshot.php
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;
use App\Service\Contrato\ContratoComercialSnapshot;
use App\Model\Entity\ClienteContrato;

$db = new Database();
$stmt = $db->execute(
    "SELECT id, plano_id, valor_mensal, taxa_adesao FROM clientes_contratos
     WHERE (comercial_snapshot_json IS NULL OR comercial_snapshot_json = '')
       AND status IN ('rascunho','aguardando_assinatura','ativo')"
);
$n = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = (int)$row['id'];
    $snap = ContratoComercialSnapshot::fromPlano(
        (int)$row['plano_id'],
        (float)$row['valor_mensal'],
        isset($row['taxa_adesao']) && $row['taxa_adesao'] !== null ? (float)$row['taxa_adesao'] : null
    );
    ClienteContrato::update($id, [
        'comercial_snapshot_json' => ContratoComercialSnapshot::encode($snap),
    ]);
    echo "Contrato #{$id} snapshot OK\n";
    ++$n;
}
echo "Total: {$n}\n";
