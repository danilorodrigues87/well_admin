<?php
/**
 * Sincroniza códigos SINIR em tipos_residuos via listas oficiais da API.
 *
 * Uso:
 *   php database/scripts/sinir_sync_residuos.php --dry-run
 *   php database/scripts/sinir_sync_residuos.php
 *   php database/scripts/sinir_sync_residuos.php --csv=storage/sinir_sync.csv
 *   php database/scripts/sinir_sync_residuos.php --dump-lists=storage/sinir_listas.json
 *   php database/scripts/sinir_sync_residuos.php --force
 *
 * Requer .env: SINIR_INTEGRATION_TOKEN, SINIR_UNIDADE, SINIR_CNPJ
 * Dev local (XAMPP): SINIR_SSL_VERIFY=false se curl falhar com erro de certificado.
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Helpers\IbamaCodigoHelper;
use App\Model\Db\Database;
use App\Service\Sinir\SinirCatalogService;
use App\Service\Sinir\SinirService;

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);
$onlyEmpty = !in_array('--all', $argv, true);
$csvPath = null;
$dumpPath = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--csv=')) {
        $csvPath = substr($arg, 6);
    }
    if (str_starts_with($arg, '--dump-lists=')) {
        $dumpPath = substr($arg, 13);
    }
}

echo '=== SINIR sync tipos_residuos ==='.PHP_EOL;
echo ($dryRun ? '[DRY-RUN] ' : '').($force ? '[FORCE] ' : '').($onlyEmpty ? '[somente vazios] ' : '').PHP_EOL;

$auditBefore = SinirService::auditCatalogoResiduos();
echo 'Antes: '.$auditBefore['com_sinir'].'/'.$auditBefore['total'].' com mapeamento SINIR completo'.PHP_EOL;

$catalogService = new SinirCatalogService();
$loaded = $catalogService->loadCatalog();
if (!$loaded['ok']) {
    echo '[FALHA] '.$loaded['error'].PHP_EOL;
    exit(1);
}
if (!empty($loaded['error'])) {
    echo '[AVISO] '.$loaded['error'].PHP_EOL;
}

$catalog = $loaded['catalog'];
echo 'Listas: '
    .count($catalog['residuos'] ?? []).' resíduos, '
    .count($catalog['tecnologias'] ?? []).' tecnologias, '
    .count($catalog['estados'] ?? []).' estados, '
    .count($catalog['acondicionamentos'] ?? []).' acondicionamentos, '
    .count($catalog['classes'] ?? []).' classes, '
    .count($catalog['unidades'] ?? []).' unidades'
    .PHP_EOL;

if ($dumpPath !== null) {
    $dir = dirname($dumpPath);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        $dumpPath,
        json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    echo 'Listas salvas em: '.$dumpPath.PHP_EOL;
}

$db = new Database();
$tipos = $db->execute(
    'SELECT t.id, t.nome, t.cod_ibama, t.classe_id, t.grupo_id,
            t.tra_codigo, t.tie_codigo, t.tia_codigo, t.cla_codigo, t.uni_codigo,
            c.nome AS classe_nome, g.codigo AS grupo_codigo
     FROM tipos_residuos t
     LEFT JOIN residuo_classes c ON c.id = t.classe_id
     LEFT JOIN residuo_grupos g ON g.id = t.grupo_id
     WHERE t.ativo = 1
     ORDER BY t.nome',
    []
)->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
$updated = 0;
$skipped = 0;
$incomplete = 0;

foreach ($tipos as $tipo) {
    $tipoId = (int)$tipo['id'];
    $hasAll = selfHasSinirCodes($tipo);

    if ($onlyEmpty && $hasAll && !$force) {
        $skipped++;
        continue;
    }

    $suggestion = $catalogService->suggestForTipo($tipo);
    $complete = (int)($suggestion['tra_codigo'] ?? 0) > 0
        && (int)($suggestion['tie_codigo'] ?? 0) > 0
        && (int)($suggestion['tia_codigo'] ?? 0) > 0
        && (int)($suggestion['cla_codigo'] ?? 0) > 0
        && (int)($suggestion['uni_codigo'] ?? 0) > 0;

    if (!$complete) {
        $incomplete++;
    }

    $newCodIbama = $suggestion['ibama_sinir'] ?? IbamaCodigoHelper::normalize((string)$tipo['cod_ibama']);
    $row = [
        'id' => $tipoId,
        'nome' => (string)$tipo['nome'],
        'cod_ibama_atual' => IbamaCodigoHelper::normalize((string)$tipo['cod_ibama']),
        'cod_ibama_novo' => $newCodIbama,
        'ibama_descricao' => (string)($suggestion['ibama_descricao'] ?? ''),
        'tra_codigo' => $suggestion['tra_codigo'],
        'tie_codigo' => $suggestion['tie_codigo'],
        'tia_codigo' => $suggestion['tia_codigo'],
        'cla_codigo' => $suggestion['cla_codigo'],
        'uni_codigo' => $suggestion['uni_codigo'],
        'status' => $complete ? 'ok' : 'incompleto',
        'notes' => implode('; ', $suggestion['notes']),
    ];
    $rows[] = $row;

    if (!$dryRun && $complete) {
        $db->execute(
            'UPDATE tipos_residuos SET
                cod_ibama = ?,
                tra_codigo = ?,
                tie_codigo = ?,
                tia_codigo = ?,
                cla_codigo = ?,
                uni_codigo = ?
             WHERE id = ?',
            [
                $newCodIbama,
                $suggestion['tra_codigo'],
                $suggestion['tie_codigo'],
                $suggestion['tia_codigo'],
                $suggestion['cla_codigo'],
                $suggestion['uni_codigo'],
                $tipoId,
            ]
        );
        $updated++;
    }
}

if ($csvPath !== null) {
    $dir = dirname($csvPath);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fp = fopen($csvPath, 'w');
    if ($fp !== false) {
        if ($rows !== []) {
            fputcsv($fp, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($fp, $row, ';');
            }
        }
        fclose($fp);
        echo 'CSV: '.$csvPath.PHP_EOL;
    }
}

echo PHP_EOL.'--- Resumo ---'.PHP_EOL;
echo 'Tipos analisados: '.count($rows).PHP_EOL;
echo 'Ignorados (já completos): '.$skipped.PHP_EOL;
echo 'Sugestões incompletas: '.$incomplete.PHP_EOL;
if ($dryRun) {
    echo 'Atualizações (simuladas): '.count(array_filter($rows, fn ($r) => $r['status'] === 'ok')).PHP_EOL;
} else {
    echo 'Atualizados no banco: '.$updated.PHP_EOL;
    $auditAfter = SinirService::auditCatalogoResiduos();
    echo 'Depois: '.$auditAfter['com_sinir'].'/'.$auditAfter['total'].' com mapeamento SINIR completo'.PHP_EOL;
}

foreach (array_slice($rows, 0, 10) as $preview) {
    echo sprintf(
        '  #%d %s | IBAMA %s → tra=%s tie=%s tia=%s cla=%s uni=%s [%s]%s',
        $preview['id'],
        $preview['nome'],
        $preview['cod_ibama_novo'] ?: $preview['cod_ibama_atual'],
        $preview['tra_codigo'] ?? '-',
        $preview['tie_codigo'] ?? '-',
        $preview['tia_codigo'] ?? '-',
        $preview['cla_codigo'] ?? '-',
        $preview['uni_codigo'] ?? '-',
        $preview['status'],
        $preview['notes'] !== '' ? ' — '.$preview['notes'] : ''
    ).PHP_EOL;
}
if (count($rows) > 10) {
    echo '  ... +'.(count($rows) - 10).' linhas (use --csv= para ver tudo)'.PHP_EOL;
}

exit($incomplete > 0 && !$dryRun ? 2 : 0);

/** @param array<string,mixed> $tipo */
function selfHasSinirCodes(array $tipo): bool
{
    return (int)($tipo['tra_codigo'] ?? 0) > 0
        && (int)($tipo['tie_codigo'] ?? 0) > 0
        && (int)($tipo['tia_codigo'] ?? 0) > 0
        && (int)($tipo['cla_codigo'] ?? 0) > 0
        && (int)($tipo['uni_codigo'] ?? 0) > 0;
}
