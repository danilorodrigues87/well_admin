<?php
/**
 * Aplica revisão do catálogo SINIR (storage/sinir_revisao_sugestoes.csv).
 *
 * Uso:
 *   php database/scripts/apply_sinir_revisao_fixes.php --dry-run
 *   php database/scripts/apply_sinir_revisao_fixes.php
 *
 * Ações:
 *   - Remapeia coleta_itens #43 (teste) → #78; remove tipo teste
 *   - Remove 11 tipos sem uso em coleta/plano
 *   - Corrige campos SINIR (IBAMA, tra, tie, tia, cla) nos tipos com uso
 *   - Desativa #58 (serviço de limpeza — legado em 5 coletas)
 *   - Padroniza nomes (sentence case PT)
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** @param string[] $stopwords */
function sentenceCasePt(string $nome, array $stopwords): string
{
    $nome = preg_replace('/\s+/u', ' ', trim($nome)) ?? '';
    if ($nome === '') {
        return '';
    }
    $words = preg_split('/\s+/u', mb_strtolower($nome, 'UTF-8')) ?: [];
    $out = [];
    $n = count($words);
    foreach ($words as $i => $w) {
        if ($i > 0 && $i < $n - 1 && in_array($w, $stopwords, true)) {
            $out[] = $w;
            continue;
        }
        $out[] = mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($w, 1, null, 'UTF-8');
    }

    return implode(' ', $out);
}

$stopwords = ['de', 'da', 'do', 'das', 'dos', 'e', 'ou', 'por', 'com', 'em', 'a', 'o', 'as', 'os', 'na', 'no', 'nas', 'nos', 'para', 'ao', 'aos', 'à', 'às'];

$db = new Database();

$deleteIds = [82, 83, 84, 85, 66, 73, 81, 80, 55, 76, 52];

$fieldFixes = [
    39 => ['cod_ibama' => '15.02.03'],
    33 => ['cod_ibama' => '20.01.33', 'tra_codigo' => 73, 'cla_codigo' => 1],
    69 => ['cod_ibama' => '13.02.05', 'tie_codigo' => 2, 'tia_codigo' => 9, 'tra_codigo' => 73],
    29 => ['cod_ibama' => '20.01.35', 'tra_codigo' => 43],
    16 => ['cla_codigo' => 21, 'tra_codigo' => 73],
    17 => ['cla_codigo' => 35, 'tra_codigo' => 73],
    18 => ['cla_codigo' => 32],
    49 => ['cla_codigo' => 24],
    74 => ['cla_codigo' => 11, 'tra_codigo' => 51],
    54 => ['tra_codigo' => 50],
    79 => ['tra_codigo' => 17],
    65 => ['tra_codigo' => 50],
    48 => ['tra_codigo' => 50],
    75 => ['tra_codigo' => 50],
    35 => ['tie_codigo' => 4, 'tra_codigo' => 73],
    59 => ['cla_codigo' => 43],
    36 => ['tia_codigo' => 9, 'tra_codigo' => 73],
    38 => ['tia_codigo' => 21],
];

$pneuIds = [22, 26, 50, 51, 57, 53];
foreach ($pneuIds as $id) {
    $fieldFixes[$id] = ['tia_codigo' => 13];
}

$manualNames = [
    16 => 'Resíduo sólido do serviço de saúde',
    17 => 'Materiais perfurocortantes ou escarificantes',
    18 => 'Medicamentos vencidos',
    22 => 'Pneus de motocicleta',
    26 => 'Pneus de trator',
    29 => 'Resíduos de equipamentos eletrônicos',
    30 => 'Lâmpadas fluorescentes P',
    31 => 'Lâmpadas fluorescentes M (até 1,20 m)',
    32 => 'Lâmpadas fluorescentes G (maior que 1,20 m)',
    33 => 'Pilhas e baterias',
    35 => 'Estopas contaminadas por óleo',
    36 => 'Vasilhames contaminados por óleo',
    38 => 'Filtros contaminados por óleo',
    39 => 'Filtros de papel',
    47 => 'Embalagens contaminadas por resíduos perigosos',
    48 => 'Massa de gordura e cinzas',
    49 => 'Carcaças, peças anatômicas e vísceras',
    50 => 'Pneus aeronáuticos',
    51 => 'Pneus de automóveis',
    53 => 'Pneus de ônibus',
    54 => 'Resíduos biodegradáveis de cozinha e cantinas',
    57 => 'Pneus de caminhões',
    58 => 'Limpeza de banheiros químicos (serviço — legado)',
    59 => 'Limpeza de caixa separadora de água e óleo',
    63 => 'Sacos de cimento',
    64 => 'Resíduos de triagem de papel e papelão',
    65 => 'Limpeza de caixa de gordura',
    67 => 'Equipamentos fora de uso',
    68 => 'Resíduos contaminados',
    69 => 'Óleo usado',
    70 => 'Sacarias',
    74 => 'Resíduos de construção civil',
    75 => 'Resíduos da indústria de laticínios — materiais impróprios',
    78 => 'Outros resíduos urbanos',
    79 => 'Lodos de tratamento de efluentes urbanos',
];

echo ($dryRun ? '[DRY-RUN] ' : '')."=== apply_sinir_revisao_fixes ===\n";

// Validar deletes
foreach ($deleteIds as $id) {
    $c = (int)$db->execute('SELECT COUNT(*) q FROM coleta_itens WHERE tipo_residuo_id=?', [$id])->fetch(PDO::FETCH_ASSOC)['q'];
    $p = (int)$db->execute('SELECT COUNT(*) q FROM plano_itens WHERE tipo_residuo_id=?', [$id])->fetch(PDO::FETCH_ASSOC)['q'];
    if ($c > 0 || $p > 0) {
        echo "[ERRO] Não pode excluir #{$id}: coletas={$c} planos={$p}\n";
        exit(1);
    }
}

// Remapear #43 → #78
$testeItens = (int)$db->execute('SELECT COUNT(*) q FROM coleta_itens WHERE tipo_residuo_id=43', [])->fetch(PDO::FETCH_ASSOC)['q'];
echo "Remapear coleta_itens #43 teste → #78 ({$testeItens} itens)\n";
if (!$dryRun && $testeItens > 0) {
    $db->execute('UPDATE coleta_itens SET tipo_residuo_id=78 WHERE tipo_residuo_id=43', []);
}

// Campos SINIR
foreach ($fieldFixes as $id => $fields) {
    $sets = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $sets[] = "{$col} = ?";
        $params[] = $val;
    }
    $params[] = $id;
    $sql = 'UPDATE tipos_residuos SET '.implode(', ', $sets).' WHERE id = ?';
    echo "UPDATE campos #{$id}: ".json_encode($fields, JSON_UNESCAPED_UNICODE)."\n";
    if (!$dryRun) {
        $db->execute($sql, $params);
    }
}

// Desativar #58
echo "Desativar #58 (serviço legado)\n";
if (!$dryRun) {
    $db->execute('UPDATE tipos_residuos SET ativo=0 WHERE id=58', []);
}

// Nomes manuais + sentence case para demais
$tipos = $db->execute('SELECT id, nome FROM tipos_residuos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
foreach ($tipos as $t) {
    $id = (int)$t['id'];
    if (in_array($id, $deleteIds, true) || $id === 43) {
        continue;
    }
    $novo = $manualNames[$id] ?? sentenceCasePt((string)$t['nome'], $stopwords);
    if ($novo === $t['nome']) {
        continue;
    }
    echo "Nome #{$id}: \"{$t['nome']}\" → \"{$novo}\"\n";
    if (!$dryRun) {
        $db->execute('UPDATE tipos_residuos SET nome=? WHERE id=?', [$novo, $id]);
    }
}

// Deletes
foreach ($deleteIds as $id) {
    $nome = $db->execute('SELECT nome FROM tipos_residuos WHERE id=?', [$id])->fetch(PDO::FETCH_ASSOC)['nome'] ?? '?';
    echo "DELETE #{$id} {$nome}\n";
    if (!$dryRun) {
        $db->execute('DELETE FROM tipos_residuos WHERE id=?', [$id]);
    }
}

if (!$dryRun) {
    $db->execute('DELETE FROM tipos_residuos WHERE id=43', []);
    echo "DELETE #43 teste\n";
} else {
    echo "DELETE #43 teste (após remapear)\n";
}

echo "\nConcluído".($dryRun ? ' (dry-run — nada alterado)' : '').".\n";
echo "Rode: php database/scripts/sinir_sync_residuos.php --dry-run\n";
echo "Audit: php database/scripts/audit_sinir_revisao_uso.php\n";
