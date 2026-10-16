<?php

declare(strict_types=1);

/**
 * Aplica migration 055 (liberação de MTR oficial e CDF no portal).
 * Uso: php database/scripts/apply_migration_055.php
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$db = new Database();
$path = dirname(__DIR__).'/migrations/055_coletas_liberacao_documentos.sql';
echo "Aplicando 055_coletas_liberacao_documentos.sql...\n";
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
