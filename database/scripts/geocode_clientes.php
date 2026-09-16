<?php
/**
 * Geocodifica clientes (endereço → lat/lng via Google Geocoding API).
 *
 * Uso:
 *   php database/scripts/geocode_clientes.php --dry-run
 *   php database/scripts/geocode_clientes.php
 *   php database/scripts/geocode_clientes.php --cliente-id=123
 *   php database/scripts/geocode_clientes.php --limit=50
 *
 * Requer .env: GOOGLE_MAPS_API_KEY
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\MapsConfig;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use App\Service\GoogleMapsService;
use PDO;

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$limit = 500;
$clienteId = 0;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int)substr($arg, 8));
    }
    if (str_starts_with($arg, '--cliente-id=')) {
        $clienteId = (int)substr($arg, 13);
    }
}

if (!MapsConfig::isConfigured()) {
    fwrite(STDERR, "GOOGLE_MAPS_API_KEY não configurada no .env\n");
    exit(1);
}

$db = new Database();
$sql = "SELECT id FROM clientes WHERE status != 'inativo'";
$params = [];
if ($clienteId > 0) {
    $sql .= ' AND id = ?';
    $params[] = $clienteId;
} else {
    $sql .= " AND (geocode_status != 'ok' OR latitude IS NULL OR longitude IS NULL)";
}
$sql .= ' ORDER BY id ASC LIMIT '.$limit;

$stmt = $db->execute($sql, $params);
$ids = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ids[] = (int)$row['id'];
}

echo 'Clientes a geocodificar: '.count($ids).($dryRun ? ' (dry-run)' : '')."\n";

$ok = 0;
$fail = 0;
foreach ($ids as $id) {
    $c = EntityCliente::getById($id);
    if (!$c) {
        continue;
    }
    $endereco = EntityCliente::enderecoCompleto($c);
    echo "#{$id} {$c->nome_fantasia} — {$endereco}\n";
    if ($dryRun) {
        continue;
    }
    if (GoogleMapsService::geocodeCliente($id)) {
        $ok++;
        echo "  OK\n";
    } else {
        $fail++;
        echo "  ERRO\n";
    }
    usleep(200000);
}

if (!$dryRun) {
    echo "Concluído: {$ok} ok, {$fail} erro(s).\n";
}
