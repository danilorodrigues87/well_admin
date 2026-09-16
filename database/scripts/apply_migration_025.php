<?php
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Environment;

Environment::load(dirname(__DIR__, 2));

$path = dirname(__DIR__).'/migrations/025_cliente_usuarios.sql';
$sql = file_get_contents($path);

$pdo = new PDO(
    'mysql:host='.Environment::get('DB_HOST', 'localhost').';dbname='.Environment::get('DB_NAME', 'well_admin').';charset=utf8mb4',
    (string)Environment::get('DB_USER', 'root'),
    (string)Environment::get('DB_PASS', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, 'SET')) {
        continue;
    }
    $pdo->exec($stmt);
    echo "OK\n";
}

echo "Migration 025 aplicada.\n";
