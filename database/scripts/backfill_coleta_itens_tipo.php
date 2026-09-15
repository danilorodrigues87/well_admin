<?php
/**
 * Preenche tipo_residuo_id em coleta_itens onde NULL.
 * Uso:
 *   php database/scripts/backfill_coleta_itens_tipo.php --dry-run
 *   php database/scripts/backfill_coleta_itens_tipo.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Helpers\ColetaItemLegacyResolver;
use App\Common\Helpers\TipoResiduoMatcher;
use App\Model\Db\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$db = new Database();

$rows = $db->execute(
    'SELECT ci.id, ci.coleta_id, ci.nome, ci.cod_ibama, l.peso AS legacy_peso,
            cl.nome_fantasia AS cliente_fantasia, p.nome AS plano_nome
     FROM coleta_itens ci
     LEFT JOIN coletas c ON c.id = ci.coleta_id
     LEFT JOIN clientes cl ON cl.id = c.cliente_id
     LEFT JOIN planos p ON p.id = cl.plano_id
     LEFT JOIN well_antigo.coletas l ON l.manifesto = c.legacy_manifesto
     WHERE ci.tipo_residuo_id IS NULL
     LIMIT 50000',
    []
)->fetchAll(PDO::FETCH_ASSOC);

/** @var array<int,list<int>> */
$siblingTiposCache = [];

$updated = 0;
$failed = 0;
$byMethod = [];

foreach ($rows as $row) {
    $match = TipoResiduoMatcher::resolveDetailed(
        (string)($row['cod_ibama'] ?? ''),
        (string)($row['nome'] ?? '')
    );
    if ($match['id'] === null && !empty($row['legacy_peso'])) {
        $match = TipoResiduoMatcher::resolveDetailed('', (string)$row['legacy_peso']);
        if ($match['id'] !== null) {
            $match['method'] = 'legacy_peso_'.$match['method'];
        }
    }
    if ($match['id'] === null) {
        $coletaId = (int)($row['coleta_id'] ?? 0);
        if ($coletaId > 0 && !isset($siblingTiposCache[$coletaId])) {
            $siblingTiposCache[$coletaId] = array_map(
                'intval',
                $db->execute(
                    'SELECT DISTINCT tipo_residuo_id FROM coleta_itens
                     WHERE coleta_id = ? AND tipo_residuo_id IS NOT NULL',
                    [$coletaId]
                )->fetchAll(PDO::FETCH_COLUMN)
            );
        }
        $trunc = ColetaItemLegacyResolver::resolveTruncated((string)$row['nome'], [
            'legacy_peso' => (string)($row['legacy_peso'] ?? ''),
            'cliente_fantasia' => (string)($row['cliente_fantasia'] ?? ''),
            'plano_nome' => (string)($row['plano_nome'] ?? ''),
            'sibling_tipos' => $siblingTiposCache[$coletaId] ?? [],
        ]);
        if ($trunc['id'] !== null) {
            $match = $trunc;
        }
    }
    $tipoId = $match['id'];
    if ($tipoId === null) {
        $failed++;
        continue;
    }
    $byMethod[$match['method']] = ($byMethod[$match['method']] ?? 0) + 1;
    if (!$dryRun) {
        $db->execute('UPDATE coleta_itens SET tipo_residuo_id = ? WHERE id = ?', [$tipoId, (int)$row['id']]);
    }
    $updated++;
}

echo 'coleta_itens: '.$updated.' atualizados, '.$failed.' sem match'.($dryRun ? ' (dry-run)' : '')."\n";
if ($byMethod !== []) {
    echo "Por método:\n";
    arsort($byMethod);
    foreach ($byMethod as $method => $count) {
        echo "  {$method}: {$count}\n";
    }
}

$remaining = (int)$db->execute(
    'SELECT COUNT(*) FROM coleta_itens WHERE tipo_residuo_id IS NULL',
    []
)->fetchColumn();
echo "Pendentes após execução: {$remaining}\n";
