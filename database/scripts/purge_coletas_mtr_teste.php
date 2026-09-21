<?php

/**
 * Remove coletas de teste por faixa de numero_mtr (filhos CASCADE: itens, snapshot, evidências, sinir_envios).
 * Reajusta coleta_sequencia.ultimo_mtr para MAX(numero_mtr) restante.
 *
 * Uso:
 *   php database/scripts/purge_coletas_mtr_teste.php --dry-run
 *   php database/scripts/purge_coletas_mtr_teste.php --from=11917 --to=11921
 *   php database/scripts/purge_coletas_mtr_teste.php --from=11917 --to=11921 --operadora-id=1
 *
 * Ver docs/MIGRACAO_DADOS.md — cutover / MTRs de teste.
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$dryRun = in_array('--dry-run', $argv, true);
$from = 11917;
$to = 11921;
$operadoraId = 1;

foreach ($argv as $arg) {
    if (preg_match('/^--from=(\d+)$/', $arg, $m)) {
        $from = (int)$m[1];
    }
    if (preg_match('/^--to=(\d+)$/', $arg, $m)) {
        $to = (int)$m[1];
    }
    if (preg_match('/^--operadora-id=(\d+)$/', $arg, $m)) {
        $operadoraId = (int)$m[1];
    }
}

if ($from > $to) {
    fwrite(STDERR, "Intervalo inválido: --from={$from} --to={$to}\n");
    exit(1);
}

$db = new Database();

$rows = $db->execute(
    'SELECT id, numero_mtr, status, legacy_manifesto, sinir_status, created_at
     FROM coletas
     WHERE operadora_id = ? AND numero_mtr IS NOT NULL AND numero_mtr BETWEEN ? AND ?
     ORDER BY numero_mtr',
    [$operadoraId, $from, $to]
)->fetchAll(PDO::FETCH_ASSOC);

$prefix = $dryRun ? '[dry-run] ' : '';
echo "{$prefix}Operadora {$operadoraId} — MTR {$from}..{$to} — encontradas: ".count($rows)."\n";

if ($rows === []) {
    exit(0);
}

foreach ($rows as $row) {
    $leg = $row['legacy_manifesto'] !== null ? ' legado='.$row['legacy_manifesto'] : ' (sem legacy_manifesto)';
    echo "  - id={$row['id']} MTR={$row['numero_mtr']} status={$row['status']}{$leg} sinir=".($row['sinir_status'] ?? '—')."\n";
    if ($row['legacy_manifesto'] !== null) {
        fwrite(STDERR, "    AVISO: coleta com legacy_manifesto — confirme que não é dado real antes de apagar.\n");
    }
}

if ($dryRun) {
    $max = $db->execute(
        'SELECT COALESCE(MAX(numero_mtr), 0) FROM coletas WHERE operadora_id = ? AND numero_mtr NOT BETWEEN ? AND ?',
        [$operadoraId, $from, $to]
    )->fetchColumn();
    echo "{$prefix}Após purge, coleta_sequencia.ultimo_mtr seria ajustado para {$max}.\n";
    exit(0);
}

$ids = array_map(static fn (array $r): int => (int)$r['id'], $rows);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $db->beginTransaction();
    $db->execute(
        'DELETE FROM coletas WHERE id IN ('.$placeholders.')',
        $ids
    );
    $maxMtr = (int)$db->execute(
        'SELECT COALESCE(MAX(numero_mtr), 0) FROM coletas WHERE operadora_id = ?',
        [$operadoraId]
    )->fetchColumn();
    $db->execute(
        'INSERT INTO coleta_sequencia (operadora_id, ultimo_mtr) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE ultimo_mtr = VALUES(ultimo_mtr)',
        [$operadoraId, $maxMtr]
    );
    $db->commit();
    echo "Removidas ".count($ids)." coleta(s). coleta_sequencia.ultimo_mtr = {$maxMtr}.\n";
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Erro: '.$e->getMessage()."\n");
    exit(1);
}
