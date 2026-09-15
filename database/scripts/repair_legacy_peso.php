<?php

/**
 * Reprocessa quantidades de coleta_itens importados do legado (parser de peso corrigido).
 *
 * Uso:
 *   php database/scripts/repair_legacy_peso.php --dry-run --manifesto=11915
 *   php database/scripts/repair_legacy_peso.php
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;
use App\Service\LegacyPesoParser;

$dryRun = in_array('--dry-run', $argv, true);
$manifestoFiltro = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--manifesto=')) {
        $manifestoFiltro = (int)substr($arg, 12);
    }
}

$db = new Database();

$sql = 'SELECT c.id, c.legacy_manifesto, l.peso
        FROM coletas c
        INNER JOIN well_antigo.coletas l ON l.manifesto = c.legacy_manifesto
        WHERE c.legacy_manifesto IS NOT NULL';
$params = [];
if ($manifestoFiltro > 0) {
    $sql .= ' AND c.legacy_manifesto = ?';
    $params[] = $manifestoFiltro;
}
$sql .= ' ORDER BY c.legacy_manifesto ASC';

$rows = $db->execute($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
echo ($dryRun ? '[dry-run] ' : '').count($rows)." coleta(s) a reprocessar\n";

$updated = 0;
$skipped = 0;

foreach ($rows as $row) {
    $coletaId = (int)$row['id'];
    $manifesto = (int)$row['legacy_manifesto'];
    $itens = LegacyPesoParser::parse((string)($row['peso'] ?? ''));

    if ($itens === []) {
        $skipped++;
        continue;
    }

    $atuais = $db->execute(
        'SELECT id, nome, quantidade FROM coleta_itens WHERE coleta_id = ? ORDER BY id',
        [$coletaId]
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($dryRun) {
        echo "MTR {$manifesto} (coleta {$coletaId}):\n";
        foreach ($itens as $idx => $item) {
            $old = isset($atuais[$idx]) ? (float)$atuais[$idx]['quantidade'] : null;
            $oldStr = $old !== null ? number_format($old, 3, '.', '') : '—';
            echo '  '.($item['nome'] ?? '').': '.$oldStr.' -> '.number_format($item['quantidade'], 3, '.', '')." kg\n";
        }
        $updated++;
        continue;
    }

    $db->beginTransaction();
    try {
        $db->execute('DELETE FROM coleta_itens WHERE coleta_id = ?', [$coletaId]);

        foreach ($itens as $item) {
            $tipoKey = mb_strtolower(trim($item['nome']));
            $tipo = $db->execute(
                'SELECT t.id, rc.nome AS classe_nome, rg.codigo AS grupo_codigo
                 FROM tipos_residuos t
                 LEFT JOIN residuo_classes rc ON rc.id = t.classe_id
                 LEFT JOIN residuo_grupos rg ON rg.id = t.grupo_id
                 WHERE LOWER(t.nome) = ? OR t.cod_ibama = ?
                 LIMIT 1',
                [$tipoKey, $item['cod_ibama'] ?? '']
            )->fetch(PDO::FETCH_ASSOC);

            $db->execute(
                'INSERT INTO coleta_itens
                    (coleta_id, tipo_residuo_id, nome, classe_nome, grupo_codigo, cod_ibama, quantidade, unidade)
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    $coletaId,
                    $tipo ? (int)$tipo['id'] : null,
                    $item['nome'],
                    $tipo['classe_nome'] ?? null,
                    $tipo['grupo_codigo'] ?? null,
                    $item['cod_ibama'],
                    $item['quantidade'],
                    $item['unidade'],
                ]
            );
        }

        $db->commit();
        $updated++;
    } catch (Throwable $e) {
        $db->rollBack();
        fwrite(STDERR, "Erro MTR {$manifesto}: ".$e->getMessage()."\n");
        $skipped++;
    }
}

echo "Concluído: {$updated} reprocessada(s), {$skipped} ignorada(s).\n";
