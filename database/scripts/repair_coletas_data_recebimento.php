<?php

/**
 * Preenche data_recebimento ausente com data_coleta (coletas legado/importadas).
 *
 * Uso:
 *   php database/scripts/repair_coletas_data_recebimento.php [--dry-run]
 *
 * Ver docs/MIGRACAO_DADOS.md — seção "Coletas — data_recebimento".
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$dryRun = in_array('--dry-run', $argv, true);
$db = new Database();

$sql = "SELECT id, numero_mtr, data_coleta, data_recebimento, situacao_recebimento, legacy_manifesto
        FROM coletas
        WHERE data_recebimento IS NULL
          AND data_coleta IS NOT NULL
          AND status = 'finalizada'";

$rows = $db->execute($sql)->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);

echo ($dryRun ? '[dry-run] ' : '')."Coletas sem data_recebimento: {$total}\n";

if ($total === 0) {
    exit(0);
}

$updated = 0;
foreach ($rows as $row) {
    $id = (int)$row['id'];
    $dataColeta = (string)$row['data_coleta'];
    $label = 'MTR '.($row['numero_mtr'] ?? $id);
    if (!empty($row['legacy_manifesto'])) {
        $label .= ' (legado '.$row['legacy_manifesto'].')';
    }

    if ($dryRun) {
        echo "  {$label}: data_recebimento ← {$dataColeta}\n";
        $updated++;
        continue;
    }

    $db->execute(
        'UPDATE coletas SET data_recebimento = ? WHERE id = ? AND data_recebimento IS NULL',
        [$dataColeta, $id]
    );
    $updated++;
}

echo ($dryRun ? 'Simulado: ' : 'Atualizado: ')."{$updated} registro(s).\n";
