<?php

/**
 * Aplica migration 034 e seed de artigos da Central de Ajuda.
 * Uso: php database/scripts/apply_migration_034.php
 */

require __DIR__.'/../../includes/app.php';

use App\Model\Db\Database;

$db = new Database();
$path = __DIR__.'/../migrations/034_help_modulos_detalhado.sql';
echo "Aplicando 034_help_modulos_detalhado.sql...\n";
$sql = file_get_contents($path);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--')) {
        continue;
    }
    try {
        $db->execute($stmt);
    } catch (Throwable $e) {
        echo 'Aviso: '.$e->getMessage()."\n";
    }
}

include __DIR__.'/seed_help_modulos.php';
