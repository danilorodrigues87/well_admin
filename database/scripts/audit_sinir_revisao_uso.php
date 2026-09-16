<?php
/**
 * Cruza storage/sinir_revisao_sugestoes.csv com uso em coleta_itens e plano_itens.
 * Uso: php database/scripts/audit_sinir_revisao_uso.php [--csv=storage/audit_sinir_revisao_uso.csv]
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$csvOut = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--csv=')) {
        $csvOut = substr($arg, 6);
    }
}

$csvIn = dirname(__DIR__, 2).'/storage/sinir_revisao_sugestoes.csv';
if (!is_readable($csvIn)) {
    fwrite(STDERR, "CSV não encontrado: {$csvIn}\n");
    exit(1);
}

$ids = [];
$meta = [];
$fp = fopen($csvIn, 'r');
$header = fgetcsv($fp, 0, ';');
while (($row = fgetcsv($fp, 0, ';')) !== false) {
    if (count($row) < 4) {
        continue;
    }
    $id = (int)$row[2];
    if ($id <= 0) {
        continue;
    }
    $ids[$id] = true;
    $meta[$id] = [
        'prioridade' => $row[0] ?? '',
        'categoria' => $row[1] ?? '',
        'nome_csv' => $row[3] ?? '',
        'problema' => $row[11] ?? '',
        'sugestao' => $row[12] ?? '',
    ];
}
fclose($fp);

$idList = array_keys($ids);
if ($idList === []) {
    fwrite(STDERR, "Nenhum ID no CSV\n");
    exit(1);
}

$db = new Database();
$placeholders = implode(',', array_fill(0, count($idList), '?'));

// Tipos atuais no banco
$tipos = [];
$stmt = $db->execute(
    "SELECT t.id, t.nome, t.cod_ibama, t.ativo,
            t.tra_codigo, t.tie_codigo, t.tia_codigo, t.cla_codigo, t.uni_codigo,
            c.nome AS classe_nome, g.codigo AS grupo_codigo
     FROM tipos_residuos t
     LEFT JOIN residuo_classes c ON c.id = t.classe_id
     LEFT JOIN residuo_grupos g ON g.id = t.grupo_id
     WHERE t.id IN ({$placeholders})",
    $idList
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tipos[(int)$row['id']] = $row;
}

// Uso em coleta_itens
$coletas = [];
$stmt = $db->execute(
    "SELECT ci.tipo_residuo_id AS id,
            COUNT(*) AS itens,
            COUNT(DISTINCT ci.coleta_id) AS coletas,
            MIN(c.data_coleta) AS primeira,
            MAX(c.data_coleta) AS ultima
     FROM coleta_itens ci
     JOIN coletas c ON c.id = ci.coleta_id
     WHERE ci.tipo_residuo_id IN ({$placeholders})
     GROUP BY ci.tipo_residuo_id",
    $idList
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $coletas[(int)$row['id']] = $row;
}

// Match por nome legado (tipo_residuo_id NULL mas nome igual)
$nomeMatch = [];
$stmt = $db->execute(
    "SELECT t.id, COUNT(*) AS itens, COUNT(DISTINCT ci.coleta_id) AS coletas
     FROM coleta_itens ci
     JOIN tipos_residuos t ON LOWER(TRIM(ci.nome)) = LOWER(TRIM(t.nome))
     WHERE ci.tipo_residuo_id IS NULL AND t.id IN ({$placeholders})
     GROUP BY t.id",
    $idList
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nomeMatch[(int)$row['id']] = $row;
}

// Uso em plano_itens
$planos = [];
$stmt = $db->execute(
    "SELECT pi.tipo_residuo_id AS id,
            COUNT(*) AS plano_itens,
            COUNT(DISTINCT pi.plano_id) AS planos,
            GROUP_CONCAT(DISTINCT p.nome ORDER BY p.nome SEPARATOR ' | ') AS planos_nomes
     FROM plano_itens pi
     JOIN planos p ON p.id = pi.plano_id
     WHERE pi.tipo_residuo_id IN ({$placeholders})
     GROUP BY pi.tipo_residuo_id",
    $idList
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $planos[(int)$row['id']] = $row;
}

// Clientes com plano que inclui tipo
$clientes = [];
$stmt = $db->execute(
    "SELECT pi.tipo_residuo_id AS id, COUNT(DISTINCT cl.id) AS clientes
     FROM plano_itens pi
     JOIN clientes cl ON cl.plano_id = pi.plano_id AND cl.status = 'ativo'
     WHERE pi.tipo_residuo_id IN ({$placeholders})
     GROUP BY pi.tipo_residuo_id",
    $idList
);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $clientes[(int)$row['id']] = (int)$row['clientes'];
}

// Total catálogo — naming patterns
$stmt = $db->execute('SELECT COUNT(*) AS total FROM tipos_residuos WHERE ativo = 1');
$totalAtivos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->execute(
    "SELECT
        SUM(CASE WHEN nome = UPPER(nome) AND nome REGEXP '[a-z]' = 0 THEN 1 ELSE 0 END) AS all_upper,
        SUM(CASE WHEN nome = LOWER(nome) THEN 1 ELSE 0 END) AS all_lower,
        SUM(CASE WHEN nome <> UPPER(nome) AND nome <> LOWER(nome) THEN 1 ELSE 0 END) AS mixed
     FROM tipos_residuos WHERE ativo = 1"
);
$caseStats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== Audit sinir_revisao_sugestoes — uso no banco ===\n\n";
echo "Tipos no CSV: ".count($idList)."\n";
echo "Tipos ativos no catálogo: {$totalAtivos}\n";
echo "Caixa: MAIÚSCULO={$caseStats['all_upper']} | minúsculo={$caseStats['all_lower']} | misto={$caseStats['mixed']}\n\n";

$report = [];
$excluir = 0;
$desativar = 0;
$corrigir = 0;
$manter = 0;

foreach ($idList as $id) {
    $m = $meta[$id] ?? [];
    $t = $tipos[$id] ?? null;
    $c = $coletas[$id] ?? null;
    $nm = $nomeMatch[$id] ?? null;
    $p = $planos[$id] ?? null;

    $itensColeta = (int)($c['itens'] ?? 0) + (int)($nm['itens'] ?? 0);
    $qtdColetas = (int)($c['coletas'] ?? 0) + (int)($nm['coletas'] ?? 0);
    $qtdPlanoItens = (int)($p['plano_itens'] ?? 0);
    $qtdPlanos = (int)($p['planos'] ?? 0);
    $qtdClientes = $clientes[$id] ?? 0;

    if (!$t) {
        $acao = 'nao_existe';
    } elseif ($itensColeta === 0 && $qtdPlanoItens === 0) {
        $acao = $m['categoria'] === 'servico_nao_residuo' || $m['categoria'] === 'registro_teste'
            ? 'excluir'
            : 'desativar_ou_excluir';
        if ($acao === 'excluir') {
            $excluir++;
        } else {
            $desativar++;
        }
    } elseif ($m['categoria'] === 'servico_nao_residuo' || $m['categoria'] === 'registro_teste') {
        $acao = 'revisar_uso_indevido';
        $corrigir++;
    } else {
        $acao = 'corrigir_campos';
        $corrigir++;
    }

    if ($itensColeta > 0 || $qtdPlanoItens > 0) {
        // counted in corrigir already
    }

    $nomeDb = $t['nome'] ?? '(não existe)';
    $caseStyle = 'N/A';
    if ($t) {
        if ($nomeDb === mb_strtoupper($nomeDb, 'UTF-8')) {
            $caseStyle = 'MAIUSCULO';
        } elseif ($nomeDb === mb_strtolower($nomeDb, 'UTF-8')) {
            $caseStyle = 'minusculo';
        } else {
            $caseStyle = 'Title/misto';
        }
    }

    $line = [
        'id' => $id,
        'prioridade' => $m['prioridade'] ?? '',
        'categoria' => $m['categoria'] ?? '',
        'nome_db' => $nomeDb,
        'case_style' => $caseStyle,
        'ativo' => $t['ativo'] ?? '',
        'itens_coleta' => $itensColeta,
        'coletas' => $qtdColetas,
        'plano_itens' => $qtdPlanoItens,
        'planos' => $qtdPlanos,
        'clientes_ativos' => $qtdClientes,
        'planos_nomes' => $p['planos_nomes'] ?? '',
        'acao_sugerida' => $acao,
        'problema' => $m['problema'] ?? '',
        'sugestao_csv' => $m['sugestao'] ?? '',
    ];
    $report[] = $line;

    $flag = str_pad($acao, 22);
    echo "#{$id} [{$m['prioridade']}] {$flag} | coletas:{$qtdColetas} itens:{$itensColeta} | planos:{$qtdPlanos} | {$nomeDb}\n";
    if ($qtdPlanos > 0) {
        echo "   planos: ".($p['planos_nomes'] ?? '')."\n";
    }
}

echo "\n=== Resumo ===\n";
echo "Excluir (sem uso): {$excluir}\n";
echo "Desativar sem uso: {$desativar}\n";
echo "Corrigir/revisar (com uso): {$corrigir}\n";

// Agrupar por acao
$byAcao = [];
foreach ($report as $r) {
    $byAcao[$r['acao_sugerida']][] = $r;
}

echo "\n--- EXCLUIR (sem coleta, sem plano) ---\n";
foreach ($byAcao['excluir'] ?? [] as $r) {
    echo "  #{$r['id']} {$r['nome_db']} — {$r['categoria']}\n";
}

echo "\n--- DESATIVAR/EXCLUIR (sem uso, não serviço) ---\n";
foreach ($byAcao['desativar_ou_excluir'] ?? [] as $r) {
    echo "  #{$r['id']} {$r['nome_db']} — {$r['categoria']}\n";
}

echo "\n--- COM USO — precisa corrigir campos ---\n";
foreach ($report as $r) {
    if ($r['itens_coleta'] > 0 || $r['plano_itens'] > 0) {
        echo "  #{$r['id']} coletas={$r['coletas']} planos={$r['planos']} — {$r['nome_db']} [{$r['categoria']}]\n";
    }
}

if ($csvOut !== null) {
    $dir = dirname($csvOut);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fp = fopen($csvOut, 'w');
    if ($fp) {
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, array_keys($report[0] ?? []), ';');
        foreach ($report as $r) {
            fputcsv($fp, $r, ';');
        }
        fclose($fp);
        echo "\nCSV: {$csvOut}\n";
    }
}
