<?php

/**
 * Corrige labels UTF-8 em modulos e remapeia tipos_residuos a partir de well_antigo.
 * Executar: php database/migrations/005b_fix_encoding_remap.php
 */

require __DIR__.'/../../vendor/autoload.php';

use App\Common\Environment;
use App\Model\Db\Database;

Environment::load(__DIR__.'/../..');

$db = new Database();

$labels = [
    'coleta_nova' => 'Lançar Coleta',
    'coletas' => 'Coletas / MTR',
    'agendamentos' => 'Agendamentos',
    'funcionarios' => 'Funcionários',
    'veiculos' => 'Veículos',
    'tipos_residuos' => 'Tipos de Resíduos',
    'relatorios' => 'Relatórios',
    'usuarios' => 'Usuários',
    'funcoes' => 'Funções e Módulos',
    'residuo_classes' => 'Classes de Resíduo',
    'residuo_grupos' => 'Grupos de Resíduo',
];

foreach ($labels as $slug => $label) {
    $db->execute('UPDATE modulos SET label = ? WHERE slug = ?', [$label, $slug]);
}
$db->execute("UPDATE modulos SET grupo = 'Operação' WHERE slug IN ('coletas','coleta_nova','agendamentos')");

echo "Labels modulos corrigidos.\n";

$classNames = [
    1 => 'Classe I — Saúde',
    2 => 'Classe I — Industrial',
    3 => 'Classe I — Eletrônicos',
    4 => 'Classe II',
    5 => 'Classe I — Geral / Perigoso',
];
foreach ($classNames as $id => $nome) {
    $db->execute('UPDATE residuo_classes SET nome = ? WHERE id = ?', [$nome, $id]);
}
echo "Nomes das classes corrigidos.\n";

function mapClasseId(string $classe): int
{
    $c = trim($classe);
    return match ($c) {
        'Classe I (Saúde)' => 1,
        'Classe I (Industrial)' => 2,
        'Classe I (Eletrônicos)' => 3,
        'Classe II', 'Classe II (Industrial)', 'CLASSE II', 'II' => 4,
        'I - PERIGOSO', 'CLASSE I' => 5,
        default => 4,
    };
}

$db->execute('DELETE FROM residuo_grupos');
$db->execute('ALTER TABLE residuo_grupos AUTO_INCREMENT = 1');

$stmt = $db->execute(
    'SELECT DISTINCT TRIM(classe) AS classe, TRIM(grupo) AS codigo
     FROM well_antigo.tipos_de_residuos
     WHERE TRIM(grupo) <> ""'
);
$inserted = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $classeId = mapClasseId((string)$row['classe']);
    $codigo = (string)$row['codigo'];
    $db->execute(
        'INSERT IGNORE INTO residuo_grupos (classe_id, codigo, nome) VALUES (?, ?, ?)',
        [$classeId, $codigo, 'Grupo '.$codigo]
    );
    $inserted++;
}
echo "Grupos recriados: {$inserted} pares.\n";

$stmt = $db->execute(
    'SELECT t.id, TRIM(l.classe) AS classe, TRIM(l.grupo) AS codigo
     FROM tipos_residuos t
     INNER JOIN well_antigo.tipos_de_residuos l ON l.id = t.id'
);
$mapped = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $classeId = mapClasseId((string)$row['classe']);
    $codigo = (string)$row['codigo'];
    $g = $db->execute(
        'SELECT id, classe_id FROM residuo_grupos WHERE classe_id = ? AND codigo = ? LIMIT 1',
        [$classeId, $codigo]
    )->fetch(PDO::FETCH_ASSOC);
    if ($g) {
        $db->execute(
            'UPDATE tipos_residuos SET classe_id = ?, grupo_id = ? WHERE id = ?',
            [(int)$g['classe_id'], (int)$g['id'], (int)$row['id']]
        );
        $mapped++;
    }
}
echo "Tipos remapeados: {$mapped}/68.\n";

$check = $db->execute(
    "SELECT m.slug, m.label FROM modulos m WHERE m.slug = 'funcoes'"
)->fetch(PDO::FETCH_ASSOC);
echo 'Verificação funcoes: '.$check['label']."\n";
