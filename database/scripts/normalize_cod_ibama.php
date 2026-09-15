<?php
/**
 * Normaliza cod_ibama em tipos_residuos e plano_itens para XX.XX.XX.
 * Uso: php database/scripts/normalize_cod_ibama.php [--dry-run]
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Common\Helpers\IbamaCodigoHelper;
use App\Model\Db\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$db = new Database();

foreach (['tipos_residuos'] as $table) {
    $stmt = $db->execute("SELECT id, cod_ibama FROM {$table} WHERE cod_ibama IS NOT NULL AND cod_ibama != ''", []);
    $updated = 0;
    $cleared = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $old = (string)$row['cod_ibama'];
        $new = IbamaCodigoHelper::normalize($old);
        if ($new === $old) {
            continue;
        }
        if ($new === '') {
            echo "[{$table}] id={$row['id']}: \"{$old}\" -> NULL\n";
            if (!$dryRun) {
                $db->execute("UPDATE {$table} SET cod_ibama = NULL WHERE id = ?", [(int)$row['id']]);
            }
            $cleared++;
        } else {
            echo "[{$table}] id={$row['id']}: \"{$old}\" -> \"{$new}\"\n";
            if (!$dryRun) {
                $db->execute("UPDATE {$table} SET cod_ibama = ? WHERE id = ?", [$new, (int)$row['id']]);
            }
            $updated++;
        }
    }
    echo "{$table}: {$updated} normalizados, {$cleared} limpos".($dryRun ? ' (dry-run)' : '')."\n";
}
