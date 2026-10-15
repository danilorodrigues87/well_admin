<?php

declare(strict_types=1);

/**
 * Aplica migration 054 (responsável técnico do CDF no destinador).
 * Uso: php database/scripts/apply_migration_054.php
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$db = new Database();
$path = dirname(__DIR__).'/migrations/054_destinadores_responsavel_cdf.sql';
echo "Aplicando 054_destinadores_responsavel_cdf.sql...\n";
$sql = file_get_contents($path);
if ($sql === false) {
    fwrite(STDERR, "Arquivo não encontrado.\n");
    exit(1);
}
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    $stmt = preg_replace('/^--.*$/m', '', $stmt) ?? $stmt;
    $stmt = trim($stmt);
    if ($stmt === '') {
        continue;
    }
    try {
        $db->execute($stmt);
        echo "OK\n";
    } catch (Throwable $e) {
        echo 'Aviso: '.$e->getMessage()."\n";
    }
}

echo "Concluído.\n";
