<?php

/**
 * Aplica migrations 027–033 (suporte, termos, ajuda, contratos).
 * Uso: php database/scripts/apply_migrations_027_033.php
 */

require __DIR__.'/../../includes/app.php';

use App\Model\Db\Database;

$files = [
    '027_suporte_chamados.sql',
    '028_usuarios_termos.sql',
    '029_cliente_usuarios_termos.sql',
    '030_help_e_modulos.sql',
    '031_planos_contrato_clausulas.sql',
    '032_clientes_contratos.sql',
    '033_modulo_contratos.sql',
];

$db = new Database();
$dir = __DIR__.'/../migrations';

foreach ($files as $file) {
    $path = $dir.'/'.$file;
    if (!is_file($path)) {
        echo "Arquivo não encontrado: {$file}\n";
        continue;
    }
    echo "Aplicando {$file}...\n";
    $sql = file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $db->execute($stmt);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists')) {
                echo "  (já aplicado) {$msg}\n";
                continue;
            }
            throw $e;
        }
    }
}

echo "Concluído.\n";
