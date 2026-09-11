<?php

/**
 * Etapa 5 — Baixa evidências Cloudinary do legado e grava localmente em WebP.
 *
 * Pré-requisitos:
 * - Bancos well_admin e well_antigo acessíveis (.env)
 * - Coletas já importadas em well_admin (coluna legacy_manifesto)
 * - extension=gd habilitado no PHP
 *
 * Uso: php database/scripts/etl_download_evidencias.php [--dry-run] [--limit=100]
 */

declare(strict_types=1);

ini_set('memory_limit', '512M');
set_time_limit(0);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Service\EvidenceStorageService;

$dryRun = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int)substr($arg, 8));
    }
}

if (!EvidenceStorageService::supportsWebp()) {
    fwrite(STDERR, "Erro: PHP GD com WebP não disponível. Habilite extension=gd no php.ini.\n");
    exit(1);
}

$dbAdmin = new Database();

$sql = 'SELECT c.id AS coleta_id, l.img1, l.img2, l.img3
        FROM coletas c
        INNER JOIN well_antigo.coletas l ON l.manifesto = c.legacy_manifesto
        WHERE (
            (l.img1 LIKE \'http%\')
            OR (l.img2 LIKE \'http%\')
            OR (l.img3 LIKE \'http%\')
        )
        AND NOT EXISTS (
            SELECT 1 FROM coleta_evidencias e
            WHERE e.coleta_id = c.id AND e.ordem = 1
        )
        ORDER BY c.id ASC';

if ($limit > 0) {
    $sql .= ' LIMIT '.(int)$limit;
}

try {
    $rows = $dbAdmin->execute($sql)->fetchAll(\PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fwrite(STDERR, "Aviso: query com JOIN legacy_manifesto falhou — aplique migration 007 e importe coletas antes.\n");
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}

$total = count($rows);
echo "Pendentes: {$total}\n";

$ok = 0;
$fail = 0;
$logFile = dirname(__DIR__, 2).'/storage/logs/evidencias_etl.log';
if (!$dryRun && !is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

foreach ($rows as $row) {
    $coletaId = (int)$row['coleta_id'];
    foreach (['img1' => 1, 'img2' => 2, 'img3' => 3] as $col => $ordem) {
        $url = trim((string)($row[$col] ?? ''));
        if ($url === '' || !str_starts_with($url, 'http')) {
            continue;
        }

        $exists = $dbAdmin->execute(
            'SELECT id FROM coleta_evidencias WHERE coleta_id = ? AND ordem = ? LIMIT 1',
            [$coletaId, $ordem]
        )->fetchColumn();

        if ($exists) {
            continue;
        }

        if ($dryRun) {
            echo "[dry-run] coleta {$coletaId} ordem {$ordem}: {$url}\n";
            continue;
        }

        $saved = EvidenceStorageService::downloadAndSave($coletaId, $ordem, $url);
        if ($saved === null) {
            $fail++;
            $msg = date('Y-m-d H:i:s')." FALHA coleta={$coletaId} ordem={$ordem} url={$url}\n";
            file_put_contents($logFile, $msg, FILE_APPEND);
            fwrite(STDERR, trim($msg)."\n");
            continue;
        }

        try {
            EntityColetaEvidencia::insert([
                'coleta_id' => $coletaId,
                'ordem' => $ordem,
                'arquivo' => $saved['arquivo'],
                'mime' => $saved['mime'],
            ]);
            $ok++;
            if ($ok % 50 === 0) {
                echo "  ... {$ok}/{$total}\n";
            }
        } catch (Throwable $e) {
            $fail++;
            $msg = date('Y-m-d H:i:s')." INSERT coleta={$coletaId} ordem={$ordem}: ".$e->getMessage()."\n";
            file_put_contents($logFile, $msg, FILE_APPEND);
        }

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}

echo "Concluído. Gravadas: {$ok}, falhas: {$fail}\n";
