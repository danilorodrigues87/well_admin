<?php
/**
 * Preenche tipo_residuo_id em plano_itens (antes de remover nome/cod_ibama).
 * Uso: php database/scripts/backfill_plano_itens_tipo.php [--dry-run]
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Common\Helpers\IbamaCodigoHelper;
use App\Model\Db\Database;
use PDO;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$db = new Database();

function normNome(string $nome): string
{
    $nome = mb_strtolower(trim($nome));
    $nome = preg_replace('/\s+/u', ' ', $nome) ?? $nome;
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);

    return preg_replace('/[^a-z0-9 ]/', ' ', is_string($trans) ? $trans : $nome) ?? $nome;
}

/** @var array<string,int> */
$byCod = [];
/** @var array<string,int> */
$byNomeNorm = [];
/** @var array<string,int> */
$byNomeExact = [];

$tipos = $db->execute('SELECT id, nome, cod_ibama FROM tipos_residuos WHERE ativo = 1', [])->fetchAll(PDO::FETCH_ASSOC);
foreach ($tipos as $t) {
    $id = (int)$t['id'];
    $byNomeExact[mb_strtolower(trim((string)$t['nome']))] = $id;
    $byNomeNorm[normNome((string)$t['nome'])] = $id;
    $cod = IbamaCodigoHelper::normalize((string)($t['cod_ibama'] ?? ''));
    if ($cod !== '') {
        $byCod[$cod] = $id;
    }
}

$rows = $db->execute(
    'SELECT id, plano_id, tipo_residuo_id, nome, cod_ibama FROM plano_itens ORDER BY plano_id, id',
    []
)->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$skipped = 0;
$failed = [];

/** @var list<array{id:int,nome:string,norm:string}> */
$tipoList = [];
foreach ($tipos as $t) {
    $tipoList[] = [
        'id' => (int)$t['id'],
        'nome' => (string)$t['nome'],
        'norm' => normNome((string)$t['nome']),
    ];
}

foreach ($rows as $row) {
    if (!empty($row['tipo_residuo_id'])) {
        $skipped++;
        continue;
    }
    $tipoId = null;
    $cod = IbamaCodigoHelper::normalize((string)($row['cod_ibama'] ?? ''));
    if ($cod !== '' && isset($byCod[$cod])) {
        $tipoId = $byCod[$cod];
    }
    $nome = trim((string)($row['nome'] ?? ''));
    if ($tipoId === null && $nome !== '') {
        $tipoId = $byNomeExact[mb_strtolower($nome)] ?? $byNomeNorm[normNome($nome)] ?? null;
    }
    if ($tipoId === null && $nome !== '') {
        $clean = preg_replace('/^\d{2}(?:[.\s]\d{2}){0,2}-?\s*/u', '', $nome) ?? $nome;
        $clean = trim($clean);
        $tipoId = $byNomeExact[mb_strtolower($clean)] ?? $byNomeNorm[normNome($clean)] ?? null;
    }
    if ($tipoId === null && $nome !== '') {
        $pn = normNome($nome);
        $best = 0;
        foreach ($tipoList as $t) {
            if ($pn === $t['norm']) {
                $tipoId = $t['id'];
                break;
            }
            similar_text($pn, $t['norm'], $pct);
            if ($pct > $best && $pct >= 55) {
                $best = $pct;
                $tipoId = $t['id'];
            }
        }
    }
    if ($tipoId === null && $nome !== '') {
        $pn = normNome($nome);
        $keywords = [
            ['estopa', 35], ['medicament', 18], ['perfuro', 17], ['escarif', 17],
            ['filtros contamin', 38], ['filtro contamin', 38], ['filtros papel', 39],
            ['vasilhame', 36], ['terra contamin', 37], ['metal', 34],
            ['sucata ferros', 44], ['sucata', 44], ['pneu', 53],
        ];
        foreach ($keywords as [$kw, $tid]) {
            if (str_contains($pn, $kw)) {
                $tipoId = $tid;
                break;
            }
        }
    }
    if ($tipoId === null) {
        $failed[] = $row;
        continue;
    }
    echo "plano_itens id={$row['id']} plano={$row['plano_id']} \"{$nome}\" -> tipo {$tipoId}\n";
    if (!$dryRun) {
        $db->execute('UPDATE plano_itens SET tipo_residuo_id = ? WHERE id = ?', [$tipoId, (int)$row['id']]);
    }
    $updated++;
}

echo "\nAtualizados: {$updated}, já tinham tipo: {$skipped}, sem match: ".count($failed).($dryRun ? ' (dry-run)' : '')."\n";
if ($failed !== []) {
    echo "Sem match (revisar manualmente):\n";
    foreach ($failed as $f) {
        echo "  id={$f['id']} plano={$f['plano_id']} nome=\"{$f['nome']}\"\n";
    }
    exit(count($failed) > 0 ? 1 : 0);
}
