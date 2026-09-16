<?php
/**
 * Aplica migrations 021–024 (multitenancy + tipos complementares).
 * Uso: php database/scripts/apply_multitenancy_migrations.php [--dry-run]
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Environment;

Environment::load(dirname(__DIR__, 2));

$dryRun = in_array('--dry-run', $argv ?? [], true);
$files = [
    '021_operadoras.sql',
    '022_operadora_id_tenant.sql',
    '023_operadora_config.sql',
    '024_tipos_residuos_complementares.sql',
];

$host = (string)Environment::get('DB_HOST', 'localhost');
$name = (string)Environment::get('DB_NAME', 'well_admin');
$user = (string)Environment::get('DB_USER', 'root');
$pass = (string)Environment::get('DB_PASS', '');

$pdo = new PDO(
    'mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']
);

echo ($dryRun ? '[DRY-RUN] ' : '')."=== Multitenancy migrations ===\n";

foreach ($files as $file) {
    $path = dirname(__DIR__).'/migrations/'.$file;
    if (!is_readable($path)) {
        fwrite(STDERR, "Arquivo não encontrado: {$path}\n");
        exit(1);
    }
    echo "→ {$file}\n";
    if ($dryRun) {
        continue;
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Falha ao ler {$path}\n");
        exit(1);
    }
    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')
            || str_contains($e->getMessage(), 'already exists')
            || str_contains($e->getMessage(), 'Duplicate key name')) {
            echo "  [skip] já aplicada parcialmente: ".$e->getMessage()."\n";
            continue;
        }
        fwrite(STDERR, "Erro em {$file}: ".$e->getMessage()."\n");
        exit(1);
    }
}

if (!$dryRun) {
    passthru(PHP_BINARY.' '.escapeshellarg(dirname(__DIR__).'/scripts/seed_operadora_well.php'), $code);
    if ($code !== 0) {
        exit($code);
    }
}

echo "Concluído.\n";
