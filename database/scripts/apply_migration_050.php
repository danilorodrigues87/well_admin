<?php

declare(strict_types=1);

/**
 * Aplica migration 050 e seed dos modelos de contrato.
 * Uso: php database/scripts/apply_migration_050.php
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$db = new Database();
$path = dirname(__DIR__).'/migrations/050_contrato_modelos.sql';
echo "Aplicando 050_contrato_modelos.sql...\n";
$sql = file_get_contents($path);
if ($sql === false) {
    fwrite(STDERR, "Arquivo não encontrado.\n");
    exit(1);
}
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

include __DIR__.'/seed_contrato_modelos.php';
