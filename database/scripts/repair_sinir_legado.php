<?php

/**
 * Marca coletas importadas (legacy_manifesto) como já registradas no SINIR.
 * Use quando o MTR foi lançado manualmente no portal nacional antes da integração.
 *
 * Não altera coletas sem legacy_manifesto (ex.: coleta nova aguardando SINIR).
 *
 * Uso:
 *   php database/scripts/repair_sinir_legado.php
 *   php database/scripts/repair_sinir_legado.php --apply
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$apply = in_array('--apply', $argv ?? [], true);
$db = new Database();

$stmt = $db->execute(
    "SELECT id, numero_mtr, legacy_manifesto, sinir_status, sinir_man_numero, sinir_enviado_em, data_coleta, finalized_at
     FROM coletas
     WHERE legacy_manifesto IS NOT NULL
       AND status = 'finalizada'
       AND (sinir_status IS NULL OR sinir_status != 'enviado')
     ORDER BY numero_mtr ASC"
);

$alterar = 0;
while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    $alterar++;
    $enviadoEm = date('Y-m-d H:i:s');
    foreach (['sinir_enviado_em', 'finalized_at'] as $col) {
        $v = trim((string)($row[$col] ?? ''));
        if ($v !== '' && $v > '1000-01-01') {
            $enviadoEm = substr($v, 0, 19);
            break;
        }
    }
    if ($enviadoEm === date('Y-m-d H:i:s')) {
        $dc = trim((string)($row['data_coleta'] ?? ''));
        if ($dc !== '' && $dc > '1000-01-01') {
            $enviadoEm = $dc.' 12:00:00';
        }
    }
    $man = $row['sinir_man_numero'] ?: (string)(int)$row['numero_mtr'];

    echo sprintf(
        "[%s] #%d MTR %s legacy=%s sinir=%s → enviado (man=%s)\n",
        $apply ? 'APPLY' : 'DRY',
        (int)$row['id'],
        (string)$row['numero_mtr'],
        (string)$row['legacy_manifesto'],
        (string)($row['sinir_status'] ?? 'NULL'),
        $man
    );

    if ($apply) {
        $db->execute(
            'UPDATE coletas SET sinir_status = ?, sinir_enviado_em = ?,
             sinir_man_numero = COALESCE(NULLIF(sinir_man_numero, \'\'), ?)
             WHERE id = ?',
            ['enviado', $enviadoEm, $man, (int)$row['id']]
        );
    }
}

$semLegacy = $db->execute(
    "SELECT id, numero_mtr, sinir_status FROM coletas
     WHERE legacy_manifesto IS NULL AND status = 'finalizada'
     ORDER BY id"
)->fetchAll(\PDO::FETCH_ASSOC);

echo "\nColetas finalizadas SEM legacy_manifesto (não alteradas por este script): ".count($semLegacy)."\n";
foreach ($semLegacy as $r) {
    echo '  #'.(int)$r['id'].' MTR '.($r['numero_mtr'] ?? '—').' sinir='.($r['sinir_status'] ?? 'NULL')."\n";
}

echo "\nTotal legado a marcar como enviado: {$alterar}\n";
if (!$apply && $alterar > 0) {
    echo "Execute com --apply para gravar.\n";
}
