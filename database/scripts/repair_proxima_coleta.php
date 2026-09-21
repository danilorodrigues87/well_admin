<?php

/**
 * Recalcula clientes.proxima_coleta a partir da última coleta finalizada + COLETA_DIAS_PROXIMA.
 * Uso: php database/scripts/repair_proxima_coleta.php [--apply]
 * Sem --apply: apenas dry-run.
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$apply = in_array('--apply', $argv ?? [], true);
$dias = max(1, (int)(getenv('COLETA_DIAS_PROXIMA') ?: 7));

$db = new Database();
$stmt = $db->execute(
    "SELECT c.id, c.nome_fantasia, c.proxima_coleta,
            (SELECT MAX(col.data_coleta) FROM coletas col
             WHERE col.cliente_id = c.id AND col.status = 'finalizada') AS ultima_coleta
     FROM clientes c
     WHERE c.status = 'ativo'
     ORDER BY c.id"
);

$alterados = 0;
while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $ultima = $row['ultima_coleta'] ?? null;
    if (!$ultima) {
        continue;
    }
    $nova = date('Y-m-d', strtotime((string)$ultima.' +'.$dias.' days'));
    $atual = $row['proxima_coleta'] ?? null;
    if ($atual === $nova) {
        continue;
    }
    $alterados++;
    echo sprintf(
        "[%s] #%d %s: %s → %s\n",
        $apply ? 'APPLY' : 'DRY',
        (int)$row['id'],
        (string)$row['nome_fantasia'],
        $atual ?: 'NULL',
        $nova
    );
    if ($apply) {
        $db->execute('UPDATE clientes SET proxima_coleta = ? WHERE id = ?', [$nova, (int)$row['id']]);
    }
}

echo "Total a ajustar: {$alterados}\n";
