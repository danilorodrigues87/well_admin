<?php

/**
 * Corrige finalized_at / sinir_enviado_em / datas zero em coletas (erro #1292 em migrations).
 *
 *   php database/scripts/repair_coletas_invalid_datetimes.php
 *   php database/scripts/repair_coletas_invalid_datetimes.php --apply
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$apply = in_array('--apply', $argv ?? [], true);
$db = new Database();

$oldMode = $db->execute('SELECT @@SESSION.sql_mode AS m')->fetch(\PDO::FETCH_ASSOC)['m'] ?? '';
$db->execute('SET SESSION sql_mode = ?', ['']);

$pairs = [
    ['finalized_at', 'timestamp'],
    ['sinir_enviado_em', 'timestamp'],
    ['data_coleta', 'date'],
    ['data_recebimento', 'date'],
    ['doc_referencia', 'date'],
];

$total = 0;
foreach ($pairs as [$col, $kind]) {
    $threshold = $kind === 'timestamp' ? '1000-01-01 00:00:00' : '1000-01-01';
    $sqlCount = "SELECT COUNT(*) AS qtd FROM coletas
        WHERE `$col` IS NOT NULL AND (`$col` = '0000-00-00 00:00:00' OR `$col` = '0000-00-00' OR `$col` < ?)";
    $qtd = (int)$db->execute($sqlCount, [$threshold])->fetch(\PDO::FETCH_ASSOC)['qtd'];
    if ($qtd > 0) {
        echo "$col: $qtd registro(s) inválido(s)\n";
        $total += $qtd;
        if ($apply) {
            $db->execute(
                "UPDATE coletas SET `$col` = NULL
                 WHERE `$col` IS NOT NULL AND (`$col` = '0000-00-00 00:00:00' OR `$col` = '0000-00-00' OR `$col` < ?)",
                [$threshold]
            );
        }
    }
}

if ($total === 0) {
    echo "Nenhuma data inválida encontrada.\n";
    exit(0);
}

echo $apply
    ? "Corrigidos $total valor(es).\n"
    : "Dry-run: rode com --apply para gravar.\n";

if ($oldMode !== '') {
    $db->execute('SET SESSION sql_mode = ?', [$oldMode]);
}
