<?php
/**
 * Audita match de tipo_residuo_id em coleta_itens sem vínculo.
 * Uso:
 *   php database/scripts/audit_coleta_itens_tipo.php
 *   php database/scripts/audit_coleta_itens_tipo.php --csv=storage/audit_coleta_tipo.csv
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Helpers\ColetaItemLegacyResolver;
use App\Common\Helpers\TipoResiduoMatcher;
use App\Model\Db\Database;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;

$csvPath = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--csv=')) {
        $csvPath = substr($arg, 6);
    }
}

$db = new Database();
$rows = $db->execute(
    'SELECT ci.nome, COUNT(*) AS qtd,
            MIN(ci.id) AS sample_id,
            MIN(c.legacy_manifesto) AS sample_mtr,
            MIN(cl.nome_fantasia) AS sample_cliente,
            MIN(p.nome) AS sample_plano,
            MIN(l.peso) AS sample_peso
     FROM coleta_itens ci
     LEFT JOIN coletas c ON c.id = ci.coleta_id
     LEFT JOIN clientes cl ON cl.id = c.cliente_id
     LEFT JOIN planos p ON p.id = cl.plano_id
     LEFT JOIN well_antigo.coletas l ON l.manifesto = c.legacy_manifesto
     WHERE ci.tipo_residuo_id IS NULL
     GROUP BY ci.nome
     ORDER BY qtd DESC',
    []
)->fetchAll(PDO::FETCH_ASSOC);

$tipoNome = [];
foreach (EntityTipoResiduo::list('1=1', [], '9999') as $t) {
    $tipoNome[$t->id] = $t->nome;
}

$auto = 0;
$revisar = 0;
$semMatch = 0;
$totalItens = 0;
$report = [];

foreach ($rows as $row) {
    $nome = (string)$row['nome'];
    $qtd = (int)$row['qtd'];
    $totalItens += $qtd;
    $match = TipoResiduoMatcher::resolveDetailed('', $nome);
    if ($match['id'] === null && !empty($row['sample_peso'])) {
        $match = TipoResiduoMatcher::resolveDetailed('', (string)$row['sample_peso']);
        if ($match['id'] !== null) {
            $match['method'] = 'legacy_peso_'.$match['method'];
        }
    }
    if ($match['id'] === null) {
        $trunc = ColetaItemLegacyResolver::resolveTruncated($nome, [
            'legacy_peso' => (string)($row['sample_peso'] ?? ''),
            'cliente_fantasia' => (string)($row['sample_cliente'] ?? ''),
            'plano_nome' => (string)($row['sample_plano'] ?? ''),
        ]);
        if ($trunc['id'] !== null) {
            $match = $trunc;
        }
    }
    $id = $match['id'];
    $score = $match['score'];
    $method = $match['method'];

    if ($id === null) {
        $status = 'sem_match';
        $semMatch += $qtd;
    } elseif ($score >= 85 || in_array($method, ['cod_param', 'cod_nome', 'nome_exato', 'material_exato', 'keyword'], true)) {
        $status = 'auto';
        $auto += $qtd;
    } elseif ($score >= 72 && str_starts_with($method, 'trunc_')) {
        $status = 'auto';
        $auto += $qtd;
    } else {
        $status = 'revisar';
        $revisar += $qtd;
    }

    $report[] = [
        'nome' => $nome,
        'qtd' => $qtd,
        'status' => $status,
        'tipo_id' => $id,
        'tipo_nome' => $id ? ($tipoNome[$id] ?? '?') : '',
        'method' => $method,
        'score' => number_format($score, 1, '.', ''),
        'material' => $match['material'] ?? '',
        'sample_mtr' => $row['sample_mtr'] ?? '',
    ];
}

echo "=== Audit coleta_itens (tipo_residuo_id NULL) ===\n";
echo 'Nomes distintos: '.count($rows)."\n";
echo "Itens totais: {$totalItens}\n";
echo "Auto (>=85% ou método forte): {$auto}\n";
echo "Revisar: {$revisar}\n";
echo "Sem match: {$semMatch}\n\n";

foreach ($report as $r) {
    $tipo = $r['tipo_id'] ? "#{$r['tipo_id']} {$r['tipo_nome']}" : '—';
    echo "[{$r['status']}] ({$r['qtd']}x) {$r['nome']}\n";
    echo "  → {$tipo} | {$r['method']} | score {$r['score']}\n";
}

if ($csvPath !== null) {
    $dir = dirname($csvPath);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fp = fopen($csvPath, 'w');
    if ($fp) {
        fputcsv($fp, array_keys($report[0] ?? []), ';');
        foreach ($report as $r) {
            fputcsv($fp, $r, ';');
        }
        fclose($fp);
        echo "\nCSV: {$csvPath}\n";
    }
}
